<?php

require_once "seguridad.php";

evitarCache();

verificarRol(['jurado']);
if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {
    header("Location: ingresar_estudiante.php");
    exit();
}

// AQUÍ CONTINÚA TODO TU CÓDIGO ACTUAL
include("config/conexion.php");


/* =========================================================
   1. VERIFICAR SESIÓN DEL JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit();

}


$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    header("Location: login.php");
    exit();

}


/* =========================================================
   2. VERIFICAR ESTUDIANTE SELECCIONADO
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {

    header("Location: ingresar_estudiante.php");
    exit();

}


$idEstudiante =
    (int)$_SESSION['estudiante_votando_id'];


/* =========================================================
   3. VERIFICAR ELECCIÓN DE LA SESIÓN
========================================================= */

if (
    !isset($_SESSION['eleccion_votante_id']) ||
    (int)$_SESSION['eleccion_votante_id'] <= 0
) {

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header("Location: ingresar_estudiante.php");
    exit();

}


$idEleccion =
    (int)$_SESSION['eleccion_votante_id'];


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$eleccion = null;

$estudiante = null;

$yaVoto = false;

$votacionRealizada = false;

$cargos = [];


/* =========================================================
   4. OBTENER ESTUDIANTE REAL DESDE MYSQL
========================================================= */

$stmt = $conn->prepare("

    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso

    FROM usuarios

    WHERE id = ?

    AND LOWER(TRIM(rol)) = 'estudiante'

    LIMIT 1

");


if (!$stmt) {

    die(
        "No se pudo consultar el estudiante."
    );

}


$stmt->bind_param(
    "i",
    $idEstudiante
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header("Location: ingresar_estudiante.php");
    exit();

}


$estudiante =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   5. OBTENER ELECCIÓN EXACTA DE LA SESIÓN
========================================================= */

$stmt = $conn->prepare("

    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado

    FROM elecciones

    WHERE id = ?

    LIMIT 1

");


if (!$stmt) {

    die(
        "No se pudo consultar la elección."
    );

}


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header("Location: ingresar_estudiante.php");
    exit();

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   6. VERIFICAR QUE LA ELECCIÓN SIGA ABIERTA
========================================================= */

if (
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) !== "abierta"
) {

    $mensaje =
        "La elección está cerrada. No se puede registrar la votación.";

    $tipoMensaje =
        "danger";

}


/* =========================================================
   7. COMPROBAR SI EL ESTUDIANTE YA VOTÓ
========================================================= */

if (
    $eleccion &&
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) === "abierta"
) {


    $stmt = $conn->prepare("

        SELECT
            id

        FROM votos

        WHERE id_usuario = ?

        AND id_eleccion = ?

        LIMIT 1

    ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEstudiante,
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        if (
            $resultado->num_rows > 0
        ) {

            $yaVoto = true;

            $mensaje =
                "Este estudiante ya realizó su votación en esta elección.";

            $tipoMensaje =
                "danger";

        }


        $stmt->close();

    }

}


/* =========================================================
   8. PROCESAR VOTACIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['votar']) &&
    $eleccion &&
    !$yaVoto &&
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) === "abierta"
) {


    /* =====================================================
       OBTENER SELECCIONES
    ===================================================== */

    $selecciones =
        $_POST['candidato'] ?? [];


    if (
        !is_array($selecciones)
    ) {

        $selecciones = [];

    }


    /* =====================================================
       OBTENER CARGOS VÁLIDOS
    ===================================================== */

    $cargosValidos = [];


    $stmtCargos = $conn->prepare("

        SELECT
            c.id,
            c.nombre_cargo

        FROM cargos c

        INNER JOIN eleccion_cargos ec

            ON ec.id_cargo = c.id

        WHERE ec.id_eleccion = ?

        ORDER BY c.id ASC

    ");


    if (!$stmtCargos) {

        $mensaje =
            "No se pudieron consultar los cargos.";

        $tipoMensaje =
            "danger";

    } else {


        $stmtCargos->bind_param(
            "i",
            $idEleccion
        );


        $stmtCargos->execute();


        $resultadoCargos =
            $stmtCargos->get_result();


        while (
            $cargo =
            $resultadoCargos->fetch_assoc()
        ) {

            $cargosValidos[
                (int)$cargo['id']
            ] =
                $cargo['nombre_cargo'];

        }


        $stmtCargos->close();


        /* =================================================
           VERIFICAR QUE EXISTAN CARGOS
        ================================================= */

        if (
            count($cargosValidos) === 0
        ) {

            $mensaje =
                "Esta elección no tiene cargos configurados.";

            $tipoMensaje =
                "warning";

        } else {


            /* =============================================
               VERIFICAR QUE TODOS LOS CARGOS
               TENGAN UNA SELECCIÓN
            ============================================= */

            $faltanCargos = [];


            foreach (
                $cargosValidos
                as $idCargo => $nombreCargo
            ) {

                if (
                    !isset(
                        $selecciones[$idCargo]
                    ) ||
                    (int)$selecciones[$idCargo] <= 0
                ) {

                    $faltanCargos[] =
                        $nombreCargo;

                }

            }


            if (
                count($faltanCargos) > 0
            ) {

                $mensaje =
                    "Debe seleccionar un candidato para cada cargo.";

                $tipoMensaje =
                    "danger";

            } else {


                /* =========================================
                   INICIAR TRANSACCIÓN
                ========================================= */

                $conn->begin_transaction();


                try {


                    /* =====================================
                       VERIFICAR ESTADO REAL DE ELECCIÓN
                    ===================================== */

                    $stmtEstado =
                        $conn->prepare("

                            SELECT
                                estado

                            FROM elecciones

                            WHERE id = ?

                            LIMIT 1

                            FOR UPDATE

                        ");


                    if (!$stmtEstado) {

                        throw new Exception(
                            "No se pudo comprobar el estado de la elección."
                        );

                    }


                    $stmtEstado->bind_param(
                        "i",
                        $idEleccion
                    );


                    $stmtEstado->execute();


                    $resultadoEstado =
                        $stmtEstado->get_result();


                    if (
                        $resultadoEstado->num_rows === 0
                    ) {

                        throw new Exception(
                            "La elección ya no existe."
                        );

                    }


                    $datosEstado =
                        $resultadoEstado->fetch_assoc();


                    $estadoActual =
                        strtolower(
                            trim(
                                (string)$datosEstado['estado']
                            )
                        );


                    if (
                        $estadoActual !== "abierta"
                    ) {

                        throw new Exception(
                            "La elección fue cerrada. No se puede registrar la votación."
                        );

                    }


                    /* =====================================
                       COMPROBAR DOBLE VOTO
                    ===================================== */

                    $stmtComprobar =
                        $conn->prepare("

                            SELECT
                                id

                            FROM votos

                            WHERE id_usuario = ?

                            AND id_eleccion = ?

                            LIMIT 1

                        ");


                    if (!$stmtComprobar) {

                        throw new Exception(
                            "No se pudo comprobar la votación."
                        );

                    }


                    $stmtComprobar->bind_param(
                        "ii",
                        $idEstudiante,
                        $idEleccion
                    );


                    $stmtComprobar->execute();


                    $resultadoComprobar =
                        $stmtComprobar->get_result();


                    if (
                        $resultadoComprobar->num_rows > 0
                    ) {

                        throw new Exception(
                            "Este estudiante ya había votado en esta elección."
                        );

                    }


                    /* =====================================
                       PREPARAR VALIDACIÓN DE CANDIDATO
                    ===================================== */

                    $stmtCandidato =
                        $conn->prepare("

                            SELECT
                                id

                            FROM candidatos

                            WHERE id = ?

                            AND id_eleccion = ?

                            AND id_cargo = ?

                            LIMIT 1

                        ");


                    if (!$stmtCandidato) {

                        throw new Exception(
                            "No se pudo validar el candidato."
                        );

                    }


                    /* =====================================
                       PREPARAR INSERT
                    ===================================== */

                    $stmtInsertar =
                        $conn->prepare("

                            INSERT INTO votos
                            (
                                id_usuario,
                                id_candidato,
                                id_eleccion,
                                fecha_voto,
                                id_cargo
                            )

                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                NOW(),
                                ?
                            )

                        ");


                    if (!$stmtInsertar) {

                        throw new Exception(
                            "No se pudo preparar el registro del voto."
                        );

                    }


                    /* =====================================
                       REGISTRAR CADA CARGO
                    ===================================== */

                    foreach (
                        $cargosValidos
                        as $idCargo => $nombreCargo
                    ) {


                        $idCandidato =
                            (int)$selecciones[$idCargo];


                        /* =================================
                           VALIDAR CANDIDATO
                        ================================= */

                        $stmtCandidato->bind_param(
                            "iii",
                            $idCandidato,
                            $idEleccion,
                            $idCargo
                        );


                        $stmtCandidato->execute();


                        $resultadoCandidato =
                            $stmtCandidato->get_result();


                        if (
                            $resultadoCandidato->num_rows === 0
                        ) {

                            throw new Exception(

                                "El candidato seleccionado para "
                                .
                                $nombreCargo
                                .
                                " no pertenece a esta elección."

                            );

                        }


                        /* =================================
                           INSERTAR VOTO
                        ================================= */

                        $stmtInsertar->bind_param(
                            "iiii",
                            $idEstudiante,
                            $idCandidato,
                            $idEleccion,
                            $idCargo
                        );


                        if (
                            !$stmtInsertar->execute()
                        ) {

                            throw new Exception(

                                "No se pudo registrar el voto para "
                                .
                                $nombreCargo
                                .
                                "."

                            );

                        }

                    }


                    /* =====================================
                       CERRAR CONSULTAS
                    ===================================== */

                    $stmtEstado->close();

                    $stmtComprobar->close();

                    $stmtCandidato->close();

                    $stmtInsertar->close();


                    /* =====================================
                       CONFIRMAR TRANSACCIÓN
                    ===================================== */

                    $conn->commit();


                    $mensaje =
                        "La votación del estudiante fue registrada correctamente.";

                    $tipoMensaje =
                        "success";


                    $votacionRealizada =
                        true;


                    $yaVoto =
                        true;


                    /* =====================================
                       LIMPIAR ESTUDIANTE DE SESIÓN
                    ===================================== */

                    unset(
                        $_SESSION['estudiante_votando_id'],
                        $_SESSION['estudiante_votando_documento'],
                        $_SESSION['estudiante_votando_nombre'],
                        $_SESSION['estudiante_votando_curso'],
                        $_SESSION['eleccion_votante_id']
                    );


                } catch (
                    Exception $e
                ) {


                    /* =====================================
                       DESHACER TODO
                    ===================================== */

                    $conn->rollback();


                    $mensaje =
                        $e->getMessage();

                    $tipoMensaje =
                        "danger";

                }

            }

        }

    }

}


/* =========================================================
   9. CARGAR CARGOS Y CANDIDATOS
========================================================= */

if (
    $eleccion &&
    !$yaVoto &&
    !$votacionRealizada &&
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) === "abierta"
) {


    $stmt =
        $conn->prepare("

            SELECT
                c.id,
                c.nombre_cargo

            FROM cargos c

            INNER JOIN eleccion_cargos ec

                ON ec.id_cargo = c.id

            WHERE ec.id_eleccion = ?

            ORDER BY c.id ASC

        ");


    if ($stmt) {


        $stmt->bind_param(
            "i",
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        while (
            $cargo =
            $resultado->fetch_assoc()
        ) {


            $cargo['id'] =
                (int)$cargo['id'];


            $cargo['candidatos'] =
                [];


            $stmtCandidatos =
                $conn->prepare("

                    SELECT
                        id,
                        nombre,
                        apellido,
                        curso,
                        foto

                    FROM candidatos

                    WHERE id_eleccion = ?

                    AND id_cargo = ?

                    ORDER BY id ASC

                ");


            if ($stmtCandidatos) {


                $stmtCandidatos->bind_param(
                    "ii",
                    $idEleccion,
                    $cargo['id']
                );


                $stmtCandidatos->execute();


                $resultadoCandidatos =
                    $stmtCandidatos->get_result();


                while (
                    $candidato =
                    $resultadoCandidatos->fetch_assoc()
                ) {

                    $cargo['candidatos'][] =
                        $candidato;

                }


                $stmtCandidatos->close();

            }


            $cargos[] =
                $cargo;

        }


        $stmt->close();

    }

}


/* =========================================================
   DATOS PARA MOSTRAR
========================================================= */

$nombreEstudiante =
    $estudiante['nombre']
    .
    " "
    .
    $estudiante['apellido'];


$documentoEstudiante =
    $estudiante['documento'];


$cursoEstudiante =
    $estudiante['curso'];

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Votación del estudiante

</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

* {

    box-sizing: border-box;

}


body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.header {

    background: #1473ed;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.header h3 {

    margin: 0;

    font-size: 22px;

}


.contenedor {

    max-width: 1100px;

    margin: auto;

    padding: 35px 20px;

}


.estudiante {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.08);

    margin-bottom: 20px;

}


.estudiante h2 {

    color: #1453a3;

    font-weight: bold;

    margin-bottom: 20px;

}


.dato {

    color: #475569;

    margin-bottom: 8px;

}


.eleccion {

    background: #1453a3;

    color: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 20px;

}


.eleccion h3 {

    font-weight: bold;

}


.estado {

    background: #198754;

    color: white;

    padding: 8px 14px;

    border-radius: 20px;

    font-weight: bold;

}


.cargo {

    background: white;

    border-radius: 18px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.08);

    overflow: hidden;

}


.cargo-header {

    background: #e8f1ff;

    padding: 18px 22px;

    color: #1453a3;

}


.cargo-header h3 {

    margin: 0;

    font-weight: bold;

}


.cargo-body {

    padding: 25px;

}


.candidato {

    display: block;

    background: white;

    border:
        2px solid
        #e2e8f0;

    border-radius: 15px;

    padding: 20px;

    cursor: pointer;

    height: 100%;

    transition: .2s;

}


.candidato:hover {

    border-color: #1473ed;

    transform:
        translateY(-2px);

}


.candidato.seleccionado {

    border-color: #198754;

    background: #f0fdf4;

    box-shadow:
        0 5px 15px
        rgba(25,135,84,.15);

}


.candidato input {

    display: none;

}


.foto,
.sin-foto {

    width: 100px;

    height: 100px;

    border-radius: 50%;

    object-fit: cover;

    margin: auto;

}


.sin-foto {

    background: #e8f1ff;

    color: #1453a3;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 45px;

}


.nombre {

    font-size: 19px;

    font-weight: bold;

    color: #1453a3;

}


.curso {

    color: #64748b;

    margin-top: 5px;

}


.btn-votar {

    width: 100%;

    border: none;

    background: #198754;

    color: white;

    padding: 15px;

    border-radius: 12px;

    font-size: 18px;

    font-weight: bold;

    cursor: pointer;

}


.btn-votar:hover {

    background: #157347;

}


.exito,
.bloqueado {

    background: white;

    border-radius: 20px;

    padding: 45px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.exito i {

    font-size: 65px;

    color: #198754;

}


.bloqueado-icono {

    font-size: 65px;

    color: #dc3545;

}


.exito h2,
.bloqueado h2 {

    color: #1453a3;

    font-weight: bold;

}


@media(max-width:600px) {

    .header {

        padding: 15px;

    }


    .header h3 {

        font-size: 18px;

    }


    .contenedor {

        padding:
            20px 12px;

    }


    .candidato {

        padding: 15px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<div class="header">


<h3>

🗳️ Sistema de Votaciones Escolares

</h3>


<span>

<i class="bi bi-person-badge-fill"></i>

Jurado:

<?php

echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Jurado'
);

?>

</span>


</div>


<div class="contenedor">


<!-- =====================================================
     VOTACIÓN REGISTRADA
===================================================== -->

<?php if (
    $votacionRealizada
) { ?>


<div class="exito">


<i class="bi bi-check-circle-fill"></i>


<h2 class="mt-3">

Votación registrada correctamente

</h2>


<p class="text-muted">

La votación de:

<strong>

<?php

echo htmlspecialchars(
    $nombreEstudiante
);

?>

</strong>

fue registrada correctamente.

</p>


<div class="alert alert-success mt-4">

<i class="bi bi-shield-check"></i>

El estudiante ya no podrá volver a votar
en esta elección.

</div>


<a
href="ingresar_estudiante.php"
class="btn btn-primary btn-lg mt-3">

<i class="bi bi-person-plus-fill"></i>

Ingresar otro estudiante

</a>


<a
href="jurado.php"
class="btn btn-outline-secondary btn-lg mt-3">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


<!-- =====================================================
     YA VOTÓ
===================================================== -->

<?php } elseif (
    $yaVoto
) { ?>


<div class="bloqueado">


<div class="bloqueado-icono">

<i class="bi bi-shield-x-fill"></i>

</div>


<h2>

Este estudiante ya votó

</h2>


<h4 class="mt-3">

<?php

echo htmlspecialchars(
    $nombreEstudiante
);

?>

</h4>


<p>

Documento:

<strong>

<?php

echo htmlspecialchars(
    $documentoEstudiante
);

?>

</strong>

</p>


<div class="alert alert-danger mt-4">

<i class="bi bi-exclamation-triangle-fill"></i>

Este estudiante ya tiene una votación
registrada en esta elección.

<br>

No se puede volver a realizar la votación.

</div>


<a
href="ingresar_estudiante.php"
class="btn btn-primary btn-lg">

<i class="bi bi-person-plus-fill"></i>

Ingresar otro estudiante

</a>


<a
href="jurado.php"
class="btn btn-outline-secondary btn-lg">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


<!-- =====================================================
     SIN ELECCIÓN ABIERTA
===================================================== -->

<?php } elseif (
    !$eleccion
) { ?>


<div class="bloqueado">


<div
class="bloqueado-icono"
style="color:#ffc107;">

<i class="bi bi-calendar-x-fill"></i>

</div>


<h2>

No hay una elección abierta

</h2>


<p class="text-muted">

El administrador debe abrir una elección
antes de registrar votos.

</p>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


<!-- =====================================================
     ELECCIÓN CERRADA
===================================================== -->

<?php } elseif (
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) !== "abierta"
) { ?>


<div class="bloqueado">


<div
class="bloqueado-icono"
style="color:#ffc107;">

<i class="bi bi-lock-fill"></i>

</div>


<h2>

Elección cerrada

</h2>


<p class="text-muted">

No se puede registrar una votación
en este momento.

</p>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


<!-- =====================================================
     FORMULARIO DE VOTACIÓN
===================================================== -->

<?php } else { ?>


<!-- =====================================================
     DATOS DEL ESTUDIANTE
===================================================== -->

<div class="estudiante">


<h2>

<i class="bi bi-person-circle"></i>

Estudiante que está votando

</h2>


<div class="row">


<div class="col-md-4 dato">

<strong>

Nombre:

</strong>

<br>

<?php

echo htmlspecialchars(
    $nombreEstudiante
);

?>

</div>


<div class="col-md-4 dato">

<strong>

Documento:

</strong>

<br>

<?php

echo htmlspecialchars(
    $documentoEstudiante
);

?>

</div>


<div class="col-md-4 dato">

<strong>

Curso:

</strong>

<br>

<?php

echo htmlspecialchars(
    $cursoEstudiante
);

?>

</div>


</div>


</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="eleccion">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>


<h3>

<?php

echo htmlspecialchars(
    $eleccion['nombre']
);

?>

</h3>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>


<p class="mb-0">

<?php

echo htmlspecialchars(
    $eleccion['descripcion']
);

?>

</p>


<?php } ?>


</div>


<span class="estado">

🟢 Elección abierta

</span>


</div>


</div>


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje
);

?>">


<i class="bi bi-info-circle-fill"></i>


<?php

echo htmlspecialchars(
    $mensaje
);

?>


</div>


<?php } ?>


<!-- =====================================================
     SIN CARGOS
===================================================== -->

<?php if (
    count($cargos) === 0
) { ?>


<div class="alert alert-warning">


<i class="bi bi-exclamation-triangle-fill"></i>


Esta elección no tiene cargos configurados.


</div>


<?php } else { ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
method="POST"
action="registrar_voto_jurado.php"
id="formVotacion">


<?php foreach (
    $cargos as $cargo
) { ?>


<div class="cargo">


<div class="cargo-header">


<h3>

<i class="bi bi-award-fill"></i>

<?php

echo htmlspecialchars(
    $cargo['nombre_cargo']
);

?>

</h3>


</div>


<div class="cargo-body">


<p class="text-muted">

Seleccione un candidato:

</p>


<div class="row g-4">


<?php if (
    count(
        $cargo['candidatos']
    ) === 0
) { ?>


<div class="col-12">


<div class="alert alert-warning">

No hay candidatos para este cargo.

</div>


</div>


<?php } else { ?>


<?php foreach (
    $cargo['candidatos']
    as $candidato
) { ?>


<div class="col-md-6 col-lg-4">


<label
class="candidato"
onclick="seleccionar(this)">


<input

type="radio"

name="candidato[<?php

echo (int)$cargo['id'];

?>]"

value="<?php

echo (int)$candidato['id'];

?>"

required>


<div class="text-center mb-3">


<?php

$foto =
    trim(
        (string)$candidato['foto']
    );


$rutaFoto =
    "uploads/candidatos/"
    .
    $foto;


if (
    $foto !== "" &&
    file_exists(
        __DIR__
        .
        "/"
        .
        $rutaFoto
    )
) {

?>


<img

src="<?php

echo htmlspecialchars(
    $rutaFoto
);

?>"

class="foto"

alt="Foto del candidato">


<?php

} else {

?>


<div class="sin-foto">

<i class="bi bi-person-fill"></i>

</div>


<?php

}

?>


</div>


<div class="text-center">


<div class="nombre">

<?php

echo htmlspecialchars(
    $candidato['nombre']
    .
    " "
    .
    $candidato['apellido']
);

?>

</div>


<div class="curso">

<i class="bi bi-mortarboard-fill"></i>

Curso:

<?php

echo htmlspecialchars(
    $candidato['curso']
);

?>

</div>


</div>


</label>


</div>


<?php } ?>


<?php } ?>


</div>


</div>


</div>


<?php } ?>


<button

type="submit"

name="votar"

class="btn-votar"

onclick="return confirmarVotacion();">


<i class="bi bi-check-circle-fill"></i>

Registrar votación

</button>


</form>


<?php } ?>


<a
href="ingresar_estudiante.php"
class="btn btn-outline-secondary w-100 mt-3">


<i class="bi bi-arrow-left"></i>


Cambiar estudiante


</a>


<?php } ?>


</div>


<script>

/* =========================================================
   MARCAR CANDIDATO
========================================================= */

function seleccionar(elemento) {


    const contenedor =
        elemento.parentElement;


    const candidatos =
        contenedor.querySelectorAll(
            ".candidato"
        );


    candidatos.forEach(
        function(candidato) {

            candidato.classList.remove(
                "seleccionado"
            );

        }
    );


    elemento.classList.add(
        "seleccionado"
    );

}


/* =========================================================
   CONFIRMAR VOTACIÓN
========================================================= */

function confirmarVotacion() {


    const formulario =
        document.getElementById(
            "formVotacion"
        );


    if (!formulario) {

        return false;

    }


    const cargos =
        formulario.querySelectorAll(
            ".cargo"
        );


    for (
        let i = 0;
        i < cargos.length;
        i++
    ) {


        const candidatos =
            cargos[i].querySelectorAll(
                "input[type='radio']"
            );


        const seleccionado =
            cargos[i].querySelector(
                "input[type='radio']:checked"
            );


        if (
            candidatos.length > 0 &&
            !seleccionado
        ) {


            alert(
                "Debe seleccionar un candidato para cada cargo."
            );


            cargos[i].scrollIntoView({

                behavior: "smooth",

                block: "center"

            });


            return false;

        }

    }


    return confirm(

        "¿Está seguro de registrar la votación?\n\n" +

        "Después de registrarla no podrá volver a votar en esta elección."

    );

}

</script>


</body>

</html>
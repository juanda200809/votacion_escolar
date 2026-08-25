<?php

require_once "seguridad.php";

evitarCache();
verificarRol(['estudiante']);



session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN
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


/* =========================================================
   SOLO ESTUDIANTE
========================================================= */

if ($rol !== "estudiante") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rol === "jurado") {

        header("Location: jurado.php");
        exit();

    }

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================================
   OBTENER ESTUDIANTE
========================================================= */

$idUsuario = (int)$_SESSION['id'];


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

    die("Error en la consulta del estudiante.");

}


$stmt->bind_param(
    "i",
    $idUsuario
);


$stmt->execute();


$resultadoUsuario =
    $stmt->get_result();


if (
    $resultadoUsuario->num_rows === 0
) {

    $stmt->close();

    session_unset();
    session_destroy();

    header("Location: login.php");

    exit();

}


$estudiante =
    $resultadoUsuario->fetch_assoc();


$stmt->close();


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$stmtEleccion =
    $conn->prepare("

        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado

        FROM elecciones

        WHERE LOWER(TRIM(estado)) = 'abierta'

        ORDER BY id DESC

        LIMIT 1

    ");


$stmtEleccion->execute();


$resultadoEleccion =
    $stmtEleccion->get_result();


if (
    $resultadoEleccion->num_rows === 0
) {

    $eleccion = null;

} else {

    $eleccion =
        $resultadoEleccion->fetch_assoc();

}


$stmtEleccion->close();


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$votacionRealizada = false;

$cargos = [];

$cargosVotados = [];


/* =========================================================
   SI EXISTE ELECCIÓN
========================================================= */

if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];


    /* =====================================================
       OBTENER CARGOS YA VOTADOS
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT DISTINCT
                v.id_cargo

            FROM votos v

            WHERE v.id_usuario = ?

            AND v.id_eleccion = ?

        ");


    $stmt->bind_param(
        "ii",
        $idUsuario,
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    while (
        $fila =
        $resultado->fetch_assoc()
    ) {

        $cargosVotados[] =
            (int)$fila['id_cargo'];

    }


    $stmt->close();


    /* =====================================================
       OBTENER CARGOS DE LA ELECCIÓN
    ===================================================== */

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


    $stmt->bind_param(
        "i",
        $idEleccion
    );


    $stmt->execute();


    $resultadoCargos =
        $stmt->get_result();


    while (
        $cargo =
        $resultadoCargos->fetch_assoc()
    ) {

        $cargo['id'] =
            (int)$cargo['id'];

        $cargo['candidatos'] =
            [];

        $cargo['ya_votado'] =
            in_array(
                $cargo['id'],
                $cargosVotados,
                true
            );

        $cargos[] =
            $cargo;

    }


    $stmt->close();


    /* =====================================================
       OBTENER CANDIDATOS POR CARGO
    ===================================================== */

    foreach (
        $cargos as &$cargo
    ) {

        $idCargo =
            (int)$cargo['id'];


        $stmt =
            $conn->prepare("

                SELECT

                    id,
                    nombre,
                    apellido,
                    curso,
                    foto,
                    propuestas

                FROM candidatos

                WHERE id_eleccion = ?

                AND id_cargo = ?

                ORDER BY
                    nombre ASC,
                    apellido ASC

            ");


        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );


        $stmt->execute();


        $resultadoCandidatos =
            $stmt->get_result();


        while (
            $candidato =
            $resultadoCandidatos->fetch_assoc()
        ) {

            $cargo['candidatos'][] =
                $candidato;

        }


        $stmt->close();

    }


    unset($cargo);


    /* =====================================================
       PROCESAR VOTACIÓN
    ===================================================== */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['votar'])
    ) {


        $selecciones =
            $_POST['candidato'] ?? [];


        if (
            !is_array($selecciones)
        ) {

            $selecciones = [];

        }


        /* =================================================
           VERIFICAR CARGOS
        ================================================= */

        $faltanCargos = [];


        foreach (
            $cargos as $cargo
        ) {

            $idCargo =
                (int)$cargo['id'];


            /*
               Si ya votó este cargo,
               no necesitamos seleccionarlo nuevamente.
            */

            if (
                $cargo['ya_votado']
            ) {

                continue;

            }


            if (
                !isset(
                    $selecciones[$idCargo]
                ) ||
                (int)$selecciones[$idCargo] <= 0
            ) {

                $faltanCargos[] =
                    $cargo['nombre_cargo'];

            }

        }


        /* =================================================
           VERIFICAR SI FALTAN CARGOS
        ================================================= */

        if (
            count($faltanCargos) > 0
        ) {

            $mensaje =
                "Debe seleccionar un candidato para cada cargo pendiente.";

            $tipoMensaje =
                "danger";

        } else {


            /* =============================================
               INICIAR TRANSACCIÓN
            ============================================= */

            $conn->begin_transaction();


            try {


                /* =========================================
                   PREPARAR VALIDACIÓN
                ========================================= */

                $stmtValidar =
                    $conn->prepare("

                        SELECT
                            id

                        FROM candidatos

                        WHERE id = ?

                        AND id_eleccion = ?

                        AND id_cargo = ?

                        LIMIT 1

                    ");


                /* =========================================
                   PREPARAR COMPROBACIÓN DE VOTO
                ========================================= */

                $stmtComprobar =
                    $conn->prepare("

                        SELECT
                            id

                        FROM votos

                        WHERE id_usuario = ?

                        AND id_eleccion = ?

                        AND id_cargo = ?

                        LIMIT 1

                    ");


                /* =========================================
                   PREPARAR INSERT
                ========================================= */

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


                if (
                    !$stmtValidar ||
                    !$stmtComprobar ||
                    !$stmtInsertar
                ) {

                    throw new Exception(
                        "No se pudieron preparar las consultas."
                    );

                }


                /* =========================================
                   REGISTRAR CADA CARGO PENDIENTE
                ========================================= */

                foreach (
                    $cargos as $cargo
                ) {

                    $idCargo =
                        (int)$cargo['id'];


                    /*
                       Si este cargo ya fue votado,
                       lo dejamos intacto.
                    */

                    if (
                        $cargo['ya_votado']
                    ) {

                        continue;

                    }


                    /* =====================================
                       OBTENER CANDIDATO SELECCIONADO
                    ===================================== */

                    $idCandidato =
                        isset(
                            $selecciones[$idCargo]
                        )
                        ? (int)$selecciones[$idCargo]
                        : 0;


                    if (
                        $idCandidato <= 0
                    ) {

                        throw new Exception(
                            "Debe seleccionar un candidato."
                        );

                    }


                    /* =====================================
                       COMPROBAR SI YA VOTÓ
                    ===================================== */

                    $stmtComprobar->bind_param(
                        "iii",
                        $idUsuario,
                        $idEleccion,
                        $idCargo
                    );


                    $stmtComprobar->execute();


                    $resultadoComprobar =
                        $stmtComprobar->get_result();


                    if (
                        $resultadoComprobar->num_rows > 0
                    ) {

                        throw new Exception(
                            "Ya registraste tu voto para el cargo: "
                            .
                            $cargo['nombre_cargo']
                        );

                    }


                    /* =====================================
                       VALIDAR CANDIDATO
                    ===================================== */

                    $stmtValidar->bind_param(
                        "iii",
                        $idCandidato,
                        $idEleccion,
                        $idCargo
                    );


                    $stmtValidar->execute();


                    $resultadoValidar =
                        $stmtValidar->get_result();


                    if (
                        $resultadoValidar->num_rows === 0
                    ) {

                        throw new Exception(
                            "El candidato seleccionado no pertenece a este cargo."
                        );

                    }


                    /* =====================================
                       INSERTAR VOTO
                    ===================================== */

                    $stmtInsertar->bind_param(
                        "iiii",
                        $idUsuario,
                        $idCandidato,
                        $idEleccion,
                        $idCargo
                    );


                    if (
                        !$stmtInsertar->execute()
                    ) {

                        /*
                           MySQL 1062 =
                           restricción UNIQUE.
                        */

                        if (
                            $stmtInsertar->errno == 1062
                        ) {

                            throw new Exception(
                                "Este voto ya había sido registrado."
                            );

                        }


                        throw new Exception(
                            "No se pudo registrar el voto."
                        );

                    }

                }


                /* =========================================
                   CERRAR CONSULTAS
                ========================================= */

                $stmtValidar->close();

                $stmtComprobar->close();

                $stmtInsertar->close();


                /* =========================================
                   CONFIRMAR
                ========================================= */

                $conn->commit();


                /*
                   Volvemos a consultar los cargos
                   para saber si terminó toda
                   la votación.
                */

                $stmtFinal =
                    $conn->prepare("

                        SELECT COUNT(DISTINCT id_cargo) AS total

                        FROM votos

                        WHERE id_usuario = ?

                        AND id_eleccion = ?

                    ");


                $stmtFinal->bind_param(
                    "ii",
                    $idUsuario,
                    $idEleccion
                );


                $stmtFinal->execute();


                $resultadoFinal =
                    $stmtFinal->get_result();


                $datosFinal =
                    $resultadoFinal->fetch_assoc();


                $stmtFinal->close();


                $totalCargos =
                    count($cargos);


                $totalVotados =
                    (int)$datosFinal['total'];


                if (
                    $totalVotados >=
                    $totalCargos
                ) {

                    $votacionRealizada =
                        true;

                } else {

                    /*
                       Todavía quedan cargos
                       pendientes.
                    */

                    $mensaje =
                        "Tu voto fue registrado correctamente. Todavía tienes cargos pendientes por votar.";

                    $tipoMensaje =
                        "success";


                    /*
                       Actualizamos la lista
                       de cargos votados.
                    */

                    $cargosVotados = [];


                    $stmtActualizado =
                        $conn->prepare("

                            SELECT DISTINCT id_cargo

                            FROM votos

                            WHERE id_usuario = ?

                            AND id_eleccion = ?

                        ");


                    $stmtActualizado->bind_param(
                        "ii",
                        $idUsuario,
                        $idEleccion
                    );


                    $stmtActualizado->execute();


                    $resultadoActualizado =
                        $stmtActualizado->get_result();


                    while (
                        $fila =
                        $resultadoActualizado->fetch_assoc()
                    ) {

                        $cargosVotados[] =
                            (int)$fila['id_cargo'];

                    }


                    $stmtActualizado->close();


                    foreach (
                        $cargos as &$cargo
                    ) {

                        $cargo['ya_votado'] =
                            in_array(
                                (int)$cargo['id'],
                                $cargosVotados,
                                true
                            );

                    }


                    unset($cargo);

                }


            } catch (
                Exception $e
            ) {

                $conn->rollback();


                $mensaje =
                    $e->getMessage();

                $tipoMensaje =
                    "danger";

            }

        }

    }

}

?>


<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Votación Escolar

</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.topbar {

    background:#1453a3;

    color:white;

    padding:18px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    gap:10px;

}


.contenedor {

    max-width:1150px;

    margin:auto;

    padding:35px 20px;

}


.bienvenida {

    background:white;

    border-radius:18px;

    padding:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    margin-bottom:25px;

}


.titulo {

    color:#1453a3;

    font-weight:bold;

}


.estado-abierta {

    background:#198754;

    color:white;

    padding:7px 15px;

    border-radius:7px;

    font-weight:bold;

}


.cargo {

    background:white;

    border-radius:18px;

    margin-bottom:25px;

    overflow:hidden;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.cargo-header {

    background:#1453a3;

    color:white;

    padding:20px 25px;

}


.cargo-header h3 {

    margin:0;

    font-weight:bold;

}


.cargo-header.votado {

    background:#198754;

}


.cargo-body {

    padding:25px;

}


.candidato {

    background:white;

    border:2px solid #e0e6ed;

    border-radius:15px;

    padding:20px;

    height:100%;

    cursor:pointer;

    transition:.2s;

}


.candidato:hover {

    border-color:#1473ed;

    background:#f5f9ff;

}


.candidato.seleccionado {

    border-color:#1473ed;

    background:#eaf3ff;

}


.candidato.bloqueado {

    opacity:.55;

    cursor:not-allowed;

}


.candidato input {

    display:none;

}


.foto-candidato {

    width:120px;

    height:120px;

    object-fit:cover;

    border-radius:50%;

    border:5px solid #e5edf8;

}


.foto-vacia {

    width:120px;

    height:120px;

    border-radius:50%;

    background:#dbe8f8;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:auto;

    font-size:50px;

    color:#1453a3;

}


.nombre-candidato {

    color:#1453a3;

    font-size:19px;

    font-weight:bold;

}


.btn-votar {

    width:100%;

    padding:14px;

    font-size:19px;

    font-weight:bold;

}


.exito {

    background:white;

    border-radius:20px;

    padding:50px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.exito i {

    font-size:80px;

    color:#198754;

}


.estado-votado {

    display:inline-block;

    background:#d1fae5;

    color:#047857;

    padding:8px 14px;

    border-radius:20px;

    font-weight:bold;

}


.estado-pendiente {

    display:inline-block;

    background:#dbeafe;

    color:#1459a6;

    padding:8px 14px;

    border-radius:20px;

    font-weight:bold;

}


</style>

</head>


<body>


<!-- =====================================================
     BARRA SUPERIOR
===================================================== -->

<div class="topbar">


<div>

<i class="bi bi-mortarboard-fill"></i>

<strong>

Sistema de Votaciones Escolares

</strong>

</div>


<div>

<i class="bi bi-person-fill"></i>

<?php

echo htmlspecialchars(
    $estudiante['nombre']
);

?>

</div>


</div>


<div class="contenedor">


<?php if (
    $votacionRealizada
) { ?>


<!-- =====================================================
     VOTACIÓN COMPLETAMENTE FINALIZADA
===================================================== -->

<div class="exito">


<i class="bi bi-check-circle-fill"></i>


<h1 class="text-success mt-3">

¡Votación registrada!

</h1>


<p class="fs-5">

Has completado correctamente
todos los cargos de esta elección.

</p>


<div class="alert alert-success">

<i class="bi bi-shield-check"></i>

<strong>

Gracias por participar.

</strong>

Tus votos han sido registrados
de forma segura.

</div>


<a
href="logout.php"
class="btn btn-outline-danger btn-lg mt-3">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>


<?php } else { ?>


<!-- =====================================================
     INFORMACIÓN DEL ESTUDIANTE
===================================================== -->

<div class="bienvenida">


<h2 class="titulo">

Votación Escolar

</h2>


<p>

Bienvenido:

<strong>

<?php

echo htmlspecialchars(
    $estudiante['nombre']
    .
    " "
    .
    $estudiante['apellido']
);

?>

</strong>

</p>


<p>

Curso:

<strong>

<?php

echo htmlspecialchars(
    $estudiante['curso']
);

?>

</strong>

</p>


<hr>


<?php if (
    $eleccion
) { ?>


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>


<h4>

<?php

echo htmlspecialchars(
    $eleccion['nombre']
);

?>

</h4>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="text-muted mb-0">

<?php

echo htmlspecialchars(
    $eleccion['descripcion']
);

?>

</p>

<?php } ?>


</div>


<div>

<span class="estado-abierta">

Elección abierta

</span>

</div>


</div>


<?php } else { ?>


<div class="alert alert-danger mb-0">

<i class="bi bi-lock-fill"></i>

No existe una elección abierta actualmente.

</div>


<?php } ?>


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


<?php if (
    $eleccion &&
    count($cargos) === 0
) { ?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

La elección no tiene cargos configurados.

</div>


<?php } elseif (
    $eleccion
) { ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
method="POST"
id="formVotacion">


<?php foreach (
    $cargos as $cargo
) { ?>


<div class="cargo">


<div class="cargo-header <?php

if (
    $cargo['ya_votado']
) {

    echo "votado";

}

?>">


<div class="d-flex
            justify-content-between
            align-items-center
            gap-2
            flex-wrap">


<h3>

<i class="bi bi-award-fill"></i>

<?php

echo htmlspecialchars(
    $cargo['nombre_cargo']
);

?>

</h3>


<?php if (
    $cargo['ya_votado']
) { ?>

<span class="estado-votado">

<i class="bi bi-check-circle-fill"></i>

Ya votaste

</span>

<?php } else { ?>

<span class="estado-pendiente">

Pendiente

</span>

<?php } ?>


</div>


</div>


<div class="cargo-body">


<?php if (
    $cargo['ya_votado']
) { ?>


<div class="alert alert-success">

<i class="bi bi-check-circle-fill"></i>

Ya registraste tu voto para este cargo.

<strong>

Este cargo está bloqueado.

</strong>

</div>


<?php } else { ?>


<p class="text-muted">

Selecciona un candidato:

</p>


<?php } ?>


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


<label class="candidato <?php

if (
    $cargo['ya_votado']
) {

    echo "bloqueado";

}

?>">


<input

type="radio"

name="candidato[
<?php

echo (int)$cargo['id'];

?>]"

value="<?php

echo (int)$candidato['id'];

?>"

<?php

if (
    $cargo['ya_votado']
) {

    echo "disabled";

} else {

    echo "required";

}

?>


>


<!-- FOTO -->

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

class="foto-candidato"

alt="Foto del candidato">


<?php

} else {

?>


<div class="foto-vacia">

<i class="bi bi-person-fill"></i>

</div>


<?php

}

?>


</div>


<!-- NOMBRE -->

<div
class="nombre-candidato text-center">


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


<p class="text-center text-muted">

Curso:

<?php

echo htmlspecialchars(
    $candidato['curso']
);

?>

</p>


<?php if (
    !empty(
        $candidato['propuestas']
    )
) { ?>


<div class="alert alert-light">

<strong>

Propuestas:

</strong>


<br>


<?php

echo nl2br(
    htmlspecialchars(
        $candidato['propuestas']
    )
);

?>


</div>


<?php } ?>


<?php if (
    $cargo['ya_votado']
) { ?>

<div class="text-center">

<span class="estado-votado">

Voto registrado

</span>

</div>

<?php } ?>


</label>


</div>


<?php } ?>


<?php } ?>


</div>


</div>


</div>


<?php } ?>


<!-- =====================================================
     BOTÓN
===================================================== -->

<?php

$cargosPendientes = 0;


foreach (
    $cargos as $cargo
) {

    if (
        !$cargo['ya_votado']
    ) {

        $cargosPendientes++;

    }

}


?>


<?php if (
    $cargosPendientes > 0
) { ?>


<button

type="submit"

name="votar"

class="btn btn-primary btn-votar"

onclick="
return confirmarVotacion();
">


<i class="bi bi-check-circle-fill"></i>

Registrar votos pendientes


</button>


<?php } ?>


</form>


<?php } ?>


<!-- =====================================================
     CERRAR SESIÓN
===================================================== -->

<div class="text-center mt-4">

<a
href="logout.php"
class="btn btn-outline-danger">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>

</div>


<?php } ?>


</div>


<script>

/* =========================================================
   SELECCIONAR CANDIDATO
========================================================= */

function seleccionar(elemento) {


    if (
        elemento.classList.contains(
            "bloqueado"
        )
    ) {

        return;

    }


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


        /*
           Si el cargo ya fue votado,
           no necesitamos comprobarlo.
        */

        const cargoBloqueado =
            cargos[i].querySelector(
                "input[disabled]"
            );


        if (
            cargoBloqueado
        ) {

            continue;

        }


        const seleccionado =
            cargos[i].querySelector(
                "input[type='radio']:checked"
            );


        if (!seleccionado) {


            alert(
                "Debe seleccionar un candidato para cada cargo pendiente."
            );


            cargos[i].scrollIntoView({

                behavior:
                    "smooth",

                block:
                    "center"

            });


            return false;

        }

    }


    return confirm(

        "¿Está seguro de registrar sus votos?\n\n" +

        "Los votos de los cargos seleccionados " +

        "no podrán modificarse posteriormente."

    );

}

</script>


</body>

</html>
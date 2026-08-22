<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
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
   VERIFICAR ESTUDIANTE SELECCIONADO
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


$nombreEstudiante =
    $_SESSION['estudiante_votando_nombre']
    ?? "Estudiante";


$documentoEstudiante =
    $_SESSION['estudiante_votando_documento']
    ?? "";


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$eleccion = null;

$yaVoto = false;

$votacionRealizada = false;

$cargos = [];


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$stmtEleccion = $conn->prepare("

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


if ($stmtEleccion) {

    $stmtEleccion->execute();

    $resultadoEleccion =
        $stmtEleccion->get_result();


    if (
        $resultadoEleccion->num_rows > 0
    ) {

        $eleccion =
            $resultadoEleccion->fetch_assoc();

    }


    $stmtEleccion->close();

}


/* =========================================================
   SI NO HAY ELECCIÓN
========================================================= */

if (!$eleccion) {

    $mensaje =
        "Actualmente no hay una elección abierta.";

    $tipoMensaje =
        "warning";

}


/* =========================================================
   ID ELECCIÓN
========================================================= */

$idEleccion =
    $eleccion
    ? (int)$eleccion['id']
    : 0;


/* =========================================================
   COMPROBAR SI YA VOTÓ
========================================================= */

if ($eleccion) {

    $stmtYaVoto = $conn->prepare("

        SELECT
            v.id

        FROM votos v

        INNER JOIN candidatos c
            ON c.id = v.id_candidato

        WHERE v.id_usuario = ?

        AND c.id_eleccion = ?

        LIMIT 1

    ");


    if ($stmtYaVoto) {

        $stmtYaVoto->bind_param(
            "ii",
            $idEstudiante,
            $idEleccion
        );


        $stmtYaVoto->execute();


        $resultadoYaVoto =
            $stmtYaVoto->get_result();


        if (
            $resultadoYaVoto->num_rows > 0
        ) {

            $yaVoto = true;

            $mensaje =
                "Este estudiante ya realizó su votación en esta elección.";

            $tipoMensaje =
                "danger";

        }


        $stmtYaVoto->close();

    }

}


/* =========================================================
   PROCESAR VOTACIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['votar']) &&
    $eleccion &&
    !$yaVoto
) {


    $selecciones =
        $_POST['candidato'] ?? [];


    if (
        !is_array($selecciones)
    ) {

        $selecciones = [];

    }


    /* =====================================================
       OBTENER CARGOS
    ===================================================== */

    $cargosValidos = [];


    $stmtCargos =
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


    if (!$stmtCargos) {

        $mensaje =
            "Error al consultar los cargos.";

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
               COMPROBAR SELECCIÓN DE TODOS LOS CARGOS
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
                   TRANSACCIÓN
                ========================================= */

                $conn->begin_transaction();


                try {


                    /* =====================================
                       VERIFICAR QUE LA ELECCIÓN
                       SIGA ABIERTA
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
                                v.id

                            FROM votos v

                            INNER JOIN candidatos c
                                ON c.id = v.id_candidato

                            WHERE v.id_usuario = ?

                            AND c.id_eleccion = ?

                            LIMIT 1

                            FOR UPDATE

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
                       VALIDAR CANDIDATOS
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
                       INSERTAR VOTOS
                    ===================================== */

                    $stmtInsertar =
                        $conn->prepare("

                            INSERT INTO votos
                            (
                                id_usuario,
                                id_candidato,
                                fecha_voto,
                                id_cargo
                            )

                            VALUES
                            (
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
                       REGISTRAR UN VOTO POR CARGO
                    ===================================== */

                    foreach (
                        $cargosValidos
                        as $idCargo => $nombreCargo
                    ) {

                        $idCandidato =
                            (int)$selecciones[$idCargo];


                        /* ================================
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
                                . $nombreCargo
                                . " no pertenece a esta elección."
                            );

                        }


                        /* ================================
                           INSERTAR
                        ================================= */

                        $stmtInsertar->bind_param(
                            "iii",
                            $idEstudiante,
                            $idCandidato,
                            $idCargo
                        );


                        if (
                            !$stmtInsertar->execute()
                        ) {

                            throw new Exception(
                                "No se pudo registrar el voto para "
                                . $nombreCargo . "."
                            );

                        }

                    }


                    /* =====================================
                       CERRAR CONSULTAS
                    ================================= */

                    $stmtEstado->close();

                    $stmtComprobar->close();

                    $stmtCandidato->close();

                    $stmtInsertar->close();


                    /* =====================================
                       CONFIRMAR
                    ================================= */

                    $conn->commit();


                    $mensaje =
                        "La votación del estudiante fue registrada correctamente.";

                    $tipoMensaje =
                        "success";


                    $votacionRealizada =
                        true;


                    /* =====================================
                       ELIMINAR TODA LA INFORMACIÓN
                       TEMPORAL DEL ESTUDIANTE
                    ===================================== */

                    unset(

                        $_SESSION[
                            'estudiante_votando_id'
                        ],

                        $_SESSION[
                            'estudiante_votando_documento'
                        ],

                        $_SESSION[
                            'estudiante_votando_nombre'
                        ],

                        $_SESSION[
                            'estudiante_votando_curso'
                        ],

                        $_SESSION[
                            'eleccion_votante_id'
                        ]

                    );


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

}


/* =========================================================
   CARGAR CARGOS Y CANDIDATOS
========================================================= */

if (
    $eleccion &&
    !$yaVoto &&
    !$votacionRealizada
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

    font-weight: bold;

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

    margin-bottom: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.estudiante h2 {

    color: #1453a3;

    font-weight: bold;

}


.eleccion {

    background: #cfe2ff;

    border: 1px solid #9ec5fe;

    border-radius: 15px;

    padding: 20px;

    margin-bottom: 30px;

    color: #084298;

}


.cargo {

    background: white;

    border-radius: 18px;

    margin-bottom: 25px;

    overflow: hidden;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.cargo-header {

    background: #1453a3;

    color: white;

    padding: 18px 22px;

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

    border: 2px solid #e0e6ed;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 15px;

    cursor: pointer;

    transition: .2s;

}


.candidato:hover {

    border-color: #1473ed;

    background: #f5f9ff;

}


.candidato.seleccionado {

    border-color: #1473ed;

    background: #eaf3ff;

}


.candidato input {

    display: none;

}


.candidato-contenido {

    display: flex;

    align-items: center;

    gap: 18px;

}


.foto {

    width: 80px;

    height: 80px;

    object-fit: cover;

    border-radius: 50%;

    border: 3px solid #e0e6ed;

}


.sin-foto {

    width: 80px;

    height: 80px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 32px;

    color: #1453a3;

}


.nombre {

    font-size: 19px;

    font-weight: bold;

    color: #1453a3;

}


.curso {

    color: #6c757d;

    margin-top: 5px;

}


.btn-votar {

    width: 100%;

    padding: 15px;

    background: #1473ed;

    border: none;

    border-radius: 12px;

    color: white;

    font-size: 19px;

    font-weight: bold;

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


    .candidato-contenido {

        gap: 10px;

    }


    .foto,
    .sin-foto {

        width: 60px;

        height: 60px;

    }

}

</style>

</head>


<body>


<div class="header">

<h3>

🗳️ Sistema de Votaciones Escolares

</h3>


<span>

<i class="bi bi-person-badge-fill"></i>

Jurado:

<?php echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Jurado'
); ?>

</span>

</div>


<div class="contenedor">


<?php if (
    $votacionRealizada
) { ?>


<div class="exito">

<i class="bi bi-check-circle-fill"></i>


<h2 class="mt-3">

Votación registrada correctamente

</h2>


<p class="text-muted">

El voto del estudiante fue registrado
correctamente.

</p>


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

<?php echo htmlspecialchars(
    $nombreEstudiante
); ?>

</h4>


<p>

Documento:

<strong>

<?php echo htmlspecialchars(
    $documentoEstudiante
); ?>

</strong>

</p>


<div class="alert alert-danger mt-4">

<i class="bi bi-exclamation-triangle-fill"></i>

Este estudiante ya tiene una votación
registrada en esta elección.

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


<?php } elseif (
    !$eleccion
) { ?>


<div class="bloqueado">


<div class="bloqueado-icono"
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
class="btn btn-primary">

Volver al panel

</a>


</div>


<?php } else { ?>


<div class="estudiante">

<h2>

<i class="bi bi-person-vcard-fill"></i>

Estudiante que está votando

</h2>


<hr>


<strong>

<?php echo htmlspecialchars(
    $nombreEstudiante
); ?>

</strong>


<div class="text-muted">

Documento:

<?php echo htmlspecialchars(
    $documentoEstudiante
); ?>

</div>

</div>


<div class="eleccion">

<h4>

<i class="bi bi-calendar-event-fill"></i>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h4>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="mb-0">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>

</div>


<?php if (
    count($cargos) === 0
) { ?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

Esta elección no tiene cargos configurados.

</div>


<?php } else { ?>


<form
method="POST"
id="formVotacion">


<?php foreach (
    $cargos as $cargo
) { ?>


<div class="cargo">


<div class="cargo-header">

<h3>

<i class="bi bi-award-fill"></i>

<?php echo htmlspecialchars(
    $cargo['nombre_cargo']
); ?>

</h3>

</div>


<div class="cargo-body">


<p class="text-muted">

Seleccione un candidato:

</p>


<?php if (
    count($cargo['candidatos']) === 0
) { ?>


<div class="alert alert-warning">

No hay candidatos registrados
para este cargo.

</div>


<?php } else { ?>


<?php foreach (
    $cargo['candidatos']
    as $candidato
) { ?>


<label
class="candidato"
onclick="seleccionar(this)">


<input

type="radio"

name="candidato[
<?php echo (int)$cargo['id']; ?>
]"

value="<?php echo (int)$candidato['id']; ?>"

required>


<div class="candidato-contenido">


<?php

$foto =
    trim(
        (string)$candidato['foto']
    );


$rutaFoto =
    "uploads/candidatos/" .
    $foto;


if (
    $foto !== "" &&
    file_exists(
        __DIR__ .
        "/" .
        $rutaFoto
    )
) {

?>

<img

src="<?php echo htmlspecialchars(
    $rutaFoto
); ?>"

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


<div>


<div class="nombre">

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    " " .
    $candidato['apellido']
); ?>

</div>


<div class="curso">

<i class="bi bi-mortarboard-fill"></i>

Curso:

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</div>


</div>


</div>


</label>


<?php } ?>


<?php } ?>


</div>

</div>


<?php } ?>


<button

type="submit"

name="votar"

class="btn-votar"

onclick="
return confirmarVotacion();
">

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

        const seleccionado =
            cargos[i].querySelector(
                "input[type='radio']:checked"
            );


        const candidatos =
            cargos[i].querySelectorAll(
                "input[type='radio']"
            );


        /*
         * Si el cargo tiene candidatos,
         * debe haber uno seleccionado.
         */

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
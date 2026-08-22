<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL JURADO
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'jurado'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");


/* =========================================
   VERIFICAR ESTUDIANTE SELECCIONADO
========================================= */

if (!isset($_SESSION['estudiante_jurado'])) {

    header("Location: jurado.php");
    exit();

}

$idEstudiante =
    (int)$_SESSION['estudiante_jurado'];


/* =========================================
   BUSCAR ESTUDIANTE
========================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE id = ?
    AND rol = 'estudiante'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEstudiante
);

$stmt->execute();

$resultadoEstudiante =
    $stmt->get_result();


if (
    $resultadoEstudiante->num_rows === 0
) {

    unset(
        $_SESSION['estudiante_jurado']
    );

    $stmt->close();

    header("Location: jurado.php");
    exit();

}


$estudiante =
    $resultadoEstudiante->fetch_assoc();

$stmt->close();


/* =========================================
   OBTENER ELECCIÓN
========================================= */

$resultadoEleccion = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (
    !$resultadoEleccion ||
    $resultadoEleccion->num_rows === 0
) {

    $eleccionExiste = false;

    $eleccion = null;

} else {

    $eleccionExiste = true;

    $eleccion =
        $resultadoEleccion->fetch_assoc();

}


/* =========================================
   COMPROBAR ESTADO
========================================= */

$eleccionAbierta = false;

if (
    $eleccionExiste &&
    $eleccion['estado'] === 'abierta'
) {

    $eleccionAbierta = true;

}


/* =========================================
   MENSAJES
========================================= */

$mensaje = "";

$tipoMensaje = "";


/* =========================================
   PROCESAR VOTO
========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['votar'])
) {


    /* =====================================
       COMPROBAR ELECCIÓN ABIERTA
    ===================================== */

    if (!$eleccionAbierta) {

        $mensaje =
            "La elección está cerrada. " .
            "No se pueden registrar votos.";

        $tipoMensaje = "danger";

    } else {


        $idCandidato =
            (int)($_POST['id_candidato'] ?? 0);


        if ($idCandidato <= 0) {

            $mensaje =
                "Debe seleccionar un candidato.";

            $tipoMensaje = "danger";

        } else {


            /* ==============================
               COMPROBAR CANDIDATO
            ============================== */

            $stmt = $conn->prepare("
                SELECT id
                FROM candidatos
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $idCandidato
            );

            $stmt->execute();

            $resultadoCandidato =
                $stmt->get_result();

            $stmt->close();


            if (
                $resultadoCandidato->num_rows === 0
            ) {

                $mensaje =
                    "El candidato seleccionado no existe.";

                $tipoMensaje = "danger";

            } else {


                /* ==============================
                   COMPROBAR SI YA VOTÓ
                ============================== */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM votos
                    WHERE id_usuario = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "i",
                    $idEstudiante
                );

                $stmt->execute();

                $resultadoVoto =
                    $stmt->get_result();

                $stmt->close();


                if (
                    $resultadoVoto->num_rows > 0
                ) {

                    $mensaje =
                        "Este estudiante ya realizó su voto.";

                    $tipoMensaje = "warning";

                } else {


                    /* ==============================
                       REGISTRAR VOTO
                    ============================== */

                    $stmt = $conn->prepare("
                        INSERT INTO votos
                        (
                            id_usuario,
                            id_candidato,
                            fecha_voto
                        )
                        VALUES
                        (?, ?, NOW())
                    ");


                    if (!$stmt) {

                        $mensaje =
                            "No se pudo preparar el registro del voto.";

                        $tipoMensaje = "danger";

                    } else {


                        $stmt->bind_param(
                            "ii",
                            $idEstudiante,
                            $idCandidato
                        );


                        if ($stmt->execute()) {

                            $mensaje =
                                "¡Voto registrado correctamente para el estudiante!";

                            $tipoMensaje =
                                "success";

                            /*
                             * El estudiante ya terminó.
                             * Eliminamos su selección de la sesión.
                             */

                            unset(
                                $_SESSION[
                                    'estudiante_jurado'
                                ]
                            );

                        } else {

                            $mensaje =
                                "No se pudo registrar el voto.";

                            $tipoMensaje =
                                "danger";

                        }


                        $stmt->close();

                    }

                }

            }

        }

    }

}


/* =========================================
   COMPROBAR SI YA VOTÓ
========================================= */

$yaVoto = false;


$stmt = $conn->prepare("
    SELECT id
    FROM votos
    WHERE id_usuario = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEstudiante
);

$stmt->execute();

$resultadoVoto =
    $stmt->get_result();


if (
    $resultadoVoto->num_rows > 0
) {

    $yaVoto = true;

}

$stmt->close();


/* =========================================
   OBTENER CANDIDATOS
========================================= */

$candidatos = $conn->query("
    SELECT
        id,
        nombre,
        apellido,
        curso,
        foto,
        propuestas
    FROM candidatos
    ORDER BY nombre ASC, apellido ASC
");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Votación por Jurado
</title>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =========================================
     ICONOS
========================================= -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================
   BARRA SUPERIOR
========================================= */

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


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:1150px;

    margin:auto;

    padding:35px 20px;

}


/* =========================================
   TARJETAS
========================================= */

.card-custom {

    background:white;

    border-radius:18px;

    padding:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


/* =========================================
   TÍTULOS
========================================= */

.titulo {

    color:#1453a3;

    font-weight:bold;

}


/* =========================================
   ESTUDIANTE
========================================= */

.estudiante {

    background:#cfe2ff;

    border:
        1px solid #9ec5fe;

    border-radius:15px;

    padding:20px;

    color:#084298;

}


.estudiante h3 {

    margin-bottom:5px;

}


/* =========================================
   ESTADOS
========================================= */

.estado-abierta {

    background:#198754;

    color:white;

    padding:7px 16px;

    border-radius:7px;

    font-weight:bold;

}


.estado-cerrada {

    background:#dc3545;

    color:white;

    padding:7px 16px;

    border-radius:7px;

    font-weight:bold;

}


/* =========================================
   CANDIDATO
========================================= */

.candidato {

    background:white;

    border-radius:18px;

    padding:25px;

    height:100%;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    transition:.2s;

}


.candidato:hover {

    transform:
        translateY(-3px);

}


.foto-candidato {

    width:130px;

    height:130px;

    object-fit:cover;

    border-radius:50%;

    border:5px solid #e5edf8;

}


.foto-vacia {

    width:130px;

    height:130px;

    border-radius:50%;

    background:#dbe8f8;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:auto;

    font-size:55px;

    color:#1453a3;

}


/* =========================================
   BOTÓN VOTAR
========================================= */

.btn-votar {

    width:100%;

    padding:12px;

    font-size:17px;

    font-weight:bold;

}


/* =========================================
   BOTÓN CAMBIAR ESTUDIANTE
========================================= */

.btn-cambiar {

    padding:10px 20px;

}


/* =========================================
   BLOQUEADO
========================================= */

.bloqueado {

    opacity:.75;

}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:700px) {

    .topbar {

        padding:15px;

    }

    .contenedor {

        padding:20px 15px;

    }

}

</style>

</head>


<body>


<!-- =========================================
     BARRA SUPERIOR
========================================= -->

<div class="topbar">


<div>

<i class="bi bi-mortarboard-fill"></i>

<strong>

Sistema de Votaciones Escolares

</strong>

</div>


<div>

<i class="bi bi-person-badge-fill"></i>

Jurado:

<strong>

<?php echo htmlspecialchars(
    $_SESSION['nombre'] ?? ''
); ?>

</strong>

</div>


</div>


<div class="contenedor">


<!-- =========================================
     INFORMACIÓN ESTUDIANTE
========================================= -->

<div class="estudiante mb-4">


<div
class="d-flex
       justify-content-between
       align-items-center
       flex-wrap
       gap-3">


<div>


<h3>

<i class="bi bi-person-fill"></i>

<?php echo htmlspecialchars(
    $estudiante['nombre'] . " " .
    $estudiante['apellido']
); ?>

</h3>


<p class="mb-1">

<strong>
Documento:
</strong>

<?php echo htmlspecialchars(
    $estudiante['documento']
); ?>

</p>


<p class="mb-0">

<strong>
Curso:
</strong>

<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>

</p>


</div>


<a
href="jurado.php"
class="btn btn-primary btn-cambiar">

<i class="bi bi-arrow-left"></i>

Cambiar estudiante

</a>


</div>

</div>


<!-- =========================================
     ELECCIÓN
========================================= -->

<div class="card-custom mb-4">


<?php if ($eleccionExiste) { ?>


<div
class="d-flex
       justify-content-between
       align-items-center
       flex-wrap
       gap-3">


<div>


<h2 class="titulo mb-1">

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h2>


<p class="text-muted mb-0">

<?php echo htmlspecialchars(
    $eleccion['descripcion'] ?? ''
); ?>

</p>


</div>


<div>


<?php if ($eleccionAbierta) { ?>

<span class="estado-abierta">

🟢 Elección abierta

</span>

<?php } else { ?>

<span class="estado-cerrada">

🔴 Elección cerrada

</span>

<?php } ?>


</div>


</div>


<?php } else { ?>


<div class="alert alert-warning mb-0">

<i class="bi bi-exclamation-triangle-fill"></i>

No existe una elección registrada.

</div>


<?php } ?>


</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div
class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     ELECCIÓN CERRADA
========================================= -->

<?php if (!$eleccionAbierta) { ?>


<div
class="alert alert-danger text-center p-5">

<i
class="bi bi-lock-fill"
style="font-size:60px;">
</i>


<h2 class="mt-3">

Votación cerrada

</h2>


<p>

El administrador ha cerrado la elección
o todavía no la ha abierto.

</p>


<a
href="jurado.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Volver al jurado

</a>


</div>


<?php } elseif ($yaVoto) { ?>


<!-- =========================================
     YA VOTÓ
========================================= -->

<div
class="alert alert-success text-center p-5">

<i
class="bi bi-check-circle-fill"
style="font-size:60px;">
</i>


<h2 class="mt-3">

Voto registrado

</h2>


<p>

Este estudiante ya realizó su voto.

</p>


<a
href="jurado.php"
class="btn btn-primary">

<i class="bi bi-person-plus-fill"></i>

Ingresar otro estudiante

</a>


</div>


<?php } else { ?>


<!-- =========================================
     CANDIDATOS
========================================= -->

<div class="card-custom">


<h2 class="titulo mb-4">

<i class="bi bi-person-vcard-fill"></i>

Seleccione el candidato

</h2>


<div class="row g-4">


<?php if (
    !$candidatos ||
    $candidatos->num_rows === 0
) { ?>


<div class="col-12">

<div class="alert alert-warning">

<i
class="bi bi-exclamation-triangle-fill">
</i>

No hay candidatos registrados.

</div>

</div>


<?php } else { ?>


<?php while (
    $candidato =
    $candidatos->fetch_assoc()
) { ?>


<div class="col-md-6 col-lg-4">


<div class="candidato">


<!-- FOTO -->

<div class="text-center mb-3">


<?php

$foto =
    trim(
        $candidato['foto'] ?? ''
    );


if (
    $foto !== "" &&
    file_exists(
        __DIR__ . "/" . $foto
    )
) {

?>


<img

src="<?php echo htmlspecialchars(
    $foto
); ?>"

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

<h4 class="text-center">

<?php echo htmlspecialchars(
    $candidato['nombre'] . " " .
    $candidato['apellido']
); ?>

</h4>


<p class="text-center text-muted">

Curso:

<strong>

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</strong>

</p>


<!-- PROPUESTAS -->

<?php if (
    !empty($candidato['propuestas'])
) { ?>


<div class="alert alert-light">

<strong>

Propuestas:

</strong>

<br>

<?php echo nl2br(
    htmlspecialchars(
        $candidato['propuestas']
    )
); ?>

</div>


<?php } ?>


<!-- BOTÓN VOTAR -->

<form

method="POST"

onsubmit="
return confirm(
'¿Está seguro de registrar este voto?'
);
">


<input

type="hidden"

name="id_candidato"

value="<?php echo (int)$candidato['id']; ?>">


<button

type="submit"

name="votar"

class="btn btn-primary btn-votar">


<i
class="bi bi-check-circle-fill">
</i>

Registrar voto

</button>


</form>


</div>

</div>


<?php } ?>


<?php } ?>


</div>


</div>


<?php } ?>


<!-- =========================================
     CERRAR SESIÓN
========================================= -->

<div class="text-center mt-4">


<a
href="logout.php"
class="btn btn-outline-danger">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>


</div>


</body>

</html>
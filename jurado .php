<?php
session_start();

/* =========================================
   VERIFICAR SESIÓN DEL JURADO
========================================= */

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'jurado') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

$mensaje = "";
$tipoMensaje = "";

/* =========================================
   INGRESAR ESTUDIANTE
========================================= */

if (isset($_POST['ingresar_estudiante'])) {

    $documento = trim($_POST['documento']);

    if ($documento == "") {

        $mensaje = "Debe ingresar el documento del estudiante.";
        $tipoMensaje = "danger";

    } else {

        /* Buscar estudiante */

        $consulta = $conn->query("
            SELECT *
            FROM usuarios
            WHERE documento='$documento'
            AND rol='estudiante'
            LIMIT 1
        ");

        if ($consulta->num_rows == 0) {

            $mensaje = "No se encontró un estudiante con ese documento.";
            $tipoMensaje = "danger";

        } else {

            $estudiante = $consulta->fetch_assoc();

            /* Guardar estudiante en la sesión del jurado */

            $_SESSION['estudiante_jurado'] = $estudiante['id'];

            /* Ir a la pantalla de votación */

            header("Location: votar_jurado.php");
            exit();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Jurado de Votación</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link
rel="stylesheet"
href="css/estilos.css">

<style>

body {

    background:linear-gradient(
        135deg,
        #0d47a1,
        #1565c0
    );

    min-height:100vh;

    display:flex;

    justify-content:center;

    align-items:center;

}

.jurado {

    width:450px;

    background:white;

    padding:40px;

    border-radius:20px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.25);

}

.logo {

    font-size:70px;

    text-align:center;

    margin-bottom:15px;

}

.jurado h2 {

    text-align:center;

    color:#0d47a1;

    margin-bottom:10px;

}

.subtitulo {

    text-align:center;

    color:#777;

    margin-bottom:30px;

}

.btn-ingresar {

    width:100%;

    padding:12px;

    font-size:18px;

}

.info-jurado {

    background:#eef5ff;

    border-radius:10px;

    padding:15px;

    margin-bottom:25px;

}

</style>

</head>

<body>

<div class="jurado">

<div class="logo">

🗳️

</div>

<h2>

Jurado de Votación

</h2>

<p class="subtitulo">

Ingreso de estudiantes

</p>


<!-- =========================================
     INFORMACIÓN DEL JURADO
========================================= -->

<div class="info-jurado">

<strong>

<i class="bi bi-person-badge-fill"></i>

Jurado:

</strong>

<?php

echo htmlspecialchars(
$_SESSION['nombre']
);

?>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje != "") { ?>

<div class="alert alert-<?php
echo $tipoMensaje;
?>">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php

echo htmlspecialchars($mensaje);

?>

</div>

<?php } ?>


<!-- =========================================
     FORMULARIO ESTUDIANTE
========================================= -->

<form method="POST">

<div class="mb-4">

<label class="form-label">

<i class="bi bi-person-vcard-fill"></i>

Documento del estudiante

</label>

<div class="input-group">

<span class="input-group-text">

<i class="bi bi-person-fill"></i>

</span>

<input

type="text"

name="documento"

class="form-control form-control-lg"

placeholder="Ingrese el documento"

autocomplete="off"

required

autofocus>

</div>

</div>


<button

type="submit"

name="ingresar_estudiante"

class="btn btn-primary btn-ingresar">

<i class="bi bi-box-arrow-in-right"></i>

Ingresar estudiante

</button>

</form>


<hr class="my-4">


<!-- =========================================
     CERRAR SESIÓN
========================================= -->

<div class="text-center">

<a

href="logout.php"

class="btn btn-outline-danger">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión del jurado

</a>

</div>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>
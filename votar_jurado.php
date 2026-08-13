<?php
session_start();

/* =========================================
   VERIFICAR JURADO
========================================= */

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'jurado') {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");

/* =========================================
   VERIFICAR ESTUDIANTE
========================================= */

if (!isset($_SESSION['estudiante_jurado'])) {

    header("Location: jurado.php");
    exit();

}

$idEstudiante = (int) $_SESSION['estudiante_jurado'];

/* =========================================
   BUSCAR ESTUDIANTE
========================================= */

$consultaEstudiante = $conn->query("
    SELECT *
    FROM usuarios
    WHERE id=$idEstudiante
    AND rol='estudiante'
    LIMIT 1
");

if ($consultaEstudiante->num_rows == 0) {

    unset($_SESSION['estudiante_jurado']);

    header("Location: jurado.php");
    exit();

}

$estudiante = $consultaEstudiante->fetch_assoc();

/* =========================================
   BUSCAR ELECCIÓN ABIERTA
========================================= */

$consultaEleccion = $conn->query("
    SELECT *
    FROM elecciones
    WHERE estado='abierta'
    LIMIT 1
");

if ($consultaEleccion->num_rows == 0) {

    die("
    <div style='text-align:center;
    margin-top:100px;
    font-family:Arial;'>

        <h2>No hay elecciones abiertas.</h2>

        <a href='jurado.php'>
            Volver
        </a>

    </div>
    ");

}

$eleccion = $consultaEleccion->fetch_assoc();

/* =========================================
   BUSCAR CARGOS
========================================= */

$cargos = $conn->query("
    SELECT cargos.*
    FROM cargos

    INNER JOIN eleccion_cargos
    ON cargos.id = eleccion_cargos.id_cargo

    WHERE eleccion_cargos.id_eleccion=".$eleccion['id']."

    ORDER BY cargos.id
");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Votación</title>

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

body{

background:#eef3f9;

}

.card-candidato{

transition:.3s;

}

.card-candidato:hover{

transform:scale(1.03);

}

.foto{

width:150px;

height:150px;

border-radius:50%;

object-fit:cover;

margin:auto;

border:4px solid #0d6efd;

}

.tarjeton{

font-size:34px;

font-weight:bold;

color:#0d6efd;

}

.estudiante-box{

background:white;

border-radius:15px;

padding:20px;

box-shadow:0 5px 15px rgba(0,0,0,.12);

}

.finalizar{

font-size:20px;

padding:15px 30px;

}

</style>

</head>

<body>

<div class="container mt-4">

<!-- =========================================
     MENSAJE DE VOTO
========================================= -->

<?php if(isset($_GET['ok'])){ ?>

<div class="alert alert-success alert-dismissible fade show">

<i class="bi bi-check-circle-fill"></i>

<strong>¡Voto registrado correctamente!</strong>

El voto del estudiante fue guardado.

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php } ?>


<?php if(isset($_GET['error']) &&
$_GET['error']=="duplicado"){ ?>

<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>Este estudiante ya votó por este cargo.</strong>

</div>

<?php } ?>


<?php if(isset($_GET['error']) &&
$_GET['error']=="bd"){ ?>

<div class="alert alert-danger">

<i class="bi bi-x-circle-fill"></i>

<strong>Error al registrar el voto.</strong>

</div>

<?php } ?>


<!-- =========================================
     INFORMACIÓN DEL ESTUDIANTE
========================================= -->

<div class="estudiante-box mb-4">

<div class="row align-items-center">

<div class="col-md-8">

<h4>

<i class="bi bi-person-check-fill text-primary"></i>

Estudiante que está votando

</h4>

<p class="mb-1">

<strong>Nombre:</strong>

<?php

echo htmlspecialchars(
$estudiante['nombre']." ".$estudiante['apellido']
);

?>

</p>

<p class="mb-1">

<strong>Documento:</strong>

<?php

echo htmlspecialchars(
$estudiante['documento']
);

?>

</p>

<p class="mb-0">

<strong>Curso:</strong>

<?php

echo htmlspecialchars(
$estudiante['curso']
);

?>

</p>

</div>

<div class="col-md-4 text-md-end mt-3 mt-md-0">

<a
href="jurado.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Cambiar estudiante

</a>

</div>

</div>

</div>


<!-- =========================================
     INFORMACIÓN ELECCIÓN
========================================= -->

<div class="text-center">

<h1>

🗳️ <?php

echo htmlspecialchars(
$eleccion['nombre']
);

?>

</h1>

<p class="text-muted">

<?php

echo htmlspecialchars(
$eleccion['descripcion']
);

?>

</p>

<hr>

</div>


<?php

/* =========================================
   RECORRER CARGOS
========================================= */

while($cargo = $cargos->fetch_assoc()){

/* =========================================
   VERIFICAR SI YA VOTÓ
========================================= */

$yaVoto = $conn->query("

SELECT id

FROM votos

WHERE id_usuario=$idEstudiante

AND id_eleccion=".$eleccion['id']."

AND id_cargo=".$cargo['id']."

LIMIT 1

");

$votado = ($yaVoto->num_rows > 0);

?>

<div class="card shadow mb-5">

<div class="card-header bg-primary text-white">

<h3 class="mb-0">

<?php

echo htmlspecialchars(
$cargo['nombre_cargo']
);

?>

</h3>

</div>

<div class="card-body">

<div class="row">

<?php

/* =========================================
   CANDIDATOS
========================================= */

$candidatos = $conn->query("

SELECT *

FROM candidatos

WHERE id_cargo=".$cargo['id']."

AND id_eleccion=".$eleccion['id']."

ORDER BY numero_tarjeton

");

if($candidatos->num_rows == 0){

?>

<div class="col-12">

<div class="alert alert-warning">

No existen candidatos para este cargo.

</div>

</div>

<?php

}

while($candidato =
$candidatos->fetch_assoc()){

?>

<div class="col-lg-4 col-md-6 mb-4">

<div class="card card-candidato h-100 shadow-sm">

<div class="card-body text-center">

<?php

if(

$candidato['foto'] != "" &&

file_exists(
"uploads/candidatos/"
.$candidato['foto']
)

){

?>

<img

src="uploads/candidatos/<?php

echo htmlspecialchars(
$candidato['foto']
);

?>"

class="foto">

<?php

}else{

?>

<img

src="https://via.placeholder.com/150?text=Sin+Foto"

class="foto">

<?php

}

?>

<br><br>

<div class="tarjeton">

#

<?php

echo htmlspecialchars(
$candidato['numero_tarjeton']
);

?>

</div>

<h4>

<?php

echo htmlspecialchars(

$candidato['nombre']
." ".
$candidato['apellido']

);

?>

</h4>

<p>

<strong>Curso:</strong>

<?php

echo htmlspecialchars(
$candidato['curso']
);

?>

</p>

<p style="min-height:90px;">

<?php

echo nl2br(

htmlspecialchars(
$candidato['propuestas']
)

);

?>

</p>


<!-- =====================================
     BOTÓN
===================================== -->

<?php if($votado){ ?>

<button
type="button"
class="btn btn-secondary w-100"
disabled>

<i class="bi bi-check-circle-fill"></i>

Ya votaste

</button>

<?php }else{ ?>

<form
method="POST"
action="registrar_voto_jurado.php">

<input
type="hidden"
name="id_candidato"
value="<?php

echo $candidato['id'];

?>">

<input
type="hidden"
name="id_cargo"
value="<?php

echo $cargo['id'];

?>">

<input
type="hidden"
name="id_eleccion"
value="<?php

echo $eleccion['id'];

?>">

<button
type="submit"
class="btn btn-success w-100"
onclick="return confirm(
'¿Está seguro de registrar este voto?'
);">

<i class="bi bi-check-circle-fill"></i>

Votar

</button>

</form>

<?php } ?>

</div>

</div>

</div>

<?php

}

?>

</div>

</div>

</div>

<?php

}

?>


<!-- =========================================
     FINALIZAR
========================================= -->

<div class="text-center mb-5">

<a
href="jurado.php"
class="btn btn-primary btn-lg finalizar">

<i class="bi bi-person-plus-fill"></i>

Finalizar votación y atender otro estudiante

</a>

</div>


<!-- =========================================
     CERRAR SESIÓN
========================================= -->

<div class="text-center mb-5">

<a
href="logout.php"
class="btn btn-danger">

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
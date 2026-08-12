<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'estudiante') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=      BUSCAR ELECCIÓN ABIERTA            =
=========================================*/

$consultaEleccion = $conn->query("
SELECT *
FROM elecciones
WHERE estado='abierta'
LIMIT 1
");

if($consultaEleccion->num_rows == 0){

    die("
    <div style='text-align:center;
    margin-top:100px;
    font-family:Arial;'>

        <h2>No hay elecciones abiertas.</h2>

    </div>
    ");

}

$eleccion = $consultaEleccion->fetch_assoc();

/*=========================================
=      DATOS DEL ESTUDIANTE              =
=========================================*/

$idEstudiante = $_SESSION['id'];

$estudiante = $conn->query("
SELECT *
FROM usuarios
WHERE id=$idEstudiante
")->fetch_assoc();

/*=========================================
=      CARGOS DE LA ELECCIÓN             =
=========================================*/

$cargos = $conn->query("
SELECT
cargos.*

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

<title><?php echo $eleccion['nombre']; ?></title>

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

</style>

</head>

<body>

<div class="container mt-4">

<?php

if(isset($_GET['ok'])){

?>

<div class="alert alert-success">

<i class="bi bi-check-circle-fill"></i>

¡Su voto fue registrado correctamente!

</div>

<?php

}

?>

<?php

if(isset($_GET['error']) && $_GET['error']=="duplicado"){

?>

<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

Usted ya votó por este cargo.

</div>

<?php

}

?>

<?php

if(isset($_GET['error']) && $_GET['error']=="bd"){

?>

<div class="alert alert-danger">

<i class="bi bi-x-circle-fill"></i>

Ocurrió un error al registrar el voto.

</div>

<?php

}

?>

<div class="text-center">

<h1>

🗳 <?php echo $eleccion['nombre']; ?>

</h1>

<p class="text-muted">

<?php echo $eleccion['descripcion']; ?>

</p>

<h5>

Bienvenido

<strong><?php echo $_SESSION['nombre']; ?></strong>

</h5>

<hr>

</div>
<?php

/*=========================================
=      RECORRER LOS CARGOS DE LA ELECCIÓN =
=========================================*/

while($cargo = $cargos->fetch_assoc()){
    /*=========================================
VERIFICAR SI YA VOTÓ ESTE CARGO
=========================================*/

$yaVoto = $conn->query("
SELECT id
FROM votos
WHERE id_usuario=".$_SESSION['id']."
AND id_eleccion=".$eleccion['id']."
AND id_cargo=".$cargo['id']."
LIMIT 1
");

$votado = ($yaVoto->num_rows > 0);

?>

<div class="card shadow mb-5">

<div class="card-header bg-primary text-white">

<h3 class="mb-0">

<?php echo $cargo['nombre_cargo']; ?>

</h3>

</div>

<div class="card-body">

<div class="row">

<?php

$candidatos = $conn->query("

SELECT *

FROM candidatos

WHERE id_cargo = ".$cargo['id']."

AND id_eleccion = ".$eleccion['id']."

ORDER BY numero_tarjeton

");

if($candidatos->num_rows==0){

?>

<div class="col-12">

<div class="alert alert-warning">

No existen candidatos para este cargo.

</div>

</div>

<?php

}

while($candidato = $candidatos->fetch_assoc()){

?>

<div class="col-lg-4 col-md-6 mb-4">

<div class="card card-candidato h-100 shadow-sm">

<div class="card-body text-center">

<?php

if(

$candidato['foto']!="" &&

file_exists("uploads/candidatos/".$candidato['foto'])

){

?>

<img

src="uploads/candidatos/<?php echo $candidato['foto']; ?>"

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

#<?php echo $candidato['numero_tarjeton']; ?>

</div>

<h4>

<?php

echo $candidato['nombre']." ".$candidato['apellido'];

?>

</h4>

<p>

<strong>Curso:</strong>

<?php echo $candidato['curso']; ?>

</p>

<p style="min-height:90px;">

<?php echo nl2br($candidato['propuestas']); ?>

</p>
<form method="POST" action="registrar_voto.php">

<input
type="hidden"
name="id_candidato"
value="<?php echo $candidato['id']; ?>">

<input
type="hidden"
name="id_cargo"
value="<?php echo $cargo['id']; ?>">

<input
type="hidden"
name="id_eleccion"
value="<?php echo $eleccion['id']; ?>">

<?php if($votado){ ?>

<button
type="button"
class="btn btn-secondary w-100"
disabled>

<i class="bi bi-check-circle-fill"></i>

Ya votaste

</button>

<?php }else{ ?>

<button
type="submit"
class="btn btn-success w-100"
onclick="return confirm('¿Está seguro de votar por este candidato?');">

<i class="bi bi-check-circle-fill"></i>

Votar

</button>

<?php } ?>

</form>

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

<div class="text-center mb-5">

<a
href="logout.php"
class="btn btn-danger">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
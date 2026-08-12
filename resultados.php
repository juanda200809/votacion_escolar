<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=      LISTAR ELECCIONES                 =
=========================================*/

$elecciones = $conn->query("
SELECT *
FROM elecciones
ORDER BY fecha_inicio DESC
");

/*=========================================
=      ELECCIÓN SELECCIONADA             =
=========================================*/

$idEleccion = 0;

if(isset($_GET['id_eleccion'])){

    $idEleccion = (int)$_GET['id_eleccion'];

}

/*=========================================
=      DATOS DE LA ELECCIÓN              =
=========================================*/

$datosEleccion = null;

if($idEleccion>0){

    $consulta = $conn->query("
    SELECT *
    FROM elecciones
    WHERE id=$idEleccion
    ");

    if($consulta->num_rows>0){

        $datosEleccion = $consulta->fetch_assoc();

    }

}

/*=========================================
=      CARGOS DE LA ELECCIÓN             =
=========================================*/

$cargos = null;

if($idEleccion>0){

    $cargos = $conn->query("

    SELECT cargos.*

    FROM cargos

    INNER JOIN eleccion_cargos

    ON cargos.id = eleccion_cargos.id_cargo

    WHERE eleccion_cargos.id_eleccion = $idEleccion

    ORDER BY cargos.id

    ");

}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Resultados Oficiales</title>

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

background:#eef2f7;

}

.card{

margin-bottom:30px;

box-shadow:0 5px 15px rgba(0,0,0,.15);

}

.ganador{

background:#d1fae5;

font-weight:bold;

}

.info-eleccion{

background:white;

padding:20px;

border-radius:10px;

box-shadow:0 5px 15px rgba(0,0,0,.1);

margin-bottom:30px;

}

</style>

</head>

<body>

<div class="container mt-5">

<h1 class="text-center mb-4">

🏆 Resultados Oficiales

</h1>

<div class="info-eleccion">

<form method="GET">

<div class="row align-items-end">

<div class="col-md-9">

<label class="form-label">

Seleccione una elección

</label>

<select
name="id_eleccion"
class="form-select"
required>

<option value="">

Seleccione...

</option>

<?php

while($e = $elecciones->fetch_assoc()){

?>

<option

value="<?php echo $e['id']; ?>"

<?php

if($idEleccion==$e['id']){

echo "selected";

}

?>

>

<?php echo $e['nombre']; ?>

</option>

<?php

}

?>

</select>

</div>

<div class="col-md-3">

<button
class="btn btn-primary w-100">

<i class="bi bi-search"></i>

Ver Resultados

</button>

</div>

</div>

</form>

</div>
<?php

if($datosEleccion!=null){

?>

<div class="card">

<div class="card-body">

<h3>

<?php echo $datosEleccion['nombre']; ?>

</h3>

<p>

<?php echo $datosEleccion['descripcion']; ?>

</p>

<div class="row">

<div class="col-md-4">

<strong>Fecha Inicio</strong>

<br>

<?php echo $datosEleccion['fecha_inicio']; ?>

</div>

<div class="col-md-4">

<strong>Fecha Fin</strong>

<br>

<?php echo $datosEleccion['fecha_fin']; ?>

</div>

<div class="col-md-4">

<strong>Estado</strong>

<br>

<?php

if($datosEleccion['estado']=="abierta"){

?>

<span class="badge bg-success">

Abierta

</span>

<?php

}else{

?>

<span class="badge bg-danger">

Cerrada

</span>

<?php

}

?>

</div>

</div>

</div>

</div>

<?php

}

?>
<?php

if($cargos != null){

while($cargo = $cargos->fetch_assoc()){

/*=========================================
=      TOTAL DE VOTOS DEL CARGO          =
=========================================*/

$totalVotos = $conn->query("

SELECT COUNT(*) total

FROM votos

INNER JOIN candidatos

ON votos.id_candidato = candidatos.id

WHERE votos.id_eleccion = $idEleccion

AND candidatos.id_cargo = ".$cargo['id']."

")->fetch_assoc()['total'];

/*=========================================
=      RESULTADOS                        =
=========================================*/

$resultados = $conn->query("

SELECT

candidatos.id,
candidatos.nombre,
candidatos.apellido,
candidatos.curso,
candidatos.foto,
candidatos.numero_tarjeton,

COUNT(votos.id) AS total

FROM candidatos

LEFT JOIN votos

ON candidatos.id = votos.id_candidato

AND votos.id_eleccion = $idEleccion

WHERE candidatos.id_cargo = ".$cargo['id']."

GROUP BY candidatos.id

ORDER BY total DESC,
candidatos.numero_tarjeton ASC

");

?>

<div class="card">

<div class="card-header bg-primary text-white">

<h3>

<?php echo $cargo['nombre_cargo']; ?>

</h3>

</div>

<div class="card-body">

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>Foto</th>

<th>Tarjetón</th>

<th>Nombre</th>

<th>Curso</th>

<th>Votos</th>

<th>%</th>

<th>Estado</th>

</tr>

</thead>

<tbody>

<?php

$primero = true;

while($r = $resultados->fetch_assoc()){

$porcentaje = 0;

if($totalVotos > 0){

$porcentaje = round(($r['total']/$totalVotos)*100,2);

}

?>

<tr class="<?php if($primero && $r['total']>0) echo 'ganador'; ?>">

<td>

<?php

if(

$r['foto']!="" &&

file_exists("uploads/candidatos/".$r['foto'])

){

?>

<img

src="uploads/candidatos/<?php echo $r['foto']; ?>"

style="width:70px;height:70px;border-radius:50%;object-fit:cover;">

<?php

}else{

?>

<img

src="https://via.placeholder.com/70?text=Foto"

style="border-radius:50%;">

<?php

}

?>

</td>

<td>

<?php echo $r['numero_tarjeton']; ?>

</td>

<td>

<?php echo $r['nombre']." ".$r['apellido']; ?>

</td>

<td>

<?php echo $r['curso']; ?>

</td>

<td>

<strong>

<?php echo $r['total']; ?>

</strong>

</td>

<td>

<?php echo $porcentaje; ?>%

</td>

<td>

<?php

if($primero && $r['total']>0){

?>

<span class="badge bg-success">

🥇 Ganador

</span>

<?php

}else{

?>

<span class="badge bg-secondary">

Participante

</span>

<?php

}

?>

</td>

</tr>

<?php

$primero = false;

}

?>

</tbody>

</table>

</div>

</div>

<?php

}

}

?>
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

echo "<h2>ID de elección: ".$idEleccion."</h2>";
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

    ON cargos.id=eleccion_cargos.id_cargo

    WHERE eleccion_cargos.id_eleccion=$idEleccion

    ORDER BY cargos.id
    ");

}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gráficas de Resultados</title>

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

</style>

</head>

<body>

<div class="container mt-5">

<h1 class="text-center mb-4">

📊 Gráficas de Resultados

</h1>

<div class="card">

<div class="card-body">

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

while($e=$elecciones->fetch_assoc()){

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

<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas

</button>

</div>

</div>

</form>

</div>

</div>
<?php

if($datosEleccion!=null){

$totalVotos = $conn->query("
SELECT COUNT(*) total
FROM votos
WHERE id_eleccion=$idEleccion
")->fetch_assoc()['total'];

?>

<div class="card">

<div class="card-body">

<div class="row">

<div class="col-md-6">

<h3>

<?php echo $datosEleccion['nombre']; ?>

</h3>

<p>

<?php echo $datosEleccion['descripcion']; ?>

</p>

</div>

<div class="col-md-6">

<table class="table">

<tr>

<td><strong>Estado</strong></td>

<td>

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

</td>

</tr>

<tr>

<td>

<strong>Total de votos</strong>

</td>

<td>

<?php echo $totalVotos; ?>

</td>

</tr>

<tr>

<td>

<strong>Fecha Inicio</strong>

</td>

<td>

<?php echo $datosEleccion['fecha_inicio']; ?>

</td>

</tr>

<tr>

<td>

<strong>Fecha Fin</strong>

</td>

<td>

<?php echo $datosEleccion['fecha_fin']; ?>

</td>

</tr>

</table>

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

$resultados = $conn->query("

SELECT

candidatos.nombre,
candidatos.apellido,

COUNT(votos.id) total

FROM candidatos

LEFT JOIN votos

ON candidatos.id=votos.id_candidato

AND votos.id_eleccion=$idEleccion

WHERE candidatos.id_cargo=".$cargo['id']."

GROUP BY candidatos.id

ORDER BY total DESC

");

$nombres=[];

$votos=[];

while($r=$resultados->fetch_assoc()){

$nombres[]=$r['nombre']." ".$r['apellido'];

$votos[]=$r['total'];

}

?>

<div class="card">

<div class="card-header bg-primary text-white">

<h4>

<?php echo $cargo['nombre_cargo']; ?>

</h4>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<canvas id="barra<?php echo $cargo['id']; ?>"></canvas>

</div>

<div class="col-md-6">

<canvas id="pastel<?php echo $cargo['id']; ?>"></canvas>

</div>

</div>

</div>

</div>

<script>

const nombres<?php echo $cargo['id']; ?> =
<?php echo json_encode($nombres); ?>;

const votos<?php echo $cargo['id']; ?> =
<?php echo json_encode($votos); ?>;

/*==========================
GRÁFICA DE BARRAS
==========================*/

new Chart(

document.getElementById("barra<?php echo $cargo['id']; ?>"),

{

type:'bar',

data:{

labels:nombres<?php echo $cargo['id']; ?>,

datasets:[{

label:'Votos',

data:votos<?php echo $cargo['id']; ?>,

backgroundColor:[
'#0d6efd',
'#198754',
'#ffc107',
'#dc3545',
'#6f42c1',
'#20c997',
'#fd7e14'
]

}]

},

options:{

responsive:true,

plugins:{

legend:{
display:false
}

}

}

}

/*==========================
GRÁFICA DE PASTEL
==========================*/

);

new Chart(

document.getElementById("pastel<?php echo $cargo['id']; ?>"),

{

type:'pie',

data:{

labels:nombres<?php echo $cargo['id']; ?>,

datasets:[{

data:votos<?php echo $cargo['id']; ?>,

backgroundColor:[

'#0d6efd',
'#198754',
'#ffc107',
'#dc3545',
'#6f42c1',
'#20c997',
'#fd7e14'

]

}]

},

options:{

responsive:true

}

}

);

</script>

<?php

}

}

?>
<?php

if($datosEleccion!=null){

$ganadores = $conn->query("

SELECT

cargos.nombre_cargo,

candidatos.nombre,

candidatos.apellido,

COUNT(votos.id) total

FROM candidatos

INNER JOIN cargos

ON candidatos.id_cargo=cargos.id

LEFT JOIN votos

ON candidatos.id=votos.id_candidato

AND votos.id_eleccion=$idEleccion

GROUP BY candidatos.id

ORDER BY cargos.id,total DESC

");

?>

<div class="card">

<div class="card-header bg-success text-white">

<h3>

🏆 Ganadores por Cargo

</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th>Cargo</th>

<th>Ganador</th>

<th>Votos</th>

</tr>

<?php

$cargoActual="";

while($g=$ganadores->fetch_assoc()){

if($cargoActual!=$g['nombre_cargo']){

$cargoActual=$g['nombre_cargo'];

?>

<tr>

<td>

<?php echo $g['nombre_cargo']; ?>

</td>

<td>

<?php echo $g['nombre']." ".$g['apellido']; ?>

</td>

<td>

<?php echo $g['total']; ?>

</td>

</tr>

<?php

}

}

?>

</table>

</div>

</div>

<?php

}

?>

<div class="text-center mb-5">

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

<a
href="pdf_resultados.php?id_eleccion=<?php echo $idEleccion; ?>"
class="btn btn-danger">

<i class="bi bi-file-earmark-pdf-fill"></i>

Descargar PDF

</a>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
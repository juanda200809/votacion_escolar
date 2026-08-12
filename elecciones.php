<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

$elecciones = $conn->query("
SELECT *
FROM elecciones
ORDER BY fecha_inicio DESC
");
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Gestión de Elecciones</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="css/estilos.css">

</head>

<body>

<div class="container mt-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-calendar-event-fill"></i>

Gestión de Elecciones

</h2>

<a href="crear_eleccion.php" class="btn btn-success">

<i class="bi bi-plus-circle-fill"></i>

Nueva Elección

</a>

</div>

<table class="table table-bordered table-hover align-middle">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Nombre</th>

<th>Estado</th>

<th>Inicio</th>

<th>Fin</th>

<th width="220">Acciones</th>

</tr>

</thead>

<tbody>

<?php while($e=$elecciones->fetch_assoc()){ ?>

<tr>

<td><?php echo $e['id']; ?></td>

<td><?php echo $e['nombre']; ?></td>

<td>

<?php

if($e['estado']=="abierta"){

echo "<span class='badge bg-success'>Abierta</span>";

}else{

echo "<span class='badge bg-danger'>Cerrada</span>";

}

?>

</td>

<td><?php echo $e['fecha_inicio']; ?></td>

<td><?php echo $e['fecha_fin']; ?></td>

<td>

<a
href="editar_eleccion.php?id=<?php echo $e['id']; ?>"
class="btn btn-primary btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>

<a
href="eliminar_eleccion.php?id=<?php echo $e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar esta elección?')">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<a href="admin.php" class="btn btn-secondary">

<i class="bi bi-arrow-left-circle"></i>

Volver

</a>

</div>

</body>

</html>
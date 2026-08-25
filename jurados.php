<?php

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

$mensaje = "";
$tipoMensaje = "";


/* =========================================
   ELIMINAR JURADO
========================================= */

if (isset($_GET['eliminar'])) {

    $id = (int)$_GET['eliminar'];

    $stmt = $conn->prepare("
        DELETE FROM usuarios
        WHERE id = ?
        AND rol = 'jurado'
    ");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $mensaje = "Jurado eliminado correctamente.";
        $tipoMensaje = "success";
    } else {
        $mensaje = "No se pudo eliminar el jurado.";
        $tipoMensaje = "danger";
    }

    $stmt->close();
}


/* =========================================
   LISTAR JURADOS
========================================= */

$jurados = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso,
        fecha_registro
    FROM usuarios
    WHERE rol = 'jurado'
    ORDER BY id DESC
");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Gestión de Jurados</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

body {
    background:#eef3f9;
}

.contenedor {
    max-width:1200px;
    margin:auto;
    padding:35px 20px;
}

.titulo {
    color:#0d47a1;
    font-weight:bold;
}

.card {
    border:none;
    border-radius:18px;
    box-shadow:0 6px 20px rgba(0,0,0,.10);
}

.encabezado {
    background:#0d47a1;
    color:white;
    border-radius:18px 18px 0 0;
    padding:20px;
}

.tabla thead {
    background:#cfe0ff;
}

.btn-editar {
    background:#ffc107;
    color:#000;
    border:none;
}

.btn-editar:hover {
    background:#e0a800;
}

.btn-eliminar {
    background:#dc3545;
    color:white;
    border:none;
}

.btn-eliminar:hover {
    background:#bb2d3b;
}

</style>

</head>

<body>

<div class="contenedor">

<!-- =========================================
     CABECERA
========================================= -->

<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3
            mb-4">

<div>

<h2 class="titulo">

<i class="bi bi-person-badge-fill"></i>

Gestión de Jurados

</h2>

<p class="text-muted mb-0">

Administra los jurados encargados de las votaciones.

</p>

</div>

<div>

<a
href="admin.php"
class="btn btn-outline-primary">

<i class="bi bi-arrow-left"></i>

Volver al panel

</a>

<a
href="crear_jurado.php"
class="btn btn-primary">

<i class="bi bi-person-plus-fill"></i>

Nuevo jurado

</a>

</div>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     TABLA
========================================= -->

<div class="card">

<div class="encabezado">

<h4 class="mb-0">

<i class="bi bi-people-fill"></i>

Jurados registrados

</h4>

</div>


<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-bordered table-hover mb-0 tabla">

<thead>

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Curso</th>

<th>Fecha registro</th>

<th>Acciones</th>

</tr>

</thead>

<tbody>

<?php if ($jurados->num_rows === 0) { ?>

<tr>

<td
colspan="7"
class="text-center p-4">

<i class="bi bi-person-x fs-2 text-muted"></i>

<p class="mb-0 mt-2">

No hay jurados registrados.

</p>

</td>

</tr>

<?php } ?>


<?php while ($jurado = $jurados->fetch_assoc()) { ?>

<tr>

<td>

<?php echo (int)$jurado['id']; ?>

</td>

<td>

<strong>

<?php echo htmlspecialchars(
    $jurado['documento']
); ?>

</strong>

</td>

<td>

<?php echo htmlspecialchars(
    $jurado['nombre']
); ?>

</td>

<td>

<?php echo htmlspecialchars(
    $jurado['apellido']
); ?>

</td>

<td>

<?php echo htmlspecialchars(
    $jurado['curso']
); ?>

</td>

<td>

<?php echo htmlspecialchars(
    $jurado['fecha_registro']
); ?>

</td>

<td>

<a
href="editar_jurado.php?id=<?php echo (int)$jurado['id']; ?>"
class="btn btn-editar btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a
href="jurados.php?eliminar=<?php echo (int)$jurado['id']; ?>"
class="btn btn-eliminar btn-sm"
onclick="return confirm('¿Está seguro de eliminar este jurado?');">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</body>

</html>
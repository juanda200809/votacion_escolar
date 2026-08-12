<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

if(!isset($_GET['id'])){
    header("Location: elecciones.php");
    exit();
}

$id = (int)$_GET['id'];

/*==========================
OBTENER ELECCIÓN
==========================*/

$consulta = $conn->query("
SELECT *
FROM elecciones
WHERE id=$id
");

if($consulta->num_rows==0){
    header("Location: elecciones.php");
    exit();
}

$eleccion = $consulta->fetch_assoc();

/*==========================
ACTUALIZAR
==========================*/

if(isset($_POST['actualizar'])){

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $estado = $_POST['estado'];

    $conn->query("
    UPDATE elecciones
    SET
    nombre='$nombre',
    descripcion='$descripcion',
    fecha_inicio='$fecha_inicio',
    fecha_fin='$fecha_fin',
    estado='$estado'
    WHERE id=$id
    ");

    //Eliminar relaciones anteriores
    $conn->query("
    DELETE FROM eleccion_cargos
    WHERE id_eleccion=$id
    ");

    //Guardar nuevos cargos
    if(isset($_POST['cargos'])){

        foreach($_POST['cargos'] as $cargo){

            $conn->query("
            INSERT INTO eleccion_cargos
            (id_eleccion,id_cargo)
            VALUES
            ($id,$cargo)
            ");

        }

    }

    header("Location: elecciones.php");
    exit();

}

/*==========================
CARGOS
==========================*/

$cargos = $conn->query("
SELECT *
FROM cargos
ORDER BY nombre_cargo
");

$seleccionados = [];

$relacion = $conn->query("
SELECT id_cargo
FROM eleccion_cargos
WHERE id_eleccion=$id
");

while($r = $relacion->fetch_assoc()){

    $seleccionados[] = $r['id_cargo'];

}

?>
<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Editar Elección</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>

<i class="bi bi-pencil-square"></i>

Editar Elección

</h3>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label>Nombre</label>

<input
type="text"
name="nombre"
class="form-control"
required
value="<?php echo $eleccion['nombre']; ?>">

</div>

<div class="mb-3">

<label>Descripción</label>

<textarea
name="descripcion"
class="form-control"
rows="4"><?php echo $eleccion['descripcion']; ?></textarea>

</div>

<div class="row">

<div class="col-md-6">

<label>Fecha Inicio</label>

<input
type="datetime-local"
name="fecha_inicio"
class="form-control"
required
value="<?php echo date('Y-m-d\TH:i', strtotime($eleccion['fecha_inicio'])); ?>">

</div>

<div class="col-md-6">

<label>Fecha Fin</label>

<input
type="datetime-local"
name="fecha_fin"
class="form-control"
required
value="<?php echo date('Y-m-d\TH:i', strtotime($eleccion['fecha_fin'])); ?>">

</div>

</div>

<br>

<div class="mb-3">

<label>Estado</label>

<select
name="estado"
class="form-select">

<option value="abierta" <?php if($eleccion['estado']=="abierta") echo "selected"; ?>>Abierta</option>

<option value="cerrada" <?php if($eleccion['estado']=="cerrada") echo "selected"; ?>>Cerrada</option>

</select>

</div>

<hr>

<h5>Cargos</h5>

<?php while($cargo=$cargos->fetch_assoc()){ ?>

<div class="form-check">

<input

type="checkbox"

class="form-check-input"

name="cargos[]"

value="<?php echo $cargo['id']; ?>"

<?php if(in_array($cargo['id'],$seleccionados)) echo "checked"; ?>>

<label class="form-check-label">

<?php echo $cargo['nombre_cargo']; ?>

</label>

</div>

<?php } ?>

<br>

<div class="d-flex justify-content-between">

<a
href="elecciones.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left-circle"></i>

Cancelar

</a>

<button
type="submit"
name="actualizar"
class="btn btn-primary">

<i class="bi bi-check-circle-fill"></i>

Guardar Cambios

</button>

</div>

</form>

</div>

</div>

</div>

</body>

</html>
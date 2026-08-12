<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

/*==============================
GUARDAR ELECCIÓN
==============================*/

if(isset($_POST['guardar'])){

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $estado = $_POST['estado'];

    $sql = "
    INSERT INTO elecciones
    (nombre, descripcion, fecha_inicio, fecha_fin, estado)
    VALUES
    ('$nombre','$descripcion','$fecha_inicio','$fecha_fin','$estado')
    ";

    if($conn->query($sql)){

        $idEleccion = $conn->insert_id;

        if(isset($_POST['cargos'])){

            foreach($_POST['cargos'] as $cargo){

                $conn->query("
                INSERT INTO eleccion_cargos
                (id_eleccion,id_cargo)
                VALUES
                ($idEleccion,$cargo)
                ");

            }

        }

        header("Location: elecciones.php");
        exit();

    }

}

$cargos = $conn->query("
SELECT *
FROM cargos
ORDER BY nombre_cargo
");

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Nueva Elección</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="css/estilos.css">

</head>

<body>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>

<i class="bi bi-calendar-plus-fill"></i>

Nueva Elección

</h3>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label>Nombre de la elección</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Descripción</label>

<textarea
name="descripcion"
class="form-control"
rows="4"></textarea>

</div>

<div class="row">

<div class="col-md-6">

<label>Fecha Inicio</label>

<input
type="datetime-local"
name="fecha_inicio"
class="form-control"
required>

</div>

<div class="col-md-6">

<label>Fecha Fin</label>

<input
type="datetime-local"
name="fecha_fin"
class="form-control"
required>

</div>

</div>

<br>

<div class="mb-3">

<label>Estado</label>

<select
name="estado"
class="form-select">

<option value="cerrada">Cerrada</option>

<option value="abierta">Abierta</option>

</select>

</div>

<hr>

<h5>

Seleccione los cargos que participarán

</h5>

<?php while($cargo=$cargos->fetch_assoc()){ ?>

<div class="form-check">

<input

class="form-check-input"

type="checkbox"

name="cargos[]"

value="<?php echo $cargo['id']; ?>"

id="cargo<?php echo $cargo['id']; ?>">

<label

class="form-check-label"

for="cargo<?php echo $cargo['id']; ?>">

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
name="guardar"
class="btn btn-success">

<i class="bi bi-check-circle-fill"></i>

Guardar Elección

</button>

</div>

</form>

</div>

</div>

</div>

</body>

</html>
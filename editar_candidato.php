<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: index.php");
    exit();
}

include("config/conexion.php");

if (!isset($_GET['id'])) {
    header("Location: candidatos.php");
    exit();
}

$id = intval($_GET['id']);

$candidato = $conn->query("
SELECT *
FROM candidatos
WHERE id=$id
");

if ($candidato->num_rows == 0) {
    header("Location: candidatos.php");
    exit();
}

$datos = $candidato->fetch_assoc();

if(isset($_POST['actualizar'])){

    $nombre=$_POST['nombre'];
    $apellido=$_POST['apellido'];
    $curso=$_POST['curso'];
    $tarjeton=$_POST['tarjeton'];
    $propuestas=$_POST['propuestas'];
    $id_cargo=$_POST['id_cargo'];

    $foto=$datos['foto'];

    if(isset($_FILES['foto']) && $_FILES['foto']['error']==0){

        if($foto!="" && file_exists("uploads/candidatos/".$foto)){
            unlink("uploads/candidatos/".$foto);
        }

        $extension=pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION);

        $foto=time().".".$extension;

        move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            "uploads/candidatos/".$foto
        );
    }

    $conn->query("
    UPDATE candidatos
    SET
    nombre='$nombre',
    apellido='$apellido',
    curso='$curso',
    numero_tarjeton='$tarjeton',
    propuestas='$propuestas',
    foto='$foto',
    id_cargo='$id_cargo'
    WHERE id=$id
    ");

    header("Location: candidatos.php");
    exit();
}

$cargos=$conn->query("
SELECT *
FROM cargos
ORDER BY nombre_cargo
");
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Editar Candidato</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="css/estilos.css">

</head>

<body class="bg-light">

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3 class="mb-0">

<i class="bi bi-pencil-square"></i>

Editar Candidato

</h3>

</div>

<div class="card-body">

<form method="POST" enctype="multipart/form-data">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">Nombre</label>

<input
type="text"
name="nombre"
class="form-control"
required
value="<?php echo $datos['nombre']; ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">Apellido</label>

<input
type="text"
name="apellido"
class="form-control"
required
value="<?php echo $datos['apellido']; ?>">

</div>

</div>

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">Curso</label>

<input
type="text"
name="curso"
class="form-control"
required
value="<?php echo $datos['curso']; ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">Número de Tarjetón</label>

<input
type="number"
name="tarjeton"
class="form-control"
required
value="<?php echo $datos['numero_tarjeton']; ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">Cargo</label>

<select
name="id_cargo"
class="form-select"
required>

<?php while($cargo=$cargos->fetch_assoc()){ ?>

<option
value="<?php echo $cargo['id']; ?>"
<?php if($cargo['id']==$datos['id_cargo']) echo "selected"; ?>>

<?php echo $cargo['nombre_cargo']; ?>

</option>

<?php } ?>

</select>

</div>

</div>
<div class="mb-3">

<label class="form-label">

Propuestas

</label>

<textarea
name="propuestas"
class="form-control"
rows="5"
required><?php echo $datos['propuestas']; ?></textarea>

</div>

<div class="mb-4">

<label class="form-label">

Fotografía Actual

</label>

<br><br>

<?php

if($datos['foto']!="" && file_exists("uploads/candidatos/".$datos['foto'])){

?>

<img
src="uploads/candidatos/<?php echo $datos['foto']; ?>"
style="width:170px;
height:170px;
object-fit:cover;
border-radius:50%;
border:4px solid #0d6efd;
display:block;
margin:auto;">

<?php

}else{

?>

<div class="alert alert-secondary text-center">

Este candidato no tiene fotografía.

</div>

<?php

}

?>

</div>

<div class="mb-4">

<label class="form-label">

Cambiar Fotografía

</label>

<input
type="file"
name="foto"
class="form-control"
accept=".jpg,.jpeg,.png,.webp">

<small class="text-muted">

Si no selecciona una imagen se conservará la fotografía actual.

</small>

</div>

<hr>

<div class="d-flex justify-content-between">

<a
href="candidatos.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Volver

</a>

<button
type="submit"
name="actualizar"
class="btn btn-success">

<i class="bi bi-check-circle-fill"></i>

Guardar Cambios

</button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
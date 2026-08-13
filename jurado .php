<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: index.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=            GUARDAR ESTUDIANTE           =
=========================================*/

if(isset($_POST['guardar'])){

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $curso = trim($_POST['curso']);
    $password = trim($_POST['password']);
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Verificar si el documento ya existe
    $verificar = $conn->query("
        SELECT id
        FROM usuarios
        WHERE documento='$documento'
    ");

    if($verificar->num_rows > 0){

        $mensaje = "<div class='alerta error'>
                        Ya existe un estudiante con ese documento.
                    </div>";

    }else{

        $sql = "INSERT INTO usuarios
        (documento,nombre,apellido,correo,curso,password,rol)

        VALUES

        (
        '$documento',
        '$nombre',
        '$apellido',
        '$correo',
        '$curso',
        '$password_hash',
        'jurado'
        )";

        if($conn->query($sql)){

            $mensaje = "<div class='alerta ok'>
                            Estudiante registrado correctamente.
                        </div>";

        }else{

            $mensaje = "<div class='alerta error'>
                            Error al registrar estudiante.
                        </div>";

        }

    }

}

/*=========================================
=             ELIMINAR                    =
=========================================*/

if(isset($_GET['eliminar'])){

    $id = intval($_GET['eliminar']);

    $conn->query("
        DELETE FROM usuarios
        WHERE id=$id
        AND rol='jurado'
    ");

    header("Location: estudiantes.php");
    exit();

}

/*=========================================
=             CONSULTA                    =
=========================================*/

$estudiantes = $conn->query("
SELECT *
FROM usuarios
WHERE rol='estudiante'
ORDER BY nombre ASC
");

$total = $estudiantes->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gestión de Estudiantes</title>

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

.card-form{

border:none;
border-radius:15px;
box-shadow:0 5px 15px rgba(0,0,0,.15);

}

.table{

background:white;

}

.alerta{

padding:15px;
border-radius:8px;
margin-bottom:20px;

}

.ok{

background:#d1fae5;
color:#065f46;

}

.error{

background:#fee2e2;
color:#991b1b;

}

.buscar{

max-width:350px;

}

</style>

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-people-fill"></i>

Gestion de jurados

</h2>

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

</div>

<?php

if(isset($mensaje)){

echo $mensaje;

}

?>

<div class="row">

<div class="col-md-4">

<div class="card card-form">

<div class="card-body">

<h4 class="mb-3">

Registrar nuevo jurado

</h4>

<form method="POST">

<div class="mb-3">

<label>Documento</label>

<input
type="text"
name="documento"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Nombre</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Apellido</label>

<input
type="text"
name="apellido"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Correo</label>

<input
type="email"
name="correo"
class="form-control">

</div>

<div class="mb-3">

<label>Curso</label>

<input
type="text"
name="curso"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Contraseña</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<button
type="submit"
name="guardar"
class="btn btn-success w-100">

<i class="bi bi-person-plus-fill"></i>

Guardar Estudiante

</button>

</form>

</div>

</div>

</div>

<div class="col-md-8">
    <div class="card card-form">

<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-3">

<h4>

Lista de jurados

</h4>

<span class="badge bg-primary fs-6">

Total: <?php echo $total; ?>

</span>

</div>

<input
type="text"
id="buscar"
class="form-control buscar mb-3"
placeholder="🔍 Buscar estudiante...">

<div class="table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead class="table-primary">

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Curso</th>

<th>Acciones</th>

</tr>

</thead>

<tbody id="tablaEstudiantes">

<?php while($e = $estudiantes->fetch_assoc()){ ?>

<tr>

<td><?php echo $e['id']; ?></td>

<td><?php echo $e['documento']; ?></td>

<td><?php echo $e['nombre']; ?></td>

<td><?php echo $e['apellido']; ?></td>

<td><?php echo $e['curso']; ?></td>

<td width="220">

<a
href="editar_estudiante.php?id=<?php echo $e['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>

<a
href="?eliminar=<?php echo $e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Desea eliminar este estudiante?')">

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

</div>

</div>

<script>

const buscar=document.getElementById("buscar");

buscar.addEventListener("keyup",function(){

let texto=this.value.toLowerCase();

let filas=document.querySelectorAll("#tablaEstudiantes tr");

filas.forEach(function(fila){

let contenido=fila.textContent.toLowerCase();

if(contenido.indexOf(texto)>-1){

fila.style.display="";

}else{

fila.style.display="none";

}

});

});

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

if (!isset($_GET['id'])) {
    header("Location: estudiantes.php");
    exit();
}

$id = (int)$_GET['id'];

/*==============================
OBTENER DATOS DEL ESTUDIANTE
==============================*/

$sql = $conn->query("
SELECT *
FROM usuarios
WHERE id=$id
AND rol='estudiante'
LIMIT 1
");

if ($sql->num_rows == 0) {
    header("Location: estudiantes.php");
    exit();
}

$estudiante = $sql->fetch_assoc();

$mensaje = "";

/*==============================
ACTUALIZAR
==============================*/

if(isset($_POST['actualizar'])){

    $documento = trim($_POST['documento']);
    $nombre     = trim($_POST['nombre']);
    $apellido   = trim($_POST['apellido']);
    $correo     = trim($_POST['correo']);
    $curso      = trim($_POST['curso']);
    $password   = trim($_POST['password']);

    $verificar = $conn->query("
    SELECT id
    FROM usuarios
    WHERE documento='$documento'
    AND id<>$id
    ");

    if($verificar->num_rows>0){

        $mensaje="
        <div class='alert alert-danger'>
        Ya existe otro estudiante con ese documento.
        </div>";

    }else{

        if($password!=""){

            $password_hash=password_hash($password,PASSWORD_DEFAULT);

            $conn->query("
            UPDATE usuarios
            SET
            documento='$documento',
            nombre='$nombre',
            apellido='$apellido',
            correo='$correo',
            curso='$curso',
            password='$password_hash'
            WHERE id=$id
            ");

        }else{

            $conn->query("
            UPDATE usuarios
            SET
            documento='$documento',
            nombre='$nombre',
            apellido='$apellido',
            correo='$correo',
            curso='$curso'
            WHERE id=$id
            ");

        }

        header("Location: estudiantes.php");
        exit();

    }

}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Editar Estudiante</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="css/estilos.css">

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-md-7">

<div class="card shadow">

<div class="card-body">

<h3 class="mb-4">

<i class="bi bi-pencil-square"></i>

Editar Estudiante

</h3>

<?php echo $mensaje; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">Documento</label>

<input
type="text"
name="documento"
class="form-control"
required
value="<?php echo htmlspecialchars($estudiante['documento']); ?>">

</div>

<div class="mb-3">

<label class="form-label">Nombre</label>

<input
type="text"
name="nombre"
class="form-control"
required
value="<?php echo htmlspecialchars($estudiante['nombre']); ?>">

</div>

<div class="mb-3">

<label class="form-label">Apellido</label>

<input
type="text"
name="apellido"
class="form-control"
required
value="<?php echo htmlspecialchars($estudiante['apellido']); ?>">

</div>

<div class="mb-3">

<label class="form-label">Correo</label>

<input
type="email"
name="correo"
class="form-control"
value="<?php echo htmlspecialchars($estudiante['correo']); ?>">

</div>

<div class="mb-3">

<label class="form-label">Curso</label>

<input
type="text"
name="curso"
class="form-control"
required
value="<?php echo htmlspecialchars($estudiante['curso']); ?>">

</div>

<div class="mb-4">

<label class="form-label">Nueva contraseña (opcional)</label>

<input
type="password"
name="password"
class="form-control"
placeholder="Déjela vacía para conservar la actual">

</div>

<div class="d-flex justify-content-between">

<a href="estudiantes.php" class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Volver

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

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
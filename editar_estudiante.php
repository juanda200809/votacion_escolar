<?php
session_start();

if (
    !isset($_SESSION['id']) ||
    $_SESSION['rol'] != 'administrador'
) {
    header("Location: index.php");
    exit();
}

include("config/conexion.php");

/* =========================================
   VERIFICAR ID
========================================= */

if (!isset($_GET['id'])) {
    header("Location: estudiantes.php");
    exit();
}

$id = intval($_GET['id']);


/* =========================================
   ACTUALIZAR ESTUDIANTE
========================================= */

if (isset($_POST['actualizar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);

    /* La contraseña será automáticamente
       el mismo documento */

    $password_hash = password_hash(
        $documento,
        PASSWORD_DEFAULT
    );


    /* Verificar que el documento no pertenezca
       a otro usuario */

    $verificar = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE documento = ?
        AND id != ?
    ");

    $verificar->bind_param(
        "si",
        $documento,
        $id
    );

    $verificar->execute();

    $resultado = $verificar->get_result();


    if ($resultado->num_rows > 0) {

        $mensaje = "
        <div class='alerta error'>
            Ya existe otro usuario con ese documento.
        </div>";

    } else {

        $actualizar = $conn->prepare("
            UPDATE usuarios
            SET
                documento = ?,
                nombre = ?,
                apellido = ?,
                curso = ?,
                password = ?
            WHERE id = ?
            AND rol = 'estudiante'
        ");

        $actualizar->bind_param(
            "sssssi",
            $documento,
            $nombre,
            $apellido,
            $curso,
            $password_hash,
            $id
        );


        if ($actualizar->execute()) {

            header("Location: estudiantes.php");
            exit();

        } else {

            $mensaje = "
            <div class='alerta error'>
                Error al actualizar el estudiante.
            </div>";
        }
    }
}


/* =========================================
   OBTENER ESTUDIANTE
========================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM usuarios
    WHERE id = ?
    AND rol = 'estudiante'
");

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {

    header("Location: estudiantes.php");
    exit();
}

$estudiante = $resultado->fetch_assoc();

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Editar Estudiante</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

body {
    background: #eef3f9;
}

.card-form {

    max-width: 700px;
    margin: 50px auto;

    border: none;
    border-radius: 15px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.15);
}

.titulo {

    color: #0d47a1;
    font-weight: bold;
}

.alerta {

    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {

    background: #fee2e2;
    color: #991b1b;
}

.info {

    background: #dbeafe;
    color: #1e40af;
}

</style>

</head>

<body>

<div class="container">

<div class="card card-form">

<div class="card-body p-4">

<h2 class="titulo mb-4">

<i class="bi bi-pencil-square"></i>

Editar Estudiante

</h2>


<?php

if (isset($mensaje)) {
    echo $mensaje;
}

?>


<form method="POST">


<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label">

<strong>Documento</strong>

</label>

<input
type="text"
name="documento"
class="form-control"
value="<?php echo htmlspecialchars($estudiante['documento']); ?>"
required>

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label class="form-label">

<strong>Nombre</strong>

</label>

<input
type="text"
name="nombre"
class="form-control"
value="<?php echo htmlspecialchars($estudiante['nombre']); ?>"
required>

</div>


<!-- APELLIDO -->

<div class="mb-3">

<label class="form-label">

<strong>Apellido</strong>

</label>

<input
type="text"
name="apellido"
class="form-control"
value="<?php echo htmlspecialchars($estudiante['apellido']); ?>"
required>

</div>


<!-- CURSO -->

<div class="mb-3">

<label class="form-label">

<strong>Curso</strong>

</label>

<input
type="text"
name="curso"
class="form-control"
value="<?php echo htmlspecialchars($estudiante['curso']); ?>"
required>

</div>


<!-- INFORMACIÓN DE CONTRASEÑA -->

<div class="alerta info">

<i class="bi bi-info-circle-fill"></i>

<strong>Contraseña:</strong>

La contraseña del estudiante será automáticamente
su número de documento.

<br><br>

Por ejemplo:

<strong>1072709874</strong>

</div>


<!-- BOTONES -->

<div class="d-flex gap-2">

<button
type="submit"
name="actualizar"
class="btn btn-success flex-fill">

<i class="bi bi-check-circle"></i>

Guardar cambios

</button>


<a
href="estudiantes.php"
class="btn btn-secondary flex-fill">

<i class="bi bi-arrow-left-circle"></i>

Cancelar

</a>

</div>


</form>

</div>

</div>

</div>

</body>

</html>
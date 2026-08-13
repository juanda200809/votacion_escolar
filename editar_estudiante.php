<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: index.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=          VERIFICAR ID DEL ESTUDIANTE    =
=========================================*/

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: estudiantes.php");
    exit();
}

$id = intval($_GET['id']);


/*=========================================
=          BUSCAR ESTUDIANTE              =
=========================================*/

$consulta = $conn->query("
    SELECT *
    FROM usuarios
    WHERE id=$id
    AND rol='estudiante'
");

if ($consulta->num_rows == 0) {
    header("Location: estudiantes.php");
    exit();
}

$estudiante = $consulta->fetch_assoc();


/*=========================================
=          ACTUALIZAR ESTUDIANTE          =
=========================================*/

if (isset($_POST['actualizar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);

    /*
     * La contraseña será automáticamente
     * el documento del estudiante.
     */
    $password_hash = password_hash(
        $documento,
        PASSWORD_DEFAULT
    );


    /*=====================================
    =      VERIFICAR DOCUMENTO DUPLICADO =
    =====================================*/

    $verificar = $conn->query("
        SELECT id
        FROM usuarios
        WHERE documento='$documento'
        AND id != $id
    ");

    if ($verificar->num_rows > 0) {

        $mensaje = "
        <div class='alerta error'>
            <i class='bi bi-exclamation-triangle-fill'></i>
            Ya existe otro usuario con ese documento.
        </div>";

    } else {

        /*=================================
        =       ACTUALIZAR DATOS          =
        =================================*/

        $sql = "
        UPDATE usuarios
        SET
            documento='$documento',
            nombre='$nombre',
            apellido='$apellido',
            correo='',
            curso='$curso',
            password='$password_hash'
        WHERE id=$id
        AND rol='estudiante'
        ";

        if ($conn->query($sql)) {

            $mensaje = "
            <div class='alerta ok'>
                <i class='bi bi-check-circle-fill'></i>
                Estudiante actualizado correctamente.
                <br>
                <strong>La nueva contraseña es su documento.</strong>
            </div>";

            /*
             * Actualizar los datos mostrados
             * en el formulario.
             */
            $estudiante['documento'] = $documento;
            $estudiante['nombre'] = $nombre;
            $estudiante['apellido'] = $apellido;
            $estudiante['curso'] = $curso;

        } else {

            $mensaje = "
            <div class='alerta error'>
                <i class='bi bi-x-circle-fill'></i>
                Error al actualizar el estudiante:
                " . $conn->error . "
            </div>";
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Editar Estudiante</title>

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

body {
    background:#eef3f9;
}

.card-form {

    max-width:700px;

    margin:auto;

    border:none;

    border-radius:15px;

    box-shadow:0 5px 15px rgba(0,0,0,.15);

}

.alerta {

    padding:15px;

    border-radius:8px;

    margin-bottom:20px;

}

.ok {

    background:#d1fae5;

    color:#065f46;

}

.error {

    background:#fee2e2;

    color:#991b1b;

}

.titulo {

    color:#0d47a1;

}

</style>

</head>


<body>


<div class="container py-5">


<div class="card card-form">


<div class="card-body p-4">


<!-- =====================================
     TITULO
===================================== -->

<h2 class="titulo mb-4">

<i class="bi bi-pencil-square"></i>

Editar Estudiante

</h2>


<!-- =====================================
     MENSAJE
===================================== -->

<?php

if (isset($mensaje)) {

    echo $mensaje;

}

?>


<!-- =====================================
     FORMULARIO
===================================== -->

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


<!-- AVISO DE CONTRASEÑA -->

<div class="alert alert-info">

<i class="bi bi-info-circle-fill"></i>

<strong>Contraseña automática</strong>

<br>

La contraseña del estudiante será automáticamente
su número de documento.

<br><br>

Si cambia el documento, también cambiará la contraseña.

</div>


<!-- BOTONES -->

<div class="d-flex gap-2">


<button

type="submit"

name="actualizar"

class="btn btn-success flex-fill">

<i class="bi bi-check-circle-fill"></i>

Guardar Cambios

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


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>
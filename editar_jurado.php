<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   VERIFICAR ID
========================================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: jurados.php?error=id");
    exit();

}

$id = (int)$_GET['id'];


/* =========================================
   BUSCAR JURADO
========================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        password,
        rol
    FROM usuarios
    WHERE id = ?
    AND rol = 'jurado'
    LIMIT 1
");

$stmt->bind_param("i", $id);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows === 0) {

    $stmt->close();

    header("Location: jurados.php?error=no_encontrado");
    exit();

}


$jurado = $resultado->fetch_assoc();

$stmt->close();


/* =========================================
   PROCESAR FORMULARIO
========================================= */

$error = "";
$exito = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documento = trim($_POST['documento'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $password = trim($_POST['password'] ?? '');


    /* =====================================
       VALIDAR
    ===================================== */

    if (
        $documento === '' ||
        $nombre === '' ||
        $apellido === ''
    ) {

        $error =
            "Debe completar documento, nombre y apellido.";

    } else {


        /* =====================================
           COMPROBAR DOCUMENTO REPETIDO
        ===================================== */

        $stmt = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $documento,
            $id
        );

        $stmt->execute();

        $resultadoDocumento =
            $stmt->get_result();


        if ($resultadoDocumento->num_rows > 0) {

            $error =
                "Ese documento ya pertenece a otro usuario.";

            $stmt->close();

        } else {

            $stmt->close();


            /* =================================
               ACTUALIZAR SIN CAMBIAR PASSWORD
            ================================= */

            if ($password === '') {

                $stmt = $conn->prepare("
                    UPDATE usuarios
                    SET
                        documento = ?,
                        nombre = ?,
                        apellido = ?
                    WHERE id = ?
                    AND rol = 'jurado'
                ");

                $stmt->bind_param(
                    "sssi",
                    $documento,
                    $nombre,
                    $apellido,
                    $id
                );


            } else {


                /* ==============================
                   ACTUALIZAR CON PASSWORD
                ============================== */

                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                $stmt = $conn->prepare("
                    UPDATE usuarios
                    SET
                        documento = ?,
                        nombre = ?,
                        apellido = ?,
                        password = ?
                    WHERE id = ?
                    AND rol = 'jurado'
                ");

                $stmt->bind_param(
                    "ssssi",
                    $documento,
                    $nombre,
                    $apellido,
                    $passwordHash,
                    $id
                );

            }


            /* =================================
               EJECUTAR
            ================================= */

            if ($stmt->execute()) {

                $exito =
                    "Los datos del jurado fueron actualizados correctamente.";


                /* Actualizar datos mostrados */

                $jurado['documento'] =
                    $documento;

                $jurado['nombre'] =
                    $nombre;

                $jurado['apellido'] =
                    $apellido;


            } else {

                $error =
                    "No se pudieron actualizar los datos.";

            }


            $stmt->close();

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

<title>
Editar Jurado
</title>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =========================================
     ICONOS
========================================= -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================
   BARRA SUPERIOR
========================================= */

.topbar {

    background:#1453a3;

    color:white;

    padding:18px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:850px;

    margin:40px auto;

    padding:0 20px;

}


/* =========================================
   TARJETA
========================================= */

.card-editar {

    background:white;

    border-radius:20px;

    padding:35px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


/* =========================================
   TÍTULO
========================================= */

.titulo {

    color:#1453a3;

    font-weight:bold;

}


/* =========================================
   ICONO
========================================= */

.icono-jurado {

    width:80px;

    height:80px;

    border-radius:50%;

    background:#dbe8f8;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:0 auto 20px;

    font-size:40px;

    color:#1453a3;

}


/* =========================================
   LABEL
========================================= */

.form-label {

    font-weight:bold;

    color:#333;

}


/* =========================================
   BOTONES
========================================= */

.btn-guardar {

    padding:12px 25px;

    font-weight:bold;

}


.btn-volver {

    padding:12px 25px;

}


/* =========================================
   INFORMACIÓN PASSWORD
========================================= */

.info-password {

    background:#cff4fc;

    border:
        1px solid #9eeaf9;

    color:#055160;

    border-radius:10px;

    padding:15px;

}

</style>

</head>


<body>


<!-- =========================================
     BARRA SUPERIOR
========================================= -->

<div class="topbar">


<div>

<i class="bi bi-mortarboard-fill"></i>

<strong>
Sistema de Votaciones Escolares
</strong>

</div>


<div>

<i class="bi bi-person-fill"></i>

Administrador

</div>


</div>


<!-- =========================================
     CONTENEDOR
========================================= -->

<div class="contenedor">


<div class="card-editar">


<!-- =========================================
     ICONO
========================================= -->

<div class="icono-jurado">

<i class="bi bi-person-badge-fill"></i>

</div>


<!-- =========================================
     TÍTULO
========================================= -->

<h2 class="text-center titulo">

<i class="bi bi-pencil-square"></i>

Editar Jurado

</h2>


<p class="text-center text-muted mb-4">

Actualiza los datos y la contraseña
del jurado.

</p>


<!-- =========================================
     MENSAJE ERROR
========================================= -->

<?php if ($error !== '') { ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars($error); ?>

</div>

<?php } ?>


<!-- =========================================
     MENSAJE ÉXITO
========================================= -->

<?php if ($exito !== '') { ?>

<div class="alert alert-success">

<i class="bi bi-check-circle-fill"></i>

<?php echo htmlspecialchars($exito); ?>

</div>

<?php } ?>


<!-- =========================================
     FORMULARIO
========================================= -->

<form
method="POST"
autocomplete="off">


<!-- DOCUMENTO -->

<div class="mb-3">

<label
class="form-label">

<i class="bi bi-person-vcard-fill"></i>

Documento

</label>


<input

type="text"

name="documento"

class="form-control form-control-lg"

value="<?php echo htmlspecialchars(
    $jurado['documento']
); ?>"

required>

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label
class="form-label">

<i class="bi bi-person-fill"></i>

Nombre

</label>


<input

type="text"

name="nombre"

class="form-control form-control-lg"

value="<?php echo htmlspecialchars(
    $jurado['nombre']
); ?>"

required>

</div>


<!-- APELLIDO -->

<div class="mb-3">

<label
class="form-label">

<i class="bi bi-person-fill"></i>

Apellido

</label>


<input

type="text"

name="apellido"

class="form-control form-control-lg"

value="<?php echo htmlspecialchars(
    $jurado['apellido']
); ?>"

required>

</div>


<!-- =========================================
     CONTRASEÑA
========================================= -->

<div class="mb-3">

<label
class="form-label">

<i class="bi bi-key-fill"></i>

Nueva contraseña

</label>


<input

type="password"

name="password"

id="password"

class="form-control form-control-lg"

placeholder="Dejar vacío para conservar la actual">


</div>


<!-- INFORMACIÓN -->

<div class="info-password mb-4">

<i class="bi bi-info-circle-fill"></i>

<strong>
Importante:
</strong>

Si no escribes una nueva contraseña,
se conservará la contraseña actual.

Si deseas cambiarla, escribe la nueva
contraseña en este campo.

</div>


<!-- =========================================
     BOTONES
========================================= -->

<div
class="d-flex
       justify-content-between
       flex-wrap
       gap-2">


<a

href="jurados.php"

class="btn btn-secondary btn-volver">

<i class="bi bi-arrow-left"></i>

Volver

</a>


<button

type="submit"

class="btn btn-primary btn-guardar">

<i class="bi bi-save-fill"></i>

Guardar cambios

</button>


</div>


</form>


</div>


</div>


</body>

</html>
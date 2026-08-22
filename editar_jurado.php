<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'administrador'
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   VERIFICAR ID
========================================================= */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: crear_jurado.php");
    exit();

}


$id = (int)$_GET['id'];


if ($id <= 0) {

    header("Location: crear_jurado.php");
    exit();

}


/* =========================================================
   BUSCAR JURADO
========================================================= */

$stmt = $conn->prepare("

    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso,
        password,
        rol

    FROM usuarios

    WHERE id = ?

    AND rol = 'jurado'

    LIMIT 1

");


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header(
        "Location: crear_jurado.php?error=no_encontrado"
    );

    exit();

}


$jurado =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   VARIABLES
========================================================= */

$error = "";

$exito = "";


/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    $documento =
        trim(
            $_POST['documento'] ?? ""
        );


    $nombre =
        trim(
            $_POST['nombre'] ?? ""
        );


    $apellido =
        trim(
            $_POST['apellido'] ?? ""
        );


    $curso =
        trim(
            $_POST['curso'] ?? ""
        );


    $password =
        trim(
            $_POST['password'] ?? ""
        );


    /* =====================================================
       VALIDAR
    ===================================================== */

    if (
        $documento === "" ||
        $nombre === "" ||
        $apellido === "" ||
        $curso === ""
    ) {

        $error =
            "Debe completar todos los campos.";

    }


    elseif (
        !preg_match(
            '/^[0-9]+$/',
            $documento
        )
    ) {

        $error =
            "El documento debe contener únicamente números.";

    }


    else {


        /* ================================================
           COMPROBAR DOCUMENTO DUPLICADO
        ================================================ */

        $stmt =
            $conn->prepare("

                SELECT
                    id,
                    rol

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


        if (
            $resultadoDocumento->num_rows > 0
        ) {

            $usuario =
                $resultadoDocumento->fetch_assoc();


            $stmt->close();


            if (
                $usuario['rol'] === 'jurado'
            ) {

                $error =
                    "Ya existe otro jurado con ese documento.";

            } else {

                $error =
                    "Ese documento ya pertenece a otro usuario.";

            }

        } else {


            $stmt->close();


            /* ============================================
               DETERMINAR CONTRASEÑA
            ============================================ */

            /*
             * Si el administrador escribe una contraseña,
             * utilizamos esa contraseña.
             *
             * Si la deja vacía:
             *
             * - Si cambió el documento, la contraseña será
             *   automáticamente el nuevo documento.
             *
             * - Si no cambió el documento, conservamos
             *   la contraseña actual.
             */

            $documentoAnterior =
                (string)$jurado['documento'];


            $documentoCambio =
                (
                    $documento !==
                    $documentoAnterior
                );


            if (
                $password !== ""
            ) {


                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


            } elseif (
                $documentoCambio
            ) {


                /*
                 * Al cambiar el documento,
                 * la nueva contraseña será el nuevo documento.
                 */

                $passwordHash =
                    password_hash(
                        $documento,
                        PASSWORD_DEFAULT
                    );


            } else {


                /*
                 * Conservar contraseña actual.
                 */

                $passwordHash =
                    $jurado['password'];

            }


            /* ============================================
               ACTUALIZAR JURADO
            ============================================ */

            $stmt =
                $conn->prepare("

                    UPDATE usuarios

                    SET

                        documento = ?,

                        nombre = ?,

                        apellido = ?,

                        curso = ?,

                        password = ?

                    WHERE id = ?

                    AND rol = 'jurado'

                ");


            if (!$stmt) {

                $error =
                    "No se pudo preparar la actualización.";

            } else {


                $stmt->bind_param(

                    "sssssi",

                    $documento,

                    $nombre,

                    $apellido,

                    $curso,

                    $passwordHash,

                    $id

                );


                if (
                    $stmt->execute()
                ) {


                    $exito =
                        "Los datos del jurado fueron actualizados correctamente.";


                    /*
                     * Actualizar datos mostrados
                     */

                    $jurado['documento'] =
                        $documento;


                    $jurado['nombre'] =
                        $nombre;


                    $jurado['apellido'] =
                        $apellido;


                    $jurado['curso'] =
                        $curso;


                    /*
                     * Mostrar aviso si se cambió
                     * el documento.
                     */

                    if (
                        $documentoCambio &&
                        $password === ""
                    ) {

                        $exito .=
                            " Como cambiaste el documento, la nueva contraseña del jurado es su nuevo documento.";

                    }


                } else {


                    $error =
                        "No se pudieron actualizar los datos del jurado.";

                }


                $stmt->close();

            }

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


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.topbar {

    background:#1453a3;

    color:white;

    padding:18px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

}


.contenedor {

    max-width:850px;

    margin:40px auto;

    padding:0 20px;

}


.card-editar {

    background:white;

    border-radius:20px;

    padding:35px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.titulo {

    color:#1453a3;

    font-weight:bold;

}


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


.form-label {

    font-weight:bold;

    color:#333;

}


.info-password {

    background:#cff4fc;

    border:
        1px solid #9eeaf9;

    color:#055160;

    border-radius:10px;

    padding:15px;

}


.btn-guardar {

    padding:12px 25px;

    font-weight:bold;

}


.btn-volver {

    padding:12px 25px;

}

</style>

</head>


<body>


<!-- =====================================================
     BARRA SUPERIOR
===================================================== -->

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


<div class="contenedor">


<div class="card-editar">


<div class="icono-jurado">

<i class="bi bi-person-badge-fill"></i>

</div>


<h2 class="text-center titulo">

<i class="bi bi-pencil-square"></i>

Editar Jurado

</h2>


<p class="text-center text-muted mb-4">

Actualiza los datos del jurado.

</p>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if (
    $error !== ""
) { ?>


<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars(
    $error
); ?>

</div>


<?php } ?>


<!-- =====================================================
     ÉXITO
===================================================== -->

<?php if (
    $exito !== ""
) { ?>


<div class="alert alert-success">

<i class="bi bi-check-circle-fill"></i>

<?php echo htmlspecialchars(
    $exito
); ?>

</div>


<?php } ?>


<form
method="POST"
autocomplete="off">


<!-- =====================================================
     DOCUMENTO
===================================================== -->

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

inputmode="numeric"

pattern="[0-9]+"

required>


</div>


<!-- =====================================================
     NOMBRE
===================================================== -->

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


<!-- =====================================================
     APELLIDO
===================================================== -->

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


<!-- =====================================================
     CURSO
===================================================== -->

<div class="mb-3">


<label
class="form-label">

<i class="bi bi-mortarboard-fill"></i>

Curso

</label>


<input

type="text"

name="curso"

class="form-control form-control-lg"

value="<?php echo htmlspecialchars(
    $jurado['curso']
); ?>"

required>


</div>


<!-- =====================================================
     CONTRASEÑA
===================================================== -->

<div class="mb-3">


<label
class="form-label">

<i class="bi bi-key-fill"></i>

Nueva contraseña

</label>


<input

type="password"

name="password"

class="form-control form-control-lg"

placeholder="Dejar vacío para conservar la actual">


</div>


<div class="info-password mb-4">


<i class="bi bi-info-circle-fill"></i>


<strong>

¿Cómo funciona la contraseña?

</strong>


<br><br>


Si dejas el campo vacío:

<ul class="mb-0">

<li>
Si no cambias el documento, se conserva la contraseña actual.
</li>

<li>
Si cambias el documento, la contraseña pasa a ser el nuevo documento.
</li>

</ul>


Si escribes una contraseña,
se utilizará esa contraseña.


</div>


<!-- =====================================================
     BOTONES
===================================================== -->

<div
class="d-flex
       justify-content-between
       flex-wrap
       gap-2">


<a

href="crear_jurado.php"

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
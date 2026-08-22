<?php

session_start();

include("config/conexion.php");


/* =========================================
   SI YA HAY SESIÓN
========================================= */

if (isset($_SESSION['id'])) {

    $rolSesion = strtolower(
        trim($_SESSION['rol'] ?? '')
    );

    if ($rolSesion === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rolSesion === "estudiante") {

        header("Location: votar.php");
        exit();

    }

    if ($rolSesion === "jurado") {

        header("Location: jurado.php");
        exit();

    }

}


/* =========================================
   VARIABLES
========================================= */

$error = "";

$documentoIngresado = "";


/* =========================================
   PROCESAR LOGIN
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documentoIngresado =
        trim($_POST['documento'] ?? '');

    $password =
        $_POST['password'] ?? '';


    /* =====================================
       VALIDAR CAMPOS
    ===================================== */

    if (
        $documentoIngresado === "" ||
        $password === ""
    ) {

        $error =
            "Debe completar todos los campos.";

    } else {


        /* =====================================
           BUSCAR USUARIO
        ===================================== */

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
            WHERE documento = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error =
                "Error al preparar la consulta.";

        } else {

            $stmt->bind_param(
                "s",
                $documentoIngresado
            );

            $stmt->execute();

            $resultado =
                $stmt->get_result();


            /* =================================
               USUARIO ENCONTRADO
            ================================= */

            if ($resultado->num_rows > 0) {

                $usuario =
                    $resultado->fetch_assoc();


                $loginCorrecto = false;


                /* =================================
                   CONTRASEÑA CIFRADA
                ================================= */

                if (
                    !empty($usuario['password']) &&
                    password_verify(
                        $password,
                        $usuario['password']
                    )
                ) {

                    $loginCorrecto = true;

                }


                /* =================================
                   CONTRASEÑA ANTIGUA EN TEXTO PLANO
                   
                   Esto permite que los usuarios
                   antiguos sigan funcionando.
                ================================= */

                elseif (
                    hash_equals(
                        (string)$usuario['password'],
                        (string)$password
                    )
                ) {

                    $loginCorrecto = true;


                    /* =============================
                       CIFRAR AUTOMÁTICAMENTE
                    ============================= */

                    $nuevaPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    $actualizar =
                        $conn->prepare("
                            UPDATE usuarios
                            SET password = ?
                            WHERE id = ?
                        ");


                    if ($actualizar) {

                        $actualizar->bind_param(
                            "si",
                            $nuevaPassword,
                            $usuario['id']
                        );

                        $actualizar->execute();

                        $actualizar->close();

                    }

                }


                /* =================================
                   CONTRASEÑA INCORRECTA
                ================================= */

                if (!$loginCorrecto) {

                    $error =
                        "Contraseña incorrecta.";

                } else {


                    /* =================================
                       OBTENER ROL
                    ================================= */

                    $rol =
                        strtolower(
                            trim(
                                $usuario['rol']
                            )
                        );


                    /* =================================
                       VALIDAR ROL
                    ================================= */

                    $rolesPermitidos = [
                        "administrador",
                        "jurado",
                        "estudiante"
                    ];


                    if (
                        !in_array(
                            $rol,
                            $rolesPermitidos,
                            true
                        )
                    ) {

                        $error =
                            "El usuario tiene un rol no válido.";

                    } else {


                        /* =============================
                           CREAR SESIÓN
                        ============================= */

                        session_regenerate_id(true);


                        $_SESSION['id'] =
                            $usuario['id'];

                        $_SESSION['nombre'] =
                            $usuario['nombre'];

                        $_SESSION['rol'] =
                            $rol;


                        /* =============================
                           LIMPIAR ESTUDIANTE DEL JURADO
                        ============================= */

                        if ($rol !== "jurado") {

                            unset(
                                $_SESSION[
                                    'estudiante_jurado'
                                ]
                            );

                        }


                        /* =============================
                           REDIRECCIONES
                        ============================= */

                        if (
                            $rol ===
                            "administrador"
                        ) {

                            header(
                                "Location: admin.php"
                            );

                            exit();

                        }


                        if (
                            $rol ===
                            "jurado"
                        ) {

                            header(
                                "Location: jurado.php"
                            );

                            exit();

                        }


                        if (
                            $rol ===
                            "estudiante"
                        ) {

                            header(
                                "Location: votar.php"
                            );

                            exit();

                        }

                    }

                }

            } else {

                $error =
                    "Documento no registrado.";

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
Sistema de Votaciones
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


<!-- =========================================
     CSS DEL PROYECTO
========================================= -->

<link
rel="stylesheet"
href="css/estilos.css">


<style>

body {

    background:
        linear-gradient(
            135deg,
            #0d47a1,
            #1565c0
        );

    min-height:100vh;

    display:flex;

    justify-content:center;

    align-items:center;

    padding:20px;

}


.login {

    width:430px;

    max-width:100%;

    background:white;

    padding:40px;

    border-radius:20px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.25);

}


.logo {

    font-size:70px;

    text-align:center;

    margin-bottom:15px;

}


.login h2 {

    text-align:center;

    margin-bottom:30px;

    color:#0d47a1;

    font-weight:bold;

}


.input-group-text {

    background:#0d6efd;

    color:white;

    border-color:#0d6efd;

}


.form-control:focus {

    border-color:#0d6efd;

    box-shadow:
        0 0 0 .2rem
        rgba(13,110,253,.15);

}


.btn-login {

    width:100%;

    padding:12px;

    font-size:18px;

    font-weight:bold;

}


.footer {

    margin-top:20px;

    text-align:center;

    font-size:13px;

    color:gray;

}


.info-acceso {

    background:#eef5ff;

    border-radius:10px;

    padding:12px;

    margin-bottom:20px;

    font-size:13px;

    color:#315477;

}

</style>

</head>


<body>


<div class="login">


<!-- =========================================
     LOGO
========================================= -->

<div class="logo">

🗳️

</div>


<!-- =========================================
     TÍTULO
========================================= -->

<h2>

Sistema de Votaciones

</h2>


<!-- =========================================
     MENSAJE DE ERROR
========================================= -->

<?php if ($error !== "") { ?>

<div
class="alert alert-danger">

<i
class="bi bi-exclamation-triangle-fill">
</i>

<?php echo htmlspecialchars($error); ?>

</div>

<?php } ?>


<!-- =========================================
     FORMULARIO
========================================= -->

<form method="POST">


<!-- =========================================
     DOCUMENTO
========================================= -->

<div class="mb-3">

<label
class="form-label">

<i
class="bi bi-person-vcard-fill">
</i>

Documento

</label>


<div class="input-group">

<span
class="input-group-text">

<i
class="bi bi-person-fill">
</i>

</span>


<input

type="text"

name="documento"

class="form-control"

placeholder="Ingrese su documento"

value="<?php echo htmlspecialchars(
    $documentoIngresado
); ?>"

required

autocomplete="username">

</div>

</div>


<!-- =========================================
     CONTRASEÑA
========================================= -->

<div class="mb-4">

<label
class="form-label">

<i
class="bi bi-lock-fill">
</i>

Contraseña

</label>


<div class="input-group">


<span
class="input-group-text">

<i
class="bi bi-key-fill">
</i>

</span>


<input

type="password"

name="password"

id="password"

class="form-control"

placeholder="Ingrese su contraseña"

required

autocomplete="current-password">


<!-- MOSTRAR CONTRASEÑA -->

<button

class="btn btn-outline-secondary"

type="button"

onclick="mostrarPassword()">

<i

class="bi bi-eye"

id="iconoPassword">

</i>

</button>


</div>

</div>


<!-- =========================================
     RECORDAR DOCUMENTO
========================================= -->

<div class="form-check mb-4">

<input

class="form-check-input"

type="checkbox"

id="recordar"

onclick="recordarDocumento()">


<label

class="form-check-label"

for="recordar">

Recordar documento

</label>

</div>


<!-- =========================================
     BOTÓN
========================================= -->

<button

type="submit"

name="ingresar"

class="btn btn-primary btn-login">

<i
class="bi bi-box-arrow-in-right">
</i>

Ingresar

</button>


</form>


<hr>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="text-center">

<h6>

Sistema de Votaciones Escolares

</h6>

<small>

Versión 2.0

</small>

</div>


<!-- =========================================
     FOOTER
========================================= -->

<div class="footer">

© <?php echo date("Y"); ?>

Todos los derechos reservados

</div>


</div>


<!-- =========================================
     JAVASCRIPT
========================================= -->

<script>

function mostrarPassword() {

    const pass =
        document.getElementById(
            "password"
        );

    const icono =
        document.getElementById(
            "iconoPassword"
        );


    if (
        pass.type === "password"
    ) {

        pass.type = "text";

        icono.className =
            "bi bi-eye-slash";

    } else {

        pass.type = "password";

        icono.className =
            "bi bi-eye";

    }

}


/* =========================================
   RECORDAR DOCUMENTO
========================================= */

function recordarDocumento() {

    const checkbox =
        document.getElementById(
            "recordar"
        );

    const documento =
        document.querySelector(
            'input[name="documento"]'
        );


    if (checkbox.checked) {

        localStorage.setItem(
            "documentoVotaciones",
            documento.value
        );

    } else {

        localStorage.removeItem(
            "documentoVotaciones"
        );

    }

}


/* =========================================
   CARGAR DOCUMENTO RECORDADO
========================================= */

document.addEventListener(
    "DOMContentLoaded",
    function() {

        const documento =
            localStorage.getItem(
                "documentoVotaciones"
            );

        const campo =
            document.querySelector(
                'input[name="documento"]'
            );

        const checkbox =
            document.getElementById(
                "recordar"
            );


        if (
            documento &&
            campo
        ) {

            campo.value =
                documento;

            checkbox.checked =
                true;

        }

    }
);

</script>


<!-- BOOTSTRAP JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
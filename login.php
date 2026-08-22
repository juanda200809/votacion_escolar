<?php

session_start();

include("config/conexion.php");


/* =====================================================
   VARIABLES
===================================================== */

$error = "";
$documentoIngresado = "";


/* =====================================================
   SI EXISTE UNA SESIÓN, COMPROBARLA
===================================================== */

if (isset($_SESSION['id'], $_SESSION['rol'])) {

    $rolSesion = strtolower(trim($_SESSION['rol']));

    /*
     * Solo redirigimos si el rol realmente es válido.
     */

    if ($rolSesion === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rolSesion === "jurado") {

        header("Location: jurado.php");
        exit();

    }

    if ($rolSesion === "estudiante") {

        header("Location: votar.php");
        exit();

    }


    /*
     * Si existe una sesión pero el rol no es válido,
     * destruimos la sesión para evitar ciclos.
     */

    session_unset();
    session_destroy();

    session_start();

}


/* =====================================================
   PROCESAR LOGIN
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documentoIngresado =
        trim($_POST['documento'] ?? '');

    $password =
        $_POST['password'] ?? '';


    /* =================================================
       VALIDAR CAMPOS
    ================================================= */

    if (
        $documentoIngresado === "" ||
        $password === ""
    ) {

        $error =
            "Debe completar todos los campos.";

    } else {


        /* =============================================
           BUSCAR USUARIO
        ============================================= */

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
                "Error al consultar el usuario.";

        } else {

            $stmt->bind_param(
                "s",
                $documentoIngresado
            );

            $stmt->execute();

            $resultado =
                $stmt->get_result();


            /* =========================================
               USUARIO ENCONTRADO
            ========================================= */

            if ($resultado->num_rows === 0) {

                $error =
                    "Documento no registrado.";

            } else {

                $usuario =
                    $resultado->fetch_assoc();


                $loginCorrecto = false;


                /* =====================================
                   CONTRASEÑA CIFRADA
                ===================================== */

                if (
                    !empty($usuario['password']) &&
                    password_verify(
                        $password,
                        $usuario['password']
                    )
                ) {

                    $loginCorrecto = true;

                }


                /* =====================================
                   CONTRASEÑA EN TEXTO PLANO
                   
                   Para usuarios antiguos.
                ===================================== */

                elseif (
                    (string)$password ===
                    (string)$usuario['password']
                ) {

                    $loginCorrecto = true;


                    /*
                     * Convertir automáticamente
                     * a contraseña cifrada.
                     */

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


                /* =====================================
                   CONTRASEÑA INCORRECTA
                ===================================== */

                if (!$loginCorrecto) {

                    $error =
                        "Contraseña incorrecta.";

                } else {


                    /* =================================
                       NORMALIZAR ROL
                    ================================= */

                    $rol =
                        strtolower(
                            trim(
                                $usuario['rol']
                            )
                        );


                    /*
                     * Aceptamos únicamente estos
                     * tres roles.
                     */

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


                        /* =================================
                           LIMPIAR SESIÓN ANTERIOR
                        ================================= */

                        session_unset();


                        /*
                         * Generar una nueva ID de sesión.
                         */

                        session_regenerate_id(true);


                        /* =================================
                           GUARDAR SESIÓN
                        ================================= */

                        $_SESSION['id'] =
                            $usuario['id'];

                        $_SESSION['nombre'] =
                            $usuario['nombre'];

                        $_SESSION['rol'] =
                            $rol;


                        /*
                         * Datos adicionales.
                         */

                        $_SESSION['documento'] =
                            $usuario['documento'];

                        $_SESSION['apellido'] =
                            $usuario['apellido'];


                        /* =================================
                           REDIRECCIÓN
                        ================================= */

                        switch ($rol) {

                            case "administrador":

                                header(
                                    "Location: admin.php"
                                );

                                exit();


                            case "jurado":

                                header(
                                    "Location: jurado.php"
                                );

                                exit();


                            case "estudiante":

                                header(
                                    "Location: votar.php"
                                );

                                exit();

                        }

                    }

                }

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


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<!-- CSS DEL PROYECTO -->

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

</style>

</head>


<body>


<div class="login">


<!-- LOGO -->

<div class="logo">

🗳️

</div>


<!-- TÍTULO -->

<h2>

Sistema de Votaciones

</h2>


<!-- ERROR -->

<?php if ($error !== "") { ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars($error); ?>

</div>

<?php } ?>


<!-- FORMULARIO -->

<form method="POST">


<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label fw-bold">

<i class="bi bi-person-vcard-fill"></i>

Documento

</label>


<div class="input-group">

<span class="input-group-text">

<i class="bi bi-person-fill"></i>

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


<!-- CONTRASEÑA -->

<div class="mb-4">

<label class="form-label fw-bold">

<i class="bi bi-lock-fill"></i>

Contraseña

</label>


<div class="input-group">

<span class="input-group-text">

<i class="bi bi-key-fill"></i>

</span>


<input

type="password"

name="password"

id="password"

class="form-control"

placeholder="Ingrese su contraseña"

required

autocomplete="current-password">


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


<!-- RECORDAR -->

<div class="form-check mb-4">

<input

class="form-check-input"

type="checkbox"

id="recordar">


<label

class="form-check-label"

for="recordar">

Recordar documento

</label>

</div>


<!-- INGRESAR -->

<button

type="submit"

name="ingresar"

class="btn btn-primary btn-login">

<i class="bi bi-box-arrow-in-right"></i>

Ingresar

</button>


</form>


<hr>


<div class="text-center">

<h6>

Sistema de Votaciones Escolares

</h6>

<small>

Versión 2.0

</small>

</div>


<div class="footer">

© <?php echo date("Y"); ?>

Todos los derechos reservados

</div>


</div>


<script>

function mostrarPassword() {

    const pass =
        document.getElementById("password");

    const icono =
        document.getElementById("iconoPassword");


    if (pass.type === "password") {

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

const documento =
    document.querySelector(
        'input[name="documento"]'
    );

const recordar =
    document.getElementById("recordar");


const documentoGuardado =
    localStorage.getItem(
        "documentoVotaciones"
    );


if (documentoGuardado) {

    documento.value =
        documentoGuardado;

    recordar.checked =
        true;

}


recordar.addEventListener(
    "change",
    function() {

        if (this.checked) {

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
);


documento.addEventListener(
    "input",
    function() {

        if (recordar.checked) {

            localStorage.setItem(
                "documentoVotaciones",
                documento.value
            );

        }

    }
);

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
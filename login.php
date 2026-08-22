<?php

session_start();

include("config/conexion.php");


/* =========================================================
   SI YA EXISTE UNA SESIÓN
========================================================= */

if (
    isset($_SESSION['id']) &&
    isset($_SESSION['rol'])
) {

    $rolSesion = strtolower(
        trim(
            (string)$_SESSION['rol']
        )
    );


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

}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";

$documento = "";


/* =========================================================
   PROCESAR LOGIN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ingresar'])
) {


    /* =====================================================
       RECIBIR DATOS
    ===================================================== */

    $documento = trim(
        $_POST['documento'] ?? ""
    );


    $password = trim(
        $_POST['password'] ?? ""
    );


    /* =====================================================
       VALIDAR CAMPOS
    ===================================================== */

    if (
        $documento === "" ||
        $password === ""
    ) {

        $error =
            "Debe completar todos los campos.";

    } else {


        /* =================================================
           BUSCAR USUARIO
        ================================================= */

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
                "No fue posible realizar el inicio de sesión.";

        } else {


            $stmt->bind_param(
                "s",
                $documento
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            /* =============================================
               DOCUMENTO NO ENCONTRADO
            ============================================= */

            if (
                $resultado->num_rows === 0
            ) {

                $error =
                    "Documento no registrado.";

            } else {


                $usuario =
                    $resultado->fetch_assoc();


                /* =========================================
                   CONTRASEÑA
                ========================================= */

                $loginCorrecto = false;


                /* =========================================
                   CONTRASEÑA CIFRADA
                ========================================= */

                if (
                    !empty(
                        $usuario['password']
                    ) &&
                    password_verify(
                        $password,
                        $usuario['password']
                    )
                ) {

                    $loginCorrecto = true;

                }


                /* =========================================
                   CONTRASEÑA ANTIGUA EN TEXTO PLANO
                ========================================= */

                elseif (
                    $password ===
                    $usuario['password']
                ) {

                    $loginCorrecto = true;


                    /* =====================================
                       CONVERTIR A PASSWORD HASH
                    ===================================== */

                    $nuevaPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    $stmtActualizar =
                        $conn->prepare("

                            UPDATE usuarios

                            SET password = ?

                            WHERE id = ?

                        ");


                    if ($stmtActualizar) {

                        $stmtActualizar->bind_param(
                            "si",
                            $nuevaPassword,
                            $usuario['id']
                        );


                        $stmtActualizar->execute();


                        $stmtActualizar->close();

                    }

                }


                /* =========================================
                   LOGIN CORRECTO
                ========================================= */

                if (
                    $loginCorrecto
                ) {


                    /* =====================================
                       NORMALIZAR ROL
                    ===================================== */

                    $rol =
                        strtolower(
                            trim(
                                (string)
                                $usuario['rol']
                            )
                        );


                    /* =====================================
                       VERIFICAR ROL
                    ===================================== */

                    if (
                        $rol !== "administrador" &&
                        $rol !== "jurado" &&
                        $rol !== "estudiante"
                    ) {

                        $error =
                            "El usuario tiene un rol no válido.";

                    } else {


                        /* =================================
                           REGENERAR ID DE SESIÓN
                        ================================= */

                        session_regenerate_id(
                            true
                        );


                        /* =================================
                           GUARDAR DATOS
                        ================================= */

                        $_SESSION['id'] =
                            (int)$usuario['id'];


                        $_SESSION['nombre'] =
                            $usuario['nombre'];


                        $_SESSION['rol'] =
                            $rol;


                        /* =================================
                           LIMPIAR DATOS DE VOTACIÓN
                           DE UNA SESIÓN ANTERIOR
                        ================================= */

                        unset(

                            $_SESSION[
                                'estudiante_votando_id'
                            ],

                            $_SESSION[
                                'estudiante_votando_documento'
                            ],

                            $_SESSION[
                                'estudiante_votando_nombre'
                            ],

                            $_SESSION[
                                'estudiante_votando_curso'
                            ],

                            $_SESSION[
                                'eleccion_votante_id'
                            ]

                        );


                        /* =================================
                           REDIRECCIONES
                        ================================= */

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


                } else {

                    $error =
                        "Contraseña incorrecta.";

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


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =====================================================
     ICONOS
===================================================== -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    min-height:100vh;

    margin:0;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        linear-gradient(
            135deg,
            #0d47a1,
            #1565c0
        );

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.login {

    width:100%;

    max-width:440px;

    background:white;

    padding:40px;

    border-radius:22px;

    box-shadow:
        0 12px 35px
        rgba(0,0,0,.25);

}


.logo {

    text-align:center;

    font-size:70px;

    margin-bottom:10px;

}


.login h2 {

    text-align:center;

    color:#0d47a1;

    font-weight:bold;

    margin-bottom:30px;

}


.form-label {

    font-weight:bold;

}


.input-group-text {

    background:#0d6efd;

    color:white;

}


.form-control {

    height:48px;

}


.btn-login {

    width:100%;

    height:52px;

    font-size:18px;

    font-weight:bold;

}


.footer {

    margin-top:20px;

    text-align:center;

    color:#777;

    font-size:13px;

}


.info {

    text-align:center;

    margin-top:20px;

}


</style>

</head>


<body>


<div class="login">


<!-- =====================================================
     LOGO
===================================================== -->

<div class="logo">

🗳️

</div>


<h2>

Sistema de Votaciones

</h2>


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
     FORMULARIO
===================================================== -->

<form
method="POST"
autocomplete="off">


<!-- =====================================================
     DOCUMENTO
===================================================== -->

<div class="mb-3">

<label
for="documento"
class="form-label">

<i class="bi bi-person-vcard-fill"></i>

Documento

</label>


<div class="input-group">

<span class="input-group-text">

<i class="bi bi-person"></i>

</span>


<input

type="text"

name="documento"

id="documento"

class="form-control"

placeholder="Ingrese su documento"

value="<?php echo htmlspecialchars(
    $documento
); ?>"

autocomplete="off"

required>

</div>

</div>


<!-- =====================================================
     CONTRASEÑA
===================================================== -->

<div class="mb-4">

<label
for="password"
class="form-label">

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

required>


<button

type="button"

class="btn btn-outline-secondary"

onclick="mostrarPassword()">

<i
class="bi bi-eye"
id="iconoPassword">

</i>

</button>


</div>

</div>


<!-- =====================================================
     INGRESAR
===================================================== -->

<button

type="submit"

name="ingresar"

class="btn btn-primary btn-login">

<i class="bi bi-box-arrow-in-right"></i>

Ingresar

</button>


</form>


<hr>


<div class="info">

<h6>

Sistema de Votaciones Escolares

</h6>


<small>

Administrador · Jurado · Estudiante

</small>

</div>


<div class="footer">

© <?php echo date("Y"); ?>

Todos los derechos reservados.

</div>


</div>


<script>

/* =========================================================
   MOSTRAR / OCULTAR CONTRASEÑA
========================================================= */

function mostrarPassword() {


    const password =
        document.getElementById(
            "password"
        );


    const icono =
        document.getElementById(
            "iconoPassword"
        );


    if (
        password.type ===
        "password"
    ) {


        password.type =
            "text";


        icono.className =
            "bi bi-eye-slash";


    } else {


        password.type =
            "password";


        icono.className =
            "bi bi-eye";

    }

}

</script>


</body>

</html>
<?php

session_start();

include("config/conexion.php");


/*=========================================
=          SI YA HAY SESIÓN              =
=========================================*/

if(isset($_SESSION['id'])){

    if($_SESSION['rol'] == "administrador"){

        header("Location: admin.php");
        exit();

    }

    if($_SESSION['rol'] == "estudiante"){

        header("Location: votar.php");
        exit();

    }

    if($_SESSION['rol'] == "jurado"){

        header("Location: jurado.php");
        exit();

    }

}


/*=========================================
=          VARIABLES                     =
=========================================*/

$error = "";


/*=========================================
=          PROCESAR LOGIN                =
=========================================*/

if(isset($_POST['ingresar'])){

    $documento = trim($_POST['documento']);
    $password = trim($_POST['password']);


    if($documento == "" || $password == ""){

        $error = "Debe completar todos los campos.";

    }else{


        /* Buscar usuario */

        $sql = $conn->query("
            SELECT *
            FROM usuarios
            WHERE documento='$documento'
            LIMIT 1
        ");


        if($sql && $sql->num_rows > 0){

            $usuario = $sql->fetch_assoc();


            /*=========================================
            =      VALIDAR CONTRASEÑA
            =========================================*/

            $loginCorrecto = false;


            /* Contraseña cifrada */

            if(password_verify($password, $usuario['password'])){

                $loginCorrecto = true;

            }


            /* Contraseña antigua en texto */

            elseif($password == $usuario['password']){

                $loginCorrecto = true;


                /* Convertir automáticamente a contraseña cifrada */

                $nuevaPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                $conn->query("
                    UPDATE usuarios
                    SET password='$nuevaPassword'
                    WHERE id=".$usuario['id']."
                ");

            }


            /*=========================================
            =          INICIAR SESIÓN
            =========================================*/

            if($loginCorrecto){

                $_SESSION['id'] = $usuario['id'];

                $_SESSION['nombre'] = $usuario['nombre'];

                $_SESSION['rol'] = $usuario['rol'];


                /*=========================================
                =          REDIRECCIONES
                =========================================*/

                if($usuario['rol'] == "administrador"){

                    header("Location: admin.php");
                    exit();

                }


                if($usuario['rol'] == "estudiante"){

                    header("Location: votar.php");
                    exit();

                }


                if($usuario['rol'] == "jurado"){

                    header("Location: jurado.php");
                    exit();

                }


                /* Si el rol no existe */

                $error = "El usuario tiene un rol no válido.";

                session_destroy();

            }else{

                $error = "Contraseña incorrecta.";

            }

        }else{

            $error = "Documento no registrado.";

        }

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Sistema de Votaciones</title>


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

background:linear-gradient(
135deg,
#0d47a1,
#1565c0
);

height:100vh;

display:flex;

justify-content:center;

align-items:center;

}


.login{

width:430px;

background:white;

padding:40px;

border-radius:20px;

box-shadow:0 10px 30px rgba(0,0,0,.25);

}


.logo{

font-size:70px;

text-align:center;

margin-bottom:15px;

}


.login h2{

text-align:center;

margin-bottom:30px;

color:#0d47a1;

}


.input-group-text{

background:#0d6efd;

color:white;

}


.btn-login{

width:100%;

padding:12px;

font-size:18px;

}


.footer{

margin-top:20px;

text-align:center;

font-size:13px;

color:gray;

}

</style>

</head>


<body>


<div class="login">


<div class="logo">

🗳️

</div>


<h2>

Sistema de Votaciones

</h2>


<?php

if($error != ""){

?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo $error; ?>

</div>

<?php

}

?>


<form method="POST">


<div class="mb-3">

<label>

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

class="form-control"

placeholder="Ingrese su documento"

required

autocomplete="off">

</div>

</div>


<div class="mb-4">

<label>

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

function mostrarPassword(){

let pass =
document.getElementById("password");

let icono =
document.getElementById("iconoPassword");


if(pass.type == "password"){

pass.type = "text";

icono.className =
"bi bi-eye-slash";

}else{

pass.type = "password";

icono.className =
"bi bi-eye";

}

}

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
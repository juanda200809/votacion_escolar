<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR QUE SEA JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'jurado'
) {

    header("Location: login.php");
    exit();

}


$error = "";


/* =========================================================
   PROCESAR DOCUMENTO
========================================================= */

if (isset($_POST['continuar'])) {

    $documento = trim($_POST['documento'] ?? "");


    if ($documento === "") {

        $error = "Ingrese el documento del estudiante.";

    } else {


        /* =====================================================
           BUSCAR ESTUDIANTE
        ===================================================== */

        $stmt = $conn->prepare("
            SELECT
                id,
                documento,
                nombre,
                apellido,
                curso,
                rol
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");


        $stmt->bind_param(
            "s",
            $documento
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        if ($resultado->num_rows === 0) {

            $error =
                "El documento no está registrado.";

        } else {


            $estudiante =
                $resultado->fetch_assoc();


            /* =================================================
               VERIFICAR QUE SEA ESTUDIANTE
            ================================================= */

            if (
                strtolower(
                    trim($estudiante['rol'])
                ) !== 'estudiante'
            ) {

                $error =
                    "El documento ingresado no pertenece a un estudiante.";

            } else {


                /* =============================================
                   GUARDAR ESTUDIANTE PARA LA VOTACIÓN
                ============================================= */

                $_SESSION['estudiante_votando_id'] =
                    (int)$estudiante['id'];

                $_SESSION['estudiante_votando_documento'] =
                    $estudiante['documento'];

                $_SESSION['estudiante_votando_nombre'] =
                    $estudiante['nombre'] .
                    " " .
                    $estudiante['apellido'];


                /* =============================================
                   IR A LA VOTACIÓN
                ============================================= */

                header(
                    "Location: votar_por_jurado.php"
                );

                exit();

            }

        }


        $stmt->close();

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
Ingresar estudiante
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #0d47a1,
            #1565c0
        );

    display: flex;

    align-items: center;

    justify-content: center;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.contenedor {

    width: 100%;

    max-width: 480px;

    padding: 20px;
}


.card-documento {

    background: white;

    border-radius: 22px;

    padding: 40px;

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.25);
}


.icono {

    text-align: center;

    font-size: 65px;

    color: #1473ed;

    margin-bottom: 10px;
}


.titulo {

    text-align: center;

    color: #1453a3;

    font-weight: bold;

    margin-bottom: 8px;
}


.descripcion {

    text-align: center;

    color: #6c757d;

    margin-bottom: 30px;
}


.form-label {

    font-weight: bold;

    color: #333;
}


.input-documento {

    height: 55px;

    font-size: 18px;
}


.btn-continuar {

    width: 100%;

    height: 55px;

    background: #1473ed;

    border: none;

    font-size: 18px;

    font-weight: bold;

    border-radius: 10px;
}


.btn-continuar:hover {

    background: #0d5dcc;
}


.btn-volver {

    width: 100%;

    margin-top: 12px;

}


.informacion {

    background: #eef5ff;

    border-radius: 12px;

    padding: 15px;

    margin-bottom: 25px;

    color: #1453a3;
}

</style>

</head>


<body>


<div class="contenedor">


<div class="card-documento">


<!-- ICONO -->

<div class="icono">

<i class="bi bi-person-vcard-fill"></i>

</div>


<!-- TÍTULO -->

<h2 class="titulo">

Ingresar estudiante

</h2>


<p class="descripcion">

Ingrese el documento del estudiante
que va a realizar la votación.

</p>


<!-- INFORMACIÓN -->

<div class="informacion">

<i class="bi bi-info-circle-fill"></i>

El estudiante debe estar registrado
en el sistema para poder votar.

</div>


<!-- ERROR -->

<?php if ($error !== "") { ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars($error); ?>

</div>

<?php } ?>


<!-- FORMULARIO -->

<form method="POST">


<label class="form-label">

<i class="bi bi-person-vcard"></i>

Documento del estudiante

</label>


<div class="input-group mb-4">


<span class="input-group-text">

<i class="bi bi-person"></i>

</span>


<input
type="text"
name="documento"
class="form-control input-documento"
placeholder="Ingrese el documento"
autocomplete="off"
required
autofocus>


</div>


<!-- BOTÓN -->

<button
type="submit"
name="continuar"
class="btn btn-primary btn-continuar">

<i class="bi bi-arrow-right-circle-fill"></i>

Continuar con la votación

</button>


<!-- VOLVER -->

<a
href="jurado.php"
class="btn btn-outline-secondary btn-volver">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


</form>


<hr class="mt-4">


<div class="text-center text-muted">

<small>

Sistema de Votaciones Escolares

</small>

<br>

<small>

Jurado: 

<strong>

<?php echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Jurado'
); ?>

</strong>

</small>

</div>


</div>

</div>


</body>

</html>
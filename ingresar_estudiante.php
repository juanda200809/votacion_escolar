<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit();

}


$rol = strtolower(
    trim(
        (string) $_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    header("Location: login.php");
    exit();

}


/* =========================================================
   CONFIGURACIÓN
========================================================= */

date_default_timezone_set("America/Bogota");


/* =========================================================
   TOKEN CSRF
========================================================= */

if (
    !isset($_SESSION['csrf_estudiante'])
) {

    $_SESSION['csrf_estudiante'] =
        bin2hex(
            random_bytes(32)
        );

}


$csrf =
    $_SESSION['csrf_estudiante'];


/* =========================================================
   VARIABLES
========================================================= */

$error = "";

$documento = "";


/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    /* =====================================================
       VERIFICAR CSRF
    ===================================================== */

    if (
        !isset($_POST['csrf']) ||
        !hash_equals(
            $_SESSION['csrf_estudiante'],
            $_POST['csrf']
        )
    ) {

        $error =
            "La solicitud no es válida. Intente nuevamente.";

    }


    /* =====================================================
       OBTENER DOCUMENTO
    ===================================================== */

    if (
        $error === ""
    ) {

        $documento =
            trim(
                $_POST['documento'] ?? ""
            );

    }


    /* =====================================================
       VALIDAR DOCUMENTO VACÍO
    ===================================================== */

    if (
        $error === "" &&
        $documento === ""
    ) {

        $error =
            "Debe ingresar el número de documento.";

    }


    /* =====================================================
       VALIDAR FORMATO
    ===================================================== */

    if (
        $error === "" &&
        !preg_match(
            '/^[0-9]+$/',
            $documento
        )
    ) {

        $error =
            "El documento solamente debe contener números.";

    }


    /* =====================================================
       VERIFICAR ELECCIÓN ABIERTA
    ===================================================== */

    $eleccion = null;


    if (
        $error === ""
    ) {

        $stmt =
            $conn->prepare("

                SELECT
                    id,
                    nombre,
                    descripcion,
                    estado

                FROM elecciones

                WHERE estado = 'abierta'

                ORDER BY id DESC

                LIMIT 1

            ");


        if (!$stmt) {

            $error =
                "No fue posible verificar la elección.";

        } else {

            $stmt->execute();

            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows === 0
            ) {

                $error =
                    "Actualmente no hay una elección abierta.";

            } else {

                $eleccion =
                    $resultado->fetch_assoc();

            }


            $stmt->close();

        }

    }


    /* =====================================================
       BUSCAR ESTUDIANTE POR DOCUMENTO
    ===================================================== */

    $estudiante = null;


    if (
        $error === "" &&
        $eleccion
    ) {

        $stmt =
            $conn->prepare("

                SELECT
                    id,
                    documento,
                    nombre,
                    apellido,
                    curso,
                    rol

                FROM usuarios

                WHERE documento = ?

                AND LOWER(
                    TRIM(rol)
                ) = 'estudiante'

                LIMIT 1

            ");


        if (!$stmt) {

            $error =
                "No fue posible verificar el estudiante.";

        } else {

            $stmt->bind_param(
                "s",
                $documento
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows === 0
            ) {

                $error =
                    "El documento no corresponde a un estudiante registrado.";

            } else {

                $estudiante =
                    $resultado->fetch_assoc();

            }


            $stmt->close();

        }

    }


    /* =====================================================
       VERIFICAR SI YA VOTÓ
    ===================================================== */

    if (
        $error === "" &&
        $estudiante &&
        $eleccion
    ) {

        $idEstudiante =
            (int) $estudiante['id'];


        $idEleccion =
            (int) $eleccion['id'];


        $stmt =
            $conn->prepare("

                SELECT id

                FROM votos

                WHERE
                    id_usuario = ?

                    AND id_eleccion = ?

                LIMIT 1

            ");


        if (!$stmt) {

            $error =
                "No fue posible verificar el estado de la votación.";

        } else {

            $stmt->bind_param(
                "ii",
                $idEstudiante,
                $idEleccion
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows > 0
            ) {

                $error =
                    "Este estudiante ya realizó su votación.";

            }


            $stmt->close();

        }

    }


    /* =====================================================
       CREAR SESIÓN DEL ESTUDIANTE
    ===================================================== */

    if (
        $error === "" &&
        $estudiante &&
        $eleccion
    ) {


        /*
         * Regeneramos el identificador de sesión
         * antes de comenzar el proceso de votación.
         */

        session_regenerate_id(true);


        $_SESSION['estudiante_jurado'] =
            (int) $estudiante['id'];


        $_SESSION['eleccion_jurado'] =
            (int) $eleccion['id'];


        $_SESSION['documento_estudiante'] =
            $estudiante['documento'];


        $_SESSION['nombre_estudiante'] =
            $estudiante['nombre']
            . " "
            . $estudiante['apellido'];


        $_SESSION['hora_inicio_votacion'] =
            time();


        /* =================================================
           GENERAR TOKEN PARA LA VOTACIÓN
        ================================================= */

        $_SESSION['token_votacion'] =
            bin2hex(
                random_bytes(32)
            );


        /* =================================================
           REDIRIGIR A LA VOTACIÓN
        ================================================= */

        header(
            "Location: votar_por_jurado.php"
        );

        exit();

    }

}


/* =========================================================
   OBTENER ELECCIÓN ACTUAL PARA MOSTRAR
========================================================= */

$eleccionActual = null;


$stmt =
    $conn->prepare("

        SELECT
            id,
            nombre,
            descripcion

        FROM elecciones

        WHERE estado = 'abierta'

        ORDER BY id DESC

        LIMIT 1

    ");


if ($stmt) {

    $stmt->execute();

    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows > 0
    ) {

        $eleccionActual =
            $resultado->fetch_assoc();

    }


    $stmt->close();

}

?>


<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Identificar estudiante | Votaciones Escolares
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<style>

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    min-height: 100vh;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #26374a;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    min-height: 70px;

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 32px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.header-title {

    font-size: 23px;

    font-weight: 700;

}


.header-user {

    font-size: 15px;

    font-weight: 600;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    width:
        min(700px, calc(100% - 30px));

    margin:
        45px auto;

}


/* =========================================================
   TARJETA PRINCIPAL
========================================================= */

.card-principal {

    background: white;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.09);

}


/* =========================================================
   ENCABEZADO
========================================================= */

.card-header-custom {

    background:
        linear-gradient(
            135deg,
            #176df0,
            #06479f
        );

    color: white;

    padding: 32px;

}


.card-header-custom h1 {

    margin: 0;

    font-size: 31px;

    font-weight: 700;

}


.card-header-custom p {

    margin:
        9px 0 0;

    opacity: .92;

}


/* =========================================================
   CUERPO
========================================================= */

.card-body-custom {

    padding: 32px;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: #eaf3ff;

    border:
        1px solid #c9ddfa;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 24px;

}


.eleccion h3 {

    margin:
        0 0 6px;

    color: #1453a3;

    font-size: 21px;

    font-weight: 700;

}


.eleccion p {

    margin: 0;

    color: #527094;

}


/* =========================================================
   SEGURIDAD
========================================================= */

.seguridad {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    background: #f4f8fd;

    border:
        1px solid #dce7f3;

    padding: 16px;

    border-radius: 12px;

    margin-bottom: 25px;

    color: #536b84;

    font-size: 14px;

}


.seguridad i {

    color: #1473ed;

    font-size: 23px;

    flex-shrink: 0;

}


/* =========================================================
   ERROR
========================================================= */

.error {

    background: #fff1f1;

    color: #b42318;

    border:
        1px solid #ffcaca;

    border-radius: 10px;

    padding: 14px 16px;

    margin-bottom: 20px;

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 14px;

}


/* =========================================================
   FORMULARIO
========================================================= */

.form-label {

    display: block;

    color: #1453a3;

    font-weight: 700;

    margin-bottom: 8px;

}


.input-documento {

    width: 100%;

    height: 56px;

    border:
        1px solid #ccd8e7;

    border-radius: 10px;

    padding:
        0 16px;

    font-size: 18px;

    outline: none;

    transition: .2s ease;

}


.input-documento:focus {

    border-color: #1473ed;

    box-shadow:
        0 0 0 3px
        rgba(20,115,237,.12);

}


/* =========================================================
   BOTÓN
========================================================= */

.btn-continuar {

    width: 100%;

    height: 56px;

    margin-top: 20px;

    border: none;

    border-radius: 10px;

    background: #1473ed;

    color: white;

    font-size: 16px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s ease;

}


.btn-continuar:hover {

    background: #0d5fcf;

    transform:
        translateY(-1px);

}


/* =========================================================
   INFORMACIÓN
========================================================= */

.ayuda {

    margin-top: 12px;

    color: #7a8ca1;

    font-size: 12px;

}


/* =========================================================
   VOLVER
========================================================= */

.volver {

    display: block;

    text-align: center;

    margin-top: 22px;

    color: #1453a3;

    text-decoration: none;

    font-weight: 600;

}


.volver:hover {

    text-decoration: underline;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    color: #718096;

    font-size: 12px;

    padding:
        20px;

}


.footer strong {

    color: #1453a3;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:600px) {

    .header {

        padding:
            0 15px;

    }


    .header-title {

        font-size: 18px;

    }


    .header-user {

        font-size: 12px;

    }


    .contenedor {

        margin:
            25px auto;

    }


    .card-header-custom {

        padding: 24px;

    }


    .card-header-custom h1 {

        font-size: 26px;

    }


    .card-body-custom {

        padding: 22px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="header">


<div class="header-title">

    <i class="bi bi-check2-square"></i>

    Sistema de Votaciones Escolares

</div>


<div class="header-user">

    <i class="bi bi-person-badge-fill"></i>

    Jurado:

    <strong>

        <?php

        echo htmlspecialchars(
            $_SESSION['nombre'] ?? 'Jurado'
        );

        ?>

    </strong>

</div>


</header>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="contenedor">


<div class="card-principal">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="card-header-custom">

    <h1>

        <i class="bi bi-person-vcard-fill"></i>

        Identificación del estudiante

    </h1>


    <p>

        Ingrese el número de documento
        del estudiante que realizará la votación.

    </p>

</div>


<!-- =====================================================
     CUERPO
===================================================== -->

<div class="card-body-custom">


<?php if (
    $eleccionActual
) { ?>


<div class="eleccion">


<h3>

    <i class="bi bi-calendar-check-fill"></i>

    <?php

    echo htmlspecialchars(
        $eleccionActual['nombre']
    );

    ?>

</h3>


<p>

    <?php

    echo htmlspecialchars(
        $eleccionActual['descripcion']
    );

    ?>

</p>


</div>


<?php } else { ?>


<div class="error">

    <i class="bi bi-exclamation-triangle-fill"></i>

    Actualmente no existe una elección abierta.

</div>


<?php } ?>


<!-- =====================================================
     SEGURIDAD
===================================================== -->

<div class="seguridad">


<i class="bi bi-shield-lock-fill"></i>


<div>

<strong>

    Proceso protegido

</strong>

<br>

El sistema verificará el documento
directamente con los estudiantes registrados.
No se mostrarán datos de otros estudiantes.

</div>


</div>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if (
    $error !== ""
) { ?>


<div class="error">

    <i class="bi bi-exclamation-circle-fill"></i>

    <?php

    echo htmlspecialchars(
        $error
    );

    ?>

</div>


<?php } ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
    method="POST"
    action="ingresar_estudiante.php"
    autocomplete="off"
>


<input
    type="hidden"
    name="csrf"
    value="<?php

    echo htmlspecialchars(
        $csrf
    );

    ?>"
>


<label
    for="documento"
    class="form-label"
>

    Número de documento

</label>


<input
    type="text"
    id="documento"
    name="documento"
    class="input-documento"
    value="<?php

    echo htmlspecialchars(
        $documento
    );

    ?>"
    placeholder="Ingrese solamente números"
    inputmode="numeric"
    pattern="[0-9]+"
    maxlength="20"
    autocomplete="off"
    required
>


<div class="ayuda">

    <i class="bi bi-info-circle"></i>

    Escriba únicamente el número de documento
    del estudiante.

</div>


<button
    type="submit"
    class="btn-continuar"
>

    <i class="bi bi-arrow-right-circle-fill"></i>

    Continuar con la votación

</button>


</form>


<!-- =====================================================
     VOLVER
===================================================== -->

<a
    href="jurado.php"
    class="volver"
>

    <i class="bi bi-arrow-left"></i>

    Volver al panel del jurado

</a>


</div>

</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

    © <?php echo date("Y"); ?>

    Sistema de Votaciones Escolares

    <br>

    Elaborado por

    <strong>
        Juan David Otero Cantor
    </strong>

    <br>

    Todos los derechos reservados.

</footer>


</main>


</body>

</html>
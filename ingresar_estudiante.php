<?php

session_start();

require_once __DIR__ . "/config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");


/* =========================================================
   SEGURIDAD
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {
    die("Sesión de jurado no válida.");
}

$rol = strtolower(trim((string) $_SESSION['rol']));

if ($rol !== "jurado") {
    die("Acceso no autorizado.");
}


/* =========================================================
   OBTENER ELECCIÓN ACTIVA
========================================================= */

$idEleccion = 0;

if (isset($_SESSION['id_eleccion_jurado'])) {
    $idEleccion = (int) $_SESSION['id_eleccion_jurado'];
}

if ($idEleccion <= 0) {
    die(
        "No se pudo identificar la elección. " .
        "Regrese al panel del jurado y presione " .
        "\"Comenzar votación\" nuevamente."
    );
}


/* =========================================================
   VERIFICAR ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        estado
    FROM elecciones
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$resultado = $stmt->get_result();

$eleccion = $resultado->fetch_assoc();

$stmt->close();


if (!$eleccion) {
    die("La elección no existe.");
}


/* =========================================================
   VERIFICAR QUE ESTÉ ABIERTA
========================================================= */

if (
    strtolower(trim($eleccion['estado'])) !== "abierta"
) {
    die("La elección está cerrada.");
}


/* =========================================================
   MENSAJE DESPUÉS DE VOTAR
========================================================= */

$mensaje = "";

if (isset($_SESSION['mensaje_votacion'])) {

    $mensaje = $_SESSION['mensaje_votacion'];

    unset($_SESSION['mensaje_votacion']);
}


/* =========================================================
   VARIABLES
========================================================= */

$documento = "";

$error = "";


/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $documento = trim(
        $_POST['documento'] ?? ''
    );


    /* =====================================================
       VALIDAR DOCUMENTO
    ===================================================== */

    if ($documento === '') {

        $error =
            "Debe ingresar el número de documento.";

    } elseif (!ctype_digit($documento)) {

        $error =
            "El número de documento debe contener " .
            "solamente números.";

    } else {


        /* =================================================
           BUSCAR ESTUDIANTE
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                documento,
                nombre,
                apellido,
                curso
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $documento
        );

        $stmt->execute();

        $resultadoEstudiante =
            $stmt->get_result();

        $estudiante =
            $resultadoEstudiante->fetch_assoc();

        $stmt->close();


        /* =================================================
           SI NO EXISTE
        ================================================= */

        if (!$estudiante) {

            $error =
                "No se encontró ningún estudiante " .
                "registrado con ese número de documento.";

        } else {


            $idEstudiante =
                (int) $estudiante['id'];


            /* =============================================
               COMPROBAR SI YA VOTÓ EN ESTA ELECCIÓN

               NO usamos solamente id_usuario.
               También comprobamos id_eleccion.
            ============================================= */

            $stmt = $conn->prepare("
                SELECT
                    COUNT(*) AS cantidad
                FROM votos
                WHERE id_usuario = ?
                  AND id_eleccion = ?
            ");

            $stmt->bind_param(
                "ii",
                $idEstudiante,
                $idEleccion
            );

            $stmt->execute();

            $resultadoVoto =
                $stmt->get_result();

            $filaVoto =
                $resultadoVoto->fetch_assoc();

            $stmt->close();


            $cantidadVotos =
                (int) $filaVoto['cantidad'];


            /* =============================================
               YA VOTÓ
            ============================================= */

            if ($cantidadVotos > 0) {

                $error =
                    "Este estudiante ya realizó " .
                    "su votación en esta elección.";

            } else {


                /* =========================================
                   GUARDAR ESTUDIANTE EN SESIÓN

                   ESTE ES EL DATO QUE NECESITA
                   votar_por_jurado.php
                ========================================= */

                $_SESSION['estudiante_votando_id'] =
                    $idEstudiante;


                $_SESSION['estudiante_votando_documento'] =
                    $estudiante['documento'];


                $_SESSION['estudiante_votando_nombre'] =
                    $estudiante['nombre'];


                /* =========================================
                   GUARDAR ELECCIÓN NUEVAMENTE
                   PARA EVITAR QUE SE PIERDA
                ========================================= */

                $_SESSION['id_eleccion_jurado'] =
                    $idEleccion;


                /* =========================================
                   IR A VOTAR
                ========================================= */

                header(
                    "Location: votar_por_jurado.php"
                );

                exit;
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
    content="width=device-width, initial-scale=1.0"
>

<title>
    Identificación del estudiante
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #eef5ff,
            #dcecff
        );

    color: #174a82;
}


/* =====================================================
   CONTENEDOR
===================================================== */

.container {

    width: 90%;

    max-width: 720px;

    margin: 50px auto;

    background: white;

    border-radius: 22px;

    overflow: hidden;

    box-shadow:
        0 15px 45px
        rgba(
            0,
            60,
            130,
            0.15
        );
}


/* =====================================================
   CABECERA
===================================================== */

.header {

    padding: 35px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #1266d6,
            #0751b8
        );
}


.header-icon {

    font-size: 42px;

    margin-bottom: 10px;
}


.header h1 {

    margin: 0 0 10px;

    font-size: 30px;
}


.header p {

    margin: 0;

    font-size: 16px;

    opacity: .92;

    line-height: 1.5;
}


/* =====================================================
   CONTENIDO
===================================================== */

.content {

    padding: 35px;
}


/* =====================================================
   ELECCIÓN
===================================================== */

.election {

    padding: 22px;

    margin-bottom: 25px;

    background: #eef6ff;

    border: 1px solid #c4dcfb;

    border-radius: 16px;
}


.election h2 {

    margin: 0 0 8px;

    color: #1266d6;

    font-size: 22px;
}


.election p {

    margin: 0;

    color: #60758e;
}


/* =====================================================
   PROCESO PROTEGIDO
===================================================== */

.protected {

    display: flex;

    gap: 15px;

    padding: 20px;

    margin-bottom: 25px;

    border-radius: 15px;

    background: #f5f9ff;

    border: 1px solid #d8e6f7;
}


.protected-icon {

    font-size: 27px;
}


.protected h3 {

    margin: 0 0 5px;

    color: #1266d6;
}


.protected p {

    margin: 0;

    color: #60758e;

    line-height: 1.5;
}


/* =====================================================
   MENSAJE ÉXITO
===================================================== */

.success {

    padding: 16px;

    margin-bottom: 22px;

    border-radius: 12px;

    background: #eaf9ef;

    border: 1px solid #9bdcaf;

    color: #176c39;

    font-weight: bold;
}


/* =====================================================
   ERROR
===================================================== */

.error {

    padding: 16px;

    margin-bottom: 22px;

    border-radius: 12px;

    background: #fff0f0;

    border: 1px solid #ffb7b7;

    color: #c62828;

    line-height: 1.5;
}


/* =====================================================
   FORMULARIO
===================================================== */

label {

    display: block;

    margin-bottom: 10px;

    font-size: 16px;

    font-weight: bold;

    color: #1266d6;
}


input {

    width: 100%;

    padding: 18px;

    border-radius: 13px;

    border: 2px solid #d0dfef;

    outline: none;

    font-size: 19px;

    color: #173d67;

    transition: .2s;
}


input:focus {

    border-color: #1266d6;

    box-shadow:
        0 0 0 4px
        rgba(
            18,
            102,
            214,
            .10
        );
}


.help {

    margin-top: 8px;

    color: #71849a;

    font-size: 13px;
}


/* =====================================================
   BOTÓN
===================================================== */

button {

    width: 100%;

    margin-top: 22px;

    padding: 18px;

    border: none;

    border-radius: 13px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #1266d6,
            #0751b8
        );

    font-size: 18px;

    font-weight: bold;

    cursor: pointer;

    transition: .2s;

    box-shadow:
        0 8px 20px
        rgba(
            18,
            102,
            214,
            .22
        );
}


button:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 26px
        rgba(
            18,
            102,
            214,
            .28
        );
}


/* =====================================================
   PIE
===================================================== */

.footer-info {

    margin-top: 20px;

    text-align: center;

    color: #75889e;

    font-size: 13px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 600px) {

    .container {

        width: 94%;

        margin: 25px auto;
    }

    .header {

        padding: 28px;
    }

    .content {

        padding: 25px;
    }

    .header h1 {

        font-size: 25px;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- ================================================
         CABECERA
    ================================================= -->

    <div class="header">

        <div class="header-icon">
            🪪
        </div>

        <h1>
            Identificación del estudiante
        </h1>

        <p>
            Ingrese el número de documento
            del estudiante que realizará
            la votación.
        </p>

    </div>


    <div class="content">


        <!-- ============================================
             ELECCIÓN
        ============================================= -->

        <div class="election">

            <h2>

                🗳️

                <?php

                echo htmlspecialchars(
                    $eleccion['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </h2>

            <p>
                Proceso democrático institucional
            </p>

        </div>


        <!-- ============================================
             PROTECCIÓN
        ============================================= -->

        <div class="protected">

            <div class="protected-icon">
                🛡️
            </div>

            <div>

                <h3>
                    Proceso protegido
                </h3>

                <p>
                    El sistema verificará el documento
                    directamente con los estudiantes
                    registrados.
                </p>

            </div>

        </div>


        <!-- ============================================
             MENSAJE DE VOTACIÓN REGISTRADA
        ============================================= -->

        <?php if ($mensaje !== ""): ?>

            <div class="success">

                ✓

                <?php

                echo htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- ============================================
             ERROR
        ============================================= -->

        <?php if ($error !== ""): ?>

            <div class="error">

                ⚠️

                <?php

                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- ============================================
             FORMULARIO
        ============================================= -->

        <form
            method="POST"
            action=""
            autocomplete="off"
        >


            <label for="documento">

                Número de documento

            </label>


            <input
                type="text"
                id="documento"
                name="documento"
                value="<?php

                    echo htmlspecialchars(
                        $documento,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                ?>"
                inputmode="numeric"
                maxlength="20"
                autocomplete="off"
                placeholder="Ingrese el documento"
                required
                autofocus
            >


            <div class="help">

                Escriba únicamente el número
                de documento del estudiante.

            </div>


            <button type="submit">

                🔎 Identificar estudiante

            </button>


        </form>


        <div class="footer-info">

            El documento será utilizado únicamente
            para verificar la habilitación del estudiante
            para esta elección.

        </div>


    </div>

</div>


</body>

</html>
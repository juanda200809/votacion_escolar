<?php

session_start();

require_once "config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit;

}


/* =========================================================
   VERIFICAR QUE SEA ADMINISTRADOR
========================================================= */

$rol = strtolower(
    trim(
        (string) $_SESSION['rol']
    )
);

if ($rol !== 'administrador') {

    http_response_code(403);

    die("
        <!DOCTYPE html>
        <html lang='es'>

        <head>
            <meta charset='UTF-8'>
            <title>Acceso denegado</title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #eef3f9;
                    font-family: Arial, sans-serif;
                }

                .mensaje {
                    width: 90%;
                    max-width: 550px;
                    background: white;
                    padding: 40px;
                    border-radius: 18px;
                    text-align: center;
                    box-shadow: 0 10px 35px rgba(0,0,0,.12);
                }

                .icono {
                    font-size: 55px;
                }

                h1 {
                    color: #dc3545;
                    margin-bottom: 15px;
                }

                p {
                    color: #555;
                    font-size: 17px;
                    line-height: 1.6;
                }

                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 22px;
                    background: #0d6efd;
                    color: white;
                    text-decoration: none;
                    border-radius: 9px;
                }

            </style>

        </head>

        <body>

            <div class='mensaje'>

                <div class='icono'>🔒</div>

                <h1>
                    Acceso denegado
                </h1>

                <p>
                    Solo el administrador puede
                    habilitar nuevamente una elección.
                </p>

                <a href='login.php'>
                    Volver al inicio
                </a>

            </div>

        </body>

        </html>
    ");

}


/* =========================================================
   OBTENER ID DE LA ELECCIÓN
========================================================= */

$idEleccion = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$idEleccion = $idEleccion
    ? (int) $idEleccion
    : 0;


if ($idEleccion <= 0) {

    die("
        <!DOCTYPE html>
        <html lang='es'>

        <head>
            <meta charset='UTF-8'>
            <title>Elección inválida</title>
        </head>

        <body>

            <h2>Elección inválida</h2>

            <p>
                No se recibió un identificador
                válido de elección.
            </p>

            <a href='admin.php'>
                Volver al panel de administración
            </a>

        </body>

        </html>
    ");

}


/* =========================================================
   BUSCAR LA ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
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


/* =========================================================
   VERIFICAR QUE EXISTA
========================================================= */

if (!$eleccion) {

    die("
        <!DOCTYPE html>
        <html lang='es'>

        <head>
            <meta charset='UTF-8'>
            <title>Elección no encontrada</title>
        </head>

        <body>

            <h2>Elección no encontrada</h2>

            <p>
                La elección indicada no existe.
            </p>

            <a href='admin.php'>
                Volver al panel de administración
            </a>

        </body>

        </html>
    ");

}


/* =========================================================
   DATOS ACTUALES
========================================================= */

$estadoActual = strtolower(
    trim(
        (string) $eleccion['estado']
    )
);


/* =========================================================
   VERIFICAR SI YA ESTÁ ABIERTA
========================================================= */

if ($estadoActual === 'abierta') {

    header(
        "Location: admin.php?eleccion_ya_abierta=1"
    );

    exit;

}


/* =========================================================
   HABILITAR ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    UPDATE elecciones
    SET estado = 'abierta'
    WHERE id = ?
");

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$filasAfectadas = $stmt->affected_rows;

$stmt->close();


/* =========================================================
   COMPROBAR RESULTADO
========================================================= */

if ($filasAfectadas > 0) {

    header(
        "Location: admin.php?eleccion_habilitada=1"
    );

    exit;

}


/*
 * Si no cambió ninguna fila,
 * comprobamos nuevamente el estado.
 */

$stmt = $conn->prepare("
    SELECT estado
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

$estadoFinal = $resultado->fetch_assoc();

$stmt->close();


if (
    $estadoFinal &&
    strtolower(
        trim(
            (string) $estadoFinal['estado']
        )
    ) === 'abierta'
) {

    header(
        "Location: admin.php?eleccion_habilitada=1"
    );

    exit;

}


/* =========================================================
   ERROR
========================================================= */

die("
    <!DOCTYPE html>

    <html lang='es'>

    <head>

        <meta charset='UTF-8'>

        <title>
            No se pudo habilitar
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #eef3f9;
                font-family: Arial, sans-serif;
            }

            .mensaje {
                width: 90%;
                max-width: 550px;
                background: white;
                padding: 40px;
                border-radius: 18px;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.12);
            }

            .icono {
                font-size: 55px;
            }

            h1 {
                color: #dc3545;
            }

            p {
                color: #555;
                font-size: 17px;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 22px;
                background: #0d6efd;
                color: white;
                text-decoration: none;
                border-radius: 9px;
            }

        </style>

    </head>

    <body>

        <div class='mensaje'>

            <div class='icono'>
                ⚠️
            </div>

            <h1>
                No se pudo habilitar
            </h1>

            <p>
                El sistema no pudo cambiar el estado
                de la elección.
            </p>

            <a href='admin.php'>
                Volver al administrador
            </a>

        </div>

    </body>

    </html>
");

?>
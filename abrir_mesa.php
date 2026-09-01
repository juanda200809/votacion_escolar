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


/*
 * IMPORTANTE:
 *
 * SOLAMENTE EL ADMINISTRADOR
 * puede abrir una mesa.
 *
 * Un jurado NO puede utilizar
 * este archivo.
 */

if ($rol !== 'administrador') {

    http_response_code(403);

    die("
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Acceso denegado</title>

            <style>

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
                    background: white;
                    padding: 40px;
                    border-radius: 18px;
                    text-align: center;
                    box-shadow: 0 8px 30px rgba(0,0,0,.12);
                    max-width: 500px;
                }

                h1 {
                    color: #dc3545;
                }

                p {
                    color: #555;
                    font-size: 17px;
                }

                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 20px;
                    background: #0d6efd;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                }

            </style>

        </head>

        <body>

            <div class='mensaje'>

                <h1>🔒 Acceso denegado</h1>

                <p>
                    Solo el administrador puede
                    habilitar una mesa de votación.
                </p>

                <a href='login.php'>
                    Volver
                </a>

            </div>

        </body>
        </html>
    ");

}


/* =========================================================
   OBTENER ID DE LA MESA
========================================================= */

$idMesa = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


$idMesa = $idMesa
    ? (int) $idMesa
    : 0;


if ($idMesa <= 0) {

    die("
        <h2>Error</h2>
        <p>La mesa indicada no es válida.</p>
        <a href='jurados.php'>
            Volver a administrar jurados
        </a>
    ");

}


/* =========================================================
   BUSCAR LA MESA
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        id_eleccion,
        id_jurado,
        nombre_mesa,
        estado,
        fecha_cierre
    FROM mesas_votacion
    WHERE id = ?
    LIMIT 1
");


$stmt->bind_param(
    "i",
    $idMesa
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$mesa =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   VERIFICAR QUE EXISTA
========================================================= */

if (!$mesa) {

    die("
        <h2>Mesa no encontrada</h2>

        <p>
            La mesa de votación indicada
            no existe.
        </p>

        <a href='jurados.php'>
            Volver
        </a>
    ");

}


/* =========================================================
   DATOS DE LA MESA
========================================================= */

$idEleccion =
    (int) $mesa['id_eleccion'];


$idJurado =
    (int) $mesa['id_jurado'];


$estadoActual =
    strtolower(
        trim(
            (string) $mesa['estado']
        )
    );


/* =========================================================
   VERIFICAR ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        estado,
        fecha_inicio,
        fecha_fin
    FROM elecciones
    WHERE id = ?
    LIMIT 1
");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


if (!$eleccion) {

    die("
        <h2>Error</h2>

        <p>
            La elección asociada a esta mesa
            no existe.
        </p>

        <a href='jurados.php'>
            Volver
        </a>
    ");

}


/* =========================================================
   VERIFICAR ESTADO DE LA ELECCIÓN
========================================================= */

$estadoEleccion =
    strtolower(
        trim(
            (string)
            $eleccion['estado']
        )
    );


/*
 * IMPORTANTE:
 *
 * No vamos a modificar
 * elecciones.estado.
 *
 * Solo vamos a modificar
 * mesas_votacion.estado.
 *
 */


/* =========================================================
   ABRIR MESA
========================================================= */

if (
    $estadoActual === 'cerrada'
) {


    $stmtAbrir =
        $conn->prepare("
            UPDATE mesas_votacion
            SET
                estado = 'abierta',
                fecha_cierre = NULL
            WHERE id = ?
              AND id_eleccion = ?
              AND id_jurado = ?
        ");


    if (!$stmtAbrir) {

        die(
            "No se pudo preparar la apertura de la mesa: "
            .
            $conn->error
        );

    }


    $stmtAbrir->bind_param(
        "iii",
        $idMesa,
        $idEleccion,
        $idJurado
    );


    $stmtAbrir->execute();


    $filasAfectadas =
        $stmtAbrir->affected_rows;


    $stmtAbrir->close();


    if (
        $filasAfectadas > 0
    ) {

        /*
         * Mesa abierta correctamente.
         *
         * Redirigimos al panel
         * de administración de jurados.
         */

        header(
            "Location: jurados.php?mesa_abierta=1"
        );

        exit;

    }


    /*
     * Si no hubo cambios,
     * volvemos igualmente.
     */

    header(
        "Location: jurados.php?mesa_abierta=0"
    );

    exit;

}


/* =========================================================
   SI YA ESTABA ABIERTA
========================================================= */

if (
    $estadoActual === 'abierta'
) {

    header(
        "Location: jurados.php?mesa_ya_abierta=1"
    );

    exit;

}


/* =========================================================
   ESTADO DESCONOCIDO
========================================================= */

die("
    <!DOCTYPE html>

    <html lang='es'>

    <head>

        <meta charset='UTF-8'>

        <title>
            Estado inválido
        </title>

        <style>

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
                background: white;
                padding: 40px;
                border-radius: 18px;
                box-shadow: 0 8px 30px rgba(0,0,0,.12);
                text-align: center;
                max-width: 550px;
            }

            h1 {
                color: #dc3545;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #0d6efd;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }

        </style>

    </head>

    <body>

        <div class='mensaje'>

            <h1>
                ⚠️ Estado de mesa inválido
            </h1>

            <p>
                El estado actual de la mesa no es
                reconocido por el sistema.
            </p>

            <a href='jurados.php'>
                Volver a jurados
            </a>

        </div>

    </body>

    </html>
");

?>
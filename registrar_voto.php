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


/* =========================================================
   VERIFICAR ESTUDIANTE
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {
    header("Location: ingresar_estudiante.php");
    exit();
}

$idUsuario = (int)$_SESSION['estudiante_votando_id'];


/* =========================================================
   RECIBIR CANDIDATOS
========================================================= */

if (
    !isset($_POST['candidato']) ||
    !is_array($_POST['candidato'])
) {
    die("No se recibieron los candidatos seleccionados.");
}

$selecciones = $_POST['candidato'];


/* =========================================================
   OBTENER ELECCIÓN ABIERTA
========================================================= */

$sql = "
    SELECT id, nombre
    FROM elecciones
    WHERE estado = 'abierta'
    ORDER BY id DESC
    LIMIT 1
";

$resultado = $conn->query($sql);

if (!$resultado) {
    die("Error al consultar la elección: " . $conn->error);
}

if ($resultado->num_rows == 0) {
    die("No existe una elección abierta.");
}

$eleccion = $resultado->fetch_assoc();

$idEleccion = (int)$eleccion['id'];


/* =========================================================
   COMPROBAR SI EL ESTUDIANTE YA VOTÓ
========================================================= */

$stmt = $conn->prepare("
    SELECT id
    FROM votos
    WHERE id_usuario = ?
    AND id_eleccion = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $idUsuario,
    $idEleccion
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {

    $stmt->close();

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso']
    );

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:80px;
        '>

            <h2 style='color:#dc3545;'>
                Este estudiante ya votó
            </h2>

            <p>
                El estudiante ya tiene una votación
                registrada en esta elección.
            </p>

            <a href='ingresar_estudiante.php'>
                Volver
            </a>

        </div>
    ");
}

$stmt->close();


/* =========================================================
   OBTENER CARGOS DE LA ELECCIÓN
========================================================= */

$cargos = [];

$stmt = $conn->prepare("
    SELECT
        c.id,
        c.nombre_cargo

    FROM cargos c

    INNER JOIN eleccion_cargos ec
        ON ec.id_cargo = c.id

    WHERE ec.id_eleccion = ?

    ORDER BY c.id ASC
");

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {

    $idCargo = (int)$fila['id'];

    $cargos[$idCargo] = $fila['nombre_cargo'];
}

$stmt->close();


/* =========================================================
   VERIFICAR QUE CADA CARGO TENGA SELECCIÓN
========================================================= */

foreach ($cargos as $idCargo => $nombreCargo) {

    /*
     * Convertimos explícitamente el ID a entero.
     */

    $idCargo = (int)$idCargo;


    /*
     * array_key_exists es más seguro para comprobar
     * que realmente llegó la posición.
     */

    if (!array_key_exists($idCargo, $selecciones)) {

        die("
            <div style='
                font-family:Arial;
                text-align:center;
                margin-top:80px;
            '>

                <h2 style='color:#dc3545;'>
                    Falta seleccionar un candidato
                </h2>

                <p>
                    Debe seleccionar un candidato
                    para el cargo:
                    <strong>
                        " . htmlspecialchars($nombreCargo) . "
                    </strong>
                </p>

                <a href='votar_por_jurado.php'>
                    Volver a la votación
                </a>

            </div>
        ");
    }
}


/* =========================================================
   INICIAR TRANSACCIÓN
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       PREPARAR CONSULTA DE CANDIDATO
    ===================================================== */

    $stmtCandidato = $conn->prepare("
        SELECT id
        FROM candidatos
        WHERE id = ?
        AND id_eleccion = ?
        AND id_cargo = ?
        LIMIT 1
    ");


    if (!$stmtCandidato) {
        throw new Exception(
            "Error preparando candidato: "
            . $conn->error
        );
    }


    /* =====================================================
       PREPARAR INSERT
    ===================================================== */

    $stmtVoto = $conn->prepare("
        INSERT INTO votos
        (
            id_usuario,
            id_candidato,
            id_eleccion,
            fecha_voto,
            id_cargo
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NOW(),
            ?
        )
    ");


    if (!$stmtVoto) {
        throw new Exception(
            "Error preparando voto: "
            . $conn->error
        );
    }


    /* =====================================================
       REGISTRAR CADA VOTO
    ===================================================== */

    foreach ($cargos as $idCargo => $nombreCargo) {

        $idCargo = (int)$idCargo;

        $idCandidato =
            (int)$selecciones[$idCargo];


        /* ================================================
           VALIDAR CANDIDATO
        ================================================ */

        $stmtCandidato->bind_param(
            "iii",
            $idCandidato,
            $idEleccion,
            $idCargo
        );

        $stmtCandidato->execute();

        $resultadoCandidato =
            $stmtCandidato->get_result();


        if ($resultadoCandidato->num_rows == 0) {

            throw new Exception(
                "El candidato seleccionado para "
                . $nombreCargo
                . " no pertenece a esta elección."
            );
        }


        /* ================================================
           INSERTAR VOTO
        ================================================ */

        $stmtVoto->bind_param(
            "iiii",
            $idUsuario,
            $idCandidato,
            $idEleccion,
            $idCargo
        );


        if (!$stmtVoto->execute()) {

            /*
             * Si la base de datos detecta
             * un voto duplicado.
             */

            if ($stmtVoto->errno == 1062) {

                throw new Exception(
                    "Este estudiante ya tiene un voto registrado."
                );
            }


            throw new Exception(
                "Error registrando el voto: "
                . $stmtVoto->error
            );
        }

    }


    /* =====================================================
       CERRAR CONSULTAS
    ===================================================== */

    $stmtCandidato->close();

    $stmtVoto->close();


    /* =====================================================
       CONFIRMAR
    ===================================================== */

    $conn->commit();


    /* =====================================================
       ELIMINAR ESTUDIANTE DE LA SESIÓN
    ===================================================== */

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );


    /* =====================================================
       MENSAJE
    ===================================================== */

    $_SESSION['mensaje_jurado'] =
        "La votación fue registrada correctamente.";


    /* =====================================================
       VOLVER A BUSCAR ESTUDIANTE
    ===================================================== */

    header(
        "Location: ingresar_estudiante.php?voto=ok"
    );

    exit();


} catch (Exception $e) {


    /* =====================================================
       CANCELAR SI OCURRIÓ UN ERROR
    ===================================================== */

    $conn->rollback();


    echo "
    <!DOCTYPE html>

    <html lang='es'>

    <head>

        <meta charset='UTF-8'>

        <title>Error de votación</title>

        <style>

            body {
                font-family: Arial;
                background: #eef3f9;
                text-align: center;
                padding-top: 80px;
            }

            .caja {
                background: white;
                max-width: 600px;
                margin: auto;
                padding: 35px;
                border-radius: 15px;
                box-shadow: 0 5px 20px
                    rgba(0,0,0,.1);
            }

            h2 {
                color: #dc3545;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #1976e8;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }

        </style>

    </head>

    <body>

        <div class='caja'>

            <h2>
                No se pudo registrar la votación
            </h2>

            <p>
                "
                . htmlspecialchars(
                    $e->getMessage()
                )
                . "
            </p>

            <a href='votar_por_jurado.php'>
                Volver a la votación
            </a>

        </div>

    </body>

    </html>
    ";

    exit();
}

?>
<?php
session_start();

include("config/conexion.php");

/* =====================================================
   1. VERIFICAR SESIÓN
===================================================== */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'estudiante'
) {
    header("Location: login.php");
    exit();
}

$idUsuario = (int) $_SESSION['id'];


/* =====================================================
   2. OBTENER LA ELECCIÓN
===================================================== */

/*
   Primero intentamos obtener la elección enviada
   por el formulario.
*/

$idEleccion = 0;

if (isset($_POST['id_eleccion'])) {
    $idEleccion = (int) $_POST['id_eleccion'];
}


/*
   Si el formulario no envía id_eleccion,
   buscamos la elección actualmente abierta.
*/

if ($idEleccion <= 0) {

    $sqlEleccion = "
        SELECT id
        FROM elecciones
        WHERE estado = 'abierta'
        ORDER BY id DESC
        LIMIT 1
    ";

    $resultadoEleccion = $conn->query($sqlEleccion);

    if (
        !$resultadoEleccion ||
        $resultadoEleccion->num_rows === 0
    ) {
        die("No hay ninguna elección abierta.");
    }

    $eleccion = $resultadoEleccion->fetch_assoc();

    $idEleccion = (int) $eleccion['id'];
}


/* =====================================================
   3. VERIFICAR QUE LA ELECCIÓN EXISTA Y ESTÉ ABIERTA
===================================================== */

$stmt = $conn->prepare("
    SELECT id, nombre, estado
    FROM elecciones
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $idEleccion);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("La elección no existe.");
}

$eleccion = $resultado->fetch_assoc();

$stmt->close();


if ($eleccion['estado'] !== 'abierta') {
    die("La elección está cerrada.");
}


/* =====================================================
   4. OBTENER LOS CANDIDATOS SELECCIONADOS
===================================================== */

$selecciones = [];


/*
   CASO 1:
   El formulario envía:

   candidato[1] = 8
   candidato[2] = 10
*/

if (
    isset($_POST['candidato']) &&
    is_array($_POST['candidato'])
) {

    foreach ($_POST['candidato'] as $idCargo => $idCandidato) {

        $idCargo = (int) $idCargo;
        $idCandidato = (int) $idCandidato;

        if ($idCargo > 0 && $idCandidato > 0) {

            $selecciones[$idCargo] = $idCandidato;
        }
    }
}


/*
   CASO 2:
   Si el formulario envía un candidato individual.
*/

elseif (isset($_POST['id_candidato'])) {

    $idCandidato = (int) $_POST['id_candidato'];

    if ($idCandidato > 0) {

        /*
           Buscamos automáticamente el cargo
           al que pertenece el candidato.
        */

        $stmt = $conn->prepare("
            SELECT id_cargo
            FROM candidatos
            WHERE id = ?
            AND id_eleccion = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $idCandidato,
            $idEleccion
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            die("El candidato no pertenece a esta elección.");
        }

        $datos = $resultado->fetch_assoc();

        $idCargo = (int) $datos['id_cargo'];

        $selecciones[$idCargo] = $idCandidato;

        $stmt->close();
    }
}


/* =====================================================
   5. VERIFICAR QUE SE HAYA SELECCIONADO ALGO
===================================================== */

if (empty($selecciones)) {

    die("
        <div style='
            font-family:Arial;
            max-width:600px;
            margin:60px auto;
            padding:30px;
            text-align:center;
            border-radius:15px;
            background:#ffffff;
            box-shadow:0 5px 25px rgba(0,0,0,.12);
        '>

            <h2 style='color:#dc3545;'>
                No se seleccionó ningún candidato
            </h2>

            <p>
                Debes seleccionar un candidato antes de registrar el voto.
            </p>

            <a href='votar.php'
               style='
                    display:inline-block;
                    padding:12px 22px;
                    background:#1976d2;
                    color:white;
                    text-decoration:none;
                    border-radius:8px;
               '>
                Volver a votar
            </a>

        </div>
    ");

}


/* =====================================================
   6. INICIAR TRANSACCIÓN
===================================================== */

$conn->begin_transaction();

try {

    /* =================================================
       7. PROCESAR CADA CARGO
    ================================================= */

    foreach ($selecciones as $idCargo => $idCandidato) {

        $idCargo = (int) $idCargo;
        $idCandidato = (int) $idCandidato;


        /* =============================================
           VERIFICAR QUE EL CARGO PERTENEZCA
           A LA ELECCIÓN
        ============================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM eleccion_cargos
            WHERE id_eleccion = ?
            AND id_cargo = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );

        $stmt->execute();

        $resultadoCargo = $stmt->get_result();

        if ($resultadoCargo->num_rows === 0) {

            throw new Exception(
                "El cargo seleccionado no pertenece a esta elección."
            );
        }

        $stmt->close();


        /* =============================================
           VERIFICAR CANDIDATO
        ============================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                nombre,
                apellido,
                id_cargo,
                id_eleccion
            FROM candidatos
            WHERE id = ?
            AND id_eleccion = ?
            AND id_cargo = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "iii",
            $idCandidato,
            $idEleccion,
            $idCargo
        );

        $stmt->execute();

        $resultadoCandidato = $stmt->get_result();

        if ($resultadoCandidato->num_rows === 0) {

            throw new Exception(
                "El candidato seleccionado no pertenece al cargo correspondiente."
            );
        }

        $candidato = $resultadoCandidato->fetch_assoc();

        $stmt->close();


        /* =============================================
           VERIFICAR SI YA VOTÓ
        ============================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM votos
            WHERE id_usuario = ?
            AND id_eleccion = ?
            AND id_cargo = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "iii",
            $idUsuario,
            $idEleccion,
            $idCargo
        );

        $stmt->execute();

        $resultadoVoto = $stmt->get_result();

        if ($resultadoVoto->num_rows > 0) {

            throw new Exception(
                "Ya registraste tu voto para el cargo: " .
                $idCargo
            );
        }

        $stmt->close();


        /* =============================================
           REGISTRAR VOTO
        ============================================= */

        $stmt = $conn->prepare("
            INSERT INTO votos (
                id_usuario,
                id_candidato,
                id_eleccion,
                fecha_voto,
                id_cargo
            )
            VALUES (?, ?, ?, NOW(), ?)
        ");

        $stmt->bind_param(
            "iiii",
            $idUsuario,
            $idCandidato,
            $idEleccion,
            $idCargo
        );


        /* =============================================
           EJECUTAR INSERT
        ============================================= */

        if (!$stmt->execute()) {

            /*
               Código MySQL 1062:
               Entrada duplicada por la clave UNIQUE.
            */

            if ($stmt->errno == 1062) {

                throw new Exception(
                    "Ya registraste tu voto para este cargo."
                );
            }

            throw new Exception(
                "No fue posible registrar el voto."
            );
        }

        $stmt->close();
    }


    /* =================================================
       8. CONFIRMAR TODOS LOS VOTOS
    ================================================= */

    $conn->commit();


    /* =================================================
       9. MENSAJE DE ÉXITO
    ================================================= */

    $_SESSION['mensaje'] =
        "Tu voto fue registrado correctamente.";

    $_SESSION['tipo_mensaje'] = "success";


    /* =================================================
       10. REDIRECCIONAR
    ================================================= */

    header("Location: votar.php?ok=1");
    exit();


}


/* =====================================================
   11. SI OCURRE ALGÚN ERROR
===================================================== */

catch (Exception $e) {

    /*
       Si algo falla, deshacemos cualquier voto
       que se haya intentado registrar durante
       esta operación.
    */

    $conn->rollback();


    $_SESSION['mensaje'] = $e->getMessage();

    $_SESSION['tipo_mensaje'] = "error";


    /*
       Regresamos a la página de votación.
    */

    header("Location: votar.php?error=1");
    exit();
}

?>
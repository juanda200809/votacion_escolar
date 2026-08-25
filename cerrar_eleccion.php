<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR ID
========================================================= */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header(
        "Location: elecciones.php?error=eleccion_invalida"
    );

    exit();

}


$idEleccion =
    (int)$_GET['id'];


if ($idEleccion <= 0) {

    header(
        "Location: elecciones.php?error=eleccion_invalida"
    );

    exit();

}


/* =========================================================
   INICIAR TRANSACCIÓN
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       1. BUSCAR ELECCIÓN
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            id,
            nombre,
            estado

        FROM elecciones

        WHERE id = ?

        LIMIT 1

        FOR UPDATE

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo consultar la elección."
        );

    }


    $stmt->bind_param(
        "i",
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows === 0
    ) {

        $stmt->close();

        throw new Exception(
            "La elección no existe."
        );

    }


    $eleccion =
        $resultado->fetch_assoc();


    $stmt->close();


    /* =====================================================
       2. VERIFICAR ESTADO
    ===================================================== */

    $estado =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estado === 'cerrada'
    ) {

        $conn->rollback();


        header(
            "Location: elecciones.php?cerrada=1"
        );

        exit();

    }


    /* =====================================================
       3. CERRAR ELECCIÓN
    ===================================================== */

    $stmt = $conn->prepare("

        UPDATE elecciones

        SET estado = 'cerrada'

        WHERE id = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo preparar el cierre de la elección."
        );

    }


    $stmt->bind_param(
        "i",
        $idEleccion
    );


    if (
        !$stmt->execute()
    ) {

        $stmt->close();

        throw new Exception(
            "No se pudo cerrar la elección."
        );

    }


    $stmt->close();


    /* =====================================================
       4. CONFIRMAR
    ===================================================== */

    $conn->commit();


    /* =====================================================
       5. VOLVER
    ===================================================== */

    header(
        "Location: elecciones.php?cerrada=1"
    );

    exit();


} catch (Throwable $e) {


    /* =====================================================
       CANCELAR
    ===================================================== */

    $conn->rollback();


    error_log(
        "Error al cerrar elección: "
        .
        $e->getMessage()
    );


    header(
        "Location: elecciones.php?error=cerrar"
    );

    exit();

}

?>
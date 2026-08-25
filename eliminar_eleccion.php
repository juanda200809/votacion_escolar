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
       2. NO ELIMINAR ELECCIÓN ABIERTA
    ===================================================== */

    $estado =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estado === 'abierta'
    ) {

        throw new Exception(
            "No se puede eliminar una elección abierta."
        );

    }


    /* =====================================================
       3. VERIFICAR VOTOS
       
       IMPORTANTE:
       Se utiliza directamente id_eleccion.
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos

        WHERE id_eleccion = ?

        FOR UPDATE

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudieron comprobar los votos."
        );

    }


    $stmt->bind_param(
        "i",
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $fila =
        $resultado->fetch_assoc();


    $totalVotos =
        (int)$fila['total'];


    $stmt->close();


    /* =====================================================
       4. NO ELIMINAR SI TIENE VOTOS
    ===================================================== */

    if (
        $totalVotos > 0
    ) {

        throw new Exception(
            "No se puede eliminar la elección porque tiene votos registrados."
        );

    }


    /* =====================================================
       5. ELIMINAR RELACIÓN CON CARGOS
    ===================================================== */

    $stmt = $conn->prepare("

        DELETE FROM eleccion_cargos

        WHERE id_eleccion = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudieron eliminar los cargos relacionados."
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
            "No se pudieron eliminar las relaciones de cargos."
        );

    }


    $stmt->close();


    /* =====================================================
       6. ELIMINAR CANDIDATOS
    ===================================================== */

    $stmt = $conn->prepare("

        DELETE FROM candidatos

        WHERE id_eleccion = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo preparar la eliminación de candidatos."
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
            "No se pudieron eliminar los candidatos."
        );

    }


    $stmt->close();


    /* =====================================================
       7. ELIMINAR ELECCIÓN
    ===================================================== */

    $stmt = $conn->prepare("

        DELETE FROM elecciones

        WHERE id = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo preparar la eliminación de la elección."
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
            "No se pudo eliminar la elección."
        );

    }


    $stmt->close();


    /* =====================================================
       8. CONFIRMAR
    ===================================================== */

    $conn->commit();


    /* =====================================================
       9. VOLVER
    ===================================================== */

    header(
        "Location: elecciones.php?eliminada=1"
    );

    exit();


} catch (Throwable $e) {


    /* =====================================================
       DESHACER CAMBIOS
    ===================================================== */

    $conn->rollback();


    error_log(
        "Error al eliminar elección: "
        .
        $e->getMessage()
    );


    /* =====================================================
       MENSAJE DE ERROR
    ===================================================== */

    $mensaje =
        $e->getMessage();


    if (
        str_contains(
            $mensaje,
            "tiene votos"
        )
    ) {

        header(
            "Location: elecciones.php?error=tiene_votos"
        );

        exit();

    }


    if (
        str_contains(
            $mensaje,
            "elección abierta"
        )
    ) {

        header(
            "Location: elecciones.php?error=abierta"
        );

        exit();

    }


    header(
        "Location: elecciones.php?error=eliminar"
    );

    exit();

}

?>
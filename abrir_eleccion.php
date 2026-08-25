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
            descripcion,
            fecha_inicio,
            fecha_fin,
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

    $estadoActual =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estadoActual === 'abierta'
    ) {

        $conn->rollback();


        header(
            "Location: elecciones.php?abierta=1"
        );

        exit();

    }


    /* =====================================================
       3. COMPROBAR SI YA EXISTE OTRA ELECCIÓN ABIERTA
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            id,
            nombre

        FROM elecciones

        WHERE estado = 'abierta'

        AND id <> ?

        LIMIT 1

        FOR UPDATE

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo comprobar el estado de las demás elecciones."
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
        $resultado->num_rows > 0
    ) {

        $otraEleccion =
            $resultado->fetch_assoc();


        $stmt->close();


        throw new Exception(
            "No se puede abrir esta elección porque ya existe otra elección abierta: "
            .
            $otraEleccion['nombre']
        );

    }


    $stmt->close();


    /* =====================================================
       4. VERIFICAR CARGOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM eleccion_cargos

        WHERE id_eleccion = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudieron comprobar los cargos."
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


    $totalCargos =
        (int)$fila['total'];


    $stmt->close();


    if (
        $totalCargos <= 0
    ) {

        throw new Exception(
            "No se puede abrir la elección porque no tiene cargos configurados."
        );

    }


    /* =====================================================
       5. VERIFICAR CANDIDATOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM candidatos

        WHERE id_eleccion = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudieron comprobar los candidatos."
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


    $totalCandidatos =
        (int)$fila['total'];


    $stmt->close();


    if (
        $totalCandidatos <= 0
    ) {

        throw new Exception(
            "No se puede abrir la elección porque no tiene candidatos registrados."
        );

    }


    /* =====================================================
       6. VERIFICAR QUE CADA CARGO TENGA CANDIDATOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT

            c.id,
            c.nombre_cargo

        FROM cargos c

        INNER JOIN eleccion_cargos ec

            ON ec.id_cargo = c.id

        LEFT JOIN candidatos ca

            ON ca.id_cargo = c.id

            AND ca.id_eleccion = ?

        WHERE ec.id_eleccion = ?

        GROUP BY
            c.id,
            c.nombre_cargo

        HAVING COUNT(ca.id) = 0

        LIMIT 1

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo comprobar la configuración de candidatos."
        );

    }


    $stmt->bind_param(
        "ii",
        $idEleccion,
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows > 0
    ) {

        $cargoSinCandidatos =
            $resultado->fetch_assoc();


        $stmt->close();


        throw new Exception(
            "El cargo "
            .
            $cargoSinCandidatos['nombre_cargo']
            .
            " no tiene candidatos registrados."
        );

    }


    $stmt->close();


    /* =====================================================
       7. ABRIR ELECCIÓN
    ===================================================== */

    $stmt = $conn->prepare("

        UPDATE elecciones

        SET estado = 'abierta'

        WHERE id = ?

    ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo preparar la apertura de la elección."
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
            "No se pudo abrir la elección."
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
        "Location: elecciones.php?abierta=1"
    );

    exit();


} catch (Throwable $e) {


    /* =====================================================
       CANCELAR
    ===================================================== */

    $conn->rollback();


    error_log(
        "Error al abrir elección: "
        .
        $e->getMessage()
    );


    /* =====================================================
       MENSAJE
    ===================================================== */

    $mensaje =
        urlencode(
            $e->getMessage()
        );


    header(
        "Location: elecciones.php?error="
        .
        $mensaje
    );

    exit();

}

?>
<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'administrador'
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   VERIFICAR ID
========================================================= */

if (
    !isset($_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    header("Location: elecciones.php");
    exit();

}


$id =
    (int)$_GET['id'];


/* =========================================================
   BUSCAR ELECCIÓN
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            id,
            nombre,
            estado

        FROM elecciones

        WHERE id = ?

        LIMIT 1

    ");


if (!$stmt) {

    header(
        "Location: elecciones.php?error=eliminar"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header(
        "Location: elecciones.php?error=no_encontrada"
    );

    exit();

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   NO PERMITIR ELIMINAR ELECCIÓN ABIERTA
========================================================= */

$estado =
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    );


if (
    $estado === "abierta"
) {

    header(
        "Location: elecciones.php?error=abierta"
    );

    exit();

}


/* =========================================================
   COMPROBAR SI TIENE VOTOS
========================================================= */

$stmtVotos =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos v

        INNER JOIN candidatos c
            ON c.id = v.id_candidato

        WHERE c.id_eleccion = ?

    ");


if (!$stmtVotos) {

    header(
        "Location: elecciones.php?error=eliminar"
    );

    exit();

}


$stmtVotos->bind_param(
    "i",
    $id
);


$stmtVotos->execute();


$resultadoVotos =
    $stmtVotos->get_result();


$datosVotos =
    $resultadoVotos->fetch_assoc();


$totalVotos =
    (int)$datosVotos['total'];


$stmtVotos->close();


/* =========================================================
   NO ELIMINAR SI TIENE VOTOS
========================================================= */

if (
    $totalVotos > 0
) {

    header(
        "Location: elecciones.php?error=tiene_votos"
    );

    exit();

}


/* =========================================================
   INICIAR TRANSACCIÓN
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       ELIMINAR RELACIONES ELECCIÓN - CARGOS
    ===================================================== */

    $stmtRelacion =
        $conn->prepare("

            DELETE FROM eleccion_cargos

            WHERE id_eleccion = ?

        ");


    if (!$stmtRelacion) {

        throw new Exception(
            "No se pudieron eliminar los cargos relacionados."
        );

    }


    $stmtRelacion->bind_param(
        "i",
        $id
    );


    if (
        !$stmtRelacion->execute()
    ) {

        throw new Exception(
            "No se pudieron eliminar las relaciones de cargos."
        );

    }


    $stmtRelacion->close();


    /* =====================================================
       ELIMINAR CANDIDATOS DE LA ELECCIÓN
    ===================================================== */

    $stmtCandidatos =
        $conn->prepare("

            DELETE FROM candidatos

            WHERE id_eleccion = ?

        ");


    if (!$stmtCandidatos) {

        throw new Exception(
            "No se pudieron eliminar los candidatos."
        );

    }


    $stmtCandidatos->bind_param(
        "i",
        $id
    );


    if (
        !$stmtCandidatos->execute()
    ) {

        throw new Exception(
            "No se pudieron eliminar los candidatos."
        );

    }


    $stmtCandidatos->close();


    /* =====================================================
       ELIMINAR ELECCIÓN
    ===================================================== */

    $stmtEleccion =
        $conn->prepare("

            DELETE FROM elecciones

            WHERE id = ?

        ");


    if (!$stmtEleccion) {

        throw new Exception(
            "No se pudo eliminar la elección."
        );

    }


    $stmtEleccion->bind_param(
        "i",
        $id
    );


    if (
        !$stmtEleccion->execute()
    ) {

        throw new Exception(
            "No se pudo eliminar la elección."
        );

    }


    $stmtEleccion->close();


    /* =====================================================
       CONFIRMAR
    ===================================================== */

    $conn->commit();


    header(
        "Location: elecciones.php?eliminada=1"
    );

    exit();


} catch (
    Exception $e
) {


    $conn->rollback();


    header(
        "Location: elecciones.php?error=eliminar"
    );

    exit();

}

?>
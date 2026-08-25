<?php

require_once "seguridad.php";

evitarCache();
verificarRol(['jurado']);

require_once "config/conexion.php";


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

$idEstudiante = (int)$_SESSION['estudiante_votando_id'];


/* =========================================================
   VERIFICAR ELECCIÓN
========================================================= */

if (
    !isset($_SESSION['eleccion_votante_id']) ||
    (int)$_SESSION['eleccion_votante_id'] <= 0
) {
    header("Location: ingresar_estudiante.php");
    exit();
}

$idEleccion = (int)$_SESSION['eleccion_votante_id'];


/* =========================================================
   SOLO POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: votar_por_jurado.php");
    exit();
}


/* =========================================================
   RECIBIR CANDIDATOS
========================================================= */

$selecciones = $_POST['candidato'] ?? [];

if (!is_array($selecciones) || count($selecciones) === 0) {

    header(
        "Location: votar_por_jurado.php?error=sin_seleccion"
    );

    exit();
}


/* =========================================================
   LIMPIAR SELECCIONES
========================================================= */

$seleccionesLimpias = [];

foreach ($selecciones as $idCargo => $idCandidato) {

    $idCargo = (int)$idCargo;
    $idCandidato = (int)$idCandidato;

    if (
        $idCargo > 0 &&
        $idCandidato > 0
    ) {

        $seleccionesLimpias[$idCargo] = $idCandidato;

    }
}


if (count($seleccionesLimpias) === 0) {

    header(
        "Location: votar_por_jurado.php?error=sin_seleccion"
    );

    exit();
}


/* =========================================================
   INICIAR TRANSACCIÓN
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       1. VERIFICAR ESTUDIANTE
    ===================================================== */

    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE id = ?
        AND LOWER(TRIM(rol)) = 'estudiante'
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception(
            "No se pudo verificar el estudiante."
        );
    }

    $stmt->bind_param(
        "i",
        $idEstudiante
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {

        $stmt->close();

        throw new Exception(
            "El estudiante no existe."
        );
    }

    $stmt->close();


    /* =====================================================
       2. VERIFICAR ELECCIÓN
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
            "No se pudo verificar la elección."
        );
    }

    $stmt->bind_param(
        "i",
        $idEleccion
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {

        $stmt->close();

        throw new Exception(
            "La elección no existe."
        );
    }

    $eleccion = $resultado->fetch_assoc();

    $stmt->close();


    /* =====================================================
       3. VERIFICAR QUE ESTÉ ABIERTA
    ===================================================== */

    if (
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        ) !== 'abierta'
    ) {

        throw new Exception(
            "La elección está cerrada."
        );
    }


    /* =====================================================
       4. OBTENER CARGOS DE LA ELECCIÓN
    ===================================================== */

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

    if (!$stmt) {
        throw new Exception(
            "No se pudieron consultar los cargos."
        );
    }

    $stmt->bind_param(
        "i",
        $idEleccion
    );

    $stmt->execute();

    $resultadoCargos = $stmt->get_result();

    $cargos = [];

    while (
        $cargo = $resultadoCargos->fetch_assoc()
    ) {

        $cargos[
            (int)$cargo['id']
        ] = $cargo['nombre_cargo'];

    }

    $stmt->close();


    /* =====================================================
       5. VERIFICAR QUE TODOS LOS CARGOS TENGAN VOTO
    ===================================================== */

    if (count($cargos) === 0) {

        throw new Exception(
            "La elección no tiene cargos configurados."
        );
    }


    foreach (
        $cargos as $idCargo => $nombreCargo
    ) {

        if (
            !isset(
                $seleccionesLimpias[$idCargo]
            )
        ) {

            throw new Exception(
                "Debe seleccionar un candidato para el cargo: "
                . $nombreCargo
            );
        }
    }


    /* =====================================================
       6. VERIFICAR QUE NO SOBREN CARGOS
    ===================================================== */

    foreach (
        $seleccionesLimpias as $idCargo => $idCandidato
    ) {

        if (
            !isset($cargos[$idCargo])
        ) {

            throw new Exception(
                "Se recibió un cargo que no pertenece a esta elección."
            );
        }
    }


    /* =====================================================
       7. VERIFICAR SI YA EXISTEN VOTOS
    ===================================================== */

    $stmt = $conn->prepare("
        SELECT id
        FROM votos
        WHERE id_usuario = ?
        AND id_eleccion = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception(
            "No se pudo comprobar si el estudiante ya votó."
        );
    }

    $stmt->bind_param(
        "ii",
        $idEstudiante,
        $idEleccion
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $stmt->close();

        throw new Exception(
            "Este estudiante ya realizó su votación en esta elección."
        );
    }

    $stmt->close();


    /* =====================================================
       8. PREPARAR VALIDACIÓN DE CANDIDATOS
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
            "No se pudo preparar la validación del candidato."
        );
    }


    /* =====================================================
       9. PREPARAR INSERT
    ===================================================== */

    $stmtInsertar = $conn->prepare("
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

    if (!$stmtInsertar) {

        $stmtCandidato->close();

        throw new Exception(
            "No se pudo preparar el registro del voto."
        );
    }


    /* =====================================================
       10. REGISTRAR TODOS LOS VOTOS
    ===================================================== */

    foreach (
        $cargos as $idCargo => $nombreCargo
    ) {

        $idCandidato =
            $seleccionesLimpias[$idCargo];


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


        if (
            $resultadoCandidato->num_rows === 0
        ) {

            throw new Exception(
                "El candidato seleccionado para "
                . $nombreCargo
                . " no pertenece a esta elección."
            );
        }


        /* ================================================
           INSERTAR VOTO
        ================================================ */

        $stmtInsertar->bind_param(
            "iiii",
            $idEstudiante,
            $idCandidato,
            $idEleccion,
            $idCargo
        );


        if (
            !$stmtInsertar->execute()
        ) {

            throw new Exception(
                "No se pudo registrar el voto para "
                . $nombreCargo
                . "."
            );
        }

    }


    /* =====================================================
       11. CERRAR CONSULTAS
    ===================================================== */

    $stmtCandidato->close();

    $stmtInsertar->close();


    /* =====================================================
       12. CONFIRMAR TODO
    ===================================================== */

    $conn->commit();


    /* =====================================================
       13. LIMPIAR ESTUDIANTE
    ===================================================== */

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );


    /* =====================================================
       14. REDIRIGIR
    ===================================================== */

    header(
        "Location: ingresar_estudiante.php?ok=1"
    );

    exit();


} catch (Throwable $e) {


    /* =====================================================
       CANCELAR TODO
    ===================================================== */

    $conn->rollback();


    error_log(
        "Error en registrar_voto_jurado.php: "
        . $e->getMessage()
    );


    /* =====================================================
       VOLVER CON ERROR
    ===================================================== */

    $mensaje =
        urlencode(
            $e->getMessage()
        );


    header(
        "Location: votar_por_jurado.php?error="
        . $mensaje
    );

    exit();

}

?>
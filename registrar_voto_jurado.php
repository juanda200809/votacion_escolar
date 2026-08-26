<?php

/* =========================================================
   INICIAR SESIÓN
========================================================= */

session_start();


/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();

verificarRol(['jurado']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR MÉTODO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    header(
        "Location: votar_por_jurado.php"
    );

    exit();

}


/* =========================================================
   VERIFICAR CSRF
========================================================= */

if (
    !isset($_POST['csrf']) ||
    !isset($_SESSION['csrf_votacion']) ||
    !hash_equals(
        $_SESSION['csrf_votacion'],
        (string)$_POST['csrf']
    )
) {

    header(
        "Location: votar_por_jurado.php?error=seguridad"
    );

    exit();

}


/* =========================================================
   VERIFICAR ESTUDIANTE EN PROCESO
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$idEstudiante =
    (int)$_SESSION['estudiante_votando_id'];


/* =========================================================
   VERIFICAR ELECCIÓN EN SESIÓN
========================================================= */

if (
    !isset($_SESSION['eleccion_votante_id']) ||
    (int)$_SESSION['eleccion_votante_id'] <= 0
) {

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$idEleccion =
    (int)$_SESSION['eleccion_votante_id'];


/* =========================================================
   OBTENER SELECCIONES
========================================================= */

$selecciones =
    $_POST['candidato'] ?? [];


if (
    !is_array($selecciones) ||
    count($selecciones) === 0
) {

    header(
        "Location: votar_por_jurado.php?error=sin_seleccion"
    );

    exit();

}


/* =========================================================
   LIMPIAR SELECCIONES
========================================================= */

$seleccionesLimpias = [];


foreach (
    $selecciones as $idCargo => $idCandidato
) {

    $idCargo =
        (int)$idCargo;

    $idCandidato =
        (int)$idCandidato;


    if (
        $idCargo > 0 &&
        $idCandidato > 0
    ) {

        $seleccionesLimpias[$idCargo] =
            $idCandidato;

    }

}


if (
    count($seleccionesLimpias) === 0
) {

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

    $stmt =
        $conn->prepare("

            SELECT
                id

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


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows === 0
    ) {

        $stmt->close();

        throw new Exception(
            "El estudiante no existe."
        );

    }


    $stmt->close();


    /* =====================================================
       2. VERIFICAR ELECCIÓN
    ===================================================== */

    $stmt =
        $conn->prepare("

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
       3. ELECCIÓN DEBE ESTAR ABIERTA
    ===================================================== */

    $estadoEleccion =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estadoEleccion !== 'abierta'
    ) {

        throw new Exception(
            "La elección está cerrada."
        );

    }


    /* =====================================================
       4. OBTENER CARGOS VÁLIDOS
    ===================================================== */

    $stmt =
        $conn->prepare("

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


    $resultadoCargos =
        $stmt->get_result();


    $cargos = [];


    while (
        $cargo =
        $resultadoCargos->fetch_assoc()
    ) {

        $cargos[
            (int)$cargo['id']
        ] =
            $cargo['nombre_cargo'];

    }


    $stmt->close();


    /* =====================================================
       5. DEBE EXISTIR AL MENOS UN CARGO
    ===================================================== */

    if (
        count($cargos) === 0
    ) {

        throw new Exception(
            "La elección no tiene cargos configurados."
        );

    }


    /* =====================================================
       6. COMPROBAR TODOS LOS CARGOS
    ===================================================== */

    foreach (
        $cargos as $idCargo => $nombreCargo
    ) {

        if (
            !isset(
                $seleccionesLimpias[$idCargo]
            )
        ) {

            throw new Exception(
                "Debe seleccionar un candidato para todos los cargos."
            );

        }

    }


    /* =====================================================
       7. COMPROBAR CARGOS EXTRA
    ===================================================== */

    foreach (
        $seleccionesLimpias
        as $idCargo => $idCandidato
    ) {

        if (
            !isset(
                $cargos[$idCargo]
            )
        ) {

            throw new Exception(
                "La selección contiene un cargo inválido."
            );

        }

    }


    /* =====================================================
       8. COMPROBAR DOBLE VOTO
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT
                id

            FROM votos

            WHERE id_usuario = ?

            AND id_eleccion = ?

            LIMIT 1

            FOR UPDATE

        ");


    if (!$stmt) {

        throw new Exception(
            "No se pudo comprobar la votación."
        );

    }


    $stmt->bind_param(
        "ii",
        $idEstudiante,
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows > 0
    ) {

        $stmt->close();

        throw new Exception(
            "Este estudiante ya realizó su votación."
        );

    }


    $stmt->close();


    /* =====================================================
       9. PREPARAR VALIDACIÓN DE CANDIDATO
    ===================================================== */

    $stmtCandidato =
        $conn->prepare("

            SELECT
                id

            FROM candidatos

            WHERE id = ?

            AND id_eleccion = ?

            AND id_cargo = ?

            LIMIT 1

        ");


    if (!$stmtCandidato) {

        throw new Exception(
            "No se pudo validar el candidato."
        );

    }


    /* =====================================================
       10. PREPARAR INSERCIÓN
    ===================================================== */

    $stmtInsertar =
        $conn->prepare("

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
            "No se pudo preparar el registro."
        );

    }


    /* =====================================================
       11. REGISTRAR CADA VOTO
    ===================================================== */

    foreach (
        $cargos as $idCargo => $nombreCargo
    ) {

        $idCandidato =
            (int)$seleccionesLimpias[$idCargo];


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
                "El candidato seleccionado no pertenece a esta elección."
            );

        }


        /* ================================================
           INSERTAR
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
                "No se pudo registrar el voto."
            );

        }

    }


    /* =====================================================
       12. CERRAR CONSULTAS
    ===================================================== */

    $stmtCandidato->close();

    $stmtInsertar->close();


    /* =====================================================
       13. CONFIRMAR
    ===================================================== */

    $conn->commit();


    /* =====================================================
       14. LIMPIAR SESIÓN TEMPORAL
    ===================================================== */

    unset(

        $_SESSION['estudiante_votando_id'],

        $_SESSION['estudiante_votando_documento'],

        $_SESSION['estudiante_votando_nombre'],

        $_SESSION['estudiante_votando_curso'],

        $_SESSION['eleccion_votante_id'],

        $_SESSION['csrf_votacion']

    );


    /* =====================================================
       15. INDICAR VOTACIÓN EXITOSA
    ===================================================== */

    $_SESSION['votacion_completada'] = true;


    header(
        "Location: ingresar_estudiante.php?ok=1"
    );

    exit();


} catch (
    Throwable $e
) {


    /* =====================================================
       DESHACER
    ===================================================== */

    $conn->rollback();


    /* =====================================================
       REGISTRAR ERROR INTERNAMENTE
    ===================================================== */

    error_log(
        "Error en registrar_voto_jurado.php: "
        .
        $e->getMessage()
    );


    /* =====================================================
       MOSTRAR MENSAJE GENERAL
    ===================================================== */

    header(
        "Location: votar_por_jurado.php?error=registro"
    );

    exit();

}

?>
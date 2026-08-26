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

$conn->set_charset("utf8mb4");


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

$idEleccion = 0;


/*
 * Compatibilidad con la sesión actual.
 */

if (
    isset($_SESSION['eleccion_votante_id'])
) {

    $idEleccion =
        (int)$_SESSION['eleccion_votante_id'];

}


/*
 * Compatibilidad con la variable utilizada
 * por ingresar_estudiante.php / votar_por_jurado.php.
 */

if (
    $idEleccion <= 0 &&
    isset($_SESSION['id_eleccion_jurado'])
) {

    $idEleccion =
        (int)$_SESSION['id_eleccion_jurado'];

}


if ($idEleccion <= 0) {

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


/* =========================================================
   VERIFICAR JURADO
========================================================= */

$idJurado =
    (int)$_SESSION['id'];


if ($idJurado <= 0) {

    header(
        "Location: jurado.php"
    );

    exit();

}


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
       4. VERIFICAR MESA DEL JURADO
    ===================================================== */

    /*
     * ESTA ES LA PROTECCIÓN PRINCIPAL.
     *
     * Solo se consulta la mesa perteneciente al jurado
     * que está realizando la votación.
     *
     * No se modifica la tabla elecciones.
     */

    $stmtMesa =
        $conn->prepare("

            SELECT
                id,
                id_eleccion,
                id_jurado,
                nombre_mesa,
                estado,
                fecha_cierre

            FROM mesas_votacion

            WHERE id_eleccion = ?

            AND id_jurado = ?

            LIMIT 1

            FOR UPDATE

        ");


    if (!$stmtMesa) {

        throw new Exception(
            "No se pudo verificar la mesa de votación."
        );

    }


    $stmtMesa->bind_param(
        "ii",
        $idEleccion,
        $idJurado
    );


    $stmtMesa->execute();


    $resultadoMesa =
        $stmtMesa->get_result();


    if (
        $resultadoMesa->num_rows === 0
    ) {

        $stmtMesa->close();

        throw new Exception(
            "Este jurado no tiene una mesa de votación asignada para esta elección."
        );

    }


    $mesa =
        $resultadoMesa->fetch_assoc();


    $stmtMesa->close();


    /* =====================================================
       5. MESA DEBE ESTAR ABIERTA
    ===================================================== */

    $estadoMesa =
        strtolower(
            trim(
                (string)$mesa['estado']
            )
        );


    if (
        $estadoMesa !== 'abierta'
    ) {

        throw new Exception(
            "🔒 La mesa de votación está cerrada. " .
            "El voto no fue registrado."
        );

    }


    /* =====================================================
       6. OBTENER CARGOS VÁLIDOS
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
       7. DEBE EXISTIR AL MENOS UN CARGO
    ===================================================== */

    if (
        count($cargos) === 0
    ) {

        throw new Exception(
            "La elección no tiene cargos configurados."
        );

    }


    /* =====================================================
       8. COMPROBAR TODOS LOS CARGOS
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
       9. COMPROBAR CARGOS EXTRA
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
       10. COMPROBAR DOBLE VOTO
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
       11. PREPARAR VALIDACIÓN DE CANDIDATO
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
       12. PREPARAR INSERCIÓN
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
       13. REGISTRAR CADA VOTO
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
                "No se pudo registrar el voto."
            );

        }

    }


    /* =====================================================
       14. CERRAR CONSULTAS
    ===================================================== */

    $stmtCandidato->close();

    $stmtInsertar->close();


    /* =====================================================
       15. CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conn->commit();


    /* =====================================================
       16. LIMPIAR SESIÓN TEMPORAL
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
       17. INDICAR VOTACIÓN EXITOSA
    ===================================================== */

    $_SESSION['votacion_completada'] =
        true;


    $_SESSION['mensaje_votacion'] =
        "La votación fue registrada correctamente.";


    /* =====================================================
       18. VOLVER A INGRESAR ESTUDIANTE
    ===================================================== */

    header(
        "Location: ingresar_estudiante.php?ok=1"
    );

    exit();


} catch (
    Throwable $e
) {


    /* =====================================================
       DESHACER TODO
    ===================================================== */

    if (
        $conn->in_transaction
    ) {

        $conn->rollback();

    }


    /* =====================================================
       REGISTRAR ERROR INTERNAMENTE
    ===================================================== */

    error_log(
        "Error en registrar_voto_jurado.php: "
        .
        $e->getMessage()
    );


    /*
     * Si la causa fue la mesa cerrada,
     * mostramos un mensaje específico.
     */

    if (
        stripos(
            $e->getMessage(),
            'mesa'
        ) !== false
    ) {

        header(
            "Location: votar_por_jurado.php?error=mesa_cerrada"
        );

        exit();

    }


    /* =====================================================
       OTROS ERRORES
    ===================================================== */

    header(
        "Location: votar_por_jurado.php?error=registro"
    );

    exit();

}

?>
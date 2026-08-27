<?php

/* =========================================================
   CERRAR SOLAMENTE LA MESA DEL JURADO
========================================================= */

require_once "seguridad.php";

evitarCache();

verificarRol([
    'jurado'
]);

require_once "config/conexion.php";

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

$conn->set_charset("utf8mb4");


/* =========================================================
   DATOS DE SESIÓN
========================================================= */

$idJurado =
    (int)($_SESSION['id'] ?? 0);


if ($idJurado <= 0) {

    header("Location: login.php");

    exit();

}


/* =========================================================
   ID DE LA MESA
========================================================= */

$idMesa =
    isset($_POST['id_mesa'])
    ? (int)$_POST['id_mesa']
    : 0;


if ($idMesa <= 0) {

    header(
        "Location: resultados.php?error=mesa_invalida"
    );

    exit();

}


/* =========================================================
   BUSCAR LA MESA
   IMPORTANTE:
   SOLO SE BUSCA UNA MESA QUE PERTENEZCA
   AL JURADO QUE TIENE LA SESIÓN ABIERTA.
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            id,
            id_eleccion,
            id_jurado,
            nombre_mesa,
            estado

        FROM mesas_votacion

        WHERE id = ?

        AND id_jurado = ?

        LIMIT 1

    ");


$stmt->bind_param(
    "ii",
    $idMesa,
    $idJurado
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$mesa =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   SI LA MESA NO PERTENECE AL JURADO
========================================================= */

if (!$mesa) {

    header(
        "Location: resultados.php?error=mesa_no_autorizada"
    );

    exit();

}


/* =========================================================
   SI YA ESTÁ CERRADA
========================================================= */

if (
    strtolower(
        trim(
            (string)$mesa['estado']
        )
    ) === 'cerrada'
) {

    header(
        "Location: resultados.php?id_eleccion="
        .
        (int)$mesa['id_eleccion']
        .
        "&mesa=cerrada"
    );

    exit();

}


/* =========================================================
   CERRAR SOLAMENTE ESTA MESA
========================================================= */

$stmt =
    $conn->prepare("

        UPDATE mesas_votacion

        SET
            estado = 'cerrada',
            fecha_cierre = NOW()

        WHERE id = ?

        AND id_jurado = ?

        AND estado = 'abierta'

        LIMIT 1

    ");


$stmt->bind_param(
    "ii",
    $idMesa,
    $idJurado
);


$stmt->execute();


$filasAfectadas =
    $stmt->affected_rows;


$stmt->close();


/* =========================================================
   COMPROBAR ACTUALIZACIÓN
========================================================= */

if (
    $filasAfectadas > 0
) {

    header(
        "Location: resultados.php?id_eleccion="
        .
        (int)$mesa['id_eleccion']
        .
        "&mesa=cerrada"
    );

    exit();

}


/* =========================================================
   SI NO SE PUDO CERRAR
========================================================= */

header(
    "Location: resultados.php?id_eleccion="
    .
    (int)$mesa['id_eleccion']
    .
    "&error=no_se_pudo_cerrar"
);

exit();

?>
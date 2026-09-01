<?php

session_start();

require_once("config/conexion.php");

/* =========================================================
   SOLO ADMINISTRADOR
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
   RECIBIR ID DE LA MESA
========================================================= */

$idMesa = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($idMesa <= 0) {

    header("Location: jurados.php?error=mesa");
    exit();

}


/* =========================================================
   BUSCAR MESA
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        id_eleccion,
        id_jurado,
        nombre_mesa,
        estado
    FROM mesas_votacion
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    header("Location: jurados.php?error=mesa");
    exit();

}

$stmt->bind_param("i", $idMesa);

$stmt->execute();

$resultado = $stmt->get_result();

$mesa = $resultado->fetch_assoc();

$stmt->close();


if (!$mesa) {

    header("Location: jurados.php?error=mesa");
    exit();

}


/* =========================================================
   CERRAR MESA
========================================================= */

$stmt = $conn->prepare("
    UPDATE mesas_votacion
    SET
        estado = 'cerrada',
        fecha_cierre = NOW()
    WHERE id = ?
");

if (!$stmt) {

    header("Location: jurados.php?error=cerrar");
    exit();

}

$stmt->bind_param("i", $idMesa);


if (!$stmt->execute()) {

    $stmt->close();

    header("Location: jurados.php?error=cerrar");
    exit();

}

$stmt->close();


/* =========================================================
   REGRESAR A JURADOS
========================================================= */

header("Location: jurados.php?mesa_cerrada=1");
exit();

?>
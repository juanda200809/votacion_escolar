<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   BUSCAR ÚLTIMA ELECCIÓN
========================================= */

$resultado = $conn->query("
    SELECT id
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (
    !$resultado ||
    $resultado->num_rows === 0
) {

    header(
        "Location: admin.php?error=no_eleccion"
    );

    exit();

}


$eleccion =
    $resultado->fetch_assoc();

$idEleccion =
    (int)$eleccion['id'];


/* =========================================
   ABRIR ELECCIÓN
========================================= */

$stmt = $conn->prepare("
    UPDATE elecciones
    SET estado = 'abierta'
    WHERE id = ?
");


if (!$stmt) {

    header(
        "Location: admin.php?error=abrir"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $idEleccion
);


if ($stmt->execute()) {

    $stmt->close();

    header(
        "Location: admin.php?abierta=1"
    );

    exit();

}


/* =========================================
   ERROR
========================================= */

$stmt->close();

header(
    "Location: admin.php?error=abrir"
);

exit();

?>
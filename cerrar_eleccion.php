<?php

session_start();

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");


$consulta = $conn->query("
    SELECT id
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (!$consulta || $consulta->num_rows == 0) {

    header("Location: admin.php?error=no_eleccion");
    exit();
}


$eleccion = $consulta->fetch_assoc();

$id = (int)$eleccion['id'];


$stmt = $conn->prepare("
    UPDATE elecciones
    SET estado='cerrada'
    WHERE id=?
");

$stmt->bind_param("i", $id);

$stmt->execute();

$stmt->close();


header("Location: admin.php?cerrada=1");

exit();

?>
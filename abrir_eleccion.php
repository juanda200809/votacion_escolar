<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

// Abrir la elección
$conn->query("UPDATE elecciones SET estado='abierta' WHERE id=1");

header("Location: admin.php");
exit();
?>
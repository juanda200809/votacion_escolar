<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

if(!isset($_GET['id'])){
    header("Location: elecciones.php");
    exit();
}

$id = (int)$_GET['id'];

/*==========================
ELIMINAR RELACIONES
==========================*/

$conn->query("
DELETE FROM eleccion_cargos
WHERE id_eleccion=$id
");

/*==========================
ELIMINAR ELECCIÓN
==========================*/

$conn->query("
DELETE FROM elecciones
WHERE id=$id
");

header("Location: elecciones.php");
exit();

?>
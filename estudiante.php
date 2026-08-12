<?php
session_start();

if($_SESSION['rol'] != 'estudiante'){
    header("Location: index.php");
}

echo "<h1>Bienvenido Estudiante</h1>";
echo "Hola ".$_SESSION['nombre'];
?>
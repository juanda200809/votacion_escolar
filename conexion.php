<?php

$host = "localhost";
$usuario = "root";
$password = "";
$basedatos = "votaciones_escolares";

$conn = new mysqli($host, $usuario, $password, $basedatos);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

?>
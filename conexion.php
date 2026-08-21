<?php

$host = "127.0.0.1";
$usuario = "root";
$password = "";
$basedatos = "votaciones_escolares";
$puerto = 3306;

$conn = new mysqli(
    $host,
    $usuario,
    $password,
    $basedatos,
    $puerto
);

if ($conn->connect_error) {

    die(
        "Error de conexión con MySQL: " .
        $conn->connect_error
    );

}

$conn->set_charset("utf8mb4");

?>
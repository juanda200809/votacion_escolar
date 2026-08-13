<?php

include("config/conexion.php");

$documento = "1000000001";
$password = "jurado123";

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "
UPDATE usuarios
SET password='$password_hash',
    rol='jurado'
WHERE documento='$documento'
";

if($conn->query($sql)) {

    echo "<h2>Jurado actualizado correctamente.</h2>";

    echo "<p><strong>Documento:</strong> 1000000001</p>";
    echo "<p><strong>Contraseña:</strong> jurado123</p>";
    echo "<p><strong>Rol:</strong> jurado</p>";

} else {

    echo "Error: " . $conn->error;

}

?>
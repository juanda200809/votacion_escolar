<?php

include("config/conexion.php");

/* =========================================
   NUEVAS CONTRASEÑAS
========================================= */

$passwordAdmin = password_hash(
    "admin1234",
    PASSWORD_DEFAULT
);

$passwordJurado = password_hash(
    "jurado1234",
    PASSWORD_DEFAULT
);


/* =========================================
   ACTUALIZAR ADMINISTRADOR
========================================= */

$stmt = $conn->prepare("
    UPDATE usuarios
    SET documento = ?, password = ?
    WHERE rol = 'administrador'
    LIMIT 1
");

$documentoAdmin = "admin";

$stmt->bind_param(
    "ss",
    $documentoAdmin,
    $passwordAdmin
);

$stmt->execute();

$stmt->close();


/* =========================================
   ACTUALIZAR JURADO
========================================= */

$stmt = $conn->prepare("
    UPDATE usuarios
    SET documento = ?, password = ?
    WHERE rol = 'jurado'
    LIMIT 1
");

$documentoJurado = "jurado";

$stmt->bind_param(
    "ss",
    $documentoJurado,
    $passwordJurado
);

$stmt->execute();

$stmt->close();


echo "<h2>Accesos actualizados correctamente</h2>";

echo "<p><strong>Administrador:</strong> admin / admin1234</p>";

echo "<p><strong>Jurado:</strong> jurado / jurado1234</p>";

echo "<p>Ahora elimina este archivo del proyecto.</p>";

?>
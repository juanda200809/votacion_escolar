<?php

session_start();

include("config/conexion.php");


/* =========================================================
   SOLO ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {
    header("Location: login.php");
    exit();
}


$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "administrador") {

    if ($rol === "jurado") {
        header("Location: jurado.php");
        exit();
    }

    header("Location: login.php");
    exit();

}


/* =========================================================
   BUSCAR ELECCIÓN
========================================================= */

$consulta = $conn->query("

    SELECT
        id,
        nombre,
        estado

    FROM elecciones

    ORDER BY id DESC

    LIMIT 1

");


if (
    !$consulta ||
    $consulta->num_rows === 0
) {

    header(
        "Location: admin.php?error=no_eleccion"
    );

    exit();

}


$eleccion =
    $consulta->fetch_assoc();


$idEleccion =
    (int)$eleccion['id'];


/* =========================================================
   SI YA ESTÁ CERRADA
========================================================= */

if (
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) === "cerrada"
) {

    header(
        "Location: admin.php?cerrada=1"
    );

    exit();

}


/* =========================================================
   CERRAR ELECCIÓN
========================================================= */

$stmt = $conn->prepare("

    UPDATE elecciones

    SET estado = 'cerrada'

    WHERE id = ?

");


if (!$stmt) {

    header(
        "Location: admin.php?error=cerrar"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $idEleccion
);


if (
    $stmt->execute()
) {

    header(
        "Location: admin.php?cerrada=1"
    );

    exit();

}


$stmt->close();


header(
    "Location: admin.php?error=cerrar"
);

exit();

?>
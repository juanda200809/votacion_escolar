<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    $_SESSION['rol'] != 'administrador'
) {

    header("Location: login.php");
    exit();

}


include("config/conexion.php");


/* =========================================
   BUSCAR ELECCIÓN ABIERTA
========================================= */

$consulta = $conn->query("

    SELECT id, nombre, estado

    FROM elecciones

    WHERE estado = 'abierta'

    ORDER BY id DESC

    LIMIT 1

");


/* =========================================
   NO HAY ELECCIÓN ABIERTA
========================================= */

if (!$consulta || $consulta->num_rows == 0) {

    header("Location: admin.php?mensaje=no_hay_eleccion");

    exit();

}


$eleccion = $consulta->fetch_assoc();

$idEleccion = (int)$eleccion['id'];


/* =========================================
   CERRAR ELECCIÓN
========================================= */

$cerrar = $conn->prepare("

    UPDATE elecciones

    SET estado = 'cerrada'

    WHERE id = ?

");


if (!$cerrar) {

    die(
        "Error al preparar la consulta: "
        . $conn->error
    );

}


$cerrar->bind_param(
    "i",
    $idEleccion
);


if ($cerrar->execute()) {

    /* =========================================
       VERIFICAR QUE REALMENTE SE CERRÓ
    ========================================= */

    $verificar = $conn->prepare("

        SELECT estado

        FROM elecciones

        WHERE id = ?

        LIMIT 1

    ");

    $verificar->bind_param(
        "i",
        $idEleccion
    );

    $verificar->execute();

    $resultado = $verificar->get_result();


    if ($resultado->num_rows > 0) {

        $estado = $resultado->fetch_assoc();


        if ($estado['estado'] == 'cerrada') {

            header(
                "Location: admin.php?mensaje=eleccion_cerrada"
            );

            exit();

        }

    }


    die("La elección no pudo cerrarse correctamente.");

} else {

    die(
        "Error al cerrar la elección: "
        . $conn->error
    );

}

?>
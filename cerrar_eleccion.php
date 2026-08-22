<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   BUSCAR LA ÚLTIMA ELECCIÓN
========================================= */

$consulta = $conn->query("
    SELECT id, nombre, estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (!$consulta || $consulta->num_rows === 0) {

    header("Location: admin.php?error=no_eleccion");
    exit();

}


$eleccion = $consulta->fetch_assoc();

$idEleccion = (int)$eleccion['id'];
$nombreEleccion = $eleccion['nombre'];
$estadoActual = $eleccion['estado'];


/* =========================================
   CERRAR ELECCIÓN
========================================= */

$stmt = $conn->prepare("
    UPDATE elecciones
    SET estado = 'cerrada'
    WHERE id = ?
");

$stmt->bind_param("i", $idEleccion);

if ($stmt->execute()) {

    /* =====================================
       REGISTRAR EN AUDITORÍA SI EXISTE
    ===================================== */

    $tablaAuditoria = $conn->query("
        SHOW TABLES LIKE 'auditoria'
    ");

    if ($tablaAuditoria && $tablaAuditoria->num_rows > 0) {

        $usuarioId = (int)$_SESSION['id'];

        $descripcion =
            "El administrador cerró la elección: "
            . $nombreEleccion;

        $stmtAuditoria = $conn->prepare("
            INSERT INTO auditoria
            (usuario_id, accion, descripcion, fecha)
            VALUES (?, 'CIERRE_ELECCION', ?, NOW())
        ");

        if ($stmtAuditoria) {

            $stmtAuditoria->bind_param(
                "is",
                $usuarioId,
                $descripcion
            );

            $stmtAuditoria->execute();

            $stmtAuditoria->close();
        }
    }


    $stmt->close();

    header("Location: admin.php?cerrada=1");
    exit();

}


/* =========================================
   ERROR
========================================= */

$stmt->close();

header("Location: admin.php?error=cerrar");
exit();

?>
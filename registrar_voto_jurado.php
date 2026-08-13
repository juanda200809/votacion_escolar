<?php
session_start();

/* =========================================
   VERIFICAR QUE SEA JURADO
========================================= */

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'jurado') {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");

/* =========================================
   VERIFICAR ESTUDIANTE
========================================= */

if (!isset($_SESSION['estudiante_jurado'])) {

    header("Location: jurado.php");
    exit();

}

/* =========================================
   VERIFICAR DATOS RECIBIDOS
========================================= */

if (
    !isset($_POST['id_eleccion']) ||
    !isset($_POST['id_cargo']) ||
    !isset($_POST['id_candidato'])
) {

    header("Location: votar_jurado.php");
    exit();

}

/* =========================================
   OBTENER DATOS
========================================= */

$idUsuario = (int) $_SESSION['estudiante_jurado'];

$idEleccion = (int) $_POST['id_eleccion'];

$idCargo = (int) $_POST['id_cargo'];

$idCandidato = (int) $_POST['id_candidato'];

/* =========================================
   VERIFICAR QUE EL ESTUDIANTE EXISTA
========================================= */

$consultaEstudiante = $conn->query("
    SELECT id
    FROM usuarios
    WHERE id=$idUsuario
    AND rol='estudiante'
    LIMIT 1
");

if ($consultaEstudiante->num_rows == 0) {

    die("El estudiante no existe.");

}

/* =========================================
   VERIFICAR ELECCIÓN
========================================= */

$consultaEleccion = $conn->query("
    SELECT *
    FROM elecciones
    WHERE id=$idEleccion
    LIMIT 1
");

if ($consultaEleccion->num_rows == 0) {

    die("La elección no existe.");

}

$eleccion = $consultaEleccion->fetch_assoc();

/* =========================================
   VERIFICAR QUE ESTÉ ABIERTA
========================================= */

if ($eleccion['estado'] != 'abierta') {

    die("La elección está cerrada.");

}

/* =========================================
   VERIFICAR CARGO
========================================= */

$consultaCargo = $conn->query("
    SELECT *
    FROM eleccion_cargos
    WHERE id_eleccion=$idEleccion
    AND id_cargo=$idCargo
    LIMIT 1
");

if ($consultaCargo->num_rows == 0) {

    die("El cargo no pertenece a esta elección.");

}

/* =========================================
   VERIFICAR CANDIDATO
========================================= */

$consultaCandidato = $conn->query("
    SELECT *
    FROM candidatos
    WHERE id=$idCandidato
    AND id_eleccion=$idEleccion
    AND id_cargo=$idCargo
    LIMIT 1
");

if ($consultaCandidato->num_rows == 0) {

    die("El candidato no pertenece a esta elección.");

}

/* =========================================
   VERIFICAR SI YA VOTÓ
========================================= */

$verificar = $conn->query("
    SELECT id
    FROM votos
    WHERE id_usuario=$idUsuario
    AND id_eleccion=$idEleccion
    AND id_cargo=$idCargo
    LIMIT 1
");

if ($verificar->num_rows > 0) {

    header("Location: votar_jurado.php?error=duplicado");
    exit();

}

/* =========================================
   REGISTRAR VOTO
========================================= */

$fecha = date("Y-m-d H:i:s");

$sql = "
INSERT INTO votos
(
    id_usuario,
    id_candidato,
    id_eleccion,
    fecha_voto,
    id_cargo
)
VALUES
(
    $idUsuario,
    $idCandidato,
    $idEleccion,
    '$fecha',
    $idCargo
)
";

if (!$conn->query($sql)) {

    die(
        "Error al registrar el voto: "
        . $conn->error
    );

}

/* =========================================
   VOLVER A LA PANTALLA DE VOTACIÓN
========================================= */

header("Location: votar_jurado.php?ok=1");
exit();

?>
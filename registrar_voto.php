<?php
session_start();

/*=========================================
=      VERIFICAR SESIÓN
=========================================*/

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'estudiante') {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");

/*=========================================
=      VALIDAR DATOS DEL FORMULARIO
=========================================*/

if (
    !isset($_POST['id_eleccion']) ||
    !isset($_POST['id_cargo']) ||
    !isset($_POST['id_candidato'])
) {

    header("Location: votar.php");
    exit();

}

/*=========================================
=      OBTENER DATOS
=========================================*/

$idUsuario   = (int) $_SESSION['id'];
$idEleccion  = (int) $_POST['id_eleccion'];
$idCargo     = (int) $_POST['id_cargo'];
$idCandidato = (int) $_POST['id_candidato'];

/*=========================================
=      VERIFICAR ELECCIÓN
=========================================*/

$sql = "

SELECT *

FROM elecciones

WHERE id = $idEleccion

LIMIT 1

";

$consultaEleccion = $conn->query($sql);

if($consultaEleccion->num_rows == 0){

    die("La elección no existe.");

}

$eleccion = $consultaEleccion->fetch_assoc();

/*=========================================
=      VERIFICAR ESTADO
=========================================*/

if($eleccion['estado'] != 'abierta'){

    die("La elección está cerrada.");

}

/*=========================================
=      VERIFICAR CARGO
=========================================*/

$sql = "

SELECT *

FROM eleccion_cargos

WHERE id_eleccion = $idEleccion

AND id_cargo = $idCargo

LIMIT 1

";

$consultaCargo = $conn->query($sql);

if($consultaCargo->num_rows == 0){

    die("El cargo no pertenece a esta elección.");

}

/*=========================================
=      VERIFICAR CANDIDATO
=========================================*/

$sql = "

SELECT *

FROM candidatos

WHERE id = $idCandidato

AND id_eleccion = $idEleccion

AND id_cargo = $idCargo

LIMIT 1

";


$consultaCandidato = $conn->query($sql);

if($consultaCandidato->num_rows == 0){

    die("El candidato no pertenece a esta elección.");

}

/*=========================================
=      VERIFICAR SI YA VOTÓ
=========================================*/

$sql = "

SELECT id

FROM votos

WHERE id_usuario = $idUsuario

AND id_eleccion = $idEleccion

AND id_cargo = $idCargo

LIMIT 1

";

$verificar = $conn->query($sql);

if($verificar->num_rows > 0){

    die("Ya registraste tu voto para este cargo.");

}
/*=========================================
=      REGISTRAR EL VOTO
=========================================*/

$fecha = date("Y-m-d H:i:s");

$sql = "

INSERT INTO votos(

id_usuario,

id_candidato,

id_eleccion,

fecha_voto,

id_cargo

)

VALUES(

$idUsuario,

$idCandidato,

$idEleccion,

'$fecha',

$idCargo

)

";

if(!$conn->query($sql)){

    die("Error al registrar el voto: " . $conn->error);

}

/*=========================================
=      OBTENER DATOS DEL CANDIDATO
=========================================*/

$sql = "

SELECT

candidatos.nombre,
candidatos.apellido,
cargos.nombre_cargo

FROM candidatos

INNER JOIN cargos

ON candidatos.id_cargo = cargos.id

WHERE candidatos.id = $idCandidato

LIMIT 1

";

$consulta = $conn->query($sql);

$candidato = $consulta->fetch_assoc();
/*=========================================
=      CONFIRMAR VOTO REGISTRADO
=========================================*/

$_SESSION['mensaje'] = "Tu voto fue registrado correctamente.";

/*=========================================
=      REDIRECCIONAR AL ESTUDIANTE
=========================================*/

header("Location: votar.php?ok=1");
exit();

?>
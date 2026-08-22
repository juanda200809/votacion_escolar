<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   VERIFICAR ROL JURADO
========================================================= */

$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rol === "estudiante") {

        header("Location: votar.php");
        exit();

    }

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================================
   RECIBIR DOCUMENTO
========================================================= */

$documento = trim(
    $_POST['documento'] ?? ''
);


if ($documento === '') {

    mostrarError(
        "Debe ingresar el documento del estudiante."
    );

}


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;

$resultado = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    WHERE LOWER(TRIM(estado)) = 'abierta'
    ORDER BY id DESC
    LIMIT 1
");


if (
    $resultado &&
    $resultado->num_rows > 0
) {

    $eleccion =
        $resultado->fetch_assoc();

}


if (!$eleccion) {

    mostrarError(
        "No existe una elección abierta actualmente."
    );

}


$idEleccion =
    (int)$eleccion['id'];


/* =========================================================
   BUSCAR ESTUDIANTE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE documento = ?
    AND LOWER(TRIM(rol)) = 'estudiante'
    LIMIT 1
");


if (!$stmt) {

    mostrarError(
        "No fue posible realizar la consulta del estudiante."
    );

}


$stmt->bind_param(
    "s",
    $documento
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    mostrarError(
        "El documento ingresado no corresponde a un estudiante registrado."
    );

}


$estudiante =
    $resultado->fetch_assoc();


$stmt->close();


$idEstudiante =
    (int)$estudiante['id'];


/* =========================================================
   OBTENER CANTIDAD DE CARGOS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM eleccion_cargos
    WHERE id_eleccion = ?
");


if (!$stmt) {

    mostrarError(
        "No fue posible comprobar los cargos de la elección."
    );

}


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$datos =
    $resultado->fetch_assoc();


$totalCargos =
    (int)$datos['total'];


$stmt->close();


if ($totalCargos <= 0) {

    mostrarError(
        "La elección no tiene cargos configurados."
    );

}


/* =========================================================
   COMPROBAR VOTOS DEL ESTUDIANTE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT v.id_cargo) AS total_votos
    FROM votos v

    INNER JOIN candidatos c
        ON c.id = v.id_candidato

    WHERE v.id_usuario = ?

    AND c.id_eleccion = ?
");


if (!$stmt) {

    mostrarError(
        "No fue posible comprobar el estado de la votación."
    );

}


$stmt->bind_param(
    "ii",
    $idEstudiante,
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$datos =
    $resultado->fetch_assoc();


$totalVotos =
    (int)$datos['total_votos'];


$stmt->close();


/* =========================================================
   COMPROBAR SI YA TERMINÓ DE VOTAR
========================================================= */

if (
    $totalVotos >= $totalCargos
) {

    mostrarYaVoto(
        $estudiante,
        $eleccion
    );

}


/* =========================================================
   GUARDAR ESTUDIANTE EN SESIÓN
========================================================= */

$_SESSION['votante_id'] =
    $idEstudiante;

$_SESSION['votante_documento'] =
    $estudiante['documento'];

$_SESSION['votante_nombre'] =
    $estudiante['nombre'];

$_SESSION['votante_apellido'] =
    $estudiante['apellido'];

$_SESSION['votante_curso'] =
    $estudiante['curso'];

$_SESSION['eleccion_votante_id'] =
    $idEleccion;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Confirmar estudiante
</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width:850px;

    margin:50px auto;

    padding:20px;

}


.card-principal {

    background:white;

    border:none;

    border-radius:20px;

    overflow:hidden;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.encabezado {

    background:#1473ed;

    color:white;

    padding:28px;

}


.encabezado h1 {

    margin:0;

    font-weight:bold;

}


.cuerpo {

    padding:35px;

}


.dato {

    background:#f5f8fc;

    border:1px solid #e1e8f0;

    border-radius:12px;

    padding:18px;

    height:100%;

}


.dato-titulo {

    color:#1453a3;

    font-weight:bold;

    margin-bottom:6px;

}


.dato-valor {

    font-size:18px;

}


.info-eleccion {

    background:#cfe2ff;

    border:
        1px solid #9ec5fe;

    color:#084298;

    border-radius:12px;

    padding:18px;

    margin-top:25px;

}


.btn-continuar {

    background:#198754;

    border:none;

    color:white;

    padding:13px 25px;

    font-size:18px;

    font-weight:bold;

    border-radius:8px;

}


.btn-continuar:hover {

    background:#157347;

    color:white;

}


.btn-cancelar {

    padding:13px 25px;

    font-size:18px;

    border-radius:8px;

}


</style>

</head>


<body>


<div class="contenedor">


<div class="card-principal">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="encabezado">

<h1>

<i class="bi bi-person-check-fill"></i>

Estudiante encontrado

</h1>

<p class="mb-0 mt-2">

Verifique los datos antes de iniciar la votación.

</p>

</div>


<!-- =====================================================
     CUERPO
===================================================== -->

<div class="cuerpo">


<div class="row g-3">


<!-- DOCUMENTO -->

<div class="col-md-6">

<div class="dato">

<div class="dato-titulo">

<i class="bi bi-person-vcard-fill"></i>

Documento

</div>


<div class="dato-valor">

<?php echo htmlspecialchars(
    $estudiante['documento']
); ?>

</div>

</div>

</div>


<!-- CURSO -->

<div class="col-md-6">

<div class="dato">

<div class="dato-titulo">

<i class="bi bi-mortarboard-fill"></i>

Curso

</div>


<div class="dato-valor">

<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>

</div>

</div>

</div>


<!-- NOMBRE -->

<div class="col-md-6">

<div class="dato">

<div class="dato-titulo">

<i class="bi bi-person-fill"></i>

Nombre

</div>


<div class="dato-valor">

<?php echo htmlspecialchars(
    $estudiante['nombre']
); ?>

</div>

</div>

</div>


<!-- APELLIDO -->

<div class="col-md-6">

<div class="dato">

<div class="dato-titulo">

<i class="bi bi-person-lines-fill"></i>

Apellido

</div>


<div class="dato-valor">

<?php echo htmlspecialchars(
    $estudiante['apellido']
); ?>

</div>

</div>

</div>


</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="info-eleccion">

<h5>

<i class="bi bi-calendar-check-fill"></i>

Elección actual

</h5>


<strong>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</strong>


<?php if (
    !empty($eleccion['descripcion'])
) { ?>

<p class="mb-0 mt-2">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>

</div>


<!-- =====================================================
     ADVERTENCIA
===================================================== -->

<div class="alert alert-warning mt-4">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>Importante:</strong>

Verifique que los datos mostrados correspondan
al estudiante que realizará la votación.

</div>


<!-- =====================================================
     BOTONES
===================================================== -->

<div class="mt-4">


<a
href="votar_por_jurado.php"
class="btn btn-continuar">

<i class="bi bi-arrow-right-circle-fill"></i>

Confirmar y continuar a votar

</a>


<a
href="jurado.php"
class="btn btn-secondary btn-cancelar ms-2">

<i class="bi bi-x-circle"></i>

Cancelar

</a>


</div>


</div>

</div>

</div>


</body>

</html>


<?php


/* =========================================================
   FUNCIÓN: ERROR
========================================================= */

function mostrarError($mensaje)
{

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
No se puede continuar
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

}


.contenedor-error {

    max-width:700px;

    margin:100px auto;

    padding:20px;

}


.card-error {

    background:white;

    border-radius:20px;

    padding:45px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.icono {

    font-size:75px;

    color:#dc3545;

}

</style>

</head>


<body>


<div class="contenedor-error">


<div class="card-error">


<i class="bi bi-exclamation-triangle-fill icono"></i>


<h1 class="text-danger mt-3">

No se puede continuar

</h1>


<p class="fs-5">

<?php echo htmlspecialchars(
    $mensaje
); ?>

</p>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


</div>

</div>


</body>

</html>

<?php

exit();

}


/* =========================================================
   FUNCIÓN: YA VOTÓ
========================================================= */

function mostrarYaVoto(
    $estudiante,
    $eleccion
)
{

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Estudiante ya votó
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

}


.contenedor {

    max-width:750px;

    margin:80px auto;

    padding:20px;

}


.card {

    background:white;

    border:none;

    border-radius:20px;

    padding:45px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.icono {

    font-size:80px;

    color:#198754;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<i class="bi bi-check-circle-fill icono"></i>


<h1 class="text-success mt-3">

Estudiante ya votó

</h1>


<p class="fs-5">

El estudiante

<strong>

<?php echo htmlspecialchars(
    $estudiante['nombre'] .
    " " .
    $estudiante['apellido']
); ?>

</strong>

ya completó su votación en:

</p>


<h5 class="text-primary">

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h5>


<div class="alert alert-success mt-4">

<i class="bi bi-shield-check"></i>

La votación ya está registrada.

<br>

No se puede volver a realizar la votación.

</div>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


</div>

</div>


</body>

</html>

<?php

exit();

}

?>
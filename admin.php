<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
========================================= */

if (!isset($_SESSION['id']) || !isset($_SESSION['rol'])) {

    header("Location: login.php");
    exit();

}

if ($_SESSION['rol'] !== 'administrador') {

    header("Location: login.php");
    exit();

}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   CONTAR ESTUDIANTES
========================================= */

$consultaEstudiantes = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='estudiante'
");

$totalEstudiantes = 0;

if ($consultaEstudiantes) {

    $fila = $consultaEstudiantes->fetch_assoc();

    $totalEstudiantes = $fila['total'];

}


/* =========================================
   CONTAR JURADOS
========================================= */

$consultaJurados = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='jurado'
");

$totalJurados = 0;

if ($consultaJurados) {

    $fila = $consultaJurados->fetch_assoc();

    $totalJurados = $fila['total'];

}


/* =========================================
   CONTAR CANDIDATOS
========================================= */

$consultaCandidatos = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

$totalCandidatos = 0;

if ($consultaCandidatos) {

    $fila = $consultaCandidatos->fetch_assoc();

    $totalCandidatos = $fila['total'];

}


/* =========================================
   CONTAR VOTOS
========================================= */

$consultaVotos = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

$totalVotos = 0;

if ($consultaVotos) {

    $fila = $consultaVotos->fetch_assoc();

    $totalVotos = $fila['total'];

}


/* =========================================
   BUSCAR ELECCIÓN
========================================= */

$consultaEleccion = $conn->query("
    SELECT *
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

$eleccion = null;

if ($consultaEleccion && $consultaEleccion->num_rows > 0) {

    $eleccion = $consultaEleccion->fetch_assoc();

}


/* =========================================
   DATOS DE LA ELECCIÓN
========================================= */

$estadoEleccion = "Sin elección";

$nombreEleccion = "No hay elecciones registradas";

if ($eleccion) {

    $estadoEleccion = $eleccion['estado'];

    $nombreEleccion = $eleccion['nombre'];

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Panel de Administrador</title>


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

    background: #eef3f9;

    min-height: 100vh;

}


/* =========================================
   ENCABEZADO
========================================= */

.header {

    background: linear-gradient(
        135deg,
        #0d47a1,
        #1565c0
    );

    color: white;

    padding: 25px 0;

    margin-bottom: 30px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.15);

}


.header h1 {

    margin: 0;

    font-weight: 600;

}


.header p {

    margin: 5px 0 0;

    opacity: .9;

}


/* =========================================
   TARJETAS DE ESTADÍSTICAS
========================================= */

.stat-card {

    background: white;

    border-radius: 15px;

    padding: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.10);

    transition: .3s;

    height: 100%;

}


.stat-card:hover {

    transform: translateY(-5px);

}


.stat-icon {

    font-size: 40px;

    color: #0d6efd;

}


.stat-number {

    font-size: 32px;

    font-weight: bold;

    color: #0d47a1;

}


/* =========================================
   TARJETAS DEL MENÚ
========================================= */

.menu-card {

    background: white;

    border-radius: 15px;

    padding: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.10);

    height: 100%;

}


.menu-card h4 {

    color: #0d47a1;

    margin-bottom: 20px;

}


/* =========================================
   ESTADO ELECCIÓN
========================================= */

.estado {

    font-size: 18px;

    font-weight: bold;

}


.estado-abierta {

    color: #198754;

}


.estado-cerrada {

    color: #dc3545;

}


.estado-sin {

    color: #6c757d;

}


/* =========================================
   BOTONES
========================================= */

.menu-btn {

    width: 100%;

    margin-bottom: 10px;

    padding: 12px;

    text-align: left;

}


</style>

</head>


<body>


<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="header">

<div class="container">

<div class="d-flex justify-content-between align-items-center">

<div>

<h1>

<i class="bi bi-speedometer2"></i>

Panel de Administrador

</h1>

<p>

Sistema de Votaciones Escolares

</p>

</div>


<div>

<a
href="logout.php"
class="btn btn-light">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>

</div>

</div>

</div>

</div>


<div class="container pb-5">


<!-- =========================================
     BIENVENIDA
========================================= -->

<div class="mb-4">

<h3>

Bienvenido,

<strong>

<?php

echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Administrador'
);

?>

</strong>

</h3>

<p class="text-muted">

Desde este panel puede administrar todo el sistema.

</p>

</div>


<!-- =========================================
     ESTADÍSTICAS
========================================= -->

<div class="row g-4 mb-4">


<!-- ESTUDIANTES -->

<div class="col-md-3">

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-people-fill"></i>

</div>

<div class="stat-number">

<?php echo $totalEstudiantes; ?>

</div>

<h6>

Estudiantes

</h6>

</div>

</div>


<!-- JURADOS -->

<div class="col-md-3">

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-person-badge-fill"></i>

</div>

<div class="stat-number">

<?php echo $totalJurados; ?>

</div>

<h6>

Jurados

</h6>

</div>

</div>


<!-- CANDIDATOS -->

<div class="col-md-3">

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-person-vcard-fill"></i>

</div>

<div class="stat-number">

<?php echo $totalCandidatos; ?>

</div>

<h6>

Candidatos

</h6>

</div>

</div>


<!-- VOTOS -->

<div class="col-md-3">

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-check2-square"></i>

</div>

<div class="stat-number">

<?php echo $totalVotos; ?>

</div>

<h6>

Votos registrados

</h6>

</div>

</div>

</div>


<!-- =========================================
     ELECCIÓN ACTUAL
========================================= -->

<div class="menu-card mb-4">

<h4>

<i class="bi bi-megaphone-fill"></i>

Elección actual

</h4>


<div class="row align-items-center">

<div class="col-md-8">

<h5>

<?php echo htmlspecialchars($nombreEleccion); ?>

</h5>


<p class="mb-0">

Estado:

<?php

if ($estadoEleccion == "abierta") {

?>

<span class="estado estado-abierta">

<i class="bi bi-unlock-fill"></i>

ABIERTA

</span>

<?php

} elseif ($estadoEleccion == "cerrada") {

?>

<span class="estado estado-cerrada">

<i class="bi bi-lock-fill"></i>

CERRADA

</span>

<?php

} else {

?>

<span class="estado estado-sin">

SIN ELECCIÓN

</span>

<?php

}

?>

</p>

</div>


<div class="col-md-4 text-md-end mt-3 mt-md-0">


<?php if ($eleccion) { ?>


<?php if ($estadoEleccion == "cerrada") { ?>

<a
href="abrir_eleccion.php"
class="btn btn-success">

<i class="bi bi-unlock-fill"></i>

Abrir elección

</a>

<?php } else { ?>

<a
href="cerrar_eleccion.php"
class="btn btn-danger"
onclick="return confirm('¿Está seguro de cerrar la elección?');">

<i class="bi bi-lock-fill"></i>

Cerrar elección

</a>

<?php } ?>


<?php } ?>


</div>

</div>

</div>


<!-- =========================================
     ADMINISTRACIÓN
========================================= -->

<div class="row g-4">


<!-- GESTIÓN DE USUARIOS -->

<div class="col-md-6">

<div class="menu-card">

<h4>

<i class="bi bi-people-fill"></i>

Gestión de usuarios

</h4>


<a
href="estudiantes.php"
class="btn btn-primary menu-btn">

<i class="bi bi-person-lines-fill"></i>

Gestionar estudiantes

</a>


<a
href="crear_jurado.php"
class="btn btn-primary menu-btn">

<i class="bi bi-person-badge-fill"></i>

Gestionar jurados

</a>


</div>

</div>


<!-- GESTIÓN ELECTORAL -->

<div class="col-md-6">

<div class="menu-card">

<h4>

<i class="bi bi-ballot-fill"></i>

Gestión electoral

</h4>


<a
href="candidatos.php"
class="btn btn-primary menu-btn">

<i class="bi bi-person-vcard-fill"></i>

Gestionar candidatos

</a>


<a
href="elecciones.php"
class="btn btn-primary menu-btn">

<i class="bi bi-calendar-event-fill"></i>

Gestionar elecciones

</a>


<a
href="resultados.php"
class="btn btn-success menu-btn">

<i class="bi bi-bar-chart-fill"></i>

Ver resultados

</a>


</div>

</div>


<!-- REPORTES -->

<div class="col-md-6">

<div class="menu-card">

<h4>

<i class="bi bi-file-earmark-text-fill"></i>

Reportes

</h4>


<a
href="resultados.php"
class="btn btn-outline-primary menu-btn">

<i class="bi bi-clipboard-data-fill"></i>

Resultados de votación

</a>


<a
href="graficas.php"
class="btn btn-outline-primary menu-btn">

<i class="bi bi-pie-chart-fill"></i>

Gráficas

</a>


</div>

</div>


<!-- CONFIGURACIÓN -->

<div class="col-md-6">

<div class="menu-card">

<h4>

<i class="bi bi-gear-fill"></i>

Sistema

</h4>


<a
href="admin.php"
class="btn btn-outline-secondary menu-btn">

<i class="bi bi-arrow-clockwise"></i>

Actualizar panel

</a>


<a
href="logout.php"
class="btn btn-outline-danger menu-btn">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>

</div>


</div>


<!-- =========================================
     PIE
========================================= -->

<div class="text-center mt-5 text-muted">

<small>

Sistema de Votaciones Escolares

<br>

Versión 2.0

</small>

</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
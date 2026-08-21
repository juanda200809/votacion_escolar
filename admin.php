<?php

session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");


/* =========================================
   ESTUDIANTES
========================================= */

$consulta = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='estudiante'
");

$totalEstudiantes = 0;

if ($consulta) {
    $fila = $consulta->fetch_assoc();
    $totalEstudiantes = $fila['total'];
}


/* =========================================
   JURADOS
========================================= */

$consulta = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='jurado'
");

$totalJurados = 0;

if ($consulta) {
    $fila = $consulta->fetch_assoc();
    $totalJurados = $fila['total'];
}


/* =========================================
   CANDIDATOS
========================================= */

$consulta = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

$totalCandidatos = 0;

if ($consulta) {
    $fila = $consulta->fetch_assoc();
    $totalCandidatos = $fila['total'];
}


/* =========================================
   VOTOS
========================================= */

$consulta = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

$totalVotos = 0;

if ($consulta) {
    $fila = $consulta->fetch_assoc();
    $totalVotos = $fila['total'];
}


/* =========================================
   ELECCIÓN ACTUAL
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


$nombreEleccion = "Sin elección registrada";
$estadoEleccion = "Sin elección";

if ($eleccion) {

    $nombreEleccion = $eleccion['nombre'];
    $estadoEleccion = $eleccion['estado'];

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Panel Administrador</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    margin: 0;

    background: #eef3f9;

    font-family: Arial, sans-serif;

}


/* =========================================
   BARRA LATERAL
========================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #0d47a1;

    color: white;

    overflow-y: auto;

}


.logo {

    padding: 25px 20px;

    font-size: 30px;

    font-weight: bold;

    border-bottom: 1px solid rgba(255,255,255,.15);

}


.logo span {

    font-size: 25px;

}


.menu {

    padding-top: 15px;

}


.menu a {

    display: block;

    color: white;

    text-decoration: none;

    padding: 15px 20px;

    font-size: 16px;

    transition: .2s;

}


.menu a:hover {

    background: #1565c0;

}


.menu a i {

    width: 25px;

}


/* =========================================
   CONTENIDO
========================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;

}


/* =========================================
   BARRA SUPERIOR
========================================= */

.topbar {

    height: 70px;

    background: #0d6efd;

    color: white;

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 0 25px;

    box-shadow: 0 3px 10px rgba(0,0,0,.15);

}


.topbar-title {

    font-size: 20px;

}


.topbar-admin {

    font-size: 16px;

}


/* =========================================
   CONTENIDO PRINCIPAL
========================================= */

.content {

    padding: 35px;

}


.welcome {

    color: #0d47a1;

    font-size: 32px;

    font-weight: bold;

}


.welcome-box {

    background: #cfe2ff;

    border: 1px solid #9ec5fe;

    border-radius: 6px;

    padding: 20px;

    color: #084298;

    margin-top: 15px;

}


.welcome-box h2 {

    font-weight: bold;

}


/* =========================================
   TARJETAS ESTADÍSTICAS
========================================= */

.stat-card {

    background: white;

    border-radius: 18px;

    padding: 30px;

    text-align: center;

    min-height: 230px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.stat-icon {

    font-size: 65px;

    margin-bottom: 15px;

}


.stat-number {

    font-size: 44px;

    color: #0d6efd;

    font-weight: bold;

}


.stat-title {

    font-size: 17px;

    margin-top: 10px;

}


/* =========================================
   ACCESOS RÁPIDOS
========================================= */

.section-card {

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.section-title {

    text-align: center;

    color: #0d47a1;

    font-size: 28px;

    font-weight: bold;

    margin-bottom: 25px;

}


.quick-btn {

    height: 125px;

    border-radius: 10px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    text-decoration: none;

    color: white;

    font-weight: bold;

    transition: .2s;

}


.quick-btn:hover {

    transform: translateY(-3px);

    color: white;

}


.quick-btn i {

    font-size: 30px;

    margin-bottom: 12px;

}


/* COLORES */

.btn-estudiantes {

    background: #0d6efd;

}


.btn-jurados {

    background: #6f42c1;

}


.btn-candidatos {

    background: #198754;

}


.btn-resultados {

    background: #ffc107;

    color: #000;

}


.btn-graficas {

    background: #0dcaf0;

}


.btn-pdf {

    background: #dc3545;

}


.btn-salir {

    background: #212529;

}


/* =========================================
   INFORMACIÓN
========================================= */

.info-card {

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.info-title {

    color: #0d47a1;

    font-size: 28px;

    text-align: center;

    margin-bottom: 25px;

}


.info-row {

    display: flex;

    justify-content: space-between;

    padding: 13px 5px;

    border-bottom: 1px solid #ddd;

}


.estado-abierta {

    background: #198754;

    color: white;

    padding: 6px 15px;

    border-radius: 7px;

    font-weight: bold;

}


.estado-cerrada {

    background: #dc3545;

    color: white;

    padding: 6px 15px;

    border-radius: 7px;

    font-weight: bold;

}


.estado-sin {

    background: #6c757d;

    color: white;

    padding: 6px 15px;

    border-radius: 7px;

    font-weight: bold;

}


/* =========================================
   RESUMEN
========================================= */

.resumen {

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.resumen h3 {

    text-align: center;

    color: #198754;

    margin-bottom: 20px;

}


/* =========================================
   PIE
========================================= */

.footer {

    text-align: center;

    margin-top: 60px;

    padding: 25px;

    color: #667085;

    border-top: 1px solid #ccd2d8;

}


@media(max-width:900px){

    .sidebar {

        width: 210px;

    }

    .main {

        margin-left: 210px;

    }

}


@media(max-width:700px){

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }

    .main {

        margin-left: 0;

    }

}

</style>

</head>


<body>


<!-- =========================================
     BARRA LATERAL
========================================= -->

<div class="sidebar">

<div class="logo">

📦 VOTACIONES

</div>


<div class="menu">


<a href="admin.php">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<a href="estudiantes.php">

<i class="bi bi-people-fill"></i>

Estudiantes

</a>


<!-- NUEVO: JURADOS -->

<a href="crear_jurado.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>


<a href="candidatos.php">

<i class="bi bi-person-vcard-fill"></i>

Candidatos

</a>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<a href="elecciones.php">

<i class="bi bi-calendar-event-fill"></i>

Elecciones

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<a href="abrir_eleccion.php">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a href="cerrar_eleccion.php">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<a href="logout.php">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>


</div>

</div>


<!-- =========================================
     CONTENIDO
========================================= -->

<div class="main">


<!-- BARRA SUPERIOR -->

<div class="topbar">

<div class="topbar-title">

🎓 Sistema de Votaciones Escolares

</div>


<div class="topbar-admin">

Administrador

</div>

</div>


<div class="content">


<!-- =========================================
     BIENVENIDA
========================================= -->

<div class="welcome">

Bienvenido, Administrador 👋

</div>


<div class="welcome-box">

<div style="font-size:20px;">

👋 ¡Bienvenido al Panel de Administración!

</div>


<h2>

Administre estudiantes, jurados, candidatos, votaciones y resultados desde un solo lugar.

</h2>

</div>


<p class="text-muted mt-3">

Panel de Administración del Sistema de Votaciones Escolares

</p>


<!-- =========================================
     ESTADÍSTICAS
========================================= -->

<div class="row g-4 mt-2">


<!-- ESTUDIANTES -->

<div class="col-lg-3 col-md-6">

<div class="stat-card">

<div class="stat-icon">

👨‍🎓

</div>

<div class="stat-number">

<?php echo $totalEstudiantes; ?>

</div>

<div class="stat-title">

Estudiantes Registrados

</div>

</div>

</div>


<!-- JURADOS -->

<div class="col-lg-3 col-md-6">

<div class="stat-card">

<div class="stat-icon">

🧑‍⚖️

</div>

<div class="stat-number">

<?php echo $totalJurados; ?>

</div>

<div class="stat-title">

Jurados Registrados

</div>

</div>

</div>


<!-- CANDIDATOS -->

<div class="col-lg-3 col-md-6">

<div class="stat-card">

<div class="stat-icon">

📦

</div>

<div class="stat-number">

<?php echo $totalCandidatos; ?>

</div>

<div class="stat-title">

Candidatos Inscritos

</div>

</div>

</div>


<!-- VOTOS -->

<div class="col-lg-3 col-md-6">

<div class="stat-card">

<div class="stat-icon">

☑️

</div>

<div class="stat-number">

<?php echo $totalVotos; ?>

</div>

<div class="stat-title">

Votos Registrados

</div>

</div>

</div>


</div>


<!-- =========================================
     ACCESOS RÁPIDOS
========================================= -->

<div class="section-card mt-5">

<div class="section-title">

⚡ Accesos Rápidos

</div>


<div class="row g-3">


<!-- ESTUDIANTES -->

<div class="col-lg-4 col-md-6">

<a
href="estudiantes.php"
class="quick-btn btn-estudiantes">

<i class="bi bi-people-fill"></i>

Gestionar Estudiantes

</a>

</div>


<!-- JURADOS -->

<div class="col-lg-4 col-md-6">

<a
href="crear_jurado.php"
class="quick-btn btn-jurados">

<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados

</a>

</div>


<!-- CANDIDATOS -->

<div class="col-lg-4 col-md-6">

<a
href="candidatos.php"
class="quick-btn btn-candidatos">

<i class="bi bi-person-vcard-fill"></i>

Gestionar Candidatos

</a>

</div>


<!-- RESULTADOS -->

<div class="col-lg-4 col-md-6">

<a
href="resultados.php"
class="quick-btn btn-resultados">

<i class="bi bi-trophy-fill"></i>

Ver Resultados

</a>

</div>


<!-- GRÁFICAS -->

<div class="col-lg-4 col-md-6">

<a
href="graficas.php"
class="quick-btn btn-graficas">

<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas

</a>

</div>


<!-- PDF -->

<div class="col-lg-4 col-md-6">

<a
href="pdf_resultados.php"
class="quick-btn btn-pdf">

<i class="bi bi-file-earmark-pdf-fill"></i>

Descargar PDF

</a>

</div>


<!-- CERRAR -->

<div class="col-lg-4 col-md-6">

<a
href="logout.php"
class="quick-btn btn-salir">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>

</div>


</div>

</div>


<!-- =========================================
     INFORMACIÓN DEL SISTEMA
========================================= -->

<div class="row g-4 mt-4">


<div class="col-lg-8">

<div class="info-card">

<div class="info-title">

ℹ️ Información del Sistema

</div>


<div class="info-row">

<strong>Administrador</strong>

<span>

<?php

echo htmlspecialchars(
$_SESSION['nombre'] ?? 'Administrador'
);

?>

</span>

</div>


<div class="info-row">

<strong>Fecha</strong>

<span>

<?php echo date("d/m/Y"); ?>

</span>

</div>


<div class="info-row">

<strong>Hora</strong>

<span>

<?php echo date("h:i A"); ?>

</span>

</div>


<div class="info-row">

<strong>Estado de la elección</strong>

<span>


<?php

if ($estadoEleccion == "abierta") {

?>

<span class="estado-abierta">

🟢 Abierta

</span>

<?php

} elseif ($estadoEleccion == "cerrada") {

?>

<span class="estado-cerrada">

🔴 Cerrada

</span>

<?php

} else {

?>

<span class="estado-sin">

⚪ Sin elección

</span>

<?php

}

?>

</span>

</div>


<div class="info-row">

<strong>Versión</strong>

<span>

2.0 Profesional

</span>

</div>


</div>

</div>


<!-- RESUMEN -->

<div class="col-lg-4">

<div class="resumen">

<h3>

💾 Resumen

</h3>


<div class="list-group">


<div class="list-group-item d-flex justify-content-between">

Estudiantes

<span class="badge bg-primary">

<?php echo $totalEstudiantes; ?>

</span>

</div>


<div class="list-group-item d-flex justify-content-between">

Jurados

<span class="badge bg-purple"
style="background:#6f42c1 !important;">

<?php echo $totalJurados; ?>

</span>

</div>


<div class="list-group-item d-flex justify-content-between">

Candidatos

<span class="badge bg-success">

<?php echo $totalCandidatos; ?>

</span>

</div>


<div class="list-group-item d-flex justify-content-between">

Votos

<span class="badge bg-warning text-dark">

<?php echo $totalVotos; ?>

</span>

</div>


<div class="list-group-item d-flex justify-content-between">

Estado

<?php

if ($estadoEleccion == "abierta") {

?>

<span class="badge bg-success">

Activa

</span>

<?php

} else {

?>

<span class="badge bg-danger">

Inactiva

</span>

<?php

}

?>

</div>


</div>

</div>

</div>


</div>


<!-- =========================================
     PIE
========================================= -->

<div class="footer">

<strong>

Sistema de Votaciones Escolares v2.0

</strong>

<br><br>

Desarrollado por <strong>Juan David Otero Cantor</strong>

<br><br>

© <?php echo date("Y"); ?> Todos los derechos reservados.

</div>


</div>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
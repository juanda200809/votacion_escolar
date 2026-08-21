<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
========================================= */

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");


/* =========================================
   CONTADORES
========================================= */

/* ESTUDIANTES */

$consultaEstudiantes = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'estudiante'
");

$totalEstudiantes = 0;

if ($consultaEstudiantes) {

    $fila = $consultaEstudiantes->fetch_assoc();

    $totalEstudiantes = $fila['total'];

}


/* JURADOS */

$consultaJurados = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'jurado'
");

$totalJurados = 0;

if ($consultaJurados) {

    $fila = $consultaJurados->fetch_assoc();

    $totalJurados = $fila['total'];

}


/* CANDIDATOS */

$consultaCandidatos = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

$totalCandidatos = 0;

if ($consultaCandidatos) {

    $fila = $consultaCandidatos->fetch_assoc();

    $totalCandidatos = $fila['total'];

}


/* VOTOS */

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
   ESTADO DE LA ELECCIÓN
========================================= */

$estadoEleccion = "cerrada";

$consultaEstado = $conn->query("
    SELECT estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

if ($consultaEstado && $consultaEstado->num_rows > 0) {

    $filaEstado = $consultaEstado->fetch_assoc();

    $estadoEleccion = $filaEstado['estado'];

}


/* =========================================
   INFORMACIÓN DE LA ELECCIÓN
========================================= */

$nombreEleccion = "Sin elección registrada";

$consultaEleccion = $conn->query("
    SELECT nombre
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

if ($consultaEleccion && $consultaEleccion->num_rows > 0) {

    $filaEleccion = $consultaEleccion->fetch_assoc();

    $nombreEleccion = $filaEleccion['nombre'];

}


/* =========================================
   FECHA Y HORA
========================================= */

$fechaActual = date("d/m/Y");
$horaActual = date("h:i A");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Panel Administrador</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

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

    font-family: Arial, Helvetica, sans-serif;

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

    z-index: 1000;

}


/* LOGO */

.logo {

    padding: 25px 20px;

    font-size: 30px;

    font-weight: bold;

    text-align: center;

    border-bottom: 1px solid rgba(255,255,255,.15);

}


.logo-icon {

    font-size: 40px;

    display: block;

    margin-bottom: 5px;

}


/* MENU */

.menu {

    padding: 15px 0;

}


.menu a {

    display: block;

    padding: 14px 20px;

    color: white;

    text-decoration: none;

    font-size: 16px;

    transition: .2s;

}


.menu a:hover {

    background: #1565c0;

    padding-left: 27px;

}


.menu a i {

    width: 25px;

    margin-right: 5px;

}


/* SEPARADOR */

.separador {

    border-top: 1px solid rgba(255,255,255,.15);

    margin: 10px 15px;

}


/* =========================================
   CONTENIDO
========================================= */

.contenido {

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

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow: 0 3px 10px rgba(0,0,0,.15);

}


.topbar-title {

    font-size: 21px;

}


.topbar-admin {

    font-size: 16px;

}


/* =========================================
   PRINCIPAL
========================================= */

.main {

    padding: 35px;

}


/* =========================================
   BIENVENIDA
========================================= */

.titulo {

    color: #0d47a1;

    font-size: 32px;

    font-weight: bold;

    margin-bottom: 20px;

}


.bienvenida {

    background: #cfe2ff;

    border: 1px solid #9ec5fe;

    border-radius: 7px;

    padding: 18px;

    margin-bottom: 15px;

}


.bienvenida h4 {

    color: #084298;

    margin-bottom: 8px;

}


.bienvenida h2 {

    color: #084298;

    font-weight: bold;

}


/* =========================================
   FECHA
========================================= */

.fecha {

    text-align: right;

    margin-bottom: 20px;

    color: #333;

}


.fecha strong {

    color: #222;

}


/* =========================================
   TARJETAS ESTADÍSTICAS
========================================= */

.stat-card {

    background: white;

    border-radius: 18px;

    padding: 30px 20px;

    text-align: center;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

    height: 100%;

    transition: .2s;

}


.stat-card:hover {

    transform: translateY(-4px);

}


.stat-icon {

    font-size: 65px;

    margin-bottom: 10px;

}


.stat-number {

    font-size: 45px;

    font-weight: bold;

    color: #0d6efd;

}


.stat-title {

    font-size: 17px;

    color: #555;

}


/* =========================================
   ESTADO
========================================= */

.estado-card {

    background: white;

    border-radius: 18px;

    padding: 30px 20px;

    text-align: center;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

    height: 100%;

}


.estado-icon {

    font-size: 65px;

}


.estado-abierta {

    color: #198754;

}


.estado-cerrada {

    color: #dc3545;

}


/* =========================================
   TARJETA INFORMACIÓN
========================================= */

.info-card {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.info-card h3 {

    color: #0d47a1;

    margin-bottom: 20px;

}


.info-row {

    display: flex;

    justify-content: space-between;

    padding: 12px 5px;

    border-bottom: 1px solid #ddd;

}


.info-row:last-child {

    border-bottom: none;

}


/* =========================================
   ACCESOS RÁPIDOS
========================================= */

.accesos {

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow: 0 5px 18px rgba(0,0,0,.10);

}


.acceso {

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    min-height: 125px;

    border-radius: 10px;

    color: white;

    text-decoration: none;

    font-weight: bold;

    transition: .2s;

}


.acceso:hover {

    transform: translateY(-3px);

    color: white;

}


.acceso i {

    font-size: 32px;

    margin-bottom: 10px;

}


.azul {

    background: #0d6efd;

}


.verde {

    background: #198754;

}


.amarillo {

    background: #ffc107;

    color: #000;

}


.amarillo:hover {

    color: #000;

}


.celeste {

    background: #0dcaf0;

    color: #000;

}


.celeste:hover {

    color: #000;

}


.rojo {

    background: #dc3545;

}


.negro {

    background: #212529;

}


/* =========================================
   FOOTER
========================================= */

.footer {

    text-align: center;

    margin-top: 45px;

    padding: 25px;

    color: #667085;

    border-top: 1px solid #ccc;

}


/* =========================================
   MÓVIL
========================================= */

@media(max-width:900px){

    .sidebar {

        width: 210px;

    }

    .contenido {

        margin-left: 210px;

    }

}


@media(max-width:700px){

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }

    .contenido {

        margin-left: 0;

    }

    .main {

        padding: 20px;

    }

}

</style>

</head>


<body>


<!-- =========================================
     SIDEBAR
========================================= -->

<div class="sidebar">


<div class="logo">

<span class="logo-icon">

📦

</span>

VOTACIONES

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


<a href="jurado.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>


<!-- =========================================
     NUEVO BOTÓN EXPORTAR EXCEL
========================================= -->

<a href="exportar_estudiantes.php">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

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


<div class="separador"></div>


<a href="abrir_eleccion.php">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a href="cerrar_eleccion.php">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<div class="separador"></div>


<a
href="logout.php"
onclick="return confirm('¿Desea cerrar sesión?');">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>


</div>

</div>


<!-- =========================================
     CONTENIDO
========================================= -->

<div class="contenido">


<!-- TOPBAR -->

<div class="topbar">

<div class="topbar-title">

🎓 Sistema de Votaciones Escolares

</div>


<div class="topbar-admin">

Administrador

</div>

</div>


<!-- MAIN -->

<div class="main">


<!-- =========================================
     TÍTULO
========================================= -->

<div class="titulo">

Bienvenido, Administrador 👋

</div>


<!-- =========================================
     BIENVENIDA
========================================= -->

<div class="bienvenida">

<h4>

👋 ¡Bienvenido al Panel de Administración!

</h4>

<h2>

Administre estudiantes, jurados, candidatos,
votaciones y resultados desde un solo lugar.

</h2>

</div>


<p class="text-muted">

Panel de Administración del Sistema de Votaciones Escolares

</p>


<!-- =========================================
     FECHA
========================================= -->

<div class="fecha">

📅 <strong>Fecha:</strong>

<?php echo $fechaActual; ?>

&nbsp;&nbsp;&nbsp;

🕐 <strong>Hora:</strong>

<?php echo $horaActual; ?>

</div>


<!-- =========================================
     ESTADÍSTICAS
========================================= -->

<div class="row g-4 mb-4">


<!-- ESTUDIANTES -->

<div class="col-xl-3 col-md-6">

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

<div class="col-xl-3 col-md-6">

<div class="stat-card">

<div class="stat-icon">

⚖️

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

<div class="col-xl-3 col-md-6">

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

<div class="col-xl-3 col-md-6">

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
     INFORMACIÓN Y ESTADO
========================================= -->

<div class="row g-4 mb-4">


<div class="col-lg-8">

<div class="info-card">

<h3>

<i class="bi bi-info-circle-fill"></i>

Información del Sistema

</h3>


<div class="info-row">

<strong>Administrador</strong>

<span>

<?php

echo htmlspecialchars(
    $_SESSION['nombre']
);

?>

</span>

</div>


<div class="info-row">

<strong>Fecha</strong>

<span>

<?php echo $fechaActual; ?>

</span>

</div>


<div class="info-row">

<strong>Hora</strong>

<span>

<?php echo $horaActual; ?>

</span>

</div>


<div class="info-row">

<strong>Elección actual</strong>

<span>

<?php

echo htmlspecialchars(
    $nombreEleccion
);

?>

</span>

</div>


<div class="info-row">

<strong>Estado de la elección</strong>

<span>


<?php if ($estadoEleccion == "abierta") { ?>

<span class="badge bg-success fs-6">

🟢 Abierta

</span>

<?php } else { ?>

<span class="badge bg-danger fs-6">

🔴 Cerrada

</span>

<?php } ?>


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


<!-- ESTADO -->

<div class="col-lg-4">

<div class="estado-card">


<?php if ($estadoEleccion == "abierta") { ?>

<div class="estado-icon estado-abierta">

🟢

</div>

<h3>

Elección Abierta

</h3>

<p class="text-muted">

Los estudiantes pueden votar actualmente.

</p>


<a
href="cerrar_eleccion.php"
class="btn btn-danger">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<?php } else { ?>


<div class="estado-icon estado-cerrada">

🔴

</div>

<h3>

Elección Cerrada

</h3>

<p class="text-muted">

Actualmente no se pueden registrar votos.

</p>


<a
href="abrir_eleccion.php"
class="btn btn-success">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<?php } ?>


</div>

</div>


</div>


<!-- =========================================
     ACCESOS RÁPIDOS
========================================= -->

<div class="accesos">


<h2 class="text-center mb-4">

⚡ Accesos Rápidos

</h2>


<div class="row g-3">


<!-- ESTUDIANTES -->

<div class="col-lg-4 col-md-6">

<a
href="estudiantes.php"
class="acceso azul">

<i class="bi bi-people-fill"></i>

Gestionar Estudiantes

</a>

</div>


<!-- JURADOS -->

<div class="col-lg-4 col-md-6">

<a
href="jurado.php"
class="acceso verde">

<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados

</a>

</div>


<!-- EXPORTAR EXCEL -->

<div class="col-lg-4 col-md-6">

<a
href="exportar_estudiantes.php"
class="acceso verde">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>

</div>


<!-- CANDIDATOS -->

<div class="col-lg-4 col-md-6">

<a
href="candidatos.php"
class="acceso celeste">

<i class="bi bi-person-vcard-fill"></i>

Gestionar Candidatos

</a>

</div>


<!-- RESULTADOS -->

<div class="col-lg-4 col-md-6">

<a
href="resultados.php"
class="acceso amarillo">

<i class="bi bi-trophy-fill"></i>

Ver Resultados

</a>

</div>


<!-- GRÁFICAS -->

<div class="col-lg-4 col-md-6">

<a
href="graficas.php"
class="acceso celeste">

<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas

</a>

</div>


<!-- ELECCIONES -->

<div class="col-lg-4 col-md-6">

<a
href="elecciones.php"
class="acceso azul">

<i class="bi bi-calendar-event-fill"></i>

Gestionar Elecciones

</a>

</div>


<!-- ABRIR ELECCIÓN -->

<div class="col-lg-4 col-md-6">

<a
href="abrir_eleccion.php"
class="acceso verde">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>

</div>


<!-- CERRAR ELECCIÓN -->

<div class="col-lg-4 col-md-6">

<a
href="cerrar_eleccion.php"
class="acceso rojo">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>

</div>


<!-- CERRAR SESIÓN -->

<div class="col-lg-4 col-md-6">

<a
href="logout.php"
class="acceso negro">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>

</div>


</div>

</div>


<!-- =========================================
     FOOTER
========================================= -->

<div class="footer">

<strong>

Sistema de Votaciones Escolares v2.0

</strong>

<br><br>

Desarrollado por

<strong>

Juan David Otero Cantor

</strong>

<br><br>

© <?php echo date("Y"); ?>

Todos los derechos reservados.

</div>


</div>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
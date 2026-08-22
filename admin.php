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

include("config/conexion.php");


/* =========================================
   MENSAJES
========================================= */

$mensaje = "";
$tipoMensaje = "";

if (isset($_GET['cerrada'])) {
    $mensaje = "La elección fue cerrada correctamente.";
    $tipoMensaje = "success";
}

if (isset($_GET['abierta'])) {
    $mensaje = "La elección fue abierta correctamente.";
    $tipoMensaje = "success";
}

if (isset($_GET['error'])) {

    $tipoMensaje = "danger";

    if ($_GET['error'] == "no_eleccion") {
        $mensaje = "No existe ninguna elección registrada.";
    }

    if ($_GET['error'] == "cerrar") {
        $mensaje = "No se pudo cerrar la elección.";
    }

    if ($_GET['error'] == "abrir") {
        $mensaje = "No se pudo abrir la elección.";
    }
}


/* =========================================
   CONTAR ESTUDIANTES
========================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='estudiante'
");

$totalEstudiantes = 0;

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalEstudiantes = $fila['total'];
}


/* =========================================
   CONTAR JURADOS
========================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol='jurado'
");

$totalJurados = 0;

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalJurados = $fila['total'];
}


/* =========================================
   CONTAR CANDIDATOS
========================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

$totalCandidatos = 0;

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalCandidatos = $fila['total'];
}


/* =========================================
   CONTAR VOTOS
========================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

$totalVotos = 0;

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalVotos = $fila['total'];
}


/* =========================================
   ESTADO DE ELECCIÓN
========================================= */

$estadoEleccion = "cerrada";
$nombreEleccion = "Sin elección";

$resultado = $conn->query("
    SELECT id, nombre, estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

if ($resultado && $resultado->num_rows > 0) {

    $eleccion = $resultado->fetch_assoc();

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

<title>Panel Administrador</title>


<!-- Bootstrap -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- Bootstrap Icons -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

* {
    box-sizing:border-box;
}

body {

    margin:0;

    background:#eef3f9;

    font-family:
    Arial,
    Helvetica,
    sans-serif;

}


/* =========================================
   SIDEBAR
========================================= */

.sidebar {

    position:fixed;

    left:0;

    top:0;

    width:250px;

    height:100vh;

    background:#1453a3;

    color:white;

    overflow-y:auto;

    z-index:1000;

}


.logo {

    text-align:center;

    padding:25px 10px;

    border-bottom:
    1px solid rgba(255,255,255,.2);

}


.logo-icon {

    font-size:45px;

}


.logo h1 {

    font-size:30px;

    margin:10px 0 0;

    font-weight:bold;

}


.menu {

    padding:15px 0;

}


.menu a {

    display:flex;

    align-items:center;

    gap:10px;

    color:white;

    text-decoration:none;

    padding:14px 20px;

    font-size:16px;

    transition:.2s;

}


.menu a:hover {

    background:#0d4388;

}


.menu a i {

    font-size:19px;

    width:22px;

}


.menu-separador {

    border-top:
    1px solid rgba(255,255,255,.2);

    margin:
    10px 15px;

}


/* =========================================
   CONTENIDO
========================================= */

.main {

    margin-left:250px;

    min-height:100vh;

}


/* =========================================
   TOPBAR
========================================= */

.topbar {

    height:70px;

    background:#1473ed;

    color:white;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;

    box-shadow:
    0 3px 10px rgba(0,0,0,.15);

}


.topbar h4 {

    margin:0;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenido {

    padding:30px;

}


/* =========================================
   BIENVENIDA
========================================= */

.titulo {

    color:#1453a3;

    font-size:32px;

    font-weight:bold;

}


.bienvenida {

    background:#cfe2ff;

    border:
    1px solid #9ec5fe;

    border-radius:6px;

    padding:20px;

    color:#084298;

}


.bienvenida h2 {

    font-size:29px;

    font-weight:bold;

}


/* =========================================
   ESTADÍSTICAS
========================================= */

.estadisticas {

    display:grid;

    grid-template-columns:
    repeat(4,1fr);

    gap:22px;

    margin-top:30px;

}


.stat {

    background:white;

    border-radius:18px;

    padding:30px 20px;

    text-align:center;

    box-shadow:
    0 6px 18px rgba(0,0,0,.10);

}


.stat-icon {

    font-size:65px;

    margin-bottom:10px;

}


.stat-numero {

    font-size:45px;

    color:#1473ed;

    font-weight:bold;

}


.stat-texto {

    font-size:17px;

    color:#555;

}


/* =========================================
   ESTADO
========================================= */

.estado-card {

    margin-top:25px;

    background:white;

    padding:25px;

    border-radius:15px;

    box-shadow:
    0 6px 18px rgba(0,0,0,.10);

}


.estado-abierta {

    display:inline-block;

    background:#198754;

    color:white;

    padding:7px 18px;

    border-radius:7px;

    font-weight:bold;

}


.estado-cerrada {

    display:inline-block;

    background:#dc3545;

    color:white;

    padding:7px 18px;

    border-radius:7px;

    font-weight:bold;

}


/* =========================================
   ACCESOS RÁPIDOS
========================================= */

.accesos {

    margin-top:30px;

    background:white;

    padding:30px;

    border-radius:18px;

    box-shadow:
    0 6px 20px rgba(0,0,0,.10);

}


.accesos h2 {

    text-align:center;

    margin-bottom:25px;

}


.acceso-grid {

    display:grid;

    grid-template-columns:
    repeat(3,1fr);

    gap:16px;

}


.acceso {

    min-height:125px;

    border-radius:10px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    color:white;

    text-decoration:none;

    font-weight:bold;

    font-size:16px;

    transition:.2s;

}


.acceso:hover {

    transform:translateY(-3px);

    color:white;

}


.acceso i {

    font-size:40px;

    margin-bottom:10px;

}


.azul {
    background:#1473ed;
}

.verde {
    background:#198754;
}

.celeste {
    background:#16c1df;
}

.amarillo {
    background:#ffc107;
    color:#111;
}

.rojo {
    background:#dc3545;
}

.oscuro {
    background:#212529;
}


/* =========================================
   FOOTER
========================================= */

.footer {

    text-align:center;

    padding:35px;

    margin-top:40px;

    color:#65758b;

}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:1000px) {

    .estadisticas {

        grid-template-columns:
        repeat(2,1fr);

    }

    .acceso-grid {

        grid-template-columns:
        repeat(2,1fr);

    }

}


@media(max-width:700px) {

    .sidebar {

        position:relative;

        width:100%;

        height:auto;

    }

    .main {

        margin-left:0;

    }

    .estadisticas {

        grid-template-columns:1fr;

    }

    .acceso-grid {

        grid-template-columns:1fr;

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

<div class="logo-icon">
📦
</div>

<h1>VOTACIONES</h1>

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


<a href="exportar_estudiantes.php">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a href="importar_estudiantes.php">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

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


<div class="menu-separador"></div>


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
     CONTENIDO PRINCIPAL
========================================= -->

<div class="main">


<!-- TOPBAR -->

<div class="topbar">

<h4>

🎓 Sistema de Votaciones Escolares

</h4>

<span>

Administrador

</span>

</div>


<div class="contenido">


<!-- =========================================
     MENSAJES
========================================= -->

<?php if ($mensaje != "") { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     BIENVENIDA
========================================= -->

<h1 class="titulo">

Bienvenido, Administrador 👋

</h1>


<div class="bienvenida">

<h4>

👋 ¡Bienvenido al Panel de Administración!

</h4>

<h2>

Administre estudiantes, jurados, candidatos,
votaciones y resultados desde un solo lugar.

</h2>

</div>


<p class="mt-3 text-secondary">

Panel de Administración del Sistema de Votaciones Escolares

</p>


<!-- =========================================
     ESTADÍSTICAS
========================================= -->

<div class="estadisticas">


<div class="stat">

<div class="stat-icon">
👨‍🎓
</div>

<div class="stat-numero">

<?php echo $totalEstudiantes; ?>

</div>

<div class="stat-texto">

Estudiantes Registrados

</div>

</div>


<div class="stat">

<div class="stat-icon">
⚖️
</div>

<div class="stat-numero">

<?php echo $totalJurados; ?>

</div>

<div class="stat-texto">

Jurados Registrados

</div>

</div>


<div class="stat">

<div class="stat-icon">
📦
</div>

<div class="stat-numero">

<?php echo $totalCandidatos; ?>

</div>

<div class="stat-texto">

Candidatos Inscritos

</div>

</div>


<div class="stat">

<div class="stat-icon">
☑️
</div>

<div class="stat-numero">

<?php echo $totalVotos; ?>

</div>

<div class="stat-texto">

Votos Registrados

</div>

</div>

</div>


<!-- =========================================
     ESTADO ELECCIÓN
========================================= -->

<div class="estado-card">

<h3>

<i class="bi bi-info-circle-fill text-primary"></i>

Estado de la elección

</h3>

<hr>

<p>

<strong>Elección:</strong>

<?php echo htmlspecialchars($nombreEleccion); ?>

</p>


<p>

<strong>Estado:</strong>

<?php if ($estadoEleccion === 'abierta') { ?>

<span class="estado-abierta">

🟢 Abierta

</span>

<?php } else { ?>

<span class="estado-cerrada">

🔴 Cerrada

</span>

<?php } ?>

</p>

</div>


<!-- =========================================
     ACCESOS RÁPIDOS
========================================= -->

<div class="accesos">

<h2>

⚡ Accesos Rápidos

</h2>


<div class="acceso-grid">


<a
href="estudiantes.php"
class="acceso azul">

<i class="bi bi-people-fill"></i>

Gestionar Estudiantes

</a>


<a
href="jurado.php"
class="acceso verde">

<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados

</a>


<a
href="exportar_estudiantes.php"
class="acceso verde">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a
href="importar_estudiantes.php"
class="acceso verde">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<a
href="candidatos.php"
class="acceso celeste">

<i class="bi bi-person-vcard-fill"></i>

Gestionar Candidatos

</a>


<a
href="resultados.php"
class="acceso amarillo">

<i class="bi bi-trophy-fill"></i>

Ver Resultados

</a>


<a
href="graficas.php"
class="acceso celeste">

<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas

</a>


<a
href="elecciones.php"
class="acceso azul">

<i class="bi bi-calendar-event-fill"></i>

Gestionar Elecciones

</a>


<a
href="abrir_eleccion.php"
class="acceso verde">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a
href="cerrar_eleccion.php"
class="acceso rojo">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<a
href="logout.php"
class="acceso oscuro">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>


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

Desarrollado para el proyecto de grado

<br><br>

© 2026 Todos los derechos reservados.

</div>


</div>

</div>


</body>

</html>
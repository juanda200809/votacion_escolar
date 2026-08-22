<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
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
   INFORMACIÓN DEL ADMINISTRADOR
========================================= */

$nombreAdministrador =
    $_SESSION['nombre'] ?? 'Administrador';


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

    $totalEstudiantes = (int)$fila['total'];

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

    $totalJurados = (int)$fila['total'];

}


/* CANDIDATOS */

$consultaCandidatos = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

$totalCandidatos = 0;

if ($consultaCandidatos) {

    $fila = $consultaCandidatos->fetch_assoc();

    $totalCandidatos = (int)$fila['total'];

}


/* VOTOS */

$consultaVotos = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

$totalVotos = 0;

if ($consultaVotos) {

    $fila = $consultaVotos->fetch_assoc();

    $totalVotos = (int)$fila['total'];

}


/* =========================================
   OBTENER ÚLTIMA ELECCIÓN
========================================= */

$consultaEleccion = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


$idEleccion = 0;
$nombreEleccion = "Sin elección registrada";
$descripcionEleccion = "";
$fechaInicio = "";
$fechaFin = "";
$estadoEleccion = "cerrada";


if (
    $consultaEleccion &&
    $consultaEleccion->num_rows > 0
) {

    $eleccion = $consultaEleccion->fetch_assoc();

    $idEleccion =
        (int)$eleccion['id'];

    $nombreEleccion =
        $eleccion['nombre'];

    $descripcionEleccion =
        $eleccion['descripcion'];

    $fechaInicio =
        $eleccion['fecha_inicio'];

    $fechaFin =
        $eleccion['fecha_fin'];

    $estadoEleccion =
        $eleccion['estado'];

}


/* =========================================
   DATOS PARA MOSTRAR ESTADO
========================================= */

if ($estadoEleccion === 'abierta') {

    $textoEstado = "Abierta";

    $claseEstado = "estado-abierta";

    $iconoEstado = "bi-unlock-fill";

} else {

    $textoEstado = "Cerrada";

    $claseEstado = "estado-cerrada";

    $iconoEstado = "bi-lock-fill";

}


/* =========================================
   FECHA Y HORA
========================================= */

$fechaActual =
    date("d/m/Y");

$horaActual =
    date("h:i A");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>
Panel Administrador - Sistema de Votaciones
</title>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =========================================
     ICONOS
========================================= -->

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

    background:#0d47a1;

    color:white;

    overflow-y:auto;

    z-index:1000;

}


/* LOGO */

.logo {

    padding:28px 20px;

    text-align:center;

    border-bottom:
        1px solid
        rgba(255,255,255,.2);

}


.logo-icon {

    font-size:45px;

    display:block;

    margin-bottom:5px;

}


.logo-text {

    font-size:30px;

    font-weight:700;

}


/* MENÚ */

.menu {

    padding:15px 0;

}


.menu a {

    display:flex;

    align-items:center;

    gap:12px;

    padding:14px 20px;

    color:white;

    text-decoration:none;

    font-size:16px;

    transition:.2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

    padding-left:25px;

}


.menu a i {

    font-size:19px;

    width:22px;

}


.menu-separador {

    height:1px;

    background:
        rgba(255,255,255,.2);

    margin:10px 15px;

}


/* =========================================
   CONTENIDO
========================================= */

.contenido {

    margin-left:250px;

    min-height:100vh;

}


/* =========================================
   BARRA SUPERIOR
========================================= */

.topbar {

    height:70px;

    background:#0d6efd;

    color:white;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;

    box-shadow:
        0 3px 12px rgba(0,0,0,.15);

}


.topbar-title {

    font-size:21px;

    font-weight:500;

}


.topbar-admin {

    font-size:16px;

}


/* =========================================
   CONTENEDOR PRINCIPAL
========================================= */

.principal {

    padding:30px;

}


/* =========================================
   BIENVENIDA
========================================= */

.titulo-principal {

    color:#0d47a1;

    font-size:34px;

    font-weight:700;

    margin-bottom:20px;

}


.descripcion {

    color:#4f5d73;

    margin-top:20px;

    margin-bottom:25px;

}


/* =========================================
   TARJETA BIENVENIDA
========================================= */

.bienvenida {

    background:#cfe2ff;

    border:1px solid #9ec5fe;

    border-radius:8px;

    padding:25px;

    color:#084298;

}


.bienvenida-titulo {

    font-size:25px;

    font-weight:700;

}


.bienvenida-texto {

    font-size:29px;

    font-weight:700;

    margin-top:5px;

}


/* =========================================
   FECHA
========================================= */

.fecha-hora {

    text-align:right;

    font-size:16px;

    margin-top:25px;

    color:#111;

}


/* =========================================
   TARJETAS ESTADÍSTICAS
========================================= */

.estadisticas {

    margin-top:25px;

}


.stat-card {

    background:white;

    border-radius:18px;

    padding:30px 20px;

    text-align:center;

    height:100%;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

    transition:.2s;

}


.stat-card:hover {

    transform:
        translateY(-4px);

}


.stat-icon {

    font-size:75px;

    line-height:1;

    margin-bottom:15px;

}


.stat-number {

    color:#0d6efd;

    font-size:44px;

    font-weight:700;

}


.stat-title {

    font-size:18px;

    color:#555;

}


/* =========================================
   ESTADO ELECCIÓN
========================================= */

.estado-card {

    background:white;

    border-radius:18px;

    padding:30px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

    height:100%;

}


.estado-titulo {

    color:#0d47a1;

    font-size:27px;

    font-weight:700;

}


.estado-abierta {

    display:inline-block;

    background:#198754;

    color:white;

    padding:8px 15px;

    border-radius:8px;

    font-weight:700;

}


.estado-cerrada {

    display:inline-block;

    background:#dc3545;

    color:white;

    padding:8px 15px;

    border-radius:8px;

    font-weight:700;

}


/* =========================================
   ACCESOS RÁPIDOS
========================================= */

.accesos {

    background:white;

    border-radius:20px;

    padding:30px;

    margin-top:30px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


.accesos-titulo {

    text-align:center;

    font-size:30px;

    margin-bottom:25px;

}


.acceso {

    min-height:125px;

    border-radius:10px;

    display:flex;

    flex-direction:column;

    justify-content:center;

    align-items:center;

    text-align:center;

    color:white;

    text-decoration:none;

    font-size:16px;

    font-weight:700;

    transition:.2s;

    padding:15px;

}


.acceso:hover {

    color:white;

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 15px rgba(0,0,0,.18);

}


.acceso i {

    font-size:34px;

    margin-bottom:12px;

}


/* COLORES */

.azul {

    background:#0d6efd;

}


.verde {

    background:#198754;

}


.celeste {

    background:#13bddd;

    color:#000;

}


.amarillo {

    background:#ffc107;

    color:#000;

}


.rojo {

    background:#dc3545;

}


.gris {

    background:#212529;

}


/* =========================================
   INFORMACIÓN ELECCIÓN
========================================= */

.info-card {

    background:white;

    border-radius:18px;

    padding:30px;

    margin-top:30px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


.info-titulo {

    color:#0d47a1;

    font-size:28px;

    font-weight:700;

    margin-bottom:20px;

}


.info-row {

    display:flex;

    justify-content:space-between;

    border-bottom:
        1px solid #ddd;

    padding:12px 5px;

}


.info-row:last-child {

    border-bottom:none;

}


/* =========================================
   RESUMEN
========================================= */

.resumen {

    background:white;

    border-radius:18px;

    padding:30px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


.resumen h3 {

    color:#198754;

}


.resumen-item {

    display:flex;

    justify-content:space-between;

    padding:12px;

    border:1px solid #ddd;

    border-radius:8px;

    margin-bottom:8px;

}


/* =========================================
   FOOTER
========================================= */

.footer {

    margin-top:50px;

    padding:30px;

    text-align:center;

    border-top:
        1px solid #ccd3dc;

    color:#667085;

}


/* =========================================
   RESPONSIVE
========================================= */

@media (max-width:900px) {

    .sidebar {

        width:210px;

    }

    .contenido {

        margin-left:210px;

    }

    .bienvenida-texto {

        font-size:23px;

    }

}


@media (max-width:700px) {

    .sidebar {

        position:relative;

        width:100%;

        height:auto;

    }

    .contenido {

        margin-left:0;

    }

    .topbar {

        padding:15px;

    }

    .principal {

        padding:15px;

    }

    .fecha-hora {

        text-align:left;

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

<div class="logo-text">

VOTACIONES

</div>

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


<a href="crear_jurado.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>


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


<div class="menu-separador"></div>


<a href="abrir_eleccion.php">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a href="cerrar_eleccion.php">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<div class="menu-separador"></div>


<a href="logout.php">

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


<!-- PRINCIPAL -->

<div class="principal">


<!-- =========================================
     BIENVENIDA
========================================= -->

<h1 class="titulo-principal">

Bienvenido,
<?php echo htmlspecialchars(
    $nombreAdministrador
); ?>

👋

</h1>


<div class="bienvenida">


<div class="bienvenida-titulo">

👋 ¡Bienvenido al Panel de Administración!

</div>


<div class="bienvenida-texto">

Administre estudiantes, jurados,
candidatos, votaciones y resultados
desde un solo lugar.

</div>

</div>


<p class="descripcion">

Panel de Administración del Sistema de
Votaciones Escolares

</p>


<div class="fecha-hora">

📅 <strong>Fecha:</strong>

<?php echo $fechaActual; ?>


&nbsp;&nbsp;&nbsp;


🕐 <strong>Hora:</strong>

<?php echo $horaActual; ?>

</div>


<!-- =========================================
     ESTADÍSTICAS
========================================= -->

<div class="row g-4 estadisticas">


<!-- ESTUDIANTES -->

<div class="col-xl-3 col-md-6">

<div class="stat-card">


<div class="stat-icon">

🧑‍🎓

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
     ESTADO ELECCIÓN + RESUMEN
========================================= -->

<div class="row g-4 mt-1">


<!-- ESTADO -->

<div class="col-lg-8">


<div class="estado-card">


<div class="estado-titulo mb-3">

<i class="bi bi-info-circle-fill"></i>

Información del Sistema

</div>


<div class="info-row">

<strong>

Administrador

</strong>

<span>

<?php echo htmlspecialchars(
    $nombreAdministrador
); ?>

</span>

</div>


<div class="info-row">

<strong>

Fecha

</strong>

<span>

<?php echo $fechaActual; ?>

</span>

</div>


<div class="info-row">

<strong>

Hora

</strong>

<span>

<?php echo $horaActual; ?>

</span>

</div>


<div class="info-row">

<strong>

Elección actual

</strong>

<span>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</span>

</div>


<div class="info-row">

<strong>

Estado de la elección

</strong>

<span>

<span class="<?php echo $claseEstado; ?>">

<i class="bi <?php echo $iconoEstado; ?>"></i>

<?php echo $textoEstado; ?>

</span>

</span>

</div>


<div class="info-row">

<strong>

Versión

</strong>

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

<i class="bi bi-clipboard-data-fill"></i>

Resumen

</h3>


<div class="resumen-item">

<span>

Estudiantes

</span>

<span class="badge bg-primary">

<?php echo $totalEstudiantes; ?>

</span>

</div>


<div class="resumen-item">

<span>

Jurados

</span>

<span class="badge bg-success">

<?php echo $totalJurados; ?>

</span>

</div>


<div class="resumen-item">

<span>

Candidatos

</span>

<span class="badge bg-success">

<?php echo $totalCandidatos; ?>

</span>

</div>


<div class="resumen-item">

<span>

Votos

</span>

<span class="badge bg-warning text-dark">

<?php echo $totalVotos; ?>

</span>

</div>


<div class="resumen-item">

<span>

Estado

</span>

<span>

<span class="<?php echo $claseEstado; ?>">

<?php echo $textoEstado; ?>

</span>

</span>

</div>


</div>

</div>


</div>


<!-- =========================================
     ACCESOS RÁPIDOS
========================================= -->

<div class="accesos">


<div class="accesos-titulo">

⚡ Accesos Rápidos

</div>


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
href="crear_jurado.php"
class="acceso verde">

<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados

</a>

</div>


<!-- EXPORTAR -->

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


<!-- ABRIR -->

<div class="col-lg-4 col-md-6">

<a
href="abrir_eleccion.php"
class="acceso verde"
onclick="
return confirm(
'¿Desea abrir la elección?'
);
">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>

</div>


<!-- CERRAR -->

<div class="col-lg-4 col-md-6">

<a
href="cerrar_eleccion.php"
class="acceso rojo"
onclick="
return confirm(
'¿Está seguro de cerrar la elección?'
);
">

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>

</div>


<!-- CERRAR SESIÓN -->

<div class="col-lg-4 col-md-6">

<a
href="logout.php"
class="acceso gris">

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>

</div>


</div>

</div>


<!-- =========================================
     INFORMACIÓN DE ELECCIÓN
========================================= -->

<div class="info-card">


<div class="info-titulo">

<i class="bi bi-calendar-check-fill"></i>

Información de la Elección

</div>


<div class="info-row">

<strong>

Nombre

</strong>

<span>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</span>

</div>


<div class="info-row">

<strong>

Descripción

</strong>

<span>

<?php

echo htmlspecialchars(
    $descripcionEleccion !== ''
        ? $descripcionEleccion
        : 'Sin descripción'
);

?>

</span>

</div>


<div class="info-row">

<strong>

Fecha de inicio

</strong>

<span>

<?php

if ($fechaInicio !== '') {

    echo htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime($fechaInicio)
        )
    );

} else {

    echo "No definida";

}

?>

</span>

</div>


<div class="info-row">

<strong>

Fecha de finalización

</strong>

<span>

<?php

if ($fechaFin !== '') {

    echo htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime($fechaFin)
        )
    );

} else {

    echo "No definida";

}

?>

</span>

</div>


<div class="info-row">

<strong>

Estado

</strong>

<span>

<span class="<?php echo $claseEstado; ?>">

<i class="bi <?php echo $iconoEstado; ?>"></i>

<?php echo $textoEstado; ?>

</span>

</span>

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


Desarrollado para el Proyecto de Grado


<br><br>


© <?php echo date("Y"); ?>

Todos los derechos reservados.

</div>


</div>

</div>


<!-- BOOTSTRAP JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
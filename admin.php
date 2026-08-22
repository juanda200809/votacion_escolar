<?php

session_start();

include("config/conexion.php");


/* =====================================================
   VERIFICAR SESIÓN DE ADMINISTRADOR
===================================================== */

if (!isset($_SESSION['id']) || !isset($_SESSION['rol'])) {

    header("Location: login.php");
    exit();

}


/* =====================================================
   NORMALIZAR ROL
===================================================== */

$rolSesion = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


/* =====================================================
   COMPROBAR ROL
===================================================== */

if ($rolSesion !== "administrador") {

    /*
     * Si la sesión no corresponde a un administrador,
     * no permitimos entrar al panel.
     */

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();

}


/* =====================================================
   INFORMACIÓN DEL ADMINISTRADOR
===================================================== */

$nombreAdministrador =
    $_SESSION['nombre'] ?? 'Administrador';


/* =====================================================
   MENSAJES
===================================================== */

$mensaje = "";
$tipoMensaje = "";


if (isset($_GET['cerrada'])) {

    $mensaje =
        "La elección fue cerrada correctamente.";

    $tipoMensaje = "success";

}


if (isset($_GET['abierta'])) {

    $mensaje =
        "La elección fue abierta correctamente.";

    $tipoMensaje = "success";

}


if (isset($_GET['error'])) {

    $tipoMensaje = "danger";


    if ($_GET['error'] === "no_eleccion") {

        $mensaje =
            "No existe ninguna elección registrada.";

    }

    elseif ($_GET['error'] === "cerrar") {

        $mensaje =
            "No se pudo cerrar la elección.";

    }

    elseif ($_GET['error'] === "abrir") {

        $mensaje =
            "No se pudo abrir la elección.";

    }

    elseif ($_GET['error'] === "acceso") {

        $mensaje =
            "No tiene permisos para realizar esta acción.";

    }

}


/* =====================================================
   CONTAR ESTUDIANTES
===================================================== */

$totalEstudiantes = 0;


$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'estudiante'
");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];

}


/* =====================================================
   CONTAR JURADOS
===================================================== */

$totalJurados = 0;


$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'jurado'
");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalJurados =
        (int)$fila['total'];

}


/* =====================================================
   CONTAR CANDIDATOS
===================================================== */

$totalCandidatos = 0;


$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalCandidatos =
        (int)$fila['total'];

}


/* =====================================================
   CONTAR VOTOS
===================================================== */

$totalVotos = 0;


$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalVotos =
        (int)$fila['total'];

}


/* =====================================================
   OBTENER ÚLTIMA ELECCIÓN
===================================================== */

$idEleccion = 0;

$nombreEleccion =
    "Sin elección registrada";

$descripcionEleccion = "";

$fechaInicio = "";

$fechaFin = "";

$estadoEleccion =
    "cerrada";


$resultado = $conn->query("
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


if (
    $resultado &&
    $resultado->num_rows > 0
) {

    $eleccion =
        $resultado->fetch_assoc();


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
        strtolower(
            trim(
                $eleccion['estado']
            )
        );

}


/* =====================================================
   NORMALIZAR ESTADO
===================================================== */

if ($estadoEleccion === "abierta") {

    $textoEstado =
        "Abierta";

    $claseEstado =
        "estado-abierta";

} else {

    $textoEstado =
        "Cerrada";

    $claseEstado =
        "estado-cerrada";

}


/* =====================================================
   FECHA Y HORA
===================================================== */

$fechaActual =
    date("d/m/Y");

$horaActual =
    date("h:i A");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Panel de Administración
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

/* =====================================================
   GENERAL
===================================================== */

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


/* =====================================================
   SIDEBAR
===================================================== */

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
        1px solid
        rgba(255,255,255,.2);

}


.logo-icon {

    font-size:45px;

}


.logo h1 {

    margin:8px 0 0;

    font-size:30px;

    font-weight:bold;

}


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

    background:#0d4388;

}


.menu a i {

    width:22px;

    font-size:19px;

}


.menu-separador {

    height:1px;

    background:
        rgba(255,255,255,.2);

    margin:10px 15px;

}


/* =====================================================
   CONTENIDO PRINCIPAL
===================================================== */

.main {

    margin-left:250px;

    min-height:100vh;

}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    height:70px;

    background:#1473ed;

    color:white;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.topbar h4 {

    margin:0;

}


/* =====================================================
   CONTENIDO
===================================================== */

.contenido {

    padding:30px;

}


/* =====================================================
   TÍTULO
===================================================== */

.titulo {

    color:#1453a3;

    font-size:32px;

    font-weight:bold;

}


/* =====================================================
   BIENVENIDA
===================================================== */

.bienvenida {

    background:#cfe2ff;

    border:
        1px solid #9ec5fe;

    border-radius:8px;

    padding:22px;

    color:#084298;

}


.bienvenida h4 {

    font-size:22px;

    font-weight:bold;

}


.bienvenida h2 {

    font-size:27px;

    font-weight:bold;

    margin:8px 0 0;

}


/* =====================================================
   ESTADÍSTICAS
===================================================== */

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
        0 6px 18px
        rgba(0,0,0,.10);

    transition:.2s;

}


.stat:hover {

    transform:
        translateY(-4px);

}


.stat-icon {

    font-size:65px;

    margin-bottom:10px;

}


.stat-numero {

    color:#1473ed;

    font-size:45px;

    font-weight:bold;

}


.stat-texto {

    color:#555;

    font-size:17px;

}


/* =====================================================
   ESTADO
===================================================== */

.estado-card {

    background:white;

    margin-top:30px;

    padding:25px;

    border-radius:18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.estado-card h3 {

    color:#1453a3;

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


/* =====================================================
   ACCESOS RÁPIDOS
===================================================== */

.accesos {

    margin-top:30px;

    background:white;

    padding:30px;

    border-radius:18px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

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

    text-align:center;

    color:white;

    text-decoration:none;

    font-size:16px;

    font-weight:bold;

    padding:15px;

    transition:.2s;

}


.acceso:hover {

    transform:
        translateY(-3px);

    color:white;

    box-shadow:
        0 7px 15px
        rgba(0,0,0,.15);

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


.amarillo:hover {

    color:#111;

}


.rojo {

    background:#dc3545;

}


.oscuro {

    background:#212529;

}


/* =====================================================
   INFORMACIÓN ELECCIÓN
===================================================== */

.info-eleccion {

    margin-top:30px;

    background:white;

    padding:30px;

    border-radius:18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.info-eleccion h2 {

    color:#1453a3;

    margin-bottom:20px;

}


.info-row {

    display:flex;

    justify-content:space-between;

    gap:20px;

    padding:13px 5px;

    border-bottom:
        1px solid #ddd;

}


.info-row:last-child {

    border-bottom:none;

}


/* =====================================================
   FOOTER
===================================================== */

.footer {

    text-align:center;

    padding:35px;

    margin-top:40px;

    color:#65758b;

}


/* =====================================================
   RESPONSIVE
===================================================== */

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


    .info-row {

        flex-direction:column;

        gap:5px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     MENÚ LATERAL
===================================================== -->

<div class="sidebar">


<div class="logo">

<div class="logo-icon">
📦
</div>

<h1>
VOTACIONES
</h1>

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


<a href="jurados.php">

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


<a
href="abrir_eleccion.php"
onclick="return confirm('¿Desea abrir la elección?');">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a
href="cerrar_eleccion.php"
onclick="return confirm('¿Está seguro de cerrar la elección?');">

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


<!-- =====================================================
     PRINCIPAL
===================================================== -->

<div class="main">


<div class="topbar">

<h4>

🎓 Sistema de Votaciones Escolares

</h4>


<span>

<i class="bi bi-person-fill"></i>

Administrador

</span>

</div>


<div class="contenido">


<!-- MENSAJE -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- BIENVENIDA -->

<h1 class="titulo">

Bienvenido,

<?php echo htmlspecialchars(
    $nombreAdministrador
); ?>

👋

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


<p class="text-secondary mt-3">

Panel de Administración del Sistema de Votaciones Escolares

</p>


<div class="text-end mt-3">

📅 <strong>Fecha:</strong>

<?php echo $fechaActual; ?>

&nbsp;&nbsp;&nbsp;

🕐 <strong>Hora:</strong>

<?php echo $horaActual; ?>

</div>


<!-- ESTADÍSTICAS -->

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


<!-- ESTADO -->

<div class="estado-card">


<h3>

<i class="bi bi-info-circle-fill"></i>

Estado de la Elección

</h3>


<hr>


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
Estado
</strong>

<span>

<?php if ($estadoEleccion === "abierta") { ?>

<span class="estado-abierta">

🟢 Abierta

</span>

<?php } else { ?>

<span class="estado-cerrada">

🔴 Cerrada

</span>

<?php } ?>

</span>

</div>


</div>


<!-- ACCESOS -->

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
href="jurados.php"
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
class="acceso verde"
onclick="return confirm('¿Desea abrir la elección?');">

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a
href="cerrar_eleccion.php"
class="acceso rojo"
onclick="return confirm('¿Está seguro de cerrar la elección?');">

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


<!-- INFORMACIÓN ELECCIÓN -->

<div class="info-eleccion">


<h2>

<i class="bi bi-calendar-check-fill"></i>

Información de la Elección

</h2>


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

if ($descripcionEleccion !== "") {

    echo htmlspecialchars(
        $descripcionEleccion
    );

} else {

    echo "Sin descripción";

}

?>

</span>

</div>


<div class="info-row">

<strong>
Fecha de inicio
</strong>

<span>

<?php

if ($fechaInicio !== "") {

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

if ($fechaFin !== "") {

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

<?php if ($estadoEleccion === "abierta") { ?>

<span class="estado-abierta">

🟢 Abierta

</span>

<?php } else { ?>

<span class="estado-cerrada">

🔴 Cerrada

</span>

<?php } ?>

</span>

</div>


</div>


<!-- FOOTER -->

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


</body>

</html>
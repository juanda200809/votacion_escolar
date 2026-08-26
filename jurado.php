<?php

session_start();

require_once "config/conexion.php";


/* =========================================================
   SEGURIDAD Y CACHE
========================================================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {
    header("Location: login.php");
    exit();
}


$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {
    header("Location: login.php");
    exit();
}


$idJurado = (int)$_SESSION['id'];

$nombreJurado =
    $_SESSION['nombre'] ?? "Jurado";


/* =========================================================
   INICIAR VOTACIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['comenzar_votacion'])
) {

    $idEleccionPost =
        isset($_POST['id_eleccion'])
        ? (int)$_POST['id_eleccion']
        : 0;


    if ($idEleccionPost <= 0) {
        die("Elección no válida.");
    }


    /* -----------------------------------------------------
       COMPROBAR ELECCIÓN
    ----------------------------------------------------- */

    $stmt = $conn->prepare("
        SELECT
            id,
            estado
        FROM elecciones
        WHERE id = ?
        LIMIT 1
    ");


    if (!$stmt) {
        die(
            "Error al comprobar la elección: "
            . $conn->error
        );
    }


    $stmt->bind_param(
        "i",
        $idEleccionPost
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $eleccionPost =
        $resultado->fetch_assoc();


    $stmt->close();


    if (!$eleccionPost) {
        die(
            "La elección seleccionada no existe."
        );
    }


    if (
        strtolower(
            trim(
                (string)$eleccionPost['estado']
            )
        ) !== "abierta"
    ) {
        die(
            "La elección está cerrada."
        );
    }


    /* -----------------------------------------------------
       GUARDAR ELECCIÓN EN SESIÓN
    ----------------------------------------------------- */

    $_SESSION['eleccion_votante_id'] =
        $idEleccionPost;

    $_SESSION['eleccion_votando_id'] =
        $idEleccionPost;

    $_SESSION['id_eleccion_jurado'] =
        $idEleccionPost;


    /* -----------------------------------------------------
       LIMPIAR DATOS DEL ESTUDIANTE ANTERIOR
    ----------------------------------------------------- */

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['estudiante_jurado'],
        $_SESSION['votacion_en_curso']
    );


    /* -----------------------------------------------------
       IR A INGRESAR ESTUDIANTE
    ----------------------------------------------------- */

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();
}


/* =========================================================
   OBTENER ELECCIÓN ACTUAL
========================================================= */

$eleccion = null;


$stmtEleccion = $conn->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        estado,
        fecha_inicio,
        fecha_fin
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if ($stmtEleccion) {

    $stmtEleccion->execute();

    $resultadoEleccion =
        $stmtEleccion->get_result();


    if (
        $resultadoEleccion->num_rows > 0
    ) {

        $eleccion =
            $resultadoEleccion->fetch_assoc();

    }


    $stmtEleccion->close();
}


/* =========================================================
   DATOS DE LA ELECCIÓN
========================================================= */

$idEleccion = 0;

$nombreEleccion =
    "Sin elección disponible";

$descripcionEleccion =
    "No existe una elección registrada.";

$estadoEleccion =
    "cerrada";

$fechaInicio = "";

$fechaFin = "";


if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];


    $nombreEleccion =
        $eleccion['nombre']
        ??
        "Elección estudiantil";


    $descripcionEleccion =
        $eleccion['descripcion']
        ??
        "Proceso democrático institucional";


    $estadoEleccion =
        strtolower(
            trim(
                (string)(
                    $eleccion['estado']
                    ??
                    'cerrada'
                )
            )
        );


    $fechaInicio =
        $eleccion['fecha_inicio']
        ??
        "";


    $fechaFin =
        $eleccion['fecha_fin']
        ??
        "";
}


$eleccionAbierta =
    ($estadoEleccion === "abierta");


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;

$totalVotantes = 0;

$totalPendientes = 0;

$totalCandidatos = 0;

$totalVotos = 0;


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultadoEstudiantes =
    $conn->query("
        SELECT
            COUNT(*) AS total
        FROM usuarios
        WHERE LOWER(
            TRIM(rol)
        ) = 'estudiante'
    ");


if ($resultadoEstudiantes) {

    $fila =
        $resultadoEstudiantes->fetch_assoc();


    $totalEstudiantes =
        (int)(
            $fila['total']
            ??
            0
        );
}


/* =========================================================
   DATOS DE LA ELECCIÓN
========================================================= */

if ($idEleccion > 0) {


    /* =====================================================
       ESTUDIANTES QUE YA VOTARON
    ===================================================== */

    $stmt =
        $conn->prepare("
            SELECT
                COUNT(
                    DISTINCT id_usuario
                ) AS total
            FROM votos
            WHERE id_eleccion = ?
        ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        $fila =
            $resultado->fetch_assoc();


        $totalVotantes =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* =====================================================
       TOTAL VOTOS
    ===================================================== */

    $stmt =
        $conn->prepare("
            SELECT
                COUNT(*) AS total
            FROM votos
            WHERE id_eleccion = ?
        ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        $fila =
            $resultado->fetch_assoc();


        $totalVotos =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* =====================================================
       TOTAL CANDIDATOS
    ===================================================== */

    $stmt =
        $conn->prepare("
            SELECT
                COUNT(*) AS total
            FROM candidatos
            WHERE id_eleccion = ?
        ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        $fila =
            $resultado->fetch_assoc();


        $totalCandidatos =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }

}


/* =========================================================
   PENDIENTES
========================================================= */

$totalPendientes =
    max(
        0,
        $totalEstudiantes -
        $totalVotantes
    );


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion = 0;


if ($totalEstudiantes > 0) {

    $participacion =
        round(
            (
                $totalVotantes /
                $totalEstudiantes
            ) * 100,
            1
        );

}


if ($participacion > 100) {
    $participacion = 100;
}


/* =========================================================
   TOKEN
========================================================= */

if (
    !isset(
        $_SESSION['token_votacion_jurado']
    )
    ||
    strlen(
        (string)
        $_SESSION['token_votacion_jurado']
    ) < 20
) {

    $_SESSION['token_votacion_jurado'] =
        bin2hex(
            random_bytes(32)
        );

}


$tokenVotacion =
    $_SESSION['token_votacion_jurado'];


/* =========================================================
   MENSAJES
========================================================= */

$mensajeJurado =
    $_SESSION['mensaje_jurado']
    ??
    "";


$tipoMensajeJurado =
    $_SESSION['tipo_mensaje_jurado']
    ??
    "success";


unset(
    $_SESSION['mensaje_jurado'],
    $_SESSION['tipo_mensaje_jurado']
);


$anioActual =
    date("Y");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Panel del Jurado | Sistema de Votaciones Escolares
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<style>

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef4fb;

    color: #164f96;

    min-height: 100vh;
}


.app {

    display: flex;

    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    background:
        linear-gradient(
            180deg,
            #1559a7,
            #124e94
        );

    color: white;

    position: fixed;

    top: 0;

    bottom: 0;

    left: 0;

    z-index: 1000;
}


.sidebar-header {

    text-align: center;

    padding: 30px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);
}


.logo-jurado {

    font-size: 55px;

    margin-bottom: 8px;
}


.sidebar-header h1 {

    font-size: 30px;

    font-weight: bold;
}


.sidebar-header p {

    margin-top: 6px;

    font-size: 13px;

    opacity: .85;
}


.menu {

    padding: 18px 12px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    color: white;

    text-decoration: none;

    padding: 14px 15px;

    margin-bottom: 7px;

    border-radius: 10px;

    transition: .2s;
}


.menu a:hover,
.menu a.active {

    background:
        rgba(255,255,255,.16);
}


.menu a i {

    font-size: 20px;
}


.menu-separator {

    height: 1px;

    background:
        rgba(255,255,255,.20);

    margin: 18px 0;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    width:
        calc(100% - 250px);
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 70px;

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.12);
}


.topbar-title {

    font-size: 21px;

    font-weight: bold;
}


.user-info {

    font-size: 15px;
}


/* =========================================================
   CONTENIDO
========================================================= */

.content {

    padding: 35px;
}


/* =========================================================
   BIENVENIDA
========================================================= */

.welcome {

    background: white;

    border-radius: 18px;

    padding: 30px;

    margin-bottom: 25px;

    box-shadow:
        0 7px 20px
        rgba(0,0,0,.08);
}


.welcome h2 {

    color: #1453a3;

    font-size: 34px;

    margin-bottom: 10px;
}


.welcome p {

    color: #64748b;

    font-size: 16px;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.election-card {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-bottom: 25px;

    box-shadow:
        0 7px 20px
        rgba(0,0,0,.08);
}


.election-top {

    padding: 25px;

    background: #dcecff;

    border-bottom:
        1px solid
        #c8def5;

    display: flex;

    justify-content: space-between;

    gap: 20px;
}


.election-name {

    color: #1458a6;

    font-size: 25px;

    font-weight: bold;
}


.election-description {

    margin-top: 8px;

    color: #5f7895;
}


.status {

    display: inline-block;

    height: fit-content;

    padding: 8px 14px;

    border-radius: 30px;

    font-size: 13px;

    font-weight: bold;

    white-space: nowrap;
}


.status.open {

    background: #198754;

    color: white;
}


.status.closed {

    background: #dc3545;

    color: white;
}


/* =========================================================
   FECHAS
========================================================= */

.election-dates {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

    padding: 20px 25px;
}


.date-box {

    background: #f8fafc;

    border:
        1px solid
        #e2e8f0;

    border-radius: 10px;

    padding: 14px;
}


.date-box span {

    display: block;

    color: #64748b;

    font-size: 13px;

    margin-bottom: 5px;
}


/* =========================================================
   ACCIONES
========================================================= */

.actions {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.action {

    background: white;

    border-radius: 18px;

    padding: 25px;

    text-decoration: none;

    color: #164f96;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.08);

    transition: .2s;
}


.action:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,.12);
}


.action i {

    font-size: 38px;

    color: #1473ed;
}


.action h4 {

    margin-top: 15px;

    color: #1453a3;

    font-weight: bold;
}


.action p {

    color: #64748b;

    font-size: 14px;

    margin-top: 8px;
}


/* =========================================================
   BOTÓN PRINCIPAL COMENZAR VOTACIONES
========================================================= */

.btn-comenzar {

    width: 100%;

    min-height: 78px;

    padding: 0 30px;

    border: none;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #1769d1,
            #125bb8
        );

    color: white;

    font-size: 21px;

    font-weight: 700;

    letter-spacing: .2px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 15px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 8px 20px
        rgba(23,105,209,.25);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}


.btn-comenzar::before {

    content: "";

    position: absolute;

    top: 0;

    left: -120%;

    width: 70%;

    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.22),
            transparent
        );

    transform: skewX(-20deg);

    transition:
        left .6s ease;
}


.btn-comenzar:hover::before {

    left: 150%;
}


.btn-comenzar:hover {

    background:
        linear-gradient(
            135deg,
            #1976e8,
            #0f58b4
        );

    transform:
        translateY(-3px);

    box-shadow:
        0 12px 28px
        rgba(23,105,209,.35);
}


.btn-comenzar:active {

    transform:
        translateY(-1px);

    box-shadow:
        0 5px 12px
        rgba(23,105,209,.25);
}


.btn-comenzar i {

    font-size: 29px;

    transition:
        transform .2s ease;
}


.btn-comenzar:hover i {

    transform:
        scale(1.12);
}


.btn-comenzar span {

    position: relative;

    z-index: 2;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.stat {

    background: white;

    border-radius: 18px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.08);
}


.stat i {

    font-size: 40px;

    color: #1473ed;
}


.stat-number {

    font-size: 36px;

    font-weight: bold;

    color: #1453a3;

    margin: 7px 0;
}


.stat-title {

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   TARJETAS
========================================================= */

.card-custom {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.08);
}


.card-title {

    color: #1453a3;

    font-size: 22px;

    font-weight: bold;

    margin-bottom: 20px;
}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participation-header {

    display: flex;

    justify-content: space-between;

    align-items: center;
}


.participation-header h3 {

    color: #1453a3;
}


.percentage {

    font-size: 30px;

    font-weight: bold;

    color: #1473ed;
}


.progress-bar-container {

    height: 15px;

    background: #e6edf5;

    border-radius: 20px;

    overflow: hidden;

    margin: 18px 0;
}


.progress {

    height: 100%;

    background: #1473ed;

    border-radius: 20px;
}


.participation-data {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    color: #64748b;
}


/* =========================================================
   INFORMACIÓN
========================================================= */

.info-row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 13px 5px;

    border-bottom:
        1px solid
        #e2e8f0;
}


.info-row:last-child {

    border-bottom: none;
}


.estado-abierta {

    background: #198754;

    color: white;

    padding: 7px 12px;

    border-radius: 7px;

    font-weight: bold;
}


.estado-cerrada {

    background: #dc3545;

    color: white;

    padding: 7px 12px;

    border-radius: 7px;

    font-weight: bold;
}


/* =========================================================
   MENSAJE
========================================================= */

.mensaje {

    background: white;

    border-radius: 12px;

    padding: 15px 20px;

    margin-bottom: 20px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top: 35px;

    padding: 25px 10px;

    text-align: center;

    border-top:
        1px solid
        #dce5ef;

    color: #72869d;

    font-size: 13px;
}


.footer strong {

    color: #1453a3;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .actions {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media(max-width:800px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }


    .main {

        margin-left: 0;

        width: 100%;
    }


    .election-top {

        flex-direction: column;
    }


    .election-dates {

        grid-template-columns:
            1fr;
    }


    .participation-data {

        flex-direction: column;
    }
}


@media(max-width:600px) {

    .content {

        padding: 20px 15px;
    }


    .topbar {

        padding: 0 15px;
    }


    .topbar-title {

        font-size: 17px;
    }


    .user-info {

        display: none;
    }


    .stats,
    .actions {

        grid-template-columns:
            1fr;
    }


    .welcome h2 {

        font-size: 28px;
    }
}

</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


<div class="sidebar-header">

<div class="logo-jurado">
⚖️
</div>


<h1>
JURADO
</h1>


<p>
Panel de votaciones
</p>

</div>


<nav class="menu">


<a
    href="jurado.php"
    class="active"
>

<i class="bi bi-house-fill"></i>

<span>
Inicio
</span>

</a>


<a
    href="ingresar_estudiante.php?token=<?php echo urlencode($tokenVotacion); ?>"
    target="_blank"
    rel="noopener noreferrer"
>

<i class="bi bi-person-check-fill"></i>

<span>
Comenzar votación
</span>

</a>


<a
    href="resultados.php"
>

<i class="bi bi-trophy-fill"></i>

<span>
Resultados
</span>

</a>


<a
    href="graficas.php"
>

<i class="bi bi-bar-chart-fill"></i>

<span>
Gráficas
</span>

</a>


<div class="menu-separator"></div>


<a
    href="logout.php"
    onclick="
        return confirm('¿Está seguro de cerrar sesión?');
    "
>

<i class="bi bi-box-arrow-right"></i>

<span>
Cerrar sesión
</span>

</a>


</nav>


</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<header class="topbar">


<div class="topbar-title">

⚖️ Sistema de Votaciones Escolares

</div>


<div class="user-info">

<i class="bi bi-person-badge-fill"></i>

Jurado:

<strong>

<?php

echo htmlspecialchars(
    $nombreJurado
);

?>

</strong>

</div>


</header>


<section class="content">


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if (
    $mensajeJurado !== ""
) { ?>

<div class="mensaje">

<?php if (
    $tipoMensajeJurado === "success"
) { ?>

<i class="bi bi-check-circle-fill text-success"></i>

<?php } else { ?>

<i class="bi bi-info-circle-fill text-primary"></i>

<?php } ?>


<?php

echo htmlspecialchars(
    $mensajeJurado
);

?>

</div>

<?php } ?>


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="welcome">

<h2>

Bienvenido,

<?php

echo htmlspecialchars(
    $nombreJurado
);

?>

👋

</h2>


<p>

Desde este panel puede gestionar
el proceso de votación de los estudiantes
y consultar los resultados electorales.

</p>

</div>


<!-- =====================================================
     ELECCIÓN ACTUAL
===================================================== -->

<div class="election-card">


<div class="election-top">


<div>

<div class="election-name">

<i class="bi bi-calendar-check-fill"></i>

<?php

echo htmlspecialchars(
    $nombreEleccion
);

?>

</div>


<div class="election-description">

<?php

echo htmlspecialchars(
    $descripcionEleccion
);

?>

</div>

</div>


<?php if (
    $eleccionAbierta
) { ?>

<span class="status open">

● ELECCIÓN ABIERTA

</span>

<?php } else { ?>

<span class="status closed">

● ELECCIÓN CERRADA

</span>

<?php } ?>


</div>


<div class="election-dates">


<div class="date-box">

<span>
Fecha de inicio
</span>


<strong>

<?php

echo $fechaInicio !== ""
    ? htmlspecialchars(
        $fechaInicio
    )
    : "No definida";

?>

</strong>

</div>


<div class="date-box">

<span>
Fecha de finalización
</span>


<strong>

<?php

echo $fechaFin !== ""
    ? htmlspecialchars(
        $fechaFin
    )
    : "No definida";

?>

</strong>

</div>


</div>


</div>


<!-- =====================================================
     ACCIONES PRINCIPALES
===================================================== -->

<div class="actions">


<!-- =====================================================
     COMENZAR VOTACIONES
===================================================== -->

<?php if (
    $eleccionAbierta
) { ?>


<form
    method="POST"
    target="_blank"
    style="margin:0; width:100%;"
>


<input
    type="hidden"
    name="id_eleccion"
    value="<?php echo $idEleccion; ?>"
>


<button
    type="submit"
    name="comenzar_votacion"
    class="btn-comenzar"
>


<i class="bi bi-play-circle-fill"></i>


<span>
Comenzar votaciones
</span>


<i class="bi bi-arrow-right"></i>


</button>


</form>


<?php } else { ?>


<div
    class="action"
    style="opacity:.6;"
>


<i class="bi bi-lock-fill"></i>


<h4>
Comenzar votaciones
</h4>


<p>
La elección está cerrada.
</p>


</div>


<?php } ?>


<!-- =====================================================
     RESULTADOS
===================================================== -->

<a
    href="resultados.php"
    class="action"
>


<i class="bi bi-trophy-fill"></i>


<h4>
Ver resultados
</h4>


<p>
Consultar los resultados
de la elección.
</p>


</a>


<!-- =====================================================
     GRÁFICAS
===================================================== -->

<a
    href="graficas.php"
    class="action"
>


<i class="bi bi-bar-chart-fill"></i>


<h4>
Ver gráficas
</h4>


<p>
Analizar visualmente
la participación electoral.
</p>


</a>


<!-- =====================================================
     CERRAR SESIÓN
===================================================== -->

<a
    href="logout.php"
    class="action"
    onclick="
        return confirm('¿Está seguro de cerrar sesión?');
    "
>


<i class="bi bi-box-arrow-right"></i>


<h4>
Cerrar sesión
</h4>


<p>
Salir de forma segura
del panel del jurado.
</p>


</a>


</div>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="stats">


<div class="stat">

<i class="bi bi-people-fill"></i>


<div class="stat-number">

<?php

echo $totalEstudiantes;

?>

</div>


<div class="stat-title">

Estudiantes registrados

</div>

</div>


<div class="stat">

<i class="bi bi-person-check-fill"></i>


<div class="stat-number">

<?php

echo $totalVotantes;

?>

</div>


<div class="stat-title">

Estudiantes que votaron

</div>

</div>


<div class="stat">

<i class="bi bi-person-exclamation"></i>


<div class="stat-number">

<?php

echo $totalPendientes;

?>

</div>


<div class="stat-title">

Estudiantes pendientes

</div>

</div>


<div class="stat">

<i class="bi bi-person-vcard-fill"></i>


<div class="stat-number">

<?php

echo $totalCandidatos;

?>

</div>


<div class="stat-title">

Candidatos de la elección

</div>

</div>


</div>


<!-- =====================================================
     INFORMACIÓN DE LA ELECCIÓN
===================================================== -->

<div class="card-custom">


<div class="card-title">

<i class="bi bi-calendar-check-fill"></i>

Información de la elección

</div>


<div class="info-row">

<strong>
Nombre
</strong>


<span>

<?php

echo htmlspecialchars(
    $nombreEleccion
);

?>

</span>

</div>


<div class="info-row">

<strong>
Descripción
</strong>


<span>

<?php

echo htmlspecialchars(
    $descripcionEleccion
);

?>

</span>

</div>


<div class="info-row">

<strong>
Estado
</strong>


<span>


<?php if (
    $eleccionAbierta
) { ?>


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


<!-- =====================================================
     PARTICIPACIÓN ELECTORAL
===================================================== -->

<div class="card-custom">


<div class="participation-header">


<h3>

<i class="bi bi-pie-chart-fill"></i>

Participación electoral

</h3>


<div class="percentage">

<?php

echo $participacion;

?>%

</div>


</div>


<div class="progress-bar-container">


<div
    class="progress"
    style="
        width: <?php echo $participacion; ?>%;
    "
></div>


</div>


<div class="participation-data">


<span>

<i class="bi bi-person-check-fill"></i>

<?php

echo $totalVotantes;

?>

estudiantes han votado

</span>


<span>

<i class="bi bi-person-exclamation"></i>

<?php

echo $totalPendientes;

?>

pendientes

</span>


<span>

<i class="bi bi-check2-square"></i>

<?php

echo $totalVotos;

?>

votos registrados

</span>


</div>


</div>


<!-- =====================================================
     ACCESOS RÁPIDOS
===================================================== -->

<div class="card-custom">


<div class="card-title">

<i class="bi bi-grid-fill"></i>

Accesos rápidos

</div>


<div class="actions">


<?php if (
    $eleccionAbierta
) { ?>


<form
    method="POST"
    target="_blank"
    style="margin:0; width:100%;"
>


<input
    type="hidden"
    name="id_eleccion"
    value="<?php echo $idEleccion; ?>"
>


<button
    type="submit"
    name="comenzar_votacion"
    class="btn-comenzar"
>


<i class="bi bi-ballot-check-fill"></i>


<span>
Comenzar votación
</span>


<i class="bi bi-arrow-right"></i>


</button>


</form>


<?php } ?>


<a
    href="resultados.php"
    class="action"
>


<i class="bi bi-trophy-fill"></i>


<h4>
Ver resultados
</h4>


<p>
Consultar resultados.
</p>


</a>


<a
    href="graficas.php"
    class="action"
>


<i class="bi bi-bar-chart-fill"></i>


<h4>
Ver gráficas
</h4>


<p>
Analizar participación.
</p>


</a>


</div>


</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">


<div>

© <?php echo $anioActual; ?>

Sistema de Votaciones Escolares

</div>


<div>

Todos los derechos reservados.

</div>


<div>

Elaborado por

<strong>
Juan David Otero Cantor
</strong>

</div>


</footer>


</section>


</main>


</div>


</body>

</html>
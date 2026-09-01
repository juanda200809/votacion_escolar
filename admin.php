<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


/* =========================================================
   SEGURIDAD
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");

    exit();

}


$rol =
    strtolower(
        trim(
            (string)$_SESSION['rol']
        )
    );


if ($rol !== "administrador") {

    if ($rol === "jurado") {

        header("Location: jurado.php");

        exit();

    }

    header("Location: login.php");

    exit();

}


/* =========================================================
   DATOS DEL ADMINISTRADOR
========================================================= */

$nombreAdministrador =
    $_SESSION['nombre']
    ??
    "Administrador";


/* =========================================================
   FECHA Y HORA
========================================================= */

date_default_timezone_set(
    "America/Bogota"
);


$fechaActual =
    date("d/m/Y");


$horaActual =
    date("h:i A");


/* =========================================================
   MENSAJES
========================================================= */

$mensaje = "";

$tipoMensaje = "";


if (isset($_GET['abierta'])) {

    $mensaje =
        "La elección fue habilitada nuevamente correctamente.";

    $tipoMensaje =
        "success";

}


if (isset($_GET['cerrada'])) {

    $mensaje =
        "La elección fue cerrada correctamente.";

    $tipoMensaje =
        "success";

}


if (isset($_GET['eliminada'])) {

    $mensaje =
        "La elección fue eliminada correctamente.";

    $tipoMensaje =
        "success";

}


if (isset($_GET['error'])) {

    $tipoMensaje =
        "danger";


    switch (
        $_GET['error']
    ) {

        case "no_eleccion":

            $mensaje =
                "No existe ninguna elección registrada.";

            break;


        case "abrir":

            $mensaje =
                "No se pudo habilitar la elección.";

            break;


        case "cerrar":

            $mensaje =
                "No se pudo cerrar la elección.";

            break;


        case "acceso":

            $mensaje =
                "No tiene permisos para realizar esta acción.";

            break;


        default:

            $mensaje =
                "Ocurrió un error en la operación.";

            break;

    }

}


/* =========================================================
   ESTADÍSTICA DE ESTUDIANTES
========================================================= */

$totalEstudiantes = 0;


$resultado =
    $conn->query("
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE LOWER(TRIM(rol)) = 'estudiante'
    ");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)(
            $fila['total']
            ??
            0
        );

}


/* =========================================================
   ESTADÍSTICA DE JURADOS
========================================================= */

$totalJurados = 0;


$resultado =
    $conn->query("
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE LOWER(TRIM(rol)) = 'jurado'
    ");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalJurados =
        (int)(
            $fila['total']
            ??
            0
        );

}


/* =========================================================
   ÚLTIMA ELECCIÓN
========================================================= */

$eleccion = null;

$idEleccion = 0;

$nombreEleccion =
    "Sin elección registrada";

$descripcionEleccion =
    "";

$fechaInicio =
    "";

$fechaFin =
    "";

$estadoEleccion =
    "cerrada";


$resultado =
    $conn->query("
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
        $eleccion['nombre']
        ??
        "Sin nombre";


    $descripcionEleccion =
        $eleccion['descripcion']
        ??
        "";


    $fechaInicio =
        $eleccion['fecha_inicio']
        ??
        "";


    $fechaFin =
        $eleccion['fecha_fin']
        ??
        "";


    $estadoEleccion =
        strtolower(
            trim(
                (string)(
                    $eleccion['estado']
                    ??
                    "cerrada"
                )
            )
        );

}


$eleccionAbierta =
    $estadoEleccion === "abierta";


/* =========================================================
   ESTADÍSTICA DE CANDIDATOS
========================================================= */

$totalCandidatos = 0;


if ($idEleccion > 0) {

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
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
   TOTAL DE VOTOS
========================================================= */

$totalVotos = 0;


if ($idEleccion > 0) {

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
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

} else {

    $resultado =
        $conn->query("
            SELECT COUNT(*) AS total
            FROM votos
        ");


    if ($resultado) {

        $fila =
            $resultado->fetch_assoc();

        $totalVotos =
            (int)(
                $fila['total']
                ??
                0
            );

    }

}


/* =========================================================
   ESTUDIANTES QUE YA VOTARON
========================================================= */

$totalVotantes = 0;


if ($idEleccion > 0) {

    $stmt =
        $conn->prepare("
            SELECT
                COUNT(
                    DISTINCT v.id_usuario
                ) AS total
            FROM votos v

            INNER JOIN candidatos c
                ON c.id = v.id_candidato

            WHERE c.id_eleccion = ?
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

}


/* =========================================================
   PENDIENTES
========================================================= */

$totalPendientes =
    max(
        0,
        $totalEstudiantes
        -
        $totalVotantes
    );


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion =
    0;


if (
    $totalEstudiantes > 0
) {

    $participacion =
        round(
            (
                $totalVotantes
                /
                $totalEstudiantes
            )
            *
            100,
            1
        );

}


/* =========================================================
   MESAS
========================================================= */

$totalMesas = 0;

$mesasAbiertas = 0;

$mesasCerradas = 0;

$mesasDisponibles = 0;

$juradosSinMesa = 0;


/* ---------------------------------------------------------
   INFORMACIÓN DE MESAS
--------------------------------------------------------- */

if ($idEleccion > 0) {


    /* TOTAL MESAS */

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
            FROM mesas_votacion
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


        $totalMesas =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* MESAS ABIERTAS */

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
            FROM mesas_votacion
            WHERE id_eleccion = ?
            AND LOWER(TRIM(estado)) = 'abierta'
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


        $mesasAbiertas =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* MESAS CERRADAS */

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
            FROM mesas_votacion
            WHERE id_eleccion = ?
            AND LOWER(TRIM(estado)) = 'cerrada'
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


        $mesasCerradas =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* MESAS SIN JURADO */

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total
            FROM mesas_votacion
            WHERE id_eleccion = ?
            AND id_jurado IS NULL
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


        $mesasDisponibles =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }


    /* JURADOS SIN MESA */

    $stmt =
        $conn->prepare("
            SELECT COUNT(*) AS total

            FROM usuarios u

            WHERE LOWER(TRIM(u.rol))
                = 'jurado'

            AND NOT EXISTS (

                SELECT 1

                FROM mesas_votacion m

                WHERE m.id_eleccion = ?

                AND m.id_jurado = u.id

            )
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


        $juradosSinMesa =
            (int)(
                $fila['total']
                ??
                0
            );


        $stmt->close();

    }

}


/* =========================================================
   DATOS PARA GRÁFICA
========================================================= */

$etiquetasGrafica = [];

$valoresGrafica = [];


$resultadoGrafica =
    $conn->query("
        SELECT
            e.nombre,
            COUNT(v.id) AS total_votos

        FROM elecciones e

        LEFT JOIN candidatos c
            ON c.id_eleccion = e.id

        LEFT JOIN votos v
            ON v.id_candidato = c.id

        GROUP BY
            e.id,
            e.nombre

        ORDER BY
            e.id ASC
    ");


if ($resultadoGrafica) {

    while (
        $fila =
        $resultadoGrafica->fetch_assoc()
    ) {

        $etiquetasGrafica[] =
            $fila['nombre'];


        $valoresGrafica[] =
            (int)(
                $fila['total_votos']
                ??
                0
            );

    }

}


?>

<!DOCTYPE html>

<html lang="es">


<head>


<meta charset="UTF-8">


<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>


<title>

Panel Administrador

</title>


<!-- BOOTSTRAP -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- ICONOS -->

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<!-- CHART.JS -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>


<style>


/* =========================================================
   GENERAL
========================================================= */

* {

    box-sizing: border-box;

}


body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #26364a;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 255px;

    height: 100vh;

    background:
        #0d47a1;

    color: white;

    z-index: 1000;

    overflow-y: auto;

}


.logo {

    text-align: center;

    padding:
        28px 15px 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);

}


.logo-icon {

    font-size: 48px;

    margin-bottom: 5px;

}


.logo h1 {

    margin: 0;

    font-size: 30px;

    font-weight: 800;

}


.logo p {

    margin:
        5px 0 0;

    font-size: 14px;

    opacity: .85;

}


.menu {

    padding:
        15px 12px;

}


.menu-title {

    font-size: 12px;

    text-transform: uppercase;

    font-weight: 800;

    color:
        rgba(255,255,255,.65);

    padding:
        12px 13px 7px;

    letter-spacing: .5px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    text-decoration: none;

    color: white;

    padding:
        13px 14px;

    border-radius: 10px;

    margin-bottom: 5px;

    font-weight: 600;

    transition:
        .2s;

}


.menu a i {

    font-size: 19px;

    width: 22px;

    text-align: center;

}


.menu a:hover {

    background:
        rgba(255,255,255,.15);

    transform:
        translateX(2px);

}


.menu a.activo {

    background:
        rgba(255,255,255,.20);

}


.menu-separador {

    height: 1px;

    background:
        rgba(255,255,255,.18);

    margin:
        15px 4px;

}


/* =========================================================
   CONTENIDO
========================================================= */

.main {

    margin-left: 255px;

    min-height: 100vh;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 70px;

    background:
        #1674e8;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.12);

}


.topbar-title {

    font-size: 21px;

    font-weight: 800;

}


.topbar-admin {

    font-weight: 700;

}


/* =========================================================
   CONTENIDO INTERNO
========================================================= */

.contenido {

    padding:
        30px;

    max-width: 1500px;

    margin: auto;

}


/* =========================================================
   TITULO
========================================================= */

.titulo {

    color:
        #1453a3;

    font-size: 34px;

    font-weight: 800;

    margin-bottom: 18px;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #eaf3ff
        );

    border-radius: 18px;

    padding:
        28px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.08);

    border-left:
        6px solid
        #1674e8;

}


.bienvenida h4 {

    color:
        #1453a3;

    font-weight: 800;

}


.bienvenida h2 {

    font-size: 21px;

    line-height: 1.5;

    margin-bottom: 0;

}


/* =========================================================
   FECHA
========================================================= */

.fecha {

    text-align: right;

    color:
        #64748b;

    margin:
        15px 0 25px;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;

}


.stat {

    background: white;

    border-radius: 16px;

    padding:
        24px 18px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

    transition:
        .2s;

}


.stat:hover {

    transform:
        translateY(-3px);

}


.stat-icon {

    font-size: 35px;

    margin-bottom: 8px;

}


.stat-numero {

    color:
        #1453a3;

    font-size: 32px;

    font-weight: 800;

}


.stat-texto {

    color:
        #64748b;

    font-weight: 600;

    margin-top: 5px;

}


/* =========================================================
   TARJETAS
========================================================= */

.card-custom {

    background: white;

    border-radius: 17px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.07);

}


.card-title {

    color:
        #1453a3;

    font-size: 23px;

    font-weight: 800;

    margin-bottom: 20px;

}


.info-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    padding:
        13px 5px;

    border-bottom:
        1px solid
        #e5e7eb;

}


.info-row:last-child {

    border-bottom: none;

}


/* =========================================================
   ESTADOS
========================================================= */

.estado-abierta {

    display: inline-block;

    background:
        #d1e7dd;

    color:
        #0f5132;

    padding:
        8px 13px;

    border-radius: 8px;

    font-weight: 700;

}


.estado-cerrada {

    display: inline-block;

    background:
        #f8d7da;

    color:
        #842029;

    padding:
        8px 13px;

    border-radius: 8px;

    font-weight: 700;

}


/* =========================================================
   MESAS
========================================================= */

.mesas-resumen {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 20px;

}


.mesa-resumen {

    background:
        #f8fafc;

    border:
        1px solid
        #e2e8f0;

    border-radius: 12px;

    text-align: center;

    padding:
        18px 10px;

}


.mesa-resumen-numero {

    font-size: 30px;

    font-weight: 800;

    color:
        #1453a3;

}


.mesa-resumen-texto {

    color:
        #64748b;

    font-weight: 600;

    margin-top: 4px;

}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participacion-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

}


.participacion-header h3 {

    color:
        #1453a3;

    font-weight: 800;

    margin: 0;

}


.porcentaje {

    font-size: 30px;

    font-weight: 800;

    color:
        #1674e8;

}


.barra {

    width: 100%;

    height: 18px;

    background:
        #e2e8f0;

    border-radius: 20px;

    overflow: hidden;

    margin:
        18px 0;

}


.barra-progreso {

    height: 100%;

    background:
        #1674e8;

    border-radius: 20px;

    transition:
        width .4s ease;

}


.participacion-datos {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    color:
        #64748b;

    font-weight: 600;

}


/* =========================================================
   GRÁFICA
========================================================= */

.grafica-container {

    position: relative;

    width: 100%;

    height: 380px;

}


/* =========================================================
   ACCESOS RÁPIDOS
========================================================= */

.acceso-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.acceso {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    min-height: 82px;

    border-radius: 13px;

    text-decoration: none;

    font-weight: 800;

    font-size: 16px;

    transition:
        .2s;

    padding:
        15px;

}


.acceso i {

    font-size: 24px;

}


.acceso:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 16px
        rgba(0,0,0,.12);

}


.azul {

    background:
        #dbeafe;

    color:
        #084298;

}


.verde {

    background:
        #d1e7dd;

    color:
        #0f5132;

}


.celeste {

    background:
        #d9f4fc;

    color:
        #087990;

}


.amarillo {

    background:
        #fff3cd;

    color:
        #664d03;

}


.rojo {

    background:
        #f8d7da;

    color:
        #842029;

}


.oscuro {

    background:
        #e2e3e5;

    color:
        #212529;

}


/* =========================================================
   BOTÓN PRINCIPAL
========================================================= */

.btn-control {

    border: none;

    border-radius: 9px;

    padding:
        11px 18px;

    font-weight: 700;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    gap: 8px;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    padding:
        35px;

    color:
        #64748b;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .acceso-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .mesas-resumen {

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

    }


    .contenido {

        padding: 20px;

    }


    .estadisticas {

        grid-template-columns:
            1fr;

    }


    .acceso-grid {

        grid-template-columns:
            1fr;

    }


    .mesas-resumen {

        grid-template-columns:
            1fr;

    }


    .topbar {

        padding:
            0 18px;

    }


    .topbar-admin {

        display: none;

    }


    .titulo {

        font-size: 27px;

    }


    .info-row {

        flex-direction: column;

        align-items: flex-start;

    }


    .participacion-header {

        flex-direction: column;

        align-items: flex-start;

    }


    .participacion-datos {

        flex-direction: column;

        gap: 8px;

    }


    .fecha {

        text-align: left;

    }


    .grafica-container {

        height: 300px;

    }

}

</style>


</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


<div class="logo">


<div class="logo-icon">

⚖️

</div>


<h1>

VOTACIONES

</h1>


<p>

Panel Administrativo

</p>


</div>


<nav class="menu">


<div class="menu-title">

Principal

</div>


<!-- INICIO -->

<a
    href="admin.php"
    class="activo"
>


<i class="bi bi-house-fill"></i>

Inicio


</a>


<!-- ELECCIONES -->

<a
    href="elecciones.php"
>


<i class="bi bi-calendar-event-fill"></i>

Elecciones


</a>


<!-- ESTUDIANTES -->

<a
    href="estudiantes.php"
>


<i class="bi bi-people-fill"></i>

Estudiantes


</a>


<!-- CANDIDATOS -->

<a
    href="candidatos.php"
>


<i class="bi bi-person-vcard-fill"></i>

Candidatos


</a>


<!-- JURADOS -->

<a
    href="jurados.php"
>


<i class="bi bi-person-badge-fill"></i>

Jurados y Mesas


</a>


<div class="menu-separador"></div>


<div class="menu-title">

Resultados

</div>


<!-- RESULTADOS -->

<a
    href="resultados.php<?php

    echo $idEleccion > 0
        ? '?id_eleccion=' . $idEleccion
        : '';

?>"
>


<i class="bi bi-trophy-fill"></i>

Resultados


</a>


<!-- GRÁFICAS -->

<a
    href="graficas.php<?php

    echo $idEleccion > 0
        ? '?id_eleccion=' . $idEleccion
        : '';

?>"
>


<i class="bi bi-bar-chart-fill"></i>

Gráficas


</a>


<div class="menu-separador"></div>


<div class="menu-title">

Herramientas

</div>


<!-- IMPORTAR -->

<a
    href="importar_estudiantes.php"
>


<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel


</a>


<!-- EXPORTAR -->

<a
    href="exportar_estudiantes.php"
>


<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel


</a>


<!-- PDF -->

<a
    href="pdf_resultados.php"
>


<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF


</a>


<div class="menu-separador"></div>


<div class="menu-title">

Control electoral

</div>


<!-- HABILITAR -->

<?php if (
    $eleccion &&
    !$eleccionAbierta
) { ?>


<a
    href="habilitar_eleccion.php?id=<?php
        echo $idEleccion;
    ?>"
    onclick="
        return confirm(
            '¿Desea habilitar nuevamente esta elección?'
        );
    "
>


<i class="bi bi-unlock-fill"></i>

Habilitar elección


</a>


<?php } ?>


<!-- CERRAR -->

<?php if (
    $eleccion &&
    $eleccionAbierta
) { ?>


<a
    href="cerrar_eleccion.php?id=<?php
        echo $idEleccion;
    ?>"
    onclick="
        return confirm(
            '¿Está seguro de cerrar esta elección?'
        );
    "
>


<i class="bi bi-lock-fill"></i>

Cerrar elección


</a>


<?php } ?>


<div class="menu-separador"></div>


<!-- CERRAR SESIÓN -->

<a
    href="logout.php"
    onclick="
        return confirm(
            '¿Desea cerrar sesión?'
        );
    "
>


<i class="bi bi-box-arrow-right"></i>

Cerrar sesión


</a>


</nav>


</aside>


<!-- =====================================================
     CONTENIDO PRINCIPAL
===================================================== -->

<main class="main">


<!-- TOPBAR -->

<header class="topbar">


<div class="topbar-title">

🎓 Sistema de Votaciones Escolares

</div>


<div class="topbar-admin">

<i class="bi bi-person-circle"></i>

Administrador

</div>


</header>


<div class="contenido">


<!-- =================================================
     MENSAJE
================================================= -->

<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>">


<i class="bi bi-info-circle-fill"></i>


<?php

echo htmlspecialchars(
    $mensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>


</div>


<?php } ?>


<!-- =================================================
     TITULO
================================================= -->

<h1 class="titulo">

Bienvenido,

<?php

echo htmlspecialchars(
    $nombreAdministrador,
    ENT_QUOTES,
    'UTF-8'
);

?>

👋


</h1>


<!-- =================================================
     BIENVENIDA
================================================= -->

<div class="bienvenida">


<h4>

👋 ¡Bienvenido al Panel de Administración!

</h4>


<h2>

Administre estudiantes, jurados, candidatos,
votaciones, mesas y resultados desde un solo lugar.

</h2>


</div>


<p class="text-secondary mt-3">

Panel de Administración del Sistema de Votaciones Escolares

</p>


<!-- FECHA -->

<div class="fecha">


📅

<strong>

Fecha:

</strong>


<?php

echo $fechaActual;

?>


&nbsp;&nbsp;&nbsp;


🕐

<strong>

Hora:

</strong>


<?php

echo $horaActual;

?>


</div>


<!-- =================================================
     ESTADÍSTICAS
================================================= -->

<section class="estadisticas">


<div class="stat">


<div class="stat-icon">

👨‍🎓

</div>


<div class="stat-numero">

<?php

echo $totalEstudiantes;

?>

</div>


<div class="stat-texto">

Estudiantes registrados

</div>


</div>


<div class="stat">


<div class="stat-icon">

⚖️

</div>


<div class="stat-numero">

<?php

echo $totalJurados;

?>

</div>


<div class="stat-texto">

Jurados registrados

</div>


</div>


<div class="stat">


<div class="stat-icon">

📦

</div>


<div class="stat-numero">

<?php

echo $totalCandidatos;

?>

</div>


<div class="stat-texto">

Candidatos inscritos

</div>


</div>


<div class="stat">


<div class="stat-icon">

☑️

</div>


<div class="stat-numero">

<?php

echo $totalVotos;

?>

</div>


<div class="stat-texto">

Votos registrados

</div>


</div>


</section>


<!-- =================================================
     ELECCIÓN ACTUAL
================================================= -->

<section class="card-custom">


<div class="card-title">


<i class="bi bi-calendar-check-fill"></i>

Elección actual


</div>


<?php if (
    $eleccion
) { ?>


<div class="info-row">


<strong>

Nombre

</strong>


<span>

<?php

echo htmlspecialchars(
    $nombreEleccion,
    ENT_QUOTES,
    'UTF-8'
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

echo $descripcionEleccion !== ""
    ? htmlspecialchars(
        $descripcionEleccion,
        ENT_QUOTES,
        'UTF-8'
    )
    : "Sin descripción";

?>

</span>


</div>


<div class="info-row">


<strong>

Fecha de inicio

</strong>


<span>

<?php

echo $fechaInicio !== ""
    ? htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime($fechaInicio)
        ),
        ENT_QUOTES,
        'UTF-8'
    )
    : "No definida";

?>

</span>


</div>


<div class="info-row">


<strong>

Fecha de finalización

</strong>


<span>

<?php

echo $fechaFin !== ""
    ? htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime($fechaFin)
        ),
        ENT_QUOTES,
        'UTF-8'
    )
    : "No definida";

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

🟢 Elección abierta

</span>


<?php } else { ?>


<span class="estado-cerrada">

🔴 Elección cerrada

</span>


<?php } ?>


</span>


</div>


<?php } else { ?>


<div class="alert alert-warning mb-0">


⚠️


No existe ninguna elección registrada.


<br><br>


<a
    href="elecciones.php"
    class="btn btn-primary"
>


<i class="bi bi-plus-circle"></i>

Crear elección


</a>


</div>


<?php } ?>


</section>


<!-- =================================================
     MESAS
================================================= -->

<section class="card-custom">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div class="card-title mb-0">


<i class="bi bi-box-seam"></i>

Mesas de votación


</div>


<a
    href="jurados.php"
    class="btn btn-primary btn-control"
>


<i class="bi bi-person-badge-fill"></i>

Administrar Jurados y Mesas


</a>


</div>


<?php if (
    $idEleccion > 0
) { ?>


<div class="mesas-resumen">


<div class="mesa-resumen">


<div class="mesa-resumen-numero">

<?php

echo $totalMesas;

?>

</div>


<div class="mesa-resumen-texto">

Mesas totales

</div>


</div>


<div class="mesa-resumen">


<div class="mesa-resumen-numero">

<?php

echo $mesasAbiertas;

?>

</div>


<div class="mesa-resumen-texto">

Mesas abiertas

</div>


</div>


<div class="mesa-resumen">


<div class="mesa-resumen-numero">

<?php

echo $mesasCerradas;

?>

</div>


<div class="mesa-resumen-texto">

Mesas cerradas

</div>


</div>


<div class="mesa-resumen">


<div class="mesa-resumen-numero">

<?php

echo $mesasDisponibles;

?>

</div>


<div class="mesa-resumen-texto">

Mesas disponibles

</div>


</div>


</div>


<div class="alert alert-info mt-3 mb-0">


<i class="bi bi-info-circle-fill"></i>


Hay


<strong>

<?php

echo $juradosSinMesa;

?>

</strong>


jurado(s) sin mesa asignada.


<br>


La administración de mesas se realiza
desde


<strong>

Administrar Jurados y Mesas.

</strong>


</div>


<?php } else { ?>


<div class="alert alert-warning mt-4 mb-0">


No existe una elección para mostrar las mesas.


</div>


<?php } ?>


</section>


<!-- =================================================
     PARTICIPACIÓN
================================================= -->

<section class="card-custom">


<div class="participacion-header">


<h3>


<i class="bi bi-pie-chart-fill"></i>

Participación electoral


</h3>


<div class="porcentaje">

<?php

echo $participacion;

?>%


</div>


</div>


<div class="barra">


<div
    class="barra-progreso"
    style="
        width:
        <?php
        echo $participacion;
        ?>%;
    "
></div>


</div>


<div class="participacion-datos">


<span>


<i class="bi bi-person-check-fill"></i>


<?php

echo $totalVotantes;

?>

estudiantes ya votaron


</span>


<span>


<i class="bi bi-person-exclamation"></i>


<?php

echo $totalPendientes;

?>

pendientes


</span>


</div>


</section>


<!-- =================================================
     GRÁFICA
================================================= -->

<section class="card-custom">


<div class="card-title">


<i class="bi bi-bar-chart-fill"></i>

Votos por elección


</div>


<div class="grafica-container">


<?php if (
    count($etiquetasGrafica) > 0
) { ?>


<canvas
    id="graficaElecciones"
></canvas>


<?php } else { ?>


<div class="alert alert-info">


No existen datos suficientes
para mostrar la gráfica.


</div>


<?php } ?>


</div>


</section>


<!-- =================================================
     ACCESOS RÁPIDOS
================================================= -->

<section class="card-custom">


<div class="card-title">


<i class="bi bi-grid-fill"></i>

Todos los accesos


</div>


<div class="acceso-grid">


<!-- ELECCIONES -->

<a
    href="elecciones.php"
    class="acceso azul"
>


<i class="bi bi-calendar-event-fill"></i>

Gestionar Elecciones


</a>


<!-- ESTUDIANTES -->

<a
    href="estudiantes.php"
    class="acceso verde"
>


<i class="bi bi-people-fill"></i>

Gestionar Estudiantes


</a>


<!-- CANDIDATOS -->

<a
    href="candidatos.php"
    class="acceso celeste"
>


<i class="bi bi-person-vcard-fill"></i>

Gestionar Candidatos


</a>


<!-- JURADOS -->

<a
    href="jurados.php"
    class="acceso azul"
>


<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados y Mesas


</a>


<!-- RESULTADOS -->

<a
    href="resultados.php<?php

echo $idEleccion > 0
    ? '?id_eleccion=' . $idEleccion
    : '';

?>"
    class="acceso amarillo"
>


<i class="bi bi-trophy-fill"></i>

Ver Resultados


</a>


<!-- GRÁFICAS -->

<a
    href="graficas.php<?php

echo $idEleccion > 0
    ? '?id_eleccion=' . $idEleccion
    : '';

?>"
    class="acceso celeste"
>


<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas


</a>


<!-- IMPORTAR -->

<a
    href="importar_estudiantes.php"
    class="acceso verde"
>


<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel


</a>


<!-- EXPORTAR -->

<a
    href="exportar_estudiantes.php"
    class="acceso verde"
>


<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel


</a>


<!-- PDF -->

<a
    href="pdf_resultados.php"
    class="acceso rojo"
>


<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF


</a>


<!-- HABILITAR -->

<?php if (
    $eleccion &&
    !$eleccionAbierta
) { ?>


<a
    href="habilitar_eleccion.php?id=<?php
        echo $idEleccion;
    ?>"
    class="acceso verde"
    onclick="
        return confirm(
            '¿Desea habilitar nuevamente esta elección?'
        );
    "
>


<i class="bi bi-unlock-fill"></i>

Habilitar Elección


</a>


<?php } ?>


<!-- CERRAR -->

<?php if (
    $eleccion &&
    $eleccionAbierta
) { ?>


<a
    href="cerrar_eleccion.php?id=<?php
        echo $idEleccion;
    ?>"
    class="acceso rojo"
    onclick="
        return confirm(
            '¿Está seguro de cerrar esta elección?'
        );
    "
>


<i class="bi bi-lock-fill"></i>

Cerrar Elección


</a>


<?php } ?>


<!-- CERRAR SESIÓN -->

<a
    href="logout.php"
    class="acceso oscuro"
    onclick="
        return confirm(
            '¿Desea cerrar sesión?'
        );
    "
>


<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión


</a>


</div>


</section>


<!-- =================================================
     INFORMACIÓN FINAL
================================================= -->

<section class="card-custom">


<div class="card-title">


<i class="bi bi-info-circle-fill"></i>

Resumen del sistema


</div>


<div class="info-row">


<strong>

Elección actual

</strong>


<span>

<?php

echo htmlspecialchars(
    $nombreEleccion,
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>


</div>


<div class="info-row">


<strong>

Jurados registrados

</strong>


<span>

<?php

echo $totalJurados;

?>

</span>


</div>


<div class="info-row">


<strong>

Mesas de votación

</strong>


<span>

<?php

echo $totalMesas;

?>

</span>


</div>


<div class="info-row">


<strong>

Candidatos

</strong>


<span>

<?php

echo $totalCandidatos;

?>

</span>


</div>


<div class="info-row">


<strong>

Votos registrados

</strong>


<span>

<?php

echo $totalVotos;

?>

</span>


</div>


<div class="info-row">


<strong>

Participación

</strong>


<span>

<?php

echo $participacion;

?>%


</span>


</div>


</section>


<!-- =================================================
     FOOTER
================================================= -->

<footer class="footer">


<strong>

Sistema de Votaciones Escolares v2.0

</strong>


<br><br>


Desarrollado por

<strong>

Juan David Otero Cantor

</strong>


<br><br>


©

<?php

echo date("Y");

?>

Todos los derechos reservados.


</footer>


</div>


</main>


<!-- =====================================================
     GRÁFICA
===================================================== -->

<?php if (
    count($etiquetasGrafica) > 0
) { ?>


<script>


const etiquetas =
<?php

echo json_encode(
    $etiquetasGrafica,
    JSON_UNESCAPED_UNICODE
);

?>;


const valores =
<?php

echo json_encode(
    $valoresGrafica
);

?>;


const canvas =
document.getElementById(
    "graficaElecciones"
);


if (
    canvas
) {

    new Chart(
        canvas,
        {

            type: "bar",

            data: {

                labels: etiquetas,

                datasets: [

                    {

                        label:
                            "Votos registrados",

                        data:
                            valores,

                        borderWidth:
                            1

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        display: true

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            precision: 0

                        }

                    }

                }

            }

        }
    );

}


</script>


<?php } ?>


</body>

</html>
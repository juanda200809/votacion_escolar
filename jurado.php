<?php

session_start();

require_once "config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


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


$rol =
    strtolower(
        trim(
            (string)$_SESSION['rol']
        )
    );


if ($rol !== "jurado") {

    header("Location: login.php");
    exit();

}


$idJurado =
    (int)$_SESSION['id'];


$nombreJurado =
    $_SESSION['nombre']
    ??
    "Jurado";


/* =========================================================
   BLOQUEAR PANEL SI HAY UNA VOTACIÓN EN CURSO
========================================================= */

if (
    isset($_SESSION['votacion_en_curso']) &&
    $_SESSION['votacion_en_curso'] === true &&
    isset($_SESSION['estudiante_votando_id']) &&
    (int)$_SESSION['estudiante_votando_id'] > 0
) {

    header(
        "Location: votar_por_jurado.php"
    );

    exit();

}


/* =========================================================
   OBTENER ELECCIÓN ACTUAL
========================================================= */

$stmtEleccion =
    $conn->prepare("
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


$stmtEleccion->execute();


$resultadoEleccion =
    $stmtEleccion->get_result();


$eleccion =
    $resultadoEleccion->fetch_assoc();


$stmtEleccion->close();


if (!$eleccion) {

    die(
        "No existe ninguna elección registrada."
    );

}


$idEleccion =
    (int)$eleccion['id'];


$estadoEleccion =
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    );


$eleccionAbierta =
    $estadoEleccion === "abierta";


/* =========================================================
   OBTENER MESA DEL JURADO
========================================================= */

$stmtMesa =
    $conn->prepare("
        SELECT
            id,
            id_eleccion,
            id_jurado,
            nombre_mesa,
            estado,
            fecha_cierre
        FROM mesas_votacion
        WHERE id_eleccion = ?
          AND id_jurado = ?
        LIMIT 1
    ");


$stmtMesa->bind_param(
    "ii",
    $idEleccion,
    $idJurado
);


$stmtMesa->execute();


$resultadoMesa =
    $stmtMesa->get_result();


$mesa =
    $resultadoMesa->fetch_assoc();


$stmtMesa->close();


/* =========================================================
   ESTADO DE LA MESA
========================================================= */

$mesaExiste =
    !empty($mesa);


$estadoMesa =
    $mesaExiste
    ? strtolower(
        trim(
            (string)$mesa['estado']
        )
    )
    : "";


$mesaAbierta =
    $mesaExiste &&
    $estadoMesa === "abierta";


$mesaCerrada =
    $mesaExiste &&
    $estadoMesa === "cerrada";


$puedeComenzar =
    $eleccionAbierta &&
    $mesaAbierta;


/* =========================================================
   GUARDAR ELECCIÓN EN SESIÓN
========================================================= */

$_SESSION['id_eleccion_jurado'] =
    $idEleccion;

$_SESSION['id_eleccion'] =
    $idEleccion;


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;

$totalVotantes = 0;

$totalPendientes = 0;

$totalCandidatos = 0;

$totalVotos = 0;


/* =========================================================
   ESTUDIANTES
========================================================= */

$stmt =
    $conn->prepare("
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE LOWER(TRIM(rol)) = 'estudiante'
    ");


$stmt->execute();


$resultado =
    $stmt->get_result();


$fila =
    $resultado->fetch_assoc();


$totalEstudiantes =
    (int)($fila['total'] ?? 0);


$stmt->close();


/* =========================================================
   ESTUDIANTES QUE YA VOTARON
========================================================= */

$stmt =
    $conn->prepare("
        SELECT COUNT(DISTINCT id_usuario) AS total
        FROM votos
        WHERE id_eleccion = ?
    ");


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
    (int)($fila['total'] ?? 0);


$stmt->close();


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
   CANDIDATOS
========================================================= */

$stmt =
    $conn->prepare("
        SELECT COUNT(*) AS total
        FROM candidatos
        WHERE id_eleccion = ?
    ");


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
    (int)($fila['total'] ?? 0);


$stmt->close();


/* =========================================================
   VOTOS
========================================================= */

$stmt =
    $conn->prepare("
        SELECT COUNT(*) AS total
        FROM votos
        WHERE id_eleccion = ?
    ");


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
    (int)($fila['total'] ?? 0);


$stmt->close();


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion = 0;


if (
    $totalEstudiantes > 0
) {

    $participacion =
        round(
            (
                $totalVotantes /
                $totalEstudiantes
            ) * 100
        );

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
   URL DE VOTACIÓN
========================================================= */

$urlComenzar =
    "ingresar_estudiante.php?token="
    .
    urlencode(
        $tokenVotacion
    );


/* =========================================================
   FECHA
========================================================= */

function fechaBonita($fecha)
{

    if (
        empty($fecha)
    ) {

        return "No definida";

    }


    $timestamp =
        strtotime($fecha);


    if (!$timestamp) {

        return htmlspecialchars(
            $fecha
        );

    }


    return date(
        "d/m/Y H:i",
        $timestamp
    );

}


?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="robots"
    content="noindex, nofollow"
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

    background:
        #eef4fb;

    color:
        #164f96;

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

    display: flex;

    flex-direction: column;

}


.sidebar-header {

    text-align: center;

    padding:
        30px 15px 25px;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            .15
        );

}


.logo-jurado {

    font-size: 48px;

    margin-bottom: 8px;

}


.sidebar-header h1 {

    font-size: 25px;

    margin-bottom: 5px;

}


.sidebar-header p {

    font-size: 13px;

    opacity: .8;

}


.menu {

    padding-top: 15px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    color: white;

    text-decoration: none;

    padding:
        15px 22px;

    font-size: 15px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

}


.menu a.active {

    background:
        rgba(
            0,
            0,
            0,
            .16
        );

}


.menu a i {

    font-size: 20px;

    width: 25px;

    text-align: center;

}


.menu a.disabled {

    background:
        rgba(
            0,
            0,
            0,
            .12
        );

    color:
        rgba(
            255,
            255,
            255,
            .55
        );

    cursor:
        not-allowed;

}


.menu a.disabled i {

    color:
        rgba(
            255,
            255,
            255,
            .45
        );

}


.menu-separador {

    height: 1px;

    background:
        rgba(
            255,
            255,
            255,
            .15
        );

    margin:
        12px 20px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    width:
        calc(
            100% - 250px
        );

}


.topbar {

    height: 70px;

    background: white;

    border-bottom:
        1px solid #dce5ef;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

}


.topbar-title {

    font-weight: 700;

    font-size: 20px;

    color:
        #1453a3;

}


.topbar-user {

    color:
        #64748b;

    font-size: 14px;

}


.topbar-user i {

    color:
        #1453a3;

}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    padding:
        35px;

    max-width:
        1300px;

    margin:
        auto;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background: white;

    border-radius: 18px;

    padding:
        30px;

    margin-bottom:
        25px;

    box-shadow:
        0 7px 22px
        rgba(
            0,
            50,
            100,
            .07
        );

}


.bienvenida h1 {

    color:
        #1453a3;

    margin-bottom:
        10px;

    font-size:
        36px;

}


.bienvenida p {

    color:
        #64748b;

    line-height:
        1.6;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion-card {

    background: white;

    border-radius: 18px;

    padding:
        25px;

    margin-bottom:
        25px;

    box-shadow:
        0 7px 22px
        rgba(
            0,
            50,
            100,
            .07
        );

}


.eleccion-top {

    display: flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap:
        20px;

}


.eleccion-titulo {

    color:
        #1453a3;

    font-size:
        25px;

    font-weight:
        700;

    margin-bottom:
        8px;

}


.eleccion-descripcion {

    color:
        #64748b;

    line-height:
        1.5;

}


.estado-eleccion {

    padding:
        9px 15px;

    border-radius:
        30px;

    font-size:
        13px;

    font-weight:
        700;

    white-space:
        nowrap;

}


.estado-eleccion.abierta {

    background:
        #d1fae5;

    color:
        #047857;

}


.estado-eleccion.cerrada {

    background:
        #e5e7eb;

    color:
        #4b5563;

}


/* =========================================================
   MESA
========================================================= */

.mesa-card {

    border-radius:
        18px;

    padding:
        25px;

    margin-bottom:
        25px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        25px;

}


.mesa-card.abierta {

    background:
        linear-gradient(
            135deg,
            #effdf5,
            #e5f9ee
        );

    border:
        1px solid #a7e7bd;

}


.mesa-card.cerrada {

    background:
        linear-gradient(
            135deg,
            #f3f4f6,
            #e5e7eb
        );

    border:
        1px solid #cbd5e1;

}


.mesa-info {

    display:
        flex;

    align-items:
        center;

    gap:
        18px;

}


.mesa-icon {

    width:
        65px;

    height:
        65px;

    border-radius:
        16px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        31px;

}


.mesa-card.abierta
.mesa-icon {

    background:
        #d1fae5;

}


.mesa-card.cerrada
.mesa-icon {

    background:
        #e2e8f0;

}


.mesa-info h3 {

    margin:
        0 0 6px;

    color:
        #1453a3;

    font-size:
        22px;

}


.mesa-info p {

    margin:
        0;

    color:
        #64748b;

}


.mesa-estado {

    display:
        inline-block;

    margin-top:
        8px;

    font-size:
        13px;

    font-weight:
        700;

}


.mesa-card.abierta
.mesa-estado {

    color:
        #047857;

}


.mesa-card.cerrada
.mesa-estado {

    color:
        #64748b;

}


/* =========================================================
   COMENZAR VOTACIÓN
========================================================= */

.comenzar-card {

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f7fbff
        );

    border:
        1px solid #dbe8f5;

    border-radius:
        20px;

    padding:
        30px;

    margin-bottom:
        30px;

    box-shadow:
        0 8px 25px
        rgba(
            20,
            83,
            150,
            .08
        );

}


.comenzar-contenido {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        25px;

}


.comenzar-info {

    display:
        flex;

    align-items:
        center;

    gap:
        20px;

}


.comenzar-icon {

    width:
        70px;

    height:
        70px;

    border-radius:
        18px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #e5efff;

    color:
        #1473ed;

    font-size:
        34px;

}


.comenzar-info h2 {

    color:
        #1453a3;

    margin-bottom:
        7px;

}


.comenzar-info p {

    color:
        #64748b;

    margin:
        0;

    line-height:
        1.5;

}


/* BOTÓN AZUL */

.btn-comenzar {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        10px;

    min-width:
        270px;

    padding:
        17px 28px;

    border-radius:
        13px;

    background:
        linear-gradient(
            135deg,
            #1473ed,
            #075bc4
        );

    color:
        white;

    text-decoration:
        none;

    font-size:
        18px;

    font-weight:
        700;

    box-shadow:
        0 8px 20px
        rgba(
            20,
            115,
            237,
            .25
        );

    transition:
        .2s;

}


.btn-comenzar:hover {

    color:
        white;

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 27px
        rgba(
            20,
            115,
            237,
            .30
        );

}


/* BOTÓN GRIS */

.btn-comenzar-bloqueado {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        10px;

    min-width:
        270px;

    padding:
        17px 28px;

    border-radius:
        13px;

    background:
        #cbd5e1;

    color:
        #64748b;

    font-size:
        18px;

    font-weight:
        700;

    cursor:
        not-allowed;

    border:
        none;

    box-shadow:
        none;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display:
        grid;

    grid-template-columns:
        repeat(
            5,
            1fr
        );

    gap:
        18px;

    margin-bottom:
        30px;

}


.stat {

    background:
        white;

    border-radius:
        17px;

    padding:
        25px 15px;

    text-align:
        center;

    box-shadow:
        0 7px 20px
        rgba(
            0,
            50,
            100,
            .07
        );

}


.stat i {

    font-size:
        31px;

    color:
        #1473ed;

    margin-bottom:
        10px;

}


.stat-numero {

    color:
        #1453a3;

    font-size:
        30px;

    font-weight:
        700;

}


.stat-texto {

    color:
        #64748b;

    margin-top:
        5px;

    font-size:
        13px;

}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.card-custom {

    background:
        white;

    border-radius:
        18px;

    padding:
        27px;

    margin-bottom:
        25px;

    box-shadow:
        0 7px 22px
        rgba(
            0,
            50,
            100,
            .07
        );

}


.card-title {

    color:
        #1453a3;

    font-size:
        21px;

    font-weight:
        700;

    margin-bottom:
        20px;

}


.participacion-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

}


.participacion-header h3 {

    color:
        #1453a3;

    font-size:
        21px;

}


.porcentaje {

    color:
        #1473ed;

    font-size:
        25px;

    font-weight:
        700;

}


.barra {

    height:
        13px;

    background:
        #e5edf7;

    border-radius:
        20px;

    overflow:
        hidden;

    margin:
        15px 0;

}


.progreso {

    height:
        100%;

    background:
        linear-gradient(
            90deg,
            #1473ed,
            #16a8d8
        );

    border-radius:
        20px;

}


.datos-participacion {

    display:
        flex;

    gap:
        25px;

    flex-wrap:
        wrap;

    color:
        #64748b;

    font-size:
        14px;

}


/* =========================================================
   ACCESOS
========================================================= */

.accesos {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        18px;

}


.acceso {

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        10px;

    padding:
        18px;

    border-radius:
        12px;

    color:
        white;

    text-decoration:
        none;

    font-weight:
        700;

    transition:
        .2s;

}


.acceso:hover {

    color:
        white;

    transform:
        translateY(-2px);

}


.acceso.azul {

    background:
        #1473ed;

}


.acceso.verde {

    background:
        #198754;

}


.acceso.celeste {

    background:
        #16a8d8;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top:
        35px;

    padding:
        24px 10px;

    text-align:
        center;

    border-top:
        1px solid
        #dce5ef;

    color:
        #72869d;

    font-size:
        13px;

}


.footer strong {

    color:
        #2c557e;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 1100px
) {

    .estadisticas {

        grid-template-columns:
            repeat(
                3,
                1fr
            );

    }

}


@media (
    max-width: 850px
) {

    .sidebar {

        position:
            relative;

        width:
            100%;

        height:
            auto;

    }


    .main {

        margin-left:
            0;

        width:
            100%;

    }


    .contenido {

        padding:
            20px;

    }


    .estadisticas {

        grid-template-columns:
            repeat(
                2,
                1fr
            );

    }


    .accesos {

        grid-template-columns:
            1fr;

    }


    .topbar-user {

        display:
            none;

    }


    .comenzar-contenido {

        flex-direction:
            column;

        align-items:
            flex-start;

    }


    .btn-comenzar,
    .btn-comenzar-bloqueado {

        width:
            100%;

    }


    .mesa-card {

        flex-direction:
            column;

        align-items:
            flex-start;

    }


    .eleccion-top {

        flex-direction:
            column;

    }

}


@media (
    max-width: 550px
) {

    .estadisticas {

        grid-template-columns:
            1fr;

    }


    .bienvenida h1 {

        font-size:
            30px;

    }


    .comenzar-info {

        align-items:
            flex-start;

    }


    .comenzar-icon {

        display:
            none;

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


        <?php if ($puedeComenzar): ?>


            <a
                href="<?php
                    echo htmlspecialchars(
                        $urlComenzar
                    );
                ?>"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="bi bi-ballot-check-fill"></i>

                <span>
                    Comenzar votación
                </span>

            </a>


        <?php else: ?>


            <a
                href="#"
                class="disabled"
                onclick="return false;"
                title="La mesa de votación está cerrada"
            >

                <i class="bi bi-lock-fill"></i>

                <span>
                    Mesa cerrada
                </span>

            </a>


        <?php endif; ?>


        <a
            href="resultados.php?id_eleccion=<?php
                echo $idEleccion;
            ?>"
        >

            <i class="bi bi-trophy-fill"></i>

            <span>
                Resultados
            </span>

        </a>


        <a
            href="graficas.php?id_eleccion=<?php
                echo $idEleccion;
            ?>"
        >

            <i class="bi bi-bar-chart-fill"></i>

            <span>
                Gráficas
            </span>

        </a>


        <div class="menu-separador"></div>


        <a
            href="logout.php"
            onclick="
                return confirm(
                    '¿Está seguro de cerrar sesión?'
                );
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
     CONTENIDO PRINCIPAL
===================================================== -->

<main class="main">


<header class="topbar">


    <div class="topbar-title">

        ⚖️ Sistema de Votaciones Escolares

    </div>


    <div class="topbar-user">

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


<section class="contenido">


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="bienvenida">


    <h1>

        Bienvenido,

        <?php

        echo htmlspecialchars(
            $nombreJurado
        );

        ?>

        👋

    </h1>


    <p>

        Desde este panel puede gestionar
        el proceso de votación de los estudiantes
        y consultar los resultados electorales.

    </p>


</div>


<!-- =====================================================
     ELECCIÓN ACTUAL
===================================================== -->

<section class="eleccion-card">


    <div class="eleccion-top">


        <div>


            <div class="eleccion-titulo">

                🗳️

                <?php

                echo htmlspecialchars(
                    $eleccion['nombre']
                );

                ?>

            </div>


            <p class="eleccion-descripcion">

                <?php

                echo htmlspecialchars(
                    $eleccion['descripcion']
                    ??
                    'Proceso democrático institucional'
                );

                ?>

            </p>


        </div>


        <?php if ($eleccionAbierta): ?>


            <span
                class="estado-eleccion abierta"
            >

                🟢 Elección abierta

            </span>


        <?php else: ?>


            <span
                class="estado-eleccion cerrada"
            >

                🔒 Elección cerrada

            </span>


        <?php endif; ?>


    </div>


    <div
        style="
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:15px;
            margin-top:20px;
        "
    >


        <div
            style="
                background:#f8fafc;
                border:1px solid #e2e8f0;
                border-radius:12px;
                padding:15px;
            "
        >

            📅 <strong>
                Inicio
            </strong>

            <br>

            <span
                style="
                    color:#64748b;
                "
            >

                <?php

                echo fechaBonita(
                    $eleccion['fecha_inicio']
                );

                ?>

            </span>

        </div>


        <div
            style="
                background:#f8fafc;
                border:1px solid #e2e8f0;
                border-radius:12px;
                padding:15px;
            "
        >

            📅 <strong>
                Finalización
            </strong>

            <br>

            <span
                style="
                    color:#64748b;
                "
            >

                <?php

                echo fechaBonita(
                    $eleccion['fecha_fin']
                );

                ?>

            </span>

        </div>


    </div>


</section>


<!-- =====================================================
     ESTADO DE LA MESA
===================================================== -->

<?php if ($mesaExiste): ?>


    <?php if ($mesaAbierta): ?>


        <section
            class="mesa-card abierta"
        >


            <div class="mesa-info">


                <div class="mesa-icon">

                    🗳️

                </div>


                <div>


                    <h3>

                        <?php

                        echo htmlspecialchars(
                            $mesa['nombre_mesa']
                        );

                        ?>

                    </h3>


                    <p>

                        Mesa asignada al jurado

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $nombreJurado
                            );

                            ?>

                        </strong>

                    </p>


                    <div class="mesa-estado">

                        🟢 Mesa de votación abierta

                    </div>


                </div>


            </div>


        </section>


    <?php else: ?>


        <section
            class="mesa-card cerrada"
        >


            <div class="mesa-info">


                <div class="mesa-icon">

                    🔒

                </div>


                <div>


                    <h3>

                        <?php

                        echo htmlspecialchars(
                            $mesa['nombre_mesa']
                        );

                        ?>

                    </h3>


                    <p>

                        Esta mesa pertenece al jurado

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $nombreJurado
                            );

                            ?>

                        </strong>

                    </p>


                    <div class="mesa-estado">

                        🔒 Mesa de votación cerrada

                    </div>


                    <?php if (
                        !empty(
                            $mesa['fecha_cierre']
                        )
                    ): ?>


                        <p
                            style="
                                margin-top:7px;
                                font-size:13px;
                            "
                        >

                            📅 Cerrada:

                            <?php

                            echo fechaBonita(
                                $mesa['fecha_cierre']
                            );

                            ?>

                        </p>


                    <?php endif; ?>


                </div>


            </div>


        </section>


    <?php endif; ?>


<?php else: ?>


    <section
        class="mesa-card cerrada"
    >


        <div class="mesa-info">


            <div class="mesa-icon">

                ⚠️

            </div>


            <div>


                <h3>

                    Mesa no asignada

                </h3>


                <p>

                    Este jurado no tiene una mesa
                    asignada para la elección actual.

                </p>


                <div class="mesa-estado">

                    🚫 No puede iniciar votaciones

                </div>


            </div>


        </div>


    </section>


<?php endif; ?>


<!-- =====================================================
     COMENZAR VOTACIÓN
===================================================== -->

<section class="comenzar-card">


    <div class="comenzar-contenido">


        <div class="comenzar-info">


            <div class="comenzar-icon">

                🪪

            </div>


            <div>


                <h2>

                    Comenzar votación

                </h2>


                <?php if ($puedeComenzar): ?>


                    <p>

                        Identifique al estudiante mediante
                        su número de documento para iniciar
                        su proceso de votación.

                    </p>


                <?php elseif ($mesaCerrada): ?>


                    <p>

                        🔒 La mesa de votación está cerrada.
                        Ya no se permiten nuevos votos
                        desde esta mesa.

                    </p>


                <?php elseif (!$mesaExiste): ?>


                    <p>

                        ⚠️ No existe una mesa asignada
                        a este jurado para esta elección.

                    </p>


                <?php else: ?>


                    <p>

                        🔒 La elección general está cerrada.

                    </p>


                <?php endif; ?>


            </div>


        </div>


        <?php if ($puedeComenzar): ?>


            <a
                href="<?php
                    echo htmlspecialchars(
                        $urlComenzar
                    );
                ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-comenzar"
            >

                <i class="bi bi-play-circle-fill"></i>

                🗳️ Comenzar votación

            </a>


        <?php else: ?>


            <button
                type="button"
                class="btn-comenzar-bloqueado"
                disabled
            >

                🔒 Mesa cerrada

            </button>


        <?php endif; ?>


    </div>


</section>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="estadisticas">


    <div class="stat">

        <i class="bi bi-people-fill"></i>


        <div class="stat-numero">

            <?php

            echo $totalEstudiantes;

            ?>

        </div>


        <div class="stat-texto">

            Estudiantes

        </div>

    </div>


    <div class="stat">

        <i class="bi bi-person-check-fill"></i>


        <div class="stat-numero">

            <?php

            echo $totalVotantes;

            ?>

        </div>


        <div class="stat-texto">

            Han votado

        </div>

    </div>


    <div class="stat">

        <i class="bi bi-person-exclamation"></i>


        <div class="stat-numero">

            <?php

            echo $totalPendientes;

            ?>

        </div>


        <div class="stat-texto">

            Pendientes

        </div>

    </div>


    <div class="stat">

        <i class="bi bi-person-vcard"></i>


        <div class="stat-numero">

            <?php

            echo $totalCandidatos;

            ?>

        </div>


        <div class="stat-texto">

            Candidatos

        </div>

    </div>


    <div class="stat">

        <i class="bi bi-check2-square"></i>


        <div class="stat-numero">

            <?php

            echo $totalVotos;

            ?>

        </div>


        <div class="stat-texto">

            Votos registrados

        </div>

    </div>


</div>


<!-- =====================================================
     PARTICIPACIÓN
===================================================== -->

<section class="card-custom">


    <div class="participacion-header">


        <h3>

            📊 Participación electoral

        </h3>


        <div class="porcentaje">

            <?php

            echo $participacion;

            ?>%

        </div>


    </div>


    <div class="barra">


        <div
            class="progreso"
            style="
                width:
                <?php
                echo $participacion;
                ?>%;
            "
        ></div>


    </div>


    <div class="datos-participacion">


        <span>

            👥

            <?php

            echo $totalVotantes;

            ?>

            estudiantes han votado

        </span>


        <span>

            ⏳

            <?php

            echo $totalPendientes;

            ?>

            pendientes

        </span>


        <span>

            🗳️

            <?php

            echo $totalVotos;

            ?>

            votos registrados

        </span>


    </div>


</section>


<!-- =====================================================
     ACCESOS RÁPIDOS
===================================================== -->

<section class="card-custom">


    <div class="card-title">

        ⚡ Accesos rápidos

    </div>


    <div class="accesos">


        <?php if ($puedeComenzar): ?>


            <a
                href="<?php
                    echo htmlspecialchars(
                        $urlComenzar
                    );
                ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="acceso azul"
            >

                🗳️

                Comenzar votación

            </a>


        <?php else: ?>


            <span
                class="acceso"
                style="
                    background:#cbd5e1;
                    color:#64748b;
                    cursor:not-allowed;
                "
            >

                🔒

                Mesa cerrada

            </span>


        <?php endif; ?>


        <a
            href="resultados.php?id_eleccion=<?php
                echo $idEleccion;
            ?>"
            class="acceso verde"
        >

            🏆

            Ver resultados

        </a>


        <a
            href="graficas.php?id_eleccion=<?php
                echo $idEleccion;
            ?>"
            class="acceso celeste"
        >

            📊

            Ver gráficas

        </a>


    </div>


</section>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">


    <div>

        ©

        <?php

        echo date("Y");

        ?>

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
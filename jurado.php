
<?php

session_start();

include("config/conexion.php");


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


$rol = strtolower(
    trim(
        (string) $_SESSION['rol']
    )
);


/* =========================================================
   SOLO JURADO
========================================================= */

if ($rol !== "jurado") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    header("Location: login.php");
    exit();

}


/* =========================================================
   PROTEGER PANEL DURANTE UNA VOTACIÓN
========================================================= */

/*
 * Si existe un estudiante en proceso de votación,
 * significa que el jurado ya inició una votación.
 *
 * En ese momento NO permitimos regresar al panel.
 *
 * Esto evita que se pueda escribir manualmente:
 *
 * jurado.php
 *
 * mientras el estudiante está votando.
 */

if (
    isset($_SESSION['estudiante_votando_id']) &&
    (int)$_SESSION['estudiante_votando_id'] > 0
) {

    /*
     * Comprobamos también que exista
     * la elección correspondiente.
     */

    if (
        isset($_SESSION['eleccion_votante_id']) &&
        (int)$_SESSION['eleccion_votante_id'] > 0
    ) {

        header(
            "Location: votar_por_jurado.php"
        );

        exit();

    }

}


/* =========================================================
   DATOS DEL JURADO
========================================================= */

$nombreJurado =
    $_SESSION['nombre'] ?? "Jurado";

$idJurado =
    (int) $_SESSION['id'];


/* =========================================================
   ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;


$resultadoEleccion =
    $conn->query("

        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado

        FROM elecciones

        WHERE estado = 'abierta'

        ORDER BY id DESC

        LIMIT 1

    ");


if (
    $resultadoEleccion &&
    $resultadoEleccion->num_rows > 0
) {

    $eleccion =
        $resultadoEleccion->fetch_assoc();

}


/* =========================================================
   DATOS DE LA ELECCIÓN
========================================================= */

$idEleccion = 0;

$nombreEleccion =
    "No hay elección abierta";

$descripcionEleccion =
    "Actualmente no existe una elección abierta.";

$fechaInicio = "";

$fechaFin = "";


if ($eleccion) {

    $idEleccion =
        (int) $eleccion['id'];

    $nombreEleccion =
        $eleccion['nombre'];

    $descripcionEleccion =
        $eleccion['descripcion'];

    $fechaInicio =
        $eleccion['fecha_inicio'];

    $fechaFin =
        $eleccion['fecha_fin'];

}


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$totalEstudiantes = 0;


$resultado =
    $conn->query("

        SELECT
            COUNT(*) AS total

        FROM usuarios

        WHERE LOWER(
            TRIM(rol)
        ) = 'estudiante'

    ");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int) $fila['total'];

}


/* =========================================================
   TOTAL VOTOS
========================================================= */

$totalVotos = 0;


if (
    $idEleccion > 0
) {

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
            (int) $fila['total'];

        $stmt->close();

    }

}


/* =========================================================
   ESTUDIANTES QUE YA VOTARON
========================================================= */

$totalVotantes = 0;


if (
    $idEleccion > 0
) {

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
            (int) $fila['total'];

        $stmt->close();

    }

}


/* =========================================================
   ESTUDIANTES PENDIENTES
========================================================= */

$totalPendientes =
    $totalEstudiantes -
    $totalVotantes;


if (
    $totalPendientes < 0
) {

    $totalPendientes = 0;

}


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
            ) * 100,
            1
        );

}


if (
    $participacion > 100
) {

    $participacion = 100;

}


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

$totalCandidatos = 0;


if (
    $idEleccion > 0
) {

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
            (int) $fila['total'];

        $stmt->close();

    }

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

<title>
Panel del Jurado | Votaciones Escolares
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
>


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
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef3f9;

    color: #26374a;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    bottom: 0;

    width: 250px;

    background: #1453a3;

    color: white;

    overflow-y: auto;

    z-index: 1000;

    box-shadow:
        3px 0 15px
        rgba(0,0,0,.12);

}


.logo {

    text-align: center;

    padding:
        28px 15px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);

}


.logo-icon {

    font-size: 58px;

    margin-bottom: 5px;

}


.logo h1 {

    margin: 0;

    font-size: 31px;

    font-weight: 800;

}


.logo p {

    margin-top: 5px;

    font-size: 13px;

    opacity: .85;

}


/* =========================================================
   MENÚ
========================================================= */

.menu {

    padding-top: 12px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    color: white;

    text-decoration: none;

    padding:
        15px 25px;

    font-size: 15px;

    transition: .2s ease;

}


.menu a:hover {

    background: #0d4388;

    padding-left: 30px;

}


.menu a.activo {

    background:
        rgba(255,255,255,.18);

    border-radius: 8px;

    margin:
        4px 15px;

    padding-left: 20px;

}


.menu a i {

    width: 25px;

    text-align: center;

    font-size: 19px;

}


.menu-separador {

    height: 1px;

    background:
        rgba(255,255,255,.20);

    margin:
        15px 20px;

}


/* =========================================================
   CONTENIDO
========================================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;

}


.topbar {

    min-height: 70px;

    background: #1473ed;

    color: white;

    padding:
        0 32px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.topbar h4 {

    margin: 0;

    font-size: 23px;

}


.topbar-user {

    font-weight: 600;

}


.contenido {

    padding: 35px;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    margin-bottom: 25px;

}


.bienvenida h2 {

    color: #1453a3;

    font-size: 40px;

    margin-bottom: 7px;

}


.bienvenida p {

    color: #64748b;

    font-size: 16px;

}


/* =========================================================
   COMENZAR VOTACIÓN
========================================================= */

.buscar-card {

    background:
        linear-gradient(
            135deg,
            #176df0,
            #06479f
        );

    color: white;

    padding: 30px;

    border-radius: 20px;

    box-shadow:
        0 10px 25px
        rgba(0,70,160,.25);

}


.buscar-titulo {

    font-size: 32px;

    font-weight: bold;

    margin-bottom: 7px;

}


.buscar-descripcion {

    margin-bottom: 22px;

    font-size: 15px;

}


.inicio-votacion {

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.25);

    border-radius: 14px;

    padding: 22px;

    margin-top: 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

}


.inicio-info {

    display: flex;

    align-items: center;

    gap: 16px;

}


.inicio-icono {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    background: white;

    color: #1473ed;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 26px;

    flex-shrink: 0;

}


.inicio-info strong {

    display: block;

    font-size: 18px;

    margin-bottom: 4px;

}


.inicio-info span {

    font-size: 14px;

    opacity: .9;

}


.btn-comenzar {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    background: white;

    color: #1453a3;

    padding:
        13px 22px;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

    white-space: nowrap;

    transition: .2s ease;

}


.btn-comenzar:hover {

    background: #edf4ff;

    color: #0d4388;

    transform:
        translateY(-2px);

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 18px;

    margin-top: 30px;

}


.stat {

    background: white;

    border-radius: 18px;

    padding:
        23px 12px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.09);

    transition: .2s ease;

}


.stat:hover {

    transform:
        translateY(-4px);

}


.stat i {

    font-size: 40px;

    color: #1473ed;

}


.stat-numero {

    color: #1453a3;

    font-size: 32px;

    font-weight: bold;

    margin-top: 5px;

}


.stat-texto {

    color: #64748b;

    font-size: 14px;

    font-weight: 600;

}


/* =========================================================
   TARJETAS
========================================================= */

.card-custom {

    background: white;

    border-radius: 18px;

    padding: 28px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.09);

}


.card-title {

    color: #1453a3;

    font-size: 26px;

    font-weight: bold;

    margin-bottom: 18px;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion-box {

    background: #f7faff;

    border:
        1px solid #d7e4f7;

    border-radius: 15px;

    padding: 23px;

}


.eleccion-box h3 {

    color: #1453a3;

    font-size: 24px;

    font-weight: bold;

}


.eleccion-box p {

    color: #64748b;

}


.estado {

    display: inline-block;

    padding:
        7px 15px;

    border-radius: 8px;

    font-weight: bold;

}


.estado-abierta {

    background: #198754;

    color: white;

}


.estado-cerrada {

    background: #dc3545;

    color: white;

}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participacion-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.participacion-header h3 {

    color: #1453a3;

    font-size: 25px;

    margin: 0;

}


.porcentaje {

    color: #1473ed;

    font-size: 30px;

    font-weight: bold;

}


.barra {

    width: 100%;

    height: 17px;

    background: #e7edf5;

    border-radius: 20px;

    overflow: hidden;

    margin-top: 17px;

}


.progreso {

    height: 100%;

    background:
        linear-gradient(
            90deg,
            #1473ed,
            #198754
        );

    border-radius: 20px;

    transition:
        width .5s ease;

}


.datos-participacion {

    display: flex;

    justify-content: space-between;

    color: #64748b;

    font-size: 14px;

    margin-top: 10px;

}


/* =========================================================
   ACCESOS RÁPIDOS
========================================================= */

.accesos {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

}


.acceso {

    text-decoration: none;

    color: white;

    padding:
        25px;

    min-height: 145px;

    border-radius: 15px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    text-align: center;

    transition: .2s ease;

}


.acceso:hover {

    transform:
        translateY(-4px);

    color: white;

    box-shadow:
        0 8px 18px
        rgba(0,0,0,.15);

}


.acceso i {

    font-size: 38px;

    margin-bottom: 10px;

}


.azul {

    background: #1473ed;

}


.verde {

    background: #198754;

}


.celeste {

    background: #16a8d8;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top: 45px;

    padding:
        30px 20px;

    background: white;

    border-top:
        1px solid #d9e1eb;

    text-align: center;

    color: #64748b;

    font-size: 13px;

}


.footer-principal {

    color: #1453a3;

    font-size: 15px;

    font-weight: bold;

    margin-bottom: 7px;

}


.footer-autor strong {

    color: #1453a3;

}


.footer-secundario {

    margin-top: 7px;

    color: #94a3b8;

    font-size: 11px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .estadisticas {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media(max-width:850px) {

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
            repeat(2, 1fr);

    }


    .accesos {

        grid-template-columns:
            1fr;

    }


    .topbar-user {

        display: none;

    }


    .inicio-votacion {

        flex-direction: column;

        align-items: flex-start;

    }


    .btn-comenzar {

        width: 100%;

    }

}


@media(max-width:550px) {

    .estadisticas {

        grid-template-columns:
            1fr;

    }


    .bienvenida h2 {

        font-size: 30px;

    }


    .buscar-titulo {

        font-size: 25px;

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
        JURADO
    </h1>

    <p>
        Panel de votaciones
    </p>

</div>


<nav class="menu">


<a
    href="jurado.php"
    class="activo"
>

    <i class="bi bi-house-fill"></i>

    Inicio

</a>


<a
    href="ingresar_estudiante.php"
>

    <i class="bi bi-ballot-check-fill"></i>

    Comenzar votación

</a>


<a
    href="resultados.php"
>

    <i class="bi bi-trophy-fill"></i>

    Resultados

</a>


<a
    href="graficas.php"
>

    <i class="bi bi-bar-chart-fill"></i>

    Gráficas

</a>


<div class="menu-separador"></div>


<a
    href="logout.php"
    onclick="
        return confirm('¿Desea cerrar sesión?');
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

<div class="main">


<header class="topbar">


<h4>

    ⚖️ Sistema de Votaciones Escolares

</h4>


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


<main class="contenido">


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="bienvenida">


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

    Desde este panel puede iniciar el proceso
    de votación de los estudiantes y consultar
    los resultados electorales.

</p>


</div>


<!-- =====================================================
     COMENZAR VOTACIÓN
===================================================== -->

<section class="buscar-card">


<div class="buscar-titulo">

    <i class="bi bi-ballot-check"></i>

    Comenzar votación

</div>


<div class="buscar-descripcion">

    Inicie el proceso de votación de un estudiante
    utilizando su número de documento.

</div>


<div class="inicio-votacion">


<div class="inicio-info">


<div class="inicio-icono">

    <i class="bi bi-person-vcard-fill"></i>

</div>


<div>

    <strong>

        Identificar estudiante

    </strong>


    <span>

        El estudiante deberá proporcionar
        su número de documento.

    </span>

</div>


</div>


<a
    href="ingresar_estudiante.php"
    class="btn-comenzar"
>

    <i class="bi bi-play-circle-fill"></i>

    Comenzar votación

</a>


</div>


</section>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<section class="estadisticas">


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

    <i class="bi bi-person-vcard-fill"></i>

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


</section>


<!-- =====================================================
     ELECCIÓN ACTUAL
===================================================== -->

<section class="card-custom">


<div class="card-title">

    <i class="bi bi-calendar-check-fill"></i>

    Elección actual

</div>


<div class="eleccion-box">


<h3>

    <?php

    echo htmlspecialchars(
        $nombreEleccion
    );

    ?>

</h3>


<p>

    <?php

    echo htmlspecialchars(
        $descripcionEleccion
    );

    ?>

</p>


<?php if (
    $idEleccion > 0
) { ?>


<span class="estado estado-abierta">

    🟢 Elección abierta

</span>


<?php if (
    $fechaInicio !== ""
) { ?>

    &nbsp;

    Inicio:

    <strong>

        <?php

        echo htmlspecialchars(
            date(
                "d/m/Y h:i A",
                strtotime(
                    $fechaInicio
                )
            )
        );

        ?>

    </strong>

<?php } ?>


&nbsp;&nbsp;


<?php if (
    $fechaFin !== ""
) { ?>

    Finalización:

    <strong>

        <?php

        echo htmlspecialchars(
            date(
                "d/m/Y h:i A",
                strtotime(
                    $fechaFin
                )
            )
        );

        ?>

    </strong>

<?php } ?>


<?php } else { ?>


<span class="estado estado-cerrada">

    🔴 No hay elección abierta

</span>


<?php } ?>


</div>


</section>


<!-- =====================================================
     PARTICIPACIÓN
===================================================== -->

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
    class="progreso"
    style="
        width: <?php echo $participacion; ?>%;
    "
></div>


</div>


<div class="datos-participacion">


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


</div>


</section>


<!-- =====================================================
     ACCESOS RÁPIDOS
===================================================== -->

<section class="card-custom">


<div class="card-title">

    <i class="bi bi-grid-fill"></i>

    Accesos rápidos

</div>


<div class="accesos">


<a
    href="ingresar_estudiante.php"
    class="acceso azul"
>

    <i class="bi bi-ballot-check-fill"></i>

    Comenzar votación

</a>


<a
    href="resultados.php"
    class="acceso verde"
>

    <i class="bi bi-trophy-fill"></i>

    Ver resultados

</a>


<a
    href="graficas.php"
    class="acceso celeste"
>

    <i class="bi bi-bar-chart-fill"></i>

    Ver gráficas

</a>


</div>


</section>


<!-- =====================================================
     PIE DE PÁGINA
===================================================== -->

<footer class="footer">


<div class="footer-principal">

    © <?php echo date("Y"); ?>

    Sistema de Votaciones Escolares

</div>


<div class="footer-autor">

    Elaborado por

    <strong>
        Juan David Otero Cantor
    </strong>

</div>


<div class="footer-secundario">

    Todos los derechos reservados

    <br>

    Proyecto de grado

    <br>

    Sistema de Votaciones Escolares

</div>


</footer>


</main>

</div>


</body>

</html>
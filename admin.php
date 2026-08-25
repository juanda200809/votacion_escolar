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
    $_SESSION['nombre'] ?? "Administrador";


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
        "La elección fue abierta correctamente.";

    $tipoMensaje = "success";

}


if (isset($_GET['cerrada'])) {

    $mensaje =
        "La elección fue cerrada correctamente.";

    $tipoMensaje = "success";

}


if (isset($_GET['eliminada'])) {

    $mensaje =
        "La elección fue eliminada correctamente.";

    $tipoMensaje = "success";

}


if (isset($_GET['error'])) {

    $tipoMensaje = "danger";


    switch ($_GET['error']) {

        case "no_eleccion":

            $mensaje =
                "No existe ninguna elección registrada.";

            break;


        case "abierta":

            $mensaje =
                "La elección ya se encuentra abierta.";

            break;


        case "cerrada":

            $mensaje =
                "La elección ya se encuentra cerrada.";

            break;


        case "abrir":

            $mensaje =
                "No se pudo abrir la elección.";

            break;


        case "cerrar":

            $mensaje =
                "No se pudo cerrar la elección.";

            break;


        default:

            $mensaje =
                "Ocurrió un error en la operación.";

            break;

    }

}


/* =========================================================
   CONTAR ESTUDIANTES
========================================================= */

$totalEstudiantes = 0;


$resultado =
    $conn->query("

        SELECT COUNT(*) AS total

        FROM usuarios

        WHERE LOWER(TRIM(rol))
        = 'estudiante'

    ");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];

}


/* =========================================================
   CONTAR JURADOS
========================================================= */

$totalJurados = 0;


$resultado =
    $conn->query("

        SELECT COUNT(*) AS total

        FROM usuarios

        WHERE LOWER(TRIM(rol))
        = 'jurado'

    ");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalJurados =
        (int)$fila['total'];

}


/* =========================================================
   OBTENER ÚLTIMA ELECCIÓN
========================================================= */

$eleccion = null;


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

}


/* =========================================================
   VARIABLES DE ELECCIÓN
========================================================= */

$idEleccion = 0;

$nombreEleccion =
    "Sin elección registrada";

$descripcionEleccion =
    "No existe una elección configurada.";

$fechaInicio = "";

$fechaFin = "";

$estadoEleccion =
    "cerrada";


if ($eleccion) {

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
                (string)$eleccion['estado']
            )
        );

}


$eleccionAbierta =
    ($estadoEleccion === "abierta");


/* =========================================================
   CONTAR CANDIDATOS
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
            (int)$fila['total'];

        $stmt->close();

    }

}


/* =========================================================
   CONTAR VOTOS
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
            (int)$fila['total'];

        $stmt->close();

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
                COUNT(DISTINCT id_usuario)
                AS total

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
            (int)$fila['total'];

        $stmt->close();

    }

}


/* =========================================================
   ESTUDIANTES PENDIENTES
========================================================= */

$totalPendientes =
    $totalEstudiantes -
    $totalVotantes;


if ($totalPendientes < 0) {

    $totalPendientes = 0;

}


/* =========================================================
   PORCENTAJE DE PARTICIPACIÓN
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
   BUSCADOR DE ESTUDIANTES
========================================================= */

$busqueda = "";

$resultadosBusqueda = [];


if (
    isset($_GET['buscar']) &&
    trim($_GET['buscar']) !== ""
) {

    $busqueda =
        trim($_GET['buscar']);


    $termino =
        "%" . $busqueda . "%";


    $stmt =
        $conn->prepare("

            SELECT

                id,
                documento,
                nombre,
                apellido,
                curso

            FROM usuarios

            WHERE

                LOWER(TRIM(rol))
                = 'estudiante'

                AND (

                    nombre LIKE ?

                    OR apellido LIKE ?

                    OR documento LIKE ?

                    OR curso LIKE ?

                )

            ORDER BY
                nombre ASC,
                apellido ASC

            LIMIT 20

        ");


    if ($stmt) {

        $stmt->bind_param(
            "ssss",
            $termino,
            $termino,
            $termino,
            $termino
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $resultadosBusqueda[] =
                $fila;

        }


        $stmt->close();

    }

}


/* =========================================================
   TODAS LAS ELECCIONES
========================================================= */

$elecciones = [];


$resultadoElecciones =
    $conn->query("

        SELECT

            e.id,
            e.nombre,
            e.descripcion,
            e.fecha_inicio,
            e.fecha_fin,
            e.estado,

            (
                SELECT COUNT(*)

                FROM votos v

                WHERE
                    v.id_eleccion = e.id

            ) AS total_votos

        FROM elecciones e

        ORDER BY e.id DESC

    ");


if ($resultadoElecciones) {

    while (
        $fila =
        $resultadoElecciones->fetch_assoc()
    ) {

        $elecciones[] =
            $fila;

    }

}


/* =========================================================
   DATOS PARA GRÁFICA DE ELECCIONES
========================================================= */

$graficaNombres = [];

$graficaVotos = [];


foreach (
    $elecciones as $e
) {

    $graficaNombres[] =
        $e['nombre'];

    $graficaVotos[] =
        (int)$e['total_votos'];

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
Panel Administrativo | Votaciones Escolares
</title>


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet"
>


<!-- =====================================================
     ICONOS
===================================================== -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<!-- =====================================================
     CHART.JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


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
        28px 15px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);
}


.logo-icon {

    font-size: 55px;

    margin-bottom: 8px;
}


.logo h1 {

    margin: 0;

    font-size: 30px;

    font-weight: 800;
}


.logo p {

    margin:
        5px 0 0;

    font-size: 13px;

    opacity: .85;
}


/* =========================================================
   MENÚ
========================================================= */

.menu {

    padding:
        12px 0 20px;
}


.menu-title {

    padding:
        12px 22px 7px;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 1px;

    opacity: .65;

    font-weight: bold;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    color: white;

    text-decoration: none;

    padding:
        14px 22px;

    font-size: 15px;

    transition:
        .2s ease;
}


.menu a:hover {

    background: #0d4388;

    padding-left: 27px;
}


.menu a.activo {

    background: #0d4388;

    border-left:
        4px solid white;

    padding-left: 18px;
}


.menu a i {

    width: 23px;

    text-align: center;

    font-size: 19px;
}


.menu-separador {

    height: 1px;

    background:
        rgba(255,255,255,.20);

    margin:
        10px 15px;
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

    padding:
        0 30px;

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);
}


.topbar h4 {

    margin: 0;

    font-size: 22px;

    font-weight: bold;
}


.topbar-admin {

    display: flex;

    align-items: center;

    gap: 7px;

    font-weight: 600;
}


.contenido {

    padding: 35px;
}


.titulo {

    color: #1453a3;

    font-size: 32px;

    font-weight: bold;

    margin: 0;
}


.subtitulo {

    color: #64748b;

    margin-top: 5px;
}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    margin-top: 20px;

    padding: 25px;

    border-radius: 16px;

    background:
        linear-gradient(
            135deg,
            #cfe2ff,
            #e5efff
        );

    border:
        1px solid #9ec5fe;

    color: #084298;
}


.bienvenida h4 {

    font-size: 22px;

    font-weight: bold;

    margin-bottom: 7px;
}


.bienvenida p {

    margin: 0;
}


/* =========================================================
   FECHA
========================================================= */

.fecha {

    text-align: right;

    color: #64748b;

    font-size: 14px;

    margin:
        18px 0;
}


/* =========================================================
   BUSCADOR
========================================================= */

.busqueda-card {

    background: white;

    border-radius: 18px;

    padding: 27px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.busqueda-titulo {

    color: #1453a3;

    font-size: 25px;

    font-weight: bold;

    margin-bottom: 5px;
}


.busqueda-descripcion {

    color: #64748b;

    margin-bottom: 18px;
}


.busqueda-form {

    display: flex;

    gap: 10px;
}


.busqueda-form input {

    flex: 1;

    min-height: 48px;

    border:
        1px solid #ced4da;

    border-radius: 9px;

    padding:
        10px 15px;

    font-size: 15px;
}


.btn-buscar {

    background: #1473ed;

    border: none;

    color: white;

    padding:
        0 25px;

    border-radius: 9px;

    font-weight: bold;
}


.btn-buscar:hover {

    background: #0d5fc7;
}


/* =========================================================
   RESULTADOS BUSCADOR
========================================================= */

.resultados-busqueda {

    margin-top: 20px;
}


.tabla-responsive {

    overflow-x: auto;
}


.tabla {

    width: 100%;

    border-collapse: collapse;
}


.tabla th {

    background: #d9e7fb;

    color: #1453a3;

    padding: 13px;

    text-align: left;
}


.tabla td {

    padding: 12px;

    border-bottom:
        1px solid #e5e7eb;
}


.tabla tr:hover {

    background: #f5f9ff;
}


.sin-resultados {

    padding: 20px;

    background: #f8f9fa;

    border-radius: 10px;

    color: #64748b;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-top: 25px;
}


.stat {

    background: white;

    border-radius: 18px;

    padding:
        25px 15px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    transition:
        transform .2s ease;
}


.stat:hover {

    transform:
        translateY(-4px);
}


.stat-icon {

    font-size: 43px;

    color: #1473ed;

    margin-bottom: 5px;
}


.stat-numero {

    color: #1453a3;

    font-size: 36px;

    font-weight: bold;
}


.stat-texto {

    color: #64748b;

    font-size: 14px;

    font-weight: 600;
}


/* =========================================================
   CARDS
========================================================= */

.card-custom {

    background: white;

    border-radius: 18px;

    padding: 28px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.card-title {

    color: #1453a3;

    font-size: 25px;

    font-weight: bold;

    margin-bottom: 20px;
}


/* =========================================================
   ESTADO
========================================================= */

.estado-abierta {

    display: inline-block;

    background: #198754;

    color: white;

    padding:
        7px 16px;

    border-radius: 8px;

    font-weight: bold;
}


.estado-cerrada {

    display: inline-block;

    background: #dc3545;

    color: white;

    padding:
        7px 16px;

    border-radius: 8px;

    font-weight: bold;
}


.info-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    padding:
        13px 5px;

    border-bottom:
        1px solid #e5e7eb;
}


.info-row:last-child {

    border-bottom: none;
}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participacion-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.participacion-header h3 {

    color: #1453a3;

    font-size: 24px;

    font-weight: bold;

    margin: 0;
}


.porcentaje {

    color: #1473ed;

    font-size: 30px;

    font-weight: bold;
}


.barra {

    width: 100%;

    height: 18px;

    background: #e7edf5;

    border-radius: 20px;

    overflow: hidden;
}


.barra-progreso {

    height: 100%;

    background:
        linear-gradient(
            90deg,
            #1473ed,
            #198754
        );

    border-radius: 20px;
}


.participacion-datos {

    display: flex;

    justify-content: space-between;

    margin-top: 12px;

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   GRÁFICA
========================================================= */

.grafica-container {

    position: relative;

    width: 100%;

    height: 330px;
}


/* =========================================================
   ACCESOS
========================================================= */

.acceso-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;
}


.acceso {

    min-height: 135px;

    border-radius: 15px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    text-align: center;

    text-decoration: none;

    padding: 18px;

    color: white;

    font-weight: bold;

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.acceso:hover {

    transform:
        translateY(-4px);

    color: white;

    box-shadow:
        0 8px 20px
        rgba(0,0,0,.16);
}


.acceso i {

    font-size: 35px;

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


.amarillo {

    background: #ffc107;

    color: #111;
}


.amarillo:hover {

    color: #111;
}


.rojo {

    background: #dc3545;
}


.oscuro {

    background: #212529;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top: 40px;

    padding:
        30px 20px 35px;

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

    margin-bottom: 8px;
}


.footer-autor {

    margin-bottom: 7px;
}


.footer-autor strong {

    color: #1453a3;
}


.footer-secundario {

    color: #94a3b8;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);
    }


    .acceso-grid {

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

        gap: 10px;
    }


    .participacion-datos {

        flex-direction: column;

        gap: 8px;
    }


    .busqueda-form {

        flex-direction: column;
    }


    .btn-buscar {

        min-height: 48px;
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
📦
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


<a
    href="admin.php"
    class="activo"
>

<i class="bi bi-house-fill"></i>

Inicio

</a>


<a href="elecciones.php">

<i class="bi bi-calendar-event-fill"></i>

Elecciones

</a>


<a href="estudiantes.php">

<i class="bi bi-people-fill"></i>

Estudiantes

</a>


<a href="candidatos.php">

<i class="bi bi-person-vcard-fill"></i>

Candidatos

</a>


<a href="jurados.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>


<div class="menu-separador"></div>


<div class="menu-title">
Resultados
</div>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<div class="menu-separador"></div>


<div class="menu-title">
Herramientas
</div>


<a href="importar_estudiantes.php">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<a href="exportar_estudiantes.php">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a href="pdf_resultados.php">

<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF

</a>


<div class="menu-separador"></div>


<div class="menu-title">
Control electoral
</div>


<a
    href="abrir_eleccion.php"
    onclick="
        return confirm(
            '¿Desea abrir la elección?'
        );
    "
>

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a
    href="cerrar_eleccion.php"
    onclick="
        return confirm(
            '¿Está seguro de cerrar la elección?'
        );
    "
>

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


<div class="menu-separador"></div>


<a
    href="logout.php"
    onclick="
        return confirm(
            '¿Desea cerrar sesión?'
        );
    "
>

<i class="bi bi-box-arrow-right"></i>

Cerrar Sesión

</a>


</nav>

</aside>


<!-- =====================================================
     PRINCIPAL
===================================================== -->

<div class="main">


<header class="topbar">


<h4>

🎓 Sistema de Votaciones Escolares

</h4>


<div class="topbar-admin">

<i class="bi bi-person-circle"></i>

<?php

echo htmlspecialchars(
    $nombreAdministrador
);

?>

</div>


</header>


<main class="contenido">


<!-- =====================================================
     MENSAJES
===================================================== -->

<?php if ($mensaje !== "") { ?>

<div
class="alert alert-<?php
echo htmlspecialchars(
    $tipoMensaje
);
?>"
>

<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>

</div>

<?php } ?>


<!-- =====================================================
     TÍTULO
===================================================== -->

<h1 class="titulo">

<i class="bi bi-speedometer2"></i>

Panel de Administración

</h1>


<p class="subtitulo">

Bienvenido al centro de control del sistema
electoral escolar.

</p>


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="bienvenida">

<h4>

👋 Bienvenido,

<?php

echo htmlspecialchars(
    $nombreAdministrador
);

?>

</h4>


<p>

Desde este panel puede administrar estudiantes,
jurados, candidatos, elecciones, votaciones,
resultados y reportes.

</p>

</div>


<!-- =====================================================
     FECHA
===================================================== -->

<div class="fecha">

📅

<strong>
<?php echo $fechaActual; ?>
</strong>

&nbsp;&nbsp;

🕐

<strong>
<?php echo $horaActual; ?>
</strong>

</div>


<!-- =====================================================
     BUSCAR EN EL SISTEMA
===================================================== -->

<section class="busqueda-card">


<div class="busqueda-titulo">

<i class="bi bi-search"></i>

Buscar estudiante

</div>


<div class="busqueda-descripcion">

Busque rápidamente un estudiante por nombre,
apellido, documento o curso.

</div>


<form
method="GET"
action="admin.php"
class="busqueda-form"
>


<input
type="text"
name="buscar"
value="<?php
echo htmlspecialchars(
    $busqueda
);
?>"
placeholder="Nombre, apellido, documento o curso..."
autocomplete="off"
>


<button
type="submit"
class="btn-buscar"
>

<i class="bi bi-search"></i>

Buscar

</button>


</form>


<?php if ($busqueda !== "") { ?>

<div class="resultados-busqueda">


<?php if (
    count($resultadosBusqueda) > 0
) { ?>


<div class="table-responsive">


<table class="tabla">


<thead>

<tr>

<th>
Documento
</th>

<th>
Nombre
</th>

<th>
Apellido
</th>

<th>
Curso
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $resultadosBusqueda as $estudiante
) { ?>


<tr>

<td>

<?php

echo htmlspecialchars(
    $estudiante['documento']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['nombre']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['apellido']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['curso']
);

?>

</td>

</tr>


<?php } ?>


</tbody>

</table>


</div>


<?php } else { ?>


<div class="sin-resultados">

<i class="bi bi-info-circle"></i>

No se encontraron estudiantes
que coincidan con:

<strong>

<?php

echo htmlspecialchars(
    $busqueda
);

?>

</strong>

</div>


<?php } ?>


</div>


<?php } ?>


</section>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<section class="estadisticas">


<div class="stat">

<div class="stat-icon">

<i class="bi bi-people-fill"></i>

</div>


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

<div class="stat-icon">

<i class="bi bi-person-badge-fill"></i>

</div>


<div class="stat-numero">

<?php

echo $totalJurados;

?>

</div>


<div class="stat-texto">

Jurados

</div>

</div>


<div class="stat">

<div class="stat-icon">

<i class="bi bi-person-vcard-fill"></i>

</div>


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

<div class="stat-icon">

<i class="bi bi-check2-square"></i>

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


<!-- =====================================================
     ELECCIÓN ACTUAL
===================================================== -->

<section class="card-custom">


<div class="card-title">

<i class="bi bi-calendar-check-fill"></i>

Elección actual

</div>


<div class="info-row">

<strong>
Elección
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
Estado
</strong>


<span>

<?php if ($eleccionAbierta) { ?>

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


<div class="info-row">

<strong>
Inicio
</strong>


<span>

<?php

if ($fechaInicio !== "") {

    echo htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime(
                $fechaInicio
            )
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
Finalización
</strong>


<span>

<?php

if ($fechaFin !== "") {

    echo htmlspecialchars(
        date(
            "d/m/Y h:i A",
            strtotime(
                $fechaFin
            )
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


</section>


<!-- =====================================================
     TABLA DE ELECCIONES
===================================================== -->

<section class="card-custom">


<div class="card-title">

<i class="bi bi-list-check"></i>

Elecciones registradas

</div>


<div class="tabla-responsive">


<table class="tabla">


<thead>

<tr>

<th>
Elección
</th>

<th>
Inicio
</th>

<th>
Finalización
</th>

<th>
Estado
</th>

<th>
Votos
</th>

<th>
Acción
</th>

</tr>

</thead>


<tbody>


<?php if (
    count($elecciones) > 0
) { ?>


<?php foreach (
    $elecciones as $e
) { ?>


<tr>

<td>

<strong>

<?php

echo htmlspecialchars(
    $e['nombre']
);

?>

</strong>

</td>


<td>

<?php

echo htmlspecialchars(
    $e['fecha_inicio']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $e['fecha_fin']
);

?>

</td>


<td>


<?php

$estadoTabla =
    strtolower(
        trim(
            (string)$e['estado']
        )
    );


if (
    $estadoTabla === "abierta"
) {

    echo
        '<span class="estado-abierta">Abierta</span>';

} else {

    echo
        '<span class="estado-cerrada">Cerrada</span>';

}

?>


</td>


<td>

<?php

echo (int)$e['total_votos'];

?>

</td>


<td>

<a
href="editar_eleccion.php?id=<?php
echo (int)$e['id'];
?>"
class="btn btn-sm btn-outline-primary"
>

<i class="bi bi-eye"></i>

Ver

</a>

</td>

</tr>


<?php } ?>


<?php } else { ?>


<tr>

<td
colspan="6"
class="text-center"
>

No hay elecciones registradas.

</td>

</tr>


<?php } ?>


</tbody>

</table>


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


<!-- =====================================================
     GRÁFICA
===================================================== -->

<section class="card-custom">


<div class="card-title">

<i class="bi bi-bar-chart-fill"></i>

Votos por elección

</div>


<div class="grafica-container">

<canvas
id="graficaElecciones">
</canvas>

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


<div class="acceso-grid">


<a
href="elecciones.php"
class="acceso azul"
>

<i class="bi bi-calendar-event-fill"></i>

Gestionar Elecciones

</a>


<a
href="estudiantes.php"
class="acceso verde"
>

<i class="bi bi-people-fill"></i>

Gestionar Estudiantes

</a>


<a
href="candidatos.php"
class="acceso celeste"
>

<i class="bi bi-person-vcard-fill"></i>

Gestionar Candidatos

</a>


<a
href="jurados.php"
class="acceso azul"
>

<i class="bi bi-person-badge-fill"></i>

Gestionar Jurados

</a>


<a
href="resultados.php"
class="acceso amarillo"
>

<i class="bi bi-trophy-fill"></i>

Ver Resultados

</a>


<a
href="graficas.php"
class="acceso celeste"
>

<i class="bi bi-bar-chart-fill"></i>

Ver Gráficas

</a>


<a
href="importar_estudiantes.php"
class="acceso verde"
>

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<a
href="exportar_estudiantes.php"
class="acceso verde"
>

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a
href="pdf_resultados.php"
class="acceso rojo"
>

<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF

</a>


<a
href="abrir_eleccion.php"
class="acceso verde"
onclick="
return confirm(
'¿Desea abrir la elección?'
);
"
>

<i class="bi bi-unlock-fill"></i>

Abrir Elección

</a>


<a
href="cerrar_eleccion.php"
class="acceso rojo"
onclick="
return confirm(
'¿Está seguro de cerrar la elección?'
);
"
>

<i class="bi bi-lock-fill"></i>

Cerrar Elección

</a>


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


<!-- =====================================================
     FOOTER
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


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

const nombresElecciones =
<?php

echo json_encode(
    $graficaNombres,
    JSON_UNESCAPED_UNICODE
);

?>;


const votosElecciones =
<?php

echo json_encode(
    $graficaVotos
);

?>;


const canvas =
document.getElementById(
    "graficaElecciones"
);


if (
    canvas &&
    nombresElecciones.length > 0
) {

    new Chart(
        canvas,
        {

            type: "bar",

            data: {

                labels:
                    nombresElecciones,

                datasets: [

                    {

                        label:
                            "Votos registrados",

                        data:
                            votosElecciones,

                        borderWidth: 1

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


</body>

</html>
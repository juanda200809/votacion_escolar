<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


/* =========================================================
   1. VERIFICAR SESIÓN DEL JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim((string)$_SESSION['rol'])) !== 'jurado'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   2. DATOS DEL JURADO
========================================================= */

$idJurado = (int)$_SESSION['id'];

$nombreJurado =
    $_SESSION['nombre'] ?? 'Jurado';


/* =========================================================
   3. OBTENER ELECCIÓN
========================================================= */

$idEleccion = 0;


/*
 * Elección guardada para el jurado.
 */

if (
    isset($_SESSION['id_eleccion_jurado']) &&
    (int)$_SESSION['id_eleccion_jurado'] > 0
) {

    $idEleccion =
        (int)$_SESSION['id_eleccion_jurado'];

}


/*
 * También aceptamos id_eleccion por GET.
 */

if (
    isset($_GET['id_eleccion']) &&
    (int)$_GET['id_eleccion'] > 0
) {

    $idEleccion =
        (int)$_GET['id_eleccion'];

}


/*
 * Si no existe, buscamos la elección abierta.
 */

if ($idEleccion <= 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM elecciones
        WHERE LOWER(TRIM(estado)) = 'abierta'
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {

        die(
            "Error al consultar la elección: "
            . $conn->error
        );

    }

    $stmt->execute();

    $resultado =
        $stmt->get_result();

    $eleccionEncontrada =
        $resultado->fetch_assoc();

    $stmt->close();


    if ($eleccionEncontrada) {

        $idEleccion =
            (int)$eleccionEncontrada['id'];

    }

}


/*
 * Si todavía no hay elección,
 * buscamos la última registrada.
 */

if ($idEleccion <= 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM elecciones
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {

        die(
            "Error al consultar las elecciones: "
            . $conn->error
        );

    }

    $stmt->execute();

    $resultado =
        $stmt->get_result();

    $eleccionEncontrada =
        $resultado->fetch_assoc();

    $stmt->close();


    if ($eleccionEncontrada) {

        $idEleccion =
            (int)$eleccionEncontrada['id'];

    }

}


/* =========================================================
   4. OBTENER ELECCIÓN
========================================================= */

$eleccion = null;

$eleccionAbierta = false;


if ($idEleccion > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado
        FROM elecciones
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        die(
            "Error al consultar la elección: "
            . $conn->error
        );

    }


    $stmt->bind_param(
        "i",
        $idEleccion
    );

    $stmt->execute();

    $resultado =
        $stmt->get_result();

    $eleccion =
        $resultado->fetch_assoc();

    $stmt->close();


    if ($eleccion) {

        $eleccionAbierta =
            strtolower(
                trim(
                    (string)$eleccion['estado']
                )
            ) === 'abierta';

    }

}


/* =========================================================
   5. BUSCAR MESA DEL JURADO
========================================================= */

$mesa = null;


if (
    $idEleccion > 0 &&
    $idJurado > 0
) {

    $stmt = $conn->prepare("
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

    if (!$stmt) {

        die(
            "Error al consultar la mesa: "
            . $conn->error
        );

    }


    $stmt->bind_param(
        "ii",
        $idEleccion,
        $idJurado
    );

    $stmt->execute();

    $resultadoMesa =
        $stmt->get_result();

    $mesa =
        $resultadoMesa->fetch_assoc();

    $stmt->close();

}


/* =========================================================
   6. CERRAR LA PROPIA MESA
=========================================================

   IMPORTANTE:

   El jurado solamente puede cerrar la mesa que:

   - pertenece a él
   - pertenece a la elección actual
   - está actualmente abierta

   No puede abrirla.
   No puede modificar otra mesa.
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cerrar_mi_mesa'])
) {

    /*
     * Volvemos a consultar la mesa directamente
     * para no confiar en datos enviados desde el navegador.
     */

    $stmt = $conn->prepare("
        SELECT
            id,
            estado
        FROM mesas_votacion
        WHERE id_eleccion = ?
        AND id_jurado = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idJurado
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $mesaSegura =
            $resultado->fetch_assoc();

        $stmt->close();


        if (!$mesaSegura) {

            header(
                "Location: jurado.php?error=sin_mesa"
            );

            exit();

        }


        $estadoMesaSegura =
            strtolower(
                trim(
                    (string)$mesaSegura['estado']
                )
            );


        /*
         * Solo se permite cerrar si está abierta.
         */

        if (
            $estadoMesaSegura !== 'abierta'
        ) {

            header(
                "Location: jurado.php?error=mesa_cerrada"
            );

            exit();

        }


        /*
         * Cerrar exclusivamente la mesa
         * del jurado conectado.
         */

        $stmtCerrar =
            $conn->prepare("
                UPDATE mesas_votacion
                SET
                    estado = 'cerrada',
                    fecha_cierre = NOW()
                WHERE id = ?
                AND id_eleccion = ?
                AND id_jurado = ?
                AND LOWER(TRIM(estado)) = 'abierta'
            ");


        if (!$stmtCerrar) {

            header(
                "Location: jurado.php?error=cerrar"
            );

            exit();

        }


        $idMesaSegura =
            (int)$mesaSegura['id'];


        $stmtCerrar->bind_param(
            "iii",
            $idMesaSegura,
            $idEleccion,
            $idJurado
        );


        if (
            !$stmtCerrar->execute()
        ) {

            $stmtCerrar->close();

            header(
                "Location: jurado.php?error=cerrar"
            );

            exit();

        }


        $stmtCerrar->close();


        header(
            "Location: jurado.php?mesa_cerrada=1"
        );

        exit();

    }


    header(
        "Location: jurado.php?error=cerrar"
    );

    exit();

}


/* =========================================================
   7. ESTADO DE LA MESA
========================================================= */

$mesaAsignada = false;

$mesaAbierta = false;

$mesaCerrada = false;


if ($mesa) {

    $mesaAsignada = true;


    $estadoMesa =
        strtolower(
            trim(
                (string)$mesa['estado']
            )
        );


    if (
        $estadoMesa === 'abierta'
    ) {

        $mesaAbierta = true;

    }


    if (
        $estadoMesa === 'cerrada'
    ) {

        $mesaCerrada = true;

    }

}


/* =========================================================
   8. GUARDAR ELECCIÓN EN SESIÓN
========================================================= */

if (
    $idEleccion > 0
) {

    $_SESSION['id_eleccion_jurado'] =
        $idEleccion;

}


/* =========================================================
   9. ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;

$totalVotos = 0;

$totalPendientes = 0;

$totalCandidatos = 0;


/*
 * Estudiantes
 */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'estudiante'
");

if ($stmt) {

    $stmt->execute();

    $resultado =
        $stmt->get_result();

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)($fila['total'] ?? 0);

    $stmt->close();

}


/*
 * Candidatos
 */

if ($idEleccion > 0) {

    $stmt = $conn->prepare("
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
            (int)($fila['total'] ?? 0);

        $stmt->close();

    }

}


/*
 * Votos
 */

if ($idEleccion > 0) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
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

        $totalVotos =
            (int)($fila['total'] ?? 0);

        $stmt->close();

    }

}


/*
 * Pendientes
 */

$totalPendientes =
    max(
        0,
        $totalEstudiantes - $totalVotos
    );


/* =========================================================
   10. MENSAJES
========================================================= */

$mensajeJurado = "";

$tipoMensajeJurado = "";


if (
    isset($_GET['mesa_cerrada'])
) {

    $mensajeJurado =
        "Tu mesa de votación fue cerrada correctamente. Solo el administrador puede volver a habilitarla.";

    $tipoMensajeJurado =
        "success";

}
elseif (
    isset($_GET['error']) &&
    $_GET['error'] === 'mesa_cerrada'
) {

    $mensajeJurado =
        "La mesa ya está cerrada. Solo el administrador puede volver a habilitarla.";

    $tipoMensajeJurado =
        "warning";

}
elseif (
    isset($_GET['error']) &&
    $_GET['error'] === 'sin_mesa'
) {

    $mensajeJurado =
        "No tienes una mesa de votación asignada.";

    $tipoMensajeJurado =
        "warning";

}
elseif (
    isset($_GET['error']) &&
    $_GET['error'] === 'cerrar'
) {

    $mensajeJurado =
        "No fue posible cerrar la mesa. Intenta nuevamente.";

    $tipoMensajeJurado =
        "danger";

}
elseif (!$eleccion) {

    $mensajeJurado =
        "No existe una elección registrada.";

    $tipoMensajeJurado =
        "warning";

}
elseif (!$mesaAsignada) {

    $mensajeJurado =
        "No tienes una mesa de votación asignada para esta elección.";

    $tipoMensajeJurado =
        "warning";

}
elseif ($mesaCerrada) {

    $mensajeJurado =
        "Tu mesa de votación está cerrada. Solo el administrador puede volver a habilitarla.";

    $tipoMensajeJurado =
        "warning";

}


/* =========================================================
   11. DATOS
========================================================= */

$nombreEleccion =
    $eleccion['nombre']
    ?? 'Sin elección';


$descripcionEleccion =
    $eleccion['descripcion']
    ?? 'Proceso democrático institucional';


$fechaInicio =
    $eleccion['fecha_inicio']
    ?? '';


$fechaFin =
    $eleccion['fecha_fin']
    ?? '';


$urlVotacion =
    "ingresar_estudiante.php?id_eleccion="
    . $idEleccion;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
Panel del Jurado
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">


<style>

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #eef4fb;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1f2937;

}


a {
    text-decoration: none;
}


/* =========================================================
   ESTRUCTURA
========================================================= */

.app {

    min-height: 100vh;

}


.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #155aaa;

    color: white;

    z-index: 1000;

}


.sidebar-header {

    height: 190px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);

}


.logo-jurado {

    font-size: 48px;

    margin-bottom: 5px;

}


.sidebar-header h1 {

    margin: 0;

    font-size: 30px;

    letter-spacing: 1px;

}


.sidebar-header p {

    margin: 5px 0 0;

    opacity: .85;

    font-size: 13px;

}


/* =========================================================
   MENÚ
========================================================= */

.menu {

    padding: 15px 12px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 15px;

    color: white;

    padding: 15px 16px;

    border-radius: 10px;

    margin-bottom: 5px;

    font-size: 15px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

}


.menu a.active {

    background:
        rgba(255,255,255,.18);

}


.menu a i {

    font-size: 20px;

    width: 25px;

    text-align: center;

}


.menu-separator {

    height: 1px;

    background:
        rgba(255,255,255,.2);

    margin: 15px 5px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 70px;

    background: #1674e8;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 32px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.12);

}


.topbar-title {

    font-size: 21px;

    font-weight: 700;

}


.user-info {

    font-size: 15px;

}


.user-info strong {

    font-weight: 700;

}


/* =========================================================
   CONTENIDO
========================================================= */

.content {

    padding: 35px;

    max-width: 1500px;

}


/* =========================================================
   MENSAJE
========================================================= */

.mensaje {

    background: white;

    border-radius: 14px;

    padding: 16px 20px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

    font-weight: 600;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.welcome {

    background: white;

    border-radius: 18px;

    padding: 32px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

}


.welcome h2 {

    color: #1458a6;

    font-size: 36px;

    margin: 0 0 10px;

}


.welcome p {

    color: #64748b;

    font-size: 16px;

    margin: 0;

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
        0 5px 18px
        rgba(0,0,0,.07);

}


.election-top {

    padding: 25px;

    background: #dcecff;

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

}


.election-name {

    font-size: 28px;

    font-weight: 700;

    color: #1458a6;

}


.election-description {

    margin-top: 7px;

    color: #58708e;

}


.election-status {

    padding: 9px 15px;

    border-radius: 20px;

    font-weight: 700;

    white-space: nowrap;

}


.status-open {

    background: #198754;

    color: white;

}


.status-closed {

    background: #6c757d;

    color: white;

}


.dates {

    padding: 20px 25px;

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;

}


.date-box {

    border:
        1px solid #d9e2ec;

    border-radius: 12px;

    padding: 15px;

}


.date-label {

    color: #64748b;

    font-size: 14px;

    margin-bottom: 5px;

}


.date-value {

    font-weight: 700;

    color: #1458a6;

}


/* =========================================================
   MESA
========================================================= */

.mesa-card {

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

}


.mesa-abierta {

    background: #e7f8ef;

    border:
        1px solid #9ce7bb;

}


.mesa-cerrada {

    background: #eef1f4;

    border:
        1px solid #cbd5e1;

}


.mesa-sin {

    background: #fff8df;

    border:
        1px solid #f1d36b;

}


.mesa-info {

    display: flex;

    align-items: center;

    gap: 18px;

}


.mesa-icon {

    width: 64px;

    height: 64px;

    border-radius: 15px;

    background: #d7f5e4;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 34px;

}


.mesa-cerrada .mesa-icon {

    background: #dce2e8;

}


.mesa-sin .mesa-icon {

    background: #fff0bd;

}


.mesa-titulo {

    color: #1458a6;

    font-size: 25px;

    font-weight: 700;

    margin-bottom: 5px;

}


.mesa-descripcion {

    color: #64748b;

    margin-bottom: 7px;

}


.mesa-estado {

    font-weight: 700;

}


.estado-abierto {

    color: #138a48;

}


.estado-cerrado {

    color: #59636f;

}


.estado-sin {

    color: #856404;

}


/* =========================================================
   BOTONES
========================================================= */

.botones-mesa {

    display: flex;

    flex-direction: column;

    gap: 10px;

    min-width: 210px;

}


.btn-comenzar {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    background: #1473ed;

    color: white;

    padding: 15px 25px;

    border-radius: 10px;

    font-size: 16px;

    font-weight: 700;

    border: none;

    cursor: pointer;

}


.btn-comenzar:hover {

    background: #0e5fc8;

    color: white;

}


.btn-cerrar-mesa {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    background: #dc3545;

    color: white;

    padding: 13px 20px;

    border-radius: 10px;

    font-size: 15px;

    font-weight: 700;

    border: none;

    cursor: pointer;

}


.btn-cerrar-mesa:hover {

    background: #b02a37;

}


.btn-bloqueado {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    background: #cbd5e1;

    color: #64748b;

    padding: 15px 25px;

    border-radius: 10px;

    font-size: 16px;

    font-weight: 700;

    cursor: not-allowed;

    border: none;

}


/* =========================================================
   INICIO VOTACIÓN
========================================================= */

.inicio-votacion {

    background: white;

    border-radius: 18px;

    padding: 28px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;

}


.inicio-contenido {

    display: flex;

    align-items: center;

    gap: 20px;

}


.inicio-icon {

    width: 65px;

    height: 65px;

    background: #e3edff;

    color: #1473ed;

    border-radius: 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

}


.inicio-titulo {

    font-size: 25px;

    color: #1458a6;

    font-weight: 700;

    margin-bottom: 7px;

}


.inicio-descripcion {

    color: #64748b;

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

    border-radius: 17px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

}


.stat i {

    font-size: 34px;

    color: #1473ed;

}


.stat-number {

    color: #1458a6;

    font-size: 34px;

    font-weight: 700;

    margin-top: 8px;

}


.stat-label {

    color: #64748b;

    margin-top: 5px;

}


/* =========================================================
   INFORMACIÓN
========================================================= */

.info {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.07);

}


.info h3 {

    color: #1458a6;

    margin-top: 0;

}


.info ul {

    color: #64748b;

    line-height: 1.8;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .stats {

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


    .topbar {

        padding: 0 18px;

    }


    .user-info {

        display: none;

    }


    .content {

        padding: 20px;

    }


    .election-top {

        flex-direction: column;

    }


    .mesa-card {

        flex-direction: column;

        align-items: stretch;

    }


    .botones-mesa {

        width: 100%;

    }


    .btn-comenzar,
    .btn-cerrar-mesa,
    .btn-bloqueado {

        width: 100%;

    }


    .inicio-votacion {

        flex-direction: column;

        align-items: stretch;

    }

}


@media(max-width:550px) {

    .stats {

        grid-template-columns: 1fr;

    }


    .dates {

        grid-template-columns: 1fr;

    }


    .welcome h2 {

        font-size: 29px;

    }


    .election-name {

        font-size: 23px;

    }


    .topbar-title {

        font-size: 17px;

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


<?php if (
    $mesaAbierta &&
    $eleccionAbierta
) { ?>


<a
href="<?php echo htmlspecialchars(
    $urlVotacion,
    ENT_QUOTES,
    'UTF-8'
); ?>"
>

<i class="bi bi-person-check-fill"></i>

<span>

Ingresar estudiante

</span>

</a>


<?php } else { ?>


<a
href="#"
style="
opacity:.55;
cursor:not-allowed;
"
onclick="return false;"
>

<i class="bi bi-lock-fill"></i>

<span>

Mesa cerrada

</span>

</a>


<?php } ?>


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


<div class="menu-separator"></div>


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
    $nombreJurado,
    ENT_QUOTES,
    'UTF-8'
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
    $tipoMensajeJurado === 'danger'
) { ?>

<i class="bi bi-x-circle-fill"></i>

<?php } elseif (
    $tipoMensajeJurado === 'success'
) { ?>

<i class="bi bi-check-circle-fill"></i>

<?php } else { ?>

<i class="bi bi-exclamation-triangle-fill"></i>

<?php } ?>


<?php

echo htmlspecialchars(
    $mensajeJurado,
    ENT_QUOTES,
    'UTF-8'
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
    $nombreJurado,
    ENT_QUOTES,
    'UTF-8'
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
     ELECCIÓN
===================================================== -->

<?php if (
    $eleccion
) { ?>


<section class="election-card">


<div class="election-top">


<div>


<div class="election-name">

<i class="bi bi-calendar-check-fill"></i>

<?php

echo htmlspecialchars(
    $nombreEleccion,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


<div class="election-description">

<?php

echo htmlspecialchars(
    $descripcionEleccion,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


<?php if (
    $eleccionAbierta
) { ?>


<div class="election-status status-open">

🟢 ELECCIÓN ABIERTA

</div>


<?php } else { ?>


<div class="election-status status-closed">

🔴 ELECCIÓN CERRADA

</div>


<?php } ?>


</div>


<div class="dates">


<div class="date-box">


<div class="date-label">

Fecha de inicio

</div>


<div class="date-value">

<?php

echo htmlspecialchars(
    $fechaInicio,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


<div class="date-box">


<div class="date-label">

Fecha de finalización

</div>


<div class="date-value">

<?php

echo htmlspecialchars(
    $fechaFin,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


</div>


</section>


<?php } ?>


<!-- =====================================================
     MESA DEL JURADO
===================================================== -->

<?php if (
    $mesaAsignada
) { ?>


<div
class="mesa-card
<?php

echo $mesaAbierta
    ? 'mesa-abierta'
    : 'mesa-cerrada';

?>"
>


<div class="mesa-info">


<div class="mesa-icon">

<?php

echo $mesaAbierta
    ? '🗳️'
    : '🔒';

?>

</div>


<div>


<div class="mesa-titulo">

<?php

echo htmlspecialchars(
    $mesa['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


<div class="mesa-descripcion">

Mesa asignada al jurado

<strong>

<?php

echo htmlspecialchars(
    $nombreJurado,
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</div>


<?php if (
    $mesaAbierta
) { ?>


<div class="mesa-estado estado-abierto">

🟢 Mesa de votación abierta

</div>


<?php } else { ?>


<div class="mesa-estado estado-cerrado">

🔴 Mesa de votación cerrada

<?php if (
    !empty($mesa['fecha_cierre'])
) { ?>


<br>

Cerrada el:

<?php

echo htmlspecialchars(
    $mesa['fecha_cierre'],
    ENT_QUOTES,
    'UTF-8'
);

?>


<?php } ?>


</div>


<?php } ?>


</div>


</div>


<!-- =====================================================
     ACCIONES DE LA MESA
===================================================== -->

<div class="botones-mesa">


<?php if (
    $mesaAbierta &&
    $eleccionAbierta
) { ?>


<a
href="<?php echo htmlspecialchars(
    $urlVotacion,
    ENT_QUOTES,
    'UTF-8'
); ?>"
class="btn-comenzar"
>

<i class="bi bi-play-fill"></i>

Comenzar votación

</a>


<!-- ================================================
     CERRAR PROPIA MESA
================================================ -->

<form
method="POST"
onsubmit="
return confirm(
'¿Está seguro de cerrar su mesa de votación? Después de cerrarla, solo el administrador podrá volver a abrirla.'
);
"
>

<button
type="submit"
name="cerrar_mi_mesa"
value="1"
class="btn-cerrar-mesa"
>

<i class="bi bi-lock-fill"></i>

Cerrar mi mesa

</button>

</form>


<?php } elseif (
    $mesaCerrada
) { ?>


<button
type="button"
class="btn-bloqueado"
disabled
>

<i class="bi bi-lock-fill"></i>

Mesa cerrada

</button>


<?php } else { ?>


<button
type="button"
class="btn-bloqueado"
disabled
>

<i class="bi bi-lock-fill"></i>

Elección cerrada

</button>


<?php } ?>


</div>


</div>


<?php } else { ?>


<!-- =====================================================
     SIN MESA
===================================================== -->

<div class="mesa-card mesa-sin">


<div class="mesa-info">


<div class="mesa-icon">

⚠️

</div>


<div>


<div class="mesa-titulo">

Mesa no asignada

</div>


<div class="mesa-descripcion">

No tienes una mesa de votación asignada
para esta elección.

</div>


<div class="mesa-estado estado-sin">

🚫 No puede iniciar votaciones

</div>


</div>


</div>


</div>


<?php } ?>


<!-- =====================================================
     INICIO VOTACIÓN
===================================================== -->

<div class="inicio-votacion">


<div class="inicio-contenido">


<div class="inicio-icon">

<i class="bi bi-person-check-fill"></i>

</div>


<div>


<div class="inicio-titulo">

Comenzar votación

</div>


<div class="inicio-descripcion">


<?php if (
    !$mesaAsignada
) { ?>


No existe una mesa asignada a este jurado.


<?php } elseif (
    !$eleccionAbierta
) { ?>


La elección está cerrada.


<?php } elseif (
    $mesaCerrada
) { ?>


La mesa está cerrada.
Solo el administrador puede habilitarla nuevamente.


<?php } else { ?>


Ingrese un estudiante para comenzar su proceso de votación.


<?php } ?>


</div>


</div>


</div>


<div>


<?php if (
    $mesaAbierta &&
    $eleccionAbierta
) { ?>


<a
href="<?php echo htmlspecialchars(
    $urlVotacion,
    ENT_QUOTES,
    'UTF-8'
); ?>"
class="btn-comenzar"
>

<i class="bi bi-play-fill"></i>

Comenzar

</a>


<?php } else { ?>


<button
type="button"
class="btn-bloqueado"
disabled
>

<i class="bi bi-lock-fill"></i>

Mesa cerrada

</button>


<?php } ?>


</div>


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

<div class="stat-label">

Estudiantes

</div>

</div>


<div class="stat">

<i class="bi bi-check-circle-fill"></i>

<div class="stat-number">

<?php

echo $totalVotos;

?>

</div>

<div class="stat-label">

Votos registrados

</div>

</div>


<div class="stat">

<i class="bi bi-person-check-fill"></i>

<div class="stat-number">

<?php

echo $totalPendientes;

?>

</div>

<div class="stat-label">

Pendientes

</div>

</div>


<div class="stat">

<i class="bi bi-person-vcard-fill"></i>

<div class="stat-number">

<?php

echo $totalCandidatos;

?>

</div>

<div class="stat-label">

Candidatos

</div>

</div>


</div>


<!-- =====================================================
     INFORMACIÓN
===================================================== -->

<div class="info">


<h3>

<i class="bi bi-info-circle-fill"></i>

Información de la mesa

</h3>


<ul>


<li>

La mesa mostrada pertenece exclusivamente
a este jurado.

</li>


<li>

El jurado puede cerrar su propia mesa
cuando termine el proceso de votación.

</li>


<li>

Una vez cerrada, el jurado no puede volver
a abrirla.

</li>


<li>

Solo el administrador puede volver a habilitar
una mesa cerrada.

</li>


<li>

El jurado no puede seleccionar,
crear ni cambiar su mesa de votación.

</li>


</ul>


</div>


</section>


</main>


</div>


</body>

</html>
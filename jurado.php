<?php

session_start();

include("config/conexion.php");


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


/* =========================================================
   VERIFICAR ROL
========================================================= */

$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rol === "estudiante") {

        header("Location: votar.php");
        exit();

    }

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================================
   DATOS DEL JURADO
========================================================= */

$nombreJurado =
    $_SESSION['nombre'] ?? "Jurado";


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$eleccion = null;


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


if (
    $consultaEleccion &&
    $consultaEleccion->num_rows > 0
) {

    $eleccion =
        $consultaEleccion->fetch_assoc();

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;

$totalCandidatos = 0;

$totalVotos = 0;


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultado =
    $conn->query("

        SELECT
            COUNT(*) AS total

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
   TOTAL CANDIDATOS Y VOTOS
========================================================= */

if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];


    /* =============================================
       CANDIDATOS
    ============================================= */

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
            (int)$fila['total'];

        $stmt->close();

    }


    /* =============================================
       VOTOS
       votos NO tiene id_eleccion.
       Se relaciona mediante candidatos.
    ============================================= */

    $stmt =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

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
            (int)$fila['total'];

        $stmt->close();

    }

}


/* =========================================================
   ESTADO DE LA ELECCIÓN
========================================================= */

$eleccionAbierta = false;


if ($eleccion) {

    $estado =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estado === "abierta"
    ) {

        $eleccionAbierta = true;

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Panel del Jurado

</title>


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =====================================================
     ICONOS
===================================================== -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


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

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #1453a3;

    color: white;

    z-index: 1000;

}


.logo {

    text-align: center;

    padding: 28px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);

}


.logo-icon {

    font-size: 55px;

}


.logo h2 {

    margin: 8px 0 0;

    font-weight: bold;

}


.logo p {

    margin: 5px 0 0;

    opacity: .75;

    font-size: 13px;

}


.menu {

    padding: 18px 15px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    color: white;

    text-decoration: none;

    padding: 14px;

    border-radius: 9px;

    margin-bottom: 8px;

    font-size: 16px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

}


.menu a.activo {

    background:
        rgba(255,255,255,.18);

}


.menu i {

    font-size: 20px;

    width: 25px;

}


.separador {

    height: 1px;

    background:
        rgba(255,255,255,.22);

    margin: 15px 5px;

}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    margin-left: 250px;

    min-height: 100vh;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 70px;

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.15);

}


.header h3 {

    margin: 0;

    font-size: 24px;

}


.usuario {

    font-size: 15px;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    padding: 35px;

    max-width: 1300px;

    margin: auto;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida h1 {

    color: #1559a6;

    font-weight: bold;

}


.bienvenida p {

    color: #6c757d;

    font-size: 17px;

}


/* =========================================================
   BLOQUE INGRESAR ESTUDIANTE
========================================================= */

.ingresar-estudiante {

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #084298
        );

    color: white;

    border-radius: 20px;

    padding: 30px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.15);

    margin-bottom: 30px;

}


.ingresar-estudiante h2 {

    font-weight: bold;

}


.ingresar-estudiante p {

    margin-bottom: 22px;

}


.btn-ingresar {

    background: white;

    color: #0d47a1;

    font-weight: bold;

    border: none;

    padding: 13px 24px;

    border-radius: 10px;

}


.btn-ingresar:hover {

    background: #f1f4f8;

    color: #084298;

}


/* =========================================================
   TARJETAS
========================================================= */

.tarjetas {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 30px;

}


.tarjeta {

    background: white;

    border-radius: 18px;

    padding: 27px;

    text-align: center;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.tarjeta-icono {

    font-size: 45px;

    color: #1473ed;

    margin-bottom: 8px;

}


.tarjeta h3 {

    color: #1559a6;

    font-weight: bold;

    font-size: 34px;

    margin: 5px 0;

}


.tarjeta p {

    color: #6c757d;

    margin: 0;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    margin-bottom: 30px;

}


.eleccion h3 {

    color: #1559a6;

    font-weight: bold;

}


.estado-abierta {

    background: #198754;

    color: white;

    padding: 8px 16px;

    border-radius: 7px;

    font-weight: bold;

}


.estado-cerrada {

    background: #dc3545;

    color: white;

    padding: 8px 16px;

    border-radius: 7px;

    font-weight: bold;

}


/* =========================================================
   ACCIONES
========================================================= */

.acciones {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

}


.accion {

    background: white;

    border-radius: 18px;

    padding: 28px;

    text-decoration: none;

    color: #212529;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    transition: .2s;

}


.accion:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,.15);

    color: #212529;

}


.accion i {

    font-size: 42px;

    color: #1473ed;

}


.accion h4 {

    color: #1559a6;

    font-weight: bold;

    margin-top: 12px;

}


.accion p {

    color: #6c757d;

    margin-bottom: 0;

}


/* =========================================================
   MENSAJE ELECCIÓN CERRADA
========================================================= */

.eleccion-cerrada {

    background: #fff3cd;

    border:
        1px solid #ffecb5;

    color: #664d03;

    border-radius: 12px;

    padding: 18px;

    margin-top: 20px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .sidebar {

        width: 210px;

    }


    .contenido {

        margin-left: 210px;

    }


    .tarjetas {

        grid-template-columns:
            1fr;

    }

}


@media(max-width:650px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }


    .contenido {

        margin-left: 0;

    }


    .tarjetas {

        grid-template-columns:
            1fr;

    }


    .acciones {

        grid-template-columns:
            1fr;

    }


    .header {

        padding: 0 15px;

    }


    .header h3 {

        font-size: 18px;

    }


    .usuario {

        display: none;

    }


    .contenedor {

        padding: 20px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">


<div class="logo">

<div class="logo-icon">

⚖️

</div>


<h2>

JURADO

</h2>


<p>

Panel de votaciones

</p>

</div>


<div class="menu">


<!-- =====================================================
     INICIO
========================================================= -->

<a
href="jurado.php"
class="activo">

<i class="bi bi-house-fill"></i>

<span>

Inicio

</span>

</a>


<!-- =====================================================
     INGRESAR ESTUDIANTE
========================================================= -->

<a
href="ingresar_estudiante.php">

<i class="bi bi-person-vcard-fill"></i>

<span>

Ingresar estudiante

</span>

</a>


<!-- =====================================================
     RESULTADOS
========================================================= -->

<a
href="resultados.php">

<i class="bi bi-trophy-fill"></i>

<span>

Resultados

</span>

</a>


<!-- =====================================================
     GRÁFICAS
========================================================= -->

<a
href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

<span>

Gráficas

</span>

</a>


<div class="separador"></div>


<!-- =====================================================
     CERRAR SESIÓN
========================================================= -->

<a
href="logout.php">

<i class="bi bi-box-arrow-right"></i>

<span>

Cerrar sesión

</span>

</a>


</div>

</div>


<!-- =====================================================
     CONTENIDO
========================================================= -->

<div class="contenido">


<!-- =====================================================
     HEADER
========================================================= -->

<div class="header">


<h3>

⚖️ Sistema de Votaciones Escolares

</h3>


<div class="usuario">

<i class="bi bi-person-badge-fill"></i>

Jurado:

<strong>

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

</strong>

</div>


</div>


<!-- =====================================================
     CONTENEDOR
========================================================= -->

<div class="contenedor">


<!-- =====================================================
     BIENVENIDA
========================================================= -->

<div class="bienvenida mb-4">

<h1>

Bienvenido,

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

👋

</h1>


<p>

Desde este panel puede habilitar la votación
de los estudiantes y consultar los resultados.

</p>

</div>


<!-- =====================================================
     INGRESAR ESTUDIANTE
========================================================= -->

<div class="ingresar-estudiante">


<h2>

<i class="bi bi-person-vcard-fill"></i>

Votación de estudiantes

</h2>


<p>

Ingrese el documento del estudiante que
realizará la votación.

</p>


<?php if (
    $eleccionAbierta
) { ?>


<a
href="ingresar_estudiante.php"
class="btn btn-ingresar">

<i class="bi bi-person-plus-fill"></i>

Ingresar documento del estudiante

</a>


<?php } else { ?>


<button
type="button"
class="btn btn-secondary"
disabled>

<i class="bi bi-lock-fill"></i>

Elección cerrada

</button>


<?php } ?>


</div>


<!-- =====================================================
     TARJETAS
========================================================= -->

<div class="tarjetas">


<div class="tarjeta">


<div class="tarjeta-icono">

<i class="bi bi-people-fill"></i>

</div>


<h3>

<?php echo $totalEstudiantes; ?>

</h3>


<p>

Estudiantes registrados

</p>


</div>


<div class="tarjeta">


<div class="tarjeta-icono">

<i class="bi bi-person-vcard-fill"></i>

</div>


<h3>

<?php echo $totalCandidatos; ?>

</h3>


<p>

Candidatos de la elección

</p>


</div>


<div class="tarjeta">


<div class="tarjeta-icono">

<i class="bi bi-check2-square"></i>

</div>


<h3>

<?php echo $totalVotos; ?>

</h3>


<p>

Votos registrados

</p>


</div>


</div>


<!-- =====================================================
     ELECCIÓN ACTUAL
========================================================= -->

<div class="eleccion">


<h3>

<i class="bi bi-calendar-event-fill"></i>

Elección actual

</h3>


<hr>


<?php if (
    $eleccion
) { ?>


<h4>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h4>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="text-muted">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


<div class="row mt-4">


<div class="col-md-4 mb-3">

<strong>

Fecha de inicio

</strong>

<br>

<span class="text-muted">

<?php echo htmlspecialchars(
    $eleccion['fecha_inicio']
); ?>

</span>

</div>


<div class="col-md-4 mb-3">

<strong>

Fecha de finalización

</strong>

<br>

<span class="text-muted">

<?php echo htmlspecialchars(
    $eleccion['fecha_fin']
); ?>

</span>

</div>


<div class="col-md-4 mb-3">

<strong>

Estado

</strong>

<br><br>


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


</div>


</div>


<?php if (
    !$eleccionAbierta
) { ?>


<div class="eleccion-cerrada">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>

La elección está cerrada.

</strong>

El jurado no puede iniciar nuevas votaciones
hasta que el administrador abra la elección.

</div>


<?php } ?>


<?php } else { ?>


<div class="alert alert-warning mb-0">

<i class="bi bi-exclamation-triangle-fill"></i>

No hay ninguna elección registrada.

</div>


<?php } ?>


</div>


<!-- =====================================================
     ACCIONES
========================================================= -->

<div class="acciones">


<a
href="resultados.php"
class="accion">


<i class="bi bi-trophy-fill"></i>


<h4>

Resultados

</h4>


<p>

Consulta los resultados oficiales de la elección.

</p>


<span class="btn btn-outline-primary">

Ver resultados

</span>


</a>


<a
href="graficas.php"
class="accion">


<i class="bi bi-bar-chart-fill"></i>


<h4>

Gráficas

</h4>


<p>

Consulta las estadísticas y gráficas
de las votaciones.

</p>


<span class="btn btn-outline-primary">

Ver gráficas

</span>


</a>


</div>


</div>


</div>


</body>

</html>
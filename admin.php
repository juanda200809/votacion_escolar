<?php

session_start();

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;
$totalJurados = 0;
$totalCandidatos = 0;
$totalVotos = 0;
$totalElecciones = 0;


/* ESTUDIANTES */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'estudiante'
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalEstudiantes = (int)$fila['total'];
}


/* JURADOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'jurado'
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalJurados = (int)$fila['total'];
}


/* CANDIDATOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalCandidatos = (int)$fila['total'];
}


/* VOTOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalVotos = (int)$fila['total'];
}


/* ELECCIONES */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM elecciones
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $totalElecciones = (int)$fila['total'];
}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$eleccionActual = null;

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

if ($resultado && $resultado->num_rows > 0) {
    $eleccionActual = $resultado->fetch_assoc();
}


/* =========================================================
   NOMBRE DEL ADMINISTRADOR
========================================================= */

$nombreAdmin = $_SESSION['nombre'] ?? 'Administrador';

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


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

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
   BARRA LATERAL
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #1459a6;

    color: white;

    overflow-y: auto;

}


.logo {

    text-align: center;

    padding: 30px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.2);

}


.logo i {

    font-size: 50px;

}


.logo h2 {

    margin-top: 10px;

    font-size: 24px;

    font-weight: bold;

}


.menu {

    padding: 15px 0;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 15px;

    padding: 15px 22px;

    color: white;

    text-decoration: none;

    font-size: 16px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

}


.menu a i {

    font-size: 20px;

    width: 20px;

}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    margin-left: 250px;

    min-height: 100vh;

}


/* =========================================================
   BARRA SUPERIOR
========================================================= */

.topbar {

    height: 75px;

    background: #1674e8;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 4px 12px
        rgba(0,0,0,.15);

}


.topbar h3 {

    margin: 0;

    font-size: 24px;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.container-admin {

    padding: 30px;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background: #cfe2ff;

    border:
        1px solid
        #9ec5fe;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 25px;

}


.bienvenida h2 {

    color: #0d47a1;

    font-weight: bold;

}


.bienvenida p {

    margin-bottom: 0;

    color: #084298;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 30px;

}


.stat {

    background: white;

    border-radius: 16px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.10);

}


.stat i {

    font-size: 40px;

    color: #1674e8;

}


.stat h3 {

    margin: 10px 0 5px;

    color: #1459a6;

    font-size: 32px;

}


.stat p {

    margin: 0;

    color: #6c757d;

}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

.eleccion {

    background: white;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 30px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.10);

}


.eleccion h3 {

    color: #1459a6;

    font-weight: bold;

}


.estado-abierta {

    display: inline-block;

    background: #198754;

    color: white;

    padding: 8px 15px;

    border-radius: 20px;

    font-weight: bold;

}


.estado-cerrada {

    display: inline-block;

    background: #dc3545;

    color: white;

    padding: 8px 15px;

    border-radius: 20px;

    font-weight: bold;

}


/* =========================================================
   ACCESOS
========================================================= */

.titulo-accesos {

    text-align: center;

    color: #222;

    font-size: 30px;

    margin-bottom: 25px;

}


.accesos {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.acceso {

    min-height: 125px;

    border-radius: 14px;

    color: white;

    text-decoration: none;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    font-weight: bold;

    transition: .2s;

    padding: 15px;

}


.acceso:hover {

    color: white;

    transform: translateY(-3px);

    box-shadow:
        0 8px 20px
        rgba(0,0,0,.18);

}


.acceso i {

    font-size: 38px;

    margin-bottom: 12px;

}


.azul {

    background: #1674e8;

}


.verde {

    background: #198754;

}


.celeste {

    background: #20b8d8;

}


.amarillo {

    background: #ffc107;

    color: #000;

}


.amarillo:hover {

    color: #000;

}


.rojo {

    background: #dc3545;

}


.negro {

    background: #212529;

}


/* =========================================================
   PDF
========================================================= */

.pdf {

    background: #b02a37;

}


.pdf:hover {

    background: #8f1f2b;

}


/* =========================================================
   SEPARADOR
========================================================= */

.separador {

    border: 0;

    border-top:
        1px solid
        #ddd;

    margin: 30px 0;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 1000px
) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .accesos {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (
    max-width: 700px
) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }


    .contenido {

        margin-left: 0;

    }


    .estadisticas {

        grid-template-columns:
            1fr;

    }


    .accesos {

        grid-template-columns:
            1fr;

    }


    .topbar {

        padding: 0 15px;

    }


    .topbar h3 {

        font-size: 18px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">


<div class="logo">

<i class="bi bi-bank2"></i>

<h2>

ADMINISTRADOR

</h2>

</div>


<div class="menu">


<a href="admin.php">

<i class="bi bi-house-fill"></i>

<span>
Inicio
</span>

</a>


<a href="estudiantes.php">

<i class="bi bi-people-fill"></i>

<span>
Estudiantes
</span>

</a>


<a href="jurados.php">

<i class="bi bi-person-badge-fill"></i>

<span>
Jurados
</span>

</a>


<a href="exportar_estudiantes.php">

<i class="bi bi-file-earmark-excel-fill"></i>

<span>
Exportar Excel
</span>

</a>


<a href="importar_estudiantes.php">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

<span>
Importar Excel
</span>

</a>


<a href="candidatos.php">

<i class="bi bi-person-vcard-fill"></i>

<span>
Candidatos
</span>

</a>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

<span>
Resultados
</span>

</a>


<a href="elecciones.php">

<i class="bi bi-calendar-event-fill"></i>

<span>
Elecciones
</span>

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

<span>
Gráficas
</span>

</a>


<hr
style="
margin:15px;
border-color:rgba(255,255,255,.3);
">


<a href="abrir_eleccion.php">

<i class="bi bi-unlock-fill"></i>

<span>
Abrir Elección
</span>

</a>


<a href="cerrar_eleccion.php">

<i class="bi bi-lock-fill"></i>

<span>
Cerrar Elección
</span>

</a>


<hr
style="
margin:15px;
border-color:rgba(255,255,255,.3);
">


<a href="logout.php">

<i class="bi bi-box-arrow-right"></i>

<span>
Cerrar Sesión
</span>

</a>


</div>

</div>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<div class="contenido">


<!-- TOPBAR -->

<div class="topbar">


<h3>

<i class="bi bi-shield-lock-fill"></i>

Sistema de Votaciones Escolares

</h3>


<div>

<i class="bi bi-person-fill"></i>

<?php echo htmlspecialchars(
    $nombreAdmin
); ?>

</div>


</div>


<div class="container-admin">


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="bienvenida">


<h2>

Bienvenido, <?php echo htmlspecialchars(
    $nombreAdmin
); ?> 👋

</h2>


<p>

Panel principal de administración
del sistema de votaciones escolares.

</p>


</div>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="estadisticas">


<div class="stat">

<i class="bi bi-people-fill"></i>

<h3>

<?php echo $totalEstudiantes; ?>

</h3>

<p>

Estudiantes

</p>

</div>


<div class="stat">

<i class="bi bi-person-badge-fill"></i>

<h3>

<?php echo $totalJurados; ?>

</h3>

<p>

Jurados

</p>

</div>


<div class="stat">

<i class="bi bi-person-vcard-fill"></i>

<h3>

<?php echo $totalCandidatos; ?>

</h3>

<p>

Candidatos

</p>

</div>


<div class="stat">

<i class="bi bi-check2-square"></i>

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
===================================================== -->

<?php if (
    $eleccionActual !== null
) { ?>


<div class="eleccion">


<div class="row align-items-center">


<div class="col-md-8">


<h3>

<i class="bi bi-calendar-event-fill"></i>

<?php echo htmlspecialchars(
    $eleccionActual['nombre']
); ?>

</h3>


<p class="mb-1">

<?php echo htmlspecialchars(
    $eleccionActual['descripcion']
); ?>

</p>


<small class="text-muted">

Inicio:

<?php echo htmlspecialchars(
    $eleccionActual['fecha_inicio']
); ?>


&nbsp; | &nbsp;


Fin:

<?php echo htmlspecialchars(
    $eleccionActual['fecha_fin']
); ?>

</small>


</div>


<div class="col-md-4 text-md-end mt-3 mt-md-0">


<?php if (
    $eleccionActual['estado']
    === 'abierta'
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


</div>


<?php } ?>


<!-- =====================================================
     ACCESOS RÁPIDOS
===================================================== -->

<h2 class="titulo-accesos">

⚡ Accesos rápidos

</h2>


<div class="accesos">


<!-- ESTUDIANTES -->

<a
href="estudiantes.php"
class="acceso azul">


<i class="bi bi-people-fill"></i>

<span>

Gestionar Estudiantes

</span>

</a>


<!-- JURADOS -->

<a
href="jurados.php"
class="acceso verde">


<i class="bi bi-person-badge-fill"></i>

<span>

Gestionar Jurados

</span>

</a>


<!-- EXPORTAR EXCEL -->

<a
href="exportar_estudiantes.php"
class="acceso verde">


<i class="bi bi-file-earmark-excel-fill"></i>

<span>

Exportar Excel

</span>

</a>


<!-- IMPORTAR EXCEL -->

<a
href="importar_estudiantes.php"
class="acceso verde">


<i class="bi bi-file-earmark-arrow-up-fill"></i>

<span>

Importar Excel

</span>

</a>


<!-- CANDIDATOS -->

<a
href="candidatos.php"
class="acceso celeste">


<i class="bi bi-person-vcard-fill"></i>

<span>

Gestionar Candidatos

</span>

</a>


<!-- RESULTADOS -->

<a
href="resultados.php"
class="acceso amarillo">


<i class="bi bi-trophy-fill"></i>

<span>

Ver Resultados

</span>

</a>


<!-- GRÁFICAS -->

<a
href="graficas.php"
class="acceso celeste">


<i class="bi bi-bar-chart-fill"></i>

<span>

Ver Gráficas

</span>

</a>


<!-- ELECCIONES -->

<a
href="elecciones.php"
class="acceso azul">


<i class="bi bi-calendar-event-fill"></i>

<span>

Gestionar Elecciones

</span>

</a>


<!-- ABRIR -->

<a
href="abrir_eleccion.php"
class="acceso verde">


<i class="bi bi-unlock-fill"></i>

<span>

Abrir Elección

</span>

</a>


<!-- CERRAR -->

<a
href="cerrar_eleccion.php"
class="acceso rojo">


<i class="bi bi-lock-fill"></i>

<span>

Cerrar Elección

</span>

</a>


<!-- =====================================================
     DESCARGAR PDF
===================================================== -->

<a
href="pdf_resultados.php"
class="acceso pdf"
target="_blank">


<i class="bi bi-file-earmark-pdf-fill"></i>

<span>

Descargar PDF

</span>


</a>


<!-- CERRAR SESIÓN -->

<a
href="logout.php"
class="acceso negro">


<i class="bi bi-box-arrow-right"></i>

<span>

Cerrar Sesión

</span>

</a>


</div>


</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
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
   VERIFICAR QUE SEA JURADO
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

    header("Location: login.php");
    exit();
}


/* =========================================================
   DATOS DEL JURADO
========================================================= */

$idJurado =
    (int)$_SESSION['id'];

$nombreJurado =
    $_SESSION['nombre'] ?? 'Jurado';


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
    ORDER BY fecha_inicio DESC
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
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE rol = 'estudiante'
    ");

if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];
}


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];

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


    /* =====================================================
       TOTAL VOTOS
    ===================================================== */

    $stmt =
        $conn->prepare("
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
            (int)$fila['total'];

        $stmt->close();
    }
}


/* =========================================================
   ESTADO DE LA ELECCIÓN
========================================================= */

$eleccionAbierta = false;

if (
    $eleccion &&
    strtolower(
        trim(
            $eleccion['estado']
        )
    ) === "abierta"
) {

    $eleccionAbierta = true;
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

    overflow-y: auto;
}


.logo {

    text-align: center;

    padding: 25px 10px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);
}


.logo-icon {

    font-size: 48px;
}


.logo h1 {

    margin: 8px 0 0;

    font-size: 27px;

    font-weight: bold;
}


.logo p {

    margin: 5px 0 0;

    font-size: 13px;

    opacity: .8;
}


/* =========================================================
   MENÚ
========================================================= */

.menu {

    padding-top: 15px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    color: white;

    text-decoration: none;

    padding: 15px 22px;

    font-size: 16px;

    transition: .2s;
}


.menu a:hover {

    background: #0d4388;
}


.menu a.activo {

    background: #0d4388;

    border-left:
        4px solid white;
}


.menu i {

    width: 22px;

    font-size: 19px;
}


.separador {

    height: 1px;

    background:
        rgba(255,255,255,.20);

    margin: 12px 15px;
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

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);
}


.topbar h4 {

    margin: 0;

    font-weight: bold;
}


.usuario {

    display: flex;

    align-items: center;

    gap: 8px;
}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    padding: 35px;
}


.titulo {

    color: #1453a3;

    font-size: 32px;

    font-weight: bold;
}


.subtitulo {

    color: #6c757d;

    margin-bottom: 25px;
}


/* =========================================================
   TARJETA BIENVENIDA
========================================================= */

.bienvenida {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.bienvenida h2 {

    color: #1453a3;

    font-weight: bold;

    margin-bottom: 8px;
}


.bienvenida p {

    margin: 0;

    color: #6c757d;
}


/* =========================================================
   ESTADO ELECCIÓN
========================================================= */

.estado-eleccion {

    border-radius: 15px;

    padding: 20px;

    margin-bottom: 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;
}


.estado-abierta {

    background: #d1e7dd;

    color: #0f5132;

    border:
        1px solid
        #badbcc;
}


.estado-cerrada {

    background: #f8d7da;

    color: #842029;

    border:
        1px solid
        #f5c2c7;
}


.estado-eleccion h5 {

    margin: 0 0 5px;

    font-weight: bold;
}


.estado-eleccion p {

    margin: 0;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 30px;
}


.estadistica {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    display: flex;

    align-items: center;

    gap: 18px;
}


.estadistica-icono {

    width: 65px;

    height: 65px;

    border-radius: 15px;

    background: #e7f1ff;

    color: #1473ed;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;
}


.estadistica h3 {

    margin: 0;

    color: #1453a3;

    font-size: 30px;

    font-weight: bold;
}


.estadistica p {

    margin: 3px 0 0;

    color: #6c757d;
}


/* =========================================================
   ACCESOS RÁPIDOS
========================================================= */

.seccion-titulo {

    color: #1453a3;

    font-size: 24px;

    font-weight: bold;

    margin-bottom: 18px;
}


.accesos {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


.acceso {

    background: white;

    color: #212529;

    text-decoration: none;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    transition: .2s;

    border:
        2px solid
        transparent;
}


.acceso:hover {

    transform:
        translateY(-3px);

    border-color: #1473ed;

    color: #1453a3;
}


.acceso i {

    display: block;

    font-size: 40px;

    color: #1473ed;

    margin-bottom: 15px;
}


.acceso h5 {

    font-weight: bold;

    margin-bottom: 6px;
}


.acceso p {

    color: #6c757d;

    margin: 0;
}


/* =========================================================
   BOTÓN PRINCIPAL DE VOTACIÓN
========================================================= */

.acceso-votacion {

    background:
        linear-gradient(
            135deg,
            #0d47a1,
            #1473ed
        );

    color: white;

    grid-column:
        span 3;
}


.acceso-votacion:hover {

    color: white;

    border-color:
        #0d47a1;
}


.acceso-votacion i {

    color: white;
}


.acceso-votacion p {

    color: rgba(255,255,255,.85);
}


/* =========================================================
   INFORMACIÓN ELECCIÓN
========================================================= */

.info-eleccion {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 30px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.info-eleccion h4 {

    color: #1453a3;

    font-weight: bold;
}


.info-dato {

    padding: 15px;

    background: #f5f8fc;

    border-radius: 10px;

    margin-top: 15px;
}


.info-dato strong {

    display: block;

    color: #1453a3;

    margin-bottom: 4px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .estadisticas {

        grid-template-columns:
            1fr;
    }

    .accesos {

        grid-template-columns:
            1fr;
    }

    .acceso-votacion {

        grid-column:
            span 1;
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

        height: auto;

        padding: 18px;

        gap: 10px;

        flex-direction: column;

        align-items: flex-start;
    }

    .contenido {

        padding: 20px;
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

<div class="logo-icon">

🗳️

</div>


<h1>

VOTACIONES

</h1>


<p>

Panel del Jurado

</p>

</div>


<div class="menu">


<!-- INICIO -->

<a
href="jurado.php"
class="activo">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<!-- INGRESAR ESTUDIANTE -->

<a
href="ingresar_estudiante.php">

<i class="bi bi-person-vcard-fill"></i>

Ingresar estudiante

</a>


<!-- RESULTADOS -->

<a
href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<!-- GRÁFICAS -->

<a
href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<div class="separador"></div>


<!-- CERRAR SESIÓN -->

<a
href="logout.php">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>

</div>


<!-- =====================================================
     MAIN
========================================================= -->

<div class="main">


<!-- =====================================================
     TOPBAR
========================================================= -->

<div class="topbar">


<h4>

🗳️ Sistema de Votaciones Escolares

</h4>


<div class="usuario">

<i class="bi bi-person-circle fs-4"></i>

<span>

Jurado:

<strong>

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

</strong>

</span>

</div>


</div>


<!-- =====================================================
     CONTENIDO
========================================================= -->

<div class="contenido">


<!-- =====================================================
     BIENVENIDA
========================================================= -->

<div class="bienvenida">


<h2>

👋 Bienvenido,

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

</h2>


<p>

Desde este panel puede gestionar
el proceso de votación de los estudiantes,
consultar resultados y revisar las gráficas.

</p>


</div>


<!-- =====================================================
     ESTADO DE ELECCIÓN
========================================================= -->

<?php if ($eleccion) { ?>


<div class="estado-eleccion
<?php echo $eleccionAbierta
    ? 'estado-abierta'
    : 'estado-cerrada';
?>">


<div>


<h5>

<i class="bi bi-calendar-event-fill"></i>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h5>


<p>

<?php

if ($eleccionAbierta) {

    echo "La elección se encuentra abierta.";

} else {

    echo "La elección se encuentra cerrada.";

}

?>

</p>


</div>


<div>

<?php if ($eleccionAbierta) { ?>

<span class="badge bg-success fs-6">

🟢 ABIERTA

</span>

<?php } else { ?>

<span class="badge bg-danger fs-6">

🔴 CERRADA

</span>

<?php } ?>

</div>


</div>


<?php } else { ?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

No hay elecciones registradas.

</div>


<?php } ?>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

<div class="estadisticas">


<div class="estadistica">


<div class="estadistica-icono">

<i class="bi bi-people-fill"></i>

</div>


<div>

<h3>

<?php echo $totalEstudiantes; ?>

</h3>

<p>

Estudiantes registrados

</p>

</div>


</div>


<div class="estadistica">


<div class="estadistica-icono">

<i class="bi bi-person-video3"></i>

</div>


<div>

<h3>

<?php echo $totalCandidatos; ?>

</h3>

<p>

Candidatos

</p>

</div>


</div>


<div class="estadistica">


<div class="estadistica-icono">

<i class="bi bi-check2-circle"></i>

</div>


<div>

<h3>

<?php echo $totalVotos; ?>

</h3>

<p>

Votos registrados

</p>

</div>


</div>


</div>


<!-- =====================================================
     ACCESOS RÁPIDOS
========================================================= -->

<h3 class="seccion-titulo">

Acciones del jurado

</h3>


<div class="accesos">


<!-- =====================================================
     INGRESAR ESTUDIANTE
========================================================= -->

<a
href="ingresar_estudiante.php"
class="acceso acceso-votacion">


<i class="bi bi-person-vcard-fill"></i>


<h5>

Ingresar estudiante para votar

</h5>


<p>

Ingrese el documento del estudiante
y acompáñelo durante el proceso de votación.

</p>


<span class="btn btn-light mt-3">

<i class="bi bi-arrow-right-circle"></i>

Comenzar

</span>


</a>


<!-- =====================================================
     RESULTADOS
========================================================= -->

<a
href="resultados.php"
class="acceso">


<i class="bi bi-trophy-fill"></i>


<h5>

Resultados

</h5>


<p>

Consulte los resultados de las elecciones
y la votación por candidato.

</p>


</a>


<!-- =====================================================
     GRÁFICAS
========================================================= -->

<a
href="graficas.php"
class="acceso">


<i class="bi bi-bar-chart-fill"></i>


<h5>

Gráficas

</h5>


<p>

Visualice gráficamente los resultados
de las elecciones.

</p>


</a>


</div>


<!-- =====================================================
     INFORMACIÓN DE ELECCIÓN
========================================================= -->

<?php if ($eleccion) { ?>


<div class="info-eleccion">


<h4>

<i class="bi bi-info-circle-fill"></i>

Información de la elección

</h4>


<div class="row">


<div class="col-md-4">


<div class="info-dato">

<strong>

Fecha de inicio

</strong>


<?php echo htmlspecialchars(
    $eleccion['fecha_inicio']
); ?>


</div>


</div>


<div class="col-md-4">


<div class="info-dato">

<strong>

Fecha de finalización

</strong>


<?php echo htmlspecialchars(
    $eleccion['fecha_fin']
); ?>


</div>


</div>


<div class="col-md-4">


<div class="info-dato">

<strong>

Estado

</strong>


<?php if ($eleccionAbierta) { ?>

<span class="text-success fw-bold">

🟢 Elección abierta

</span>

<?php } else { ?>

<span class="text-danger fw-bold">

🔴 Elección cerrada

</span>

<?php } ?>


</div>


</div>


</div>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>


<div class="info-dato">

<strong>

Descripción

</strong>


<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>


</div>


<?php } ?>


</div>


<?php } ?>


</div>

</div>


</body>

</html>
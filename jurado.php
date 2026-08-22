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

$rol = strtolower(trim((string)$_SESSION['rol']));

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

$idJurado = (int)$_SESSION['id'];

$nombreJurado = $_SESSION['nombre'] ?? 'Jurado';
$apellidoJurado = "";


/* =========================================================
   CONSULTAR JURADO
========================================================= */

$stmt = $conn->prepare("
    SELECT nombre, apellido
    FROM usuarios
    WHERE id = ?
    AND LOWER(TRIM(rol)) = 'jurado'
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $idJurado);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $jurado = $resultado->fetch_assoc();

        $nombreJurado = $jurado['nombre'];
        $apellidoJurado = $jurado['apellido'];
    }

    $stmt->close();
}


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;

$resultadoEleccion = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    WHERE LOWER(TRIM(estado)) = 'abierta'
    ORDER BY id DESC
    LIMIT 1
");

if (
    $resultadoEleccion &&
    $resultadoEleccion->num_rows > 0
) {

    $eleccion = $resultadoEleccion->fetch_assoc();
}

$eleccionAbierta = ($eleccion !== null);


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$totalEstudiantes = 0;

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'estudiante'
");

if ($resultado) {

    $datos = $resultado->fetch_assoc();

    $totalEstudiantes = (int)$datos['total'];
}


/* =========================================================
   TOTAL VOTANTES
========================================================= */

$totalVotantes = 0;
$totalPendientes = $totalEstudiantes;

if ($eleccionAbierta) {

    $idEleccion = (int)$eleccion['id'];

    /* ---------------------------------------------
       CANTIDAD DE CARGOS
    --------------------------------------------- */

    $totalCargos = 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM eleccion_cargos
        WHERE id_eleccion = ?
    ");

    if ($stmt) {

        $stmt->bind_param("i", $idEleccion);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $datos = $resultado->fetch_assoc();

        $totalCargos = (int)$datos['total'];

        $stmt->close();
    }


    /* ---------------------------------------------
       ESTUDIANTES QUE COMPLETARON LA VOTACIÓN
    --------------------------------------------- */

    if ($totalCargos > 0) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM (
                SELECT
                    v.id_usuario
                FROM votos v
                INNER JOIN candidatos c
                    ON c.id = v.id_candidato
                WHERE c.id_eleccion = ?
                GROUP BY v.id_usuario
                HAVING COUNT(DISTINCT v.id_cargo) = ?
            ) AS votantes
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $idEleccion,
                $totalCargos
            );

            $stmt->execute();

            $resultado = $stmt->get_result();
            $datos = $resultado->fetch_assoc();

            $totalVotantes = (int)$datos['total'];

            $stmt->close();
        }
    }

    $totalPendientes = max(
        0,
        $totalEstudiantes - $totalVotantes
    );
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Panel del Jurado</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

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

    padding: 25px 10px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);
}


.logo-icon {

    font-size: 50px;
}


.logo h1 {

    margin: 8px 0 0;

    font-size: 30px;

    font-weight: bold;
}


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
   CONTENIDO
========================================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;
}


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
}


.contenido {

    padding: 35px;
}


.titulo {

    color: #1453a3;

    font-size: 32px;

    font-weight: bold;
}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background: #cfe2ff;

    border:
        1px solid #9ec5fe;

    border-radius: 10px;

    padding: 25px;

    color: #084298;
}


.bienvenida h3 {

    font-weight: bold;
}


.bienvenida h2 {

    font-weight: bold;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-top: 25px;
}


.estadistica {

    background: white;

    border-radius: 18px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.estadistica i {

    font-size: 45px;

    color: #1473ed;
}


.estadistica h2 {

    color: #1453a3;

    font-size: 38px;

    font-weight: bold;

    margin: 8px 0;
}


.estadistica p {

    margin: 0;

    font-weight: bold;
}


/* =========================================================
   TARJETA DEL VOTANTE
========================================================= */

.votante-card {

    background: white;

    margin-top: 25px;

    padding: 30px;

    border-radius: 18px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);
}


.votante-card h2 {

    color: #1453a3;

    font-weight: bold;
}


.input-documento {

    height: 55px;

    font-size: 18px;
}


.btn-votar {

    background: #1473ed;

    border: none;

    color: white;

    font-size: 18px;

    font-weight: bold;

    padding: 13px 25px;

    border-radius: 8px;
}


.btn-votar:hover {

    background: #0d5dcc;

    color: white;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    margin-top: 25px;

    padding: 30px;

    border-radius: 18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.estado {

    display: inline-block;

    padding: 8px 18px;

    border-radius: 8px;

    font-weight: bold;
}


.abierta {

    background: #198754;

    color: white;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .estadisticas {

        grid-template-columns: 1fr;
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
⚖️
</div>

<h1>
JURADO
</h1>

</div>


<div class="menu">


<a
href="jurado.php"
class="activo">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<div class="separador"></div>


<a href="logout.php">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>

</div>


<!-- =====================================================
     CONTENIDO PRINCIPAL
===================================================== -->

<div class="main">


<!-- TOPBAR -->

<div class="topbar">

<h4>

⚖️ Sistema de Votaciones Escolares

</h4>


<span>

<i class="bi bi-person-badge-fill"></i>

Jurado

</span>

</div>


<div class="contenido">


<!-- =====================================================
     TÍTULO
===================================================== -->

<h1 class="titulo">

Bienvenido,

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

👋

</h1>


<!-- =====================================================
     BIENVENIDA
===================================================== -->

<div class="bienvenida">

<h3>

⚖️ Panel del Jurado

</h3>


<h2>

Control de votaciones escolares

</h2>


<p class="mb-0">

Desde este panel puedes ingresar el documento
del estudiante que realizará la votación.

</p>

</div>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="estadisticas">


<div class="estadistica">

<i class="bi bi-people-fill"></i>

<h2>

<?php echo $totalEstudiantes; ?>

</h2>

<p>
Estudiantes registrados
</p>

</div>


<div class="estadistica">

<i class="bi bi-person-check-fill"></i>

<h2>

<?php echo $totalVotantes; ?>

</h2>

<p>
Votaciones terminadas
</p>

</div>


<div class="estadistica">

<i class="bi bi-hourglass-split"></i>

<h2>

<?php echo $totalPendientes; ?>

</h2>

<p>
Estudiantes pendientes
</p>

</div>


</div>


<!-- =====================================================
     INGRESAR DOCUMENTO
===================================================== -->

<div class="votante-card">


<h2>

<i class="bi bi-person-vcard-fill"></i>

Registrar estudiante votante

</h2>


<p class="text-muted">

Ingrese el documento del estudiante para comprobar
si puede realizar la votación.

</p>


<hr>


<?php if (!$eleccionAbierta) { ?>


<div class="alert alert-danger">

<i class="bi bi-lock-fill"></i>

<strong>

La elección está cerrada.

</strong>

<br>

No se pueden realizar votaciones en este momento.

</div>


<?php } else { ?>


<form
method="POST"
action="votar_jurado.php">


<div class="mb-4">

<label class="form-label fw-bold">

<i class="bi bi-person-vcard-fill"></i>

Documento del estudiante

</label>


<input

type="text"

name="documento"

class="form-control input-documento"

placeholder="Ingrese el documento del estudiante"

required

autocomplete="off"

maxlength="30">

</div>


<button
type="submit"
class="btn btn-votar">

<i class="bi bi-search"></i>

Buscar estudiante

</button>


</form>


<?php } ?>


</div>


<!-- =====================================================
     ELECCIÓN ACTUAL
===================================================== -->

<div class="eleccion">


<h2 class="text-primary">

<i class="bi bi-calendar-check-fill"></i>

Elección actual

</h2>


<hr>


<?php if ($eleccion) { ?>


<div class="row">


<div class="col-md-8">

<h5>
Nombre
</h5>

<p>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</p>


<?php if (
    !empty($eleccion['descripcion'])
) { ?>

<h5>
Descripción
</h5>

<p>

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>

</div>


<div class="col-md-4">

<h5>
Estado
</h5>


<span class="estado abierta">

🟢 Elección abierta

</span>

</div>

</div>


<?php } else { ?>


<div class="alert alert-danger">

<i class="bi bi-lock-fill"></i>

No existe una elección abierta actualmente.

</div>


<?php } ?>


</div>


</div>

</div>


</body>

</html>
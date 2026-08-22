<?php

session_start();

include("config/conexion.php");


/* =====================================================
   VERIFICAR SESIÓN
===================================================== */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit();

}


/* =====================================================
   VERIFICAR QUE SEA JURADO
===================================================== */

$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    /*
     * Si alguien que no es jurado intenta
     * entrar directamente a esta página,
     * lo enviamos a su panel correspondiente.
     */

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


/* =====================================================
   DATOS DEL JURADO
===================================================== */

$idJurado =
    (int)$_SESSION['id'];

$nombreJurado =
    $_SESSION['nombre'] ?? 'Jurado';


/* =====================================================
   OBTENER INFORMACIÓN DEL JURADO
===================================================== */

$documento = "";
$apellido = "";
$curso = "";


$stmt = $conn->prepare("
    SELECT
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE id = ?
    AND LOWER(TRIM(rol)) = 'jurado'
    LIMIT 1
");


if ($stmt) {

    $stmt->bind_param(
        "i",
        $idJurado
    );

    $stmt->execute();

    $resultado =
        $stmt->get_result();


    if ($resultado->num_rows > 0) {

        $jurado =
            $resultado->fetch_assoc();

        $documento =
            $jurado['documento'];

        $nombreJurado =
            $jurado['nombre'];

        $apellido =
            $jurado['apellido'];

        $curso =
            $jurado['curso'];

    }

    $stmt->close();

}


/* =====================================================
   ELECCIÓN ACTUAL
===================================================== */

$nombreEleccion =
    "Sin elección registrada";

$estadoEleccion =
    "cerrada";

$descripcion =
    "";


$resultado = $conn->query("
    SELECT
        nombre,
        descripcion,
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

    $nombreEleccion =
        $eleccion['nombre'];

    $descripcion =
        $eleccion['descripcion'];

    $estadoEleccion =
        strtolower(
            trim(
                $eleccion['estado']
            )
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

<title>
Panel del Jurado
</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =====================================================
   GENERAL
===================================================== */

* {
    box-sizing:border-box;
}


body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position:fixed;

    left:0;

    top:0;

    width:250px;

    height:100vh;

    background:#1453a3;

    color:white;

}


.logo {

    text-align:center;

    padding:25px 10px;

    border-bottom:
        1px solid
        rgba(255,255,255,.2);

}


.logo-icon {

    font-size:50px;

}


.logo h1 {

    margin:8px 0 0;

    font-size:30px;

    font-weight:bold;

}


.menu {

    padding-top:15px;

}


.menu a {

    display:flex;

    align-items:center;

    gap:12px;

    color:white;

    text-decoration:none;

    padding:15px 22px;

    font-size:16px;

}


.menu a:hover {

    background:#0d4388;

}


.menu i {

    width:22px;

    font-size:19px;

}


.separador {

    height:1px;

    background:
        rgba(255,255,255,.2);

    margin:12px 15px;

}


/* =====================================================
   PRINCIPAL
===================================================== */

.main {

    margin-left:250px;

    min-height:100vh;

}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    height:70px;

    background:#1473ed;

    color:white;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.topbar h4 {

    margin:0;

}


/* =====================================================
   CONTENIDO
===================================================== */

.contenido {

    padding:35px;

}


.titulo {

    color:#1453a3;

    font-size:32px;

    font-weight:bold;

}


/* =====================================================
   BIENVENIDA
===================================================== */

.bienvenida {

    background:#cfe2ff;

    border:
        1px solid #9ec5fe;

    border-radius:10px;

    padding:25px;

    color:#084298;

}


.bienvenida h3 {

    font-weight:bold;

}


.bienvenida h2 {

    font-weight:bold;

}


/* =====================================================
   TARJETAS
===================================================== */

.tarjetas {

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:22px;

    margin-top:30px;

}


.tarjeta {

    background:white;

    border-radius:18px;

    padding:30px;

    text-align:center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.tarjeta i {

    font-size:55px;

    color:#1473ed;

    margin-bottom:15px;

}


.tarjeta h3 {

    color:#1453a3;

    font-weight:bold;

}


/* =====================================================
   ELECCIÓN
===================================================== */

.eleccion {

    background:white;

    margin-top:30px;

    padding:30px;

    border-radius:18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.estado-abierta {

    display:inline-block;

    background:#198754;

    color:white;

    padding:8px 18px;

    border-radius:8px;

    font-weight:bold;

}


.estado-cerrada {

    display:inline-block;

    background:#dc3545;

    color:white;

    padding:8px 18px;

    border-radius:8px;

    font-weight:bold;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:800px) {

    .sidebar {

        position:relative;

        width:100%;

        height:auto;

    }


    .main {

        margin-left:0;

    }


    .tarjetas {

        grid-template-columns:1fr;

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
⚖️
</div>

<h1>
JURADO
</h1>

</div>


<div class="menu">


<a href="jurado.php">

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
     PRINCIPAL
===================================================== -->

<div class="main">


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

Bienvenido al sistema de votaciones escolares.

</h2>


<p class="mb-0 mt-2">

Desde este panel puedes consultar la información
de la elección y verificar sus resultados.

</p>

</div>


<!-- =====================================================
     TARJETAS
===================================================== -->

<div class="tarjetas">


<div class="tarjeta">

<i class="bi bi-person-badge-fill"></i>

<h3>

Jurado

</h3>

<p>

<?php echo htmlspecialchars(
    $nombreJurado
); ?>

<?php echo htmlspecialchars(
    $apellido
); ?>

</p>

</div>


<div class="tarjeta">

<i class="bi bi-calendar-event-fill"></i>

<h3>

Elección

</h3>

<p>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</p>

</div>


<div class="tarjeta">

<i class="bi bi-shield-check"></i>

<h3>

Estado

</h3>


<?php if ($estadoEleccion === "abierta") { ?>

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


<!-- =====================================================
     INFORMACIÓN
===================================================== -->

<div class="eleccion">


<h2 class="text-primary">

<i class="bi bi-info-circle-fill"></i>

Información de la elección

</h2>


<hr>


<h5>

Nombre

</h5>

<p>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</p>


<h5>

Descripción

</h5>

<p>

<?php

if ($descripcion !== "") {

    echo htmlspecialchars(
        $descripcion
    );

} else {

    echo "No hay descripción disponible.";

}

?>

</p>


<h5>

Estado

</h5>


<?php if ($estadoEleccion === "abierta") { ?>

<span class="estado-abierta">

🟢 Abierta

</span>

<?php } else { ?>

<span class="estado-cerrada">

🔴 Cerrada

</span>

<?php } ?>


</div>


</div>

</div>


</body>

</html>
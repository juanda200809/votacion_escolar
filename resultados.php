<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


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

$rol = strtolower(trim($_SESSION['rol']));

/*
 * Resultados puede ser consultado por administrador y jurado.
 */
if (
    $rol !== 'administrador' &&
    $rol !== 'jurado'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$idUsuario = (int)$_SESSION['id'];

$nombreUsuario =
    $_SESSION['nombre']
    ?? 'Usuario';


/* =========================================================
   OBTENER ELECCIÓN
========================================================= */

$idEleccion = 0;


/*
 * Primero intentamos recibirla por GET.
 */
if (
    isset($_GET['id_eleccion']) &&
    is_numeric($_GET['id_eleccion'])
) {

    $idEleccion =
        (int)$_GET['id_eleccion'];

}


/*
 * Si no viene por GET, usamos la elección guardada
 * en sesión.
 */
if (
    $idEleccion <= 0 &&
    isset($_SESSION['id_eleccion'])
) {

    $idEleccion =
        (int)$_SESSION['id_eleccion'];

}


/*
 * Si todavía no tenemos elección,
 * buscamos la última registrada.
 */
if ($idEleccion <= 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM elecciones
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $fila =
            $resultado->fetch_assoc();

        $stmt->close();

        if ($fila) {

            $idEleccion =
                (int)$fila['id'];

        }

    }

}


/*
 * Si no existe ninguna elección.
 */
if ($idEleccion <= 0) {

    die(
        "No existe ninguna elección registrada."
    );

}


/*
 * Guardar elección en sesión.
 */
$_SESSION['id_eleccion'] =
    $idEleccion;


/* =========================================================
   INFORMACIÓN DE LA ELECCIÓN
========================================================= */

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


if (!$eleccion) {

    die(
        "La elección seleccionada no existe."
    );

}


/* =========================================================
   INFORMACIÓN DE LA MESA DEL JURADO
========================================================= */

$mesa = null;

if ($rol === 'jurado') {

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

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idUsuario
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $mesa =
            $resultado->fetch_assoc();

        $stmt->close();

    }

}


/* =========================================================
   CONTAR VOTOS
========================================================= */

$totalVotos = 0;

$stmt = $conn->prepare("
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
        (int)($fila['total'] ?? 0);

    $stmt->close();

}


/* =========================================================
   CONTAR CANDIDATOS
========================================================= */

$totalCandidatos = 0;

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


/* =========================================================
   CONTAR CARGOS
========================================================= */

$totalCargos = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM eleccion_cargos
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

    $totalCargos =
        (int)($fila['total'] ?? 0);

    $stmt->close();

}


/* =========================================================
   OBTENER CANDIDATOS Y VOTOS
========================================================= */

$candidatosPorCargo = [];


$sql = "
    SELECT

        c.id,
        c.nombre,
        c.apellido,
        c.curso,
        c.foto,
        c.id_cargo,

        ca.nombre_cargo,

        COUNT(v.id) AS votos

    FROM candidatos c

    INNER JOIN cargos ca
        ON ca.id = c.id_cargo

    LEFT JOIN votos v
        ON v.id_candidato = c.id
        AND v.id_eleccion = ?

    WHERE c.id_eleccion = ?

    GROUP BY
        c.id,
        c.nombre,
        c.apellido,
        c.curso,
        c.foto,
        c.id_cargo,
        ca.nombre_cargo

    ORDER BY
        c.id_cargo ASC,
        votos DESC,
        c.apellido ASC,
        c.nombre ASC
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Error al obtener los resultados: "
        . $conn->error
    );

}


$stmt->bind_param(
    "ii",
    $idEleccion,
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


while (
    $fila =
    $resultado->fetch_assoc()
) {

    $idCargo =
        (int)$fila['id_cargo'];


    if (
        !isset(
            $candidatosPorCargo[$idCargo]
        )
    ) {

        $candidatosPorCargo[$idCargo] = [

            'nombre_cargo' =>
                $fila['nombre_cargo'],

            'candidatos' => []

        ];

    }


    $candidatosPorCargo[$idCargo]['candidatos'][] =
        $fila;

}


$stmt->close();


/* =========================================================
   FUNCIÓN PARA FOTOS
========================================================= */

function obtenerFoto($foto)
{

    $foto =
        trim(
            (string)$foto
        );


    if ($foto === '') {

        return '';

    }


    /*
     * URL externa.
     */
    if (
        filter_var(
            $foto,
            FILTER_VALIDATE_URL
        )
    ) {

        return $foto;

    }


    /*
     * Normalizar barras.
     */
    $foto =
        str_replace(
            '\\',
            '/',
            $foto
        );


    $foto =
        ltrim(
            $foto,
            '/'
        );


    /*
     * Si la ruta guardada ya existe.
     */
    $rutaFisica =
        __DIR__
        . DIRECTORY_SEPARATOR
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $foto
        );


    if (
        file_exists($rutaFisica)
    ) {

        return $foto;

    }


    /*
     * Buscar por nombre en carpetas comunes.
     */
    $nombreArchivo =
        basename($foto);


    $carpetas = [

        'uploads/candidatos/',
        'imagenes/candidatos/',
        'img/candidatos/',
        'fotos/candidatos/',
        'candidatos/',
        'fotos/',
        'uploads/'

    ];


    foreach (
        $carpetas as $carpeta
    ) {

        $ruta =
            $carpeta
            . $nombreArchivo;


        $rutaFisica =
            __DIR__
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $ruta
            );


        if (
            file_exists($rutaFisica)
        ) {

            return $ruta;

        }

    }


    return '';

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
Resultados - Votaciones Escolares
</title>


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

    background: #eef4fb;

    color: #1f2937;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 70px;

    background: #1769d2;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

}


.topbar-title {

    font-size: 21px;

    font-weight: 700;

}


.usuario {

    font-size: 15px;

    font-weight: 600;

}


/* =========================================================
   LAYOUT
========================================================= */

.layout {

    display: flex;

    min-height:
        calc(100vh - 70px);

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    background: #185ca8;

    color: white;

    flex-shrink: 0;

}


.sidebar-header {

    text-align: center;

    padding:
        35px 15px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);

}


.sidebar-icon {

    font-size: 45px;

    margin-bottom: 8px;

}


.sidebar-header h2 {

    margin: 0;

    font-size: 28px;

}


.sidebar-header p {

    margin:
        5px 0 0;

    opacity: .85;

}


.menu {

    padding:
        15px 10px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 13px;

    padding:
        15px;

    color: white;

    text-decoration: none;

    border-radius: 9px;

    margin-bottom: 4px;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

}


.menu a.active {

    background:
        rgba(255,255,255,.20);

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    flex: 1;

    padding: 35px;

    overflow-x: auto;

}


.container {

    max-width: 1150px;

    margin: auto;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.election-card {

    background: white;

    border-radius: 18px;

    padding: 28px;

    margin-bottom: 25px;

    box-shadow:
        0 7px 25px
        rgba(0,0,0,.08);

}


.election-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

}


.election-title {

    margin:
        0 0 8px;

    color: #1769d2;

    font-size: 29px;

}


.election-description {

    margin: 0;

    color: #64748b;

}


.estado {

    padding:
        9px 15px;

    border-radius: 20px;

    font-weight: 700;

    white-space: nowrap;

}


.estado-abierta {

    background: #d1fae5;

    color: #047857;

}


.estado-cerrada {

    background: #e5e7eb;

    color: #4b5563;

}


.fechas {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-top: 25px;

}


.fecha {

    background: #f8fafc;

    border:
        1px solid #dbe4ef;

    border-radius: 12px;

    padding: 15px;

}


.fecha-label {

    color: #64748b;

    font-size: 14px;

    margin-bottom: 5px;

}


.fecha-valor {

    color: #175cae;

    font-weight: 700;

}


/* =========================================================
   MESA
========================================================= */

.mesa-card {

    border-radius: 17px;

    padding: 22px;

    margin-bottom: 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

}


.mesa-abierta {

    background: #ecfdf5;

    border:
        1px solid #a7f3d0;

}


.mesa-cerrada {

    background: #f1f5f9;

    border:
        1px solid #cbd5e1;

}


.mesa-info {

    display: flex;

    align-items: center;

    gap: 18px;

}


.mesa-icon {

    width: 60px;

    height: 60px;

    border-radius: 14px;

    background: #dbeafe;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

}


.mesa-nombre {

    margin:
        0 0 5px;

    color: #1453a3;

}


.mesa-texto {

    margin: 4px 0;

    color: #64748b;

}


.mesa-estado {

    font-weight: 700;

    margin-top: 8px;

}


.mesa-abierta .mesa-estado {

    color: #047857;

}


.mesa-cerrada .mesa-estado {

    color: #64748b;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 30px;

}


.stat {

    background: white;

    border-radius: 17px;

    padding: 25px;

    text-align: center;

    box-shadow:
        0 7px 22px
        rgba(0,0,0,.07);

}


.stat-icon {

    font-size: 35px;

}


.stat-number {

    font-size: 34px;

    color: #175cae;

    font-weight: 700;

    margin-top: 5px;

}


.stat-label {

    color: #64748b;

    font-weight: 600;

    margin-top: 5px;

}


/* =========================================================
   RESULTADOS
========================================================= */

.cargo {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-bottom: 30px;

    box-shadow:
        0 7px 25px
        rgba(0,0,0,.08);

}


.cargo-header {

    background: #1760ad;

    color: white;

    padding:
        20px 25px;

    font-size: 24px;

    font-weight: 700;

}


.tabla-contenedor {

    overflow-x: auto;

}


.tabla {

    width: 100%;

    min-width: 750px;

    border-collapse: collapse;

}


.tabla th {

    background: #cfe0fa;

    color: #1556a1;

    text-align: left;

    padding: 15px;

}


.tabla td {

    padding: 15px;

    border-bottom:
        1px solid #e5e7eb;

}


.candidato {

    display: flex;

    align-items: center;

    gap: 13px;

}


.foto {

    width: 55px;

    height: 55px;

    object-fit: cover;

    border-radius: 50%;

    border:
        2px solid #dbeafe;

}


.foto-vacia {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    background: #dbeafe;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

}


.nombre {

    font-weight: 700;

}


.votos {

    font-size: 19px;

    font-weight: 700;

    color: #1769d2;

}


.porcentaje {

    font-weight: 700;

    color: #175cae;

}


.ganador {

    background: #fbbf24;

    color: #78350f;

    padding:
        7px 11px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 800;

}


.sin-resultados {

    padding: 45px;

    text-align: center;

    color: #64748b;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {

        width: 210px;

    }


    .stats {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 700px) {

    .layout {

        display: block;

    }


    .sidebar {

        width: 100%;

    }


    .content {

        padding: 20px;

    }


    .topbar {

        padding:
            0 15px;

    }


    .usuario {

        display: none;

    }


    .election-top {

        flex-direction: column;

    }


    .fechas {

        grid-template-columns: 1fr;

    }


    .mesa-card {

        flex-direction: column;

        align-items: flex-start;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
========================================================= -->

<header class="topbar">


<div class="topbar-title">

⚖️ Sistema de Votaciones Escolares

</div>


<div class="usuario">

👤

<?php

echo htmlspecialchars(
    $nombreUsuario,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</header>


<div class="layout">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


<div class="sidebar-header">


<div class="sidebar-icon">

🗳️

</div>


<h2>

VOTACIONES

</h2>


<p>

Panel de <?php

echo $rol === 'administrador'
    ? 'Administrador'
    : 'Jurado';

?>

</p>


</div>


<nav class="menu">


<?php if (
    $rol === 'administrador'
) { ?>


<a href="admin.php">

🏠

<span>

Inicio

</span>

</a>


<a href="jurados.php">

👥

<span>

Jurados

</span>

</a>


<a href="candidatos.php">

👤

<span>

Candidatos

</span>

</a>


<a href="elecciones.php">

📅

<span>

Elecciones

</span>

</a>


<a
    href="resultados.php?id_eleccion=<?php echo $idEleccion; ?>"
    class="active"
>

🏆

<span>

Resultados

</span>

</a>


<a
    href="graficas.php?id_eleccion=<?php echo $idEleccion; ?>"
>

📊

<span>

Gráficas

</span>

</a>


<?php } else { ?>


<a href="jurado.php">

🏠

<span>

Inicio

</span>

</a>


<a
    href="ingresar_estudiante.php?id_eleccion=<?php echo $idEleccion; ?>"
>

👤

<span>

Ingresar estudiante

</span>

</a>


<a
    href="resultados.php?id_eleccion=<?php echo $idEleccion; ?>"
    class="active"
>

🏆

<span>

Resultados

</span>

</a>


<a
    href="graficas.php?id_eleccion=<?php echo $idEleccion; ?>"
>

📊

<span>

Gráficas

</span>

</a>


<?php } ?>


<div
    style="
        height:1px;
        background:rgba(255,255,255,.18);
        margin:15px 5px;
    "
></div>


<a
    href="logout.php"
    onclick="
        return confirm(
            '¿Está seguro de cerrar sesión?'
        );
    "
>

🚪

<span>

Cerrar sesión

</span>

</a>


</nav>


</aside>


<!-- =========================================================
     CONTENIDO
========================================================= -->

<main class="content">


<div class="container">


<!-- =========================================================
     ELECCIÓN
========================================================= -->

<section class="election-card">


<div class="election-top">


<div>


<h1 class="election-title">

🗳️

<?php

echo htmlspecialchars(
    $eleccion['nombre'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</h1>


<p class="election-description">

<?php

echo htmlspecialchars(
    $eleccion['descripcion']
    ??
    'Proceso democrático institucional',
    ENT_QUOTES,
    'UTF-8'
);

?>

</p>


</div>


<?php

$estadoEleccion =
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    );


if (
    $estadoEleccion === 'abierta'
) {

?>


<span class="estado estado-abierta">

🟢 Elección abierta

</span>


<?php

} else {

?>


<span class="estado estado-cerrada">

🔒 Elección cerrada

</span>


<?php

}

?>


</div>


<div class="fechas">


<div class="fecha">


<div class="fecha-label">

📅 Fecha de inicio

</div>


<div class="fecha-valor">

<?php

echo htmlspecialchars(
    $eleccion['fecha_inicio']
    ??
    'No definida',
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


<div class="fecha">


<div class="fecha-label">

📅 Fecha de finalización

</div>


<div class="fecha-valor">

<?php

echo htmlspecialchars(
    $eleccion['fecha_fin']
    ??
    'No definida',
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


</div>


</section>


<!-- =========================================================
     MESA DEL JURADO
========================================================= -->

<?php if (
    $rol === 'jurado'
) { ?>


<?php if (
    $mesa
) { ?>


<div
    class="
        mesa-card
        <?php

        echo strtolower(
            trim(
                (string)$mesa['estado']
            )
        ) === 'abierta'
            ? 'mesa-abierta'
            : 'mesa-cerrada';

        ?>
    "
>


<div class="mesa-info">


<div class="mesa-icon">

<?php

echo strtolower(
    trim(
        (string)$mesa['estado']
    )
) === 'abierta'
    ? '🗳️'
    : '🔒';

?>

</div>


<div>


<h2 class="mesa-nombre">

<?php

echo htmlspecialchars(
    $mesa['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</h2>


<p class="mesa-texto">

Mesa asignada al jurado

<strong>

<?php

echo htmlspecialchars(
    $nombreUsuario,
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</p>


<div class="mesa-estado">

<?php

if (
    strtolower(
        trim(
            (string)$mesa['estado']
        )
    ) === 'abierta'
) {

    echo "🟢 Mesa de votación abierta";

} else {

    echo "🔴 Mesa de votación cerrada";

}

?>

</div>


<?php if (
    !empty($mesa['fecha_cierre'])
) { ?>


<p class="mesa-texto">

📅 Fecha de cierre:

<?php

echo htmlspecialchars(
    $mesa['fecha_cierre'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</p>


<?php } ?>


</div>


</div>


</div>


<?php } else { ?>


<div class="mesa-card mesa-cerrada">


<div class="mesa-info">


<div class="mesa-icon">

⚠️

</div>


<div>


<h2 class="mesa-nombre">

Mesa no asignada

</h2>


<p class="mesa-texto">

No tienes una mesa de votación asignada
para esta elección.

</p>


<div class="mesa-estado">

🚫 No puedes iniciar una votación

</div>


</div>


</div>


</div>


<?php } ?>


<?php } ?>


<!-- =========================================================
     ESTADÍSTICAS
========================================================= -->

<section class="stats">


<div class="stat">


<div class="stat-icon">

🗳️

</div>


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


<div class="stat-icon">

👥

</div>


<div class="stat-number">

<?php

echo $totalCandidatos;

?>

</div>


<div class="stat-label">

Candidatos

</div>


</div>


<div class="stat">


<div class="stat-icon">

🏅

</div>


<div class="stat-number">

<?php

echo $totalCargos;

?>

</div>


<div class="stat-label">

Cargos

</div>


</div>


</section>


<!-- =========================================================
     RESULTADOS
========================================================= -->

<?php

if (
    empty($candidatosPorCargo)
) {

?>


<section class="cargo">


<div class="cargo-header">

🏆 Resultados

</div>


<div class="sin-resultados">

👤

<h3>

No hay resultados disponibles

</h3>


<p>

No existen candidatos registrados
para esta elección.

</p>


</div>


</section>


<?php

} else {


foreach (
    $candidatosPorCargo
    as $cargo
) {


    $candidatos =
        $cargo['candidatos'];


    /*
     * Encontrar máximo de votos.
     */

    $maxVotos = 0;


    foreach (
        $candidatos
        as $candidato
    ) {

        $votos =
            (int)$candidato['votos'];


        if (
            $votos > $maxVotos
        ) {

            $maxVotos =
                $votos;

        }

    }


    /*
     * Total de votos del cargo.
     */

    $totalVotosCargo = 0;


    foreach (
        $candidatos
        as $candidato
    ) {

        $totalVotosCargo +=
            (int)$candidato['votos'];

    }

?>


<section class="cargo">


<div class="cargo-header">

🏅

<?php

echo htmlspecialchars(
    $cargo['nombre_cargo'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


<div class="tabla-contenedor">


<table class="tabla">


<thead>


<tr>


<th>

👤 Candidato

</th>


<th>

🎓 Curso

</th>


<th>

🗳️ Votos

</th>


<th>

📊 Porcentaje

</th>


<th>

🏆 Estado

</th>


</tr>


</thead>


<tbody>


<?php

foreach (
    $candidatos
    as $candidato
) {


    $votosCandidato =
        (int)$candidato['votos'];


    $porcentaje = 0;


    if (
        $totalVotosCargo > 0
    ) {

        $porcentaje =
            (
                $votosCandidato
                /
                $totalVotosCargo
            ) * 100;

    }


    $esGanador =
        (
            $maxVotos > 0 &&
            $votosCandidato === $maxVotos
        );


    $foto =
        obtenerFoto(
            $candidato['foto'] ?? ''
        );

?>


<tr>


<td>


<div class="candidato">


<?php if (
    $foto !== ''
) { ?>


<img
    src="<?php echo htmlspecialchars(
        $foto,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    class="foto"
    alt="Foto del candidato"
    onerror="
        this.style.display='none';
        this.nextElementSibling.style.display='flex';
    "
>


<div
    class="foto-vacia"
    style="display:none;"
>

👤

</div>


<?php } else { ?>


<div class="foto-vacia">

👤

</div>


<?php } ?>


<div class="nombre">


<?php

echo htmlspecialchars(
    $candidato['nombre']
    . ' '
    . $candidato['apellido'],
    ENT_QUOTES,
    'UTF-8'
);

?>


</div>


</div>


</td>


<td>


🎓

<?php

echo htmlspecialchars(
    $candidato['curso']
    ?? '',
    ENT_QUOTES,
    'UTF-8'
);

?>


</td>


<td>


<span class="votos">

<?php

echo $votosCandidato;

?>

</span>


</td>


<td>


<span class="porcentaje">

<?php

echo number_format(
    $porcentaje,
    1
);

?>%

</span>


</td>


<td>


<?php if (
    $esGanador
) { ?>


<span class="ganador">

🏆 GANADOR

</span>


<?php } else { ?>


—

<?php } ?>


</td>


</tr>


<?php

}

?>


</tbody>


</table>


</div>


</section>


<?php

}

}

?>


</div>


</main>


</div>


</body>

</html>
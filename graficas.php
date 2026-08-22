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
   ADMINISTRADOR Y JURADO
========================================================= */

$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);

if (
    $rol !== "administrador" &&
    $rol !== "jurado"
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   ELECCIONES
========================================================= */

$elecciones = [];

$resultadoElecciones = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    ORDER BY fecha_inicio DESC
");

if ($resultadoElecciones) {

    while (
        $fila =
        $resultadoElecciones->fetch_assoc()
    ) {

        $elecciones[] = $fila;
    }
}


/* =========================================================
   ELECCIÓN SELECCIONADA
========================================================= */

$idEleccion =
    isset($_GET['id_eleccion'])
    ? (int)$_GET['id_eleccion']
    : 0;


/* =========================================================
   SI NO SE SELECCIONA,
   TOMAR LA ÚLTIMA ELECCIÓN
========================================================= */

if ($idEleccion <= 0 && count($elecciones) > 0) {

    $idEleccion =
        (int)$elecciones[0]['id'];
}


/* =========================================================
   DATOS ELECCIÓN
========================================================= */

$eleccion = null;

foreach (
    $elecciones as $e
) {

    if (
        (int)$e['id'] ===
        $idEleccion
    ) {

        $eleccion = $e;

        break;
    }
}


/* =========================================================
   VARIABLES
========================================================= */

$datosCandidatos = [];

$labelsCandidatos = [];

$valoresCandidatos = [];

$labelsCargos = [];

$datosCargos = [];

$totalVotos = 0;

$totalCandidatos = 0;

$totalCargos = 0;


/* =========================================================
   SI EXISTE ELECCIÓN
========================================================= */

if ($eleccion) {


    /* =====================================================
       CANDIDATOS Y VOTOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT

            ca.id,

            ca.nombre,

            ca.apellido,

            ca.curso,

            ca.foto,

            ca.id_cargo,

            cg.nombre_cargo,

            COUNT(v.id) AS votos

        FROM candidatos ca

        INNER JOIN cargos cg
            ON cg.id = ca.id_cargo

        LEFT JOIN votos v
            ON v.id_candidato = ca.id

        WHERE ca.id_eleccion = ?

        GROUP BY

            ca.id,
            ca.nombre,
            ca.apellido,
            ca.curso,
            ca.foto,
            ca.id_cargo,
            cg.nombre_cargo

        ORDER BY

            cg.id ASC,

            votos DESC,

            ca.id ASC

    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $datosCandidatos[] =
                $fila;


            $labelsCandidatos[] =
                $fila['nombre'] .
                ' ' .
                $fila['apellido'];


            $valoresCandidatos[] =
                (int)$fila['votos'];
        }


        $stmt->close();

    }


    /* =====================================================
       TOTAL CANDIDATOS
    ===================================================== */

    $totalCandidatos =
        count($datosCandidatos);


    /* =====================================================
       TOTAL VOTOS
    ===================================================== */

    $stmt = $conn->prepare("

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

        $datos =
            $resultado->fetch_assoc();

        $totalVotos =
            (int)$datos['total'];

        $stmt->close();
    }


    /* =====================================================
       VOTOS POR CARGO
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT

            cg.id,

            cg.nombre_cargo,

            COUNT(v.id) AS votos

        FROM cargos cg

        INNER JOIN eleccion_cargos ec

            ON ec.id_cargo = cg.id

        LEFT JOIN candidatos ca

            ON ca.id_cargo = cg.id

            AND ca.id_eleccion = ec.id_eleccion

        LEFT JOIN votos v

            ON v.id_candidato = ca.id

        WHERE ec.id_eleccion = ?

        GROUP BY

            cg.id,

            cg.nombre_cargo

        ORDER BY

            cg.id ASC

    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $labelsCargos[] =
                $fila['nombre_cargo'];

            $datosCargos[] =
                (int)$fila['votos'];
        }


        $stmt->close();
    }


    $totalCargos =
        count($labelsCargos);
}


/* =========================================================
   NOMBRE USUARIO
========================================================= */

$nombreUsuario =
    $_SESSION['nombre'] ?? 'Usuario';


/* =========================================================
   COLORES
========================================================= */

$colores = [

    '#0d6efd',

    '#198754',

    '#ffc107',

    '#dc3545',

    '#6f42c1',

    '#fd7e14',

    '#20c997',

    '#0dcaf0',

    '#6610f2',

    '#d63384'

];


$coloresCandidatos = [];


for (
    $i = 0;
    $i < count($valoresCandidatos);
    $i++
) {

    $coloresCandidatos[] =
        $colores[
            $i % count($colores)
        ];
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

Gráficas electorales

</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<!-- CHART.JS -->

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

    font-weight: bold;
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
   SELECTOR
========================================================= */

.selector {

    background: white;

    padding: 25px;

    border-radius: 18px;

    margin-top: 20px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.btn-ver {

    background: #1473ed;

    color: white;

    border: none;

    font-weight: bold;
}


.btn-ver:hover {

    background: #0d5dcc;

    color: white;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.eleccion h3 {

    color: #1453a3;

    font-weight: bold;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadistica {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 20px;

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

    font-size: 38px;

    color: #1453a3;

    font-weight: bold;

    margin: 8px 0;
}


/* =========================================================
   GRÁFICAS
========================================================= */

.grafica-card {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);
}


.grafica-card h3 {

    color: #1453a3;

    font-weight: bold;

    margin-bottom: 20px;
}


.grafica-container {

    position: relative;

    width: 100%;

    height: 450px;
}


/* =========================================================
   TABLA RESUMEN
========================================================= */

.tabla {

    margin-top: 25px;
}


.tabla th {

    background: #cfe2ff;

    color: #084298;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }


    .main {

        margin-left: 0;
    }


    .grafica-container {

        height: 350px;
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
========================================================= -->

<div class="sidebar">


<div class="logo">

<div class="logo-icon">

🗳️

</div>


<h1>

VOTACIONES

</h1>


<p>

<?php

echo $rol === "jurado"
    ? "Panel del Jurado"
    : "Panel del Administrador";

?>

</p>

</div>


<div class="menu">


<!-- INICIO -->

<?php if (
    $rol === "administrador"
) { ?>


<a href="admin.php">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<?php } else { ?>


<a href="jurado.php">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<?php } ?>


<!-- INGRESAR ESTUDIANTE -->

<?php if (
    $rol === "jurado"
) { ?>


<a href="ingresar_estudiante.php">

<i class="bi bi-person-vcard-fill"></i>

Ingresar estudiante

</a>


<?php } ?>


<!-- RESULTADOS -->

<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<!-- GRÁFICAS -->

<a
href="graficas.php"
class="activo">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<!-- JURADOS SOLO ADMIN -->

<?php if (
    $rol === "administrador"
) { ?>


<div class="separador"></div>


<a href="jurados.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>


<?php } ?>


<div class="separador"></div>


<!-- CERRAR SESIÓN -->

<a href="logout.php">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>

</div>


<!-- =====================================================
     MAIN
========================================================= -->

<div class="main">


<!-- TOPBAR -->

<div class="topbar">


<h4>

🗳️ Sistema de Votaciones Escolares

</h4>


<span>

<i class="bi bi-person-circle"></i>

<?php echo htmlspecialchars(
    $nombreUsuario
); ?>

</span>


</div>


<div class="contenido">


<!-- =====================================================
     TÍTULO
========================================================= -->

<h1 class="titulo">

<i class="bi bi-bar-chart-fill"></i>

Gráficas electorales

</h1>


<p class="text-muted">

Consulta visualmente los resultados
de las elecciones.

</p>


<!-- =====================================================
     SELECTOR DE ELECCIÓN
========================================================= -->

<div class="selector">


<form method="GET">


<div class="row align-items-end">


<div class="col-md-9">


<label class="form-label fw-bold">

<i class="bi bi-calendar-event"></i>

Elección

</label>


<select
name="id_eleccion"
class="form-select"
required>


<option value="">

Seleccione una elección

</option>


<?php foreach (
    $elecciones as $e
) { ?>


<option

value="<?php echo (int)$e['id']; ?>"

<?php

if (
    $idEleccion ===
    (int)$e['id']
) {

    echo "selected";

}

?>

>

<?php echo htmlspecialchars(
    $e['nombre']
); ?>

</option>


<?php } ?>


</select>


</div>


<div class="col-md-3 mt-3 mt-md-0">


<button
type="submit"
class="btn btn-ver w-100">

<i class="bi bi-search"></i>

Ver gráfica

</button>


</div>


</div>


</form>


</div>


<?php if (
    $eleccion
) { ?>


<!-- =====================================================
     INFORMACIÓN ELECCIÓN
========================================================= -->

<div class="eleccion">


<h3>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h3>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p>

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


<div class="row mt-3">


<div class="col-md-4">

<strong>

Fecha de inicio

</strong>

<br>

<?php echo htmlspecialchars(
    $eleccion['fecha_inicio']
); ?>

</div>


<div class="col-md-4">

<strong>

Fecha de finalización

</strong>

<br>

<?php echo htmlspecialchars(
    $eleccion['fecha_fin']
); ?>

</div>


<div class="col-md-4">

<strong>

Estado

</strong>

<br>


<span class="badge
<?php

echo strtolower(
    trim(
        $eleccion['estado']
    )
) === "abierta"

    ? "bg-success"

    : "bg-secondary";

?>">

<?php echo htmlspecialchars(
    ucfirst(
        $eleccion['estado']
    )
); ?>

</span>


</div>


</div>


</div>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

<div class="row">


<div class="col-md-4">


<div class="estadistica">


<i class="bi bi-hand-index-thumb-fill"></i>


<h2>

<?php echo $totalVotos; ?>

</h2>


<p>

Votos registrados

</p>


</div>


</div>


<div class="col-md-4">


<div class="estadistica">


<i class="bi bi-people-fill"></i>


<h2>

<?php echo $totalCandidatos; ?>

</h2>


<p>

Candidatos

</p>


</div>


</div>


<div class="col-md-4">


<div class="estadistica">


<i class="bi bi-award-fill"></i>


<h2>

<?php echo $totalCargos; ?>

</h2>


<p>

Cargos

</p>


</div>


</div>


</div>


<!-- =====================================================
     GRÁFICA CANDIDATOS
========================================================= -->

<div class="grafica-card">


<h3>

<i class="bi bi-bar-chart-fill"></i>

Votos por candidato

</h3>


<?php if (
    count($datosCandidatos) > 0
) { ?>


<div class="grafica-container">

<canvas id="graficaCandidatos"></canvas>

</div>


<?php } else { ?>


<div class="alert alert-warning">

<i class="bi bi-info-circle-fill"></i>

No hay candidatos o votos para mostrar.

</div>


<?php } ?>


</div>


<!-- =====================================================
     GRÁFICA CARGOS
========================================================= -->

<div class="grafica-card">


<h3>

<i class="bi bi-pie-chart-fill"></i>

Votos por cargo

</h3>


<?php if (
    count($labelsCargos) > 0
) { ?>


<div class="grafica-container">

<canvas id="graficaCargos"></canvas>

</div>


<?php } else { ?>


<div class="alert alert-warning">

No hay cargos configurados para esta elección.

</div>


<?php } ?>


</div>


<!-- =====================================================
     TABLA RESUMEN
========================================================= -->

<div class="grafica-card">


<h3>

<i class="bi bi-table"></i>

Resumen de votación

</h3>


<div class="table-responsive">


<table class="table table-bordered table-hover tabla">


<thead>

<tr>

<th>

Candidato

</th>

<th>

Cargo

</th>

<th>

Votos

</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $datosCandidatos as $fila
) { ?>


<tr>


<td>

<?php echo htmlspecialchars(
    $fila['nombre'] .
    " " .
    $fila['apellido']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
    $fila['nombre_cargo']
); ?>

</td>


<td>

<strong>

<?php echo (int)$fila['votos']; ?>

</strong>

</td>


</tr>


<?php } ?>


</tbody>

</table>


</div>


</div>


<?php } else { ?>


<div class="alert alert-warning mt-4">

<i class="bi bi-calendar-x-fill"></i>

No hay elecciones registradas.

</div>


<?php } ?>


</div>

</div>


<!-- =====================================================
     JAVASCRIPT
========================================================= -->

<script>

/* =========================================================
   DATOS CANDIDATOS
========================================================= */

const labelsCandidatos =

<?php echo json_encode(
    $labelsCandidatos,
    JSON_UNESCAPED_UNICODE
); ?>;


const valoresCandidatos =

<?php echo json_encode(
    $valoresCandidatos
); ?>;


const coloresCandidatos =

<?php echo json_encode(
    $coloresCandidatos
); ?>;


/* =========================================================
   GRÁFICA CANDIDATOS
========================================================= */

const canvasCandidatos =
document.getElementById(
    "graficaCandidatos"
);


if (
    canvasCandidatos &&
    labelsCandidatos.length > 0
) {

    new Chart(
        canvasCandidatos,
        {

            type: "bar",

            data: {

                labels:
                    labelsCandidatos,

                datasets: [

                    {

                        label:
                            "Votos",

                        data:
                            valoresCandidatos,

                        backgroundColor:
                            coloresCandidatos,

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


/* =========================================================
   DATOS CARGOS
========================================================= */

const labelsCargos =

<?php echo json_encode(
    $labelsCargos,
    JSON_UNESCAPED_UNICODE
); ?>;


const valoresCargos =

<?php echo json_encode(
    $datosCargos
); ?>;


/* =========================================================
   GRÁFICA CARGOS
========================================================= */

const canvasCargos =
document.getElementById(
    "graficaCargos"
);


if (
    canvasCargos &&
    labelsCargos.length > 0
) {

    new Chart(
        canvasCargos,
        {

            type: "doughnut",

            data: {

                labels:
                    labelsCargos,

                datasets: [

                    {

                        label:
                            "Votos",

                        data:
                            valoresCargos,

                        backgroundColor: [

                            "#0d6efd",

                            "#198754",

                            "#ffc107",

                            "#dc3545",

                            "#6f42c1",

                            "#fd7e14",

                            "#20c997",

                            "#0dcaf0",

                            "#6610f2",

                            "#d63384"

                        ],

                        borderWidth: 2

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        position:
                            "bottom"

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>
<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   DATOS DEL ADMINISTRADOR
========================================================= */

$nombreAdmin = $_SESSION['nombre'] ?? 'Administrador';


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;
$totalCandidatos = 0;
$totalJurados = 0;
$totalVotos = 0;
$totalElecciones = 0;


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'estudiante'
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];

}


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalCandidatos =
        (int)$fila['total'];

}


/* =========================================================
   TOTAL JURADOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'jurado'
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalJurados =
        (int)$fila['total'];

}


/* =========================================================
   TOTAL VOTOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalVotos =
        (int)$fila['total'];

}


/* =========================================================
   TOTAL ELECCIONES
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM elecciones
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalElecciones =
        (int)$fila['total'];

}


/* =========================================================
   ELECCIÓN ABIERTA
========================================================= */

$eleccionActiva = null;


$resultado = $conn->query("
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


if ($resultado && $resultado->num_rows > 0) {

    $eleccionActiva =
        $resultado->fetch_assoc();

}


/* =========================================================
   ÚLTIMAS ELECCIONES
========================================================= */

$elecciones = [];


$resultado = $conn->query("
    SELECT
        id,
        nombre,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 5
");


if ($resultado) {

    while (
        $fila =
        $resultado->fetch_assoc()
    ) {

        $elecciones[] =
            $fila;

    }

}


/* =========================================================
   VOTOS POR ELECCIÓN
========================================================= */

$votosElecciones = [];


$resultado = $conn->query("
    SELECT
        e.id,
        e.nombre,
        e.estado,
        COUNT(v.id) AS votos

    FROM elecciones e

    LEFT JOIN votos v
        ON v.id_eleccion = e.id

    GROUP BY
        e.id,
        e.nombre,
        e.estado

    ORDER BY e.id DESC

    LIMIT 5
");


if ($resultado) {

    while (
        $fila =
        $resultado->fetch_assoc()
    ) {

        $votosElecciones[] =
            $fila;

    }

}


/* =========================================================
   CANDIDATOS POR ELECCIÓN
========================================================= */

$candidatosElecciones = [];


$resultado = $conn->query("
    SELECT
        e.id,
        e.nombre,
        COUNT(c.id) AS candidatos

    FROM elecciones e

    LEFT JOIN candidatos c
        ON c.id_eleccion = e.id

    GROUP BY
        e.id,
        e.nombre

    ORDER BY e.id DESC

    LIMIT 5
");


if ($resultado) {

    while (
        $fila =
        $resultado->fetch_assoc()
    ) {

        $candidatosElecciones[] =
            $fila;

    }

}


/* =========================================================
   PORCENTAJE DE PARTICIPACIÓN
========================================================= */

$porcentajeParticipacion = 0;


/*
| En el sistema un estudiante puede registrar
| más de un voto si la elección tiene varios cargos.
|
| Por eso calculamos estudiantes que han votado,
| no simplemente COUNT(votos).
*/

if ($eleccionActiva) {

    $idEleccionActiva =
        (int)$eleccionActiva['id'];


    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT id_usuario) AS total
        FROM votos
        WHERE id_eleccion = ?
    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccionActiva
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $fila =
            $resultado->fetch_assoc();

        $estudiantesQueVotaron =
            (int)$fila['total'];

        $stmt->close();


        if ($totalEstudiantes > 0) {

            $porcentajeParticipacion =
                round(
                    (
                        $estudiantesQueVotaron /
                        $totalEstudiantes
                    ) * 100,
                    1
                );

        }

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
Panel Administrativo
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<script
src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


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
   MENU
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

    padding: 14px 22px;

    font-size: 15px;

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

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 18px;

    margin-top: 25px;

}


.stat {

    background: white;

    border-radius: 18px;

    padding: 22px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    text-align: center;

}


.stat i {

    font-size: 36px;

    color: #1473ed;

}


.stat h2 {

    margin: 8px 0;

    color: #1453a3;

    font-size: 30px;

    font-weight: bold;

}


.stat p {

    margin: 0;

    color: #6c757d;

    font-weight: bold;

}


/* =========================================================
   ELECCIÓN ACTIVA
========================================================= */

.eleccion-activa {

    margin-top: 25px;

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.eleccion-activa h2 {

    color: #1453a3;

    font-weight: bold;

}


.estado-abierta {

    display: inline-block;

    background: #198754;

    color: white;

    padding: 7px 12px;

    border-radius: 8px;

    font-size: 13px;

    font-weight: bold;

}


.sin-eleccion {

    background: white;

    border-radius: 18px;

    padding: 30px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


/* =========================================================
   ACCIONES
========================================================= */

.acciones {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-top: 25px;

}


.accion {

    background: white;

    border-radius: 18px;

    padding: 25px;

    text-decoration: none;

    color: #212529;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    transition: .2s;

}


.accion:hover {

    transform: translateY(-4px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,.15);

}


.accion i {

    font-size: 36px;

    color: #1473ed;

}


.accion h4 {

    margin-top: 12px;

    color: #1453a3;

    font-weight: bold;

}


.accion p {

    margin: 0;

    color: #6c757d;

}


/* =========================================================
   TARJETAS
========================================================= */

.panel {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.panel h3 {

    color: #1453a3;

    font-weight: bold;

    margin-bottom: 20px;

}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participacion {

    display: flex;

    align-items: center;

    gap: 25px;

}


.circulo {

    width: 130px;

    height: 130px;

    border-radius: 50%;

    background:
        conic-gradient(
            #1473ed
            <?php echo $porcentajeParticipacion; ?>%,
            #e9ecef
            0
        );

    display: flex;

    align-items: center;

    justify-content: center;

}


.circulo-interno {

    width: 95px;

    height: 95px;

    background: white;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

    font-weight: bold;

    color: #1453a3;

}


/* =========================================================
   TABLA
========================================================= */

.tabla {

    margin: 0;

}


.tabla th {

    background: #cfe2ff;

    color: #084298;

}


.tabla td,
.tabla th {

    vertical-align: middle;

    padding: 13px;

}


/* =========================================================
   GRÁFICA
========================================================= */

.grafica {

    position: relative;

    height: 350px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px) {

    .estadisticas {

        grid-template-columns:
            repeat(3, 1fr);

    }


    .acciones {

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

        grid-template-columns: 1fr;

    }


    .acciones {

        grid-template-columns: 1fr;

    }


    .topbar {

        padding: 0 15px;

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
Panel Administrativo
</p>

</div>


<div class="menu">


<a
href="admin.php"
class="activo">

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


<div class="separador"></div>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<div class="separador"></div>


<a href="importar_excel.php">

<i class="bi bi-file-earmark-excel-fill"></i>

Importar Excel

</a>


<a href="pdf_resultados.php">

<i class="bi bi-file-earmark-pdf-fill"></i>

PDF de resultados

</a>


<div class="separador"></div>


<a href="cerrar_sesion.php">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>

</div>


<!-- =====================================================
     MAIN
========================================================= -->

<div class="main">


<div class="topbar">

<h4>
🗳️ Sistema de Votaciones Escolares
</h4>


<div class="usuario">

<i class="bi bi-person-circle"></i>

<?php

echo htmlspecialchars(
    $nombreAdmin
);

?>

</div>

</div>


<div class="contenido">


<h1 class="titulo">

<i class="bi bi-speedometer2"></i>

Panel de Administración

</h1>


<p class="subtitulo">

Bienvenido al centro de control del sistema electoral escolar.

</p>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

<div class="estadisticas">


<div class="stat">

<i class="bi bi-people-fill"></i>

<h2>

<?php

echo $totalEstudiantes;

?>

</h2>

<p>
Estudiantes
</p>

</div>


<div class="stat">

<i class="bi bi-person-vcard-fill"></i>

<h2>

<?php

echo $totalCandidatos;

?>

</h2>

<p>
Candidatos
</p>

</div>


<div class="stat">

<i class="bi bi-person-badge-fill"></i>

<h2>

<?php

echo $totalJurados;

?>

</h2>

<p>
Jurados
</p>

</div>


<div class="stat">

<i class="bi bi-check2-square"></i>

<h2>

<?php

echo $totalVotos;

?>

</h2>

<p>
Votos registrados
</p>

</div>


<div class="stat">

<i class="bi bi-calendar-event-fill"></i>

<h2>

<?php

echo $totalElecciones;

?>

</h2>

<p>
Elecciones
</p>

</div>


</div>


<!-- =====================================================
     ELECCIÓN ACTIVA
========================================================= -->

<?php if (
    $eleccionActiva
) { ?>


<div class="eleccion-activa">


<div class="row align-items-center">


<div class="col-md-8">

<span class="estado-abierta">

● ELECCIÓN ABIERTA

</span>


<h2 class="mt-3">

<?php

echo htmlspecialchars(
    $eleccionActiva['nombre']
);

?>

</h2>


<?php if (
    !empty(
        $eleccionActiva['descripcion']
    )
) { ?>

<p class="text-muted">

<?php

echo htmlspecialchars(
    $eleccionActiva['descripcion']
);

?>

</p>

<?php } ?>


<div class="row mt-3">


<div class="col-md-6">

<strong>
Inicio:
</strong>

<br>

<?php

echo htmlspecialchars(
    $eleccionActiva['fecha_inicio']
);

?>

</div>


<div class="col-md-6">

<strong>
Finalización:
</strong>

<br>

<?php

echo htmlspecialchars(
    $eleccionActiva['fecha_fin']
);

?>

</div>

</div>

</div>


<div class="col-md-4 text-md-end mt-4 mt-md-0">


<a
href="elecciones.php"
class="btn btn-primary">

<i class="bi bi-gear-fill"></i>

Administrar elección

</a>


</div>

</div>

</div>


<?php } else { ?>


<div class="sin-eleccion">


<h4>

<i class="bi bi-pause-circle-fill text-warning"></i>

No hay una elección abierta actualmente.

</h4>


<p class="text-muted">

Puedes crear una nueva elección o abrir una elección existente desde el módulo de elecciones.

</p>


<a
href="elecciones.php"
class="btn btn-primary">

<i class="bi bi-calendar-plus"></i>

Administrar elecciones

</a>


</div>


<?php } ?>


<!-- =====================================================
     ACCIONES RÁPIDAS
========================================================= -->

<div class="acciones">


<a
href="elecciones.php"
class="accion">

<i class="bi bi-calendar-plus"></i>

<h4>
Gestionar elecciones
</h4>

<p>
Crear, editar, abrir o cerrar elecciones.
</p>

</a>


<a
href="candidatos.php"
class="accion">

<i class="bi bi-person-vcard-fill"></i>

<h4>
Gestionar candidatos
</h4>

<p>
Administrar candidatos y sus propuestas.
</p>

</a>


<a
href="estudiantes.php"
class="accion">

<i class="bi bi-people-fill"></i>

<h4>
Gestionar estudiantes
</h4>

<p>
Consultar y administrar estudiantes.
</p>

</a>


<a
href="jurados.php"
class="accion">

<i class="bi bi-person-badge-fill"></i>

<h4>
Gestionar jurados
</h4>

<p>
Administrar usuarios con rol de jurado.
</p>

</a>


<a
href="resultados.php"
class="accion">

<i class="bi bi-trophy-fill"></i>

<h4>
Ver resultados
</h4>

<p>
Consultar los resultados oficiales.
</p>

</a>


<a
href="graficas.php"
class="accion">

<i class="bi bi-bar-chart-fill"></i>

<h4>
Ver gráficas
</h4>

<p>
Analizar visualmente la votación.
</p>

</a>


<a
href="importar_excel.php"
class="accion">

<i class="bi bi-file-earmark-excel-fill"></i>

<h4>
Importar estudiantes
</h4>

<p>
Cargar información mediante Excel.
</p>

</a>


<a
href="pdf_resultados.php"
class="accion">

<i class="bi bi-file-earmark-pdf-fill"></i>

<h4>
Generar PDF
</h4>

<p>
Obtener un reporte de resultados.
</p>

</a>


</div>


<!-- =====================================================
     PARTICIPACIÓN
========================================================= -->

<div class="panel">


<h3>

<i class="bi bi-pie-chart-fill"></i>

Participación electoral

</h3>


<div class="participacion">


<div class="circulo">

<div class="circulo-interno">

<?php

echo $porcentajeParticipacion;

?>%

</div>

</div>


<div>

<h4>

Participación de estudiantes

</h4>


<p class="text-muted">

Porcentaje de estudiantes que ya realizaron
su votación en la elección actualmente abierta.

</p>

<?php if (
    $eleccionActiva
) { ?>

<p>

<strong>
Elección:
</strong>

<?php

echo htmlspecialchars(
    $eleccionActiva['nombre']
);

?>

</p>

<?php } ?>

</div>

</div>

</div>


<!-- =====================================================
     ÚLTIMAS ELECCIONES
========================================================= -->

<div class="panel">


<h3>

<i class="bi bi-clock-history"></i>

Últimas elecciones

</h3>


<div class="table-responsive">


<table
class="table table-hover tabla">


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
Acción
</th>

</tr>

</thead>


<tbody>


<?php if (
    count($elecciones) === 0
) { ?>

<tr>

<td
colspan="5"
class="text-center">

No hay elecciones registradas.

</td>

</tr>

<?php } ?>


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

$estado =
    strtolower(
        trim(
            (string)$e['estado']
        )
    );


if (
    $estado === 'abierta'
) {

?>

<span class="badge bg-success">

Abierta

</span>

<?php

} else {

?>

<span class="badge bg-secondary">

Cerrada

</span>

<?php

}

?>

</td>


<td>

<a
href="resultados.php?id_eleccion=<?php echo (int)$e['id']; ?>"
class="btn btn-sm btn-outline-primary">

<i class="bi bi-eye"></i>

Ver

</a>

</td>


</tr>


<?php } ?>


</tbody>

</table>

</div>

</div>


<!-- =====================================================
     GRÁFICA
========================================================= -->

<div class="panel">


<h3>

<i class="bi bi-bar-chart-fill"></i>

Votos por elección

</h3>


<?php if (
    count($votosElecciones) > 0
) { ?>

<div class="grafica">

<canvas id="graficaElecciones"></canvas>

</div>

<?php } else { ?>

<div class="alert alert-info">

Todavía no existen votos registrados.

</div>

<?php } ?>


</div>


</div>

</div>


<script>

/* =========================================================
   DATOS
========================================================= */

const nombresElecciones =

<?php

echo json_encode(
    array_column(
        $votosElecciones,
        'nombre'
    ),
    JSON_UNESCAPED_UNICODE
);

?>;


const votosElecciones =

<?php

echo json_encode(
    array_map(
        'intval',
        array_column(
            $votosElecciones,
            'votos'
        )
    )
);

?>;


/* =========================================================
   GRÁFICA
========================================================= */

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

                        backgroundColor:
                            "#1473ed",

                        borderColor:
                            "#1453a3",

                        borderWidth: 1

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            precision: 0

                        }

                    }

                },

                plugins: {

                    legend: {

                        display: true

                    }

                }

            }

        }

    );

}

</script>


</body>

</html>
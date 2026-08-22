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
   PERMITIR ADMINISTRADOR Y JURADO
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
   BUSCAR ELECCIÓN
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
    ORDER BY id DESC
    LIMIT 1
");


if (
    $resultadoEleccion &&
    $resultadoEleccion->num_rows > 0
) {

    $eleccion =
        $resultadoEleccion->fetch_assoc();

}


/* =========================================================
   VARIABLES
========================================================= */

$resultados = [];

$totalVotos = 0;

$totalCandidatos = 0;

$totalCargos = 0;


/* =========================================================
   SI EXISTE ELECCIÓN
========================================================= */

if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];


    /* =====================================================
       TOTAL CARGOS
    ===================================================== */

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

        $datos =
            $resultado->fetch_assoc();

        $totalCargos =
            (int)$datos['total'];

        $stmt->close();
    }


    /* =====================================================
       TOTAL CANDIDATOS
    ===================================================== */

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

        $datos =
            $resultado->fetch_assoc();

        $totalCandidatos =
            (int)$datos['total'];

        $stmt->close();
    }


    /* =====================================================
       TOTAL VOTOS
    ===================================================== */

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

        $datos =
            $resultado->fetch_assoc();

        $totalVotos =
            (int)$datos['total'];

        $stmt->close();
    }


    /* =====================================================
       RESULTADOS POR CARGO
    ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            ca.id AS id_candidato,
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

            $resultados[] =
                $fila;
        }


        $stmt->close();
    }
}


/* =========================================================
   AGRUPAR POR CARGO
========================================================= */

$resultadosPorCargo = [];

foreach ($resultados as $fila) {

    $nombreCargo =
        $fila['nombre_cargo'];

    if (
        !isset(
            $resultadosPorCargo[$nombreCargo]
        )
    ) {

        $resultadosPorCargo[$nombreCargo] = [];
    }

    $resultadosPorCargo[$nombreCargo][] =
        $fila;
}


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombreUsuario =
    $_SESSION['nombre'] ?? 'Usuario';

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Resultados electorales
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
   PRINCIPAL
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


/* =========================================================
   TÍTULO
========================================================= */

.titulo {

    color: #1453a3;

    font-size: 32px;

    font-weight: bold;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    padding: 25px;

    border-radius: 18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

    margin-bottom: 25px;
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

    padding: 25px;

    text-align: center;

    border-radius: 18px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.estadistica i {

    font-size: 40px;

    color: #1473ed;
}


.estadistica h2 {

    color: #1453a3;

    font-weight: bold;

    font-size: 35px;

    margin: 8px 0;
}


.estadistica p {

    margin: 0;

    font-weight: bold;
}


/* =========================================================
   CARGO
========================================================= */

.cargo-card {

    background: white;

    border-radius: 18px;

    margin-bottom: 25px;

    overflow: hidden;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);
}


.cargo-header {

    background: #1453a3;

    color: white;

    padding: 20px 25px;
}


.cargo-header h3 {

    margin: 0;

    font-weight: bold;
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

    padding: 15px;
}


.foto {

    width: 55px;

    height: 55px;

    object-fit: cover;

    border-radius: 50%;

    border: 2px solid #dce7f5;
}


.sin-foto {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #1453a3;

    font-size: 25px;
}


.votos {

    font-size: 20px;

    font-weight: bold;

    color: #1453a3;
}


.ganador {

    background: #fff3cd !important;
}


.badge-ganador {

    background: #ffc107;

    color: #000;

    padding: 6px 10px;

    border-radius: 6px;

    font-size: 12px;

    font-weight: bold;
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

</div>


<div class="menu">


<?php if ($rol === "administrador") { ?>

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


<a
href="resultados.php"
class="activo">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<?php if ($rol === "administrador") { ?>

<div class="separador"></div>

<a href="jurados.php">

<i class="bi bi-person-badge-fill"></i>

Jurados

</a>

<?php } ?>


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


<h1 class="titulo">

<i class="bi bi-trophy-fill"></i>

Resultados electorales

</h1>


<p class="text-muted">

Resultados de la elección registrada en el sistema.

</p>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<?php if ($eleccion) { ?>

<div class="eleccion">

<h3 class="text-primary">

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h3>


<?php if (
    !empty($eleccion['descripcion'])
) { ?>

<p>

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


<span class="badge
<?php
echo strtolower(
    trim($eleccion['estado']
)) === 'abierta'
    ? 'bg-success'
    : 'bg-secondary';
?>">

<?php echo htmlspecialchars(
    ucfirst($eleccion['estado'])
); ?>

</span>

</div>


<!-- =====================================================
     ESTADÍSTICAS
===================================================== -->

<div class="estadisticas">


<div class="estadistica">

<i class="bi bi-hand-index-thumb-fill"></i>

<h2>

<?php echo $totalVotos; ?>

</h2>

<p>
Votos registrados
</p>

</div>


<div class="estadistica">

<i class="bi bi-person-badge-fill"></i>

<h2>

<?php echo $totalCandidatos; ?>

</h2>

<p>
Candidatos
</p>

</div>


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


<!-- =====================================================
     RESULTADOS
===================================================== -->

<?php if (
    count($resultadosPorCargo) > 0
) { ?>


<?php foreach (
    $resultadosPorCargo
    as $nombreCargo => $candidatos
) { ?>


<?php

$mayorCantidad = 0;

foreach (
    $candidatos
    as $candidato
) {

    if (
        (int)$candidato['votos']
        >
        $mayorCantidad
    ) {

        $mayorCantidad =
            (int)$candidato['votos'];
    }
}

?>


<div class="cargo-card">


<div class="cargo-header">

<h3>

<i class="bi bi-award-fill"></i>

<?php echo htmlspecialchars(
    $nombreCargo
); ?>

</h3>

</div>


<div class="table-responsive">


<table class="table table-hover tabla">


<thead>

<tr>

<th>
Candidato
</th>

<th>
Curso
</th>

<th>
Votos
</th>

<th>
Estado
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $candidatos
    as $candidato
) { ?>


<?php

$votosCandidato =
    (int)$candidato['votos'];

$esGanador =
    (
        $votosCandidato ===
        $mayorCantidad
        &&
        $mayorCantidad > 0
    );

?>


<tr
class="<?php echo $esGanador
    ? 'ganador'
    : '';
?>">


<td>


<div class="d-flex
            align-items-center
            gap-3">


<?php if (
    !empty($candidato['foto'])
) { ?>


<img
src="<?php echo htmlspecialchars(
    $candidato['foto']
); ?>"
class="foto"
alt="Candidato">


<?php } else { ?>


<div class="sin-foto">

<i class="bi bi-person-fill"></i>

</div>


<?php } ?>


<strong>

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    ' ' .
    $candidato['apellido']
); ?>

</strong>


</div>


</td>


<td>

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</td>


<td>

<span class="votos">

<?php echo $votosCandidato; ?>

</span>

</td>


<td>


<?php if ($esGanador) { ?>

<span class="badge-ganador">

🏆 MAYOR VOTACIÓN

</span>

<?php } else { ?>

<span class="text-muted">

—

</span>

<?php } ?>


</td>


</tr>


<?php } ?>


</tbody>

</table>


</div>

</div>


<?php } ?>


<?php } else { ?>


<div class="alert alert-warning">

<i class="bi bi-info-circle-fill"></i>

Todavía no hay candidatos o votos registrados
para esta elección.

</div>


<?php } ?>


<?php } else { ?>


<div class="alert alert-danger">

<i class="bi bi-calendar-x-fill"></i>

No existe ninguna elección registrada.

</div>


<?php } ?>


</div>

</div>


</body>

</html>
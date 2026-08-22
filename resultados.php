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
   ADMINISTRADOR Y JURADO PUEDEN VER RESULTADOS
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
   NOMBRE DEL USUARIO
========================================================= */

$nombreUsuario =
    $_SESSION['nombre'] ?? 'Usuario';


/* =========================================================
   LISTAR ELECCIONES
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

        $elecciones[] =
            $fila;

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
   SI NO SE SELECCIONA ELECCIÓN,
   TOMAR LA ÚLTIMA
========================================================= */

if (
    $idEleccion <= 0 &&
    count($elecciones) > 0
) {

    $idEleccion =
        (int)$elecciones[0]['id'];

}


/* =========================================================
   DATOS DE LA ELECCIÓN
========================================================= */

$datosEleccion = null;


if ($idEleccion > 0) {

    $stmt =
        $conn->prepare("

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


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        if (
            $resultado->num_rows > 0
        ) {

            $datosEleccion =
                $resultado->fetch_assoc();

        }


        $stmt->close();

    }

}


/* =========================================================
   CARGOS DE LA ELECCIÓN
========================================================= */

$cargos = [];


if (
    $datosEleccion
) {

    $stmt =
        $conn->prepare("

            SELECT
                c.id,
                c.nombre_cargo

            FROM cargos c

            INNER JOIN eleccion_cargos ec

                ON ec.id_cargo = c.id

            WHERE ec.id_eleccion = ?

            ORDER BY c.id ASC

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
            $cargo =
            $resultado->fetch_assoc()
        ) {

            $cargos[] =
                $cargo;

        }


        $stmt->close();

    }

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalVotos = 0;

$totalCandidatos = 0;

$totalCargos =
    count($cargos);


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

if (
    $datosEleccion
) {

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

}


/* =========================================================
   TOTAL VOTOS
   IMPORTANTE:
   votos NO TIENE id_eleccion.

   Se relaciona así:

   votos.id_candidato
        ↓
   candidatos.id
        ↓
   candidatos.id_eleccion
========================================================= */

if (
    $datosEleccion
) {

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

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Resultados Oficiales
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
   CONTENIDO
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


/* =========================================================
   SELECTOR
========================================================= */

.selector-card {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 20px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.btn-resultados {

    background: #1473ed;

    border: none;

    color: white;

    font-weight: bold;

}


.btn-resultados:hover {

    background: #0d5dcc;

    color: white;

}


/* =========================================================
   INFORMACIÓN ELECCIÓN
========================================================= */

.info-eleccion {

    background: white;

    padding: 25px;

    border-radius: 18px;

    margin-top: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.info-eleccion h2 {

    color: #1453a3;

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

    font-size: 35px;

    font-weight: bold;

    margin: 8px 0;

}


.estadistica p {

    margin: 0;

    font-weight: bold;

    color: #6c757d;

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


/* =========================================================
   FOTO
========================================================= */

.foto {

    width: 60px;

    height: 60px;

    object-fit: cover;

    border-radius: 50%;

    border: 2px solid #dce7f5;

}


.sin-foto {

    width: 60px;

    height: 60px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #1453a3;

    font-size: 25px;

}


/* =========================================================
   VOTOS
========================================================= */

.votos {

    font-size: 20px;

    font-weight: bold;

    color: #1453a3;

}


/* =========================================================
   GANADOR
========================================================= */

.ganador {

    background: #fff3cd !important;

}


.badge-ganador {

    background: #ffc107;

    color: #000;

    padding: 7px 10px;

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

if (
    $rol === "jurado"
) {

    echo "Panel del Jurado";

} else {

    echo "Panel del Administrador";

}

?>

</p>

</div>


<div class="menu">


<!-- =====================================================
     INICIO
========================================================= -->

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


<!-- =====================================================
     INGRESAR ESTUDIANTE
========================================================= -->

<?php if (
    $rol === "jurado"
) { ?>

<a href="ingresar_estudiante.php">

<i class="bi bi-person-vcard-fill"></i>

Ingresar estudiante

</a>

<?php } ?>


<!-- =====================================================
     RESULTADOS
========================================================= -->

<a
href="resultados.php"
class="activo">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<!-- =====================================================
     GRÁFICAS
========================================================= -->

<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<!-- =====================================================
     JURADOS SOLO ADMINISTRADOR
========================================================= -->

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


<!-- =====================================================
     CERRAR SESIÓN
========================================================= -->

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


<!-- =====================================================
     TOPBAR
========================================================= -->

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


<!-- =====================================================
     CONTENIDO
========================================================= -->

<div class="contenido">


<h1 class="titulo">

<i class="bi bi-trophy-fill"></i>

Resultados Oficiales

</h1>


<p class="text-muted">

Consulta los resultados de las elecciones escolares.

</p>


<!-- =====================================================
     SELECTOR
========================================================= -->

<div class="selector-card">


<form method="GET">


<div class="row align-items-end">


<div class="col-md-9">


<label class="form-label fw-bold">

<i class="bi bi-calendar-event"></i>

Seleccione una elección

</label>


<select
name="id_eleccion"
class="form-select"
required>


<option value="">

Seleccione...

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
class="btn btn-resultados w-100">

<i class="bi bi-search"></i>

Ver resultados

</button>


</div>


</div>


</form>


</div>


<?php if (
    $datosEleccion
) { ?>


<!-- =====================================================
     INFORMACIÓN
========================================================= -->

<div class="info-eleccion">


<h2>

<?php echo htmlspecialchars(
    $datosEleccion['nombre']
); ?>

</h2>


<?php if (
    !empty(
        $datosEleccion['descripcion']
    )
) { ?>

<p>

<?php echo htmlspecialchars(
    $datosEleccion['descripcion']
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
    $datosEleccion['fecha_inicio']
); ?>

</div>


<div class="col-md-4">

<strong>

Fecha de finalización

</strong>

<br>

<?php echo htmlspecialchars(
    $datosEleccion['fecha_fin']
); ?>

</div>


<div class="col-md-4">

<strong>

Estado

</strong>

<br>


<?php

$estado =
    strtolower(
        trim(
            (string)
            $datosEleccion['estado']
        )
    );


if (
    $estado === "abierta"
) {

?>


<span class="badge bg-success">

🟢 Abierta

</span>


<?php

} else {

?>


<span class="badge bg-secondary">

🔴 Cerrada

</span>


<?php

}

?>


</div>

</div>


</div>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

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

<i class="bi bi-people-fill"></i>

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
     CARGOS
========================================================= -->

<?php

if (
    count($cargos) > 0
) {


    foreach (
        $cargos as $cargo
    ) {


        $idCargo =
            (int)$cargo['id'];


        /* =============================================
           TOTAL VOTOS DEL CARGO
        ============================================= */

        $totalVotosCargo = 0;


        $stmtTotal =
            $conn->prepare("

                SELECT
                    COUNT(*) AS total

                FROM votos v

                INNER JOIN candidatos c
                    ON c.id = v.id_candidato

                WHERE c.id_eleccion = ?

                AND c.id_cargo = ?

            ");


        if ($stmtTotal) {

            $stmtTotal->bind_param(
                "ii",
                $idEleccion,
                $idCargo
            );

            $stmtTotal->execute();

            $resultadoTotal =
                $stmtTotal->get_result();

            $filaTotal =
                $resultadoTotal->fetch_assoc();

            $totalVotosCargo =
                (int)$filaTotal['total'];

            $stmtTotal->close();

        }


        /* =============================================
           CANDIDATOS DEL CARGO
        ============================================= */

        $resultadosCargo = [];


        $stmt =
            $conn->prepare("

                SELECT

                    c.id,

                    c.nombre,

                    c.apellido,

                    c.curso,

                    c.foto,

                    COUNT(v.id) AS total

                FROM candidatos c

                LEFT JOIN votos v

                    ON v.id_candidato = c.id

                WHERE c.id_eleccion = ?

                AND c.id_cargo = ?

                GROUP BY

                    c.id,

                    c.nombre,

                    c.apellido,

                    c.curso,

                    c.foto

                ORDER BY

                    total DESC,

                    c.id ASC

            ");


        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $idEleccion,
                $idCargo
            );

            $stmt->execute();

            $resultado =
                $stmt->get_result();


            while (
                $fila =
                $resultado->fetch_assoc()
            ) {

                $resultadosCargo[] =
                    $fila;

            }


            $stmt->close();

        }


        /* =============================================
           MAYOR CANTIDAD DE VOTOS
        ============================================= */

        $mayorCantidad = 0;


        foreach (
            $resultadosCargo
            as $fila
        ) {

            $cantidad =
                (int)$fila['total'];


            if (
                $cantidad >
                $mayorCantidad
            ) {

                $mayorCantidad =
                    $cantidad;

            }

        }

?>


<!-- =====================================================
     TARJETA CARGO
========================================================= -->

<div class="cargo-card">


<div class="cargo-header">


<h3>

<i class="bi bi-award-fill"></i>

<?php echo htmlspecialchars(
    $cargo['nombre_cargo']
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

Porcentaje

</th>

<th>

Estado

</th>

</tr>

</thead>


<tbody>


<?php if (
    count($resultadosCargo) > 0
) { ?>


<?php foreach (
    $resultadosCargo
    as $fila
) {


    $votosCandidato =
        (int)$fila['total'];


    $porcentaje = 0;


    if (
        $totalVotosCargo > 0
    ) {

        $porcentaje =
            round(
                (
                    $votosCandidato /
                    $totalVotosCargo
                ) * 100,
                2
            );

    }


    $esGanador =
        (
            $mayorCantidad > 0 &&
            $votosCandidato ===
            $mayorCantidad
        );

?>


<tr
class="<?php echo $esGanador
    ? 'ganador'
    : '';
?>">


<!-- FOTO + NOMBRE -->

<td>


<div class="d-flex
            align-items-center
            gap-3">


<?php

$foto =
    trim(
        (string)$fila['foto']
    );


$rutaFoto =
    "uploads/candidatos/" .
    $foto;


if (
    $foto !== "" &&
    file_exists($rutaFoto)
) {

?>


<img

src="<?php echo htmlspecialchars(
    $rutaFoto
); ?>"

class="foto"

alt="Foto del candidato">


<?php

} else {

?>


<div class="sin-foto">

<i class="bi bi-person-fill"></i>

</div>


<?php

}

?>


<strong>

<?php echo htmlspecialchars(
    $fila['nombre'] .
    " " .
    $fila['apellido']
); ?>

</strong>


</div>


</td>


<!-- CURSO -->

<td>

<?php echo htmlspecialchars(
    $fila['curso']
); ?>

</td>


<!-- VOTOS -->

<td>

<span class="votos">

<?php echo $votosCandidato; ?>

</span>

</td>


<!-- PORCENTAJE -->

<td>

<?php echo $porcentaje; ?>%

</td>


<!-- ESTADO -->

<td>


<?php if (
    $esGanador
) { ?>


<span class="badge-ganador">

🏆 Mayor votación

</span>


<?php } else { ?>


<span class="text-muted">

Participante

</span>


<?php } ?>


</td>


</tr>


<?php } ?>


<?php } else { ?>


<tr>

<td
colspan="5"
class="text-center p-4">

<i class="bi bi-person-x fs-2 text-muted"></i>

<p class="mb-0 mt-2">

No hay candidatos registrados
para este cargo.

</p>

</td>

</tr>


<?php } ?>


</tbody>

</table>


</div>


</div>


<?php

    }

} else {

?>


<div class="alert alert-warning">

<i class="bi bi-info-circle-fill"></i>

Esta elección no tiene cargos configurados.

</div>


<?php

}


} else {

?>


<div class="alert alert-warning mt-4">

<i class="bi bi-info-circle-fill"></i>

No hay elecciones registradas
en el sistema.

</div>


<?php } ?>


</div>

</div>

</body>

</html>
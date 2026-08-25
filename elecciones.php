<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   MENSAJES
========================================================= */

$mensaje = "";
$tipoMensaje = "";


if (isset($_GET['abierta'])) {

    $mensaje = "La elección fue abierta correctamente.";
    $tipoMensaje = "success";

}


if (isset($_GET['cerrada'])) {

    $mensaje = "La elección fue cerrada correctamente.";
    $tipoMensaje = "success";

}


if (isset($_GET['eliminada'])) {

    $mensaje = "La elección fue eliminada correctamente.";
    $tipoMensaje = "success";

}


if (isset($_GET['error'])) {

    $mensaje = "No fue posible realizar la operación solicitada.";
    $tipoMensaje = "danger";

}


if (isset($_GET['creada'])) {

    $mensaje = "La elección fue creada correctamente.";
    $tipoMensaje = "success";

}


if (isset($_GET['actualizada'])) {

    $mensaje = "La elección fue actualizada correctamente.";
    $tipoMensaje = "success";

}


/* =========================================================
   LISTAR ELECCIONES
========================================================= */

$sql = "

    SELECT

        e.id,
        e.nombre,
        e.descripcion,
        e.fecha_inicio,
        e.fecha_fin,
        e.estado,

        (
            SELECT COUNT(*)
            FROM candidatos c
            WHERE c.id_eleccion = e.id
        ) AS total_candidatos,

        (
            SELECT COUNT(*)
            FROM votos v
            WHERE v.id_eleccion = e.id
        ) AS total_votos,

        (
            SELECT COUNT(*)
            FROM eleccion_cargos ec
            WHERE ec.id_eleccion = e.id
        ) AS total_cargos

    FROM elecciones e

    ORDER BY
        CASE
            WHEN e.estado = 'abierta' THEN 0
            ELSE 1
        END,

        e.fecha_inicio DESC,
        e.id DESC
";


$elecciones = $conn->query($sql);


if (!$elecciones) {

    die(
        "Error al consultar las elecciones: "
        . htmlspecialchars($conn->error)
    );

}


/* =========================================================
   CONTADORES
========================================================= */

$totalElecciones = 0;
$eleccionesAbiertas = 0;
$eleccionesCerradas = 0;


$totalElecciones =
    $elecciones->num_rows;


/*
|--------------------------------------------------------------------------
| Guardamos las elecciones para poder recorrerlas
| varias veces sin perder el resultado de MySQL.
|--------------------------------------------------------------------------
*/

$listaElecciones = [];


while (
    $fila =
    $elecciones->fetch_assoc()
) {

    $listaElecciones[] =
        $fila;


    if (
        strtolower(
            trim(
                (string)$fila['estado']
            )
        ) === 'abierta'
    ) {

        $eleccionesAbiertas++;

    } else {

        $eleccionesCerradas++;

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
Gestión de Elecciones
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

    font-size: 45px;

}


.logo h1 {

    margin: 8px 0 0;

    font-size: 26px;

    font-weight: bold;

}


.logo p {

    margin: 5px 0 0;

    font-size: 13px;

    opacity: .8;

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
   ENCABEZADO
========================================================= */

.encabezado {

    background: #1453a3;

    color: white;

    border-radius: 18px;

    padding: 25px;

    margin-top: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.12);

}


.encabezado h2 {

    margin: 0;

    font-weight: bold;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-top: 25px;

}


.stat {

    background: white;

    border-radius: 18px;

    padding: 22px;

    text-align: center;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.stat i {

    font-size: 35px;

    color: #1473ed;

}


.stat h2 {

    margin: 8px 0;

    font-size: 30px;

    color: #1453a3;

    font-weight: bold;

}


.stat p {

    margin: 0;

    color: #6c757d;

    font-weight: bold;

}


/* =========================================================
   TABLA
========================================================= */

.card-elecciones {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-top: 25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.table {

    margin-bottom: 0;

}


.table thead th {

    background: #0d47a1;

    color: white;

    white-space: nowrap;

}


.table td,
.table th {

    vertical-align: middle;

    padding: 14px;

}


.nombre-eleccion {

    font-weight: bold;

    color: #1453a3;

}


.descripcion {

    max-width: 280px;

    color: #6c757d;

}


/* =========================================================
   ESTADOS
========================================================= */

.estado-abierta {

    display: inline-block;

    background: #198754;

    color: white;

    padding: 7px 12px;

    border-radius: 7px;

    font-weight: bold;

    font-size: 13px;

}


.estado-cerrada {

    display: inline-block;

    background: #6c757d;

    color: white;

    padding: 7px 12px;

    border-radius: 7px;

    font-weight: bold;

    font-size: 13px;

}


/* =========================================================
   DATOS
========================================================= */

.mini-dato {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    margin-right: 8px;

    margin-bottom: 5px;

    padding: 5px 8px;

    border-radius: 6px;

    background: #eef3f9;

    color: #1453a3;

    font-size: 12px;

    font-weight: bold;

}


/* =========================================================
   ACCIONES
========================================================= */

.acciones {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;

}


.acciones .btn {

    white-space: nowrap;

}


/* =========================================================
   ELECCIÓN ABIERTA
========================================================= */

.fila-abierta {

    background:
        rgba(25,135,84,.06);

}


/* =========================================================
   VACÍA
========================================================= */

.vacia {

    padding: 60px !important;

    text-align: center;

    color: #6c757d;

}


.vacia i {

    font-size: 55px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .estadisticas {

        grid-template-columns:
            1fr;

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


<a href="admin.php">

<i class="bi bi-house-fill"></i>

Inicio

</a>


<a
href="elecciones.php"
class="activo">

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
    $_SESSION['nombre'] ?? 'Administrador'
);

?>

</div>

</div>


<div class="contenido">


<h1 class="titulo">

<i class="bi bi-calendar-event-fill"></i>

Gestión de Elecciones

</h1>


<p class="subtitulo">

Crea, configura, abre, cierra y administra
las elecciones escolares.

</p>


<!-- =====================================================
     ENCABEZADO
========================================================= -->

<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>

<h2>

<i class="bi bi-calendar-check-fill"></i>

Elecciones del sistema

</h2>


<p class="mb-0 mt-2">

Administra el proceso electoral desde un solo lugar.

</p>

</div>


<a
href="crear_eleccion.php"
class="btn btn-light btn-lg">

<i class="bi bi-plus-circle-fill"></i>

Nueva elección

</a>


</div>

</div>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

<div class="estadisticas">


<div class="stat">

<i class="bi bi-calendar-event-fill"></i>

<h2>

<?php

echo $totalElecciones;

?>

</h2>

<p>
Total elecciones
</p>

</div>


<div class="stat">

<i class="bi bi-unlock-fill text-success"></i>

<h2>

<?php

echo $eleccionesAbiertas;

?>

</h2>

<p>
Elecciones abiertas
</p>

</div>


<div class="stat">

<i class="bi bi-lock-fill text-secondary"></i>

<h2>

<?php

echo $eleccionesCerradas;

?>

</h2>

<p>
Elecciones cerradas
</p>

</div>


</div>


<!-- =====================================================
     MENSAJE
========================================================= -->

<?php if (
    $mensaje !== ""
) { ?>


<div
class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?> alert-dismissible fade show mt-4">


<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>


<button
type="button"
class="btn-close"
data-bs-dismiss="alert">

</button>


</div>


<?php } ?>


<!-- =====================================================
     ADVERTENCIA
========================================================= -->

<?php if (
    $eleccionesAbiertas > 1
) { ?>


<div class="alert alert-warning mt-4">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>Atención:</strong>

Hay más de una elección abierta actualmente.

Esto puede generar conflictos durante la votación.

Se recomienda mantener solamente una elección abierta.

</div>


<?php } ?>


<!-- =====================================================
     TABLA
========================================================= -->

<div class="card-elecciones">


<div class="table-responsive">


<table
class="table table-bordered table-hover align-middle">


<thead>

<tr>

<th>
ID
</th>

<th>
Elección
</th>

<th>
Descripción
</th>

<th>
Inicio
</th>

<th>
Fin
</th>

<th>
Datos
</th>

<th>
Estado
</th>

<th>
Acciones
</th>

</tr>

</thead>


<tbody>


<?php if (
    count($listaElecciones) === 0
) { ?>


<tr>

<td
colspan="8"
class="vacia">


<i class="bi bi-calendar-x"></i>


<h4 class="mt-3">

No hay elecciones registradas

</h4>


<p>

Crea una nueva elección para comenzar.

</p>


<a
href="crear_eleccion.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

Crear elección

</a>


</td>

</tr>


<?php } else { ?>


<?php foreach (
    $listaElecciones as $e
) { ?>


<?php

$estado =
    strtolower(
        trim(
            (string)$e['estado']
        )
    );


$esAbierta =
    $estado === 'abierta';


?>


<tr class="<?php

echo $esAbierta
    ? 'fila-abierta'
    : '';

?>">


<!-- =================================================
     ID
===================================================== -->

<td>

<strong>

<?php

echo (int)$e['id'];

?>

</strong>

</td>


<!-- =================================================
     NOMBRE
===================================================== -->

<td>

<div class="nombre-eleccion">

<?php

echo htmlspecialchars(
    $e['nombre']
);

?>

</div>

</td>


<!-- =================================================
     DESCRIPCIÓN
===================================================== -->

<td>

<div class="descripcion">

<?php

$descripcion =
    trim(
        (string)$e['descripcion']
    );


if (
    $descripcion === ""
) {

    echo "Sin descripción.";

} else {

    echo htmlspecialchars(
        $descripcion
    );

}

?>

</div>

</td>


<!-- =================================================
     INICIO
===================================================== -->

<td>

<?php

echo htmlspecialchars(
    $e['fecha_inicio']
);

?>

</td>


<!-- =================================================
     FIN
===================================================== -->

<td>

<?php

echo htmlspecialchars(
    $e['fecha_fin']
);

?>

</td>


<!-- =================================================
     DATOS
===================================================== -->

<td>


<span class="mini-dato">

<i class="bi bi-award-fill"></i>

<?php

echo (int)$e['total_cargos'];

?>

cargos

</span>


<span class="mini-dato">

<i class="bi bi-person-fill"></i>

<?php

echo (int)$e['total_candidatos'];

?>

candidatos

</span>


<span class="mini-dato">

<i class="bi bi-check2-square"></i>

<?php

echo (int)$e['total_votos'];

?>

votos

</span>


</td>


<!-- =================================================
     ESTADO
===================================================== -->

<td>


<?php if (
    $esAbierta
) { ?>


<span class="estado-abierta">

🟢 Abierta

</span>


<?php } else { ?>


<span class="estado-cerrada">

⚪ Cerrada

</span>


<?php } ?>


</td>


<!-- =================================================
     ACCIONES
===================================================== -->

<td>


<div class="acciones">


<!-- ===============================================
     EDITAR
================================================ -->

<a
href="editar_eleccion.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-primary btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<!-- ===============================================
     RESULTADOS
================================================ -->

<a
href="resultados.php?id_eleccion=<?php echo (int)$e['id']; ?>"
class="btn btn-info btn-sm text-white">

<i class="bi bi-trophy-fill"></i>

Resultados

</a>


<!-- ===============================================
     GRÁFICAS
================================================ -->

<a
href="graficas.php?id_eleccion=<?php echo (int)$e['id']; ?>"
class="btn btn-secondary btn-sm">

<i class="bi bi-bar-chart-fill"></i>

Gráficas

</a>


<?php if (
    $esAbierta
) { ?>


<!-- =============================================
     CERRAR
================================================ -->

<a
href="cerrar_eleccion.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-warning btn-sm"
onclick="
return confirm(
'¿Está seguro de cerrar esta elección? Después de cerrarla no se podrán registrar nuevos votos.'
);
">

<i class="bi bi-lock-fill"></i>

Cerrar

</a>


<?php } else { ?>


<!-- =============================================
     ABRIR
================================================ -->

<a
href="abrir_eleccion.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-success btn-sm"
onclick="
return confirm(
'¿Desea abrir esta elección para permitir la votación?'
);
">

<i class="bi bi-unlock-fill"></i>

Abrir

</a>


<?php } ?>


<!-- ===============================================
     ELIMINAR
================================================ -->

<a
href="eliminar_eleccion.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="
return confirm(
'¿Está seguro de eliminar esta elección? Esta acción puede afectar candidatos y votos asociados.'
);
">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>


</div>


</td>


</tr>


<?php } ?>


<?php } ?>


</tbody>


</table>


</div>


</div>


<!-- =====================================================
     VOLVER
========================================================= -->

<div class="mt-4">

<a
href="admin.php"
class="btn btn-outline-secondary">

<i class="bi bi-arrow-left-circle"></i>

Volver al panel

</a>

</div>


</div>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'administrador'
) {

    header("Location: login.php");
    exit();

}


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


/* =========================================================
   LISTAR ELECCIONES
========================================================= */

$elecciones = $conn->query("

    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado

    FROM elecciones

    ORDER BY fecha_inicio DESC, id DESC

");


if (!$elecciones) {

    die(
        "Error al consultar las elecciones: "
        . htmlspecialchars($conn->error)
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

Gestión de Elecciones

</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width:1250px;

    margin:auto;

    padding:35px 20px;

}


.encabezado {

    background:#1453a3;

    color:white;

    border-radius:18px;

    padding:25px;

    margin-bottom:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.12);

}


.encabezado h1 {

    margin:0;

    font-weight:bold;

}


.card-elecciones {

    background:white;

    border-radius:18px;

    overflow:hidden;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.table {

    margin-bottom:0;

}


.table thead th {

    background:#0d47a1;

    color:white;

    white-space:nowrap;

}


.nombre-eleccion {

    font-weight:bold;

    color:#1453a3;

}


.descripcion {

    max-width:300px;

    color:#6c757d;

}


.estado-abierta {

    background:#198754;

    color:white;

    padding:7px 12px;

    border-radius:7px;

    font-weight:bold;

}


.estado-cerrada {

    background:#dc3545;

    color:white;

    padding:7px 12px;

    border-radius:7px;

    font-weight:bold;

}


.acciones {

    display:flex;

    flex-wrap:wrap;

    gap:5px;

}


.vacia {

    padding:50px;

    text-align:center;

    color:#6c757d;

}


.vacia i {

    font-size:55px;

}


</style>

</head>


<body>


<div class="contenedor">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>

<h1>

<i class="bi bi-calendar-event-fill"></i>

Gestión de Elecciones

</h1>


<p class="mb-0 mt-2">

Crea y administra las elecciones escolares.

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
     MENSAJE
===================================================== -->

<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php echo htmlspecialchars(
    $tipoMensaje
); ?> alert-dismissible fade show">


<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars(
    $mensaje
); ?>


<button
type="button"
class="btn-close"
data-bs-dismiss="alert">

</button>


</div>


<?php } ?>


<!-- =====================================================
     TABLA
===================================================== -->

<div class="card-elecciones">


<div class="table-responsive">


<table class="table table-bordered table-hover align-middle">


<thead>

<tr>

<th>ID</th>

<th>Elección</th>

<th>Descripción</th>

<th>Inicio</th>

<th>Fin</th>

<th>Estado</th>

<th>Acciones</th>

</tr>

</thead>


<tbody>


<?php if (
    $elecciones->num_rows === 0
) { ?>


<tr>

<td
colspan="7"
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


<?php while (
    $e =
    $elecciones->fetch_assoc()
) { ?>


<tr>


<!-- =================================================
     ID
===================================================== -->

<td>

<strong>

<?php echo (int)$e['id']; ?>

</strong>

</td>


<!-- =================================================
     NOMBRE
===================================================== -->

<td>

<div class="nombre-eleccion">

<?php echo htmlspecialchars(
    $e['nombre']
); ?>

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
     FECHA INICIO
===================================================== -->

<td>

<?php echo htmlspecialchars(
    $e['fecha_inicio']
); ?>

</td>


<!-- =================================================
     FECHA FIN
===================================================== -->

<td>

<?php echo htmlspecialchars(
    $e['fecha_fin']
); ?>

</td>


<!-- =================================================
     ESTADO
===================================================== -->

<td>


<?php

$estado =
    strtolower(
        trim(
            (string)$e['estado']
        )
    );


if (
    $estado === "abierta"
) {

?>


<span class="estado-abierta">

🟢 Abierta

</span>


<?php

} else {

?>


<span class="estado-cerrada">

🔴 Cerrada

</span>


<?php

}

?>


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
    $estado === "abierta"
) { ?>


<!-- =============================================
     CERRAR
================================================ -->

<a
href="cerrar_eleccion.php"
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
href="abrir_eleccion.php"
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
'¿Está seguro de eliminar esta elección? Esta acción puede afectar sus candidatos y votos asociados.'
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
===================================================== -->

<div class="mt-4">


<a
href="admin.php"
class="btn btn-outline-secondary">


<i class="bi bi-arrow-left-circle"></i>

Volver al panel


</a>


</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
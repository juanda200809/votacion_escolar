<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

$mensaje = "";
$tipoMensaje = "";


/* =========================================
   ELIMINAR ESTUDIANTE
========================================= */

if (isset($_GET['eliminar'])) {

    $id = (int)$_GET['eliminar'];

    $stmt = $conn->prepare("
        DELETE FROM usuarios
        WHERE id = ?
        AND rol = 'estudiante'
    ");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        $mensaje =
            "Estudiante eliminado correctamente.";

        $tipoMensaje = "success";

    } else {

        $mensaje =
            "No se pudo eliminar el estudiante.";

        $tipoMensaje = "danger";

    }

    $stmt->close();
}


/* =========================================
   REGISTRAR ESTUDIANTE
========================================= */

if (
    isset($_POST['registrar_estudiante'])
) {

    $documento =
        trim($_POST['documento'] ?? '');

    $nombre =
        trim($_POST['nombre'] ?? '');

    $apellido =
        trim($_POST['apellido'] ?? '');

    $curso =
        trim($_POST['curso'] ?? '');


    if (
        $documento === "" ||
        $nombre === "" ||
        $apellido === "" ||
        $curso === ""
    ) {

        $mensaje =
            "Todos los campos son obligatorios.";

        $tipoMensaje = "danger";

    } else {


        /* =====================================
           COMPROBAR DOCUMENTO
        ===================================== */

        $stmt = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $documento
        );

        $stmt->execute();

        $existe =
            $stmt->get_result();

        $stmt->close();


        if ($existe->num_rows > 0) {

            $mensaje =
                "El documento ya está registrado.";

            $tipoMensaje = "danger";

        } else {


            /* =================================
               CONTRASEÑA = DOCUMENTO
            ================================= */

            $password =
                password_hash(
                    $documento,
                    PASSWORD_DEFAULT
                );


            $rol = "estudiante";


            /* =================================
               INSERTAR
            ================================= */

            $stmt = $conn->prepare("
                INSERT INTO usuarios
                (
                    documento,
                    nombre,
                    apellido,
                    curso,
                    password,
                    rol,
                    fecha_registro
                )
                VALUES
                (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "ssssss",
                $documento,
                $nombre,
                $apellido,
                $curso,
                $password,
                $rol
            );


            if ($stmt->execute()) {

                $mensaje =
                    "Estudiante registrado correctamente. " .
                    "La contraseña inicial es el documento.";

                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "Error al registrar estudiante: " .
                    $stmt->error;

                $tipoMensaje = "danger";

            }

            $stmt->close();

        }

    }

}


/* =========================================
   EDITAR ESTUDIANTE
========================================= */

if (
    isset($_POST['editar_estudiante'])
) {

    $id =
        (int)($_POST['id'] ?? 0);

    $documento =
        trim($_POST['documento'] ?? '');

    $nombre =
        trim($_POST['nombre'] ?? '');

    $apellido =
        trim($_POST['apellido'] ?? '');

    $curso =
        trim($_POST['curso'] ?? '');


    if (
        $id <= 0 ||
        $documento === "" ||
        $nombre === "" ||
        $apellido === "" ||
        $curso === ""
    ) {

        $mensaje =
            "Todos los campos son obligatorios.";

        $tipoMensaje = "danger";

    } else {


        /* =====================================
           COMPROBAR DOCUMENTO DUPLICADO
        ===================================== */

        $stmt = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $documento,
            $id
        );

        $stmt->execute();

        $duplicado =
            $stmt->get_result();

        $stmt->close();


        if ($duplicado->num_rows > 0) {

            $mensaje =
                "El documento ya pertenece a otro usuario.";

            $tipoMensaje = "danger";

        } else {


            /* =================================
               ACTUALIZAR DATOS
            ================================= */

            $stmt = $conn->prepare("
                UPDATE usuarios
                SET
                    documento = ?,
                    nombre = ?,
                    apellido = ?,
                    curso = ?
                WHERE id = ?
                AND rol = 'estudiante'
            ");

            $stmt->bind_param(
                "ssssi",
                $documento,
                $nombre,
                $apellido,
                $curso,
                $id
            );


            if ($stmt->execute()) {

                $mensaje =
                    "Estudiante actualizado correctamente.";

                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "No se pudo actualizar el estudiante.";

                $tipoMensaje = "danger";

            }

            $stmt->close();

        }

    }

}


/* =========================================
   OBTENER ESTUDIANTES
========================================= */

$estudiantes = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso,
        fecha_registro
    FROM usuarios
    WHERE rol = 'estudiante'
    ORDER BY nombre ASC, apellido ASC
");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Gestión de Estudiantes
</title>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =========================================
     ICONOS
========================================= -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:1300px;

    margin:auto;

    padding:35px 25px;

}


/* =========================================
   CABECERA
========================================= */

.cabecera {

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    gap:15px;

    margin-bottom:25px;

}


.titulo {

    color:#1453a3;

    font-weight:bold;

    margin:0;

}


/* =========================================
   TARJETA
========================================= */

.card {

    border:none;

    border-radius:18px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


/* =========================================
   CABECERA DE TARJETA
========================================= */

.card-header {

    background:#1453a3;

    color:white;

    padding:18px 22px;

    border-radius:
        18px 18px 0 0 !important;

}


/* =========================================
   TABLA
========================================= */

.tabla {

    vertical-align:middle;

}


.tabla thead {

    background:#cfe0ff;

    color:#084298;

}


.tabla th {

    white-space:nowrap;

}


.tabla td {

    padding:12px;

}


/* =========================================
   BOTONES
========================================= */

.btn-editar {

    background:#ffc107;

    border:none;

    color:#111;

}


.btn-editar:hover {

    background:#e0a800;

    color:#111;

}


.btn-eliminar {

    background:#dc3545;

    border:none;

    color:white;

}


.btn-eliminar:hover {

    background:#bb2d3b;

    color:white;

}


/* =========================================
   INFO CONTRASEÑA
========================================= */

.info-password {

    background:#eef5ff;

    border:
        1px solid #b6d4fe;

    color:#084298;

    border-radius:10px;

    padding:12px;

}


/* =========================================
   MODAL
========================================= */

.modal-content {

    border:none;

    border-radius:18px;

}


.modal-header {

    background:#1453a3;

    color:white;

    border-radius:
        18px 18px 0 0;

}


</style>

</head>


<body>


<div class="contenedor">


<!-- =========================================
     CABECERA
========================================= -->

<div class="cabecera">


<div>

<h1 class="titulo">

<i class="bi bi-people-fill"></i>

Estudiantes

</h1>

<p class="text-muted mb-0">

Administración de estudiantes registrados.

</p>

</div>


<div class="d-flex gap-2 flex-wrap">


<a
href="admin.php"
class="btn btn-outline-primary">

<i class="bi bi-arrow-left"></i>

Volver

</a>


<a
href="importar_estudiantes.php"
class="btn btn-success">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<a
href="exportar_estudiantes.php"
class="btn btn-success">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<button
type="button"
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#modalRegistrar">

<i class="bi bi-person-plus-fill"></i>

Nuevo estudiante

</button>


</div>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div
class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="alert alert-info">

<i class="bi bi-key-fill"></i>

<strong>Contraseña inicial:</strong>

El documento del estudiante.

Por ejemplo:

Documento:

<strong>1000000003</strong>

Contraseña:

<strong>1000000003</strong>

</div>


<!-- =========================================
     TABLA
========================================= -->

<div class="card">


<div class="card-header">

<div class="d-flex
            justify-content-between
            align-items-center">

<h4 class="mb-0">

<i class="bi bi-person-lines-fill"></i>

Listado de estudiantes

</h4>

<span>

<?php echo $estudiantes
    ? $estudiantes->num_rows
    : 0; ?>

registrados

</span>

</div>

</div>


<div class="card-body p-0">


<div class="table-responsive">


<table
class="table table-hover table-bordered mb-0 tabla">


<thead>

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Curso</th>

<th>Fecha registro</th>

<th>Acciones</th>

</tr>

</thead>


<tbody>


<?php if (
    !$estudiantes ||
    $estudiantes->num_rows === 0
) { ?>


<tr>

<td
colspan="7"
class="text-center p-5">


<i
class="bi bi-person-x fs-1 text-muted">
</i>


<h5 class="mt-3">

No hay estudiantes registrados.

</h5>


</td>

</tr>


<?php } else { ?>


<?php while (
    $estudiante =
    $estudiantes->fetch_assoc()
) { ?>


<tr>


<td>

<?php echo (int)$estudiante['id']; ?>

</td>


<td>

<strong>

<?php echo htmlspecialchars(
    $estudiante['documento']
); ?>

</strong>

</td>


<td>

<?php echo htmlspecialchars(
    $estudiante['nombre']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
    $estudiante['apellido']
); ?>

</td>


<td>

<span
class="badge bg-primary">

<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>

</span>

</td>


<td>

<?php echo htmlspecialchars(
    $estudiante['fecha_registro']
); ?>

</td>


<td>


<!-- EDITAR -->

<button

type="button"

class="btn btn-editar btn-sm"

data-bs-toggle="modal"

data-bs-target="#modalEditar"

data-id="<?php echo (int)$estudiante['id']; ?>"

data-documento="<?php echo htmlspecialchars(
    $estudiante['documento'],
    ENT_QUOTES
); ?>"

data-nombre="<?php echo htmlspecialchars(
    $estudiante['nombre'],
    ENT_QUOTES
); ?>"

data-apellido="<?php echo htmlspecialchars(
    $estudiante['apellido'],
    ENT_QUOTES
); ?>"

data-curso="<?php echo htmlspecialchars(
    $estudiante['curso'],
    ENT_QUOTES
); ?>"

onclick="cargarEditar(this)">

<i
class="bi bi-pencil-square">
</i>

Editar

</button>


<!-- ELIMINAR -->

<a

href="estudiantes.php?eliminar=<?php echo (int)$estudiante['id']; ?>"

class="btn btn-eliminar btn-sm"

onclick="
return confirm(
'¿Está seguro de eliminar este estudiante?'
);
">

<i
class="bi bi-trash-fill">
</i>

Eliminar

</a>


</td>


</tr>


<?php } ?>


<?php } ?>


</tbody>

</table>

</div>

</div>

</div>


<!-- =========================================
     VOLVER
========================================= -->

<div class="text-center mt-4">

<a
href="admin.php"
class="btn btn-outline-primary">

<i class="bi bi-arrow-left"></i>

Volver al panel de administración

</a>

</div>


</div>


<!-- =====================================================
     MODAL REGISTRAR
===================================================== -->

<div
class="modal fade"
id="modalRegistrar"
tabindex="-1">


<div
class="modal-dialog modal-lg modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">

<h5 class="modal-title">

<i class="bi bi-person-plus-fill"></i>

Registrar estudiante

</h5>


<button
type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal">
</button>

</div>


<form method="POST">


<div class="modal-body">


<div class="row">


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Documento

</label>

<input
type="text"
name="documento"
class="form-control"
required
autocomplete="off">

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Curso

</label>

<input
type="text"
name="curso"
class="form-control"
required>

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Nombre

</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Apellido

</label>

<input
type="text"
name="apellido"
class="form-control"
required>

</div>


</div>


<div class="info-password">

<i class="bi bi-key-fill"></i>

La contraseña se generará automáticamente
con el documento del estudiante.

</div>


</div>


<div class="modal-footer">


<button
type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Cancelar

</button>


<button
type="submit"
name="registrar_estudiante"
class="btn btn-primary">

<i class="bi bi-save-fill"></i>

Registrar estudiante

</button>


</div>


</form>

</div>

</div>

</div>


<!-- =====================================================
     MODAL EDITAR
===================================================== -->

<div
class="modal fade"
id="modalEditar"
tabindex="-1">


<div
class="modal-dialog modal-lg modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">

<h5 class="modal-title">

<i class="bi bi-pencil-square"></i>

Editar estudiante

</h5>


<button
type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal">
</button>

</div>


<form method="POST">


<div class="modal-body">


<input
type="hidden"
name="id"
id="editar_id">


<div class="row">


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Documento

</label>

<input
type="text"
name="documento"
id="editar_documento"
class="form-control"
required>

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Curso

</label>

<input
type="text"
name="curso"
id="editar_curso"
class="form-control"
required>

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Nombre

</label>

<input
type="text"
name="nombre"
id="editar_nombre"
class="form-control"
required>

</div>


<div class="col-md-6 mb-3">

<label
class="form-label fw-bold">

Apellido

</label>

<input
type="text"
name="apellido"
id="editar_apellido"
class="form-control"
required>

</div>


</div>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

Si cambia el documento, la contraseña
<strong>no se cambia automáticamente</strong>.

</div>


</div>


<div class="modal-footer">


<button
type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Cancelar

</button>


<button
type="submit"
name="editar_estudiante"
class="btn btn-primary">

<i class="bi bi-check-circle-fill"></i>

Guardar cambios

</button>


</div>


</form>

</div>

</div>

</div>


<!-- =========================================
     JAVASCRIPT
========================================= -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

function cargarEditar(boton) {

    document.getElementById(
        "editar_id"
    ).value =
        boton.dataset.id;


    document.getElementById(
        "editar_documento"
    ).value =
        boton.dataset.documento;


    document.getElementById(
        "editar_nombre"
    ).value =
        boton.dataset.nombre;


    document.getElementById(
        "editar_apellido"
    ).value =
        boton.dataset.apellido;


    document.getElementById(
        "editar_curso"
    ).value =
        boton.dataset.curso;

}

</script>


</body>

</html>
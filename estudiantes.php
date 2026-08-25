<<?php

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

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


/* =========================================================
   REGISTRAR ESTUDIANTE
========================================================= */

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento'] ?? '');
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $curso     = trim($_POST['curso'] ?? '');

    if (
        $documento === "" ||
        $nombre === "" ||
        $apellido === "" ||
        $curso === ""
    ) {

        $mensaje = "Debe completar todos los campos.";
        $tipoMensaje = "danger";

    } elseif (!preg_match('/^[0-9]+$/', $documento)) {

        $mensaje = "El documento debe contener únicamente números.";
        $tipoMensaje = "danger";

    } else {

        /* COMPROBAR DOCUMENTO */

        $stmt = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $mensaje = "No se pudo comprobar el documento.";
            $tipoMensaje = "danger";

        } else {

            $stmt->bind_param("s", $documento);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {

                $mensaje = "Ya existe un usuario con ese documento.";
                $tipoMensaje = "danger";

                $stmt->close();

            } else {

                $stmt->close();

                /* CONTRASEÑA = DOCUMENTO */

                $password_hash = password_hash(
                    $documento,
                    PASSWORD_DEFAULT
                );

                $correo = "";

                /* INSERTAR */

                $stmt = $conn->prepare("
                    INSERT INTO usuarios
                    (
                        documento,
                        nombre,
                        apellido,
                        correo,
                        curso,
                        password,
                        rol
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, 'estudiante')
                ");

                if (!$stmt) {

                    $mensaje = "No se pudo preparar el registro.";
                    $tipoMensaje = "danger";

                } else {

                    $stmt->bind_param(
                        "ssssss",
                        $documento,
                        $nombre,
                        $apellido,
                        $correo,
                        $curso,
                        $password_hash
                    );

                    if ($stmt->execute()) {

                        $mensaje =
                            "Estudiante registrado correctamente.";

                        $tipoMensaje = "success";

                    } else {

                        $mensaje =
                            "Error al registrar el estudiante.";

                        $tipoMensaje = "danger";
                    }

                    $stmt->close();
                }
            }
        }
    }
}


/* =========================================================
   ELIMINAR ESTUDIANTE
========================================================= */

if (isset($_GET['eliminar'])) {

    $id = (int)$_GET['eliminar'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM usuarios
            WHERE id = ?
            AND rol = 'estudiante'
        ");

        if ($stmt) {

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: estudiantes.php");
    exit();
}


/* =========================================================
   CONSULTAR ESTUDIANTES
========================================================= */

$estudiantes = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE rol = 'estudiante'
    ORDER BY nombre ASC, apellido ASC
");

if (!$estudiantes) {

    die(
        "Error al consultar estudiantes: " .
        htmlspecialchars($conn->error)
    );
}

$total = $estudiantes->num_rows;

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

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link
rel="stylesheet"
href="css/estilos.css">

<style>

body {

    background: #eef3f9;

    min-height: 100vh;

}

.contenedor {

    max-width: 1400px;

}

.card-principal {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.12);

}

.titulo {

    color: #0d47a1;

    font-weight: 700;

}

.btn-accion {

    border-radius: 10px;

    font-weight: 600;

}

.info-password {

    background: #cff4fc;

    color: #055160;

    border: 1px solid #b6effb;

    border-radius: 10px;

    padding: 14px;

}

.buscar {

    max-width: 700px;

}

.table {

    background: white;

}

.table thead th {

    background: #cfe2ff;

    font-weight: 700;

    vertical-align: middle;

}

.total-badge {

    font-size: 15px;

    padding: 9px 14px;

}

.btn-editar {

    background: #ffc107;

    color: #000;

    border: none;

    font-weight: 600;

}

.btn-editar:hover {

    background: #ffca2c;

    color: #000;

}

.btn-eliminar {

    background: #dc3545;

    color: white;

    border: none;

    font-weight: 600;

}

.btn-eliminar:hover {

    background: #bb2d3b;

    color: white;

}

.modal-content {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 10px 40px
        rgba(0,0,0,.25);

}

.modal-header {

    background: #198754;

    color: white;

    border-radius:
        18px 18px 0 0;

}

.modal-title {

    font-weight: bold;

}

.footer {

    text-align: center;

    color: #6c757d;

    margin-top: 30px;

    padding: 20px;

}

</style>

</head>


<body>


<div class="container-fluid contenedor py-4">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="card card-principal mb-4">

<div class="card-body">

<div
class="d-flex
       justify-content-between
       align-items-center
       flex-wrap
       gap-3">


<div>

<h2 class="titulo mb-1">

<i class="bi bi-people-fill"></i>

Gestión de Estudiantes

</h2>

<p class="text-muted mb-0">

Administre los estudiantes registrados
en el sistema.

</p>

</div>


<div
class="d-flex
       gap-2
       flex-wrap">


<!-- BOTÓN REGISTRAR -->

<button
type="button"
class="btn btn-success btn-accion"
data-bs-toggle="modal"
data-bs-target="#modalRegistrarEstudiante">

<i class="bi bi-person-plus-fill"></i>

Registrar estudiante

</button>


<!-- IMPORTAR -->

<a
href="importar_estudiantes.php"
class="btn btn-primary btn-accion">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<!-- EXPORTAR -->

<a
href="exportar_estudiantes.php"
class="btn btn-success btn-accion">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<!-- ADMIN -->

<a
href="admin.php"
class="btn btn-secondary btn-accion">

<i class="bi bi-arrow-left-circle-fill"></i>

Panel Admin

</a>

</div>

</div>

</div>

</div>


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if ($mensaje !== "") { ?>

<div
class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?> alert-dismissible fade show">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php } ?>


<!-- =====================================================
     LISTA DE ESTUDIANTES
===================================================== -->

<div class="card card-principal">

<div class="card-body p-4">


<div
class="d-flex
       justify-content-between
       align-items-center
       flex-wrap
       gap-2
       mb-3">


<h4 class="titulo mb-0">

<i class="bi bi-list-ul"></i>

Lista de estudiantes

</h4>


<span class="badge bg-primary total-badge">

Total:

<?php echo $total; ?>

</span>


</div>


<!-- =====================================================
     BUSCADOR
===================================================== -->

<div class="mb-2">

<div class="input-group buscar">

<span class="input-group-text">

<i class="bi bi-search"></i>

</span>


<input
type="text"
id="buscar"
class="form-control"
placeholder="Buscar por documento, nombre, apellido o curso..."
autocomplete="off">


<button
type="button"
id="limpiarBusqueda"
class="btn btn-outline-secondary">

<i class="bi bi-x-circle"></i>

Limpiar

</button>

</div>

</div>


<div
id="resultadoBusqueda"
class="text-muted small mb-3">

Mostrando todos los estudiantes.

</div>


<!-- =====================================================
     TABLA
===================================================== -->

<div class="table-responsive">

<table
class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>
ID
</th>

<th>
Documento
</th>

<th>
Nombre
</th>

<th>
Apellido
</th>

<th>
Curso
</th>

<th>
Acciones
</th>

</tr>

</thead>


<tbody id="tablaEstudiantes">

<?php

if ($estudiantes->num_rows > 0) {

    while (
        $e = $estudiantes->fetch_assoc()
    ) {

?>

<tr>

<td>

<?php echo (int)$e['id']; ?>

</td>


<td>

<strong>

<?php echo htmlspecialchars(
    $e['documento']
); ?>

</strong>

</td>


<td>

<?php echo htmlspecialchars(
    $e['nombre']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
    $e['apellido']
); ?>

</td>


<td>

<span class="badge bg-secondary">

<?php echo htmlspecialchars(
    $e['curso']
); ?>

</span>

</td>


<td>

<div
class="d-flex gap-1 flex-wrap">


<a
href="editar_estudiante.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-editar btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a
href="estudiantes.php?eliminar=<?php echo (int)$e['id']; ?>"
class="btn btn-eliminar btn-sm"
onclick="return confirm('¿Está seguro de eliminar este estudiante?');">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>


</div>

</td>

</tr>

<?php

    }

} else {

?>

<tr>

<td
colspan="6"
class="text-center text-muted py-5">

<i class="bi bi-person-x fs-1"></i>

<br>

<strong>

No hay estudiantes registrados.

</strong>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

</div>


<!-- =====================================================
     INFORMACIÓN
===================================================== -->

<div class="card card-principal mt-4">

<div class="card-body">

<div class="row text-center">

<div class="col-md-4">

<i
class="bi bi-people-fill fs-2 text-primary">
</i>

<h5 class="mt-2">

Estudiantes

</h5>

<p class="text-muted">

<?php echo $total; ?>

registrados

</p>

</div>


<div class="col-md-4">

<i
class="bi bi-key-fill fs-2 text-success">
</i>

<h5 class="mt-2">

Contraseña

</h5>

<p class="text-muted">

Documento del estudiante

</p>

</div>


<div class="col-md-4">

<i
class="bi bi-file-earmark-excel-fill fs-2 text-success">
</i>

<h5 class="mt-2">

Excel

</h5>

<p class="text-muted">

Importar y exportar estudiantes

</p>

</div>

</div>

</div>

</div>


<div class="footer">

<strong>

Sistema de Votaciones Escolares v2.0

</strong>

<br>

© <?php echo date("Y"); ?>

Todos los derechos reservados.

</div>


</div>


<!-- =====================================================
     MODAL REGISTRAR ESTUDIANTE
===================================================== -->

<div
class="modal fade"
id="modalRegistrarEstudiante"
tabindex="-1"
aria-hidden="true">


<div
class="modal-dialog modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

<i class="bi bi-person-plus-fill"></i>

Registrar nuevo estudiante

</h5>


<button
type="button"
class="btn-close btn-close-white"
data-bs-dismiss="modal"
aria-label="Cerrar">
</button>


</div>


<div class="modal-body p-4">


<form
method="POST"
autocomplete="off">


<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label fw-bold">

Documento

</label>


<input
type="text"
name="documento"
class="form-control form-control-lg"
placeholder="Documento del estudiante"
inputmode="numeric"
pattern="[0-9]+"
required>

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label class="form-label fw-bold">

Nombre

</label>


<input
type="text"
name="nombre"
class="form-control"
placeholder="Nombre"
required>

</div>


<!-- APELLIDO -->

<div class="mb-3">

<label class="form-label fw-bold">

Apellido

</label>


<input
type="text"
name="apellido"
class="form-control"
placeholder="Apellido"
required>

</div>


<!-- CURSO -->

<div class="mb-3">

<label class="form-label fw-bold">

Curso

</label>


<input
type="text"
name="curso"
class="form-control"
placeholder="Ejemplo: 1101"
required>

</div>


<!-- INFORMACIÓN CONTRASEÑA -->

<div class="info-password mb-4">

<i class="bi bi-key-fill"></i>

<strong>
Contraseña automática
</strong>

<br>

La contraseña inicial será el
<strong>número de documento</strong>
del estudiante.

</div>


<div
class="d-flex
       justify-content-end
       gap-2">


<button
type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Cancelar

</button>


<button
type="submit"
name="guardar"
class="btn btn-success">

<i class="bi bi-person-plus-fill"></i>

Guardar estudiante

</button>


</div>


</form>


</div>

</div>

</div>

</div>


<!-- =====================================================
     JAVASCRIPT BUSCADOR
===================================================== -->

<script>

const buscar =
    document.getElementById("buscar");

const limpiarBusqueda =
    document.getElementById("limpiarBusqueda");

const resultadoBusqueda =
    document.getElementById("resultadoBusqueda");

const filas =
    document.querySelectorAll(
        "#tablaEstudiantes tr"
    );


function buscarEstudiante() {

    const texto =
        buscar.value
        .toLowerCase()
        .trim();

    let encontrados = 0;


    filas.forEach(
        function(fila) {

            const celdas =
                fila.querySelectorAll("td");


            if (
                celdas.length < 6
            ) {

                return;

            }


            const contenido =
                fila.textContent
                .toLowerCase();


            if (
                texto === "" ||
                contenido.includes(texto)
            ) {

                fila.style.display = "";

                encontrados++;

            } else {

                fila.style.display = "none";

            }

        }
    );


    if (
        texto === ""
    ) {

        resultadoBusqueda.innerHTML =
            "Mostrando todos los estudiantes.";

    }

    else if (
        encontrados === 0
    ) {

        resultadoBusqueda.innerHTML =
            '<span class="text-danger">' +
            '<i class="bi bi-search"></i> ' +
            'No se encontraron estudiantes.' +
            '</span>';

    }

    else {

        resultadoBusqueda.innerHTML =
            '<span class="text-success">' +
            '<i class="bi bi-check-circle"></i> ' +
            'Estudiantes encontrados: ' +
            encontrados +
            '</span>';

    }

}


buscar.addEventListener(
    "input",
    buscarEstudiante
);


limpiarBusqueda.addEventListener(
    "click",
    function() {

        buscar.value = "";

        buscarEstudiante();

        buscar.focus();

    }
);


buscar.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            buscar.value = "";

            buscarEstudiante();

        }

    }
);

</script>


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
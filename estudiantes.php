<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
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
   REGISTRAR ESTUDIANTE
========================================= */

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

    } else {

        /* =========================================
           VERIFICAR SI YA EXISTE EL DOCUMENTO
        ========================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $documento);
        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $mensaje = "Ya existe un usuario con ese documento.";
            $tipoMensaje = "danger";

        } else {

            /* =========================================
               CONTRASEÑA AUTOMÁTICA
               = DOCUMENTO
            ========================================= */

            $password_hash = password_hash(
                $documento,
                PASSWORD_DEFAULT
            );

            /*
                El correo queda vacío.
                Ya no se solicita al administrador.
            */

            $correo = "";

            /* =========================================
               INSERTAR ESTUDIANTE
            ========================================= */

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
                VALUES (?, ?, ?, ?, ?, ?, 'estudiante')
            ");

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

                $mensaje = "Estudiante registrado correctamente.";
                $tipoMensaje = "success";

            } else {

                $mensaje = "Error al registrar el estudiante.";
                $tipoMensaje = "danger";
            }

            $stmt->close();
        }
    }
}


/* =========================================
   ELIMINAR ESTUDIANTE
========================================= */

if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM usuarios
            WHERE id = ?
            AND rol = 'estudiante'
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt->close();
    }

    header("Location: estudiantes.php");
    exit();
}


/* =========================================
   CONSULTAR ESTUDIANTES
========================================= */

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
        $conn->error
    );
}

$total = $estudiantes->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Gestión de Estudiantes</title>


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


<!-- =========================================
     ESTILOS DEL SISTEMA
========================================= -->

<link
rel="stylesheet"
href="css/estilos.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    background:#eef3f9;

    min-height:100vh;

}


/* =========================================
   CONTENEDOR PRINCIPAL
========================================= */

.contenedor-principal {

    max-width:1400px;

}


/* =========================================
   TARJETAS
========================================= */

.card-form {

    border:none;

    border-radius:18px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.12);

}


/* =========================================
   TITULOS
========================================= */

.titulo-principal {

    color:#0d47a1;

    font-weight:700;

}


/* =========================================
   BOTONES SUPERIORES
========================================= */

.btn-accion {

    border-radius:10px;

    padding:10px 18px;

    font-weight:600;

}


/* =========================================
   BOTONES EXCEL
========================================= */

.btn-exportar {

    background:#198754;

    color:white;

    border:none;

}

.btn-exportar:hover {

    background:#157347;

    color:white;

}


.btn-importar {

    background:#0d6efd;

    color:white;

    border:none;

}

.btn-importar:hover {

    background:#0b5ed7;

    color:white;

}


/* =========================================
   INFORMACIÓN CONTRASEÑA
========================================= */

.info-password {

    background:#cff4fc;

    color:#055160;

    border-radius:10px;

    padding:14px;

    margin-bottom:18px;

    border:1px solid #b6effb;

}


/* =========================================
   TABLA
========================================= */

.table {

    background:white;

    margin-bottom:0;

}

.table thead th {

    background:#cfe2ff;

    color:#000;

    font-weight:700;

    vertical-align:middle;

}


/* =========================================
   BUSCADOR
========================================= */

.buscar {

    max-width:400px;

    border-radius:10px;

}


/* =========================================
   BADGE TOTAL
========================================= */

.total-badge {

    font-size:16px;

    padding:9px 15px;

}


/* =========================================
   ALERTAS
========================================= */

.alert {

    border-radius:10px;

}


/* =========================================
   BOTONES TABLA
========================================= */

.btn-editar {

    background:#ffc107;

    border:none;

    color:#000;

    font-weight:600;

}

.btn-editar:hover {

    background:#ffca2c;

}


.btn-eliminar {

    background:#dc3545;

    border:none;

    color:white;

    font-weight:600;

}

.btn-eliminar:hover {

    background:#bb2d3b;

}


/* =========================================
   PIE
========================================= */

.footer {

    text-align:center;

    color:#6c757d;

    margin-top:30px;

    padding:20px;

}

</style>

</head>


<body>


<div class="container-fluid contenedor-principal py-4">


<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="card card-form mb-4">

<div class="card-body">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>

<h2 class="titulo-principal mb-1">

<i class="bi bi-people-fill"></i>

Gestión de Estudiantes

</h2>

<p class="text-muted mb-0">

Administre los estudiantes registrados
en el sistema.

</p>

</div>


<div class="d-flex gap-2 flex-wrap">


<!-- IMPORTAR -->

<a
href="importar_estudiantes.php"
class="btn btn-importar btn-accion">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar Excel

</a>


<!-- EXPORTAR -->

<a
href="exportar_estudiantes.php"
class="btn btn-exportar btn-accion">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<!-- VOLVER -->

<a
href="admin.php"
class="btn btn-primary btn-accion">

<i class="bi bi-arrow-left-circle-fill"></i>

Panel Admin

</a>


</div>

</div>

</div>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     CONTENIDO
========================================= -->

<div class="row g-4">


<!-- =========================================
     FORMULARIO
========================================= -->

<div class="col-lg-4">


<div class="card card-form">

<div class="card-body p-4">


<h4 class="titulo-principal mb-4">

<i class="bi bi-person-plus-fill"></i>

Registrar estudiante

</h4>


<form method="POST">


<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label">

<strong>Documento</strong>

</label>

<input
type="text"
name="documento"
class="form-control form-control-lg"
placeholder="Documento del estudiante"
required
autocomplete="off">

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label class="form-label">

<strong>Nombre</strong>

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

<label class="form-label">

<strong>Apellido</strong>

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

<label class="form-label">

<strong>Curso</strong>

</label>

<input
type="text"
name="curso"
class="form-control"
placeholder="Ejemplo: 1101"
required>

</div>


<!-- =========================================
     CONTRASEÑA AUTOMÁTICA
========================================= -->

<div class="info-password">

<i class="bi bi-key-fill"></i>

<strong>Contraseña automática</strong>

<br>

La contraseña será el
<strong>número de documento</strong>
del estudiante.

</div>


<!-- GUARDAR -->

<button
type="submit"
name="guardar"
class="btn btn-success btn-lg w-100">

<i class="bi bi-person-plus-fill"></i>

Guardar estudiante

</button>


</form>


<hr class="my-4">


<!-- IMPORTAR DESDE FORMULARIO -->

<div class="d-grid">

<a
href="importar_estudiantes.php"
class="btn btn-primary">

<i class="bi bi-file-earmark-arrow-up-fill"></i>

Importar estudiantes desde Excel

</a>

</div>


</div>

</div>

</div>


<!-- =========================================
     LISTA DE ESTUDIANTES
========================================= -->

<div class="col-lg-8">


<div class="card card-form">

<div class="card-body p-4">


<!-- ENCABEZADO -->

<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3">


<h4 class="titulo-principal mb-0">

<i class="bi bi-list-ul"></i>

Lista de estudiantes

</h4>


<span class="badge bg-primary total-badge">

Total: <?php echo $total; ?>

</span>


</div>


<!-- =========================================
     ACCIONES EXCEL
========================================= -->

<div class="d-flex
            gap-2
            flex-wrap
            mb-3">


<a
href="importar_estudiantes.php"
class="btn btn-primary">

<i class="bi bi-upload"></i>

Importar

</a>


<a
href="exportar_estudiantes.php"
class="btn btn-success">

<i class="bi bi-download"></i>

Exportar

</a>


</div>


<!-- =========================================
     BUSCADOR
========================================= -->

<div class="mb-3">

<div class="input-group buscar">

<span class="input-group-text">

<i class="bi bi-search"></i>

</span>

<input
type="text"
id="buscar"
class="form-control"
placeholder="Buscar estudiante...">

</div>

</div>


<!-- =========================================
     TABLA
========================================= -->

<div class="table-responsive">


<table
class="table table-bordered table-hover align-middle">


<thead>


<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Curso</th>

<th>Acciones</th>

</tr>


</thead>


<tbody id="tablaEstudiantes">


<?php

if ($estudiantes->num_rows > 0) {

    while ($e = $estudiantes->fetch_assoc()) {

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


<div class="d-flex gap-1 flex-wrap">


<!-- EDITAR -->

<a
href="editar_estudiante.php?id=<?php echo (int)$e['id']; ?>"
class="btn btn-editar btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<!-- ELIMINAR -->

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

</div>


</div>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="card card-form mt-4">

<div class="card-body">


<div class="row text-center">


<div class="col-md-4">

<i class="bi bi-people-fill fs-2 text-primary"></i>

<h5 class="mt-2">

Estudiantes

</h5>

<p class="text-muted">

<?php echo $total; ?> registrados

</p>

</div>


<div class="col-md-4">

<i class="bi bi-key-fill fs-2 text-success"></i>

<h5 class="mt-2">

Contraseña

</h5>

<p class="text-muted">

Documento del estudiante

</p>

</div>


<div class="col-md-4">

<i class="bi bi-file-earmark-excel-fill fs-2 text-success"></i>

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


<!-- =========================================
     PIE
========================================= -->

<div class="footer">

<strong>

Sistema de Votaciones Escolares v2.0

</strong>

<br>

© <?php echo date("Y"); ?>

Todos los derechos reservados.

</div>


</div>


<!-- =========================================
     BUSCADOR JAVASCRIPT
========================================= -->

<script>

const buscar =
document.getElementById("buscar");

const filas =
document.querySelectorAll(
    "#tablaEstudiantes tr"
);


buscar.addEventListener(
    "input",
    function () {

        const texto =
        this.value
        .toLowerCase()
        .trim();


        filas.forEach(
            function (fila) {

                const contenido =
                fila.textContent
                .toLowerCase();


                if (
                    contenido.includes(texto)
                ) {

                    fila.style.display = "";

                } else {

                    fila.style.display = "none";

                }

            }
        );

    }
);

</script>


<!-- BOOTSTRAP JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
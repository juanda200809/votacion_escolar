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
   REGISTRAR JURADO
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
           VERIFICAR DOCUMENTO
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

            $mensaje =
                "Ya existe un usuario con ese documento.";

            $tipoMensaje = "danger";

            $stmt->close();

        } else {

            $stmt->close();


            /* =========================================
               CONTRASEÑA AUTOMÁTICA
               = DOCUMENTO
            ========================================= */

            $password_hash = password_hash(
                $documento,
                PASSWORD_DEFAULT
            );


            /* =========================================
               CORREO VACÍO
            ========================================= */

            $correo = "";


            /* =========================================
               INSERTAR JURADO
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
                VALUES (?, ?, ?, ?, ?, ?, 'jurado')
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

                $mensaje =
                    "Jurado registrado correctamente.";

                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "Error al registrar el jurado.";

                $tipoMensaje = "danger";
            }

            $stmt->close();
        }
    }
}


/* =========================================
   ELIMINAR JURADO
========================================= */

if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM usuarios
            WHERE id = ?
            AND rol = 'jurado'
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt->close();
    }

    header("Location: crear_jurado.php");
    exit();
}


/* =========================================
   CONSULTAR JURADOS
========================================= */

$jurados = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE rol = 'jurado'
    ORDER BY nombre ASC, apellido ASC
");


if (!$jurados) {

    die(
        "Error al consultar jurados: " .
        $conn->error
    );

}


$totalJurados = $jurados->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Gestión de Jurados</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================
   GENERAL
========================================= */

body {

    background:#eef3f9;

    min-height:100vh;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:1400px;

}


/* =========================================
   TARJETAS
========================================= */

.card-jurado {

    border:none;

    border-radius:18px;

    box-shadow:
        0 5px 20px rgba(0,0,0,.12);

}


/* =========================================
   TITULOS
========================================= */

.titulo {

    color:#0d47a1;

    font-weight:700;

}


/* =========================================
   CABECERA
========================================= */

.encabezado {

    background:#0d47a1;

    color:white;

    border-radius:
        18px 18px 0 0;

    padding:25px;

}


/* =========================================
   ICONO
========================================= */

.icono-jurado {

    font-size:55px;

}


/* =========================================
   INFORMACIÓN
========================================= */

.info-password {

    background:#cff4fc;

    color:#055160;

    border:1px solid #b6effb;

    border-radius:10px;

    padding:14px;

}


/* =========================================
   TABLA
========================================= */

.table {

    background:white;

}


.table thead th {

    background:#cfe2ff;

    font-weight:700;

    vertical-align:middle;

}


/* =========================================
   BOTONES
========================================= */

.btn-accion {

    border-radius:10px;

    font-weight:600;

}


.btn-editar {

    background:#ffc107;

    border:none;

    color:#000;

}


.btn-eliminar {

    background:#dc3545;

    border:none;

    color:white;

}


/* =========================================
   BUSCADOR
========================================= */

.buscar {

    max-width:400px;

}


/* =========================================
   FOOTER
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


<div class="container-fluid contenedor py-4">


<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="card card-jurado mb-4">


<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div class="d-flex
            align-items-center
            gap-3">


<div class="icono-jurado">

⚖️

</div>


<div>

<h2 class="mb-1">

Gestión de Jurados

</h2>

<p class="mb-0">

Administre los jurados encargados
de las votaciones.

</p>

</div>

</div>


<a
href="admin.php"
class="btn btn-light btn-accion">

<i class="bi bi-arrow-left-circle-fill"></i>

Panel Administrador

</a>


</div>

</div>


<div class="card-body p-4">


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<div class="row g-4">


<!-- =========================================
     FORMULARIO
========================================= -->

<div class="col-lg-4">


<div class="card card-jurado h-100">


<div class="card-body p-4">


<h4 class="titulo mb-4">

<i class="bi bi-person-badge-fill"></i>

Registrar nuevo jurado

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
class="form-control"
placeholder="Documento del jurado"
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
     CONTRASEÑA
========================================= -->

<div class="info-password mb-4">

<i class="bi bi-key-fill"></i>

<strong>Contraseña automática</strong>

<br>

La contraseña del jurado será
su <strong>número de documento</strong>.

</div>


<!-- GUARDAR -->

<button
type="submit"
name="guardar"
class="btn btn-success btn-lg w-100 btn-accion">

<i class="bi bi-person-plus-fill"></i>

Guardar Jurado

</button>


</form>


</div>

</div>

</div>


<!-- =========================================
     LISTA
========================================= -->

<div class="col-lg-8">


<div class="card card-jurado h-100">


<div class="card-body p-4">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3">


<h4 class="titulo mb-0">

<i class="bi bi-list-ul"></i>

Jurados registrados

</h4>


<span class="badge bg-primary fs-6">

Total: <?php echo $totalJurados; ?>

</span>


</div>


<!-- BUSCADOR -->

<div class="input-group buscar mb-3">


<span class="input-group-text">

<i class="bi bi-search"></i>

</span>


<input
type="text"
id="buscar"
class="form-control"
placeholder="Buscar jurado...">

</div>


<!-- TABLA -->

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


<tbody id="tablaJurados">


<?php

if ($jurados->num_rows > 0) {

    while ($j = $jurados->fetch_assoc()) {

?>


<tr>


<td>

<?php echo (int)$j['id']; ?>

</td>


<td>

<strong>

<?php echo htmlspecialchars(
    $j['documento']
); ?>

</strong>

</td>


<td>

<?php echo htmlspecialchars(
    $j['nombre']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
    $j['apellido']
); ?>

</td>


<td>

<span class="badge bg-secondary">

<?php echo htmlspecialchars(
    $j['curso']
); ?>

</span>

</td>


<td>


<div class="d-flex gap-1 flex-wrap">


<a
href="editar_jurado.php?id=<?php echo (int)$j['id']; ?>"
class="btn btn-editar btn-sm btn-accion">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a
href="crear_jurado.php?eliminar=<?php echo (int)$j['id']; ?>"
class="btn btn-eliminar btn-sm btn-accion"
onclick="return confirm('¿Está seguro de eliminar este jurado?');">

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

No hay jurados registrados.

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


</div>

</div>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="card card-jurado mt-4">


<div class="card-body">


<div class="row text-center">


<div class="col-md-4">

<i class="bi bi-person-badge-fill fs-2 text-primary"></i>

<h5 class="mt-2">

Jurados

</h5>

<p class="text-muted">

<?php echo $totalJurados; ?> registrados

</p>

</div>


<div class="col-md-4">

<i class="bi bi-key-fill fs-2 text-success"></i>

<h5 class="mt-2">

Contraseña

</h5>

<p class="text-muted">

Documento del jurado

</p>

</div>


<div class="col-md-4">

<i class="bi bi-shield-check fs-2 text-primary"></i>

<h5 class="mt-2">

Rol

</h5>

<p class="text-muted">

Jurado de votación

</p>

</div>


</div>

</div>

</div>


<!-- FOOTER -->

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
     BUSCADOR
========================================= -->

<script>

const buscar =
document.getElementById("buscar");

const filas =
document.querySelectorAll(
    "#tablaJurados tr"
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


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
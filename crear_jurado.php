<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");

$mensaje = "";
$tipoMensaje = "";


/* =========================================
   CREAR JURADO
========================================= */

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);


    if (
        $documento == "" ||
        $nombre == "" ||
        $apellido == ""
    ) {

        $mensaje = "Debe completar todos los campos.";
        $tipoMensaje = "danger";

    } else {


        /* =========================================
           VERIFICAR DOCUMENTO
        ========================================= */

        $verificar = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE documento = ?
            LIMIT 1
        ");

        $verificar->bind_param(
            "s",
            $documento
        );

        $verificar->execute();

        $resultado = $verificar->get_result();


        if ($resultado->num_rows > 0) {

            $mensaje = "Ya existe un usuario con ese documento.";
            $tipoMensaje = "danger";

        } else {


            /* =========================================
               CONTRASEÑA = DOCUMENTO
            ========================================= */

            $passwordHash = password_hash(
                $documento,
                PASSWORD_DEFAULT
            );


            /* =========================================
               CREAR JURADO
            ========================================= */

            $insertar = $conn->prepare("
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
                (
                    ?,
                    ?,
                    ?,
                    '',
                    '',
                    ?,
                    'jurado'
                )
            ");


            $insertar->bind_param(
                "ssss",
                $documento,
                $nombre,
                $apellido,
                $passwordHash
            );


            if ($insertar->execute()) {

                $mensaje =
                    "Jurado creado correctamente. " .
                    "La contraseña es su documento.";

                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "Error al crear el jurado: " .
                    $conn->error;

                $tipoMensaje = "danger";

            }

        }

    }

}


/* =========================================
   ELIMINAR JURADO
========================================= */

if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);


    $eliminar = $conn->prepare("
        DELETE FROM usuarios
        WHERE id = ?
        AND rol = 'jurado'
    ");

    $eliminar->bind_param(
        "i",
        $id
    );

    $eliminar->execute();


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
        apellido
    FROM usuarios
    WHERE rol = 'jurado'
    ORDER BY nombre ASC
");


$totalJurados = $jurados->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gestión de Jurados</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background: #eef3f9;

    font-family: Arial, sans-serif;

}


.contenedor {

    max-width: 1200px;

    margin: auto;

    padding: 30px;

}


.encabezado {

    background: #0d47a1;

    color: white;

    padding: 20px 25px;

    border-radius: 15px;

    margin-bottom: 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.encabezado h2 {

    margin: 0;

}


.card {

    border: none;

    border-radius: 15px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


.icono-jurado {

    font-size: 60px;

    text-align: center;

    color: #6f42c1;

}


.form-title {

    color: #0d47a1;

    font-weight: bold;

}


.btn-guardar {

    background: #198754;

    color: white;

    width: 100%;

    padding: 12px;

    font-size: 17px;

    font-weight: bold;

}


.btn-guardar:hover {

    background: #157347;

    color: white;

}


.contador {

    background: #6f42c1;

    color: white;

    padding: 8px 15px;

    border-radius: 20px;

    font-weight: bold;

}


.buscar {

    max-width: 350px;

}


.table thead {

    background: #0d6efd;

    color: white;

}


@media(max-width:768px){

    .encabezado {

        flex-direction: column;

        gap: 15px;

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="contenedor">


<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="encabezado">

<div>

<h2>

<i class="bi bi-person-badge-fill"></i>

Gestión de Jurados

</h2>

<small>

Administración de jurados

</small>

</div>


<a
href="admin.php"
class="btn btn-light">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje != "") { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<div class="row g-4">


<!-- =========================================
     FORMULARIO
========================================= -->

<div class="col-lg-4">

<div class="card">

<div class="card-body p-4">


<div class="icono-jurado">

<i class="bi bi-person-badge-fill"></i>

</div>


<h4 class="text-center form-title mb-4">

Registrar nuevo jurado

</h4>


<form method="POST">


<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label">

<i class="bi bi-person-vcard-fill"></i>

Documento

</label>

<input

type="text"

name="documento"

class="form-control"

placeholder="Ingrese el documento"

required

autocomplete="off">

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label class="form-label">

<i class="bi bi-person-fill"></i>

Nombre

</label>

<input

type="text"

name="nombre"

class="form-control"

placeholder="Nombre del jurado"

required>

</div>


<!-- APELLIDO -->

<div class="mb-4">

<label class="form-label">

<i class="bi bi-person-fill"></i>

Apellido

</label>

<input

type="text"

name="apellido"

class="form-control"

placeholder="Apellido del jurado"

required>

</div>


<!-- INFORMACIÓN CONTRASEÑA -->

<div class="alert alert-info">

<i class="bi bi-key-fill"></i>

<strong>Contraseña automática:</strong>

<br>

La contraseña será igual al documento del jurado.

</div>


<!-- BOTÓN -->

<button

type="submit"

name="guardar"

class="btn btn-guardar">

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

<div class="card">

<div class="card-body p-4">


<div class="d-flex justify-content-between align-items-center mb-3">

<h4>

<i class="bi bi-people-fill"></i>

Lista de Jurados

</h4>


<span class="contador">

Total: <?php echo $totalJurados; ?>

</span>

</div>


<input

type="text"

id="buscar"

class="form-control buscar mb-4"

placeholder="🔍 Buscar jurado...">


<div class="table-responsive">


<table class="table table-bordered table-hover">

<thead>

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Acciones</th>

</tr>

</thead>


<tbody id="tablaJurados">


<?php

if ($jurados->num_rows == 0) {

?>

<tr>

<td
colspan="5"
class="text-center text-muted">

No hay jurados registrados.

</td>

</tr>

<?php

}


while ($jurado = $jurados->fetch_assoc()) {

?>

<tr>


<td>

<?php echo $jurado['id']; ?>

</td>


<td>

<?php echo htmlspecialchars(
$jurado['documento']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
$jurado['nombre']
); ?>

</td>


<td>

<?php echo htmlspecialchars(
$jurado['apellido']
); ?>

</td>


<td>


<a

href="editar_estudiante.php?id=<?php echo $jurado['id']; ?>&tipo=jurado"

class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a

href="crear_jurado.php?eliminar=<?php echo $jurado['id']; ?>"

class="btn btn-danger btn-sm"

onclick="return confirm('¿Está seguro de eliminar este jurado?');">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>


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


<div class="text-center mt-4">

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Volver al Panel Administrador

</a>

</div>


</div>


<script>

/* =========================================
   BUSCADOR
========================================= */

const buscar =
document.getElementById("buscar");


buscar.addEventListener("keyup", function() {

    let texto =
        this.value.toLowerCase();


    let filas =
        document.querySelectorAll(
            "#tablaJurados tr"
        );


    filas.forEach(function(fila) {

        let contenido =
            fila.textContent.toLowerCase();


        if (contenido.includes(texto)) {

            fila.style.display = "";

        } else {

            fila.style.display = "none";

        }

    });

});

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
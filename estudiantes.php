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


/* =========================================
   VARIABLES
========================================= */

$mensaje = "";
$tipoMensaje = "";


/* =========================================
   GUARDAR ESTUDIANTE
========================================= */

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $curso = trim($_POST['curso']);
    $password = trim($_POST['password']);


    /* =========================================
       VALIDAR CAMPOS
    ========================================= */

    if (
        $documento == "" ||
        $nombre == "" ||
        $apellido == "" ||
        $curso == "" ||
        $password == ""
    ) {

        $mensaje = "Debe completar todos los campos obligatorios.";
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
               CIFRAR CONTRASEÑA
            ========================================= */

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* =========================================
               INSERTAR ESTUDIANTE
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
                (?, ?, ?, ?, ?, ?, 'estudiante')
            ");


            $insertar->bind_param(
                "ssssss",
                $documento,
                $nombre,
                $apellido,
                $correo,
                $curso,
                $passwordHash
            );


            if ($insertar->execute()) {

                $mensaje = "Estudiante registrado correctamente.";
                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "Error al registrar estudiante: " .
                    $conn->error;

                $tipoMensaje = "danger";

            }

        }

    }

}


/* =========================================
   ELIMINAR ESTUDIANTE
========================================= */

if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);


    $eliminar = $conn->prepare("
        DELETE FROM usuarios
        WHERE id = ?
        AND rol = 'estudiante'
    ");

    $eliminar->bind_param(
        "i",
        $id
    );

    $eliminar->execute();


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
        correo,
        curso
    FROM usuarios
    WHERE rol = 'estudiante'
    ORDER BY nombre ASC
");


$totalEstudiantes = $estudiantes->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gestión de Estudiantes</title>


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

    background: #eef3f9;

    font-family: Arial, sans-serif;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width: 1250px;

    margin: auto;

    padding: 30px;

}


/* =========================================
   ENCABEZADO
========================================= */

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


/* =========================================
   TARJETAS
========================================= */

.card {

    border: none;

    border-radius: 15px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


/* =========================================
   ICONO
========================================= */

.icono-estudiante {

    font-size: 60px;

    text-align: center;

    color: #0d6efd;

}


/* =========================================
   TITULO FORMULARIO
========================================= */

.form-title {

    color: #0d47a1;

    font-weight: bold;

}


/* =========================================
   BOTÓN GUARDAR
========================================= */

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


/* =========================================
   CONTADOR
========================================= */

.contador {

    background: #0d6efd;

    color: white;

    padding: 8px 15px;

    border-radius: 20px;

    font-weight: bold;

}


/* =========================================
   BUSCADOR
========================================= */

.buscar {

    max-width: 350px;

}


/* =========================================
   TABLA
========================================= */

.table thead {

    background: #0d6efd;

    color: white;

}


.table th {

    vertical-align: middle;

}


.table td {

    vertical-align: middle;

}


/* =========================================
   EXPORTAR
========================================= */

.btn-excel {

    background: #198754;

    color: white;

}


.btn-excel:hover {

    background: #157347;

    color: white;

}


/* =========================================
   RESPONSIVE
========================================= */

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

<i class="bi bi-people-fill"></i>

Gestión de Estudiantes

</h2>

<small>

Administración de estudiantes del sistema

</small>

</div>


<div>

<a
href="exportar_estudiantes.php"
class="btn btn-success me-2">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a
href="admin.php"
class="btn btn-light">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

</div>

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


<div class="icono-estudiante">

<i class="bi bi-person-plus-fill"></i>

</div>


<h4 class="text-center form-title mb-4">

Registrar nuevo estudiante

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

placeholder="Documento"

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

placeholder="Nombre"

required>

</div>


<!-- APELLIDO -->

<div class="mb-3">

<label class="form-label">

<i class="bi bi-person-fill"></i>

Apellido

</label>

<input

type="text"

name="apellido"

class="form-control"

placeholder="Apellido"

required>

</div>


<!-- CORREO -->

<div class="mb-3">

<label class="form-label">

<i class="bi bi-envelope-fill"></i>

Correo

</label>

<input

type="email"

name="correo"

class="form-control"

placeholder="Correo electrónico">

</div>


<!-- CURSO -->

<div class="mb-3">

<label class="form-label">

<i class="bi bi-mortarboard-fill"></i>

Curso

</label>

<input

type="text"

name="curso"

class="form-control"

placeholder="Ejemplo: 11A"

required>

</div>


<!-- CONTRASEÑA -->

<div class="mb-4">

<label class="form-label">

<i class="bi bi-key-fill"></i>

Contraseña

</label>

<input

type="text"

name="password"

class="form-control"

placeholder="Contraseña"

required>

</div>


<!-- BOTÓN -->

<button

type="submit"

name="guardar"

class="btn btn-guardar">

<i class="bi bi-person-plus-fill"></i>

Guardar Estudiante

</button>


</form>


</div>

</div>

</div>


<!-- =========================================
     LISTA DE ESTUDIANTES
========================================= -->

<div class="col-lg-8">

<div class="card">

<div class="card-body p-4">


<div class="d-flex justify-content-between align-items-center mb-3">

<h4>

<i class="bi bi-list-ul"></i>

Lista de Estudiantes

</h4>


<span class="contador">

Total: <?php echo $totalEstudiantes; ?>

</span>

</div>


<!-- BUSCADOR -->

<input

type="text"

id="buscar"

class="form-control buscar mb-4"

placeholder="🔍 Buscar estudiante...">


<div class="table-responsive">


<table class="table table-bordered table-hover">

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

if ($estudiantes->num_rows == 0) {

?>

<tr>

<td
colspan="6"
class="text-center text-muted">

No hay estudiantes registrados.

</td>

</tr>

<?php

}


while ($estudiante = $estudiantes->fetch_assoc()) {

?>

<tr>


<td>

<?php echo $estudiante['id']; ?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['documento']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['nombre']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['apellido']
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $estudiante['curso']
);

?>

</td>


<td>


<a

href="editar_estudiante.php?id=<?php echo $estudiante['id']; ?>"

class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a

href="estudiantes.php?eliminar=<?php echo $estudiante['id']; ?>"

class="btn btn-danger btn-sm"

onclick="return confirm('¿Está seguro de eliminar este estudiante?');">

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


<!-- =========================================
     BOTÓN VOLVER
========================================= -->

<div class="text-center mt-4">

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Volver al Panel Administrador

</a>

</div>


</div>


<!-- =========================================
     BUSCADOR
========================================= -->

<script>

const buscar =
document.getElementById("buscar");


buscar.addEventListener(
    "keyup",
    function()
    {

        let texto =
            this.value.toLowerCase();


        let filas =
            document.querySelectorAll(
                "#tablaEstudiantes tr"
            );


        filas.forEach(
            function(fila)
            {

                let contenido =
                    fila.textContent.toLowerCase();


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
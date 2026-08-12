<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: index.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=          GUARDAR ESTUDIANTE             =
=========================================*/

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);

    /*
     * La contraseña será automáticamente
     * el mismo documento del estudiante.
     */
    $password_hash = password_hash($documento, PASSWORD_DEFAULT);

    // Verificar si el documento ya existe
    $verificar = $conn->query("
        SELECT id
        FROM usuarios
        WHERE documento='$documento'
    ");

    if ($verificar->num_rows > 0) {

        $mensaje = "
        <div class='alerta error'>
            <i class='bi bi-exclamation-triangle-fill'></i>
            Ya existe un usuario con ese documento.
        </div>";

    } else {

        /*
         * Como ya no utilizaremos correo,
         * dejamos ese campo vacío.
         */
        $sql = "
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
            '$documento',
            '$nombre',
            '$apellido',
            '',
            '$curso',
            '$password_hash',
            'estudiante'
        )
        ";

        if ($conn->query($sql)) {

            $mensaje = "
            <div class='alerta ok'>
                <i class='bi bi-check-circle-fill'></i>
                Estudiante registrado correctamente.
                <br>
                <strong>Usuario:</strong> $documento
                <br>
                <strong>Contraseña:</strong> su documento
            </div>";

        } else {

            $mensaje = "
            <div class='alerta error'>
                <i class='bi bi-x-circle-fill'></i>
                Error al registrar el estudiante:
                " . $conn->error . "
            </div>";
        }
    }
}

/*=========================================
=             ELIMINAR                    =
=========================================*/

if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);

    $conn->query("
        DELETE FROM usuarios
        WHERE id=$id
        AND rol='estudiante'
    ");

    header("Location: estudiantes.php");
    exit();
}

/*=========================================
=             CONSULTA                    =
=========================================*/

$estudiantes = $conn->query("
    SELECT *
    FROM usuarios
    WHERE rol='estudiante'
    ORDER BY nombre ASC
");

$total = $estudiantes->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gestión de Estudiantes</title>

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
    background:#eef3f9;
}

.card-form {
    border:none;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.15);
}

.table {
    background:white;
}

.alerta {
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
}

.ok {
    background:#d1fae5;
    color:#065f46;
}

.error {
    background:#fee2e2;
    color:#991b1b;
}

.buscar {
    max-width:350px;
}

</style>

</head>

<body>

<div class="container py-4">

<!-- ENCABEZADO -->

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-people-fill"></i>

Gestión de Estudiantes

</h2>

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

</div>


<!-- MENSAJE -->

<?php

if (isset($mensaje)) {
    echo $mensaje;
}

?>


<div class="row">

<!-- =========================================
     FORMULARIO
========================================= -->

<div class="col-md-4">

<div class="card card-form">

<div class="card-body">

<h4 class="mb-4">

<i class="bi bi-person-plus-fill"></i>

Registrar Estudiante

</h4>

<form method="POST">

<!-- DOCUMENTO -->

<div class="mb-3">

<label class="form-label">
Documento
</label>

<input
type="text"
name="documento"
class="form-control"
required
autocomplete="off">

</div>


<!-- NOMBRE -->

<div class="mb-3">

<label class="form-label">
Nombre
</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>


<!-- APELLIDO -->

<div class="mb-3">

<label class="form-label">
Apellido
</label>

<input
type="text"
name="apellido"
class="form-control"
required>

</div>


<!-- CURSO -->

<div class="mb-3">

<label class="form-label">
Curso
</label>

<input
type="text"
name="curso"
class="form-control"
required>

</div>


<!-- AVISO DE CONTRASEÑA -->

<div class="alert alert-info">

<i class="bi bi-info-circle-fill"></i>

<strong>Contraseña automática</strong>

<br>

La contraseña del estudiante será
su mismo número de documento.

</div>


<!-- BOTÓN -->

<button
type="submit"
name="guardar"
class="btn btn-success w-100">

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

<div class="col-md-8">

<div class="card card-form">

<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-3">

<h4>

<i class="bi bi-list-ul"></i>

Lista de Estudiantes

</h4>

<span class="badge bg-primary fs-6">

Total: <?php echo $total; ?>

</span>

</div>


<!-- BUSCADOR -->

<input
type="text"
id="buscar"
class="form-control buscar mb-3"
placeholder="🔍 Buscar estudiante...">


<!-- TABLA -->

<div class="table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead class="table-primary">

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

while ($e = $estudiantes->fetch_assoc()) {

?>

<tr>

<td>
<?php echo $e['id']; ?>
</td>

<td>
<?php echo $e['documento']; ?>
</td>

<td>
<?php echo $e['nombre']; ?>
</td>

<td>
<?php echo $e['apellido']; ?>
</td>

<td>
<?php echo $e['curso']; ?>
</td>

<td>

<a
href="editar_estudiante.php?id=<?php echo $e['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>

<a
href="?eliminar=<?php echo $e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Desea eliminar este estudiante?')">

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

</div>


<!-- =========================================
     BUSCADOR
========================================= -->

<script>

const buscar = document.getElementById("buscar");

buscar.addEventListener("keyup", function(){

    let texto = this.value.toLowerCase();

    let filas = document.querySelectorAll(
        "#tablaEstudiantes tr"
    );

    filas.forEach(function(fila){

        let contenido =
            fila.textContent.toLowerCase();

        if(contenido.indexOf(texto) > -1){

            fila.style.display = "";

        }else{

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
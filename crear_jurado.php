<?php

session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

$mensaje = "";
$tipo = "";

/* =========================================
   CREAR JURADO
========================================= */

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (
        $documento == "" ||
        $nombre == "" ||
        $apellido == "" ||
        $password == ""
    ) {

        $mensaje = "Debe completar los campos obligatorios.";
        $tipo = "danger";

    } else {

        $documentoSeguro = $conn->real_escape_string($documento);

        /* Verificar documento */

        $verificar = $conn->query("
            SELECT id
            FROM usuarios
            WHERE documento='$documentoSeguro'
            LIMIT 1
        ");

        if ($verificar && $verificar->num_rows > 0) {

            $mensaje = "Ya existe un usuario con ese documento.";
            $tipo = "danger";

        } else {

            /* Cifrar contraseña */

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $nombreSeguro = $conn->real_escape_string($nombre);
            $apellidoSeguro = $conn->real_escape_string($apellido);
            $correoSeguro = $conn->real_escape_string($correo);

            /* Crear jurado */

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
                    '$documentoSeguro',
                    '$nombreSeguro',
                    '$apellidoSeguro',
                    '$correoSeguro',
                    '',
                    '$passwordHash',
                    'jurado'
                )
            ";

            if ($conn->query($sql)) {

                $mensaje = "Jurado creado correctamente.";
                $tipo = "success";

            } else {

                $mensaje = "Error al crear el jurado: " . $conn->error;
                $tipo = "danger";

            }

        }

    }

}


/* =========================================
   LISTAR JURADOS
========================================= */

$jurados = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        correo,
        rol
    FROM usuarios
    WHERE rol='jurado'
    ORDER BY nombre ASC
");

$total = $jurados ? $jurados->num_rows : 0;


/* =========================================
   ELIMINAR JURADO
========================================= */

if (isset($_GET['eliminar'])) {

    $id = (int) $_GET['eliminar'];

    $conn->query("
        DELETE FROM usuarios
        WHERE id=$id
        AND rol='jurado'
    ");

    header("Location: crear_jurado.php");
    exit();
}

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
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,.12);
}

.titulo {
    color: #0d47a1;
}

</style>

</head>

<body>

<div class="container py-4">

<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

<h2 class="titulo">

<i class="bi bi-person-badge-fill"></i>

Gestión de Jurados

</h2>

<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje != "") { ?>

<div class="alert alert-<?php echo $tipo; ?>">

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<div class="row">


<!-- =========================================
     FORMULARIO
========================================= -->

<div class="col-md-4">

<div class="card">

<div class="card-body">

<h4 class="mb-4">

<i class="bi bi-person-plus-fill"></i>

Crear Jurado

</h4>

<form method="POST">


<div class="mb-3">

<label class="form-label">
Documento *
</label>

<input
type="text"
name="documento"
class="form-control"
required
autocomplete="off">

</div>


<div class="mb-3">

<label class="form-label">
Nombre *
</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>


<div class="mb-3">

<label class="form-label">
Apellido *
</label>

<input
type="text"
name="apellido"
class="form-control"
required>

</div>


<div class="mb-3">

<label class="form-label">
Correo
</label>

<input
type="email"
name="correo"
class="form-control">

</div>


<div class="mb-3">

<label class="form-label">
Contraseña *
</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>


<button
type="submit"
name="guardar"
class="btn btn-success w-100">

<i class="bi bi-person-plus-fill"></i>

Crear Jurado

</button>

</form>

</div>

</div>

</div>


<!-- =========================================
     LISTA DE JURADOS
========================================= -->

<div class="col-md-8">

<div class="card">

<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-3">

<h4>

Lista de Jurados

</h4>

<span class="badge bg-primary fs-6">

Total: <?php echo $total; ?>

</span>

</div>


<div class="table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead class="table-primary">

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Correo</th>

<th>Acciones</th>

</tr>

</thead>

<tbody>

<?php if ($jurados && $jurados->num_rows > 0) { ?>

<?php while ($j = $jurados->fetch_assoc()) { ?>

<tr>

<td>
<?php echo $j['id']; ?>
</td>

<td>
<?php echo htmlspecialchars($j['documento']); ?>
</td>

<td>

<?php
echo htmlspecialchars(
    $j['nombre'] . " " . $j['apellido']
);
?>

</td>

<td>

<?php echo htmlspecialchars($j['correo']); ?>

</td>

<td>

<a
href="?eliminar=<?php echo $j['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Desea eliminar este jurado?');">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>

</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>

<td colspan="5" class="text-center">

No hay jurados registrados.

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

</div>


</body>

</html>
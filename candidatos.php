<?php

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

/*=========================================
=           VALIDAR SESIÓN
=========================================*/

if (!isset($_SESSION['id']) || $_SESSION['rol'] != "administrador") {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

/*=========================================
=      OBTENER ELECCIÓN ABIERTA
=========================================*/

$sql = "
SELECT *
FROM elecciones
WHERE estado='abierta'
LIMIT 1
";

$resultadoEleccion = $conn->query($sql);

if (!$resultadoEleccion) {
    die("Error al consultar la elección: " . $conn->error);
}

if ($resultadoEleccion->num_rows == 0) {
    die("
    <div style='
        width:600px;
        margin:80px auto;
        font-family:Arial;
        text-align:center;
        border:1px solid #ddd;
        padding:30px;
        border-radius:10px;
        box-shadow:0 0 10px rgba(0,0,0,.15);
    '>

        <h2>No existe una elección abierta.</h2>

        <p>
            Primero debe crear una elección y dejarla en estado
            <strong>Abierta</strong>.
        </p>

        <a href='elecciones.php'>
            Ir a Elecciones
        </a>

    </div>
    ");
}

$eleccion = $resultadoEleccion->fetch_assoc();

$idEleccion = (int)$eleccion['id'];

/*=========================================
=      REGISTRAR CANDIDATO
=========================================*/

if(isset($_POST['guardar'])){

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);
    $tarjeton = (int)$_POST['tarjeton'];
    $propuestas = trim($_POST['propuestas']);
    $idCargo = (int)$_POST['id_cargo'];

    /*=========================
    VALIDAR CAMPOS
    =========================*/

    if(
        empty($nombre) ||
        empty($apellido) ||
        empty($curso) ||
        empty($tarjeton) ||
        empty($idCargo)
    ){
        die("Todos los campos obligatorios deben estar completos.");
    }

    /*=========================
    VALIDAR TARJETÓN
    =========================*/

    $sql = "
    SELECT id
    FROM candidatos
    WHERE numero_tarjeton = $tarjeton
    AND id_eleccion = $idEleccion
    AND id_cargo = $idCargo
    LIMIT 1
    ";

    $existe = $conn->query($sql);

    if($existe->num_rows > 0){
        die("Ya existe un candidato con ese número de tarjetón para este cargo.");
    }

    $foto = "";

    /*=========================
    SUBIR FOTO
    =========================*/

    if(
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] == 0
    ){

        if(!is_dir("uploads/candidatos")){
            mkdir("uploads/candidatos",0777,true);
        }

        $extension = strtolower(
            pathinfo(
                $_FILES['foto']['name'],
                PATHINFO_EXTENSION
            )
        );

        $permitidas = ["jpg","jpeg","png","webp"];

        if(in_array($extension,$permitidas)){

            $foto = uniqid().".".$extension;

            move_uploaded_file(
                $_FILES['foto']['tmp_name'],
                "uploads/candidatos/".$foto
            );

        }

    }
        /*=========================================
    =      INSERTAR CANDIDATO
    =========================================*/

    $sql = "
    INSERT INTO candidatos
    (
        nombre,
        apellido,
        curso,
        foto,
        propuestas,
        numero_tarjeton,
        id_eleccion,
        id_cargo
    )
    VALUES
    (
        '$nombre',
        '$apellido',
        '$curso',
        '$foto',
        '$propuestas',
        $tarjeton,
        $idEleccion,
        $idCargo
    )
    ";

    if(!$conn->query($sql)){

        die("Error al registrar el candidato:<br><br>".$conn->error);

    }

    header("Location: candidatos.php?ok=1");
    exit();

}

/*=========================================
=      ELIMINAR CANDIDATO
=========================================*/

if(isset($_GET['eliminar'])){

    $id = (int)$_GET['eliminar'];

    $buscar = $conn->query("
    SELECT foto
    FROM candidatos
    WHERE id=$id
    ");

    if($buscar->num_rows>0){

        $datos = $buscar->fetch_assoc();

        if(
            $datos['foto']!="" &&
            file_exists("uploads/candidatos/".$datos['foto'])
        ){

            unlink("uploads/candidatos/".$datos['foto']);

        }

    }

    $conn->query("
    DELETE FROM candidatos
    WHERE id=$id
    ");

    header("Location: candidatos.php");
    exit();

}

/*=========================================
=      CONSULTAR CARGOS
=========================================*/

$cargos = $conn->query("
SELECT *
FROM cargos
ORDER BY nombre_cargo
");

/*=========================================
=      CONSULTAR CANDIDATOS
=========================================*/

$candidatos = $conn->query("
SELECT

candidatos.*,

cargos.nombre_cargo,

elecciones.nombre AS nombre_eleccion

FROM candidatos

INNER JOIN cargos

ON candidatos.id_cargo = cargos.id

INNER JOIN elecciones

ON candidatos.id_eleccion = elecciones.id

ORDER BY

cargos.nombre_cargo,

candidatos.numero_tarjeton
");

$total = $candidatos->num_rows;

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Gestión de Candidatos</title>

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

.tarjeton{

font-size:28px;
font-weight:bold;
color:#0d6efd;

}

.foto{

width:80px;
height:80px;
border-radius:50%;
object-fit:cover;
border:3px solid #0d6efd;

}

</style>

</head>

<body>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">

    <h2>

        <i class="bi bi-person-badge-fill"></i>

        Gestión de Candidatos

    </h2>

    <a
    href="admin.php"
    class="btn btn-secondary">

        <i class="bi bi-arrow-left-circle"></i>

        Volver

    </a>

</div>

<?php

if(isset($_GET['ok'])){

?>

<div class="alert alert-success">

    <i class="bi bi-check-circle-fill"></i>

    El candidato fue registrado correctamente.

</div>

<?php

}

?>

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h4 class="mb-0">

Elección activa:
<strong>

<?php echo $eleccion['nombre']; ?>

</strong>

</h4>

</div>

<div class="card-body">

<form
method="POST"
enctype="multipart/form-data">

<div class="row">

<div class="col-md-6">

<label class="form-label">

Nombre

</label>

<input
type="text"
name="nombre"
class="form-control"
required>

</div>

<div class="col-md-6">

<label class="form-label">

Apellido

</label>

<input
type="text"
name="apellido"
class="form-control"
required>

</div>

</div>

<br>

<div class="row">

<div class="col-md-4">

<label class="form-label">

Curso

</label>

<input
type="text"
name="curso"
class="form-control"
required>

</div>

<div class="col-md-4">

<label class="form-label">

Número de Tarjetón

</label>

<input
type="number"
name="tarjeton"
class="form-control"
min="1"
required>

</div>

<div class="col-md-4">

<label class="form-label">

Cargo

</label>

<select
name="id_cargo"
class="form-select"
required>

<option value="">

Seleccione...

</option>

<?php

while($cargo = $cargos->fetch_assoc()){

?>

<option value="<?php echo $cargo['id']; ?>">

<?php echo $cargo['nombre_cargo']; ?>

</option>

<?php

}

?>

</select>

</div>

</div>

<br>

<label class="form-label">

Fotografía

</label>

<input
type="file"
name="foto"
class="form-control"
accept=".jpg,.jpeg,.png,.webp">

<br>

<label class="form-label">

Propuestas

</label>

<textarea
name="propuestas"
class="form-control"
rows="5"
placeholder="Escriba las propuestas del candidato..."></textarea>

<br>

<button
type="submit"
name="guardar"
class="btn btn-success">

<i class="bi bi-floppy-fill"></i>

Guardar Candidato

</button>

<a
href="admin.php"
class="btn btn-secondary">

Cancelar

</a>

</form>

</div>

</div>

<br>
<div class="card shadow">

<div class="card-body">

<div class="row">

<div class="col-md-6">

<h4>

Lista de Candidatos

</h4>

</div>

<div class="col-md-6 text-end">

<span class="badge bg-primary">

Total:
<?php echo $total; ?>

</span>

</div>

</div>

<hr>

<input
type="text"
id="buscar"
class="form-control"
placeholder="🔍 Buscar candidato...">

<br>

<div class="table-responsive">

<table
class="table table-hover align-middle">

<thead class="table-primary">

<tr>

<th>Foto</th>

<th>Tarjetón</th>

<th>Nombre</th>

<th>Curso</th>

<th>Cargo</th>

<th>Elección</th>

<th>Acciones</th>

</tr>

</thead>

<tbody id="tablaCandidatos">

<?php

while($c = $candidatos->fetch_assoc()){

?>

<tr>

<td>

<?php

if(
$c['foto'] != "" &&
file_exists("uploads/candidatos/".$c['foto'])
){

?>

<img
src="uploads/candidatos/<?php echo $c['foto']; ?>"
class="foto">

<?php

}else{

?>

<img
src="https://via.placeholder.com/80?text=Foto"
class="foto">

<?php

}

?>

</td>

<td>

<span class="tarjeton">

#<?php echo $c['numero_tarjeton']; ?>

</span>

</td>

<td>

<strong>

<?php echo $c['nombre']." ".$c['apellido']; ?>

</strong>

<br>

<small>

<?php echo nl2br($c['propuestas']); ?>

</small>

</td>

<td>

<?php echo $c['curso']; ?>

</td>

<td>

<span class="badge bg-info">

<?php echo $c['nombre_cargo']; ?>

</span>

</td>

<td>

<span class="badge bg-success">

<?php echo $c['nombre_eleccion']; ?>

</span>

</td>

<td>

<a
href="editar_candidato.php?id=<?php echo $c['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>

<a
href="?eliminar=<?php echo $c['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Desea eliminar este candidato?');">

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
<script>

document.getElementById("buscar").addEventListener("keyup", function(){

    let texto = this.value.toLowerCase();

    let filas = document.querySelectorAll("#tablaCandidatos tr");

    filas.forEach(function(fila){

        if(fila.textContent.toLowerCase().includes(texto)){

            fila.style.display = "";

        }else{

            fila.style.display = "none";

        }

    });

});

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>


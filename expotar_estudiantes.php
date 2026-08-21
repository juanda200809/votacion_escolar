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
   CONSULTAR ESTUDIANTES
========================================= */

$sql = $conn->query("
    SELECT
        id,
        nombre,
        apellido,
        documento,
        curso
    FROM usuarios
    WHERE rol='estudiante'
    ORDER BY nombre ASC
");


/* =========================================
   NOMBRE DEL ARCHIVO
========================================= */

$nombreArchivo = "estudiantes_" . date("Y-m-d") . ".xls";


/* =========================================
   ENCABEZADOS PARA EXCEL
========================================= */

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=\"$nombreArchivo\""
);

header("Pragma: no-cache");

header("Expires: 0");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<style>

table {

    border-collapse: collapse;

}

th {

    background: #0d6efd;

    color: white;

    font-weight: bold;

    padding: 10px;

    border: 1px solid #000;

}

td {

    padding: 8px;

    border: 1px solid #000;

}

</style>

</head>

<body>

<table>

<tr>

<th>ID</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Documento</th>

<th>Curso</th>

</tr>


<?php

while ($estudiante = $sql->fetch_assoc()) {

?>

<tr>

<td>

<?php echo $estudiante['id']; ?>

</td>

<td>

<?php echo htmlspecialchars($estudiante['nombre']); ?>

</td>

<td>

<?php echo htmlspecialchars($estudiante['apellido']); ?>

</td>

<td>

<?php echo htmlspecialchars($estudiante['documento']); ?>

</td>

<td>

<?php echo htmlspecialchars($estudiante['curso']); ?>

</td>

</tr>

<?php

}

?>

</table>

</body>

</html>
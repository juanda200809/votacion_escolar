<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

/*==========================
    ESTADÍSTICAS
==========================*/

$totalEstudiantes = $conn->query("
SELECT COUNT(*) total
FROM usuarios
WHERE rol='estudiante'
")->fetch_assoc()['total'];

$totalCandidatos = $conn->query("
SELECT COUNT(*) total
FROM candidatos
")->fetch_assoc()['total'];

$totalVotos = $conn->query("
SELECT COUNT(*) total
FROM votos
")->fetch_assoc()['total'];

$estado = "Cerrada";

$consulta = $conn->query("
SELECT estado
FROM elecciones
LIMIT 1
");

if($consulta->num_rows>0){
    $estado = ucfirst($consulta->fetch_assoc()['estado']);
}

$fecha = date("d/m/Y");
$hora  = date("h:i A");
?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Panel Administrador</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet"
href="css/estilos.css">

<style>
    .card-dashboard{

animation: aparecer .6s ease;

}

@keyframes aparecer{

from{

opacity:0;

transform:translateY(25px);

}

to{

opacity:1;

transform:translateY(0);

}

}

.card-dashboard:hover{

transform:translateY(-8px);

transition:.3s;

box-shadow:0 15px 35px rgba(0,0,0,.18);

}
    .btn{
    transition:.3s;
}

.btn:hover{
    transform:translateY(-4px);
}

.btn i{
    display:block;
    margin-bottom:8px;
}
    .card-dashboard .card-body{

padding:35px 20px;

min-height:240px;

display:flex;

flex-direction:column;

justify-content:center;

align-items:center;

}

.numero{

font-size:48px;

font-weight:bold;

margin:10px 0;

color:#0d6efd;

}

.icono{

font-size:65px;

}

.titulo{

font-size:20px;

font-weight:600;

color:#555;

text-align:center;

line-height:1.4;

}

body{
    background:#eef3f8;
}

.main{
    margin-left:250px;
    padding:35px;
}

.card-dashboard{
    border:none;
    border-radius:18px;
    transition:.3s;
    box-shadow:0 8px 20px rgba(0,0,0,.12);
}

.card-dashboard:hover{
    transform:translateY(-6px);
}

.numero{
    font-size:45px;
    font-weight:bold;
    color:#0d6efd;
}

.icono{
    font-size:60px;
}

.titulo{
    font-size:17px;
    color:#666;
}

.estado-abierta{
    color:green;
    font-weight:bold;
}

.estado-cerrada{
    color:red;
    font-weight:bold;
}

</style>

</head>

<body>

<nav class="navbar navbar-dark bg-primary shadow"
style="margin-left:250px;width:calc(100% - 250px);">

<div class="container-fluid">

<span class="navbar-brand">

🗳 Sistema de Votaciones Escolares

</span>

<span class="text-white">

Administrador

</span>

</div>

</nav>
    <div class="sidebar">

<h2 class="text-center text-white mt-3 mb-4">
🗳️ VOTACIONES
</h2>

<a href="admin.php">
<i class="bi bi-house-fill"></i>
Inicio
</a>

<a href="estudiantes.php">
<i class="bi bi-people-fill"></i>
Estudiantes
</a>

<a href="candidatos.php">
<i class="bi bi-person-badge-fill"></i>
Candidatos
</a>

<a href="resultados.php">
<i class="bi bi-trophy-fill"></i>
Resultados
</a>
<a href="elecciones.php">

<i class="bi bi-calendar-event-fill"></i>

Elecciones

</a>

<a href="graficas.php">
<i class="bi bi-bar-chart-fill"></i>
Gráficas
</a>

<a href="abrir_eleccion.php">
<i class="bi bi-unlock-fill"></i>
Abrir Elección
</a>

<a href="cerrar_eleccion.php">
<i class="bi bi-lock-fill"></i>
Cerrar Elección
</a>

<a href="logout.php">
<i class="bi bi-box-arrow-right"></i>
Cerrar Sesión
</a>

</div>

<div class="main">
    <!-- ENCABEZADO -->

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Bienvenido,

<?php echo $_SESSION['nombre']; ?> 👋
<div class="alert alert-primary mt-3 shadow-sm">

<h5 class="mb-1">

👋 ¡Bienvenido al Panel de Administración!

</h5>

Administre estudiantes, candidatos, votaciones y resultados desde un solo lugar.

</div>

</h2>

<p class="text-muted">

Panel de Administración del Sistema de Votaciones Escolares

</p>

</div>

<div class="text-end">

<strong>📅 Fecha:</strong>

<?php echo $fecha; ?>

<br>

<strong>🕒 Hora:</strong>

<?php echo $hora; ?>

</div>

</div>
<div class="row g-4">

<!-- ESTUDIANTES -->

<div class="col-xl-3 col-md-6">

<div class="card card-dashboard">

<div class="card-body text-center">

<div class="icono">

👨‍🎓

</div>

<div class="numero">

<?php echo $totalEstudiantes; ?>

</div>

<div class="titulo">

Estudiantes Registrados

</div>

</div>

</div>

</div>


<!-- CANDIDATOS -->

<div class="col-xl-3 col-md-6">

<div class="card card-dashboard">

<div class="card-body text-center">

<div class="icono">

🗳️

</div>

<div class="numero">

<?php echo $totalCandidatos; ?>

</div>

<div class="titulo">

Candidatos Inscritos

</div>

</div>

</div>

</div>

<!-- VOTOS -->

<div class="col-xl-3 col-md-6">

<div class="card card-dashboard">

<div class="card-body text-center">

<div class="icono">

✅

</div>

<div class="numero">

<?php echo $totalVotos; ?>

</div>

<div class="titulo">

Votos Registrados

</div>

</div>

</div>

</div>

<!-- ESTADO -->

<div class="col-xl-3 col-md-6">

<div class="card card-dashboard">

<div class="card-body text-center">

<div class="icono">

<?php

if($estado=="Abierta"){

echo "🟢";

}else{

echo "🔴";

}

?>

</div>
<div class="alert alert-success mt-4">

<h5>

📈 Estado General del Sistema

</h5>

<ul>

<li>Total estudiantes: <b><?php echo $totalEstudiantes; ?></b></li>

<li>Total candidatos: <b><?php echo $totalCandidatos; ?></b></li>

<li>Total votos registrados: <b><?php echo $totalVotos; ?></b></li>

<li>Elección: <b><?php echo $estado; ?></b></li>

</ul>

</div>

<div class="numero" style="font-size:28px;">

<?php echo $estado; ?>

</div>

<div class="titulo">

Estado de la Elección

</div>

</div>

</div>

</div>

</div>
<!-- ACCESOS RÁPIDOS -->

<div class="card card-dashboard mt-5">

    <div class="card-body">

        <h3 class="mb-4">
            <a
href="admin.php"
class="btn btn-outline-primary">

<i class="bi bi-arrow-clockwise"></i>

Actualizar Panel

</a>
            <i class="bi bi-lightning-charge-fill text-warning"></i>
            Accesos Rápidos
        </h3>

        <div class="row g-3">

            <div class="col-lg-4 col-md-6">
                <a href="estudiantes.php" class="btn btn-primary w-100 p-3">
                    <i class="bi bi-people-fill fs-4"></i><br>
                    Gestionar Estudiantes
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="candidatos.php" class="btn btn-success w-100 p-3">
                    <i class="bi bi-person-badge-fill fs-4"></i><br>
                    Gestionar Candidatos
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="resultados.php" class="btn btn-warning w-100 p-3">
                    <i class="bi bi-trophy-fill fs-4"></i><br>
                    Ver Resultados
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="graficas.php" class="btn btn-info w-100 p-3 text-white">
                    <i class="bi bi-bar-chart-fill fs-4"></i><br>
                    Ver Gráficas
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="pdf_resultados.php" class="btn btn-danger w-100 p-3">
                    <i class="bi bi-file-earmark-pdf-fill fs-4"></i><br>
                    Descargar PDF
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="logout.php" class="btn btn-dark w-100 p-3">
                    <i class="bi bi-box-arrow-right fs-4"></i><br>
                    Cerrar Sesión
                </a>
                
            </div>

        </div>

    </div>

</div>
<!-- INFORMACIÓN DEL SISTEMA -->

<div class="row mt-5">

    <!-- Información -->

    <div class="col-lg-8">

        <div class="card card-dashboard">

            <div class="card-body">

                <h3 class="mb-4">

                    <i class="bi bi-info-circle-fill text-primary"></i>

                    Información del Sistema

                </h3>

                <table class="table table-hover align-middle">

                    <tr>
                        <th width="35%">Administrador</th>
                        <td><?php echo $_SESSION['nombre']; ?></td>
                    </tr>

                    <tr>
                        <th>Fecha</th>
                        <td><?php echo $fecha; ?></td>
                    </tr>

                    <tr>
                        <th>Hora</th>
                        <td><?php echo $hora; ?></td>
                    </tr>

                    <tr>
                        <th>Estado de la elección</th>

                        <td>

                        <?php if($estado=="Abierta"){ ?>

                            <span class="badge bg-success fs-6">
                                🟢 Abierta
                            </span>

                        <?php }else{ ?>

                            <span class="badge bg-danger fs-6">
                                🔴 Cerrada
                            </span>

                        <?php } ?>

                        </td>

                    </tr>

                    <tr>
                        <th>Versión</th>
                        <td>2.0 Profesional</td>
                    </tr>

                </table>

            </div>

        </div>

    </div>
        <!-- Resumen -->

    <div class="col-lg-4">

        <div class="card card-dashboard">

            <div class="card-body">

                <h3 class="mb-4">

                    <i class="bi bi-clipboard-data-fill text-success"></i>

                    Resumen

                </h3>

                <ul class="list-group">

                    <li class="list-group-item d-flex justify-content-between">

                        Estudiantes

                        <span class="badge bg-primary">

                            <?php echo $totalEstudiantes; ?>

                        </span>

                    </li>

                    <li class="list-group-item d-flex justify-content-between">

                        Candidatos

                        <span class="badge bg-success">

                            <?php echo $totalCandidatos; ?>

                        </span>

                    </li>

                    <li class="list-group-item d-flex justify-content-between">

                        Votos

                        <span class="badge bg-warning text-dark">

                            <?php echo $totalVotos; ?>

                        </span>

                    </li>

                    <li class="list-group-item d-flex justify-content-between">

                        Estado

                        <?php if($estado=="Abierta"){ ?>

                        <span class="badge bg-success">

                            Activa

                        </span>

                        <?php }else{ ?>

                        <span class="badge bg-danger">

                            Cerrada

                        </span>

                        <?php } ?>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</div>
<hr class="mt-5">

<footer class="text-center mt-5 text-secondary">

<hr>

<p>

<b>Sistema de Votaciones Escolares v2.0</b>

</p>

<p>

Desarrollado por <b>Juan David Otero Cantor</b>

</p>

<p>

© <?php echo date("Y"); ?> Todos los derechos reservados.

</p>

</footer>
<script>

function actualizarHora(){
    <span id="reloj"></span>

const ahora=new Date();

document.getElementById("reloj").innerHTML=ahora.toLocaleTimeString();

}

setInterval(actualizarHora,1000);

actualizarHora();

</script>

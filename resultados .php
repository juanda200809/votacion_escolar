<?php

session_start();

/*=========================================
=          VERIFICAR SESIÓN
=========================================*/

if (!isset($_SESSION['id'])) {

    header("Location: login.php");
    exit();

}


/*=========================================
=          VERIFICAR ROL
=========================================*/

if ($_SESSION['rol'] != 'jurado' &&
    $_SESSION['rol'] != 'administrador') {

    header("Location: login.php");
    exit();

}


include("config/conexion.php");


/*=========================================
=          BUSCAR ELECCIÓN
=========================================*/

$consultaEleccion = $conn->query("
    SELECT *
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (!$consultaEleccion ||
    $consultaEleccion->num_rows == 0) {

    die("
        <div style='
            text-align:center;
            margin-top:100px;
            font-family:Arial;
        '>

            <h2>No hay elecciones registradas.</h2>

            <a href='jurado.php'>
                Volver
            </a>

        </div>
    ");

}


$eleccion = $consultaEleccion->fetch_assoc();

$idEleccion = (int)$eleccion['id'];


/*=========================================
=          TOTAL DE VOTOS
=========================================*/

$consultaTotal = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
    WHERE id_eleccion = $idEleccion
");


$totalVotos = 0;

if ($consultaTotal) {

    $resultadoTotal = $consultaTotal->fetch_assoc();

    $totalVotos = $resultadoTotal['total'];

}


/*=========================================
=          CARGOS
=========================================*/

$cargos = $conn->query("
    SELECT cargos.*
    FROM cargos

    INNER JOIN eleccion_cargos
    ON cargos.id = eleccion_cargos.id_cargo

    WHERE eleccion_cargos.id_eleccion = $idEleccion

    ORDER BY cargos.id ASC
");

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Resultados de la Elección</title>


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


/*=========================================
=          ENCABEZADO
=========================================*/

.encabezado {

    background:linear-gradient(
        135deg,
        #0d47a1,
        #1565c0
    );

    color:white;

    padding:30px;

    border-radius:0 0 20px 20px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.2);

}


/*=========================================
=          TARJETA
=========================================*/

.card-resultados {

    border:none;

    border-radius:15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.12);

}


/*=========================================
=          CANDIDATO
=========================================*/

.candidato {

    border:1px solid #dee2e6;

    border-radius:12px;

    padding:20px;

    margin-bottom:15px;

    background:white;

}


.votos {

    font-size:30px;

    font-weight:bold;

    color:#0d6efd;

}


.barra {

    height:25px;

    border-radius:10px;

}


.ganador {

    border:3px solid #198754;

    background:#f0fff7;

}


.medalla {

    font-size:25px;

}

</style>

</head>


<body>


<!--=======================================
    ENCABEZADO
========================================-->

<div class="encabezado">

<div class="container">

<div class="d-flex
justify-content-between
align-items-center">

<div>

<h1>

<i class="bi bi-bar-chart-fill"></i>

Resultados

</h1>

<h4>

<?php echo htmlspecialchars($eleccion['nombre']); ?>

</h4>

<p class="mb-0">

<?php echo htmlspecialchars($eleccion['descripcion']); ?>

</p>

</div>


<div>

<a
href="jurado.php"
class="btn btn-light me-2">

<i class="bi bi-arrow-left"></i>

Panel

</a>


<a
href="logout.php"
class="btn btn-danger">

<i class="bi bi-box-arrow-right"></i>

Salir

</a>

</div>

</div>

</div>

</div>


<!--=======================================
    CONTENIDO
========================================-->

<div class="container py-4">


<!-- TOTAL -->

<div class="card card-resultados mb-4">

<div class="card-body text-center">

<h5 class="text-muted">

Total de votos registrados

</h5>

<div class="votos">

<?php echo $totalVotos; ?>

</div>

</div>

</div>



<?php

/*=========================================
=          RECORRER CARGOS
=========================================*/

if ($cargos && $cargos->num_rows > 0) {

    while ($cargo = $cargos->fetch_assoc()) {

        $idCargo = (int)$cargo['id'];


        /*=========================================
        =          CANDIDATOS DEL CARGO
        =========================================*/

        $candidatos = $conn->query("

            SELECT

                candidatos.id,

                candidatos.nombre,

                candidatos.apellido,

                candidatos.curso,

                candidatos.foto,

                candidatos.numero_tarjeton,

                COUNT(votos.id) AS total_votos

            FROM candidatos

            LEFT JOIN votos

            ON candidatos.id = votos.id_candidato

            AND votos.id_eleccion = $idEleccion

            WHERE candidatos.id_eleccion = $idEleccion

            AND candidatos.id_cargo = $idCargo

            GROUP BY

                candidatos.id,

                candidatos.nombre,

                candidatos.apellido,

                candidatos.curso,

                candidatos.foto,

                candidatos.numero_tarjeton

            ORDER BY total_votos DESC

        ");


        /*=========================================
        =          CALCULAR TOTAL
        =========================================*/

        $totalCargo = 0;

        $listaCandidatos = [];

        if ($candidatos) {

            while ($candidato = $candidatos->fetch_assoc()) {

                $listaCandidatos[] = $candidato;

                $totalCargo += (int)$candidato['total_votos'];

            }

        }

        ?>


        <!--=======================================
            CARGO
        ========================================-->

        <div class="card card-resultados mb-5">

        <div class="card-header bg-primary text-white">

            <h3 class="mb-0">

                <i class="bi bi-person-lines-fill"></i>

                <?php
                echo htmlspecialchars(
                    $cargo['nombre_cargo']
                );
                ?>

            </h3>

        </div>


        <div class="card-body">


        <?php

        if (count($listaCandidatos) == 0) {

        ?>

            <div class="alert alert-warning">

                No existen candidatos para este cargo.

            </div>

        <?php

        } else {

            $posicion = 0;

            foreach ($listaCandidatos as $candidato) {

                $posicion++;

                $votosCandidato =
                    (int)$candidato['total_votos'];


                /*=================================
                =          PORCENTAJE
                =================================*/

                if ($totalCargo > 0) {

                    $porcentaje =
                        ($votosCandidato / $totalCargo) * 100;

                } else {

                    $porcentaje = 0;

                }


                $claseGanador = "";

                if ($posicion == 1 &&
                    $votosCandidato > 0) {

                    $claseGanador = "ganador";

                }

                ?>


                <div class="candidato
                <?php echo $claseGanador; ?>">


                <div class="row align-items-center">


                <!-- INFORMACIÓN -->

                <div class="col-md-7">

                <h4>

                <?php

                if ($posicion == 1 &&
                    $votosCandidato > 0) {

                    ?>

                    <span class="medalla">
                    🏆
                    </span>

                    <?php

                }

                ?>

                <?php

                echo htmlspecialchars(
                    $candidato['nombre']
                    . " "
                    . $candidato['apellido']
                );

                ?>

                </h4>


                <p class="mb-1">

                <strong>Curso:</strong>

                <?php

                echo htmlspecialchars(
                    $candidato['curso']
                );

                ?>

                </p>


                <p class="mb-1">

                <strong>Tarjetón:</strong>

                #

                <?php

                echo htmlspecialchars(
                    $candidato['numero_tarjeton']
                );

                ?>

                </p>


                <div class="progress barra mt-3">

                <div
                class="progress-bar"
                role="progressbar"
                style="width:
                <?php echo $porcentaje; ?>%;">

                <?php

                echo number_format(
                    $porcentaje,
                    1
                );

                ?>%

                </div>

                </div>

                </div>


                <!-- VOTOS -->

                <div
                class="col-md-5 text-center">

                <div class="votos">

                <?php echo $votosCandidato; ?>

                </div>

                <div class="text-muted">

                votos

                </div>

                </div>


                </div>

                </div>


                <?php

            }

        }

        ?>


        </div>


        <!-- TOTAL DEL CARGO -->

        <div class="card-footer text-end">

        <strong>

        Total de votos para este cargo:

        <?php echo $totalCargo; ?>

        </strong>

        </div>


        </div>


        <?php

    }

} else {

?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

Esta elección no tiene cargos asignados.

</div>


<?php

}

?>


<!--=======================================
    PIE
========================================-->

<div class="text-center mb-5">

<p class="text-muted">

Sistema de Votaciones Escolares

</p>

<small>

Sesión del jurado:
<strong>

<?php

echo htmlspecialchars(
    $_SESSION['nombre']
);

?>

</strong>

</small>

</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
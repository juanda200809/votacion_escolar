<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

require('fpdf/fpdf.php');
include("config/conexion.php");

/*=========================================
=      SI NO SE HA ENVIADO EL FORMULARIO
=========================================*/

if(!isset($_POST['generar'])){

    $elecciones = $conn->query("
        SELECT *
        FROM elecciones
        ORDER BY fecha_inicio DESC
    ");
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<title>Generar PDF</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#eef2f7;
}

.card{
    max-width:700px;
    margin:auto;
    margin-top:80px;
    box-shadow:0 5px 20px rgba(0,0,0,.15);
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="card-header bg-danger text-white">

<h3>📄 Generar PDF de Resultados</h3>

</div>

<div class="card-body">

<form method="POST">

<label class="form-label">

Seleccione la elección

</label>

<select
name="id_eleccion"
class="form-select"
required>

<option value="">

Seleccione...

</option>

<?php

while($e = $elecciones->fetch_assoc()){

?>

<option value="<?php echo $e['id']; ?>">

<?php echo $e['nombre']; ?>

</option>

<?php

}

?>

</select>

<br>

<button
type="submit"
name="generar"
class="btn btn-danger w-100">

Generar PDF

</button>

</form>

<br>

<a
href="admin.php"
class="btn btn-secondary w-100">

Volver al Panel

</a>

</div>

</div>

</div>

</body>

</html>

<?php

exit();

}

/*=========================================
=      ELECCIÓN SELECCIONADA
=========================================*/

$idEleccion = (int)$_POST['id_eleccion'];

$consulta = $conn->query("
SELECT *
FROM elecciones
WHERE id=$idEleccion
");

if($consulta->num_rows==0){

    die("La elección no existe.");

}

$eleccion = $consulta->fetch_assoc();
/*=========================================
=      CREAR EL PDF
=========================================*/

$pdf = new FPDF('P','mm','Letter');

$pdf->AliasNbPages();

$pdf->AddPage();

$pdf->SetAutoPageBreak(true,20);

/*=========================================
=      LOGO DEL COLEGIO
=========================================*/

if(file_exists("img/logo.png")){

    $pdf->Image("img/logo.png",15,10,22);

}

/*=========================================
=      TÍTULO
=========================================*/

$pdf->SetFont('Arial','B',16);

$pdf->Cell(
190,
10,
utf8_decode("SISTEMA DE VOTACIONES ESCOLARES"),
0,
1,
'C'
);

$pdf->SetFont('Arial','B',13);

$pdf->Cell(
190,
8,
utf8_decode($eleccion['nombre']),
0,
1,
'C'
);

$pdf->Ln(5);

/*=========================================
=      INFORMACIÓN DE LA ELECCIÓN
=========================================*/

$pdf->SetFont('Arial','B',10);

$pdf->SetFillColor(230,230,230);

$pdf->Cell(45,8,"Estado",1,0,'C',true);

$pdf->Cell(
145,
8,
utf8_decode(ucfirst($eleccion['estado'])),
1,
1
);

$pdf->Cell(45,8,"Fecha Inicio",1,0,'C',true);

$pdf->Cell(
145,
8,
$eleccion['fecha_inicio'],
1,
1
);

$pdf->Cell(45,8,"Fecha Fin",1,0,'C',true);

$pdf->Cell(
145,
8,
$eleccion['fecha_fin'],
1,
1
);

$pdf->Cell(45,8,"Generado",1,0,'C',true);

$pdf->Cell(
145,
8,
date("d/m/Y H:i"),
1,
1
);

$pdf->Ln(10);

/*=========================================
=      TOTAL DE VOTOS
=========================================*/

$totalVotos = $conn->query("
SELECT COUNT(*) total
FROM votos
WHERE id_eleccion=$idEleccion
")->fetch_assoc()['total'];

$pdf->SetFont('Arial','B',11);

$pdf->Cell(
190,
8,
utf8_decode("Total de votos registrados: ".$totalVotos),
0,
1
);

$pdf->Ln(5);
/*=========================================
=      CARGOS DE LA ELECCIÓN
=========================================*/

$cargos = $conn->query("

SELECT cargos.*

FROM cargos

INNER JOIN eleccion_cargos

ON cargos.id = eleccion_cargos.id_cargo

WHERE eleccion_cargos.id_eleccion = $idEleccion

ORDER BY cargos.id

");

while($cargo = $cargos->fetch_assoc()){

    /*=========================================
    =      TÍTULO DEL CARGO
    =========================================*/

    $pdf->SetFillColor(13,110,253);

    $pdf->SetTextColor(255,255,255);

    $pdf->SetFont('Arial','B',12);

    $pdf->Cell(
        190,
        9,
        utf8_decode($cargo['nombre_cargo']),
        1,
        1,
        'C',
        true
    );

    /*=========================================
    =      ENCABEZADO DE LA TABLA
    =========================================*/

    $pdf->SetTextColor(0,0,0);

    $pdf->SetFont('Arial','B',10);

    $pdf->SetFillColor(230,230,230);

    $pdf->Cell(20,8,"No.",1,0,'C',true);

    $pdf->Cell(80,8,"Candidato",1,0,'C',true);

    $pdf->Cell(30,8,"Curso",1,0,'C',true);

    $pdf->Cell(25,8,"Votos",1,0,'C',true);

    $pdf->Cell(35,8,"%",1,1,'C',true);

    /*=========================================
    =      TOTAL DE VOTOS DEL CARGO
    =========================================*/

    $totalCargo = $conn->query("

    SELECT COUNT(*) total

    FROM votos

    INNER JOIN candidatos

    ON votos.id_candidato = candidatos.id

    WHERE votos.id_eleccion = $idEleccion

    AND candidatos.id_cargo = ".$cargo['id']."

    ")->fetch_assoc()['total'];

    /*=========================================
    =      CANDIDATOS DEL CARGO
    =========================================*/

    $candidatos = $conn->query("

    SELECT

    candidatos.*,

    COUNT(votos.id) total

    FROM candidatos

    LEFT JOIN votos

    ON candidatos.id = votos.id_candidato

    AND votos.id_eleccion = $idEleccion

    WHERE candidatos.id_cargo = ".$cargo['id']."

    GROUP BY candidatos.id

    ORDER BY total DESC,
    candidatos.numero_tarjeton ASC

    ");
/*=========================================
=      IMPRIMIR CANDIDATOS
=========================================*/

$primero = true;

$nombreGanador = "";
$votosGanador = 0;

while($candidato = $candidatos->fetch_assoc()){

    if($primero){

        $pdf->SetFillColor(220,255,220);

        $nombreGanador = $candidato['nombre']." ".$candidato['apellido'];

        $votosGanador = $candidato['total'];

    }else{

        $pdf->SetFillColor(255,255,255);

    }

    if($totalCargo>0){

        $porcentaje = round(($candidato['total']/$totalCargo)*100,2);

    }else{

        $porcentaje = 0;

    }

    $pdf->SetFont('Arial','',10);

    /* Tarjetón */

    $pdf->Cell(
        20,
        8,
        $candidato['numero_tarjeton'],
        1,
        0,
        'C',
        true
    );

    /* Nombre */

    $pdf->Cell(
        80,
        8,
        utf8_decode($candidato['nombre']." ".$candidato['apellido']),
        1,
        0,
        'L',
        true
    );

    /* Curso */

    $pdf->Cell(
        30,
        8,
        utf8_decode($candidato['curso']),
        1,
        0,
        'C',
        true
    );

    /* Votos */

    $pdf->Cell(
        25,
        8,
        $candidato['total'],
        1,
        0,
        'C',
        true
    );

    /* Porcentaje */

    $pdf->Cell(
        35,
        8,
        $porcentaje." %",
        1,
        1,
        'C',
        true
    );

    $primero = false;

}

/*=========================================
=      GANADOR DEL CARGO
=========================================*/

$pdf->SetFont('Arial','B',10);

$pdf->SetFillColor(245,245,245);

$pdf->Cell(
190,
8,
utf8_decode("Ganador: ".$nombreGanador."  |  ".$votosGanador." votos"),
1,
1,
'C',
true
);

$pdf->Ln(8);
/*=========================================
=      FIN DEL CARGO
=========================================*/

$pdf->Ln(3);

} // <-- FIN DEL WHILE DE CARGOS

/*=========================================
=      RESUMEN GENERAL
=========================================*/

$pdf->SetFont('Arial','B',13);

$pdf->SetFillColor(220,220,220);

$pdf->Cell(
190,
10,
utf8_decode("RESUMEN GENERAL DE LA ELECCIÓN"),
1,
1,
'C',
true
);

$totalCandidatos = $conn->query("
SELECT COUNT(*) total
FROM candidatos
WHERE id_eleccion=$idEleccion
")->fetch_assoc()['total'];

$totalCargos = $conn->query("
SELECT COUNT(*) total
FROM eleccion_cargos
WHERE id_eleccion=$idEleccion
")->fetch_assoc()['total'];

$totalEstudiantes = $conn->query("
SELECT COUNT(*) total
FROM usuarios
WHERE rol='estudiante'
")->fetch_assoc()['total'];

$pdf->SetFont('Arial','',11);

$pdf->Cell(95,8,"Total de cargos",1,0);

$pdf->Cell(95,8,$totalCargos,1,1);

$pdf->Cell(95,8,"Total de candidatos",1,0);

$pdf->Cell(95,8,$totalCandidatos,1,1);

$pdf->Cell(95,8,"Total de estudiantes",1,0);

$pdf->Cell(95,8,$totalEstudiantes,1,1);

$pdf->Cell(95,8,"Total de votos",1,0);

$pdf->Cell(95,8,$totalVotos,1,1);

/*=========================================
=      PORCENTAJE DE PARTICIPACIÓN
=========================================*/

$participacion = 0;

if($totalEstudiantes>0){

    $participacion = round(($totalVotos/$totalEstudiantes)*100,2);

}

$pdf->Cell(95,8,utf8_decode("Participación"),1,0);

$pdf->Cell(95,8,$participacion." %",1,1);

$pdf->Ln(10);
/*=========================================
=      OBSERVACIONES
=========================================*/

$pdf->SetFont('Arial','B',11);

$pdf->Cell(
190,
8,
utf8_decode("Observaciones"),
0,
1
);

$pdf->SetFont('Arial','',10);

$pdf->MultiCell(
190,
6,
utf8_decode("Este documento fue generado automáticamente por el Sistema de Votaciones Escolares y contiene los resultados oficiales registrados en la plataforma.")
);

$pdf->Ln(10);

/*=========================================
=      FIRMAS
=========================================*/

$pdf->SetFont('Arial','B',10);

$pdf->Cell(80,8,"______________________________",0,0,'C');

$pdf->Cell(30,8,"",0,0);

$pdf->Cell(80,8,"______________________________",0,1,'C');

$pdf->Cell(80,6,utf8_decode("Rector(a)"),0,0,'C');

$pdf->Cell(30,6,"",0,0);

$pdf->Cell(80,6,utf8_decode("Comité Electoral"),0,1,'C');

$pdf->Ln(15);

$pdf->Cell(
190,
6,
utf8_decode("Fecha de generación: ".date("d/m/Y H:i:s")),
0,
1,
'C'
);

$pdf->Cell(
190,
6,
utf8_decode("Sistema de Votaciones Escolares"),
0,
1,
'C'
);

/*=========================================
=      MOSTRAR PDF
=========================================*/

$pdf->Output(
'I',
'Resultados_'.$idEleccion.'.pdf'
);

exit();
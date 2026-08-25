<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";

require_once "fpdf/fpdf.php";


/* =========================================================
   FUNCIÓN PARA TEXTO DEL PDF
========================================================= */

function textoPDF($texto)
{
    return utf8_decode(
        (string)$texto
    );
}


/* =========================================================
   SI NO SE HA ENVIADO EL FORMULARIO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['generar'])
) {


    /* =====================================================
       OBTENER ELECCIONES
    ===================================================== */

    $elecciones =
        $conn->query("

            SELECT
                id,
                nombre,
                descripcion,
                fecha_inicio,
                fecha_fin,
                estado

            FROM elecciones

            ORDER BY id DESC

        ");


    if (!$elecciones) {

        die(
            "No se pudieron consultar las elecciones."
        );

    }

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Generar PDF de resultados
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width: 800px;

    margin: auto;

    padding: 50px 20px;

}


.card-principal {

    border: none;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.10);

}


.encabezado {

    background:
        linear-gradient(
            135deg,
            #1453a3,
            #0d6efd
        );

    color: white;

    padding: 30px;

}


.encabezado h1 {

    margin: 0;

    font-weight: bold;

}


.cuerpo {

    background: white;

    padding: 35px;

}


.form-label {

    font-weight: bold;

    color: #334155;

}


.form-select {

    min-height: 48px;

    border-radius: 10px;

}


.btn {

    border-radius: 10px;

    font-weight: bold;

    padding: 12px 20px;

}


.info {

    background: #e8f1ff;

    border: 1px solid #b6d4fe;

    color: #084298;

    border-radius: 12px;

    padding: 18px;

}


</style>

</head>


<body>


<div class="contenedor">


<div class="card-principal">


<div class="encabezado">

<h1>

<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF de resultados

</h1>


<p class="mb-0 mt-2">

Genera un documento oficial con los resultados
de una elección.

</p>

</div>


<div class="cuerpo">


<div class="info mb-4">

<i class="bi bi-info-circle-fill"></i>


<strong>
Información:
</strong>


El documento incluirá candidatos,
votos, porcentajes, ganadores,
estadísticas generales y participación.

</div>


<form
method="POST">


<div class="mb-4">


<label
for="id_eleccion"
class="form-label">


<i class="bi bi-calendar-event-fill"></i>

Seleccione la elección


</label>


<select

name="id_eleccion"

id="id_eleccion"

class="form-select"

required>


<option value="">

Seleccione una elección...

</option>


<?php while (
    $eleccion =
    $elecciones->fetch_assoc()
) { ?>


<option

value="<?php

echo (int)$eleccion['id'];

?>">


<?php

echo htmlspecialchars(
    $eleccion['nombre']
);

?>


</option>


<?php } ?>


</select>


</div>


<div class="d-flex
            justify-content-between
            gap-2
            flex-wrap">


<a
href="admin.php"
class="btn btn-outline-secondary">


<i class="bi bi-arrow-left"></i>

Volver al panel


</a>


<button

type="submit"

name="generar"

class="btn btn-danger">


<i class="bi bi-file-earmark-pdf-fill"></i>

Generar PDF


</button>


</div>


</form>


</div>


</div>


</div>


</body>

</html>

<?php

exit();

}


/* =========================================================
   VALIDAR ELECCIÓN RECIBIDA
========================================================= */

$idEleccion =
    filter_var(
        $_POST['id_eleccion'] ?? 0,
        FILTER_VALIDATE_INT
    );


if (
    $idEleccion === false ||
    $idEleccion <= 0
) {

    die(
        "La elección seleccionada no es válida."
    );

}


/* =========================================================
   OBTENER ELECCIÓN
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado

        FROM elecciones

        WHERE id = ?

        LIMIT 1

    ");


if (!$stmt) {

    die(
        "No se pudo preparar la consulta de la elección."
    );

}


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    die(
        "La elección seleccionada no existe."
    );

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   CREAR PDF
========================================================= */

$pdf =
    new FPDF(
        'P',
        'mm',
        'Letter'
    );


$pdf->AliasNbPages();


$pdf->SetAutoPageBreak(
    true,
    20
);


$pdf->AddPage();


/* =========================================================
   LOGO
========================================================= */

if (
    file_exists(
        "img/logo.png"
    )
) {

    $pdf->Image(
        "img/logo.png",
        15,
        10,
        22
    );

}


/* =========================================================
   TÍTULO
========================================================= */

$pdf->SetTextColor(
    20,
    83,
    163
);


$pdf->SetFont(
    'Arial',
    'B',
    16
);


$pdf->Cell(
    190,
    10,
    textoPDF(
        "SISTEMA DE VOTACIONES ESCOLARES"
    ),
    0,
    1,
    'C'
);


$pdf->SetFont(
    'Arial',
    'B',
    13
);


$pdf->Cell(
    190,
    8,
    textoPDF(
        $eleccion['nombre']
    ),
    0,
    1,
    'C'
);


$pdf->Ln(5);


/* =========================================================
   INFORMACIÓN DE LA ELECCIÓN
========================================================= */

$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->SetFont(
    'Arial',
    'B',
    10
);


$pdf->SetFillColor(
    230,
    238,
    249
);


$pdf->Cell(
    45,
    8,
    "Estado",
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    145,
    8,
    textoPDF(
        ucfirst(
            $eleccion['estado']
        )
    ),
    1,
    1
);


$pdf->Cell(
    45,
    8,
    "Fecha Inicio",
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    145,
    8,
    $eleccion['fecha_inicio'],
    1,
    1
);


$pdf->Cell(
    45,
    8,
    "Fecha Fin",
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    145,
    8,
    $eleccion['fecha_fin'],
    1,
    1
);


$pdf->Cell(
    45,
    8,
    "Generado",
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    145,
    8,
    date(
        "d/m/Y H:i"
    ),
    1,
    1
);


$pdf->Ln(8);


/* =========================================================
   TOTAL DE VOTOS
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos

        WHERE id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$fila =
    $resultado->fetch_assoc();


$totalVotos =
    (int)$fila['total'];


$stmt->close();


$pdf->SetFont(
    'Arial',
    'B',
    11
);


$pdf->Cell(
    190,
    8,
    textoPDF(
        "Total de votos registrados: "
        .
        $totalVotos
    ),
    0,
    1
);


$pdf->Ln(5);


/* =========================================================
   CARGOS DE LA ELECCIÓN
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            c.id,
            c.nombre_cargo

        FROM cargos c

        INNER JOIN eleccion_cargos ec

            ON ec.id_cargo = c.id

        WHERE ec.id_eleccion = ?

        ORDER BY c.id ASC

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$cargos =
    $stmt->get_result();


$stmt->close();


/* =========================================================
   RECORRER CARGOS
========================================================= */

while (
    $cargo =
    $cargos->fetch_assoc()
) {


    $idCargo =
        (int)$cargo['id'];


    /* =====================================================
       TOTAL VOTOS DEL CARGO
    ===================================================== */

    $stmtTotal =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

            FROM votos

            WHERE id_eleccion = ?

            AND id_cargo = ?

        ");


    $stmtTotal->bind_param(
        "ii",
        $idEleccion,
        $idCargo
    );


    $stmtTotal->execute();


    $resultadoTotal =
        $stmtTotal->get_result();


    $filaTotal =
        $resultadoTotal->fetch_assoc();


    $totalCargo =
        (int)$filaTotal['total'];


    $stmtTotal->close();


    /* =====================================================
       CANDIDATOS DEL CARGO
    ===================================================== */

    $stmtCandidatos =
        $conn->prepare("

            SELECT

                c.id,
                c.nombre,
                c.apellido,
                c.curso,
                c.foto,

                COUNT(v.id) AS total

            FROM candidatos c

            LEFT JOIN votos v

                ON v.id_candidato = c.id

                AND v.id_eleccion = ?

                AND v.id_cargo = ?

            WHERE c.id_eleccion = ?

            AND c.id_cargo = ?

            GROUP BY

                c.id,
                c.nombre,
                c.apellido,
                c.curso,
                c.foto

            ORDER BY

                total DESC,
                c.apellido ASC,
                c.nombre ASC,
                c.id ASC

        ");


    $stmtCandidatos->bind_param(
        "iiii",
        $idEleccion,
        $idCargo,
        $idEleccion,
        $idCargo
    );


    $stmtCandidatos->execute();


    $resultadoCandidatos =
        $stmtCandidatos->get_result();


    $listaCandidatos = [];


    while (
        $candidato =
        $resultadoCandidatos->fetch_assoc()
    ) {

        $listaCandidatos[] =
            $candidato;

    }


    $stmtCandidatos->close();


    /* =====================================================
       TÍTULO DEL CARGO
    ===================================================== */

    $pdf->SetFillColor(
        20,
        83,
        163
    );


    $pdf->SetTextColor(
        255,
        255,
        255
    );


    $pdf->SetFont(
        'Arial',
        'B',
        12
    );


    $pdf->Cell(
        190,
        9,
        textoPDF(
            $cargo['nombre_cargo']
        ),
        1,
        1,
        'C',
        true
    );


    /* =====================================================
       ENCABEZADO
    ===================================================== */

    $pdf->SetTextColor(
        0,
        0,
        0
    );


    $pdf->SetFont(
        'Arial',
        'B',
        10
    );


    $pdf->SetFillColor(
        230,
        230,
        230
    );


    /*
     * Ahora "No." es simplemente
     * una numeración automática.
     *
     * Ya NO depende de numero_tarjeton.
     */

    $pdf->Cell(
        15,
        8,
        "No.",
        1,
        0,
        'C',
        true
    );


    $pdf->Cell(
        80,
        8,
        "Candidato",
        1,
        0,
        'C',
        true
    );


    $pdf->Cell(
        30,
        8,
        "Curso",
        1,
        0,
        'C',
        true
    );


    $pdf->Cell(
        25,
        8,
        "Votos",
        1,
        0,
        'C',
        true
    );


    $pdf->Cell(
        40,
        8,
        "%",
        1,
        1,
        'C',
        true
    );


    /* =====================================================
       MAYOR VOTACIÓN
    ===================================================== */

    $mayorCantidad =
        0;


    foreach (
        $listaCandidatos
        as $candidato
    ) {

        $cantidad =
            (int)$candidato['total'];


        if (
            $cantidad >
            $mayorCantidad
        ) {

            $mayorCantidad =
                $cantidad;

        }

    }


    /* =====================================================
       IMPRIMIR CANDIDATOS
    ===================================================== */

    $numero =
        1;


    foreach (
        $listaCandidatos
        as $candidato
    ) {


        $votosCandidato =
            (int)$candidato['total'];


        $porcentaje =
            0;


        if (
            $totalCargo > 0
        ) {

            $porcentaje =
                round(
                    (
                        $votosCandidato /
                        $totalCargo
                    ) * 100,
                    2
                );

        }


        $esGanador =
            (
                $mayorCantidad > 0 &&
                $votosCandidato ===
                $mayorCantidad
            );


        /* =================================================
           COLOR DEL GANADOR
        ================================================= */

        if (
            $esGanador
        ) {

            $pdf->SetFillColor(
                220,
                255,
                220
            );

        } else {

            $pdf->SetFillColor(
                255,
                255,
                255
            );

        }


        $pdf->SetFont(
            'Arial',
            '',
            9
        );


        /* =================================================
           NÚMERO
        ================================================= */

        $pdf->Cell(
            15,
            8,
            $numero,
            1,
            0,
            'C',
            true
        );


        /* =================================================
           NOMBRE
        ================================================= */

        $nombreCompleto =
            $candidato['nombre']
            .
            " "
            .
            $candidato['apellido'];


        $pdf->Cell(
            80,
            8,
            textoPDF(
                $nombreCompleto
            ),
            1,
            0,
            'L',
            true
        );


        /* =================================================
           CURSO
        ================================================= */

        $pdf->Cell(
            30,
            8,
            textoPDF(
                $candidato['curso']
            ),
            1,
            0,
            'C',
            true
        );


        /* =================================================
           VOTOS
        ================================================= */

        $pdf->Cell(
            25,
            8,
            $votosCandidato,
            1,
            0,
            'C',
            true
        );


        /* =================================================
           PORCENTAJE
        ================================================= */

        $pdf->Cell(
            40,
            8,
            $porcentaje . " %",
            1,
            1,
            'C',
            true
        );


        $numero++;

    }


    /* =====================================================
       GANADOR
    ===================================================== */

    $pdf->SetFont(
        'Arial',
        'B',
        10
    );


    $pdf->SetFillColor(
        245,
        245,
        245
    );


    if (
        count($listaCandidatos) === 0
    ) {

        $textoGanador =
            "No hay candidatos registrados.";

    }


    elseif (
        $mayorCantidad <= 0
    ) {

        $textoGanador =
            "Sin ganador: no se han registrado votos.";

    }


    else {


        $ganadores = [];


        foreach (
            $listaCandidatos
            as $candidato
        ) {

            if (
                (int)$candidato['total']
                ===
                $mayorCantidad
            ) {

                $ganadores[] =
                    $candidato['nombre']
                    .
                    " "
                    .
                    $candidato['apellido'];

            }

        }


        if (
            count($ganadores) === 1
        ) {

            $textoGanador =
                "Ganador: "
                .
                $ganadores[0]
                .
                " | "
                .
                $mayorCantidad
                .
                " votos";

        } else {

            $textoGanador =
                "Empate entre: "
                .
                implode(
                    ", ",
                    $ganadores
                )
                .
                " | "
                .
                $mayorCantidad
                .
                " votos";

        }

    }


    $pdf->Cell(
        190,
        8,
        textoPDF(
            $textoGanador
        ),
        1,
        1,
        'C',
        true
    );


    $pdf->Ln(8);

}


/* =========================================================
   RESUMEN GENERAL
========================================================= */

$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->SetFont(
    'Arial',
    'B',
    13
);


$pdf->SetFillColor(
    220,
    220,
    220
);


$pdf->Cell(
    190,
    10,
    textoPDF(
        "RESUMEN GENERAL DE LA ELECCIÓN"
    ),
    1,
    1,
    'C',
    true
);


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM candidatos

        WHERE id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$totalCandidatos =
    (int)$resultado
        ->fetch_assoc()['total'];


$stmt->close();


/* =========================================================
   TOTAL CARGOS
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM eleccion_cargos

        WHERE id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$totalCargos =
    (int)$resultado
        ->fetch_assoc()['total'];


$stmt->close();


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultado =
    $conn->query("

        SELECT
            COUNT(*) AS total

        FROM usuarios

        WHERE rol = 'estudiante'

    ");


$totalEstudiantes =
    (int)$resultado
        ->fetch_assoc()['total'];


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion =
    0;


if (
    $totalEstudiantes > 0
) {

    $participacion =
        round(
            (
                $totalVotos /
                $totalEstudiantes
            ) * 100,
            2
        );

}


/* =========================================================
   TABLA RESUMEN
========================================================= */

$pdf->SetFont(
    'Arial',
    '',
    11
);


$pdf->Cell(
    95,
    8,
    "Total de cargos",
    1,
    0
);


$pdf->Cell(
    95,
    8,
    $totalCargos,
    1,
    1
);


$pdf->Cell(
    95,
    8,
    "Total de candidatos",
    1,
    0
);


$pdf->Cell(
    95,
    8,
    $totalCandidatos,
    1,
    1
);


$pdf->Cell(
    95,
    8,
    "Total de estudiantes",
    1,
    0
);


$pdf->Cell(
    95,
    8,
    $totalEstudiantes,
    1,
    1
);


$pdf->Cell(
    95,
    8,
    "Total de votos",
    1,
    0
);


$pdf->Cell(
    95,
    8,
    $totalVotos,
    1,
    1
);


$pdf->Cell(
    95,
    8,
    textoPDF(
        "Participación"
    ),
    1,
    0
);


$pdf->Cell(
    95,
    8,
    $participacion . " %",
    1,
    1
);


$pdf->Ln(10);


/* =========================================================
   OBSERVACIONES
========================================================= */

$pdf->SetFont(
    'Arial',
    'B',
    11
);


$pdf->Cell(
    190,
    8,
    textoPDF(
        "Observaciones"
    ),
    0,
    1
);


$pdf->SetFont(
    'Arial',
    '',
    10
);


$pdf->MultiCell(
    190,
    6,
    textoPDF(
        "Este documento fue generado automáticamente "
        .
        "por el Sistema de Votaciones Escolares y "
        .
        "contiene los resultados registrados en la "
        .
        "plataforma para la elección seleccionada."
    )
);


$pdf->Ln(10);


/* =========================================================
   FIRMAS
========================================================= */

$pdf->SetFont(
    'Arial',
    'B',
    10
);


$pdf->Cell(
    80,
    8,
    "______________________________",
    0,
    0,
    'C'
);


$pdf->Cell(
    30,
    8,
    "",
    0,
    0
);


$pdf->Cell(
    80,
    8,
    "______________________________",
    0,
    1,
    'C'
);


$pdf->Cell(
    80,
    6,
    textoPDF(
        "Rector(a)"
    ),
    0,
    0,
    'C'
);


$pdf->Cell(
    30,
    6,
    "",
    0,
    0
);


$pdf->Cell(
    80,
    6,
    textoPDF(
        "Comité Electoral"
    ),
    0,
    1,
    'C'
);


$pdf->Ln(15);


/* =========================================================
   PIE
========================================================= */

$pdf->SetFont(
    'Arial',
    '',
    9
);


$pdf->Cell(
    190,
    6,
    textoPDF(
        "Fecha de generación: "
        .
        date(
            "d/m/Y H:i:s"
        )
    ),
    0,
    1,
    'C'
);


$pdf->Cell(
    190,
    6,
    textoPDF(
        "Sistema de Votaciones Escolares"
    ),
    0,
    1,
    'C'
);


/* =========================================================
   MOSTRAR PDF
========================================================= */

$pdf->Output(
    'I',
    'Resultados_' .
    $idEleccion .
    '.pdf'
);


exit();

?>
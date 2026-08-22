<?php

session_start();


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   CONEXIÓN
========================================================= */

include("config/conexion.php");


/* =========================================================
   FPDF
========================================================= */

require_once("fpdf/fpdf.php");


/* =========================================================
   SI NO SE HA SELECCIONADO ELECCIÓN
========================================================= */

if (!isset($_POST['generar'])) {

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

        <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

        <title>
            Generar PDF
        </title>


        <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


        <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


        <style>

            body {

                background:#eef2f7;

            }


            .card {

                max-width:700px;

                margin:80px auto;

                box-shadow:
                    0 5px 20px
                    rgba(0,0,0,.15);

                border:0;

                border-radius:15px;

            }


            .card-header {

                border-radius:
                    15px 15px 0 0 !important;

            }

        </style>

    </head>


    <body>


        <div class="container">


            <div class="card">


                <div class="card-header bg-danger text-white">

                    <h3 class="mb-0">

                        <i class="bi bi-file-earmark-pdf-fill"></i>

                        Generar PDF de Resultados

                    </h3>

                </div>


                <div class="card-body p-4">


                    <?php

                    if (
                        $elecciones &&
                        $elecciones->num_rows > 0
                    ) {

                    ?>

                        <form method="POST">


                            <div class="mb-3">

                                <label
                                class="form-label fw-bold">

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

                                    while (
                                        $e =
                                        $elecciones->fetch_assoc()
                                    ) {

                                    ?>

                                        <option
                                        value="<?php echo (int)$e['id']; ?>">

                                            <?php

                                            echo htmlspecialchars(
                                                $e['nombre']
                                            );

                                            ?>

                                        </option>

                                    <?php

                                    }

                                    ?>

                                </select>

                            </div>


                            <button
                            type="submit"
                            name="generar"
                            class="btn btn-danger w-100">

                                <i class="bi bi-file-earmark-pdf-fill"></i>

                                Generar PDF

                            </button>


                        </form>


                    <?php

                    } else {

                    ?>

                        <div class="alert alert-warning">

                            <i class="bi bi-info-circle-fill"></i>

                            No hay elecciones registradas.

                        </div>

                    <?php

                    }

                    ?>


                    <div class="mt-3">

                        <a
                        href="admin.php"
                        class="btn btn-secondary w-100">

                            <i class="bi bi-arrow-left"></i>

                            Volver al Panel

                        </a>

                    </div>


                </div>

            </div>

        </div>


    </body>

    </html>

    <?php

    exit();

}


/* =========================================================
   VALIDAR ELECCIÓN
========================================================= */

if (
    !isset($_POST['id_eleccion']) ||
    !is_numeric($_POST['id_eleccion'])
) {

    die("Debe seleccionar una elección.");

}


$idEleccion =
    (int)$_POST['id_eleccion'];


if ($idEleccion <= 0) {

    die("Elección no válida.");

}


/* =========================================================
   BUSCAR ELECCIÓN
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

    die("La elección no existe.");

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
    file_exists("img/logo.png")
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

$pdf->SetFont(
    'Arial',
    'B',
    16
);


$pdf->Cell(
    190,
    10,
    utf8_decode(
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
    utf8_decode(
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


$pdf->Cell(
    45,
    8,
    "Estado",
    1,
    0,
    'C',
    true
);


$pdf->SetFont(
    'Arial',
    '',
    10
);


$pdf->Cell(
    145,
    8,
    utf8_decode(
        ucfirst($eleccion['estado'])
    ),
    1,
    1
);


$pdf->SetFont(
    'Arial',
    'B',
    10
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


$pdf->SetFont(
    'Arial',
    '',
    10
);


$pdf->Cell(
    145,
    8,
    $eleccion['fecha_inicio'],
    1,
    1
);


$pdf->SetFont(
    'Arial',
    'B',
    10
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


$pdf->SetFont(
    'Arial',
    '',
    10
);


$pdf->Cell(
    145,
    8,
    $eleccion['fecha_fin'],
    1,
    1
);


$pdf->SetFont(
    'Arial',
    'B',
    10
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


$pdf->SetFont(
    'Arial',
    '',
    10
);


$pdf->Cell(
    145,
    8,
    date("d/m/Y H:i"),
    1,
    1
);


$pdf->Ln(10);


/* =========================================================
   TOTAL DE VOTOS
   IMPORTANTE:
   votos NO usa id_eleccion.
   Se relaciona con candidatos.
========================================================= */

$stmt =
    $conn->prepare("

        SELECT COUNT(*) AS total

        FROM votos v

        INNER JOIN candidatos c
            ON v.id_candidato = c.id

        WHERE c.id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$totalVotos =
    (int)$stmt
        ->get_result()
        ->fetch_assoc()['total'];


$stmt->close();


$pdf->SetFont(
    'Arial',
    'B',
    11
);


$pdf->Cell(
    190,
    8,
    utf8_decode(
        "Total de votos registrados: "
        . $totalVotos
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
            cargos.id,
            cargos.nombre_cargo

        FROM cargos

        INNER JOIN eleccion_cargos
            ON cargos.id =
               eleccion_cargos.id_cargo

        WHERE eleccion_cargos.id_eleccion = ?

        ORDER BY cargos.id

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$cargos =
    $stmt->get_result();


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
       TÍTULO DEL CARGO
    ===================================================== */

    $pdf->SetFillColor(
        13,
        110,
        253
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
        utf8_decode(
            $cargo['nombre_cargo']
        ),
        1,
        1,
        'C',
        true
    );


    /* =====================================================
       ENCABEZADO TABLA
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


    $pdf->Cell(
        20,
        8,
        "Tarjeton",
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
        35,
        8,
        "%",
        1,
        1,
        'C',
        true
    );


    /* =====================================================
       TOTAL VOTOS DEL CARGO
    ===================================================== */

    $stmtTotalCargo =
        $conn->prepare("

            SELECT COUNT(*) AS total

            FROM votos v

            INNER JOIN candidatos c
                ON v.id_candidato = c.id

            WHERE c.id_eleccion = ?

            AND c.id_cargo = ?

        ");


    $stmtTotalCargo->bind_param(
        "ii",
        $idEleccion,
        $idCargo
    );


    $stmtTotalCargo->execute();


    $totalCargo =
        (int)$stmtTotalCargo
            ->get_result()
            ->fetch_assoc()['total'];


    $stmtTotalCargo->close();


    /* =====================================================
       CANDIDATOS
    ===================================================== */

    $stmtCandidatos =
        $conn->prepare("

            SELECT

                c.id,

                c.nombre,

                c.apellido,

                c.curso,

                c.numero_tarjeton,

                COUNT(v.id) AS total

            FROM candidatos c

            LEFT JOIN votos v
                ON c.id = v.id_candidato

            WHERE c.id_eleccion = ?

            AND c.id_cargo = ?

            GROUP BY
                c.id,
                c.nombre,
                c.apellido,
                c.curso,
                c.numero_tarjeton

            ORDER BY
                total DESC,
                c.numero_tarjeton ASC

        ");


    $stmtCandidatos->bind_param(
        "ii",
        $idEleccion,
        $idCargo
    );


    $stmtCandidatos->execute();


    $candidatos =
        $stmtCandidatos->get_result();


    /* =====================================================
       VARIABLES GANADOR
    ===================================================== */

    $nombreGanador = "";

    $votosGanador = 0;

    $hayGanador = false;


    /* =====================================================
       IMPRIMIR CANDIDATOS
    ===================================================== */

    while (
        $candidato =
        $candidatos->fetch_assoc()
    ) {


        $votos =
            (int)$candidato['total'];


        /* ===============================================
           PORCENTAJE
        =============================================== */

        if (
            $totalCargo > 0
        ) {

            $porcentaje =
                round(
                    ($votos / $totalCargo) * 100,
                    2
                );

        } else {

            $porcentaje = 0;

        }


        /* ===============================================
           GANADOR
        =============================================== */

        if (
            !$hayGanador &&
            $votos > 0
        ) {

            $nombreGanador =
                $candidato['nombre']
                . " "
                .
                $candidato['apellido'];


            $votosGanador =
                $votos;


            $hayGanador = true;

        }


        /* ===============================================
           COLOR FILA
        =============================================== */

        if (
            $hayGanador &&
            $nombreGanador ===
            (
                $candidato['nombre']
                . " "
                .
                $candidato['apellido']
            ) &&
            $votos === $votosGanador
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
            10
        );


        /* TARJETÓN */

        $pdf->Cell(
            20,
            8,
            $candidato['numero_tarjeton'],
            1,
            0,
            'C',
            true
        );


        /* NOMBRE */

        $pdf->Cell(
            80,
            8,
            utf8_decode(
                $candidato['nombre']
                . " "
                .
                $candidato['apellido']
            ),
            1,
            0,
            'L',
            true
        );


        /* CURSO */

        $pdf->Cell(
            30,
            8,
            utf8_decode(
                $candidato['curso']
            ),
            1,
            0,
            'C',
            true
        );


        /* VOTOS */

        $pdf->Cell(
            25,
            8,
            $votos,
            1,
            0,
            'C',
            true
        );


        /* PORCENTAJE */

        $pdf->Cell(
            35,
            8,
            $porcentaje . " %",
            1,
            1,
            'C',
            true
        );

    }


    $stmtCandidatos->close();


    /* =====================================================
       MOSTRAR GANADOR
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


    if ($hayGanador) {

        $textoGanador =
            "Ganador: "
            .
            $nombreGanador
            .
            " | "
            .
            $votosGanador
            .
            " votos";

    } else {

        $textoGanador =
            "Ganador: No hay ganador. No se han registrado votos.";

    }


    $pdf->Cell(
        190,
        8,
        utf8_decode(
            $textoGanador
        ),
        1,
        1,
        'C',
        true
    );


    $pdf->Ln(8);

}


$stmt->close();


/* =========================================================
   RESUMEN GENERAL
========================================================= */

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
    utf8_decode(
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

        SELECT COUNT(*) AS total

        FROM candidatos

        WHERE id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$totalCandidatos =
    (int)$stmt
        ->get_result()
        ->fetch_assoc()['total'];


$stmt->close();


/* =========================================================
   TOTAL CARGOS
========================================================= */

$stmt =
    $conn->prepare("

        SELECT COUNT(*) AS total

        FROM eleccion_cargos

        WHERE id_eleccion = ?

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$totalCargos =
    (int)$stmt
        ->get_result()
        ->fetch_assoc()['total'];


$stmt->close();


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultado =
    $conn->query("

        SELECT COUNT(*) AS total

        FROM usuarios

        WHERE rol = 'estudiante'

    ");


$totalEstudiantes =
    (int)$resultado
        ->fetch_assoc()['total'];


/* =========================================================
   RESUMEN
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


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion = 0;


if (
    $totalEstudiantes > 0
) {

    $participacion =
        round(
            (
                $totalVotos
                /
                $totalEstudiantes
            ) * 100,
            2
        );

}


$pdf->Cell(
    95,
    8,
    utf8_decode(
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
    utf8_decode(
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
    utf8_decode(
        "Este documento fue generado automáticamente por el Sistema de Votaciones Escolares y contiene los resultados registrados en la plataforma."
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
    utf8_decode("Rector(a)"),
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
    utf8_decode("Comité Electoral"),
    0,
    1,
    'C'
);


$pdf->Ln(15);


/* =========================================================
   PIE
========================================================= */

$pdf->Cell(
    190,
    6,
    utf8_decode(
        "Fecha de generación: "
        .
        date("d/m/Y H:i:s")
    ),
    0,
    1,
    'C'
);


$pdf->Cell(
    190,
    6,
    utf8_decode(
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
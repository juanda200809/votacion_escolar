<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   CARGAR PHPSPREADSHEET
========================================= */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;


/* =========================================
   CREAR LIBRO DE EXCEL
========================================= */

$spreadsheet = new Spreadsheet();

$hoja = $spreadsheet->getActiveSheet();

$hoja->setTitle("Estudiantes");


/* =========================================
   ENCABEZADOS
========================================= */

$hoja->setCellValue("A1", "Documento");
$hoja->setCellValue("B1", "Nombre");
$hoja->setCellValue("C1", "Apellido");
$hoja->setCellValue("D1", "Curso");


/* =========================================
   ESTILO DE ENCABEZADOS
========================================= */

$estiloEncabezado = [
    'font' => [
        'bold' => true,
        'color' => [
            'rgb' => 'FFFFFF'
        ]
    ],

    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '0D47A1'
        ]
    ],

    'alignment' => [
        'horizontal' =>
            Alignment::HORIZONTAL_CENTER,

        'vertical' =>
            Alignment::VERTICAL_CENTER
    ],

    'borders' => [
        'allBorders' => [
            'borderStyle' =>
                Border::BORDER_THIN
        ]
    ]
];


$hoja
    ->getStyle("A1:D1")
    ->applyFromArray($estiloEncabezado);


/* =========================================
   ALTURA DEL ENCABEZADO
========================================= */

$hoja
    ->getRowDimension(1)
    ->setRowHeight(25);


/* =========================================
   CONSULTAR ESTUDIANTES
========================================= */

$consulta = $conn->query("
    SELECT
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE rol = 'estudiante'
    ORDER BY nombre ASC, apellido ASC
");


if (!$consulta) {

    die(
        "Error al consultar estudiantes: " .
        $conn->error
    );

}


/* =========================================
   AGREGAR ESTUDIANTES
========================================= */

$fila = 2;


while ($estudiante = $consulta->fetch_assoc()) {


    $hoja->setCellValue(
        "A" . $fila,
        $estudiante['documento']
    );


    $hoja->setCellValue(
        "B" . $fila,
        $estudiante['nombre']
    );


    $hoja->setCellValue(
        "C" . $fila,
        $estudiante['apellido']
    );


    $hoja->setCellValue(
        "D" . $fila,
        $estudiante['curso']
    );


    $fila++;
}


/* =========================================
   BORDES DE LA TABLA
========================================= */

if ($fila > 2) {

    $hoja
        ->getStyle("A1:D" . ($fila - 1))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(
            Border::BORDER_THIN
        );

}


/* =========================================
   AJUSTAR COLUMNAS
========================================= */

foreach (range('A', 'D') as $columna) {

    $hoja
        ->getColumnDimension($columna)
        ->setAutoSize(true);

}


/* =========================================
   FORMATO DOCUMENTO
========================================= */

/*
   Tratamos el documento como texto para
   evitar que Excel cambie su formato.
*/

if ($fila > 2) {

    $hoja
        ->getStyle("A2:A" . ($fila - 1))
        ->getNumberFormat()
        ->setFormatCode('@');

}


/* =========================================
   CONGELAR ENCABEZADO
========================================= */

$hoja->freezePane("A2");


/* =========================================
   CREAR NOMBRE DEL ARCHIVO
========================================= */

$nombreArchivo =
    "estudiantes_" .
    date("Y-m-d_H-i-s") .
    ".xlsx";


/* =========================================
   LIMPIAR BUFFER
========================================= */

if (ob_get_length()) {

    ob_end_clean();

}


/* =========================================
   CABECERAS HTTP
========================================= */

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="' .
    $nombreArchivo .
    '"'
);

header(
    'Cache-Control: max-age=0'
);

header(
    'Cache-Control: max-age=1'
);

header(
    'Expires: Mon, 26 Jul 1997 05:00:00 GMT'
);


/* =========================================
   GENERAR EXCEL
========================================= */

$writer = new Xlsx($spreadsheet);

$writer->save("php://output");

exit();

?>
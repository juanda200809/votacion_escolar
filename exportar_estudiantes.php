<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
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

$rutasComposer = [

    __DIR__ . "/vendor/autoload.php",

    dirname(__DIR__) . "/vendor/autoload.php"

];

$composerEncontrado = false;

foreach ($rutasComposer as $ruta) {

    if (file_exists($ruta)) {

        require_once $ruta;

        $composerEncontrado = true;

        break;

    }

}


if (!$composerEncontrado) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>
                No se encontró PhpSpreadsheet
            </h2>

            <p>
                Verifica que hayas instalado la librería
                correctamente con Composer.
            </p>

            <code>
                composer require phpoffice/phpspreadsheet
            </code>

        </div>
    ");

}


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


/* =========================================
   OBTENER ESTUDIANTES
========================================= */

$sql = "
    SELECT
        documento,
        nombre,
        apellido,
        curso,
        fecha_registro
    FROM usuarios
    WHERE rol = 'estudiante'
    ORDER BY nombre ASC, apellido ASC
";


$resultado = $conn->query($sql);


if (!$resultado) {

    die(
        "Error al consultar los estudiantes: "
        . $conn->error
    );

}


/* =========================================
   CREAR ARCHIVO EXCEL
========================================= */

$spreadsheet = new Spreadsheet();

$hoja = $spreadsheet->getActiveSheet();


/* =========================================
   NOMBRE DE LA HOJA
========================================= */

$hoja->setTitle(
    "Estudiantes"
);


/* =========================================
   TÍTULO
========================================= */

$hoja->mergeCells(
    "A1:E1"
);

$hoja->setCellValue(
    "A1",
    "LISTADO DE ESTUDIANTES"
);


/* =========================================
   ENCABEZADOS
========================================= */

$hoja->setCellValue(
    "A3",
    "Documento"
);

$hoja->setCellValue(
    "B3",
    "Nombre"
);

$hoja->setCellValue(
    "C3",
    "Apellido"
);

$hoja->setCellValue(
    "D3",
    "Curso"
);

$hoja->setCellValue(
    "E3",
    "Fecha de registro"
);


/* =========================================
   ESTILO DEL TÍTULO
========================================= */

$hoja->getStyle("A1:E1")
    ->getFont()
    ->setBold(true);

$hoja->getStyle("A1:E1")
    ->getFont()
    ->setSize(16);

$hoja->getStyle("A1:E1")
    ->getAlignment()
    ->setHorizontal("center");

$hoja->getStyle("A1:E1")
    ->getAlignment()
    ->setVertical("center");


/* =========================================
   ESTILO ENCABEZADOS
========================================= */

$hoja->getStyle("A3:E3")
    ->getFont()
    ->setBold(true);

$hoja->getStyle("A3:E3")
    ->getAlignment()
    ->setHorizontal("center");


/* =========================================
   AGREGAR ESTUDIANTES
========================================= */

$fila = 4;

while ($estudiante = $resultado->fetch_assoc()) {

    /*
     * Documento como texto.
     *
     * Esto evita que Excel convierta
     * documentos largos en números
     * científicos.
     */

    $hoja->setCellValueExplicit(
        "A" . $fila,
        $estudiante['documento'],
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
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


    $hoja->setCellValue(
        "E" . $fila,
        $estudiante['fecha_registro']
    );


    $fila++;

}


/* =========================================
   BORDES
========================================= */

if ($fila > 4) {

    $hoja->getStyle(
        "A3:E" . ($fila - 1)
    )
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );

}


/* =========================================
   AJUSTAR COLUMNAS
========================================= */

foreach (
    range("A", "E")
    as $columna
) {

    $hoja
        ->getColumnDimension($columna)
        ->setAutoSize(true);

}


/* =========================================
   CONGELAR ENCABEZADO
========================================= */

$hoja->freezePane("A4");


/* =========================================
   CONFIGURAR DESCARGA
========================================= */

$fecha =
    date("Y-m-d_H-i-s");


$nombreArchivo =
    "estudiantes_" . $fecha . ".xlsx";


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
    "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
);

header(
    "Content-Disposition: attachment; filename=\"" .
    $nombreArchivo .
    "\""
);

header(
    "Cache-Control: max-age=0"
);

header(
    "Expires: 0"
);

header(
    "Pragma: public"
);


/* =========================================
   GENERAR EXCEL
========================================= */

$writer =
    new Xlsx($spreadsheet);


$writer->save("php://output");


exit();

?>
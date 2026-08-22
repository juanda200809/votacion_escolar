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

    dirname(__DIR__) .
    "/vendor/autoload.php"

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
                con Composer.
            </p>

            <code>
                composer require phpoffice/phpspreadsheet
            </code>

        </div>
    ");

}


use PhpOffice\PhpSpreadsheet\IOFactory;


/* =========================================
   VARIABLES
========================================= */

$mensaje = "";

$tipoMensaje = "";

$insertados = 0;

$duplicados = 0;

$errores = 0;


/* =========================================
   IMPORTAR EXCEL
========================================= */

if (
    isset($_POST['importar']) &&
    isset($_FILES['archivo_excel'])
) {


    $archivo =
        $_FILES['archivo_excel'];


    /* =====================================
       COMPROBAR ERROR DE SUBIDA
    ===================================== */

    if (
        $archivo['error'] !==
        UPLOAD_ERR_OK
    ) {

        $mensaje =
            "No se pudo subir el archivo.";

        $tipoMensaje = "danger";

    } else {


        /* =================================
           EXTENSIÓN
        ================================= */

        $nombreArchivo =
            $archivo['name'];

        $extension =
            strtolower(
                pathinfo(
                    $nombreArchivo,
                    PATHINFO_EXTENSION
                )
            );


        $extensionesPermitidas = [
            "xlsx",
            "xls",
            "csv"
        ];


        if (
            !in_array(
                $extension,
                $extensionesPermitidas,
                true
            )
        ) {

            $mensaje =
                "El archivo debe ser XLSX, XLS o CSV.";

            $tipoMensaje = "danger";

        } else {


            try {


                /* =============================
                   LEER ARCHIVO
                ============================= */

                $documento =
                    IOFactory::load(
                        $archivo['tmp_name']
                    );


                $hoja =
                    $documento->getActiveSheet();


                $filas =
                    $hoja->toArray(
                        null,
                        true,
                        true,
                        true
                    );


                if (
                    count($filas) <= 1
                ) {

                    throw new Exception(
                        "El archivo no contiene estudiantes."
                    );

                }


                /* =============================
                   RECORRER FILAS
                ============================= */

                $numeroFila = 0;


                foreach ($filas as $fila) {

                    $numeroFila++;


                    /* =========================
                       OMITIR ENCABEZADO
                    ========================= */

                    if ($numeroFila === 1) {

                        continue;

                    }


                    /* =========================
                       COLUMNAS ESPERADAS
                       
                       A = Documento
                       B = Nombre
                       C = Apellido
                       D = Curso
                    ========================= */

                    $documento =
                        trim(
                            (string)(
                                $fila['A'] ?? ''
                            )
                        );


                    $nombre =
                        trim(
                            (string)(
                                $fila['B'] ?? ''
                            )
                        );


                    $apellido =
                        trim(
                            (string)(
                                $fila['C'] ?? ''
                            )
                        );


                    $curso =
                        trim(
                            (string)(
                                $fila['D'] ?? ''
                            )
                        );


                    /* =========================
                       FILA VACÍA
                    ========================= */

                    if (
                        $documento === "" &&
                        $nombre === "" &&
                        $apellido === "" &&
                        $curso === ""
                    ) {

                        continue;

                    }


                    /* =========================
                       VALIDAR CAMPOS
                    ========================= */

                    if (
                        $documento === "" ||
                        $nombre === "" ||
                        $apellido === "" ||
                        $curso === ""
                    ) {

                        $errores++;

                        continue;

                    }


                    /* =========================
                       COMPROBAR DOCUMENTO
                    ========================= */

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM usuarios
                        WHERE documento = ?
                        LIMIT 1
                    ");


                    if (!$stmt) {

                        $errores++;

                        continue;

                    }


                    $stmt->bind_param(
                        "s",
                        $documento
                    );


                    $stmt->execute();


                    $resultado =
                        $stmt->get_result();


                    if (
                        $resultado->num_rows > 0
                    ) {

                        $duplicados++;

                        $stmt->close();

                        continue;

                    }


                    $stmt->close();


                    /* =========================
                       CONTRASEÑA
                       
                       DOCUMENTO
                    ========================= */

                    $password =
                        password_hash(
                            $documento,
                            PASSWORD_DEFAULT
                        );


                    $rol =
                        "estudiante";


                    /* =========================
                       INSERTAR ESTUDIANTE
                    ========================= */

                    $stmt = $conn->prepare("
                        INSERT INTO usuarios
                        (
                            documento,
                            nombre,
                            apellido,
                            curso,
                            password,
                            rol,
                            fecha_registro
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, NOW())
                    ");


                    if (!$stmt) {

                        $errores++;

                        continue;

                    }


                    $stmt->bind_param(
                        "ssssss",
                        $documento,
                        $nombre,
                        $apellido,
                        $curso,
                        $password,
                        $rol
                    );


                    if ($stmt->execute()) {

                        $insertados++;

                    } else {

                        $errores++;

                    }


                    $stmt->close();

                }


                /* =============================
                   MENSAJE FINAL
                ============================= */

                $mensaje =
                    "Importación terminada.";

                $tipoMensaje =
                    "success";


            } catch (Exception $e) {

                $mensaje =
                    "Error al procesar el archivo: " .
                    $e->getMessage();

                $tipoMensaje =
                    "danger";

            }

        }

    }

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
Importar Estudiantes
</title>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =========================================
     ICONOS
========================================= -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width:850px;

    margin:auto;

    padding:45px 20px;

}


.card {

    border:none;

    border-radius:20px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.cabecera {

    background:#1453a3;

    color:white;

    padding:25px;

    border-radius:
        20px 20px 0 0;

}


.zona-subida {

    border:
        2px dashed #0d6efd;

    border-radius:15px;

    padding:40px 25px;

    text-align:center;

    background:#f8fbff;

}


.icono-excel {

    font-size:70px;

    color:#198754;

}


.btn-importar {

    padding:13px 30px;

    font-size:17px;

}


.info {

    background:#eef5ff;

    border:
        1px solid #b6d4fe;

    border-radius:12px;

    padding:18px;

}


.tabla-ejemplo {

    font-size:14px;

}


.tabla-ejemplo th {

    background:#cfe0ff;

    color:#084298;

}


</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<!-- =========================================
     CABECERA
========================================= -->

<div class="cabecera">

<div
class="d-flex
       justify-content-between
       align-items-center
       flex-wrap
       gap-3">


<div>

<h3 class="mb-1">

<i
class="bi bi-file-earmark-arrow-up-fill">
</i>

Importar Estudiantes

</h3>


<p class="mb-0">

Carga estudiantes mediante Excel.

</p>

</div>


<a
href="admin.php"
class="btn btn-light">

<i class="bi bi-arrow-left"></i>

Volver

</a>


</div>

</div>


<div class="card-body p-4">


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div
class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     RESULTADOS
========================================= -->

<?php if (
    $insertados > 0 ||
    $duplicados > 0 ||
    $errores > 0
) { ?>


<div class="row g-3 mb-4">


<div class="col-md-4">

<div class="alert alert-success text-center mb-0">

<i
class="bi bi-check-circle-fill fs-3">
</i>

<br>

<strong>

<?php echo $insertados; ?>

</strong>

<br>

Estudiantes importados

</div>

</div>


<div class="col-md-4">

<div class="alert alert-warning text-center mb-0">

<i
class="bi bi-exclamation-triangle-fill fs-3">
</i>

<br>

<strong>

<?php echo $duplicados; ?>

</strong>

<br>

Documentos duplicados

</div>

</div>


<div class="col-md-4">

<div class="alert alert-danger text-center mb-0">

<i
class="bi bi-x-circle-fill fs-3">
</i>

<br>

<strong>

<?php echo $errores; ?>

</strong>

<br>

Filas con errores

</div>

</div>


</div>


<?php } ?>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="info mb-4">

<h5>

<i class="bi bi-info-circle-fill"></i>

Formato del archivo

</h5>


<p class="mb-2">

El Excel debe tener estas columnas
en este orden:

</p>


<div class="table-responsive">

<table
class="table table-bordered tabla-ejemplo mb-2">

<thead>

<tr>

<th>A</th>

<th>B</th>

<th>C</th>

<th>D</th>

</tr>

</thead>


<tbody>

<tr>

<td>Documento</td>

<td>Nombre</td>

<td>Apellido</td>

<td>Curso</td>

</tr>


<tr>

<td>1000000003</td>

<td>Juan</td>

<td>Gómez</td>

<td>1101</td>

</tr>


<tr>

<td>1000000004</td>

<td>María</td>

<td>Rodríguez</td>

<td>1102</td>

</tr>

</tbody>

</table>

</div>


<p class="mb-0">

<strong>Contraseña:</strong>

se genera automáticamente usando
el documento del estudiante.

</p>

</div>


<!-- =========================================
     FORMULARIO
========================================= -->

<form
method="POST"
enctype="multipart/form-data">


<div class="zona-subida">


<div class="icono-excel">

<i class="bi bi-file-earmark-excel-fill"></i>

</div>


<h4 class="mt-3">

Seleccionar archivo Excel

</h4>


<p class="text-muted">

Formatos permitidos:

<strong>
.xlsx
</strong>,
<strong>
.xls
</strong>
o
<strong>
.csv
</strong>

</p>


<input

type="file"

name="archivo_excel"

id="archivo_excel"

class="form-control
       form-control-lg
       mt-4"

accept=".xlsx,.xls,.csv"

required>


<div
id="nombreArchivo"
class="mt-3 text-primary fw-bold">

</div>


</div>


<!-- =========================================
     BOTONES
========================================= -->

<div class="d-flex
            justify-content-center
            gap-2
            flex-wrap
            mt-4">


<a
href="estudiantes.php"
class="btn btn-outline-secondary">

<i class="bi bi-people-fill"></i>

Ver estudiantes

</a>


<button

type="submit"

name="importar"

class="btn btn-success btn-importar">

<i
class="bi bi-cloud-arrow-up-fill">
</i>

Importar estudiantes

</button>


</div>


</form>


<hr class="my-4">


<!-- =========================================
     EXPORTAR
========================================= -->

<div class="text-center">


<h5>

¿Necesitas una plantilla?

</h5>


<p class="text-muted">

Puedes exportar los estudiantes actuales
para utilizarlos como referencia.

</p>


<a
href="exportar_estudiantes.php"
class="btn btn-outline-success">

<i
class="bi bi-file-earmark-excel-fill">
</i>

Exportar estudiantes

</a>


</div>


</div>

</div>


<!-- =========================================
     FOOTER
========================================= -->

<div class="text-center
            text-muted
            mt-4">

Sistema de Votaciones Escolares

</div>


</div>


<!-- =========================================
     JAVASCRIPT
========================================= -->

<script>

const archivo =
    document.getElementById(
        "archivo_excel"
    );


const nombreArchivo =
    document.getElementById(
        "nombreArchivo"
    );


archivo.addEventListener(
    "change",
    function() {

        if (
            this.files.length > 0
        ) {

            nombreArchivo.innerHTML =
                "<i class='bi bi-file-earmark-check-fill'></i> " +
                this.files[0].name;

        } else {

            nombreArchivo.innerHTML =
                "";

        }

    }
);

</script>


</body>

</html>
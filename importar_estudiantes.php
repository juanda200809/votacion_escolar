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

include("config/conexion.php");

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$mensaje = "";
$tipoMensaje = "";

$importados = 0;
$duplicados = 0;
$errores = 0;


/* =========================================
   IMPORTAR EXCEL
========================================= */

if (isset($_POST['importar'])) {

    if (
        !isset($_FILES['archivo']) ||
        $_FILES['archivo']['error'] !== UPLOAD_ERR_OK
    ) {

        $mensaje = "Debe seleccionar un archivo Excel.";
        $tipoMensaje = "danger";

    } else {

        $archivo = $_FILES['archivo']['tmp_name'];
        $nombreArchivo = $_FILES['archivo']['name'];

        $extension = strtolower(
            pathinfo($nombreArchivo, PATHINFO_EXTENSION)
        );

        /* =========================================
           VALIDAR EXTENSIÓN
        ========================================= */

        if (
            $extension !== 'xlsx' &&
            $extension !== 'xls'
        ) {

            $mensaje =
                "El archivo debe tener formato .xlsx o .xls.";

            $tipoMensaje = "danger";

        } else {

            try {

                /* =========================================
                   LEER EXCEL
                ========================================= */

                $excel = IOFactory::load($archivo);

                $hoja = $excel->getActiveSheet();

                $filas = $hoja->toArray(
                    null,
                    true,
                    true,
                    true
                );


                /* =========================================
                   RECORRER FILAS
                ========================================= */

                foreach ($filas as $numero => $fila) {

                    /*
                        La primera fila se considera
                        encabezado.
                    */

                    if ($numero === 1) {
                        continue;
                    }


                    $documento = trim(
                        (string)($fila['A'] ?? '')
                    );

                    $nombre = trim(
                        (string)($fila['B'] ?? '')
                    );

                    $apellido = trim(
                        (string)($fila['C'] ?? '')
                    );

                    $curso = trim(
                        (string)($fila['D'] ?? '')
                    );


                    /* =====================================
                       IGNORAR FILAS COMPLETAMENTE VACÍAS
                    ===================================== */

                    if (
                        $documento === '' &&
                        $nombre === '' &&
                        $apellido === '' &&
                        $curso === ''
                    ) {
                        continue;
                    }


                    /* =====================================
                       VALIDAR DATOS
                    ===================================== */

                    if (
                        $documento === '' ||
                        $nombre === '' ||
                        $apellido === '' ||
                        $curso === ''
                    ) {

                        $errores++;

                        continue;
                    }


                    /* =====================================
                       VERIFICAR DOCUMENTO EXISTENTE
                    ===================================== */

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM usuarios
                        WHERE documento = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param(
                        "s",
                        $documento
                    );

                    $stmt->execute();

                    $resultado = $stmt->get_result();

                    if ($resultado->num_rows > 0) {

                        $duplicados++;

                        $stmt->close();

                        continue;
                    }

                    $stmt->close();


                    /* =====================================
                       CONTRASEÑA AUTOMÁTICA
                    ===================================== */

                    $password_hash = password_hash(
                        $documento,
                        PASSWORD_DEFAULT
                    );

                    $correo = "";


                    /* =====================================
                       INSERTAR ESTUDIANTE
                    ===================================== */

                    $stmt = $conn->prepare("
                        INSERT INTO usuarios
                        (
                            documento,
                            nombre,
                            apellido,
                            correo,
                            curso,
                            password,
                            rol
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, 'estudiante')
                    ");

                    $stmt->bind_param(
                        "ssssss",
                        $documento,
                        $nombre,
                        $apellido,
                        $correo,
                        $curso,
                        $password_hash
                    );


                    if ($stmt->execute()) {

                        $importados++;

                    } else {

                        $errores++;
                    }

                    $stmt->close();
                }


                /* =========================================
                   MENSAJE FINAL
                ========================================= */

                $mensaje =
                    "Importación completada. " .
                    "Importados: " . $importados .
                    " | Duplicados: " . $duplicados .
                    " | Errores: " . $errores;

                if ($errores > 0) {

                    $tipoMensaje = "warning";

                } else {

                    $tipoMensaje = "success";
                }

            } catch (Throwable $e) {

                $mensaje =
                    "No fue posible procesar el archivo Excel.";

                $tipoMensaje = "danger";
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Importar Estudiantes</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

    min-height:100vh;

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:900px;

    margin:40px auto;

}


/* =========================================
   TARJETA
========================================= */

.card-importar {

    border:none;

    border-radius:20px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.12);

}


/* =========================================
   CABECERA
========================================= */

.encabezado {

    background:#0d47a1;

    color:white;

    border-radius:
        20px 20px 0 0;

    padding:25px;

}


.icono-excel {

    font-size:55px;

}


/* =========================================
   FORMATO
========================================= */

.formato {

    background:#eef5ff;

    border-left:5px solid #0d6efd;

    border-radius:10px;

    padding:20px;

}


/* =========================================
   BOTONES
========================================= */

.btn-accion {

    border-radius:10px;

    font-weight:600;

    padding:11px 18px;

}


/* =========================================
   PASOS
========================================= */

.paso {

    text-align:center;

    padding:20px 10px;

}


.paso-icono {

    font-size:38px;

    color:#0d6efd;

}


/* =========================================
   ALERTA
========================================= */

.alert {

    border-radius:10px;

}

</style>

</head>


<body>


<div class="container contenedor">


<div class="card card-importar">


<!-- =========================================
     ENCABEZADO
========================================= -->

<div class="encabezado">


<div class="d-flex
            align-items-center
            gap-3">


<div class="icono-excel">

<i class="bi bi-file-earmark-excel-fill"></i>

</div>


<div>

<h2 class="mb-1">

Importar estudiantes

</h2>

<p class="mb-0">

Cargar varios estudiantes desde un archivo Excel.

</p>

</div>


</div>

</div>


<div class="card-body p-4">


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo htmlspecialchars($tipoMensaje); ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     FORMATO EXCEL
========================================= -->

<div class="formato mb-4">


<h5>

<i class="bi bi-info-circle-fill text-primary"></i>

Formato del archivo

</h5>


<p>

El Excel debe tener estas columnas,
en este orden:

</p>


<div class="table-responsive">


<table class="table table-bordered bg-white">


<thead class="table-primary">

<tr>

<th>A</th>

<th>B</th>

<th>C</th>

<th>D</th>

</tr>

</thead>


<tbody>

<tr>

<td><strong>Documento</strong></td>

<td><strong>Nombre</strong></td>

<td><strong>Apellido</strong></td>

<td><strong>Curso</strong></td>

</tr>

</tbody>


</table>


</div>


<div class="alert alert-info mb-0">

<i class="bi bi-key-fill"></i>

La contraseña se creará automáticamente
y será igual al documento del estudiante.

</div>


</div>


<!-- =========================================
     PASOS
========================================= -->

<div class="row mb-4">


<div class="col-md-4 paso">

<div class="paso-icono">

<i class="bi bi-file-earmark-excel"></i>

</div>

<strong>1. Preparar Excel</strong>

<p class="text-muted small">

Documento, nombre, apellido y curso.

</p>

</div>


<div class="col-md-4 paso">

<div class="paso-icono">

<i class="bi bi-upload"></i>

</div>

<strong>2. Seleccionar</strong>

<p class="text-muted small">

Seleccione el archivo desde su computador.

</p>

</div>


<div class="col-md-4 paso">

<div class="paso-icono">

<i class="bi bi-person-check"></i>

</div>

<strong>3. Importar</strong>

<p class="text-muted small">

Los estudiantes serán registrados.

</p>

</div>


</div>


<!-- =========================================
     FORMULARIO
========================================= -->

<form
method="POST"
enctype="multipart/form-data">


<div class="mb-4">


<label class="form-label">

<strong>

<i class="bi bi-file-earmark-arrow-up"></i>

Seleccionar archivo Excel

</strong>

</label>


<input
type="file"
name="archivo"
class="form-control form-control-lg"
accept=".xlsx,.xls"
required>


<div class="form-text">

Formatos permitidos: Excel .xlsx y .xls

</div>


</div>


<button
type="submit"
name="importar"
class="btn btn-success btn-lg w-100 btn-accion">

<i class="bi bi-upload"></i>

Importar estudiantes

</button>


</form>


<hr class="my-4">


<!-- =========================================
     BOTONES
========================================= -->

<div class="d-flex
            justify-content-center
            gap-2
            flex-wrap">


<a
href="estudiantes.php"
class="btn btn-outline-primary btn-accion">

<i class="bi bi-people-fill"></i>

Estudiantes

</a>


<a
href="exportar_estudiantes.php"
class="btn btn-outline-success btn-accion">

<i class="bi bi-file-earmark-excel-fill"></i>

Exportar Excel

</a>


<a
href="admin.php"
class="btn btn-outline-dark btn-accion">

<i class="bi bi-house-fill"></i>

Panel Admin

</a>


</div>


</div>

</div>


<!-- =========================================
     INFORMACIÓN
========================================= -->

<div class="text-center text-muted mt-4">

<small>

Sistema de Votaciones Escolares v2.0

</small>

</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
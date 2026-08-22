<?php

session_start();

/* =====================================================
   VERIFICAR SESIÓN DEL ADMINISTRADOR
===================================================== */

if (
    !isset($_SESSION['id']) ||
    $_SESSION['rol'] != 'administrador'
) {

    header("Location: login.php");
    exit();

}

include("config/conexion.php");


/* =====================================================
   EXPORTAR ESTUDIANTES
===================================================== */

if (isset($_GET['exportar'])) {

    $consulta = $conn->query("
        SELECT documento, nombre, apellido, curso
        FROM usuarios
        WHERE rol='estudiante'
        ORDER BY nombre ASC
    ");

    header('Content-Type: text/csv; charset=utf-8');

    header(
        'Content-Disposition: attachment; filename="estudiantes.csv"'
    );

    $salida = fopen('php://output', 'w');

    /*
       BOM para que Excel reconozca correctamente
       las tildes y caracteres especiales
    */

    fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv(
        $salida,
        array(
            'Documento',
            'Nombre',
            'Apellido',
            'Curso'
        ),
        ';'
    );

    while ($fila = $consulta->fetch_assoc()) {

        fputcsv(
            $salida,
            array(
                $fila['documento'],
                $fila['nombre'],
                $fila['apellido'],
                $fila['curso']
            ),
            ';'
        );

    }

    fclose($salida);

    exit();

}


/* =====================================================
   VARIABLES
===================================================== */

$mensaje = "";
$tipoMensaje = "";


/* =====================================================
   REGISTRAR ESTUDIANTE
===================================================== */

if (isset($_POST['guardar'])) {

    $documento = trim($_POST['documento']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);


    if (
        $documento == "" ||
        $nombre == "" ||
        $apellido == "" ||
        $curso == ""
    ) {

        $mensaje =
            "Debe completar todos los campos.";

        $tipoMensaje = "danger";

    } else {

        /* =============================================
           VERIFICAR DOCUMENTO
        ============================================= */

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

            $mensaje =
                "Ya existe un usuario con ese documento.";

            $tipoMensaje = "warning";

        } else {

            /*
               CONTRASEÑA AUTOMÁTICA
               = DOCUMENTO
            */

            $passwordHash =
                password_hash(
                    $documento,
                    PASSWORD_DEFAULT
                );


            /* =========================================
               INSERTAR ESTUDIANTE
            ========================================= */

            $stmt = $conn->prepare("
                INSERT INTO usuarios
                (
                    documento,
                    nombre,
                    apellido,
                    curso,
                    password,
                    rol
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'estudiante'
                )
            ");

            $stmt->bind_param(
                "sssss",
                $documento,
                $nombre,
                $apellido,
                $curso,
                $passwordHash
            );


            if ($stmt->execute()) {

                $mensaje =
                    "Estudiante registrado correctamente. "
                    . "Su contraseña es su documento.";

                $tipoMensaje = "success";

            } else {

                $mensaje =
                    "Error al registrar el estudiante: "
                    . $conn->error;

                $tipoMensaje = "danger";

            }

        }

    }

}


/* =====================================================
   IMPORTAR ESTUDIANTES
===================================================== */

if (isset($_POST['importar'])) {

    if (
        !isset($_FILES['archivo']) ||
        $_FILES['archivo']['error'] != 0
    ) {

        $mensaje =
            "Debe seleccionar un archivo.";

        $tipoMensaje = "danger";

    } else {

        $archivo = $_FILES['archivo']['tmp_name'];

        $extension =
            strtolower(
                pathinfo(
                    $_FILES['archivo']['name'],
                    PATHINFO_EXTENSION
                )
            );


        /*
           Para evitar depender de librerías externas,
           aceptamos CSV, que Excel abre directamente.
        */

        if ($extension != "csv") {

            $mensaje =
                "El archivo debe estar en formato CSV. "
                . "Puedes abrirlo y guardarlo desde Excel "
                . "como CSV UTF-8.";

            $tipoMensaje = "warning";

        } else {

            $handle = fopen($archivo, "r");

            if ($handle === false) {

                $mensaje =
                    "No se pudo abrir el archivo.";

                $tipoMensaje = "danger";

            } else {

                /*
                   Leer primera fila
                   Encabezados:
                   Documento | Nombre | Apellido | Curso
                */

                $encabezado = fgetcsv(
                    $handle,
                    1000,
                    ";"
                );

                $importados = 0;
                $duplicados = 0;
                $errores = 0;


                while (
                    ($fila = fgetcsv(
                        $handle,
                        1000,
                        ";"
                    )) !== false
                ) {

                    if (count($fila) < 4) {

                        $errores++;

                        continue;

                    }


                    $documento =
                        trim($fila[0]);

                    $nombre =
                        trim($fila[1]);

                    $apellido =
                        trim($fila[2]);

                    $curso =
                        trim($fila[3]);


                    if (
                        $documento == "" ||
                        $nombre == "" ||
                        $apellido == "" ||
                        $curso == ""
                    ) {

                        $errores++;

                        continue;

                    }


                    /*
                       VERIFICAR SI YA EXISTE
                    */

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

                    $resultado =
                        $stmt->get_result();


                    if (
                        $resultado->num_rows > 0
                    ) {

                        $duplicados++;

                        continue;

                    }


                    /*
                       CONTRASEÑA AUTOMÁTICA
                       = DOCUMENTO
                    */

                    $passwordHash =
                        password_hash(
                            $documento,
                            PASSWORD_DEFAULT
                        );


                    /*
                       INSERTAR
                    */

                    $stmt = $conn->prepare("
                        INSERT INTO usuarios
                        (
                            documento,
                            nombre,
                            apellido,
                            curso,
                            password,
                            rol
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'estudiante'
                        )
                    ");

                    $stmt->bind_param(
                        "sssss",
                        $documento,
                        $nombre,
                        $apellido,
                        $curso,
                        $passwordHash
                    );


                    if ($stmt->execute()) {

                        $importados++;

                    } else {

                        $errores++;

                    }

                }


                fclose($handle);


                $mensaje =
                    "Importación terminada. "
                    . "Importados: $importados | "
                    . "Duplicados: $duplicados | "
                    . "Errores: $errores";

                $tipoMensaje = "success";

            }

        }

    }

}


/* =====================================================
   ELIMINAR ESTUDIANTE
===================================================== */

if (isset($_GET['eliminar'])) {

    $id =
        intval($_GET['eliminar']);


    $stmt = $conn->prepare("
        DELETE FROM usuarios
        WHERE id = ?
        AND rol = 'estudiante'
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();


    header(
        "Location: estudiantes.php"
    );

    exit();

}


/* =====================================================
   CONSULTAR ESTUDIANTES
===================================================== */

$estudiantes = $conn->query("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE rol='estudiante'
    ORDER BY nombre ASC
");


$total = $estudiantes->num_rows;

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Gestión de Estudiantes</title>


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


.card-form {

    border:none;

    border-radius:15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,.15);

}


.table {

    background:white;

}


.buscar {

    max-width:350px;

}


.boton-excel {

    border-radius:10px;

}


.info-password {

    background:#dff6ff;

    border-radius:10px;

    padding:12px;

    color:#075985;

    margin-bottom:20px;

}


.titulo {

    color:#0d47a1;

}


</style>

</head>


<body>


<div class="container-fluid py-4 px-4">


<!-- =================================================
     ENCABEZADO
================================================= -->

<div
class="d-flex
       justify-content-between
       align-items-center
       mb-4">


<div>

<h2 class="titulo">

<i class="bi bi-people-fill"></i>

Gestión de Estudiantes

</h2>

<p class="text-muted mb-0">

Administra los estudiantes del sistema

</p>

</div>


<a
href="admin.php"
class="btn btn-primary">

<i class="bi bi-arrow-left-circle"></i>

Volver al Panel

</a>


</div>


<!-- =================================================
     MENSAJES
================================================= -->

<?php if ($mensaje != "") { ?>

<div
class="alert
       alert-<?php echo $tipoMensaje; ?>
       alert-dismissible fade show">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>


<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php } ?>


<div class="row g-4">


<!-- =================================================
     FORMULARIO
================================================= -->

<div class="col-lg-4">

<div class="card card-form">

<div class="card-body p-4">


<h3 class="mb-4">

<i class="bi bi-person-plus-fill"></i>

Registrar estudiante

</h3>


<form method="POST">


<div class="mb-3">

<label class="form-label fw-bold">

Documento

</label>

<input
type="text"
name="documento"
class="form-control"
placeholder="Documento del estudiante"
required>

</div>


<div class="mb-3">

<label class="form-label fw-bold">

Nombre

</label>

<input
type="text"
name="nombre"
class="form-control"
placeholder="Nombre"
required>

</div>


<div class="mb-3">

<label class="form-label fw-bold">

Apellido

</label>

<input
type="text"
name="apellido"
class="form-control"
placeholder="Apellido"
required>

</div>


<div class="mb-3">

<label class="form-label fw-bold">

Curso

</label>

<input
type="text"
name="curso"
class="form-control"
placeholder="Ejemplo: 1104"
required>

</div>


<!-- =================================================
     CONTRASEÑA AUTOMÁTICA
================================================= -->

<div class="info-password">

<i class="bi bi-key-fill"></i>

<strong>
Contraseña automática:
</strong>

el documento del estudiante.

<br>

<small>

Ejemplo: Documento <strong>1072709874</strong>
→ Contraseña <strong>1072709874</strong>

</small>

</div>


<button
type="submit"
name="guardar"
class="btn btn-success w-100">

<i class="bi bi-person-plus-fill"></i>

Guardar estudiante

</button>


</form>


<hr class="my-4">


<!-- =================================================
     IMPORTAR
================================================= -->

<h5>

<i class="bi bi-upload"></i>

Importar estudiantes

</h5>


<p class="text-muted">

Puedes importar varios estudiantes
al mismo tiempo.

</p>


<form
method="POST"
enctype="multipart/form-data">


<div class="mb-3">

<input
type="file"
name="archivo"
class="form-control"
accept=".csv"
required>

</div>


<button
type="submit"
name="importar"
class="btn btn-success w-100 boton-excel">

<i class="bi bi-file-earmark-spreadsheet-fill"></i>

Importar estudiantes

</button>


</form>


<small class="text-muted d-block mt-2">

Formato: CSV separado por punto y coma.

</small>


<!-- =================================================
     EXPORTAR
================================================= -->

<a
href="estudiantes.php?exportar=1"
class="btn btn-outline-success w-100 mt-3 boton-excel">

<i class="bi bi-download"></i>

Exportar estudiantes

</a>


</div>

</div>

</div>


<!-- =================================================
     LISTA
================================================= -->

<div class="col-lg-8">

<div class="card card-form">

<div class="card-body p-4">


<div
class="d-flex
       justify-content-between
       align-items-center
       mb-3">


<h3>

<i class="bi bi-list-ul"></i>

Lista de estudiantes

</h3>


<span
class="badge bg-primary fs-6">

Total:

<?php echo $total; ?>

</span>


</div>


<!-- =================================================
     BOTONES EXCEL
================================================= -->

<div class="mb-3">


<a
href="estudiantes.php?exportar=1"
class="btn btn-success">

<i class="bi bi-file-earmark-excel"></i>

Exportar Excel

</a>


<button
type="button"
class="btn btn-outline-success"
onclick="document.getElementById('archivo').click();">

<i class="bi bi-upload"></i>

Importar Excel

</button>


</div>


<!-- INPUT OCULTO PARA IMPORTAR -->

<form
method="POST"
enctype="multipart/form-data"
id="formImportar">


<input
type="file"
name="archivo"
id="archivo"
accept=".csv"
style="display:none;"
onchange="document.getElementById('formImportar').submit();">


<input
type="hidden"
name="importar"
value="1">


</form>


<!-- =================================================
     BUSCADOR
================================================= -->

<input
type="text"
id="buscar"
class="form-control buscar mb-3"
placeholder="🔍 Buscar estudiante...">


<!-- =================================================
     TABLA
================================================= -->

<div class="table-responsive">

<table
class="table
       table-bordered
       table-hover
       align-middle">


<thead class="table-primary">

<tr>

<th>ID</th>

<th>Documento</th>

<th>Nombre</th>

<th>Apellido</th>

<th>Curso</th>

<th>Acciones</th>

</tr>

</thead>


<tbody id="tablaEstudiantes">


<?php

while (
    $e =
    $estudiantes->fetch_assoc()
) {

?>

<tr>


<td>

<?php echo $e['id']; ?>

</td>


<td>

<?php
echo htmlspecialchars(
    $e['documento']
);
?>

</td>


<td>

<?php
echo htmlspecialchars(
    $e['nombre']
);
?>

</td>


<td>

<?php
echo htmlspecialchars(
    $e['apellido']
);
?>

</td>


<td>

<?php
echo htmlspecialchars(
    $e['curso']
);
?>

</td>


<td>


<a
href="editar_estudiante.php?id=<?php echo $e['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-square"></i>

Editar

</a>


<a
href="estudiantes.php?eliminar=<?php echo $e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Está seguro de eliminar este estudiante?');">

<i class="bi bi-trash-fill"></i>

Eliminar

</a>


</td>


</tr>

<?php

}

?>


</tbody>

</table>

</div>


</div>

</div>

</div>


</div>


</div>


<!-- =================================================
     BUSCADOR
================================================= -->

<script>

const buscar =
document.getElementById("buscar");


buscar.addEventListener(
    "keyup",
    function() {

        let texto =
            this.value.toLowerCase();


        let filas =
            document.querySelectorAll(
                "#tablaEstudiantes tr"
            );


        filas.forEach(
            function(fila) {

                let contenido =
                    fila.textContent
                    .toLowerCase();


                if (
                    contenido.indexOf(texto)
                    > -1
                ) {

                    fila.style.display = "";

                } else {

                    fila.style.display =
                        "none";

                }

            }
        );

    }
);

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
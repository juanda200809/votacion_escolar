<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR QUE SEA JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {

    header("Location: login.php");
    exit();

}


$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    header("Location: login.php");
    exit();

}


/* =========================================================
   OBTENER ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;

$stmtEleccion = $conn->prepare("

    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado

    FROM elecciones

    WHERE LOWER(TRIM(estado)) = 'abierta'

    ORDER BY id DESC

    LIMIT 1

");


if ($stmtEleccion) {

    $stmtEleccion->execute();

    $resultadoEleccion =
        $stmtEleccion->get_result();

    if (
        $resultadoEleccion->num_rows > 0
    ) {

        $eleccion =
            $resultadoEleccion->fetch_assoc();

    }

    $stmtEleccion->close();

}


/* =========================================================
   VARIABLES
========================================================= */

$busqueda = "";

$mensaje = "";

$tipoMensaje = "";

$resultados = [];


/* =========================================================
   BUSCAR ESTUDIANTES
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buscar'])
) {

    $busqueda =
        trim(
            $_POST['busqueda'] ?? ""
        );


    if (
        $eleccion === null
    ) {

        $mensaje =
            "No hay una elección abierta actualmente.";

        $tipoMensaje =
            "warning";

    }

    elseif (
        $busqueda === ""
    ) {

        $mensaje =
            "Escriba un documento, nombre, apellido o curso.";

        $tipoMensaje =
            "danger";

    }

    else {


        /* =================================================
           BUSCAR ESTUDIANTES
        ================================================= */

        $textoBusqueda =
            "%" . $busqueda . "%";


        $stmt =
            $conn->prepare("

                SELECT

                    u.id,

                    u.documento,

                    u.nombre,

                    u.apellido,

                    u.curso,

                    CASE

                        WHEN EXISTS (

                            SELECT 1

                            FROM votos v

                            INNER JOIN candidatos c
                                ON c.id = v.id_candidato

                            WHERE v.id_usuario = u.id

                            AND c.id_eleccion = ?

                        )

                        THEN 1

                        ELSE 0

                    END AS ya_voto

                FROM usuarios u

                WHERE LOWER(TRIM(u.rol))
                    = 'estudiante'

                AND (

                    u.documento LIKE ?

                    OR u.nombre LIKE ?

                    OR u.apellido LIKE ?

                    OR u.curso LIKE ?

                    OR CONCAT(
                        u.nombre,
                        ' ',
                        u.apellido
                    ) LIKE ?

                )

                ORDER BY
                    u.nombre ASC,
                    u.apellido ASC

                LIMIT 100

            ");


        if (!$stmt) {

            $mensaje =
                "No se pudo realizar la búsqueda.";

            $tipoMensaje =
                "danger";

        }

        else {


            $stmt->bind_param(

                "isssss",

                $eleccion['id'],

                $textoBusqueda,

                $textoBusqueda,

                $textoBusqueda,

                $textoBusqueda,

                $textoBusqueda

            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            while (
                $estudiante =
                $resultado->fetch_assoc()
            ) {

                $resultados[] =
                    $estudiante;

            }


            $stmt->close();


            if (
                count($resultados) === 0
            ) {

                $mensaje =
                    "No se encontraron estudiantes con esa búsqueda.";

                $tipoMensaje =
                    "warning";

            }

        }

    }

}


/* =========================================================
   SELECCIONAR ESTUDIANTE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['seleccionar_estudiante'])
) {

    $idEstudiante =
        (int)(
            $_POST['id_estudiante'] ?? 0
        );


    if (
        $idEstudiante <= 0
    ) {

        $mensaje =
            "Estudiante no válido.";

        $tipoMensaje =
            "danger";

    }

    elseif (
        $eleccion === null
    ) {

        $mensaje =
            "No hay una elección abierta.";

        $tipoMensaje =
            "warning";

    }

    else {


        /* =================================================
           BUSCAR ESTUDIANTE
        ================================================= */

        $stmt =
            $conn->prepare("

                SELECT

                    id,

                    documento,

                    nombre,

                    apellido,

                    curso

                FROM usuarios

                WHERE id = ?

                AND LOWER(TRIM(rol))
                    = 'estudiante'

                LIMIT 1

            ");


        if (!$stmt) {

            $mensaje =
                "No se pudo comprobar el estudiante.";

            $tipoMensaje =
                "danger";

        }

        else {


            $stmt->bind_param(
                "i",
                $idEstudiante
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows === 0
            ) {

                $mensaje =
                    "El estudiante no existe.";

                $tipoMensaje =
                    "danger";

            }

            else {


                $estudiante =
                    $resultado->fetch_assoc();


                /* =========================================
                   COMPROBAR NUEVAMENTE SI YA VOTÓ
                ========================================= */

                $stmtVoto =
                    $conn->prepare("

                        SELECT
                            COUNT(*) AS total

                        FROM votos v

                        INNER JOIN candidatos c
                            ON c.id = v.id_candidato

                        WHERE v.id_usuario = ?

                        AND c.id_eleccion = ?

                    ");


                if (!$stmtVoto) {

                    $mensaje =
                        "No se pudo comprobar la votación.";

                    $tipoMensaje =
                        "danger";

                }

                else {


                    $stmtVoto->bind_param(

                        "ii",

                        $idEstudiante,

                        $eleccion['id']

                    );


                    $stmtVoto->execute();


                    $datosVoto =
                        $stmtVoto
                        ->get_result()
                        ->fetch_assoc();


                    $totalVotos =
                        (int)$datosVoto['total'];


                    $stmtVoto->close();


                    /* =====================================
                       BLOQUEAR SI YA VOTÓ
                    ===================================== */

                    if (
                        $totalVotos > 0
                    ) {

                        $mensaje =
                            "Este estudiante ya realizó su votación en esta elección.";

                        $tipoMensaje =
                            "danger";

                    }

                    else {


                        /* =================================
                           GUARDAR ESTUDIANTE EN SESIÓN
                        ================================= */

                        $_SESSION[
                            'estudiante_votando_id'
                        ] =
                            $idEstudiante;


                        $_SESSION[
                            'estudiante_votando_documento'
                        ] =
                            $estudiante['documento'];


                        $_SESSION[
                            'estudiante_votando_nombre'
                        ] =
                            $estudiante['nombre']
                            . " "
                            .
                            $estudiante['apellido'];


                        $_SESSION[
                            'estudiante_votando_curso'
                        ] =
                            $estudiante['curso'];


                        $_SESSION[
                            'eleccion_votante_id'
                        ] =
                            (int)$eleccion['id'];


                        /* =================================
                           IR A VOTACIÓN
                        ================================= */

                        header(
                            "Location: votar_por_jurado.php"
                        );

                        exit();

                    }

                }

            }


            $stmt->close();

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

Buscar estudiante

</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

    min-height:100vh;

}


.contenedor {

    max-width:1100px;

}


.card-principal {

    border:none;

    border-radius:20px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.encabezado {

    background:#1459a6;

    color:white;

    border-radius:
        20px 20px 0 0;

    padding:25px;

}


.icono {

    width:75px;

    height:75px;

    border-radius:50%;

    background:rgba(255,255,255,.15);

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:38px;

}


.titulo {

    font-weight:bold;

}


.buscar-input {

    height:55px;

    font-size:17px;

}


.btn-buscar {

    height:55px;

    font-weight:bold;

}


.tabla {

    background:white;

}


.tabla thead th {

    background:#cfe2ff;

    vertical-align:middle;

}


.estado-ok {

    color:#198754;

    font-weight:bold;

}


.estado-voto {

    color:#dc3545;

    font-weight:bold;

}


.btn-seleccionar {

    font-weight:bold;

}


.info-eleccion {

    background:#e7f1ff;

    border:1px solid #b6d4fe;

    border-radius:12px;

    padding:15px;

}


</style>

</head>


<body>


<div class="container contenedor py-5">


<div class="card card-principal">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="encabezado">


<div class="d-flex
            align-items-center
            gap-3">


<div class="icono">

<i class="bi bi-person-vcard-fill"></i>

</div>


<div>


<h2 class="titulo mb-1">

Buscar estudiante

</h2>


<p class="mb-0">

Seleccione el estudiante que va a realizar la votación.

</p>


</div>


</div>


</div>


<div class="card-body p-4">


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<?php if (
    $eleccion !== null
) { ?>


<div class="info-eleccion mb-4">


<strong>

<i class="bi bi-calendar-check-fill"></i>

Elección activa:

</strong>


<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>


<br>


<small>

Estado:

<strong class="text-success">

Abierta

</strong>

</small>


</div>


<?php } else { ?>


<div class="alert alert-warning">


<i class="bi bi-exclamation-triangle-fill"></i>


<strong>

No hay una elección abierta.

</strong>


</div>


<?php } ?>


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php echo htmlspecialchars(
    $tipoMensaje
); ?>">


<i class="bi bi-info-circle-fill"></i>


<?php echo htmlspecialchars(
    $mensaje
); ?>


</div>


<?php } ?>


<!-- =====================================================
     BUSCADOR
===================================================== -->

<form
method="POST"
autocomplete="off"
class="mb-4">


<div class="row g-2">


<div class="col-md-9">


<input

type="text"

name="busqueda"

class="form-control buscar-input"

placeholder="Documento, nombre, apellido o curso..."

value="<?php echo htmlspecialchars(
    $busqueda
); ?>"

required

>


</div>


<div class="col-md-3">


<button

type="submit"

name="buscar"

class="btn btn-primary btn-buscar w-100">


<i class="bi bi-search"></i>


Buscar


</button>


</div>


</div>


</form>


<!-- =====================================================
     RESULTADOS
===================================================== -->

<?php if (
    count($resultados) > 0
) { ?>


<div class="d-flex
            justify-content-between
            align-items-center
            mb-3">


<h5 class="mb-0">


<i class="bi bi-list-ul"></i>


Resultados encontrados:


<?php echo count($resultados); ?>


</h5>


</div>


<div class="table-responsive">


<table
class="table table-bordered table-hover align-middle tabla">


<thead>


<tr>


<th>

Documento

</th>


<th>

Nombre completo

</th>


<th>

Curso

</th>


<th>

Estado

</th>


<th>

Acción

</th>


</tr>


</thead>


<tbody>


<?php foreach (
    $resultados
    as $estudiante
) { ?>


<tr>


<td>


<strong>

<?php echo htmlspecialchars(
    $estudiante['documento']
); ?>


</strong>


</td>


<td>


<?php echo htmlspecialchars(
    $estudiante['nombre']
    . " "
    .
    $estudiante['apellido']
); ?>


</td>


<td>


<span
class="badge bg-secondary">


<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>


</span>


</td>


<td>


<?php if (
    (int)$estudiante['ya_voto'] === 1
) { ?>


<span class="estado-voto">


<i class="bi bi-x-circle-fill"></i>


Ya votó


</span>


<?php } else { ?>


<span class="estado-ok">


<i class="bi bi-check-circle-fill"></i>


Puede votar


</span>


<?php } ?>


</td>


<td>


<?php if (
    (int)$estudiante['ya_voto'] === 1
) { ?>


<button

type="button"

class="btn btn-secondary btn-sm"

disabled>


<i class="bi bi-lock-fill"></i>


Bloqueado


</button>


<?php } else { ?>


<form
method="POST"
style="display:inline;">


<input

type="hidden"

name="id_estudiante"

value="<?php echo (int)$estudiante['id']; ?>">


<button

type="submit"

name="seleccionar_estudiante"

class="btn btn-success btn-sm btn-seleccionar"

onclick="return confirm('¿Desea seleccionar a <?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido'], ENT_QUOTES); ?> para votar?');"


>


<i class="bi bi-check-circle-fill"></i>


Seleccionar


</button>


</form>


<?php } ?>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>


<?php } ?>


<!-- =====================================================
     BOTONES
===================================================== -->

<div class="d-flex
            justify-content-between
            flex-wrap
            gap-2
            mt-4">


<a
href="jurado.php"
class="btn btn-secondary">


<i class="bi bi-arrow-left"></i>


Volver al panel del jurado


</a>


<?php if (
    $busqueda !== ""
) { ?>


<a
href="ingresar_estudiante.php"
class="btn btn-outline-primary">


<i class="bi bi-x-circle"></i>


Nueva búsqueda


</a>


<?php } ?>


</div>


</div>


</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
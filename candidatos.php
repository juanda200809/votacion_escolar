<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "";

$idEleccionSeleccionada = 0;


/* =========================================================
   ELECCIÓN SELECCIONADA
========================================================= */

if (
    isset($_GET['id_eleccion']) &&
    is_numeric($_GET['id_eleccion'])
) {

    $idEleccionSeleccionada =
        (int) $_GET['id_eleccion'];

}


/* =========================================================
   SI NO HAY ELECCIÓN SELECCIONADA
   BUSCAR LA MÁS RECIENTE
========================================================= */

if (
    $idEleccionSeleccionada <= 0
) {

    $resultado =
        $conn->query("

            SELECT id

            FROM elecciones

            ORDER BY id DESC

            LIMIT 1

        ");


    if (
        $resultado &&
        $resultado->num_rows > 0
    ) {

        $fila =
            $resultado->fetch_assoc();

        $idEleccionSeleccionada =
            (int) $fila['id'];

    }

}


/* =========================================================
   PROCESAR ELIMINACIÓN
========================================================= */

if (
    isset($_GET['eliminar']) &&
    is_numeric($_GET['eliminar'])
) {

    $idEliminar =
        (int) $_GET['eliminar'];


    if (
        $idEliminar <= 0
    ) {

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $idEleccionSeleccionada
            .
            "&error=eliminar"
        );

        exit();

    }


    /* =====================================================
       OBTENER CANDIDATO
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT
                id,
                nombre,
                apellido,
                foto,
                id_eleccion,
                id_cargo

            FROM candidatos

            WHERE id = ?

            LIMIT 1

        ");


    if (!$stmt) {

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $idEleccionSeleccionada
            .
            "&error=eliminar"
        );

        exit();

    }


    $stmt->bind_param(
        "i",
        $idEliminar
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows === 0
    ) {

        $stmt->close();

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $idEleccionSeleccionada
            .
            "&error=no_encontrado"
        );

        exit();

    }


    $candidatoEliminar =
        $resultado->fetch_assoc();


    $stmt->close();


    $eleccionCandidato =
        (int) $candidatoEliminar['id_eleccion'];


    $fotoCandidato =
        $candidatoEliminar['foto'];


    /* =====================================================
       CONTAR VOTOS
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

            FROM votos

            WHERE id_candidato = ?

        ");


    if (!$stmt) {

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $eleccionCandidato
            .
            "&error=eliminar"
        );

        exit();

    }


    $stmt->bind_param(
        "i",
        $idEliminar
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $datos =
        $resultado->fetch_assoc();


    $totalVotos =
        (int) $datos['total'];


    $stmt->close();


    /* =====================================================
       NO ELIMINAR SI TIENE VOTOS
    ===================================================== */

    if (
        $totalVotos > 0
    ) {

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $eleccionCandidato
            .
            "&error=tiene_votos"
        );

        exit();

    }


    /* =====================================================
       ELIMINAR CANDIDATO
    ===================================================== */

    $stmt =
        $conn->prepare("

            DELETE FROM candidatos

            WHERE id = ?

        ");


    if (!$stmt) {

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $eleccionCandidato
            .
            "&error=eliminar"
        );

        exit();

    }


    $stmt->bind_param(
        "i",
        $idEliminar
    );


    if (
        !$stmt->execute()
    ) {

        $stmt->close();

        header(
            "Location: candidatos.php?id_eleccion="
            .
            $eleccionCandidato
            .
            "&error=eliminar"
        );

        exit();

    }


    $eliminado =
        $stmt->affected_rows > 0;


    $stmt->close();


    /* =====================================================
       ELIMINAR FOTO DESPUÉS DEL REGISTRO
    ===================================================== */

    if (
        $eliminado &&
        $fotoCandidato !== ''
    ) {

        $rutaFoto =
            "uploads/candidatos/"
            .
            $fotoCandidato;


        if (
            file_exists($rutaFoto)
        ) {

            unlink($rutaFoto);

        }

    }


    header(
        "Location: candidatos.php?id_eleccion="
        .
        $eleccionCandidato
        .
        "&eliminado=1"
    );

    exit();

}


/* =========================================================
   SI NO EXISTEN ELECCIONES
========================================================= */

if (
    $idEleccionSeleccionada <= 0
) {

    ?>

    <!DOCTYPE html>

    <html lang="es">

    <head>

        <meta charset="UTF-8">

        <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

        <title>
            Candidatos
        </title>

        <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    </head>

    <body class="bg-light">

        <div
        class="container text-center py-5">

            <div
            class="card shadow border-0 p-5">

                <h2>
                    No existen elecciones registradas.
                </h2>

                <p class="text-muted">
                    Primero debes crear una elección.
                </p>

                <a
                href="crear_eleccion.php"
                class="btn btn-primary">

                    Crear elección

                </a>

            </div>

        </div>

    </body>

    </html>

    <?php

    exit();

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
        "No se pudo consultar la elección."
    );

}


$stmt->bind_param(
    "i",
    $idEleccionSeleccionada
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header(
        "Location: candidatos.php?error=eleccion"
    );

    exit();

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   PROCESAR REGISTRO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['guardar'])
) {


    $nombre =
        trim(
            $_POST['nombre'] ?? ''
        );


    $apellido =
        trim(
            $_POST['apellido'] ?? ''
        );


    $curso =
        trim(
            $_POST['curso'] ?? ''
        );


    $propuestas =
        trim(
            $_POST['propuestas'] ?? ''
        );


    $idCargo =
        filter_var(
            $_POST['id_cargo'] ?? 0,
            FILTER_VALIDATE_INT
        );


    $idEleccionFormulario =
        filter_var(
            $_POST['id_eleccion'] ?? 0,
            FILTER_VALIDATE_INT
        );


    /* =====================================================
       VALIDAR ELECCIÓN
    ===================================================== */

    if (
        $idEleccionFormulario !==
        $idEleccionSeleccionada
    ) {

        $mensaje =
            "La elección seleccionada no es válida.";

        $tipoMensaje =
            "danger";

    }


    elseif (
        $nombre === '' ||
        $apellido === '' ||
        $curso === '' ||
        $idCargo === false ||
        $idCargo <= 0
    ) {

        $mensaje =
            "Completa todos los campos obligatorios.";

        $tipoMensaje =
            "danger";

    }


    else {


        /* =================================================
           VERIFICAR CARGO
        ================================================= */

        $stmt =
            $conn->prepare("

                SELECT
                    c.id

                FROM cargos c

                INNER JOIN eleccion_cargos ec
                    ON ec.id_cargo = c.id

                WHERE ec.id_eleccion = ?

                AND c.id = ?

                LIMIT 1

            ");


        if (!$stmt) {

            $mensaje =
                "No se pudo validar el cargo.";

            $tipoMensaje =
                "danger";

        }


        else {


            $stmt->bind_param(
                "ii",
                $idEleccionSeleccionada,
                $idCargo
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows === 0
            ) {

                $mensaje =
                    "El cargo seleccionado no pertenece a esta elección.";

                $tipoMensaje =
                    "danger";

            }


            $stmt->close();

        }


        /* =================================================
           CANDIDATO DUPLICADO
        ================================================= */

        if (
            $mensaje === ''
        ) {

            $stmt =
                $conn->prepare("

                    SELECT
                        id

                    FROM candidatos

                    WHERE nombre = ?

                    AND apellido = ?

                    AND id_eleccion = ?

                    AND id_cargo = ?

                    LIMIT 1

                ");


            if (!$stmt) {

                $mensaje =
                    "No se pudo comprobar el candidato.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $stmt->bind_param(
                    "ssii",
                    $nombre,
                    $apellido,
                    $idEleccionSeleccionada,
                    $idCargo
                );


                $stmt->execute();


                $resultado =
                    $stmt->get_result();


                if (
                    $resultado->num_rows > 0
                ) {

                    $mensaje =
                        "Ese candidato ya está registrado para este cargo.";

                    $tipoMensaje =
                        "danger";

                }


                $stmt->close();

            }

        }


        /* =================================================
           FOTO
        ================================================= */

        $foto = "";


        if (
            $mensaje === '' &&
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !==
            UPLOAD_ERR_NO_FILE
        ) {


            if (
                $_FILES['foto']['error'] !==
                UPLOAD_ERR_OK
            ) {

                $mensaje =
                    "No se pudo cargar la fotografía.";

                $tipoMensaje =
                    "danger";

            }


            elseif (
                $_FILES['foto']['size'] >
                5 * 1024 * 1024
            ) {

                $mensaje =
                    "La fotografía no puede superar los 5 MB.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $tipoMime =
                    mime_content_type(
                        $_FILES['foto']['tmp_name']
                    );


                $permitidosMime = [

                    'image/jpeg',
                    'image/png',
                    'image/webp'

                ];


                if (
                    !in_array(
                        $tipoMime,
                        $permitidosMime,
                        true
                    )
                ) {

                    $mensaje =
                        "El formato de la fotografía no es válido.";

                    $tipoMensaje =
                        "danger";

                }


                else {


                    $extension =
                        strtolower(
                            pathinfo(
                                $_FILES['foto']['name'],
                                PATHINFO_EXTENSION
                            )
                        );


                    $permitidas = [

                        'jpg',
                        'jpeg',
                        'png',
                        'webp'

                    ];


                    if (
                        !in_array(
                            $extension,
                            $permitidas,
                            true
                        )
                    ) {

                        $mensaje =
                            "El formato de la fotografía no es válido.";

                        $tipoMensaje =
                            "danger";

                    }


                    else {


                        $directorio =
                            "uploads/candidatos";


                        if (
                            !is_dir($directorio)
                        ) {

                            if (
                                !mkdir(
                                    $directorio,
                                    0755,
                                    true
                                )
                            ) {

                                $mensaje =
                                    "No se pudo preparar la carpeta de fotografías.";

                                $tipoMensaje =
                                    "danger";

                            }

                        }


                        if (
                            $mensaje === ''
                        ) {


                            $foto =
                                bin2hex(
                                    random_bytes(16)
                                )
                                .
                                '.'
                                .
                                $extension;


                            $ruta =
                                $directorio
                                .
                                '/'
                                .
                                $foto;


                            if (
                                !move_uploaded_file(
                                    $_FILES['foto']['tmp_name'],
                                    $ruta
                                )
                            ) {

                                $mensaje =
                                    "No se pudo guardar la fotografía.";

                                $tipoMensaje =
                                    "danger";

                                $foto = "";

                            }

                        }

                    }

                }

            }

        }


        /* =================================================
           INSERTAR
        ================================================= */

        if (
            $mensaje === ''
        ) {


            $stmt =
                $conn->prepare("

                    INSERT INTO candidatos
                    (
                        nombre,
                        apellido,
                        curso,
                        foto,
                        propuestas,
                        id_eleccion,
                        id_cargo
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )

                ");


            if (!$stmt) {

                if (
                    $foto !== '' &&
                    file_exists(
                        "uploads/candidatos/"
                        .
                        $foto
                    )
                ) {

                    unlink(
                        "uploads/candidatos/"
                        .
                        $foto
                    );

                }


                $mensaje =
                    "No se pudo preparar el registro del candidato.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $stmt->bind_param(
                    "sssssii",
                    $nombre,
                    $apellido,
                    $curso,
                    $foto,
                    $propuestas,
                    $idEleccionSeleccionada,
                    $idCargo
                );


                if (
                    !$stmt->execute()
                ) {

                    if (
                        $foto !== '' &&
                        file_exists(
                            "uploads/candidatos/"
                            .
                            $foto
                        )
                    ) {

                        unlink(
                            "uploads/candidatos/"
                            .
                            $foto
                        );

                    }


                    $mensaje =
                        "No se pudo registrar el candidato.";

                    $tipoMensaje =
                        "danger";

                }


                else {

                    $stmt->close();


                    header(
                        "Location: candidatos.php?id_eleccion="
                        .
                        $idEleccionSeleccionada
                        .
                        "&ok=1"
                    );

                    exit();

                }


                $stmt->close();

            }

        }

    }

}


/* =========================================================
   MENSAJES
========================================================= */

if (
    isset($_GET['ok'])
) {

    $mensaje =
        "Candidato registrado correctamente.";

    $tipoMensaje =
        "success";

}


if (
    isset($_GET['actualizado'])
) {

    $mensaje =
        "Candidato actualizado correctamente.";

    $tipoMensaje =
        "success";

}


if (
    isset($_GET['eliminado'])
) {

    $mensaje =
        "Candidato eliminado correctamente.";

    $tipoMensaje =
        "success";

}


if (
    isset($_GET['error'])
) {

    switch (
        $_GET['error']
    ) {

        case 'tiene_votos':

            $mensaje =
                "No se puede eliminar este candidato porque tiene votos registrados.";

            $tipoMensaje =
                "warning";

            break;


        case 'no_encontrado':

            $mensaje =
                "El candidato no existe.";

            $tipoMensaje =
                "danger";

            break;


        case 'eliminar':

            $mensaje =
                "No se pudo eliminar el candidato.";

            $tipoMensaje =
                "danger";

            break;


        case 'eleccion':

            $mensaje =
                "La elección seleccionada no existe.";

            $tipoMensaje =
                "danger";

            break;

    }

}


/* =========================================================
   CARGOS
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

        ORDER BY c.nombre_cargo ASC

    ");


$stmt->bind_param(
    "i",
    $idEleccionSeleccionada
);


$stmt->execute();


$cargos =
    $stmt->get_result();


$stmt->close();


/* =========================================================
   CANDIDATOS
========================================================= */

$stmt =
    $conn->prepare("

        SELECT

            c.id,
            c.nombre,
            c.apellido,
            c.curso,
            c.foto,
            c.propuestas,
            c.id_eleccion,
            c.id_cargo,

            ca.nombre_cargo,

            e.nombre AS nombre_eleccion

        FROM candidatos c

        INNER JOIN cargos ca
            ON ca.id = c.id_cargo

        INNER JOIN elecciones e
            ON e.id = c.id_eleccion

        WHERE c.id_eleccion = ?

        ORDER BY
            ca.nombre_cargo ASC,
            c.apellido ASC,
            c.nombre ASC

    ");


$stmt->bind_param(
    "i",
    $idEleccionSeleccionada
);


$stmt->execute();


$candidatos =
    $stmt->get_result();


$stmt->close();

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Gestión de Candidatos
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
rel="stylesheet">


<style>

body {

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width: 1250px;

    margin: auto;

    padding: 30px 20px;

}


.encabezado {

    background:
        linear-gradient(
            135deg,
            #1453a3,
            #0d6efd
        );

    color: white;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 7px 25px
        rgba(0,0,0,.12);

}


.card {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.08);

}


.card-body {

    padding: 25px;

}


.form-control,
.form-select {

    border-radius: 10px;

    padding: 11px 13px;

}


.form-control:focus,
.form-select:focus {

    border-color: #0d6efd;

    box-shadow:
        0 0 0 .2rem
        rgba(13,110,253,.12);

}


.btn {

    border-radius: 9px;

    font-weight: 600;

}


.table {

    margin-bottom: 0;

}


.table thead th {

    background: #f1f5f9;

    color: #334155;

    border-bottom: none;

    white-space: nowrap;

}


.table tbody tr {

    transition:
        background .2s ease;

}


.table tbody tr:hover {

    background: #f8fafc;

}


.candidato-foto {

    width: 70px;

    height: 70px;

    object-fit: cover;

    border-radius: 50%;

    border: 3px solid #0d6efd;

}


.sin-foto {

    width: 70px;

    height: 70px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 27px;

    color: #6c757d;

}


.badge {

    font-size: .82rem;

}


.estado {

    border-radius: 12px;

    padding: 12px 15px;

}


@media (
    max-width: 768px
) {

    .contenedor {

        padding: 15px;

    }


    .encabezado {

        padding: 20px;

    }


    .card-body {

        padding: 18px;

    }


    .table {

        font-size: .9rem;

    }

}

</style>

</head>


<body>


<div class="contenedor">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>

<h1 class="mb-1">

<i class="bi bi-person-vcard-fill"></i>

Candidatos

</h1>


<p class="mb-0 opacity-75">

Administración de candidatos por elección y cargo.

</p>

</div>


<a
href="admin.php"
class="btn btn-light">

<i class="bi bi-arrow-left"></i>

Panel

</a>


</div>


</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="card mt-4">


<div class="card-body">


<div class="row
            align-items-end
            g-3">


<div class="col-md-8">


<label
class="form-label">

<i class="bi bi-calendar-event"></i>

Elección

</label>


<form
method="GET">


<select
name="id_eleccion"
class="form-select"
onchange="this.form.submit()">


<?php

$listaElecciones =
    $conn->query("

        SELECT
            id,
            nombre,
            estado

        FROM elecciones

        ORDER BY id DESC

    ");


while (
    $e =
    $listaElecciones->fetch_assoc()
) {

?>


<option

value="<?php

echo (int)$e['id'];

?>"

<?php

if (
    (int)$e['id'] ===
    $idEleccionSeleccionada
) {

    echo "selected";

}

?>

>


<?php

echo htmlspecialchars(
    $e['nombre']
);

?>


 -

<?php

echo htmlspecialchars(
    ucfirst(
        $e['estado']
    )
);

?>


</option>


<?php

}

?>


</select>


</form>


</div>


<div class="col-md-4">


<div class="estado

<?php

echo strtolower(
    trim(
        $eleccion['estado']
    )
) === 'abierta'

    ? 'bg-success-subtle text-success-emphasis'

    : 'bg-secondary-subtle text-secondary-emphasis';

?>


">


<strong>

Estado:

</strong>


<?php

echo htmlspecialchars(
    ucfirst(
        $eleccion['estado']
    )
);

?>


</div>


</div>


</div>


</div>

</div>


<!-- =====================================================
     MENSAJES
===================================================== -->

<?php if (
    $mensaje !== ''
) { ?>


<div
class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje
);

?>
alert-dismissible fade show mt-4">


<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>


<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>


</div>


<?php } ?>


<!-- =====================================================
     REGISTRAR
===================================================== -->

<div class="card mt-4">


<div class="card-body">


<h4 class="mb-4">

<i class="bi bi-person-plus-fill"></i>

Registrar candidato

</h4>


<form
method="POST"
enctype="multipart/form-data">


<input
type="hidden"
name="id_eleccion"
value="<?php

echo $idEleccionSeleccionada;

?>">


<div class="row">


<div class="col-md-6 mb-3">


<label
class="form-label">

Nombre

</label>


<input
type="text"
name="nombre"
class="form-control"
maxlength="100"
required>

</div>


<div class="col-md-6 mb-3">


<label
class="form-label">

Apellido

</label>


<input
type="text"
name="apellido"
class="form-control"
maxlength="100"
required>

</div>


</div>


<div class="row">


<div class="col-md-6 mb-3">


<label
class="form-label">

Curso

</label>


<input
type="text"
name="curso"
class="form-control"
maxlength="20"
required>

</div>


<div class="col-md-6 mb-3">


<label
class="form-label">

Cargo

</label>


<select
name="id_cargo"
class="form-select"
required>


<option value="">

Seleccione un cargo

</option>


<?php while (
    $cargo =
    $cargos->fetch_assoc()
) { ?>


<option
value="<?php

echo (int)$cargo['id'];

?>">


<?php

echo htmlspecialchars(
    $cargo['nombre_cargo']
);

?>


</option>


<?php } ?>


</select>


</div>


</div>


<div class="mb-3">


<label
class="form-label">

Propuestas

</label>


<textarea
name="propuestas"
class="form-control"
rows="5"
maxlength="500"
placeholder="Escriba las propuestas del candidato..."></textarea>


</div>


<div class="mb-4">


<label
class="form-label">

Fotografía

</label>


<input
type="file"
name="foto"
class="form-control"
accept=".jpg,.jpeg,.png,.webp">


<small class="text-muted">

JPG, JPEG, PNG o WEBP. Máximo 5 MB.

</small>


</div>


<button
type="submit"
name="guardar"
class="btn btn-success">


<i class="bi bi-check-circle-fill"></i>

Registrar candidato


</button>


</form>


</div>

</div>


<!-- =====================================================
     LISTADO
===================================================== -->

<div class="card mt-4">


<div class="card-body">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-4">


<h4 class="mb-0">

<i class="bi bi-people-fill"></i>

Candidatos registrados

</h4>


<span class="badge text-bg-primary">

<?php

echo $candidatos->num_rows;

?>

candidato(s)

</span>


</div>


<div class="table-responsive">


<table
class="table table-hover align-middle">


<thead>

<tr>

<th>
Candidato
</th>

<th>
Curso
</th>

<th>
Cargo
</th>

<th>
Propuestas
</th>

<th>
Acciones
</th>

</tr>

</thead>


<tbody>


<?php if (
    $candidatos->num_rows === 0
) { ?>


<tr>

<td
colspan="5"
class="text-center py-5">


<i
class="bi bi-person-x fs-1 text-secondary">
</i>


<p class="mt-3 mb-0 text-muted">

No hay candidatos registrados
para esta elección.

</p>


</td>

</tr>


<?php } else { ?>


<?php while (
    $c =
    $candidatos->fetch_assoc()
) { ?>


<tr>


<td>


<div class="d-flex
            align-items-center
            gap-3">


<?php

if (
    !empty($c['foto']) &&
    file_exists(
        "uploads/candidatos/"
        .
        $c['foto']
    )
) {

?>


<img

src="uploads/candidatos/<?php

echo htmlspecialchars(
    $c['foto']
);

?>"

class="candidato-foto"

alt="Fotografía del candidato">


<?php

} else {

?>


<div class="sin-foto">

<i class="bi bi-person-fill"></i>

</div>


<?php

}

?>


<div>

<strong>

<?php

echo htmlspecialchars(
    $c['nombre']
    .
    ' '
    .
    $c['apellido']
);

?>

</strong>


</div>


</div>


</td>


<td>

<?php

echo htmlspecialchars(
    $c['curso']
);

?>

</td>


<td>

<span class="badge text-bg-primary">

<?php

echo htmlspecialchars(
    $c['nombre_cargo']
);

?>

</span>

</td>


<td>

<?php

$texto =
    trim(
        (string)$c['propuestas']
    );


if (
    $texto === ''
) {

    echo '<span class="text-muted">Sin propuestas</span>';

} else {

    echo nl2br(
        htmlspecialchars(
            $texto
        )
    );

}

?>

</td>


<td>


<div class="d-flex
            gap-2
            flex-wrap">


<a

href="editar_candidato.php?id=<?php

echo (int)$c['id'];

?>"

class="btn btn-sm btn-primary">


<i class="bi bi-pencil-square"></i>

Editar

</a>


<a

href="candidatos.php?eliminar=<?php

echo (int)$c['id'];

?>&id_eleccion=<?php

echo $idEleccionSeleccionada;

?>"

class="btn btn-sm btn-danger"

onclick="return confirm(
'¿Está seguro de eliminar este candidato? Esta acción no se puede deshacer.'
);">


<i class="bi bi-trash-fill"></i>

Eliminar

</a>


</div>


</td>


</tr>


<?php } ?>


<?php } ?>


</tbody>


</table>


</div>


</div>

</div>


</div>


</body>

</html>
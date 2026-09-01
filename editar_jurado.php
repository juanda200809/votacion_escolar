<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


/* =========================================================
   SOLO ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim((string)$_SESSION['rol'])) !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   ID DEL JURADO
========================================================= */

$idJurado = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($idJurado <= 0) {

    header("Location: admin.php?error=jurado");
    exit();

}


/* =========================================================
   BUSCAR JURADO
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso,
        correo,
        fecha_registro
    FROM usuarios
    WHERE id = ?
    AND LOWER(TRIM(rol)) = 'jurado'
    LIMIT 1
");

if (!$stmt) {

    die("Error al consultar el jurado: " . $conn->error);

}

$stmt->bind_param("i", $idJurado);

$stmt->execute();

$resultado = $stmt->get_result();

$jurado = $resultado->fetch_assoc();

$stmt->close();


if (!$jurado) {

    header("Location: admin.php?error=jurado");
    exit();

}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$idEleccion = 0;

if (
    isset($_GET['id_eleccion']) &&
    (int)$_GET['id_eleccion'] > 0
) {

    $idEleccion = (int)$_GET['id_eleccion'];

}


/* Si no viene por URL, tomar la última elección */

if ($idEleccion <= 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM elecciones
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->execute();

        $resultado = $stmt->get_result();

        $fila = $resultado->fetch_assoc();

        $stmt->close();

        if ($fila) {

            $idEleccion = (int)$fila['id'];

        }

    }

}


/* =========================================================
   PROCESAR ASIGNACIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['asignar_mesa'])
) {

    $idMesa = isset($_POST['id_mesa'])
        ? (int)$_POST['id_mesa']
        : 0;


    $idEleccionPost = isset($_POST['id_eleccion'])
        ? (int)$_POST['id_eleccion']
        : $idEleccion;


    if (
        $idMesa <= 0 ||
        $idEleccionPost <= 0
    ) {

        header(
            "Location: editar_jurado.php?id="
            . $idJurado
            . "&error=datos"
        );

        exit();

    }


    $conn->begin_transaction();

    try {

        /* =============================================
           COMPROBAR QUE LA MESA ESTÁ DISPONIBLE
        ============================================= */

        $stmtMesa = $conn->prepare("
            SELECT
                id,
                id_eleccion,
                id_jurado,
                nombre_mesa,
                estado
            FROM mesas_votacion
            WHERE id = ?
            AND id_eleccion = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmtMesa) {

            throw new Exception(
                "No se pudo consultar la mesa."
            );

        }

        $stmtMesa->bind_param(
            "ii",
            $idMesa,
            $idEleccionPost
        );

        $stmtMesa->execute();

        $resultadoMesa =
            $stmtMesa->get_result();

        $mesa =
            $resultadoMesa->fetch_assoc();

        $stmtMesa->close();


        if (!$mesa) {

            throw new Exception(
                "La mesa no existe para esta elección."
            );

        }


        /* =============================================
           LA MESA NO PUEDE ESTAR ASIGNADA
        ============================================= */

        if (
            !empty($mesa['id_jurado'])
        ) {

            /*
             * Si ya pertenece al mismo jurado,
             * simplemente dejamos continuar.
             */

            if (
                (int)$mesa['id_jurado']
                !==
                $idJurado
            ) {

                throw new Exception(
                    "Esta mesa ya está asignada a otro jurado."
                );

            }

        }


        /* =============================================
           QUITAR MESA ANTERIOR DEL JURADO
        ============================================= */

        $stmtAnterior = $conn->prepare("
            UPDATE mesas_votacion
            SET
                id_jurado = NULL
            WHERE id_eleccion = ?
            AND id_jurado = ?
        ");

        if (!$stmtAnterior) {

            throw new Exception(
                "No se pudo liberar la mesa anterior."
            );

        }

        $stmtAnterior->bind_param(
            "ii",
            $idEleccionPost,
            $idJurado
        );

        if (!$stmtAnterior->execute()) {

            $stmtAnterior->close();

            throw new Exception(
                "No se pudo actualizar la mesa anterior."
            );

        }

        $stmtAnterior->close();


        /* =============================================
           ASIGNAR NUEVA MESA
        ============================================= */

        $stmtAsignar = $conn->prepare("
            UPDATE mesas_votacion
            SET
                id_jurado = ?
            WHERE id = ?
            AND id_eleccion = ?
            AND (
                id_jurado IS NULL
                OR id_jurado = ?
            )
        ");

        if (!$stmtAsignar) {

            throw new Exception(
                "No se pudo preparar la asignación."
            );

        }

        $stmtAsignar->bind_param(
            "iiii",
            $idJurado,
            $idMesa,
            $idEleccionPost,
            $idJurado
        );

        if (!$stmtAsignar->execute()) {

            $stmtAsignar->close();

            throw new Exception(
                "No se pudo asignar la mesa."
            );

        }

        $filasAfectadas =
            $stmtAsignar->affected_rows;

        $stmtAsignar->close();


        if (
            $filasAfectadas <= 0
        ) {

            throw new Exception(
                "La mesa ya no está disponible."
            );

        }


        $conn->commit();


        header(
            "Location: editar_jurado.php?id="
            . $idJurado
            . "&id_eleccion="
            . $idEleccionPost
            . "&guardado=1"
        );

        exit();


    } catch (
        Throwable $e
    ) {

        $conn->rollback();


        header(
            "Location: editar_jurado.php?id="
            . $idJurado
            . "&id_eleccion="
            . $idEleccion
            . "&error="
            . urlencode(
                $e->getMessage()
            )
        );

        exit();

    }

}


/* =========================================================
   QUITAR MESA DEL JURADO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['quitar_mesa'])
) {

    $idEleccionPost = isset($_POST['id_eleccion'])
        ? (int)$_POST['id_eleccion']
        : $idEleccion;


    if (
        $idEleccionPost > 0
    ) {

        $stmt = $conn->prepare("
            UPDATE mesas_votacion
            SET
                id_jurado = NULL
            WHERE id_eleccion = ?
            AND id_jurado = ?
        ");


        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $idEleccionPost,
                $idJurado
            );

            $stmt->execute();

            $stmt->close();

        }

    }


    header(
        "Location: editar_jurado.php?id="
        . $idJurado
        . "&id_eleccion="
        . $idEleccion
        . "&quitado=1"
    );

    exit();

}


/* =========================================================
   OBTENER MESA ACTUAL
========================================================= */

$mesaActual = null;


if (
    $idEleccion > 0
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            nombre_mesa,
            estado,
            fecha_cierre
        FROM mesas_votacion
        WHERE id_eleccion = ?
        AND id_jurado = ?
        LIMIT 1
    ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idJurado
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $mesaActual =
            $resultado->fetch_assoc();

        $stmt->close();

    }

}


/* =========================================================
   OBTENER MESAS DISPONIBLES
========================================================= */

$mesasDisponibles = [];


if (
    $idEleccion > 0
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            nombre_mesa,
            estado
        FROM mesas_votacion
        WHERE id_eleccion = ?
        AND (
            id_jurado IS NULL
            OR id_jurado = ?
        )
        ORDER BY
            CAST(
                TRIM(
                    REPLACE(
                        nombre_mesa,
                        'Mesa ',
                        ''
                    )
                ) AS UNSIGNED
            ) ASC,
            id ASC
    ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idJurado
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $mesasDisponibles[] =
                $fila;

        }


        $stmt->close();

    }

}


/* =========================================================
   MENSAJE
========================================================= */

$mensaje = "";

$tipoMensaje = "";


if (
    isset($_GET['guardado'])
) {

    $mensaje =
        "La mesa fue asignada correctamente al jurado.";

    $tipoMensaje =
        "success";

}
elseif (
    isset($_GET['quitado'])
) {

    $mensaje =
        "La mesa fue retirada del jurado.";

    $tipoMensaje =
        "success";

}
elseif (
    isset($_GET['error'])
) {

    $mensaje =
        $_GET['error'];

    $tipoMensaje =
        "danger";

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<title>
Editar jurado
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet"
>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet"
>


<style>

body {

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width: 950px;

}


.card {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 6px 22px
        rgba(0,0,0,.09);

}


.encabezado {

    background: #0d47a1;

    color: white;

    padding: 25px;

    border-radius:
        18px 18px 0 0;

}


.titulo {

    color: #0d47a1;

    font-weight: 700;

}


.mesa-actual {

    background: #e7f8ef;

    border:
        1px solid #9ce7bb;

    border-radius: 14px;

    padding: 20px;

}


.sin-mesa {

    background: #fff3cd;

    border:
        1px solid #ffe69c;

    border-radius: 14px;

    padding: 20px;

}


.mesa-item {

    border:
        1px solid #dbe4ef;

    border-radius: 12px;

    padding: 15px;

    margin-bottom: 10px;

    background: #fff;

}


</style>

</head>


<body>


<div class="container contenedor py-5">


<div class="card">


<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2">


<div>


<h2 class="mb-1">

<i class="bi bi-person-gear"></i>

Editar jurado

</h2>


<p class="mb-0">

Asignación de mesa de votación

</p>


</div>


<a
href="admin.php"
class="btn btn-light"
>

<i class="bi bi-arrow-left"></i>

Volver al administrador

</a>


</div>


</div>


<div class="card-body p-4">


<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php
echo htmlspecialchars(
    $tipoMensaje,
    ENT_QUOTES,
    'UTF-8'
);
?>">


<?php

echo htmlspecialchars(
    $mensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>


</div>


<?php } ?>


<!-- =====================================================
     DATOS DEL JURADO
===================================================== -->

<div class="mb-4">


<h4 class="titulo">

Datos del jurado

</h4>


<div class="row g-3">


<div class="col-md-6">


<strong>

Nombre

</strong>


<div>

<?php

echo htmlspecialchars(
    $jurado['nombre']
    . ' '
    . $jurado['apellido'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


<div class="col-md-6">


<strong>

Documento

</strong>


<div>

<?php

echo htmlspecialchars(
    $jurado['documento'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


<div class="col-md-6">


<strong>

Curso

</strong>


<div>

<?php

echo htmlspecialchars(
    $jurado['curso'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


</div>


</div>


</div>


<hr>


<!-- =====================================================
     MESA ACTUAL
===================================================== -->

<div class="mb-4">


<h4 class="titulo mb-3">

🗳️ Mesa actual

</h4>


<?php if (
    $mesaActual
) { ?>


<div class="mesa-actual">


<h5>

<?php

echo htmlspecialchars(
    $mesaActual['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</h5>


<p class="mb-2">

Esta mesa está actualmente asignada
a este jurado.

</p>


<?php if (
    strtolower(
        trim(
            (string)$mesaActual['estado']
        )
    ) === 'abierta'
) { ?>


<span class="badge bg-success">

🟢 Abierta

</span>


<?php } else { ?>


<span class="badge bg-danger">

🔴 Cerrada

</span>


<?php } ?>


<form
method="POST"
class="mt-3"
>


<input
type="hidden"
name="id_eleccion"
value="<?php echo $idEleccion; ?>"
>


<button
type="submit"
name="quitar_mesa"
value="1"
class="btn btn-outline-danger"
onclick="
return confirm(
'¿Desea quitar la mesa de este jurado?'
);
"
>

<i class="bi bi-x-circle"></i>

Quitar mesa

</button>


</form>


</div>


<?php } else { ?>


<div class="sin-mesa">


<h5>

⚠️ Sin mesa asignada

</h5>


<p class="mb-0">

Este jurado todavía no tiene una mesa
asignada para esta elección.

</p>


</div>


<?php } ?>


</div>


<!-- =====================================================
     ASIGNAR MESA
===================================================== -->

<div>


<h4 class="titulo mb-3">

📦 Asignar mesa

</h4>


<?php if (
    count($mesasDisponibles) > 0
) { ?>


<p class="text-muted">

Selecciona una mesa disponible.
Las mesas asignadas a otros jurados
no aparecen como disponibles.

</p>


<?php foreach (
    $mesasDisponibles
    as $mesa
) { ?>


<div class="mesa-item">


<div class="row align-items-center">


<div class="col-md-7">


<h5 class="mb-1">

<?php

echo htmlspecialchars(
    $mesa['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>


<?php if (
    strtolower(
        trim(
            (string)$mesa['estado']
        )
    ) === 'abierta'
) { ?>


<span class="badge bg-success ms-2">

🟢 Abierta

</span>


<?php } else { ?>


<span class="badge bg-secondary ms-2">

🔴 Cerrada

</span>


<?php } ?>


</h5>


<small class="text-muted">

Mesa disponible para asignación.

</small>


</div>


<div class="col-md-5 text-md-end mt-2 mt-md-0">


<form
method="POST"
>


<input
type="hidden"
name="id_eleccion"
value="<?php echo $idEleccion; ?>"
>


<input
type="hidden"
name="id_mesa"
value="<?php echo (int)$mesa['id']; ?>"
>


<button
type="submit"
name="asignar_mesa"
value="1"
class="btn btn-primary"
>


<i class="bi bi-check-circle-fill"></i>

Asignar esta mesa


</button>


</form>


</div>


</div>


</div>


<?php } ?>


<?php } else { ?>


<div class="alert alert-warning">


<i class="bi bi-exclamation-triangle-fill"></i>


<strong>

No hay mesas disponibles.

</strong>


<br>


Ve a:

<strong>

Administrador → Jurados → Nueva mesa

</strong>


para crear una mesa disponible.


</div>


<?php } ?>


</div>


</div>


</div>


</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
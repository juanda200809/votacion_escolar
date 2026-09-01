<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


/* =========================================================
   SEGURIDAD
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
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "success";


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$idEleccion = 0;

$eleccionActual = null;


$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (!$stmt) {
    die(
        "Error al consultar la elección: "
        . $conn->error
    );
}


$stmt->execute();

$resultado = $stmt->get_result();

$eleccionActual = $resultado->fetch_assoc();

$stmt->close();


if ($eleccionActual) {

    $idEleccion =
        (int)$eleccionActual['id'];
}


/* =========================================================
   MENSAJES RECIBIDOS DESDE OTRAS PÁGINAS
========================================================= */

if (isset($_GET['creado'])) {

    if ((int)$_GET['creado'] === 1) {

        $mensaje =
            "El jurado fue registrado correctamente y su mesa fue creada automáticamente.";

        $tipoMensaje =
            "success";
    }
}


if (isset($_GET['eliminado'])) {

    if ((int)$_GET['eliminado'] === 1) {

        $mensaje =
            "El jurado fue eliminado correctamente.";

        $tipoMensaje =
            "success";
    }
}


if (isset($_GET['error'])) {

    $mensaje =
        trim((string)$_GET['error']);

    $tipoMensaje =
        "danger";
}


/* =========================================================
   CERRAR MESA
   SOLO ADMINISTRADOR
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cerrar_mesa'])
) {

    $idMesa =
        isset($_POST['id_mesa'])
            ? (int)$_POST['id_mesa']
            : 0;


    if ($idMesa <= 0) {

        $mensaje =
            "La mesa seleccionada no es válida.";

        $tipoMensaje =
            "danger";

    } else {

        $stmt =
            $conn->prepare("
                UPDATE mesas_votacion
                SET
                    estado = 'cerrada',
                    fecha_cierre = NOW()
                WHERE id = ?
                AND id_eleccion = ?
            ");


        if (!$stmt) {

            $mensaje =
                "Error al preparar el cierre de la mesa.";

            $tipoMensaje =
                "danger";

        } else {

            $stmt->bind_param(
                "ii",
                $idMesa,
                $idEleccion
            );


            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $mensaje =
                        "La mesa fue cerrada correctamente.";

                    $tipoMensaje =
                        "success";

                } else {

                    $mensaje =
                        "La mesa no pudo cerrarse o ya estaba cerrada.";

                    $tipoMensaje =
                        "warning";
                }

            } else {

                $mensaje =
                    "No se pudo cerrar la mesa: "
                    . $stmt->error;

                $tipoMensaje =
                    "danger";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   ABRIR MESA
   SOLO ADMINISTRADOR
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['abrir_mesa'])
) {

    $idMesa =
        isset($_POST['id_mesa'])
            ? (int)$_POST['id_mesa']
            : 0;


    if ($idMesa <= 0) {

        $mensaje =
            "La mesa seleccionada no es válida.";

        $tipoMensaje =
            "danger";

    } else {

        $stmt =
            $conn->prepare("
                UPDATE mesas_votacion
                SET
                    estado = 'abierta',
                    fecha_cierre = NULL
                WHERE id = ?
                AND id_eleccion = ?
            ");


        if (!$stmt) {

            $mensaje =
                "Error al preparar la apertura de la mesa.";

            $tipoMensaje =
                "danger";

        } else {

            $stmt->bind_param(
                "ii",
                $idMesa,
                $idEleccion
            );


            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $mensaje =
                        "La mesa fue habilitada nuevamente.";

                    $tipoMensaje =
                        "success";

                } else {

                    $mensaje =
                        "La mesa ya estaba abierta o no existe.";

                    $tipoMensaje =
                        "warning";
                }

            } else {

                $mensaje =
                    "No se pudo abrir la mesa: "
                    . $stmt->error;

                $tipoMensaje =
                    "danger";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   CREAR NUEVA MESA PARA JURADO SIN MESA
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['crear_mesa'])
) {

    $idJurado =
        isset($_POST['id_jurado'])
            ? (int)$_POST['id_jurado']
            : 0;


    if (
        $idJurado <= 0 ||
        $idEleccion <= 0
    ) {

        $mensaje =
            "El jurado o la elección no son válidos.";

        $tipoMensaje =
            "danger";

    } else {

        $conn->begin_transaction();


        try {

            /* ---------------------------------------------
               VERIFICAR QUE SEA JURADO
            --------------------------------------------- */

            $stmtJurado =
                $conn->prepare("
                    SELECT
                        id,
                        nombre,
                        apellido,
                        documento
                    FROM usuarios
                    WHERE id = ?
                    AND LOWER(TRIM(rol)) = 'jurado'
                    LIMIT 1
                ");


            if (!$stmtJurado) {

                throw new Exception(
                    "No se pudo consultar el jurado."
                );
            }


            $stmtJurado->bind_param(
                "i",
                $idJurado
            );


            $stmtJurado->execute();


            $resultadoJurado =
                $stmtJurado->get_result();


            $jurado =
                $resultadoJurado->fetch_assoc();


            $stmtJurado->close();


            if (!$jurado) {

                throw new Exception(
                    "El jurado seleccionado no existe."
                );
            }


            /* ---------------------------------------------
               VERIFICAR SI YA TIENE MESA
            --------------------------------------------- */

            $stmtTieneMesa =
                $conn->prepare("
                    SELECT id
                    FROM mesas_votacion
                    WHERE id_eleccion = ?
                    AND id_jurado = ?
                    LIMIT 1
                ");


            if (!$stmtTieneMesa) {

                throw new Exception(
                    "No se pudo verificar la mesa del jurado."
                );
            }


            $stmtTieneMesa->bind_param(
                "ii",
                $idEleccion,
                $idJurado
            );


            $stmtTieneMesa->execute();


            $resultadoTieneMesa =
                $stmtTieneMesa->get_result();


            $mesaExistente =
                $resultadoTieneMesa->fetch_assoc();


            $stmtTieneMesa->close();


            if ($mesaExistente) {

                throw new Exception(
                    "Este jurado ya tiene una mesa asignada."
                );
            }


            /* ---------------------------------------------
               OBTENER SIGUIENTE NÚMERO DE MESA
            --------------------------------------------- */

            $stmtNumero =
                $conn->prepare("
                    SELECT
                        COALESCE(
                            MAX(
                                CAST(
                                    TRIM(
                                        REPLACE(
                                            nombre_mesa,
                                            'Mesa ',
                                            ''
                                        )
                                    )
                                    AS UNSIGNED
                                )
                            ),
                            0
                        ) AS ultimo_numero

                    FROM mesas_votacion

                    WHERE id_eleccion = ?
                ");


            if (!$stmtNumero) {

                throw new Exception(
                    "No se pudo calcular el número de mesa."
                );
            }


            $stmtNumero->bind_param(
                "i",
                $idEleccion
            );


            $stmtNumero->execute();


            $resultadoNumero =
                $stmtNumero->get_result();


            $filaNumero =
                $resultadoNumero->fetch_assoc();


            $stmtNumero->close();


            $ultimoNumero =
                (int)(
                    $filaNumero['ultimo_numero']
                    ?? 0
                );


            $numeroMesa =
                $ultimoNumero + 1;


            $nombreMesa =
                "Mesa " . $numeroMesa;


            /* ---------------------------------------------
               ESTADO INICIAL
            --------------------------------------------- */

            $estadoMesa =
                (
                    strtolower(
                        trim(
                            (string)$eleccionActual['estado']
                        )
                    ) === 'abierta'
                )
                ? 'abierta'
                : 'cerrada';


            $fechaCierre =
                ($estadoMesa === 'cerrada')
                    ? date('Y-m-d H:i:s')
                    : null;


            /* ---------------------------------------------
               CREAR MESA Y ASIGNARLA
            --------------------------------------------- */

            $stmtMesa =
                $conn->prepare("
                    INSERT INTO mesas_votacion
                    (
                        id_eleccion,
                        id_jurado,
                        nombre_mesa,
                        estado,
                        fecha_cierre
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


            if (!$stmtMesa) {

                throw new Exception(
                    "No se pudo preparar la creación de la mesa."
                );
            }


            $stmtMesa->bind_param(
                "iisss",
                $idEleccion,
                $idJurado,
                $nombreMesa,
                $estadoMesa,
                $fechaCierre
            );


            if (!$stmtMesa->execute()) {

                $error =
                    $stmtMesa->error;

                $stmtMesa->close();

                throw new Exception(
                    "No se pudo crear la mesa: "
                    . $error
                );
            }


            $stmtMesa->close();


            $conn->commit();


            $nombreCompleto =
                $jurado['nombre']
                . " "
                . $jurado['apellido'];


            $mensaje =
                $nombreMesa
                . " fue creada y asignada automáticamente a "
                . $nombreCompleto
                . ".";


            $tipoMensaje =
                "success";


        } catch (Throwable $e) {

            $conn->rollback();


            $mensaje =
                $e->getMessage();

            $tipoMensaje =
                "danger";
        }
    }
}


/* =========================================================
   OBTENER TODOS LOS JURADOS
========================================================= */

$jurados = [];


$resultadoJurados =
    $conn->query("
        SELECT
            id,
            documento,
            nombre,
            apellido,
            curso,
            fecha_registro
        FROM usuarios
        WHERE LOWER(TRIM(rol)) = 'jurado'
        ORDER BY
            nombre ASC,
            apellido ASC
    ");


if (!$resultadoJurados) {

    die(
        "Error al consultar jurados: "
        . $conn->error
    );
}


while (
    $fila =
    $resultadoJurados->fetch_assoc()
) {

    $jurados[] =
        $fila;
}


$totalJurados =
    count($jurados);


/* =========================================================
   OBTENER MESAS DE LA ELECCIÓN ACTUAL
========================================================= */

$mesas = [];


if ($idEleccion > 0) {

    $stmtMesas =
        $conn->prepare("
            SELECT

                m.id,
                m.id_eleccion,
                m.id_jurado,
                m.nombre_mesa,
                m.estado,
                m.fecha_cierre,

                u.nombre AS nombre_jurado,
                u.apellido AS apellido_jurado,
                u.documento AS documento_jurado

            FROM mesas_votacion m

            LEFT JOIN usuarios u
                ON u.id = m.id_jurado

            WHERE m.id_eleccion = ?

            ORDER BY

                CAST(
                    TRIM(
                        REPLACE(
                            m.nombre_mesa,
                            'Mesa ',
                            ''
                        )
                    )
                    AS UNSIGNED
                ) ASC,

                m.id ASC
        ");


    if (!$stmtMesas) {

        die(
            "Error al consultar mesas: "
            . $conn->error
        );
    }


    $stmtMesas->bind_param(
        "i",
        $idEleccion
    );


    $stmtMesas->execute();


    $resultadoMesas =
        $stmtMesas->get_result();


    while (
        $fila =
        $resultadoMesas->fetch_assoc()
    ) {

        $mesas[] =
            $fila;
    }


    $stmtMesas->close();
}


$totalMesas =
    count($mesas);


/* =========================================================
   CONTADORES
========================================================= */

$mesasOcupadas =
    0;

$mesasDisponibles =
    0;

$mesasAbiertas =
    0;

$mesasCerradas =
    0;


foreach (
    $mesas as $mesa
) {

    if (
        !empty($mesa['id_jurado'])
    ) {

        $mesasOcupadas++;

    } else {

        $mesasDisponibles++;
    }


    $estado =
        strtolower(
            trim(
                (string)$mesa['estado']
            )
        );


    if ($estado === 'abierta') {

        $mesasAbiertas++;

    } else {

        $mesasCerradas++;
    }
}


/* =========================================================
   JURADOS SIN MESA
========================================================= */

$juradosSinMesa =
    0;


foreach (
    $jurados as $jurado
) {

    $tieneMesa =
        false;


    foreach (
        $mesas as $mesa
    ) {

        if (
            (int)(
                $mesa['id_jurado'] ?? 0
            )
            ===
            (int)$jurado['id']
        ) {

            $tieneMesa =
                true;

            break;
        }
    }


    if (!$tieneMesa) {

        $juradosSinMesa++;
    }
}


/* =========================================================
   FUNCIÓN PARA OBTENER MESA DE UN JURADO
========================================================= */

function obtenerMesaJurado(
    array $mesas,
    int $idJurado
) {

    foreach (
        $mesas as $mesa
    ) {

        if (
            (int)(
                $mesa['id_jurado'] ?? 0
            )
            ===
            $idJurado
        ) {

            return $mesa;
        }
    }


    return null;
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
Administrar Jurados
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<style>

body {

    background: #eef3f9;

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.contenedor {

    max-width: 1400px;

    margin: auto;
}


.card-jurado {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.10);

    overflow: hidden;
}


.encabezado {

    background: #0d47a1;

    color: white;

    padding: 25px;
}


.encabezado h2 {

    font-weight: 800;

    margin: 0;
}


.encabezado p {

    margin:
        6px 0 0;

    opacity: .85;
}


.icono-jurado {

    font-size: 42px;
}


.titulo {

    color: #0d47a1;

    font-weight: 800;
}


.card-principal {

    border: none;

    border-radius: 16px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.08);
}


.stat {

    background: white;

    border-radius: 15px;

    padding: 20px;

    text-align: center;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.06);
}


.stat-icon {

    font-size: 30px;

    margin-bottom: 8px;

    color: #0d47a1;
}


.stat-number {

    color: #0d47a1;

    font-size: 30px;

    font-weight: 800;
}


.stat-label {

    color: #64748b;

    font-weight: 600;
}


.mesa-ocupada {

    display: inline-block;

    background: #e7f0ff;

    color: #0d47a1;

    padding:
        7px 12px;

    border-radius: 9px;

    font-weight: 800;
}


.sin-mesa {

    display: inline-block;

    background: #fff3cd;

    color: #664d03;

    padding:
        7px 12px;

    border-radius: 9px;

    font-weight: 700;
}


.estado-abierta {

    display: inline-block;

    background: #d1e7dd;

    color: #0f5132;

    padding:
        7px 12px;

    border-radius: 9px;

    font-weight: 700;
}


.estado-cerrada {

    display: inline-block;

    background: #f8d7da;

    color: #842029;

    padding:
        7px 12px;

    border-radius: 9px;

    font-weight: 700;
}


.btn-accion {

    font-weight: 700;

    border-radius: 8px;
}


.btn-crear {

    background: #198754;

    color: white;

    border: none;
}


.btn-crear:hover {

    background: #157347;

    color: white;
}


.btn-nueva-mesa {

    background: #ffc107;

    color: #212529;

    border: none;
}


.btn-nueva-mesa:hover {

    background: #e0a800;

    color: #212529;
}


.btn-editar {

    background: #ffc107;

    color: #212529;

    border: none;
}


.btn-eliminar {

    background: #dc3545;

    color: white;

    border: none;
}


.btn-cerrar {

    background: #dc3545;

    color: white;

    border: none;
}


.btn-abrir {

    background: #198754;

    color: white;

    border: none;
}


.buscar {

    max-width: 450px;
}


.info-box {

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 12px;

    padding: 18px;

    height: 100%;
}


.footer {

    text-align: center;

    color: #64748b;

    padding:
        25px;

    margin-top: 30px;
}


.table th {

    white-space: nowrap;
}


.table td {

    vertical-align: middle;
}


@media(max-width:768px) {

    .encabezado {

        padding: 20px;
    }


    .encabezado .btn {

        width: 100%;
    }


    .table {

        font-size: 14px;
    }

}

</style>

</head>


<body>


<div class="container-fluid contenedor py-4">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="card card-jurado mb-4">


<div class="encabezado">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div class="d-flex
            align-items-center
            gap-3">


<div class="icono-jurado">

⚖️

</div>


<div>

<h2>

Gestión de Jurados

</h2>


<p>

Administre los jurados y sus mesas
de votación.

</p>

</div>


</div>


<div class="d-flex gap-2 flex-wrap">


<a
    href="admin.php"
    class="btn btn-light btn-accion"
>

<i class="bi bi-arrow-left-circle-fill"></i>

Panel Administrador

</a>


<a
    href="crear_jurado.php"
    class="btn btn-success btn-accion"
>

<i class="bi bi-person-plus-fill"></i>

Crear jurado

</a>


</div>


</div>


</div>


<div class="card-body p-4">


<!-- =================================================
     MENSAJES
================================================= -->

<?php if ($mensaje !== "") { ?>


<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>">


<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>


</div>


<?php } ?>


<!-- =================================================
     ELECCIÓN ACTUAL
================================================= -->

<?php if ($eleccionActual) { ?>


<div class="alert alert-light border">


<div class="row align-items-center">


<div class="col-md-5 mb-3 mb-md-0">


<strong>

🗳️ Elección actual

</strong>


<br>


<span class="text-primary fw-bold">

<?php

echo htmlspecialchars(
    $eleccionActual['nombre'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>


</div>


<div class="col-md-3 mb-3 mb-md-0">


<strong>

Estado

</strong>


<br>


<?php

$estadoEleccion =
    strtolower(
        trim(
            (string)$eleccionActual['estado']
        )
    );

?>


<?php if (
    $estadoEleccion === 'abierta'
) { ?>


<span class="estado-abierta">

🟢 Abierta

</span>


<?php } else { ?>


<span class="estado-cerrada">

🔴 Cerrada

</span>


<?php } ?>


</div>


<div class="col-md-4">


<strong>

Jurados sin mesa

</strong>


<br>


<?php if (
    $juradosSinMesa > 0
) { ?>


<span class="sin-mesa">

⚠️

<?php

echo $juradosSinMesa;

?>

sin mesa

</span>


<?php } else { ?>


<span class="estado-abierta">

✓ Todos tienen mesa

</span>


<?php } ?>


</div>


</div>


</div>


<?php } else { ?>


<div class="alert alert-warning">

⚠️ No hay elecciones registradas.

</div>


<?php } ?>


<!-- =================================================
     ESTADÍSTICAS
================================================= -->

<div class="row g-3 mb-4">


<div class="col-md-3">


<div class="stat">


<div class="stat-icon">

👤

</div>


<div class="stat-number">

<?php

echo $totalJurados;

?>

</div>


<div class="stat-label">

Jurados

</div>


</div>


</div>


<div class="col-md-3">


<div class="stat">


<div class="stat-icon">

🗳️

</div>


<div class="stat-number">

<?php

echo $totalMesas;

?>

</div>


<div class="stat-label">

Mesas totales

</div>


</div>


</div>


<div class="col-md-3">


<div class="stat">


<div class="stat-icon">

🟢

</div>


<div class="stat-number">

<?php

echo $mesasAbiertas;

?>

</div>


<div class="stat-label">

Mesas abiertas

</div>


</div>


</div>


<div class="col-md-3">


<div class="stat">


<div class="stat-icon">

🔴

</div>


<div class="stat-number">

<?php

echo $mesasCerradas;

?>

</div>


<div class="stat-label">

Mesas cerradas

</div>


</div>


</div>


</div>


<!-- =================================================
     BOTONES PRINCIPALES
================================================= -->

<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3">


<h4 class="titulo mb-0">

<i class="bi bi-people-fill"></i>

Jurados registrados

</h4>


<div class="d-flex gap-2 flex-wrap">


<a
    href="crear_jurado.php"
    class="btn btn-crear btn-accion"
>

<i class="bi bi-person-plus-fill"></i>

Crear jurado

</a>


<?php if (
    $juradosSinMesa > 0 &&
    $idEleccion > 0
) { ?>


<button
    type="button"
    class="btn btn-nueva-mesa btn-accion"
    data-bs-toggle="modal"
    data-bs-target="#modalNuevaMesa"
>


<i class="bi bi-box-seam"></i>

Nueva mesa

</button>


<?php } ?>


</div>


</div>


<!-- =================================================
     BUSCADOR
================================================= -->

<div class="mb-3 buscar">


<div class="input-group">


<span class="input-group-text">

<i class="bi bi-search"></i>

</span>


<input
    type="text"
    id="buscar"
    class="form-control"
    placeholder="Buscar jurado..."
    autocomplete="off"
>


</div>


</div>


<!-- =================================================
     TABLA JURADOS
================================================= -->

<div class="table-responsive mb-5">


<table
    class="table table-bordered table-hover align-middle"
>


<thead class="table-primary">


<tr>

<th>
ID
</th>

<th>
Documento
</th>

<th>
Nombre
</th>

<th>
Curso
</th>

<th>
Mesa
</th>

<th>
Estado
</th>

<th>
Acciones
</th>

</tr>


</thead>


<tbody id="tablaJurados">


<?php if (
    $totalJurados > 0
) { ?>


<?php foreach (
    $jurados as $j
) { ?>


<?php

$idJuradoFila =
    (int)$j['id'];


$mesaJuradoFila =
    obtenerMesaJurado(
        $mesas,
        $idJuradoFila
    );

?>


<tr class="fila-jurado">


<td>

<?php

echo $idJuradoFila;

?>

</td>


<td>

<strong>

<?php

echo htmlspecialchars(
    $j['documento'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>

</td>


<td class="nombre-jurado">

<?php

echo htmlspecialchars(
    $j['nombre']
    . " "
    . $j['apellido'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<td>


<?php if (
    trim(
        (string)$j['curso']
    ) !== ""
) { ?>


<span class="badge bg-secondary">

<?php

echo htmlspecialchars(
    $j['curso'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>


<?php } else { ?>


<span class="text-muted">

—

</span>


<?php } ?>


</td>


<td>


<?php if (
    $mesaJuradoFila
) { ?>


<span class="mesa-ocupada">

📦

<?php

echo htmlspecialchars(
    $mesaJuradoFila['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>


<?php } else { ?>


<span class="sin-mesa">

⚠️ Sin mesa

</span>


<?php } ?>


</td>


<td>


<?php if (
    !$mesaJuradoFila
) { ?>


<span class="sin-mesa">

⚠️ Sin mesa

</span>


<?php } else { ?>


<?php

$estadoMesaFila =
    strtolower(
        trim(
            (string)$mesaJuradoFila['estado']
        )
    );

?>


<?php if (
    $estadoMesaFila === 'abierta'
) { ?>


<span class="estado-abierta">

🟢 Abierta

</span>


<?php } else { ?>


<span class="estado-cerrada">

🔴 Cerrada

</span>


<?php } ?>


<?php } ?>


</td>


<td>


<div class="d-flex
            gap-1
            flex-wrap">


<!-- EDITAR -->

<a
    href="editar_jurado.php?id=<?php
        echo $idJuradoFila;
    ?>"
    class="btn btn-editar btn-sm btn-accion"
>


<i class="bi bi-pencil-square"></i>

Editar

</a>


<!-- ELIMINAR -->

<a
    href="crear_jurado.php?eliminar=<?php
        echo $idJuradoFila;
    ?>"
    class="btn btn-eliminar btn-sm btn-accion"
    onclick="
        return confirm(
            '¿Está seguro de eliminar este jurado?'
        );
    "
>


<i class="bi bi-trash-fill"></i>

Eliminar

</a>


<!-- CERRAR MESA -->

<?php if (
    $mesaJuradoFila &&
    strtolower(
        trim(
            (string)$mesaJuradoFila['estado']
        )
    ) === 'abierta'
) { ?>


<form
    method="POST"
    style="display:inline;"
>


<input
    type="hidden"
    name="id_mesa"
    value="<?php

echo (int)$mesaJuradoFila['id'];

?>"
>


<button
    type="submit"
    name="cerrar_mesa"
    value="1"
    class="btn btn-cerrar btn-sm btn-accion"
    onclick="
        return confirm(
            '¿Desea cerrar esta mesa?'
        );
    "
>


<i class="bi bi-lock-fill"></i>

Cerrar mesa

</button>


</form>


<?php } ?>


<!-- ABRIR MESA -->

<?php if (
    $mesaJuradoFila &&
    strtolower(
        trim(
            (string)$mesaJuradoFila['estado']
        )
    ) === 'cerrada'
) { ?>


<form
    method="POST"
    style="display:inline;"
>


<input
    type="hidden"
    name="id_mesa"
    value="<?php

echo (int)$mesaJuradoFila['id'];

?>"
>


<button
    type="submit"
    name="abrir_mesa"
    value="1"
    class="btn btn-abrir btn-sm btn-accion"
    onclick="
        return confirm(
            '¿Desea habilitar nuevamente esta mesa?'
        );
    "
>


<i class="bi bi-unlock-fill"></i>

Abrir mesa

</button>


</form>


<?php } ?>


</div>


</td>


</tr>


<?php } ?>


<?php } else { ?>


<tr>


<td
    colspan="7"
    class="text-center text-muted py-5"
>


<i class="bi bi-person-x fs-1"></i>


<br>


<strong>

No hay jurados registrados.

</strong>


<br><br>


<a
    href="crear_jurado.php"
    class="btn btn-crear btn-accion"
>


<i class="bi bi-person-plus-fill"></i>

Crear primer jurado

</a>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>


<!-- =================================================
     MESAS DE VOTACIÓN
================================================= -->

<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3">


<h4 class="titulo mb-0">

<i class="bi bi-box-seam"></i>

Mesas de votación

</h4>


<span class="badge bg-primary fs-6">

<?php

echo $totalMesas;

?>

mesas

</span>


</div>


<div class="table-responsive">


<table
    class="table table-bordered table-hover align-middle"
>


<thead class="table-primary">


<tr>

<th>
Mesa
</th>

<th>
Jurado asignado
</th>

<th>
Documento
</th>

<th>
Estado
</th>

<th>
Fecha de cierre
</th>

<th>
Acciones
</th>

</tr>


</thead>


<tbody>


<?php if (
    $totalMesas > 0
) { ?>


<?php foreach (
    $mesas as $m
) { ?>


<tr>


<td>


<span class="mesa-ocupada">

📦

<?php

echo htmlspecialchars(
    $m['nombre_mesa'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</span>


</td>


<td>


<?php if (
    !empty($m['id_jurado'])
) { ?>


<strong>

<?php

echo htmlspecialchars(
    $m['nombre_jurado']
    . " "
    . $m['apellido_jurado'],
    ENT_QUOTES,
    'UTF-8'
);

?>

</strong>


<?php } else { ?>


<span class="sin-mesa">

⚠️ Sin jurado

</span>


<?php } ?>


</td>


<td>


<?php

echo htmlspecialchars(
    $m['documento_jurado']
        ?? '—',
    ENT_QUOTES,
    'UTF-8'
);

?>

</td>


<td>


<?php

$estadoMesa =
    strtolower(
        trim(
            (string)$m['estado']
        )
    );

?>


<?php if (
    $estadoMesa === 'abierta'
) { ?>


<span class="estado-abierta">

🟢 Abierta

</span>


<?php } else { ?>


<span class="estado-cerrada">

🔴 Cerrada

</span>


<?php } ?>


</td>


<td>


<?php if (
    !empty($m['fecha_cierre'])
) {


echo htmlspecialchars(
    $m['fecha_cierre'],
    ENT_QUOTES,
    'UTF-8'
);


} else {


echo "—";


} ?>


</td>


<td>


<div class="d-flex
            gap-1
            flex-wrap">


<?php if (
    $estadoMesa === 'abierta'
) { ?>


<form
    method="POST"
    style="display:inline;"
>


<input
    type="hidden"
    name="id_mesa"
    value="<?php

echo (int)$m['id'];

?>"
>


<button
    type="submit"
    name="cerrar_mesa"
    value="1"
    class="btn btn-cerrar btn-sm btn-accion"
    onclick="
        return confirm(
            '¿Desea cerrar esta mesa?'
        );
    "
>


<i class="bi bi-lock-fill"></i>

Cerrar

</button>


</form>


<?php } else { ?>


<form
    method="POST"
    style="display:inline;"
>


<input
    type="hidden"
    name="id_mesa"
    value="<?php

echo (int)$m['id'];

?>"
>


<button
    type="submit"
    name="abrir_mesa"
    value="1"
    class="btn btn-abrir btn-sm btn-accion"
    onclick="
        return confirm(
            '¿Desea habilitar nuevamente esta mesa?'
        );
    "
>


<i class="bi bi-unlock-fill"></i>

Abrir

</button>


</form>


<?php } ?>


<?php if (
    !empty($m['id_jurado'])
) { ?>


<a
    href="editar_jurado.php?id=<?php
        echo (int)$m['id_jurado'];
    ?>"
    class="btn btn-editar btn-sm btn-accion"
>


<i class="bi bi-person-gear"></i>

Jurado

</a>


<?php } ?>


</div>


</td>


</tr>


<?php } ?>


<?php } else { ?>


<tr>


<td
    colspan="6"
    class="text-center text-muted py-5"
>


<i class="bi bi-box fs-1"></i>


<br>


<strong>

No hay mesas creadas para esta elección.

</strong>


</td>


</tr>


<?php } ?>


</tbody>


</table>


</div>


</div>

</div>


<!-- =====================================================
     INFORMACIÓN
===================================================== -->

<div class="card card-principal mt-4">


<div class="card-body">


<div class="row">


<div class="col-md-4 mb-3 mb-md-0">


<div class="info-box">


<strong>

👤 Jurados

</strong>


<br><br>


<?php

echo $totalJurados;

?>

jurados registrados.


<br>


<?php

echo $juradosSinMesa;

?>

sin mesa asignada.


</div>


</div>


<div class="col-md-4 mb-3 mb-md-0">


<div class="info-box">


<strong>

🗳️ Mesas

</strong>


<br><br>


<?php

echo $mesasOcupadas;

?>

mesas asignadas.


<br>


<?php

echo $mesasDisponibles;

?>

mesas disponibles.


</div>


</div>


<div class="col-md-4">


<div class="info-box">


<strong>

🔐 Control

</strong>


<br><br>


Solo el administrador puede:

<br>

• Crear jurados

<br>

• Crear mesas para jurados sin mesa

<br>

• Asignar mesas

<br>

• Cerrar mesas

<br>

• Abrir mesas

<br>

• Editar jurados

</div>


</div>


</div>


<hr>


<div class="text-center">


<strong>

Funcionamiento de las mesas

</strong>


<br><br>


🟢 <strong>Mesa abierta:</strong>
el jurado puede iniciar votaciones.


<br>


🔴 <strong>Mesa cerrada:</strong>
el jurado no puede iniciar nuevas votaciones.


<br>


👤 <strong>Jurado sin mesa:</strong>
el administrador puede crearle una mesa desde
<strong>Nueva mesa</strong>.


<br>


🔒 <strong>El jurado no puede abrir su propia mesa.</strong>


<br>


📦 <strong>Al crear un jurado nuevo:</strong>
su mesa se crea automáticamente desde
<strong>crear_jurado.php</strong>.

</div>


</div>

</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<div class="footer">


<strong>

Sistema de Votaciones Escolares v2.0

</strong>


<br>


© <?php echo date("Y"); ?>

Todos los derechos reservados.


</div>


</div>


<!-- =====================================================
     MODAL NUEVA MESA
===================================================== -->

<div
    class="modal fade"
    id="modalNuevaMesa"
    tabindex="-1"
    aria-hidden="true"
>


<div
    class="modal-dialog modal-dialog-centered"
>


<div class="modal-content">


<div class="modal-header bg-primary text-white">


<h5 class="modal-title">

<i class="bi bi-box-seam"></i>

Nueva mesa para jurado

</h5>


<button
    type="button"
    class="btn-close btn-close-white"
    data-bs-dismiss="modal"
    aria-label="Cerrar"
></button>


</div>


<form method="POST">


<div class="modal-body">


<div class="alert alert-info">


<i class="bi bi-info-circle-fill"></i>


<strong>

Esta opción es únicamente para un jurado que no tenga mesa.

</strong>


<br><br>


Al seleccionar un jurado, el sistema creará
automáticamente la siguiente mesa disponible
y la asignará a ese jurado.


</div>


<div class="mb-3">


<label
    for="id_jurado"
    class="form-label fw-bold"
>

Seleccionar jurado sin mesa

</label>


<select
    name="id_jurado"
    id="id_jurado"
    class="form-select"
    required
>


<option value="">

-- Seleccione un jurado --

</option>


<?php foreach (
    $jurados as $jurado
) { ?>


<?php

$idJ =
    (int)$jurado['id'];


$mesaDelJurado =
    obtenerMesaJurado(
        $mesas,
        $idJ
    );


?>


<?php if (
    !$mesaDelJurado
) { ?>


<option
    value="<?php
        echo $idJ;
    ?>"
>


<?php

echo htmlspecialchars(
    $jurado['nombre']
    . " "
    . $jurado['apellido'],
    ENT_QUOTES,
    'UTF-8'
);

?>


—

<?php

echo htmlspecialchars(
    $jurado['documento'],
    ENT_QUOTES,
    'UTF-8'
);

?>


</option>


<?php } ?>


<?php } ?>


</select>


</div>


<?php if (
    $juradosSinMesa > 0
) { ?>


<div class="alert alert-success mb-0">


🗳️ Hay

<strong>

<?php

echo $juradosSinMesa;

?>

</strong>

jurado(s) sin mesa.


<br>


La nueva mesa quedará asignada
automáticamente al jurado seleccionado.

</div>


<?php } else { ?>


<div class="alert alert-warning mb-0">


⚠️ Todos los jurados ya tienen una mesa
asignada.


</div>


<?php } ?>


</div>


<div class="modal-footer">


<button
    type="button"
    class="btn btn-secondary"
    data-bs-dismiss="modal"
>

Cancelar

</button>


<?php if (
    $juradosSinMesa > 0
) { ?>


<button
    type="submit"
    name="crear_mesa"
    value="1"
    class="btn btn-success"
>


<i class="bi bi-plus-circle-fill"></i>

Crear y asignar mesa

</button>


<?php } ?>


</div>


</form>


</div>

</div>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   BUSCADOR DE JURADOS
========================================================= */

const buscador =
    document.getElementById("buscar");


const tabla =
    document.getElementById("tablaJurados");


if (
    buscador &&
    tabla
) {

    buscador.addEventListener(
        "input",
        function () {

            const texto =
                this.value
                    .toLowerCase()
                    .trim();


            const filas =
                tabla.querySelectorAll(
                    ".fila-jurado"
                );


            filas.forEach(
                function (fila) {

                    const contenido =
                        fila.textContent
                            .toLowerCase();


                    if (
                        contenido.includes(texto)
                    ) {

                        fila.style.display =
                            "";

                    } else {

                        fila.style.display =
                            "none";
                    }
                }
            );
        }
    );
}

</script>


</body>

</html>
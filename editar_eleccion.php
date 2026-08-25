<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR ID
========================================================= */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: elecciones.php?error=eleccion_invalida");
    exit();

}


$idEleccion = (int)$_GET['id'];


if ($idEleccion <= 0) {

    header("Location: elecciones.php?error=eleccion_invalida");
    exit();

}


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "";

$nombre = "";
$descripcion = "";
$fecha_inicio = "";
$fecha_fin = "";

$seleccionados = [];


/* =========================================================
   OBTENER ELECCIÓN
========================================================= */

$stmt = $conn->prepare("

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

    die("No se pudo consultar la elección.");

}


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado = $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header("Location: elecciones.php?error=no_encontrada");

    exit();

}


$eleccion = $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   DATOS INICIALES
========================================================= */

$nombre =
    $eleccion['nombre'];

$descripcion =
    $eleccion['descripcion'];

$fecha_inicio =
    date(
        'Y-m-d\TH:i',
        strtotime(
            $eleccion['fecha_inicio']
        )
    );

$fecha_fin =
    date(
        'Y-m-d\TH:i',
        strtotime(
            $eleccion['fecha_fin']
        )
    );


$estadoActual =
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    );


/* =========================================================
   CARGOS ACTUALES
========================================================= */

$stmt = $conn->prepare("

    SELECT
        id_cargo

    FROM eleccion_cargos

    WHERE id_eleccion = ?

");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado = $stmt->get_result();


while (
    $fila = $resultado->fetch_assoc()
) {

    $seleccionados[] =
        (int)$fila['id_cargo'];

}


$stmt->close();


/* =========================================================
   CONTAR VOTOS
========================================================= */

$stmt = $conn->prepare("

    SELECT
        COUNT(*) AS total

    FROM votos

    WHERE id_eleccion = ?

");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado = $stmt->get_result();


$fila = $resultado->fetch_assoc();


$totalVotos =
    (int)$fila['total'];


$stmt->close();


/* =========================================================
   PROCESAR FORMULARIO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['actualizar'])
) {


    /* =====================================================
       RECIBIR DATOS
    ===================================================== */

    $nombre =
        trim(
            $_POST['nombre'] ?? ""
        );


    $descripcion =
        trim(
            $_POST['descripcion'] ?? ""
        );


    $fecha_inicio =
        trim(
            $_POST['fecha_inicio'] ?? ""
        );


    $fecha_fin =
        trim(
            $_POST['fecha_fin'] ?? ""
        );


    $cargosSeleccionados =
        $_POST['cargos'] ?? [];


    if (
        !is_array(
            $cargosSeleccionados
        )
    ) {

        $cargosSeleccionados = [];

    }


    /* =====================================================
       LIMPIAR CARGOS
    ===================================================== */

    $cargosLimpios = [];


    foreach (
        $cargosSeleccionados
        as $cargo
    ) {

        $idCargo =
            filter_var(
                $cargo,
                FILTER_VALIDATE_INT
            );


        if (
            $idCargo !== false &&
            $idCargo > 0
        ) {

            $cargosLimpios[] =
                $idCargo;

        }

    }


    $cargosLimpios =
        array_values(
            array_unique(
                $cargosLimpios
            )
        );


    $seleccionados =
        $cargosLimpios;


    /* =====================================================
       VALIDAR NOMBRE
    ===================================================== */

    if (
        $nombre === ""
    ) {

        $mensaje =
            "El nombre de la elección es obligatorio.";

        $tipoMensaje =
            "danger";

    }


    elseif (
        mb_strlen($nombre) < 3
    ) {

        $mensaje =
            "El nombre debe tener al menos 3 caracteres.";

        $tipoMensaje =
            "danger";

    }


    /* =====================================================
       VALIDAR FECHAS
    ===================================================== */

    elseif (
        $fecha_inicio === "" ||
        $fecha_fin === ""
    ) {

        $mensaje =
            "Debe ingresar las fechas de inicio y finalización.";

        $tipoMensaje =
            "danger";

    }


    else {


        $inicio =
            DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $fecha_inicio
            );


        $fin =
            DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $fecha_fin
            );


        if (
            !$inicio ||
            !$fin
        ) {

            $mensaje =
                "Las fechas ingresadas no son válidas.";

            $tipoMensaje =
                "danger";

        }


        elseif (
            $fin <= $inicio
        ) {

            $mensaje =
                "La fecha de finalización debe ser posterior a la fecha de inicio.";

            $tipoMensaje =
                "danger";

        }


        elseif (
            count($cargosLimpios) === 0
        ) {

            $mensaje =
                "Debe seleccionar al menos un cargo.";

            $tipoMensaje =
                "danger";

        }


        else {


            /* =================================================
               SI YA HAY VOTOS
            ================================================= */

            if (
                $totalVotos > 0
            ) {


                $cargosOriginales =
                    $seleccionados;


                /*
                 * Recuperamos los cargos reales
                 * de la elección.
                 */

                $stmtOriginales =
                    $conn->prepare("

                        SELECT
                            id_cargo

                        FROM eleccion_cargos

                        WHERE id_eleccion = ?

                    ");


                $stmtOriginales->bind_param(
                    "i",
                    $idEleccion
                );


                $stmtOriginales->execute();


                $resultadoOriginales =
                    $stmtOriginales->get_result();


                $cargosOriginales = [];


                while (
                    $filaOriginal =
                    $resultadoOriginales->fetch_assoc()
                ) {

                    $cargosOriginales[] =
                        (int)$filaOriginal['id_cargo'];

                }


                $stmtOriginales->close();


                sort(
                    $cargosOriginales
                );


                $cargosComparar =
                    $cargosLimpios;


                sort(
                    $cargosComparar
                );


                if (
                    $cargosOriginales !==
                    $cargosComparar
                ) {

                    $mensaje =
                        "Esta elección ya tiene votos registrados y sus cargos no pueden modificarse.";

                    $tipoMensaje =
                        "danger";

                }

            }


            /* =================================================
               GUARDAR CAMBIOS
            ================================================= */

            if (
                $mensaje === ""
            ) {


                $conn->begin_transaction();


                try {


                    /* =========================================
                       COMPROBAR NOMBRE DUPLICADO
                    ========================================= */

                    $stmtDuplicado =
                        $conn->prepare("

                            SELECT
                                id

                            FROM elecciones

                            WHERE LOWER(TRIM(nombre))
                                =
                                LOWER(TRIM(?))

                            AND id <> ?

                            LIMIT 1

                        ");


                    if (!$stmtDuplicado) {

                        throw new Exception(
                            "No se pudo comprobar el nombre."
                        );

                    }


                    $stmtDuplicado->bind_param(
                        "si",
                        $nombre,
                        $idEleccion
                    );


                    $stmtDuplicado->execute();


                    $resultadoDuplicado =
                        $stmtDuplicado->get_result();


                    if (
                        $resultadoDuplicado->num_rows > 0
                    ) {

                        $stmtDuplicado->close();

                        throw new Exception(
                            "Ya existe otra elección con ese nombre."
                        );

                    }


                    $stmtDuplicado->close();


                    /* =========================================
                       ACTUALIZAR DATOS
                    ========================================= */

                    /*
                     * IMPORTANTE:
                     * El estado NO se modifica aquí.
                     *
                     * Para abrir:
                     * abrir_eleccion.php
                     *
                     * Para cerrar:
                     * cerrar_eleccion.php
                     */

                    $stmtActualizar =
                        $conn->prepare("

                            UPDATE elecciones

                            SET
                                nombre = ?,
                                descripcion = ?,
                                fecha_inicio = ?,
                                fecha_fin = ?

                            WHERE id = ?

                        ");


                    if (!$stmtActualizar) {

                        throw new Exception(
                            "No se pudo preparar la actualización."
                        );

                    }


                    $stmtActualizar->bind_param(

                        "ssssi",

                        $nombre,
                        $descripcion,
                        $fecha_inicio,
                        $fecha_fin,
                        $idEleccion

                    );


                    if (
                        !$stmtActualizar->execute()
                    ) {

                        $stmtActualizar->close();

                        throw new Exception(
                            "No se pudo actualizar la elección."
                        );

                    }


                    $stmtActualizar->close();


                    /* =========================================
                       ACTUALIZAR CARGOS
                    ========================================= */

                    /*
                     * Si ya existen votos no tocamos
                     * la relación de cargos.
                     */

                    if (
                        $totalVotos === 0
                    ) {


                        $stmtEliminar =
                            $conn->prepare("

                                DELETE FROM eleccion_cargos

                                WHERE id_eleccion = ?

                            ");


                        if (!$stmtEliminar) {

                            throw new Exception(
                                "No se pudieron actualizar los cargos."
                            );

                        }


                        $stmtEliminar->bind_param(
                            "i",
                            $idEleccion
                        );


                        if (
                            !$stmtEliminar->execute()
                        ) {

                            $stmtEliminar->close();

                            throw new Exception(
                                "No se pudieron eliminar los cargos anteriores."
                            );

                        }


                        $stmtEliminar->close();


                        /* =================================
                           INSERTAR NUEVOS CARGOS
                        ================================= */

                        $stmtCargo =
                            $conn->prepare("

                                INSERT INTO eleccion_cargos
                                (
                                    id_eleccion,
                                    id_cargo
                                )

                                VALUES
                                (
                                    ?,
                                    ?
                                )

                            ");


                        if (!$stmtCargo) {

                            throw new Exception(
                                "No se pudo preparar la asignación de cargos."
                            );

                        }


                        foreach (
                            $cargosLimpios
                            as $idCargo
                        ) {


                            /* =============================
                               VERIFICAR CARGO
                            ============================= */

                            $stmtExiste =
                                $conn->prepare("

                                    SELECT
                                        id

                                    FROM cargos

                                    WHERE id = ?

                                    LIMIT 1

                                ");


                            if (!$stmtExiste) {

                                throw new Exception(
                                    "No se pudo validar un cargo."
                                );

                            }


                            $stmtExiste->bind_param(
                                "i",
                                $idCargo
                            );


                            $stmtExiste->execute();


                            $resultadoCargo =
                                $stmtExiste->get_result();


                            if (
                                $resultadoCargo->num_rows === 0
                            ) {

                                $stmtExiste->close();

                                throw new Exception(
                                    "Uno de los cargos seleccionados no existe."
                                );

                            }


                            $stmtExiste->close();


                            /* =============================
                               INSERTAR
                            ============================= */

                            $stmtCargo->bind_param(
                                "ii",
                                $idEleccion,
                                $idCargo
                            );


                            if (
                                !$stmtCargo->execute()
                            ) {

                                throw new Exception(
                                    "No se pudo asignar uno de los cargos."
                                );

                            }

                        }


                        $stmtCargo->close();

                    }


                    /* =========================================
                       CONFIRMAR
                    ========================================= */

                    $conn->commit();


                    header(
                        "Location: elecciones.php?actualizada=1"
                    );

                    exit();


                } catch (
                    Throwable $e
                ) {


                    $conn->rollback();


                    error_log(
                        "Error editar_eleccion.php: "
                        .
                        $e->getMessage()
                    );


                    $mensaje =
                        $e->getMessage();

                    $tipoMensaje =
                        "danger";

                }

            }

        }

    }

}


/* =========================================================
   LISTAR CARGOS
========================================================= */

$cargos =
    $conn->query("

        SELECT
            id,
            nombre_cargo

        FROM cargos

        ORDER BY nombre_cargo ASC

    ");


if (!$cargos) {

    die(
        "Error al consultar los cargos: "
        .
        htmlspecialchars(
            $conn->error
        )
    );

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
Editar Elección
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width: 950px;

    margin: auto;

    padding: 35px 20px;

}


.card {

    border: none;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.card-header {

    background: #0d6efd;

    color: white;

    padding: 25px;

}


.card-header h2 {

    margin: 0;

    font-weight: bold;

}


.card-body {

    padding: 30px;

}


.form-label {

    font-weight: bold;

    color: #333;

}


.form-control {

    min-height: 45px;

    border-radius: 9px;

}


textarea.form-control {

    min-height: 110px;

}


.cargos {

    background: #f5f8fc;

    border-radius: 12px;

    padding: 20px;

}


.cargo {

    background: white;

    border: 1px solid #dee2e6;

    border-radius: 9px;

    padding: 13px 15px;

    margin-bottom: 10px;

    transition: .2s;

}


.cargo:hover {

    background: #eef5ff;

    border-color: #1473ed;

}


.cargo:last-child {

    margin-bottom: 0;

}


.info {

    background: #cff4fc;

    border: 1px solid #b6effb;

    color: #055160;

    border-radius: 10px;

    padding: 15px;

}


.btn {

    border-radius: 9px;

    font-weight: bold;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="card-header">


<h2>

<i class="bi bi-pencil-square"></i>

Editar Elección

</h2>


<p class="mb-0 mt-1">

Modifica la información de la elección.

</p>


</div>


<div class="card-body">


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if (
    $mensaje !== ""
) { ?>


<div
class="alert alert-<?php echo htmlspecialchars(
    $tipoMensaje
); ?> alert-dismissible fade show">


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
     INFORMACIÓN
===================================================== -->

<div class="info mb-4">


<i class="bi bi-info-circle-fill"></i>


<strong>
Importante:
</strong>


Desde esta pantalla se modifica la información
de la elección.


El estado se controla mediante las acciones
<strong>Abrir</strong> y <strong>Cerrar</strong>
de Gestión de Elecciones.


</div>


<!-- =====================================================
     AVISO DE VOTOS
===================================================== -->

<?php if (
    $totalVotos > 0
) { ?>


<div class="alert alert-warning">


<i class="bi bi-shield-exclamation"></i>


<strong>

Esta elección ya tiene
<?php echo $totalVotos; ?>
voto(s).

</strong>


<br>


Los cargos están bloqueados para proteger
la integridad de los votos registrados.


</div>


<?php } ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
method="POST"
autocomplete="off">


<!-- =====================================================
     NOMBRE
===================================================== -->

<div class="mb-3">


<label
for="nombre"
class="form-label">


<i class="bi bi-card-heading"></i>

Nombre de la elección


</label>


<input

type="text"

id="nombre"

name="nombre"

class="form-control"

maxlength="150"

required

value="<?php

echo htmlspecialchars(
    $nombre
);

?>">


</div>


<!-- =====================================================
     DESCRIPCIÓN
===================================================== -->

<div class="mb-3">


<label
for="descripcion"
class="form-label">


<i class="bi bi-text-paragraph"></i>

Descripción


</label>


<textarea

id="descripcion"

name="descripcion"

class="form-control"

maxlength="500"

placeholder="Descripción de la elección..."><?php

echo htmlspecialchars(
    $descripcion
);

?></textarea>


</div>


<!-- =====================================================
     FECHAS
===================================================== -->

<div class="row">


<div class="col-md-6 mb-3">


<label
for="fecha_inicio"
class="form-label">


<i class="bi bi-calendar-plus"></i>

Fecha de inicio


</label>


<input

type="datetime-local"

id="fecha_inicio"

name="fecha_inicio"

class="form-control"

required

value="<?php

echo htmlspecialchars(
    $fecha_inicio
);

?>">


</div>


<div class="col-md-6 mb-3">


<label
for="fecha_fin"

class="form-label">


<i class="bi bi-calendar-check"></i>

Fecha de finalización


</label>


<input

type="datetime-local"

id="fecha_fin"

name="fecha_fin"

class="form-control"

required

value="<?php

echo htmlspecialchars(
    $fecha_fin
);

?>">


</div>


</div>


<!-- =====================================================
     ESTADO ACTUAL
===================================================== -->

<div class="mb-4">


<label class="form-label">

<i class="bi bi-toggle-on"></i>

Estado actual

</label>


<div>


<?php if (
    $estadoActual === 'abierta'
) { ?>


<span class="badge bg-success p-2">

🟢 Elección abierta

</span>


<?php } else { ?>


<span class="badge bg-secondary p-2">

⚪ Elección cerrada

</span>


<?php } ?>


</div>


</div>


<hr>


<!-- =====================================================
     CARGOS
===================================================== -->

<h4 class="mb-3">


<i class="bi bi-person-badge-fill"></i>

Cargos de la elección


</h4>


<?php if (
    $totalVotos > 0
) { ?>


<p class="text-muted">

Los cargos están bloqueados porque ya existen
votos registrados.

</p>


<?php } else { ?>


<p class="text-muted">

Selecciona los cargos que estarán disponibles
en esta elección.

</p>


<?php } ?>


<div class="cargos">


<?php if (
    $cargos->num_rows === 0
) { ?>


<div class="alert alert-warning mb-0">


<i class="bi bi-exclamation-triangle-fill"></i>


No existen cargos registrados.


</div>


<?php } else { ?>


<?php while (
    $cargo =
    $cargos->fetch_assoc()
) { ?>


<div class="cargo">


<div class="form-check">


<input

type="checkbox"

class="form-check-input"

name="cargos[]"

value="<?php

echo (int)$cargo['id'];

?>"

id="cargo_<?php

echo (int)$cargo['id'];

?>"

<?php

if (
    in_array(
        (int)$cargo['id'],
        $seleccionados,
        true
    )
) {

    echo "checked";

}

?>

<?php

if (
    $totalVotos > 0
) {

    echo " disabled";

}

?>

>


<label

class="form-check-label"

for="cargo_<?php

echo (int)$cargo['id'];

?>">


<strong>

<?php

echo htmlspecialchars(
    $cargo['nombre_cargo']
);

?>

</strong>


</label>


</div>


</div>


<?php } ?>


<?php } ?>


</div>


<!-- =====================================================
     BOTONES
===================================================== -->

<div class="d-flex
            justify-content-between
            flex-wrap
            gap-2
            mt-4">


<a
href="elecciones.php"
class="btn btn-outline-secondary">


<i class="bi bi-arrow-left-circle"></i>

Cancelar


</a>


<button

type="submit"

name="actualizar"

class="btn btn-primary">


<i class="bi bi-check-circle-fill"></i>

Guardar cambios


</button>


</div>


</form>


</div>


</div>


</div>


</body>

</html>
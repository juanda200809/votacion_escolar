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


/* =========================================================
   DATOS DEL FORMULARIO
========================================================= */

$nombre = "";
$descripcion = "";
$fecha_inicio = "";
$fecha_fin = "";

$cargosMarcados = [];


/* =========================================================
   GUARDAR ELECCIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['guardar'])
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


    $cargosMarcados =
        $_POST['cargos'] ?? [];


    /* =====================================================
       VALIDAR CARGOS COMO ARRAY
    ===================================================== */

    if (
        !is_array($cargosMarcados)
    ) {

        $cargosMarcados = [];

    }


    /* =====================================================
       LIMPIAR CARGOS
    ===================================================== */

    $cargosLimpios = [];


    foreach (
        $cargosMarcados as $cargo
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


    /* =====================================================
       VALIDAR NOMBRE
    ===================================================== */

    if (
        $nombre === ""
    ) {

        $mensaje =
            "Debe ingresar el nombre de la elección.";

        $tipoMensaje =
            "danger";

    }


    elseif (
        mb_strlen($nombre) < 3
    ) {

        $mensaje =
            "El nombre de la elección debe tener al menos 3 caracteres.";

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
            "Debe ingresar la fecha y hora de inicio y finalización.";

        $tipoMensaje =
            "danger";

    }


    else {


        /* =================================================
           CREAR OBJETOS DE FECHA
        ================================================= */

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


        $erroresInicio =
            DateTime::getLastErrors();


        /*
         * En algunas versiones de PHP getLastErrors()
         * puede devolver false cuando no existen errores.
         */

        if (
            $erroresInicio === false
        ) {

            $erroresInicio = [
                'warning_count' => 0,
                'error_count' => 0
            ];

        }


        if (
            !$inicio ||
            !$fin
        ) {

            $mensaje =
                "Las fechas ingresadas no tienen un formato válido.";

            $tipoMensaje =
                "danger";

        }


        elseif (
            $inicio >= $fin
        ) {

            $mensaje =
                "La fecha de finalización debe ser posterior a la fecha de inicio.";

            $tipoMensaje =
                "danger";

        }


        /* =================================================
           VALIDAR CARGOS
        ================================================= */

        elseif (
            count($cargosLimpios) === 0
        ) {

            $mensaje =
                "Debe seleccionar al menos un cargo.";

            $tipoMensaje =
                "danger";

        }


        else {


            /* =============================================
               TRANSACCIÓN
            ============================================= */

            $conn->begin_transaction();


            try {


                /* =========================================
                   1. VALIDAR QUE TODOS LOS CARGOS EXISTAN
                ========================================= */

                $stmtCargo =
                    $conn->prepare("

                        SELECT
                            id

                        FROM cargos

                        WHERE id = ?

                        LIMIT 1

                    ");


                if (!$stmtCargo) {

                    throw new Exception(
                        "No se pudo preparar la validación de cargos."
                    );

                }


                foreach (
                    $cargosLimpios as $idCargo
                ) {


                    $stmtCargo->bind_param(
                        "i",
                        $idCargo
                    );


                    $stmtCargo->execute();


                    $resultadoCargo =
                        $stmtCargo->get_result();


                    if (
                        $resultadoCargo->num_rows === 0
                    ) {

                        throw new Exception(
                            "Uno de los cargos seleccionados no existe."
                        );

                    }

                }


                $stmtCargo->close();


                /* =========================================
                   2. COMPROBAR NOMBRE DUPLICADO
                ========================================= */

                $stmtDuplicado =
                    $conn->prepare("

                        SELECT
                            id

                        FROM elecciones

                        WHERE LOWER(TRIM(nombre))
                            =
                            LOWER(TRIM(?))

                        LIMIT 1

                    ");


                if (!$stmtDuplicado) {

                    throw new Exception(
                        "No se pudo comprobar el nombre de la elección."
                    );

                }


                $stmtDuplicado->bind_param(
                    "s",
                    $nombre
                );


                $stmtDuplicado->execute();


                $resultadoDuplicado =
                    $stmtDuplicado->get_result();


                if (
                    $resultadoDuplicado->num_rows > 0
                ) {

                    $stmtDuplicado->close();

                    throw new Exception(
                        "Ya existe una elección con ese nombre."
                    );

                }


                $stmtDuplicado->close();


                /* =========================================
                   3. CREAR ELECCIÓN
                ========================================= */

                /*
                 * Siempre comienza cerrada.
                 */

                $estado = "cerrada";


                $stmt =
                    $conn->prepare("

                        INSERT INTO elecciones
                        (
                            nombre,
                            descripcion,
                            fecha_inicio,
                            fecha_fin,
                            estado
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


                if (!$stmt) {

                    throw new Exception(
                        "No se pudo preparar la creación de la elección."
                    );

                }


                $stmt->bind_param(
                    "sssss",
                    $nombre,
                    $descripcion,
                    $fecha_inicio,
                    $fecha_fin,
                    $estado
                );


                if (
                    !$stmt->execute()
                ) {

                    $stmt->close();

                    throw new Exception(
                        "No se pudo crear la elección."
                    );

                }


                $idEleccion =
                    (int)$conn->insert_id;


                $stmt->close();


                /* =========================================
                   4. ASIGNAR CARGOS
                ========================================= */

                $stmtRelacion =
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


                if (!$stmtRelacion) {

                    throw new Exception(
                        "No se pudo preparar la asignación de cargos."
                    );

                }


                foreach (
                    $cargosLimpios as $idCargo
                ) {


                    $stmtRelacion->bind_param(
                        "ii",
                        $idEleccion,
                        $idCargo
                    );


                    if (
                        !$stmtRelacion->execute()
                    ) {

                        throw new Exception(
                            "No se pudo asignar uno de los cargos."
                        );

                    }

                }


                $stmtRelacion->close();


                /* =========================================
                   5. CONFIRMAR
                ========================================= */

                $conn->commit();


                /* =========================================
                   6. REDIRIGIR
                ========================================= */

                header(
                    "Location: elecciones.php?creada=1"
                );

                exit();


            } catch (
                Throwable $e
            ) {


                /* =========================================
                   CANCELAR
                ========================================= */

                $conn->rollback();


                error_log(
                    "Error crear_eleccion.php: "
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
Nueva Elección
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

    background: #198754;

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

    border-radius: 9px;

    min-height: 45px;

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

<i class="bi bi-calendar-plus-fill"></i>

Nueva Elección

</h2>


<p class="mb-0 mt-1">

Configura una nueva jornada de votación.

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


La elección se creará inicialmente como
<strong>cerrada</strong>.


Después podrás abrirla desde
<strong>Gestión de Elecciones</strong>
cuando los candidatos y cargos estén correctamente configurados.


</div>


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

placeholder="Ejemplo: Elecciones Personero 2026"

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

Fecha y hora de inicio


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

Fecha y hora de finalización


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
     CARGOS
===================================================== -->

<hr class="my-4">


<h4 class="mb-3">

<i class="bi bi-person-badge-fill"></i>

Cargos de la elección

</h4>


<p class="text-muted">

Selecciona los cargos que estarán disponibles
en esta elección.

</p>


<div class="cargos">


<?php if (
    $cargos->num_rows === 0
) { ?>


<div class="alert alert-warning mb-0">


<i class="bi bi-exclamation-triangle-fill"></i>


No existen cargos registrados en el sistema.


</div>


<?php } else { ?>


<?php while (
    $cargo =
    $cargos->fetch_assoc()
) { ?>


<div class="cargo">


<div class="form-check">


<input

class="form-check-input"

type="checkbox"

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
        $cargosMarcados,
        true
    )
) {

    echo "checked";

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


<?php if (
    $cargos->num_rows > 0
) { ?>


<button

type="submit"

name="guardar"

class="btn btn-success">


<i class="bi bi-check-circle-fill"></i>

Crear elección


</button>


<?php } ?>


</div>


</form>


</div>


</div>


</div>


</body>

</html>
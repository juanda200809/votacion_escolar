<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR ADMINISTRADOR
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'administrador'
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";


/* =========================================================
   GUARDAR ELECCIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['guardar'])
) {


    $nombre =
        trim(
            $_POST['nombre'] ?? ""
        );


    $descripcion =
        trim(
            $_POST['descripcion'] ?? ""
        );


    $fecha_inicio =
        $_POST['fecha_inicio'] ?? "";


    $fecha_fin =
        $_POST['fecha_fin'] ?? "";


    /*
     * Las nuevas elecciones se crean cerradas.
     * Después el administrador puede abrirlas.
     */
    $estado = "cerrada";


    $cargosSeleccionados =
        $_POST['cargos'] ?? [];


    /* =====================================================
       VALIDAR NOMBRE
    ===================================================== */

    if ($nombre === "") {

        $mensaje =
            "Debe ingresar el nombre de la elección.";

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
            "Debe ingresar la fecha de inicio y la fecha de finalización.";

        $tipoMensaje =
            "danger";

    }


    else {


        $inicio =
            strtotime($fecha_inicio);


        $fin =
            strtotime($fecha_fin);


        if (
            $inicio === false ||
            $fin === false
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


        /* =================================================
           VALIDAR CARGOS
        ================================================= */

        elseif (
            !is_array($cargosSeleccionados) ||
            count($cargosSeleccionados) === 0
        ) {

            $mensaje =
                "Debe seleccionar al menos un cargo.";

            $tipoMensaje =
                "danger";

        }


        else {


            /* =============================================
               LIMPIAR IDS DE CARGOS
            ============================================= */

            $cargosLimpios = [];


            foreach (
                $cargosSeleccionados
                as $cargo
            ) {

                $idCargo =
                    (int)$cargo;


                if (
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


            if (
                count($cargosLimpios) === 0
            ) {

                $mensaje =
                    "Debe seleccionar cargos válidos.";

                $tipoMensaje =
                    "danger";

            } else {


                /* =========================================
                   INICIAR TRANSACCIÓN
                ========================================= */

                $conn->begin_transaction();


                try {


                    /* =====================================
                       INSERTAR ELECCIÓN
                    ===================================== */

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

                        throw new Exception(
                            "No se pudo crear la elección."
                        );

                    }


                    $idEleccion =
                        (int)$conn->insert_id;


                    $stmt->close();


                    /* =====================================
                       INSERTAR CARGOS
                    ===================================== */

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
                           COMPROBAR QUE EL CARGO EXISTE
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
                                "No se pudo validar uno de los cargos."
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
                           GUARDAR RELACIÓN
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


                    /* =====================================
                       CONFIRMAR TRANSACCIÓN
                    ===================================== */

                    $conn->commit();


                    header(
                        "Location: elecciones.php?creada=1"
                    );

                    exit();


                } catch (
                    Exception $e
                ) {


                    $conn->rollback();


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
        . htmlspecialchars(
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

body {

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width:900px;

    margin:auto;

    padding:35px 20px;

}


.card {

    border:none;

    border-radius:20px;

    overflow:hidden;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.card-header {

    background:#198754;

    color:white;

    padding:22px 25px;

}


.card-header h2 {

    margin:0;

    font-weight:bold;

}


.card-body {

    padding:30px;

}


.form-label {

    font-weight:bold;

    color:#333;

}


.form-control,
.form-select {

    border-radius:9px;

    min-height:45px;

}


textarea.form-control {

    min-height:110px;

}


.cargos {

    background:#f5f8fc;

    border-radius:12px;

    padding:20px;

}


.cargo {

    background:white;

    border:1px solid #dee2e6;

    border-radius:9px;

    padding:12px 15px;

    margin-bottom:10px;

}


.cargo:hover {

    background:#eef5ff;

}


.cargo:last-child {

    margin-bottom:0;

}


.info {

    background:#cff4fc;

    border:1px solid #b6effb;

    color:#055160;

    border-radius:10px;

    padding:15px;

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


<div class="alert alert-<?php echo htmlspecialchars(
    $tipoMensaje
); ?>">


<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars(
    $mensaje
); ?>


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
<strong>cerrada</strong>. Cuando todo esté listo,
el administrador podrá abrirla desde
<strong>Gestión de Elecciones</strong>.


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

placeholder="Ejemplo: Elecciones Personero 2026"

required

value="<?php echo htmlspecialchars(
    $_POST['nombre'] ?? ''
); ?>">


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

placeholder="Descripción de la elección..."><?php echo htmlspecialchars(
    $_POST['descripcion'] ?? ''
); ?></textarea>


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

value="<?php echo htmlspecialchars(
    $_POST['fecha_inicio'] ?? ''
); ?>">


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

value="<?php echo htmlspecialchars(
    $_POST['fecha_fin'] ?? ''
); ?>">


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


<div class="cargos">


<?php if (
    $cargos->num_rows === 0
) { ?>


<div class="alert alert-warning mb-0">


<i class="bi bi-exclamation-triangle-fill"></i>


No existen cargos registrados.


<br><br>


<a
href="cargos.php"
class="btn btn-warning btn-sm">


Crear cargos


</a>


</div>


<?php } else { ?>


<?php

$cargosMarcados =
    $_POST['cargos'] ?? [];

?>


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

value="<?php echo (int)$cargo['id']; ?>"

id="cargo<?php echo (int)$cargo['id']; ?>"

<?php

if (
    in_array(
        $cargo['id'],
        $cargosMarcados
    )
) {

    echo "checked";

}

?>


>


<label

class="form-check-label"

for="cargo<?php echo (int)$cargo['id']; ?>">


<strong>

<?php echo htmlspecialchars(
    $cargo['nombre_cargo']
); ?>

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
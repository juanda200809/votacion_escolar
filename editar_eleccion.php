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
   VERIFICAR ID
========================================================= */

if (
    !isset($_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    header("Location: elecciones.php");
    exit();

}


$id =
    (int)$_GET['id'];


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


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header("Location: elecciones.php");
    exit();

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";


/* =========================================================
   ACTUALIZAR ELECCIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['actualizar'])
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


    $estado =
        strtolower(
            trim(
                $_POST['estado'] ?? "cerrada"
            )
        );


    $cargosSeleccionados =
        $_POST['cargos'] ?? [];


    /* =====================================================
       VALIDAR ESTADO
    ===================================================== */

    if (
        $estado !== "abierta" &&
        $estado !== "cerrada"
    ) {

        $estado =
            "cerrada";

    }


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
           LIMPIAR CARGOS
        ================================================= */

        else {


            if (
                !is_array(
                    $cargosSeleccionados
                )
            ) {

                $cargosSeleccionados =
                    [];

            }


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
                    "Debe seleccionar al menos un cargo.";

                $tipoMensaje =
                    "danger";

            }


            else {


                /* =================================================
                   COMPROBAR SI EXISTEN VOTOS
                ================================================= */

                $stmtVotos =
                    $conn->prepare("

                        SELECT
                            COUNT(*) AS total

                        FROM votos v

                        INNER JOIN candidatos c
                            ON c.id = v.id_candidato

                        WHERE c.id_eleccion = ?

                    ");


                $stmtVotos->bind_param(
                    "i",
                    $id
                );


                $stmtVotos->execute();


                $resultadoVotos =
                    $stmtVotos->get_result();


                $datosVotos =
                    $resultadoVotos->fetch_assoc();


                $totalVotos =
                    (int)$datosVotos['total'];


                $stmtVotos->close();


                /* =================================================
                   SI YA HAY VOTOS
                ================================================= */

                if (
                    $totalVotos > 0
                ) {


                    /*
                     * Para no dañar los votos existentes,
                     * no permitimos cambiar los cargos
                     * de una elección que ya tiene votos.
                     */

                    $stmtCargosActuales =
                        $conn->prepare("

                            SELECT
                                id_cargo

                            FROM eleccion_cargos

                            WHERE id_eleccion = ?

                            ORDER BY id_cargo

                        ");


                    $stmtCargosActuales->bind_param(
                        "i",
                        $id
                    );


                    $stmtCargosActuales->execute();


                    $resultadoCargosActuales =
                        $stmtCargosActuales->get_result();


                    $cargosActuales = [];


                    while (
                        $fila =
                        $resultadoCargosActuales->fetch_assoc()
                    ) {

                        $cargosActuales[] =
                            (int)$fila['id_cargo'];

                    }


                    $stmtCargosActuales->close();


                    sort(
                        $cargosActuales
                    );


                    $cargosComparar =
                        $cargosLimpios;


                    sort(
                        $cargosComparar
                    );


                    if (
                        $cargosActuales !==
                        $cargosComparar
                    ) {

                        $mensaje =
                            "No se pueden cambiar los cargos de una elección que ya tiene votos registrados.";

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
                           SI SE QUIERE ABRIR
                        ========================================= */

                        if (
                            $estado === "abierta"
                        ) {


                            /*
                             * Cerramos cualquier otra elección
                             * abierta antes de abrir esta.
                             */

                            $stmtCerrarOtras =
                                $conn->prepare("

                                    UPDATE elecciones

                                    SET estado = 'cerrada'

                                    WHERE estado = 'abierta'

                                    AND id <> ?

                                ");


                            if (!$stmtCerrarOtras) {

                                throw new Exception(
                                    "No se pudieron cerrar las demás elecciones abiertas."
                                );

                            }


                            $stmtCerrarOtras->bind_param(
                                "i",
                                $id
                            );


                            $stmtCerrarOtras->execute();


                            $stmtCerrarOtras->close();

                        }


                        /* =========================================
                           ACTUALIZAR ELECCIÓN
                        ========================================= */

                        $stmtActualizar =
                            $conn->prepare("

                                UPDATE elecciones

                                SET
                                    nombre = ?,
                                    descripcion = ?,
                                    fecha_inicio = ?,
                                    fecha_fin = ?,
                                    estado = ?

                                WHERE id = ?

                            ");


                        if (!$stmtActualizar) {

                            throw new Exception(
                                "No se pudo preparar la actualización."
                            );

                        }


                        $stmtActualizar->bind_param(

                            "sssssi",

                            $nombre,

                            $descripcion,

                            $fecha_inicio,

                            $fecha_fin,

                            $estado,

                            $id

                        );


                        if (
                            !$stmtActualizar->execute()
                        ) {

                            throw new Exception(
                                "No se pudo actualizar la elección."
                            );

                        }


                        $stmtActualizar->close();


                        /* =========================================
                           ACTUALIZAR CARGOS
                        ========================================= */

                        /*
                         * Solo modificamos la relación de cargos
                         * si la elección todavía no tiene votos.
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
                                $id
                            );


                            $stmtEliminar->execute();


                            $stmtEliminar->close();


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

                                $stmtCargo->bind_param(
                                    "ii",
                                    $id,
                                    $idCargo
                                );


                                if (
                                    !$stmtCargo->execute()
                                ) {

                                    throw new Exception(
                                        "No se pudo guardar uno de los cargos."
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


    /*
     * Mostrar nuevamente los datos introducidos
     * si hubo algún error.
     */

    $eleccion['nombre'] =
        $nombre;

    $eleccion['descripcion'] =
        $descripcion;

    $eleccion['fecha_inicio'] =
        $fecha_inicio;

    $eleccion['fecha_fin'] =
        $fecha_fin;

    $eleccion['estado'] =
        $estado;

}


/* =========================================================
   CARGOS DISPONIBLES
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


/* =========================================================
   CARGOS ACTUALMENTE SELECCIONADOS
========================================================= */

$seleccionados = [];


$stmtRelacion =
    $conn->prepare("

        SELECT
            id_cargo

        FROM eleccion_cargos

        WHERE id_eleccion = ?

    ");


$stmtRelacion->bind_param(
    "i",
    $id
);


$stmtRelacion->execute();


$resultadoRelacion =
    $stmtRelacion->get_result();


while (
    $fila =
    $resultadoRelacion->fetch_assoc()
) {

    $seleccionados[] =
        (int)$fila['id_cargo'];

}


$stmtRelacion->close();


/* =========================================================
   SI EL FORMULARIO FUE ENVIADO Y HUBO ERROR
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['actualizar']) &&
    isset($_POST['cargos']) &&
    is_array($_POST['cargos'])
) {

    $seleccionados = [];


    foreach (
        $_POST['cargos']
        as $cargo
    ) {

        $idCargo =
            (int)$cargo;


        if (
            $idCargo > 0
        ) {

            $seleccionados[] =
                $idCargo;

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

Editar Elección

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

    background:#0d6efd;

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

}


.form-control,
.form-select {

    min-height:45px;

    border-radius:9px;

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

<i class="bi bi-pencil-square"></i>

Editar Elección

</h2>


<p class="mb-0 mt-1">

Modifica la información de esta elección.

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
     AVISO SI YA HAY VOTOS
===================================================== -->

<?php

/* Obtener cantidad de votos */

$stmtAviso =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos v

        INNER JOIN candidatos c
            ON c.id = v.id_candidato

        WHERE c.id_eleccion = ?

    ");


$stmtAviso->bind_param(
    "i",
    $id
);


$stmtAviso->execute();


$resultadoAviso =
    $stmtAviso->get_result();


$datosAviso =
    $resultadoAviso->fetch_assoc();


$totalVotosAviso =
    (int)$datosAviso['total'];


$stmtAviso->close();

?>


<?php if (
    $totalVotosAviso > 0
) { ?>


<div class="alert alert-warning">


<i class="bi bi-shield-exclamation"></i>


<strong>

Esta elección ya tiene
<?php echo $totalVotosAviso; ?>
voto(s).

</strong>


<br>


Los cargos no pueden modificarse porque
podrían afectar la relación con los votos
existentes.


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

Nombre


</label>


<input

type="text"

id="nombre"

name="nombre"

class="form-control"

required

value="<?php echo htmlspecialchars(
    $eleccion['nombre']
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

rows="4"><?php echo htmlspecialchars(
    $eleccion['descripcion']
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

Fecha de inicio


</label>


<input

type="datetime-local"

id="fecha_inicio"

name="fecha_inicio"

class="form-control"

required

value="<?php echo date(
    'Y-m-d\TH:i',
    strtotime(
        $eleccion['fecha_inicio']
    )
); ?>">


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

value="<?php echo date(
    'Y-m-d\TH:i',
    strtotime(
        $eleccion['fecha_fin']
    )
); ?>">


</div>


</div>


<!-- =====================================================
     ESTADO
===================================================== -->

<div class="mb-4">


<label
for="estado"
class="form-label">


<i class="bi bi-toggle-on"></i>

Estado


</label>


<select

name="estado"

id="estado"

class="form-select">


<option

value="cerrada"

<?php

if (
    $eleccion['estado']
    === 'cerrada'
) {

    echo 'selected';

}

?>

>

🔴 Cerrada

</option>


<option

value="abierta"

<?php

if (
    $eleccion['estado']
    === 'abierta'
) {

    echo 'selected';

}

?>

>

🟢 Abierta

</option>


</select>


</div>


<hr>


<!-- =====================================================
     CARGOS
===================================================== -->

<h4 class="mb-3">


<i class="bi bi-person-badge-fill"></i>

Cargos


</h4>


<div class="cargos">


<?php if (
    $cargos->num_rows === 0
) { ?>


<div class="alert alert-warning mb-0">


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

value="<?php echo (int)$cargo['id']; ?>"

id="cargo<?php echo (int)$cargo['id']; ?>"

<?php

if (
    in_array(
        (int)$cargo['id'],
        $seleccionados
    )
) {

    echo "checked";

}

?>

<?php

/*
 * Si existen votos, no permitimos
 * cambiar los cargos.
 */

if (
    $totalVotosAviso > 0
) {

    echo " disabled";

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
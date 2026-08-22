<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   VERIFICAR ROL
========================================================= */

$rol = strtolower(trim((string)$_SESSION['rol']));

if ($rol !== "jurado") {

    if ($rol === "administrador") {
        header("Location: admin.php");
        exit();
    }

    if ($rol === "estudiante") {
        header("Location: votar.php");
        exit();
    }

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}


/* =========================================================
   VERIFICAR VOTANTE
========================================================= */

if (
    !isset($_SESSION['votante_id']) ||
    !isset($_SESSION['eleccion_votante_id'])
) {
    header("Location: jurado.php");
    exit();
}


$idEstudiante = (int)$_SESSION['votante_id'];
$idEleccion   = (int)$_SESSION['eleccion_votante_id'];


/* =========================================================
   BUSCAR ESTUDIANTE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE id = ?
    AND LOWER(TRIM(rol)) = 'estudiante'
    LIMIT 1
");

if (!$stmt) {
    mostrarError("No fue posible consultar el estudiante.");
}

$stmt->bind_param("i", $idEstudiante);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    $stmt->close();

    limpiarVotante();

    mostrarError("El estudiante no existe o ya no está registrado.");

}

$estudiante = $resultado->fetch_assoc();

$stmt->close();


/* =========================================================
   BUSCAR ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        estado
    FROM elecciones
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $idEleccion);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    $stmt->close();

    limpiarVotante();

    mostrarError("La elección no existe.");

}

$eleccion = $resultado->fetch_assoc();

$stmt->close();


/* =========================================================
   COMPROBAR QUE LA ELECCIÓN SIGA ABIERTA
========================================================= */

if (
    strtolower(trim($eleccion['estado'])) !== "abierta"
) {
    mostrarError("La elección se encuentra cerrada.");
}


/* =========================================================
   OBTENER CARGOS DE LA ELECCIÓN
========================================================= */

$cargos = [];

$stmt = $conn->prepare("
    SELECT
        c.id,
        c.nombre_cargo
    FROM cargos c
    INNER JOIN eleccion_cargos ec
        ON ec.id_cargo = c.id
    WHERE ec.id_eleccion = ?
    ORDER BY c.id ASC
");

if (!$stmt) {
    mostrarError("No fue posible consultar los cargos.");
}

$stmt->bind_param("i", $idEleccion);
$stmt->execute();

$resultado = $stmt->get_result();

while ($cargo = $resultado->fetch_assoc()) {
    $cargos[] = $cargo;
}

$stmt->close();


if (count($cargos) === 0) {
    mostrarError(
        "Esta elección todavía no tiene cargos configurados."
    );
}


/* =========================================================
   PROCESAR VOTACIÓN
========================================================= */

$mensajeError = "";
$mensajeExito = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $votos = $_POST['voto'] ?? [];

    if (!is_array($votos)) {
        $votos = [];
    }


    /* =====================================================
       VALIDAR QUE EXISTA UN VOTO POR CADA CARGO
    ===================================================== */

    foreach ($cargos as $cargo) {

        $idCargo = (int)$cargo['id'];

        if (
            !isset($votos[$idCargo]) ||
            (int)$votos[$idCargo] <= 0
        ) {

            $mensajeError =
                "Debe seleccionar un candidato para cada cargo.";

            break;
        }
    }


    /* =====================================================
       VALIDAR CANDIDATOS
    ===================================================== */

    if ($mensajeError === "") {

        foreach ($votos as $idCargo => $idCandidato) {

            $idCargo =
                (int)$idCargo;

            $idCandidato =
                (int)$idCandidato;


            if (
                $idCargo <= 0 ||
                $idCandidato <= 0
            ) {

                $mensajeError =
                    "Se recibió una selección inválida.";

                break;
            }


            /*
             * El candidato debe pertenecer
             * exactamente a:
             *
             * - esta elección
             * - este cargo
             */

            $stmt = $conn->prepare("
                SELECT id
                FROM candidatos
                WHERE id = ?
                AND id_eleccion = ?
                AND id_cargo = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $mensajeError =
                    "No fue posible validar los candidatos.";

                break;
            }

            $stmt->bind_param(
                "iii",
                $idCandidato,
                $idEleccion,
                $idCargo
            );

            $stmt->execute();

            $resultado =
                $stmt->get_result();

            if ($resultado->num_rows === 0) {

                $mensajeError =
                    "Uno de los candidatos seleccionados no pertenece al cargo correspondiente.";

                $stmt->close();

                break;
            }

            $stmt->close();
        }
    }


    /* =====================================================
       COMPROBAR SI YA TIENE VOTOS
    ===================================================== */

    if ($mensajeError === "") {

        $stmt = $conn->prepare("
            SELECT
                COUNT(DISTINCT v.id_cargo) AS total
            FROM votos v
            INNER JOIN candidatos c
                ON c.id = v.id_candidato
            WHERE v.id_usuario = ?
            AND c.id_eleccion = ?
        ");

        if (!$stmt) {

            $mensajeError =
                "No fue posible comprobar la votación anterior.";

        } else {

            $stmt->bind_param(
                "ii",
                $idEstudiante,
                $idEleccion
            );

            $stmt->execute();

            $resultado =
                $stmt->get_result();

            $datos =
                $resultado->fetch_assoc();

            $votosExistentes =
                (int)$datos['total'];

            $stmt->close();


            if ($votosExistentes > 0) {

                $mensajeError =
                    "Este estudiante ya tiene votos registrados para esta elección.";

            }
        }
    }


    /* =====================================================
       VERIFICAR CANTIDAD EXACTA DE VOTOS
    ===================================================== */

    if ($mensajeError === "") {

        if (
            count($votos) !== count($cargos)
        ) {

            $mensajeError =
                "La cantidad de votos no coincide con los cargos de la elección.";

        }
    }


    /* =====================================================
       GUARDAR VOTOS
    ===================================================== */

    if ($mensajeError === "") {

        try {

            $conn->begin_transaction();


            $stmtInsertar = $conn->prepare("
                INSERT INTO votos
                (
                    id_usuario,
                    id_candidato,
                    fecha_voto,
                    id_cargo
                )
                VALUES
                (
                    ?,
                    ?,
                    NOW(),
                    ?
                )
            ");


            if (!$stmtInsertar) {

                throw new Exception(
                    "No fue posible preparar el registro de votos."
                );
            }


            foreach ($votos as $idCargo => $idCandidato) {

                $idCargo =
                    (int)$idCargo;

                $idCandidato =
                    (int)$idCandidato;


                $stmtInsertar->bind_param(
                    "iii",
                    $idEstudiante,
                    $idCandidato,
                    $idCargo
                );


                if (!$stmtInsertar->execute()) {

                    throw new Exception(
                        "No fue posible guardar uno de los votos."
                    );
                }
            }


            $stmtInsertar->close();


            /*
             * Confirmar TODOS los votos.
             */

            $conn->commit();


            /*
             * Eliminar información temporal
             * del estudiante.
             */

            limpiarVotante();


            $mensajeExito =
                "La votación fue registrada correctamente.";

        } catch (Exception $e) {

            /*
             * Si algo falla,
             * no se guarda ningún voto.
             */

            $conn->rollback();

            $mensajeError =
                "No fue posible registrar la votación. No se guardaron los votos.";
        }
    }
}


/* =========================================================
   FUNCIÓN LIMPIAR SESIÓN DEL VOTANTE
========================================================= */

function limpiarVotante()
{

    unset(
        $_SESSION['votante_id'],
        $_SESSION['votante_documento'],
        $_SESSION['votante_nombre'],
        $_SESSION['votante_apellido'],
        $_SESSION['votante_curso'],
        $_SESSION['eleccion_votante_id']
    );
}


/* =========================================================
   FUNCIÓN MOSTRAR ERROR
========================================================= */

function mostrarError($mensaje)
{
?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Error</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    background:#eef3f9;

    font-family:Arial, Helvetica, sans-serif;
}


.contenedor {

    max-width:700px;

    margin:100px auto;

    padding:20px;
}


.card-error {

    background:white;

    border-radius:20px;

    padding:45px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);
}


.icono {

    font-size:75px;

    color:#dc3545;
}

</style>

</head>


<body>

<div class="contenedor">

<div class="card-error">

<i
class="bi bi-exclamation-triangle-fill icono">
</i>


<h1 class="text-danger mt-3">

No se puede continuar

</h1>


<p class="fs-5">

<?php echo htmlspecialchars($mensaje); ?>

</p>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>

</div>

</div>

</body>

</html>

<?php

exit();

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
Votación Escolar
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

    max-width:1100px;

    margin:30px auto;

    padding:20px;
}


/* =====================================================
   ENCABEZADO
===================================================== */

.encabezado {

    background:#1473ed;

    color:white;

    border-radius:18px;

    padding:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.12);
}


/* =====================================================
   CARGOS
===================================================== */

.card-cargo {

    background:white;

    border-radius:18px;

    padding:25px;

    margin-top:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);
}


.titulo-cargo {

    color:#1453a3;

    font-weight:bold;

    border-bottom:
        2px solid #cfe2ff;

    padding-bottom:12px;
}


/* =====================================================
   CANDIDATOS
===================================================== */

.candidato {

    display:block;

    border:
        2px solid #e0e6ed;

    border-radius:15px;

    padding:20px;

    margin-top:15px;

    cursor:pointer;

    transition:.2s;
}


.candidato:hover {

    border-color:#1473ed;

    background:#f5f9ff;
}


.candidato input {

    transform:scale(1.3);

    cursor:pointer;
}


.foto-candidato {

    width:80px;

    height:80px;

    object-fit:cover;

    border-radius:50%;

    border:3px solid #dce7f5;
}


/* =====================================================
   BOTONES
===================================================== */

.btn-confirmar {

    background:#198754;

    color:white;

    border:none;

    padding:15px 30px;

    border-radius:10px;

    font-size:18px;

    font-weight:bold;
}


.btn-confirmar:hover {

    background:#157347;

    color:white;
}


.btn-cancelar {

    font-size:18px;

    padding:13px 25px;
}


/* =====================================================
   ÉXITO
===================================================== */

.exito {

    max-width:700px;

    margin:80px auto;

    background:white;

    border-radius:20px;

    padding:45px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);
}


.exito-icon {

    font-size:80px;

    color:#198754;
}

</style>

</head>


<body>


<?php if ($mensajeExito !== "") { ?>


<!-- =====================================================
     VOTACIÓN REGISTRADA
===================================================== -->

<div class="exito">


<div class="exito-icon">

<i class="bi bi-check-circle-fill"></i>

</div>


<h1 class="text-success">

¡Votación registrada!

</h1>


<p class="fs-5">

<?php echo htmlspecialchars(
    $mensajeExito
); ?>

</p>


<p class="text-muted">

El estudiante ha terminado su votación.

</p>


<a
href="jurado.php"
class="btn btn-primary btn-lg">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


</div>


<?php } else { ?>


<div class="contenedor">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="encabezado">

<h1>

🗳️ Votación Escolar

</h1>


<p class="mb-1">

Elección:

<strong>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</strong>

</p>


<p class="mb-0">

Votante:

<strong>

<?php echo htmlspecialchars(
    $estudiante['nombre'] .
    " " .
    $estudiante['apellido']
); ?>

</strong>

</p>

</div>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if ($mensajeError !== "") { ?>

<div class="alert alert-danger mt-4">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars(
    $mensajeError
); ?>

</div>

<?php } ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
method="POST"
action="votar_por_jurado.php"
onsubmit="return confirmarVotacion();">


<?php foreach ($cargos as $cargo) { ?>


<?php

$idCargo =
    (int)$cargo['id'];

?>


<div class="card-cargo">


<h2 class="titulo-cargo">

<i class="bi bi-award-fill"></i>

<?php echo htmlspecialchars(
    $cargo['nombre_cargo']
); ?>

</h2>


<?php

$stmtCandidatos = $conn->prepare("
    SELECT
        id,
        nombre,
        apellido,
        curso,
        foto,
        propuestas
    FROM candidatos
    WHERE id_eleccion = ?
    AND id_cargo = ?
    ORDER BY id ASC
");


$stmtCandidatos->bind_param(
    "ii",
    $idEleccion,
    $idCargo
);


$stmtCandidatos->execute();


$resultadoCandidatos =
    $stmtCandidatos->get_result();


if (
    $resultadoCandidatos->num_rows === 0
) {

?>

<div class="alert alert-warning">

<i class="bi bi-exclamation-circle-fill"></i>

No hay candidatos registrados para este cargo.

</div>

<?php

}


while (
    $candidato =
    $resultadoCandidatos->fetch_assoc()
) {

?>


<label class="candidato">


<div class="row align-items-center">


<div class="col-auto">


<?php if (
    !empty($candidato['foto'])
) { ?>

<img
src="<?php echo htmlspecialchars(
    $candidato['foto']
); ?>"
class="foto-candidato"
alt="Foto del candidato">

<?php } else { ?>

<div
class="foto-candidato
       d-flex
       align-items-center
       justify-content-center
       bg-light">

<i
class="bi bi-person-fill
       fs-1
       text-primary">
</i>

</div>

<?php } ?>


</div>


<div class="col">


<h4>

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    " " .
    $candidato['apellido']
); ?>

</h4>


<p class="text-muted mb-1">

Curso:

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</p>


<?php if (
    !empty($candidato['propuestas'])
) { ?>

<p class="mb-0">

<strong>
Propuestas:
</strong>

<?php echo htmlspecialchars(
    $candidato['propuestas']
); ?>

</p>

<?php } ?>


</div>


<div class="col-auto">

<input

type="radio"

name="voto[<?php echo $idCargo; ?>]"

value="<?php echo (int)$candidato['id']; ?>"

required>

</div>


</div>


</label>


<?php

}


$stmtCandidatos->close();

?>


</div>


<?php } ?>


<!-- =====================================================
     CONFIRMAR
===================================================== -->

<div class="card-cargo text-center">


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>

Revise sus selecciones antes de continuar.

</strong>

<br>

Una vez registrada la votación no podrá modificarse.

</div>


<button
type="submit"
class="btn btn-confirmar">

<i class="bi bi-check-circle-fill"></i>

Confirmar y registrar votación

</button>


<a
href="jurado.php"
class="btn btn-secondary btn-cancelar ms-2">

<i class="bi bi-x-circle"></i>

Cancelar

</a>


</div>


</form>


</div>


<?php } ?>


<script>

function confirmarVotacion() {

    return confirm(
        "¿Está seguro de que desea registrar esta votación? Esta acción no se puede deshacer."
    );

}

</script>


</body>

</html>

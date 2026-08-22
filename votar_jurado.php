<?php

session_start();

/* =========================================
   VERIFICAR SESIÓN DEL JURADO
========================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'jurado'
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   VERIFICAR ESTUDIANTE
========================================= */

if (!isset($_SESSION['estudiante_jurado'])) {

    header("Location: jurado.php");
    exit();

}


include("config/conexion.php");


/* =========================================
   OBTENER ESTUDIANTE
========================================= */

$idEstudiante =
    (int)$_SESSION['estudiante_jurado'];


$stmt = $conn->prepare("
    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso
    FROM usuarios
    WHERE id = ?
    AND rol = 'estudiante'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEstudiante
);

$stmt->execute();

$resultado =
    $stmt->get_result();


if ($resultado->num_rows === 0) {

    unset($_SESSION['estudiante_jurado']);

    header("Location: jurado.php");
    exit();

}


$estudiante =
    $resultado->fetch_assoc();

$stmt->close();


/* =========================================
   BUSCAR ÚLTIMA ELECCIÓN
========================================= */

$consultaEleccion = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");


if (
    !$consultaEleccion ||
    $consultaEleccion->num_rows === 0
) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:50px;
        '>
            <h2>No existe una elección registrada.</h2>
            <a href='jurado.php'>
                Volver
            </a>
        </div>
    ");

}


$eleccion =
    $consultaEleccion->fetch_assoc();


$idEleccion =
    (int)$eleccion['id'];

$nombreEleccion =
    $eleccion['nombre'];

$descripcionEleccion =
    $eleccion['descripcion'];

$estadoEleccion =
    $eleccion['estado'];


/* =========================================
   SI ESTÁ CERRADA
========================================= */

if ($estadoEleccion !== 'abierta') {

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Elección cerrada</title>

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

    display:flex;

    align-items:center;

    justify-content:center;

}

.card {

    max-width:600px;

    border:none;

    border-radius:20px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.15);

}

.icono {

    font-size:80px;

    color:#dc3545;

}

</style>

</head>

<body>

<div class="card">

<div class="card-body text-center p-5">

<div class="icono">

<i class="bi bi-lock-fill"></i>

</div>

<h2 class="text-danger mt-3">

Elección cerrada

</h2>

<p class="text-muted">

La elección actualmente se encuentra cerrada.

No se pueden registrar votos en este momento.

</p>

<hr>

<h5>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</h5>

<a
href="jurado.php"
class="btn btn-primary mt-3">

<i class="bi bi-arrow-left"></i>

Volver al jurado

</a>

</div>

</div>

</body>

</html>

<?php

exit();

}


/* =========================================
   CONSULTAR CARGOS
========================================= */

$cargos = $conn->query("
    SELECT
        id,
        nombre_cargo
    FROM cargos
    ORDER BY id ASC
");


if (!$cargos) {

    die(
        "Error al consultar cargos: " .
        $conn->error
    );

}


/* =========================================
   PROCESAR VOTACIÓN
========================================= */

$mensaje = "";
$tipoMensaje = "";


if (isset($_POST['votar'])) {

    /* =====================================
       VOLVER A COMPROBAR ELECCIÓN
    ===================================== */

    $verificarEstado = $conn->query("
        SELECT estado
        FROM elecciones
        WHERE id = $idEleccion
        LIMIT 1
    ");

    if (
        !$verificarEstado ||
        $verificarEstado->num_rows === 0
    ) {

        $mensaje =
            "No se pudo verificar el estado de la elección.";

        $tipoMensaje = "danger";

    } else {

        $estadoActual =
            $verificarEstado
                ->fetch_assoc()['estado'];


        if ($estadoActual !== 'abierta') {

            $mensaje =
                "La elección acaba de ser cerrada. " .
                "No se puede registrar el voto.";

            $tipoMensaje = "danger";

        } else {

            /* =================================
               INICIAR TRANSACCIÓN
            ================================= */

            $conn->begin_transaction();

            try {

                $votosRealizados = 0;


                /* =================================
                   RECORRER CARGOS
                ================================= */

                $cargos->data_seek(0);


                while (
                    $cargo = $cargos->fetch_assoc()
                ) {

                    $idCargo =
                        (int)$cargo['id'];


                    $campo =
                        "cargo_" . $idCargo;


                    if (
                        !isset($_POST[$campo])
                    ) {

                        continue;

                    }


                    $idCandidato =
                        (int)$_POST[$campo];


                    if ($idCandidato <= 0) {

                        continue;

                    }


                    /* =================================
                       VERIFICAR SI YA VOTÓ
                    ================================= */

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM votos
                        WHERE id_usuario = ?
                        AND id_cargo = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param(
                        "ii",
                        $idEstudiante,
                        $idCargo
                    );

                    $stmt->execute();

                    $yaVoto =
                        $stmt->get_result();


                    if ($yaVoto->num_rows > 0) {

                        $stmt->close();

                        throw new Exception(
                            "El estudiante ya tiene " .
                            "un voto registrado para " .
                            $cargo['nombre_cargo'] . "."
                        );

                    }

                    $stmt->close();


                    /* =================================
                       VERIFICAR CANDIDATO
                    ================================= */

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM candidatos
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->bind_param(
                        "i",
                        $idCandidato
                    );

                    $stmt->execute();

                    $candidato =
                        $stmt->get_result();


                    if ($candidato->num_rows === 0) {

                        $stmt->close();

                        throw new Exception(
                            "El candidato seleccionado " .
                            "no existe."
                        );

                    }

                    $stmt->close();


                    /* =================================
                       INSERTAR VOTO
                    ================================= */

                    $stmt = $conn->prepare("
                        INSERT INTO votos
                        (
                            id_usuario,
                            id_candidato,
                            fecha_voto,
                            id_cargo
                        )
                        VALUES
                        (?, ?, NOW(), ?)
                    ");

                    $stmt->bind_param(
                        "iii",
                        $idEstudiante,
                        $idCandidato,
                        $idCargo
                    );


                    if (!$stmt->execute()) {

                        $stmt->close();

                        throw new Exception(
                            "No se pudo registrar el voto."
                        );

                    }


                    $stmt->close();

                    $votosRealizados++;

                }


                /* =================================
                   VERIFICAR QUE HAYA VOTOS
                ================================= */

                if ($votosRealizados === 0) {

                    throw new Exception(
                        "Debe seleccionar al menos " .
                        "un candidato."
                    );

                }


                /* =================================
                   CONFIRMAR
                ================================= */

                $conn->commit();


                $mensaje =
                    "La votación fue registrada " .
                    "correctamente.";

                $tipoMensaje = "success";


                /* =================================
                   LIMPIAR ESTUDIANTE
                ================================= */

                unset(
                    $_SESSION['estudiante_jurado']
                );

            } catch (Exception $e) {

                $conn->rollback();

                $mensaje =
                    $e->getMessage();

                $tipoMensaje = "danger";

            }

        }

    }

}


/* =========================================
   OBTENER CANDIDATOS
========================================= */

$candidatosPorCargo = [];


/* Reiniciar puntero de cargos */

$cargos->data_seek(0);


while (
    $cargo = $cargos->fetch_assoc()
) {

    $idCargo =
        (int)$cargo['id'];


    $stmt = $conn->prepare("
        SELECT
            id,
            nombre,
            apellido,
            curso,
            foto,
            propuestas
        FROM candidatos
        WHERE id_cargo = ?
        ORDER BY nombre ASC
    ");

    $stmt->bind_param(
        "i",
        $idCargo
    );

    $stmt->execute();

    $resultado =
        $stmt->get_result();


    $candidatosPorCargo[$idCargo] = [
        'nombre_cargo' =>
            $cargo['nombre_cargo'],

        'candidatos' =>
            $resultado->fetch_all(
                MYSQLI_ASSOC
            )
    ];


    $stmt->close();

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>

Votación del Estudiante

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


/* =========================================
   CABECERA
========================================= */

.header {

    background:#0d47a1;

    color:white;

    padding:25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.15);

}


/* =========================================
   CONTENEDOR
========================================= */

.contenedor {

    max-width:1100px;

    margin:auto;

    padding:30px 20px;

}


/* =========================================
   TARJETAS
========================================= */

.card {

    border:none;

    border-radius:18px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.10);

}


/* =========================================
   ESTUDIANTE
========================================= */

.estudiante {

    background:white;

    padding:25px;

    margin-bottom:25px;

}


/* =========================================
   CARGO
========================================= */

.cargo {

    color:#0d47a1;

    font-weight:bold;

    border-bottom:
        2px solid #0d6efd;

    padding-bottom:10px;

    margin-bottom:20px;

}


/* =========================================
   CANDIDATO
========================================= */

.candidato {

    border:
        2px solid #dee2e6;

    border-radius:15px;

    padding:20px;

    height:100%;

    cursor:pointer;

    transition:.2s;

}


.candidato:hover {

    border-color:#0d6efd;

    box-shadow:
        0 5px 15px rgba(0,0,0,.12);

}


.candidato input:checked + .contenido-candidato {

    border-color:#0d6efd;

}


.foto {

    width:100px;

    height:100px;

    object-fit:cover;

    border-radius:50%;

    border:3px solid #0d6efd;

}


/* =========================================
   BOTÓN
========================================= */

.btn-votar {

    background:#198754;

    color:white;

    border:none;

    padding:14px;

    font-size:19px;

    border-radius:12px;

}


.btn-votar:hover {

    background:#157347;

    color:white;

}


/* =========================================
   MENSAJE
========================================= */

.alert {

    border-radius:12px;

}

</style>

</head>


<body>


<!-- =========================================
     HEADER
========================================= -->

<div class="header">


<div class="container">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>

<h2 class="mb-1">

🗳️ Votación Escolar

</h2>

<p class="mb-0">

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</p>

</div>


<a
href="jurado.php"
class="btn btn-light">

<i class="bi bi-arrow-left"></i>

Cambiar estudiante

</a>


</div>

</div>

</div>


<!-- =========================================
     CONTENIDO
========================================= -->

<div class="contenedor">


<!-- =========================================
     ESTUDIANTE
========================================= -->

<div class="card estudiante">


<h4 class="text-primary">

<i class="bi bi-person-fill"></i>

Estudiante

</h4>


<hr>


<div class="row">


<div class="col-md-3">

<strong>Documento</strong>

<br>

<?php echo htmlspecialchars(
    $estudiante['documento']
); ?>

</div>


<div class="col-md-3">

<strong>Nombre</strong>

<br>

<?php echo htmlspecialchars(
    $estudiante['nombre']
); ?>

</div>


<div class="col-md-3">

<strong>Apellido</strong>

<br>

<?php echo htmlspecialchars(
    $estudiante['apellido']
); ?>

</div>


<div class="col-md-3">

<strong>Curso</strong>

<br>

<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>

</div>


</div>

</div>


<!-- =========================================
     MENSAJE
========================================= -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<!-- =========================================
     FORMULARIO
========================================= -->

<form method="POST">


<?php foreach (
    $candidatosPorCargo
    as $idCargo => $datos
) { ?>


<div class="card p-4 mb-4">


<h3 class="cargo">

<i class="bi bi-person-vcard-fill"></i>

<?php echo htmlspecialchars(
    $datos['nombre_cargo']
); ?>

</h3>


<div class="row g-3">


<?php if (
    count($datos['candidatos']) === 0
) { ?>

<div class="col-12">

<div class="alert alert-warning">

No hay candidatos registrados
para este cargo.

</div>

</div>

<?php } ?>


<?php foreach (
    $datos['candidatos']
    as $candidato
) { ?>


<div class="col-md-6 col-lg-4">


<label class="w-100">


<div class="candidato">


<input
type="radio"
name="cargo_<?php echo $idCargo; ?>"
value="<?php echo (int)$candidato['id']; ?>"
class="form-check-input mb-3"
required>


<div class="text-center">


<?php if (
    !empty($candidato['foto'])
) { ?>

<img
src="uploads/<?php echo htmlspecialchars(
    $candidato['foto']
); ?>"
class="foto mb-3">

<?php } else { ?>

<div
class="foto mx-auto mb-3
d-flex align-items-center
justify-content-center
bg-light">

<i class="bi bi-person-fill fs-1
text-secondary"></i>

</div>

<?php } ?>


<h5>

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    " " .
    $candidato['apellido']
); ?>

</h5>


<p class="text-muted mb-1">

Curso:

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</p>


<?php if (
    !empty($candidato['propuestas'])
) { ?>

<small class="text-secondary">

<?php echo htmlspecialchars(
    $candidato['propuestas']
); ?>

</small>

<?php } ?>


</div>


</div>


</label>


</div>


<?php } ?>


</div>

</div>


<?php } ?>


<!-- =========================================
     CONFIRMAR
========================================= -->

<div class="card p-4 mb-5">


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

<strong>Importante:</strong>

Verifique sus selecciones antes
de registrar la votación.

</div>


<button
type="submit"
name="votar"
class="btn btn-votar w-100"
onclick="
return confirm(
'¿Está seguro de registrar esta votación?'
);
">

<i class="bi bi-check-circle-fill"></i>

Registrar votación

</button>


</div>


</form>


</div>


</body>

</html>


<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR QUE SEA JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'jurado'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   VERIFICAR ESTUDIANTE
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {
    header("Location: ingresar_estudiante.php");
    exit();
}


$idEstudiante =
    (int)$_SESSION['estudiante_votando_id'];

$nombreEstudiante =
    $_SESSION['estudiante_votando_nombre'] ?? '';

$documentoEstudiante =
    $_SESSION['estudiante_votando_documento'] ?? '';

$cursoEstudiante =
    $_SESSION['estudiante_votando_curso'] ?? '';


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

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

$stmtEleccion->execute();

$resultadoEleccion =
    $stmtEleccion->get_result();

$eleccion =
    $resultadoEleccion->fetch_assoc();

$stmtEleccion->close();


if (!$eleccion) {

    mostrarError(
        "No hay una elección abierta actualmente."
    );

}


$idEleccion =
    (int)$eleccion['id'];


/* =========================================================
   COMPROBAR NUEVAMENTE SI YA VOTÓ
========================================================= */

$stmtYaVoto = $conn->prepare("

    SELECT
        COUNT(*) AS total

    FROM votos v

    INNER JOIN candidatos c
        ON c.id = v.id_candidato

    WHERE v.id_usuario = ?

    AND c.id_eleccion = ?

");

$stmtYaVoto->bind_param(
    "ii",
    $idEstudiante,
    $idEleccion
);

$stmtYaVoto->execute();

$resultadoYaVoto =
    $stmtYaVoto->get_result();

$datosYaVoto =
    $resultadoYaVoto->fetch_assoc();

$totalVotosPrevios =
    (int)$datosYaVoto['total'];

$stmtYaVoto->close();


/* =========================================================
   SI YA VOTÓ, BLOQUEAR
========================================================= */

if ($totalVotosPrevios > 0) {

    mostrarYaVoto(
        $nombreEstudiante,
        $documentoEstudiante,
        $eleccion['nombre']
    );

}


/* =========================================================
   OBTENER CARGOS
========================================================= */

$cargos = [];


$stmtCargos = $conn->prepare("

    SELECT
        c.id,
        c.nombre_cargo

    FROM cargos c

    INNER JOIN eleccion_cargos ec
        ON ec.id_cargo = c.id

    WHERE ec.id_eleccion = ?

    ORDER BY c.id ASC

");

$stmtCargos->bind_param(
    "i",
    $idEleccion
);

$stmtCargos->execute();

$resultadoCargos =
    $stmtCargos->get_result();


while (
    $cargo =
    $resultadoCargos->fetch_assoc()
) {

    $cargo['id'] =
        (int)$cargo['id'];

    $cargo['candidatos'] = [];

    $cargos[] =
        $cargo;

}

$stmtCargos->close();


if (count($cargos) === 0) {

    mostrarError(
        "Esta elección no tiene cargos configurados."
    );

}


/* =========================================================
   OBTENER CANDIDATOS DE CADA CARGO
========================================================= */

foreach (
    $cargos as &$cargo
) {

    $stmtCandidatos =
        $conn->prepare("

            SELECT
                id,
                nombre,
                apellido,
                curso,
                foto

            FROM candidatos

            WHERE id_eleccion = ?

            AND id_cargo = ?

            ORDER BY id ASC

        ");

    $stmtCandidatos->bind_param(
        "ii",
        $idEleccion,
        $cargo['id']
    );

    $stmtCandidatos->execute();

    $resultadoCandidatos =
        $stmtCandidatos->get_result();


    while (
        $candidato =
        $resultadoCandidatos->fetch_assoc()
    ) {

        $cargo['candidatos'][] =
            $candidato;

    }


    $stmtCandidatos->close();

}

unset($cargo);


/* =========================================================
   PROCESAR VOTACIÓN
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$votacionRealizada = false;


if (
    isset($_POST['registrar_votos'])
) {

    $selecciones =
        $_POST['candidato'] ?? [];


    if (!is_array($selecciones)) {

        $selecciones = [];

    }


    /* =====================================================
       COMPROBAR QUE HAYA UN CANDIDATO POR CARGO
    ===================================================== */

    $faltanCargos = [];


    foreach (
        $cargos as $cargo
    ) {

        $idCargo =
            (int)$cargo['id'];


        if (
            !isset(
                $selecciones[$idCargo]
            ) ||
            (int)$selecciones[$idCargo] <= 0
        ) {

            $faltanCargos[] =
                $cargo['nombre_cargo'];

        }

    }


    if (
        count($faltanCargos) > 0
    ) {

        $mensaje =
            "Debe seleccionar un candidato para cada cargo.";

        $tipoMensaje =
            "danger";

    } else {


        /* =================================================
           TRANSACCIÓN
        ================================================= */

        $conn->begin_transaction();


        try {


            /* =============================================
               SEGUNDA COMPROBACIÓN DE DOBLE VOTO
            ============================================= */

            $stmtBloqueo =
                $conn->prepare("

                    SELECT
                        v.id

                    FROM votos v

                    INNER JOIN candidatos c
                        ON c.id = v.id_candidato

                    WHERE v.id_usuario = ?

                    AND c.id_eleccion = ?

                    LIMIT 1

                ");


            if (!$stmtBloqueo) {

                throw new Exception(
                    "No se pudo verificar la votación."
                );

            }


            $stmtBloqueo->bind_param(
                "ii",
                $idEstudiante,
                $idEleccion
            );


            $stmtBloqueo->execute();


            $resultadoBloqueo =
                $stmtBloqueo->get_result();


            if (
                $resultadoBloqueo->num_rows > 0
            ) {

                throw new Exception(
                    "Este estudiante ya había votado en esta elección."
                );

            }


            /* =============================================
               PREPARAR VALIDACIÓN
            ============================================= */

            $stmtValidar =
                $conn->prepare("

                    SELECT
                        id

                    FROM candidatos

                    WHERE id = ?

                    AND id_eleccion = ?

                    AND id_cargo = ?

                    LIMIT 1

                ");


            if (!$stmtValidar) {

                throw new Exception(
                    "No se pudo validar el candidato."
                );

            }


            /* =============================================
               PREPARAR INSERT
            ============================================= */

            $stmtInsertar =
                $conn->prepare("

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
                    "No se pudo preparar el registro del voto."
                );

            }


            /* =============================================
               REGISTRAR UN VOTO POR CADA CARGO
            ============================================= */

            foreach (
                $cargos as $cargo
            ) {

                $idCargo =
                    (int)$cargo['id'];

                $idCandidato =
                    (int)$selecciones[$idCargo];


                /* =========================================
                   VALIDAR CANDIDATO
                ========================================= */

                $stmtValidar->bind_param(
                    "iii",
                    $idCandidato,
                    $idEleccion,
                    $idCargo
                );


                $stmtValidar->execute();


                $resultadoValidar =
                    $stmtValidar->get_result();


                if (
                    $resultadoValidar->num_rows === 0
                ) {

                    throw new Exception(
                        "El candidato seleccionado no pertenece al cargo indicado."
                    );

                }


                /* =========================================
                   INSERTAR VOTO
                ========================================= */

                $stmtInsertar->bind_param(
                    "iii",
                    $idEstudiante,
                    $idCandidato,
                    $idCargo
                );


                if (
                    !$stmtInsertar->execute()
                ) {

                    throw new Exception(
                        "No se pudo registrar uno de los votos."
                    );

                }

            }


            /* =============================================
               CERRAR CONSULTAS
            ============================================= */

            $stmtBloqueo->close();

            $stmtValidar->close();

            $stmtInsertar->close();


            /* =============================================
               CONFIRMAR TRANSACCIÓN
            ============================================= */

            $conn->commit();


            /* =============================================
               ELIMINAR ESTUDIANTE DE SESIÓN
            ============================================= */

            unset(
                $_SESSION['estudiante_votando_id'],
                $_SESSION['estudiante_votando_documento'],
                $_SESSION['estudiante_votando_nombre'],
                $_SESSION['estudiante_votando_curso'],
                $_SESSION['eleccion_votante_id']
            );


            $votacionRealizada =
                true;


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

?>


<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Registrar votación
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.header {

    background: #1473ed;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.header h3 {

    margin: 0;

}


.contenedor {

    max-width: 1100px;

    margin: auto;

    padding: 30px 20px;

}


.estudiante {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.estudiante h2 {

    color: #1453a3;

    font-weight: bold;

}


.eleccion {

    background: #cfe2ff;

    border: 1px solid #9ec5fe;

    color: #084298;

    padding: 20px;

    border-radius: 15px;

    margin-bottom: 25px;

}


.cargo {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-bottom: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);

}


.cargo-header {

    background: #1453a3;

    color: white;

    padding: 18px 22px;

}


.cargo-header h3 {

    margin: 0;

}


.cargo-body {

    padding: 25px;

}


.candidato {

    display: block;

    border: 2px solid #e0e6ed;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 15px;

    cursor: pointer;

    transition: .2s;

}


.candidato:hover {

    border-color: #1473ed;

    background: #f5f9ff;

}


.candidato.seleccionado {

    border-color: #1473ed;

    background: #eaf3ff;

}


.candidato input {

    display: none;

}


.candidato-contenido {

    display: flex;

    align-items: center;

    gap: 18px;

}


.foto {

    width: 80px;

    height: 80px;

    object-fit: cover;

    border-radius: 50%;

}


.sin-foto {

    width: 80px;

    height: 80px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 32px;

    color: #1453a3;

}


.nombre {

    font-size: 19px;

    font-weight: bold;

    color: #1453a3;

}


.curso {

    color: #6c757d;

    margin-top: 5px;

}


.btn-votar {

    width: 100%;

    padding: 15px;

    font-size: 19px;

    font-weight: bold;

}


.exito {

    background: white;

    border-radius: 20px;

    padding: 50px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.exito-icono {

    font-size: 70px;

    color: #198754;

}


@media(max-width:600px) {

    .header {

        padding: 15px;

    }


    .header h3 {

        font-size: 18px;

    }


    .candidato-contenido {

        gap: 10px;

    }


    .foto,
    .sin-foto {

        width: 60px;

        height: 60px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
========================================================= -->

<div class="header">


<h3>

🗳️ Sistema de Votaciones Escolares

</h3>


<span>

<i class="bi bi-person-badge-fill"></i>

Jurado:

<?php echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Jurado'
); ?>

</span>


</div>


<div class="contenedor">


<?php if (
    $votacionRealizada
) { ?>


<!-- =====================================================
     ÉXITO
========================================================= -->

<div class="exito">


<div class="exito-icono">

<i class="bi bi-check-circle-fill"></i>

</div>


<h1 class="text-success mt-3">

¡Votación registrada!

</h1>


<p class="fs-5 text-muted">

La votación del estudiante fue registrada
correctamente.

</p>


<div class="alert alert-success">

<i class="bi bi-shield-check"></i>

El estudiante ya no podrá votar nuevamente
en esta elección.

</div>


<a
href="ingresar_estudiante.php"
class="btn btn-primary btn-lg mt-3">

<i class="bi bi-person-plus-fill"></i>

Ingresar otro estudiante

</a>


<a
href="jurado.php"
class="btn btn-outline-secondary btn-lg mt-3">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


<?php } else { ?>


<!-- =====================================================
     MENSAJE
========================================================= -->

<?php if (
    $mensaje !== ""
) { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?>">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php echo htmlspecialchars(
    $mensaje
); ?>

</div>

<?php } ?>


<!-- =====================================================
     DATOS DEL ESTUDIANTE
========================================================= -->

<div class="estudiante">


<h2>

<i class="bi bi-person-check-fill"></i>

Estudiante

</h2>


<hr>


<div class="row">


<div class="col-md-6">

<strong>

Nombre

</strong>

<br>

<?php echo htmlspecialchars(
    $nombreEstudiante
); ?>

</div>


<div class="col-md-6">

<strong>

Documento

</strong>

<br>

<?php echo htmlspecialchars(
    $documentoEstudiante
); ?>

</div>


<div class="col-md-6 mt-3">

<strong>

Curso

</strong>

<br>

<?php echo htmlspecialchars(
    $cursoEstudiante
); ?>

</div>


</div>


</div>


<!-- =====================================================
     ELECCIÓN
========================================================= -->

<div class="eleccion">


<h4>

<i class="bi bi-calendar-event-fill"></i>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h4>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="mb-0">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


</div>


<!-- =====================================================
     FORMULARIO
========================================================= -->

<form
method="POST"
id="formVotacion">


<?php foreach (
    $cargos as $cargo
) { ?>


<div class="cargo">


<div class="cargo-header">


<h3>

<i class="bi bi-award-fill"></i>

<?php echo htmlspecialchars(
    $cargo['nombre_cargo']
); ?>

</h3>


</div>


<div class="cargo-body">


<p class="text-muted">

Seleccione un candidato:

</p>


<?php if (
    count($cargo['candidatos']) === 0
) { ?>


<div class="alert alert-warning">

No hay candidatos registrados
para este cargo.

</div>


<?php } else { ?>


<?php foreach (
    $cargo['candidatos']
    as $candidato
) { ?>


<label
class="candidato"
onclick="seleccionar(this)">


<input

type="radio"

name="candidato[
<?php echo (int)$cargo['id']; ?>
]"

value="<?php echo (int)$candidato['id']; ?>"

required>


<div class="candidato-contenido">


<?php

$foto =
    trim(
        (string)$candidato['foto']
    );


$rutaFoto =
    "uploads/candidatos/" .
    $foto;


if (
    $foto !== "" &&
    file_exists($rutaFoto)
) {

?>


<img

src="<?php echo htmlspecialchars(
    $rutaFoto
); ?>"

class="foto"

alt="Foto del candidato">


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


<div class="nombre">

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    " " .
    $candidato['apellido']
); ?>

</div>


<div class="curso">

<i class="bi bi-mortarboard-fill"></i>

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</div>


</div>


</div>


</label>


<?php } ?>


<?php } ?>


</div>


</div>


<?php } ?>


<button

type="submit"

name="registrar_votos"

class="btn btn-primary btn-votar"

onclick="
return confirmarVotacion();
">

<i class="bi bi-check-circle-fill"></i>

Registrar votación

</button>


</form>


<a
href="ingresar_estudiante.php"
class="btn btn-outline-secondary w-100 mt-3">

<i class="bi bi-arrow-left"></i>

Cambiar estudiante

</a>


<?php } ?>


</div>


<script>

/* =========================================================
   SELECCIONAR CANDIDATO
========================================================= */

function seleccionar(elemento) {

    const contenedor =
        elemento.parentElement;

    const candidatos =
        contenedor.querySelectorAll(
            ".candidato"
        );


    candidatos.forEach(
        function(candidato) {

            candidato.classList.remove(
                "seleccionado"
            );

        }
    );


    elemento.classList.add(
        "seleccionado"
    );

}


/* =========================================================
   CONFIRMAR VOTACIÓN
========================================================= */

function confirmarVotacion() {

    const formulario =
        document.getElementById(
            "formVotacion"
        );


    const cargos =
        formulario.querySelectorAll(
            ".cargo"
        );


    for (
        let i = 0;
        i < cargos.length;
        i++
    ) {

        const seleccionado =
            cargos[i].querySelector(
                "input[type='radio']:checked"
            );


        if (!seleccionado) {

            alert(
                "Debe seleccionar un candidato para cada cargo."
            );

            cargos[i].scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            return false;

        }

    }


    return confirm(
        "¿Está seguro de registrar la votación?\n\n" +
        "Después de registrarla no será posible volver a votar con este estudiante."
    );

}

</script>


</body>

</html>


<?php


/* =========================================================
   FUNCIÓN ERROR
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

<title>
No se puede votar
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<style>

body {

    background: #eef3f9;

}


.contenedor {

    max-width: 700px;

    margin: 100px auto;

    padding: 20px;

}


.card {

    background: white;

    border-radius: 20px;

    padding: 45px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.icono {

    font-size: 70px;

    color: #dc3545;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<div class="icono">

⚠️

</div>


<h2 class="text-danger">

No se puede continuar

</h2>


<p>

<?php echo htmlspecialchars(
    $mensaje
); ?>

</p>


<a
href="ingresar_estudiante.php"
class="btn btn-primary">

Volver

</a>


</div>

</div>


</body>

</html>

<?php

exit();

}


/* =========================================================
   FUNCIÓN ESTUDIANTE YA VOTÓ
========================================================= */

function mostrarYaVoto(
    $nombre,
    $documento,
    $nombreEleccion
)
{

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Estudiante ya votó
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<style>

body {

    background: #eef3f9;

}


.contenedor {

    max-width: 750px;

    margin: 80px auto;

    padding: 20px;

}


.card {

    background: white;

    border-radius: 20px;

    padding: 45px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.icono {

    font-size: 75px;

    color: #dc3545;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<div class="icono">

🛑

</div>


<h1 class="text-danger">

Estudiante ya votó

</h1>


<p class="fs-5">

El estudiante:

</p>


<h3>

<?php echo htmlspecialchars(
    $nombre
); ?>

</h3>


<p>

Documento:

<strong>

<?php echo htmlspecialchars(
    $documento
); ?>

</strong>

</p>


<div class="alert alert-danger mt-4">

Este estudiante ya tiene una votación
registrada en:

<br>

<strong>

<?php echo htmlspecialchars(
    $nombreEleccion
); ?>

</strong>


<br><br>

No es posible registrar otra votación.

</div>


<a
href="ingresar_estudiante.php"
class="btn btn-primary btn-lg">

Ingresar otro estudiante

</a>


<a
href="jurado.php"
class="btn btn-outline-secondary btn-lg mt-2">

Volver al panel

</a>


</div>

</div>


</body>

</html>

<?php

exit();

}

?>
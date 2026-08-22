<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
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
   VERIFICAR QUE HAYA UN ESTUDIANTE SELECCIONADO
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
    $_SESSION['estudiante_votando_nombre']
    ?? "Estudiante";


$documentoEstudiante =
    $_SESSION['estudiante_votando_documento']
    ?? "";


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "";


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;

$consultaEleccion = $conn->query("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    WHERE estado = 'abierta'
    ORDER BY fecha_inicio DESC
    LIMIT 1
");


if (
    $consultaEleccion &&
    $consultaEleccion->num_rows > 0
) {

    $eleccion =
        $consultaEleccion->fetch_assoc();

}


/* =========================================================
   SI NO HAY ELECCIÓN ABIERTA
========================================================= */

if (!$eleccion) {

    $mensaje =
        "Actualmente no hay una elección abierta.";

    $tipoMensaje = "warning";

}


/* =========================================================
   ID ELECCIÓN
========================================================= */

$idEleccion = $eleccion
    ? (int)$eleccion['id']
    : 0;


/* =========================================================
   PROCESAR VOTACIÓN
========================================================= */

if (
    isset($_POST['votar']) &&
    $eleccion
) {

    $selecciones =
        $_POST['candidato'] ?? [];


    if (
        !is_array($selecciones)
    ) {

        $selecciones = [];

    }


    /* =====================================================
       OBTENER CARGOS DE LA ELECCIÓN
    ===================================================== */

    $cargosValidos = [];

    $stmtCargos =
        $conn->prepare("
            SELECT
                c.id,
                c.nombre_cargo

            FROM cargos c

            INNER JOIN eleccion_cargos ec
                ON c.id = ec.id_cargo

            WHERE ec.id_eleccion = ?

            ORDER BY c.id ASC
        ");


    if ($stmtCargos) {

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

            $cargosValidos[
                (int)$cargo['id']
            ] = $cargo['nombre_cargo'];

        }


        $stmtCargos->close();

    }


    /* =====================================================
       COMPROBAR QUE SE HAYA SELECCIONADO
       UN CANDIDATO PARA CADA CARGO
    ===================================================== */

    $faltanCargos = [];

    foreach (
        $cargosValidos as $idCargo => $nombreCargo
    ) {

        if (
            !isset($selecciones[$idCargo]) ||
            (int)$selecciones[$idCargo] <= 0
        ) {

            $faltanCargos[] =
                $nombreCargo;

        }

    }


    if (
        count($faltanCargos) > 0
    ) {

        $mensaje =
            "Debe seleccionar un candidato para cada cargo.";

        $tipoMensaje = "danger";

    } else {


        /* =================================================
           INICIAR TRANSACCIÓN
        ================================================= */

        $conn->begin_transaction();


        try {


            /* =============================================
               PREPARAR CONSULTA PARA COMPROBAR VOTO
            ============================================= */

            $stmtComprobar =
                $conn->prepare("
                    SELECT id
                    FROM votos
                    WHERE id_usuario = ?
                    AND id_cargo = ?
                    LIMIT 1
                ");


            if (!$stmtComprobar) {

                throw new Exception(
                    "No se pudo preparar la consulta de votos."
                );

            }


            /* =============================================
               PREPARAR CONSULTA DEL CANDIDATO
            ============================================= */

            $stmtCandidato =
                $conn->prepare("
                    SELECT id
                    FROM candidatos
                    WHERE id = ?
                    AND id_eleccion = ?
                    AND id_cargo = ?
                    LIMIT 1
                ");


            if (!$stmtCandidato) {

                throw new Exception(
                    "No se pudo validar el candidato."
                );

            }


            /* =============================================
               PREPARAR INSERCIÓN
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
               REGISTRAR VOTO DE CADA CARGO
            ============================================= */

            foreach (
                $cargosValidos as $idCargo => $nombreCargo
            ) {


                $idCandidato =
                    (int)$selecciones[$idCargo];


                /* =========================================
                   COMPROBAR SI YA VOTÓ
                ========================================= */

                $stmtComprobar->bind_param(
                    "ii",
                    $idEstudiante,
                    $idCargo
                );


                $stmtComprobar->execute();


                $yaVoto =
                    $stmtComprobar
                    ->get_result()
                    ->num_rows > 0;


                if ($yaVoto) {

                    throw new Exception(
                        "El estudiante ya tiene un voto registrado para el cargo: "
                        . $nombreCargo
                    );

                }


                /* =========================================
                   VALIDAR CANDIDATO
                ========================================= */

                $stmtCandidato->bind_param(
                    "iii",
                    $idCandidato,
                    $idEleccion,
                    $idCargo
                );


                $stmtCandidato->execute();


                $candidatoValido =
                    $stmtCandidato
                    ->get_result()
                    ->num_rows > 0;


                if (!$candidatoValido) {

                    throw new Exception(
                        "Uno de los candidatos seleccionados no es válido."
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

            $stmtComprobar->close();

            $stmtCandidato->close();

            $stmtInsertar->close();


            /* =============================================
               CONFIRMAR
            ============================================= */

            $conn->commit();


            $mensaje =
                "La votación del estudiante fue registrada correctamente.";

            $tipoMensaje = "success";


            /* =============================================
               LIMPIAR ESTUDIANTE SELECCIONADO
            ============================================= */

            unset(
                $_SESSION['estudiante_votando_id'],
                $_SESSION['estudiante_votando_documento'],
                $_SESSION['estudiante_votando_nombre']
            );


            /*
             * Indicamos que la votación terminó
             */
            $votacionRealizada = true;


        } catch (Exception $e) {


            /* =============================================
               DESHACER VOTOS SI OCURRIÓ UN ERROR
            ============================================= */

            $conn->rollback();


            $mensaje =
                $e->getMessage();

            $tipoMensaje = "danger";


            $votacionRealizada = false;

        }

    }

} else {

    $votacionRealizada = false;

}


/* =========================================================
   SI NO EXISTE LA VARIABLE
========================================================= */

if (!isset($votacionRealizada)) {

    $votacionRealizada = false;

}


/* =========================================================
   CARGOS Y CANDIDATOS
========================================================= */

$cargos = [];


if (
    $eleccion &&
    !$votacionRealizada
) {


    $stmt =
        $conn->prepare("
            SELECT
                c.id,
                c.nombre_cargo

            FROM cargos c

            INNER JOIN eleccion_cargos ec
                ON c.id = ec.id_cargo

            WHERE ec.id_eleccion = ?

            ORDER BY c.id ASC
        ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();


        while (
            $cargo =
            $resultado->fetch_assoc()
        ) {

            $cargo['id'] =
                (int)$cargo['id'];


            $cargo['candidatos'] = [];


            /* =============================================
               BUSCAR CANDIDATOS
            ============================================= */

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


            if ($stmtCandidatos) {

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


            $cargos[] =
                $cargo;

        }


        $stmt->close();

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
Votación del estudiante
</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- ICONOS -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>

/* =========================================================
   GENERAL
========================================================= */

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


/* =========================================================
   CABECERA
========================================================= */

.header {

    background: #1473ed;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);
}


.header h3 {

    margin: 0;

    font-weight: bold;
}


.header span {

    font-size: 15px;
}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    max-width: 1100px;

    margin: auto;

    padding: 35px 20px;
}


/* =========================================================
   INFORMACIÓN ESTUDIANTE
========================================================= */

.estudiante {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.10);
}


.estudiante-titulo {

    color: #1453a3;

    font-size: 25px;

    font-weight: bold;
}


.documento {

    color: #6c757d;
}


/* =========================================================
   INFORMACIÓN ELECCIÓN
========================================================= */

.eleccion {

    background: #cfe2ff;

    border: 1px solid #9ec5fe;

    border-radius: 15px;

    padding: 20px;

    margin-bottom: 30px;

    color: #084298;
}


/* =========================================================
   CARGO
========================================================= */

.cargo {

    background: white;

    border-radius: 18px;

    margin-bottom: 25px;

    overflow: hidden;

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

    font-weight: bold;
}


.cargo-body {

    padding: 25px;
}


/* =========================================================
   CANDIDATO
========================================================= */

.candidato {

    border: 2px solid #e0e6ed;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 15px;

    cursor: pointer;

    transition: .2s;

    position: relative;
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

    position: absolute;

    opacity: 0;
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

    border: 3px solid #e0e6ed;
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


.check {

    margin-left: auto;

    font-size: 28px;

    color: #1473ed;

    display: none;
}


.candidato.seleccionado
.check {

    display: block;
}


/* =========================================================
   BOTÓN VOTAR
========================================================= */

.btn-votar {

    width: 100%;

    padding: 15px;

    background: #1473ed;

    border: none;

    border-radius: 12px;

    color: white;

    font-size: 19px;

    font-weight: bold;

    margin-top: 10px;
}


.btn-votar:hover {

    background: #0d5dcc;
}


/* =========================================================
   BOTONES
========================================================= */

.btn-secundario {

    width: 100%;

    padding: 13px;

    margin-top: 12px;
}


/* =========================================================
   MENSAJES
========================================================= */

.alert {

    border-radius: 12px;
}


/* =========================================================
   ÉXITO
========================================================= */

.exito {

    background: white;

    border-radius: 22px;

    padding: 45px;

    text-align: center;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.12);
}


.exito-icono {

    font-size: 70px;

    color: #198754;

    margin-bottom: 15px;
}


.exito h2 {

    color: #1453a3;

    font-weight: bold;
}


/* =========================================================
   RESPONSIVE
========================================================= */

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
     CABECERA
===================================================== -->

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


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if ($mensaje !== "") { ?>

<div class="alert alert-<?php echo $tipoMensaje; ?> mb-4">

<i class="bi bi-info-circle-fill"></i>

<?php echo htmlspecialchars($mensaje); ?>

</div>

<?php } ?>


<?php if (
    $votacionRealizada
) { ?>


<!-- =====================================================
     VOTACIÓN TERMINADA
===================================================== -->

<div class="exito">


<div class="exito-icono">

<i class="bi bi-check-circle-fill"></i>

</div>


<h2>

Votación registrada correctamente

</h2>


<p class="text-muted mt-3">

Los votos del estudiante fueron
registrados correctamente.

</p>


<div class="mt-4">


<a
href="ingresar_estudiante.php"
class="btn btn-primary btn-lg">

<i class="bi bi-person-plus-fill"></i>

Ingresar otro estudiante

</a>


<a
href="jurado.php"
class="btn btn-outline-secondary btn-lg">

<i class="bi bi-house-fill"></i>

Volver al panel

</a>


</div>


</div>


<?php } elseif (!$eleccion) { ?>


<!-- =====================================================
     NO HAY ELECCIÓN
===================================================== -->

<div class="exito">


<div class="exito-icono"
style="color:#ffc107;">

<i class="bi bi-calendar-x-fill"></i>

</div>


<h2>

No hay una elección abierta

</h2>


<p class="text-muted">

El administrador debe abrir una elección
antes de registrar votos.

</p>


<a
href="ingresar_estudiante.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Volver

</a>


</div>


<?php } else { ?>


<!-- =====================================================
     ESTUDIANTE
===================================================== -->

<div class="estudiante">


<div class="estudiante-titulo">

<i class="bi bi-person-vcard-fill"></i>

Estudiante que está votando

</div>


<div class="mt-3">

<strong>

<?php echo htmlspecialchars(
    $nombreEstudiante
); ?>

</strong>


<div class="documento">

Documento:

<?php echo htmlspecialchars(
    $documentoEstudiante
); ?>

</div>


</div>


</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="eleccion">


<h4>

<i class="bi bi-calendar-event-fill"></i>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h4>


<?php if (
    !empty($eleccion['descripcion'])
) { ?>

<p class="mb-0">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


</div>


<!-- =====================================================
     FORMULARIO DE VOTACIÓN
===================================================== -->

<form
method="POST"
id="formVotacion">


<?php

$cantidadCargos =
    count($cargos);

?>


<?php if (
    $cantidadCargos === 0
) { ?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

No hay cargos configurados
para esta elección.

</div>


<?php } else { ?>


<?php foreach (
    $cargos as $cargo
) { ?>


<!-- =====================================================
     CARGO
===================================================== -->

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

Seleccione un candidato.

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
) {


$idCandidato =
    (int)$candidato['id'];


$nombreCandidato =
    $candidato['nombre'] .
    " " .
    $candidato['apellido'];


$foto =
    trim(
        (string)$candidato['foto']
    );

?>


<label
class="candidato"
onclick="seleccionarCandidato(this)">


<input

type="radio"

name="candidato[
<?php echo (int)$cargo['id']; ?>
]"

value="<?php echo $idCandidato; ?>"

required>


<div class="candidato-contenido">


<?php

if (
    $foto !== "" &&
    file_exists(
        "uploads/candidatos/" . $foto
    )
) {

?>


<img

src="uploads/candidatos/<?php echo htmlspecialchars(
    $foto
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
    $nombreCandidato
); ?>

</div>


<div class="curso">

<i class="bi bi-mortarboard-fill"></i>

Curso:

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</div>


</div>


<div class="check">

<i class="bi bi-check-circle-fill"></i>

</div>


</div>


</label>


<?php

}

?>


<?php } ?>


</div>


</div>


<?php

}

?>


<!-- =====================================================
     BOTÓN DE VOTAR
===================================================== -->

<button

type="submit"

name="votar"

class="btn-votar"

onclick="
return confirmarVotacion();
">

<i class="bi bi-check-circle-fill"></i>

Registrar votación

</button>


<?php } ?>


</form>


<!-- =====================================================
     CANCELAR
===================================================== -->

<a

href="ingresar_estudiante.php"

class="btn btn-outline-secondary btn-secundario">

<i class="bi bi-arrow-left"></i>

Cancelar y cambiar estudiante

</a>


<?php } ?>


</div>


<script>

/* =========================================================
   SELECCIONAR CANDIDATO
========================================================= */

function seleccionarCandidato(elemento) {

    const grupo =
        elemento.parentElement;

    const candidatos =
        grupo.querySelectorAll(
            '.candidato'
        );


    candidatos.forEach(
        function(candidato) {

            candidato.classList.remove(
                'seleccionado'
            );

        }
    );


    elemento.classList.add(
        'seleccionado'
    );

}


/* =========================================================
   CONFIRMAR VOTACIÓN
========================================================= */

function confirmarVotacion() {

    return confirm(
        "¿Está seguro de registrar la votación de este estudiante?\\n\\n" +
        "Una vez registrada, no podrá modificarse."
    );

}

</script>


</body>

</html>
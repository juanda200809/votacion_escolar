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


$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


/* =========================================================
   SOLO ESTUDIANTE
========================================================= */

if ($rol !== "estudiante") {

    if ($rol === "administrador") {

        header("Location: admin.php");
        exit();

    }

    if ($rol === "jurado") {

        header("Location: jurado.php");
        exit();

    }

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================================
   OBTENER ESTUDIANTE
========================================================= */

$idUsuario =
    (int)$_SESSION['id'];


$stmt = $conn->prepare("

    SELECT
        id,
        documento,
        nombre,
        apellido,
        curso

    FROM usuarios

    WHERE id = ?

    AND LOWER(TRIM(rol))
        = 'estudiante'

    LIMIT 1

");


if (!$stmt) {

    die("Error en la consulta del estudiante.");

}


$stmt->bind_param(
    "i",
    $idUsuario
);


$stmt->execute();


$resultadoUsuario =
    $stmt->get_result();


if (
    $resultadoUsuario->num_rows === 0
) {

    $stmt->close();

    session_unset();
    session_destroy();

    header("Location: login.php");

    exit();

}


$estudiante =
    $resultadoUsuario->fetch_assoc();


$stmt->close();


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
========================================================= */

$stmtEleccion =
    $conn->prepare("

        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado

        FROM elecciones

        WHERE LOWER(TRIM(estado))
            = 'abierta'

        ORDER BY id DESC

        LIMIT 1

    ");


$stmtEleccion->execute();


$resultadoEleccion =
    $stmtEleccion->get_result();


if (
    $resultadoEleccion->num_rows === 0
) {

    $eleccion = null;

} else {

    $eleccion =
        $resultadoEleccion->fetch_assoc();

}


$stmtEleccion->close();


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$yaVoto = false;

$votacionRealizada = false;

$cargos = [];


/* =========================================================
   SI EXISTE ELECCIÓN
========================================================= */

if ($eleccion) {


    $idEleccion =
        (int)$eleccion['id'];


    /* =====================================================
       COMPROBAR SI YA VOTÓ EN ESTA ELECCIÓN
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

            FROM votos v

            INNER JOIN candidatos c

                ON c.id = v.id_candidato

            WHERE v.id_usuario = ?

            AND c.id_eleccion = ?

        ");


    $stmt->bind_param(
        "ii",
        $idUsuario,
        $idEleccion
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $datos =
        $resultado->fetch_assoc();


    if (
        (int)$datos['total'] > 0
    ) {

        $yaVoto = true;

    }


    $stmt->close();


    /* =====================================================
       OBTENER CARGOS
    ===================================================== */

    $stmt =
        $conn->prepare("

            SELECT

                c.id,
                c.nombre_cargo

            FROM cargos c

            INNER JOIN eleccion_cargos ec

                ON ec.id_cargo = c.id

            WHERE ec.id_eleccion = ?

            ORDER BY c.id ASC

        ");


    $stmt->bind_param(
        "i",
        $idEleccion
    );


    $stmt->execute();


    $resultadoCargos =
        $stmt->get_result();


    while (
        $cargo =
        $resultadoCargos->fetch_assoc()
    ) {

        $cargo['id'] =
            (int)$cargo['id'];

        $cargo['candidatos'] =
            [];

        $cargos[] =
            $cargo;

    }


    $stmt->close();


    /* =====================================================
       OBTENER CANDIDATOS POR CARGO
    ===================================================== */

    foreach (
        $cargos as &$cargo
    ) {


        $idCargo =
            (int)$cargo['id'];


        $stmt =
            $conn->prepare("

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

                ORDER BY
                    nombre ASC,
                    apellido ASC

            ");


        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );


        $stmt->execute();


        $resultadoCandidatos =
            $stmt->get_result();


        while (
            $candidato =
            $resultadoCandidatos->fetch_assoc()
        ) {

            $cargo['candidatos'][] =
                $candidato;

        }


        $stmt->close();

    }


    unset($cargo);


    /* =====================================================
       PROCESAR VOTACIÓN
    ===================================================== */

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['votar'])
    ) {


        /* =============================================
           ELECCIÓN ABIERTA
        ============================================= */

        if ($yaVoto) {

            $mensaje =
                "Usted ya realizó su votación en esta elección.";

            $tipoMensaje =
                "warning";

        } else {


            $selecciones =
                $_POST['candidato'] ?? [];


            if (
                !is_array($selecciones)
            ) {

                $selecciones =
                    [];

            }


            /* =========================================
               VERIFICAR TODOS LOS CARGOS
            ========================================= */

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


                /* =====================================
                   INICIAR TRANSACCIÓN
                ===================================== */

                $conn->begin_transaction();


                try {


                    /* =================================
                       SEGUNDA PROTECCIÓN
                    ================================= */

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


                    $stmtBloqueo->bind_param(
                        "ii",
                        $idUsuario,
                        $idEleccion
                    );


                    $stmtBloqueo->execute();


                    $resultadoBloqueo =
                        $stmtBloqueo->get_result();


                    if (
                        $resultadoBloqueo->num_rows > 0
                    ) {

                        throw new Exception(
                            "Usted ya realizó su votación en esta elección."
                        );

                    }


                    /* =================================
                       PREPARAR VALIDACIÓN
                    ================================= */

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


                    /* =================================
                       PREPARAR INSERT
                    ================================= */

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


                    if (
                        !$stmtValidar ||
                        !$stmtInsertar
                    ) {

                        throw new Exception(
                            "No se pudo preparar el registro de votos."
                        );

                    }


                    /* =================================
                       GUARDAR VOTOS
                    ================================= */

                    foreach (
                        $cargos as $cargo
                    ) {

                        $idCargo =
                            (int)$cargo['id'];


                        $idCandidato =
                            (int)$selecciones[$idCargo];


                        /* =============================
                           VALIDAR CANDIDATO
                        ============================= */

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
                                "Uno de los candidatos seleccionados no pertenece a esta elección."
                            );

                        }


                        /* =============================
                           INSERTAR
                        ============================= */

                        $stmtInsertar->bind_param(
                            "iii",
                            $idUsuario,
                            $idCandidato,
                            $idCargo
                        );


                        if (
                            !$stmtInsertar->execute()
                        ) {

                            throw new Exception(
                                "No se pudo registrar la votación."
                            );

                        }

                    }


                    /* =================================
                       CERRAR CONSULTAS
                    ================================= */

                    $stmtBloqueo->close();

                    $stmtValidar->close();

                    $stmtInsertar->close();


                    /* =================================
                       CONFIRMAR
                    ================================= */

                    $conn->commit();


                    $votacionRealizada =
                        true;


                    $yaVoto =
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

    margin:0;

    background:#eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.topbar {

    background:#1453a3;

    color:white;

    padding:18px 30px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    gap:10px;

}


.contenedor {

    max-width:1150px;

    margin:auto;

    padding:35px 20px;

}


.bienvenida {

    background:white;

    border-radius:18px;

    padding:25px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    margin-bottom:25px;

}


.titulo {

    color:#1453a3;

    font-weight:bold;

}


.estado-abierta {

    background:#198754;

    color:white;

    padding:7px 15px;

    border-radius:7px;

    font-weight:bold;

}


.estado-cerrada {

    background:#dc3545;

    color:white;

    padding:7px 15px;

    border-radius:7px;

    font-weight:bold;

}


.cargo {

    background:white;

    border-radius:18px;

    margin-bottom:25px;

    overflow:hidden;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

}


.cargo-header {

    background:#1453a3;

    color:white;

    padding:20px 25px;

}


.cargo-header h3 {

    margin:0;

    font-weight:bold;

}


.cargo-body {

    padding:25px;

}


.candidato {

    background:white;

    border:2px solid #e0e6ed;

    border-radius:15px;

    padding:20px;

    height:100%;

    cursor:pointer;

    transition:.2s;

}


.candidato:hover {

    border-color:#1473ed;

    background:#f5f9ff;

}


.candidato.seleccionado {

    border-color:#1473ed;

    background:#eaf3ff;

}


.candidato input {

    display:none;

}


.foto-candidato {

    width:120px;

    height:120px;

    object-fit:cover;

    border-radius:50%;

    border:5px solid #e5edf8;

}


.foto-vacia {

    width:120px;

    height:120px;

    border-radius:50%;

    background:#dbe8f8;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:auto;

    font-size:50px;

    color:#1453a3;

}


.nombre-candidato {

    color:#1453a3;

    font-size:19px;

    font-weight:bold;

}


.btn-votar {

    width:100%;

    padding:14px;

    font-size:19px;

    font-weight:bold;

}


.exito {

    background:white;

    border-radius:20px;

    padding:50px;

    text-align:center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);

}


.exito i {

    font-size:80px;

    color:#198754;

}


</style>

</head>


<body>


<!-- =====================================================
     BARRA SUPERIOR
===================================================== -->

<div class="topbar">


<div>

<i class="bi bi-mortarboard-fill"></i>

<strong>

Sistema de Votaciones Escolares

</strong>

</div>


<div>

<i class="bi bi-person-fill"></i>

<?php echo htmlspecialchars(
    $estudiante['nombre']
); ?>

</div>


</div>


<div class="contenedor">


<?php if (
    $votacionRealizada
) { ?>


<!-- =====================================================
     VOTACIÓN REGISTRADA
===================================================== -->

<div class="exito">


<i class="bi bi-check-circle-fill"></i>


<h1 class="text-success mt-3">

¡Votación registrada!

</h1>


<p class="fs-5">

Su votación fue registrada
correctamente.

</p>


<div class="alert alert-success">

<i class="bi bi-shield-check"></i>

<strong>

Gracias por participar.

</strong>

No podrá volver a votar en esta elección.

</div>


<a
href="logout.php"
class="btn btn-outline-danger btn-lg mt-3">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>


</div>


<?php } else { ?>


<!-- =====================================================
     INFORMACIÓN DEL ESTUDIANTE
===================================================== -->

<div class="bienvenida">


<h2 class="titulo">

🗳️ Votación Escolar

</h2>


<p>

Bienvenido:

<strong>

<?php echo htmlspecialchars(
    $estudiante['nombre'] .
    " " .
    $estudiante['apellido']
); ?>

</strong>

</p>


<p>

Curso:

<strong>

<?php echo htmlspecialchars(
    $estudiante['curso']
); ?>

</strong>

</p>


<hr>


<?php if (
    $eleccion
) { ?>


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>


<h4>

<?php echo htmlspecialchars(
    $eleccion['nombre']
); ?>

</h4>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="text-muted mb-0">

<?php echo htmlspecialchars(
    $eleccion['descripcion']
); ?>

</p>

<?php } ?>


</div>


<div>

<span class="estado-abierta">

🟢 Elección abierta

</span>

</div>


</div>


<?php } else { ?>


<div class="alert alert-danger mb-0">

<i class="bi bi-lock-fill"></i>

No existe una elección abierta actualmente.

</div>


<?php } ?>


</div>


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


<?php if (
    $yaVoto
) { ?>


<!-- =====================================================
     YA VOTÓ
===================================================== -->

<div class="alert alert-success text-center p-5">

<i
class="bi bi-check-circle-fill fs-1 text-success">
</i>


<h2 class="mt-3">

Ya realizó su votación

</h2>


<p class="fs-5">

Usted ya tiene una votación registrada
en esta elección.

</p>


<p class="mb-0">

No es posible volver a votar.

</p>


</div>


<?php } elseif (
    $eleccion &&
    count($cargos) === 0
) { ?>


<div class="alert alert-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

La elección no tiene cargos configurados.

</div>


<?php } elseif (
    $eleccion
) { ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

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


<div class="row g-4">


<?php if (
    count($cargo['candidatos']) === 0
) { ?>


<div class="col-12">

<div class="alert alert-warning">

No hay candidatos para este cargo.

</div>

</div>


<?php } else { ?>


<?php foreach (
    $cargo['candidatos']
    as $candidato
) { ?>


<div class="col-md-6 col-lg-4">


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


<!-- FOTO -->

<div class="text-center mb-3">


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
    file_exists(
        __DIR__ .
        "/" .
        $rutaFoto
    )
) {

?>


<img

src="<?php echo htmlspecialchars(
    $rutaFoto
); ?>"

class="foto-candidato"

alt="Foto del candidato">


<?php

} else {

?>


<div class="foto-vacia">

<i class="bi bi-person-fill"></i>

</div>


<?php

}

?>


</div>


<!-- NOMBRE -->

<div
class="nombre-candidato text-center">

<?php echo htmlspecialchars(
    $candidato['nombre'] .
    " " .
    $candidato['apellido']
); ?>

</div>


<p class="text-center text-muted">

Curso:

<?php echo htmlspecialchars(
    $candidato['curso']
); ?>

</p>


<?php if (
    !empty(
        $candidato['propuestas']
    )
) { ?>


<div class="alert alert-light">

<strong>

Propuestas:

</strong>


<br>


<?php echo nl2br(
    htmlspecialchars(
        $candidato['propuestas']
    )
); ?>


</div>


<?php } ?>


</label>


</div>


<?php } ?>


<?php } ?>


</div>


</div>


</div>


<?php } ?>


<!-- =====================================================
     BOTÓN
===================================================== -->

<button

type="submit"

name="votar"

class="btn btn-primary btn-votar"

onclick="
return confirmarVotacion();
">

<i class="bi bi-check-circle-fill"></i>

Registrar mi votación

</button>


</form>


<?php } ?>


<!-- =====================================================
     CERRAR SESIÓN
===================================================== -->

<div class="text-center mt-4">

<a
href="logout.php"
class="btn btn-outline-danger">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>

</div>


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


    if (!formulario) {

        return false;

    }


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

                behavior:
                    "smooth",

                block:
                    "center"

            });


            return false;

        }

    }


    return confirm(

        "¿Está seguro de registrar su votación?\n\n" +

        "Después de registrarla no podrá volver a votar en esta elección."

    );

}

</script>


</body>

</html>
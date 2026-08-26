<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();

verificarRol(['jurado']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR ESTUDIANTE EN PROCESO
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$idEstudiante =
    (int)$_SESSION['estudiante_votando_id'];


/* =========================================================
   VERIFICAR ELECCIÓN EN SESIÓN
========================================================= */

if (
    !isset($_SESSION['eleccion_votante_id']) ||
    (int)$_SESSION['eleccion_votante_id'] <= 0
) {

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$idEleccion =
    (int)$_SESSION['eleccion_votante_id'];


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";

$tipoMensaje = "";

$eleccion = null;

$estudiante = null;

$cargos = [];


/* =========================================================
   OBTENER ESTUDIANTE REAL
========================================================= */

$stmt =
    $conn->prepare("

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

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $idEstudiante
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$estudiante =
    $resultado->fetch_assoc();


$stmt->close();


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


if (!$stmt) {

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

    header(
        "Location: ingresar_estudiante.php"
    );

    exit();

}


$eleccion =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   VERIFICAR ELECCIÓN ABIERTA
========================================================= */

if (
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) !== "abierta"
) {

    $mensaje =
        "La elección está cerrada. No se puede continuar.";

    $tipoMensaje =
        "danger";

}


/* =========================================================
   VERIFICAR SI EL ESTUDIANTE YA VOTÓ
========================================================= */

$yaVoto = false;


if (
    $mensaje === ""
) {

    $stmt =
        $conn->prepare("

            SELECT
                id

            FROM votos

            WHERE id_usuario = ?

            AND id_eleccion = ?

            LIMIT 1

        ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEstudiante,
            $idEleccion
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        if (
            $resultado->num_rows > 0
        ) {

            $yaVoto = true;

        }


        $stmt->close();

    }

}


/* =========================================================
   SI YA VOTÓ
========================================================= */

if (
    $yaVoto
) {

    /*
     * Limpiamos el estudiante de la sesión
     * para impedir reutilizar la URL.
     */

    unset(
        $_SESSION['estudiante_votando_id'],
        $_SESSION['estudiante_votando_documento'],
        $_SESSION['estudiante_votando_nombre'],
        $_SESSION['estudiante_votando_curso'],
        $_SESSION['eleccion_votante_id']
    );

}


/* =========================================================
   OBTENER CARGOS
========================================================= */

if (
    $mensaje === "" &&
    !$yaVoto
) {

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

            $cargo['candidatos'] =
                [];

            $cargos[] =
                $cargo;

        }


        $stmt->close();

    }

}


/* =========================================================
   OBTENER CANDIDATOS POR CARGO
========================================================= */

if (
    count($cargos) > 0
) {

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


        if (!$stmt) {

            continue;

        }


        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        while (
            $candidato =
            $resultado->fetch_assoc()
        ) {

            $cargo['candidatos'][] =
                $candidato;

        }


        $stmt->close();

    }


    unset($cargo);

}


/* =========================================================
   TOKEN CSRF
========================================================= */

if (
    !isset(
        $_SESSION['csrf_votacion']
    )
) {

    $_SESSION['csrf_votacion'] =
        bin2hex(
            random_bytes(32)
        );

}


$csrf =
    $_SESSION['csrf_votacion'];

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
Votación Escolar
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

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background:
        linear-gradient(
            135deg,
            #eef3f9,
            #e2ebf7
        );

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1e293b;

}


/* =========================================================
   HEADER
========================================================= */

.topbar {

    background:
        linear-gradient(
            135deg,
            #176df0,
            #0955b8
        );

    color: white;

    padding:
        18px 30px;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.15);

}


.topbar h1 {

    margin: 0;

    font-size: 22px;

    font-weight: 700;

}


.jurado {

    font-size: 14px;

    font-weight: 600;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    width:
        min(
            1150px,
            calc(100% - 30px)
        );

    margin:
        30px auto;

}


/* =========================================================
   AVISO DE SEGURIDAD
========================================================= */

.aviso-seguridad {

    background: #eaf3ff;

    border:
        1px solid #c8ddfa;

    border-radius: 14px;

    padding: 16px 20px;

    margin-bottom: 22px;

    color: #174f91;

    display: flex;

    gap: 12px;

    align-items: flex-start;

}


.aviso-seguridad i {

    font-size: 22px;

    color: #1473ed;

}


/* =========================================================
   ESTUDIANTE
========================================================= */

.estudiante {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 22px;

    box-shadow:
        0 7px 22px
        rgba(0,0,0,.08);

}


.estudiante h2 {

    color: #1459a6;

    margin:
        0 0 18px;

    font-size: 24px;

    font-weight: 700;

}


.dato {

    color: #536b84;

    margin-bottom: 5px;

}


.dato strong {

    color: #1e293b;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background:
        linear-gradient(
            135deg,
            #dcecff,
            #edf5ff
        );

    border:
        1px solid #bcd6f7;

    border-radius: 16px;

    padding: 22px;

    margin-bottom: 25px;

    color: #1453a3;

}


.eleccion h3 {

    margin:
        0 0 7px;

    font-weight: 700;

}


.estado {

    display: inline-block;

    background: #198754;

    color: white;

    padding:
        7px 15px;

    border-radius: 20px;

    font-weight: 700;

    font-size: 13px;

}


/* =========================================================
   CARGO
========================================================= */

.cargo {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-bottom: 25px;

    box-shadow:
        0 7px 22px
        rgba(0,0,0,.08);

}


.cargo-header {

    background:
        linear-gradient(
            135deg,
            #1459a6,
            #0d6efd
        );

    color: white;

    padding:
        19px 24px;

}


.cargo-header h3 {

    margin: 0;

    font-size: 21px;

    font-weight: 700;

}


.cargo-body {

    padding: 25px;

}


/* =========================================================
   CANDIDATO
========================================================= */

.candidato {

    display: block;

    height: 100%;

    background: #fff;

    border:
        2px solid #e1e8f0;

    border-radius: 16px;

    padding: 20px;

    cursor: pointer;

    transition:
        .2s ease;

}


.candidato:hover {

    transform:
        translateY(-3px);

    border-color: #1473ed;

    box-shadow:
        0 8px 20px
        rgba(20,115,237,.12);

}


.candidato.seleccionado {

    border-color: #1473ed;

    background: #edf5ff;

    box-shadow:
        0 0 0 3px
        rgba(20,115,237,.12);

}


.candidato input {

    position: absolute;

    opacity: 0;

}


.foto,
.sin-foto {

    width: 110px;

    height: 110px;

    border-radius: 50%;

    object-fit: cover;

    margin: 0 auto;

}


.sin-foto {

    background: #eaf1fa;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #6c8bab;

    font-size: 45px;

}


.nombre {

    color: #1453a3;

    font-weight: 700;

    font-size: 18px;

}


.curso {

    color: #6c757d;

    margin-top: 6px;

    font-size: 14px;

}


/* =========================================================
   PROPUESTAS
========================================================= */

.propuestas {

    margin-top: 15px;

    padding: 13px;

    border-radius: 10px;

    background: #f5f8fc;

    color: #536b84;

    font-size: 14px;

    text-align: left;

}


.propuestas strong {

    color: #1453a3;

}


/* =========================================================
   BOTÓN
========================================================= */

.btn-votar {

    width: 100%;

    padding: 16px;

    background:
        linear-gradient(
            135deg,
            #1473ed,
            #095cc9
        );

    border: none;

    border-radius: 13px;

    color: white;

    font-size: 19px;

    font-weight: 700;

    box-shadow:
        0 7px 18px
        rgba(20,115,237,.22);

    transition: .2s ease;

}


.btn-votar:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 24px
        rgba(20,115,237,.3);

}


/* =========================================================
   BLOQUEADO
========================================================= */

.bloqueado {

    background: white;

    border-radius: 20px;

    padding: 50px 30px;

    text-align: center;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.10);

}


.bloqueado-icono {

    font-size: 65px;

    color: #dc3545;

}


.bloqueado h2 {

    color: #1453a3;

    font-weight: 700;

}


/* =========================================================
   PIE
========================================================= */

.footer {

    text-align: center;

    color: #718096;

    padding:
        25px 10px 35px;

    font-size: 12px;

}


.footer strong {

    color: #1453a3;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:700px) {

    .topbar {

        padding:
            15px;

    }


    .topbar h1 {

        font-size: 18px;

    }


    .jurado {

        font-size: 12px;

    }


    .contenedor {

        width:
            calc(100% - 20px);

        margin-top: 20px;

    }


    .estudiante,
    .cargo-body {

        padding: 18px;

    }


    .foto,
    .sin-foto {

        width: 85px;

        height: 85px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="topbar">


<h1>

    <i class="bi bi-check2-square"></i>

    Sistema de Votaciones Escolares

</h1>


<div class="jurado">

    <i class="bi bi-shield-check"></i>

    Proceso supervisado por jurado

</div>


</header>


<main class="contenedor">


<!-- =====================================================
     AVISO
===================================================== -->

<div class="aviso-seguridad">

    <i class="bi bi-shield-lock-fill"></i>

    <div>

        <strong>
            Votación protegida
        </strong>

        <br>

        Esta pantalla corresponde únicamente al proceso
        de votación iniciado por el jurado.

        Seleccione sus candidatos y registre su voto
        una sola vez.

    </div>

</div>


<!-- =====================================================
     DATOS DEL ESTUDIANTE
===================================================== -->

<div class="estudiante">


<h2>

    <i class="bi bi-person-circle"></i>

    Estudiante que está votando

</h2>


<div class="row">


<div class="col-md-4 dato">

<strong>
Nombre:
</strong>

<br>

<?php

echo htmlspecialchars(
    $estudiante['nombre']
    . " "
    . $estudiante['apellido']
);

?>

</div>


<div class="col-md-4 dato">

<strong>
Documento:
</strong>

<br>

<?php

echo htmlspecialchars(
    $estudiante['documento']
);

?>

</div>


<div class="col-md-4 dato">

<strong>
Curso:
</strong>

<br>

<?php

echo htmlspecialchars(
    $estudiante['curso']
);

?>

</div>


</div>

</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="eleccion">


<div class="d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3">


<div>


<h3>

    <i class="bi bi-calendar-event-fill"></i>

    <?php

    echo htmlspecialchars(
        $eleccion['nombre']
    );

    ?>

</h3>


<?php if (
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<p class="mb-0">

<?php

echo htmlspecialchars(
    $eleccion['descripcion']
);

?>

</p>

<?php } ?>


</div>


<span class="estado">

    <i class="bi bi-unlock-fill"></i>

    Elección abierta

</span>


</div>

</div>


<!-- =====================================================
     MENSAJE
===================================================== -->

<?php if (
    $mensaje !== ""
) { ?>

<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje
);

?>">

<i class="bi bi-exclamation-triangle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>

</div>

<?php } ?>


<!-- =====================================================
     YA VOTÓ
===================================================== -->

<?php if (
    $yaVoto
) { ?>


<div class="bloqueado">


<i class="
    bi
    bi-shield-fill-check
    bloqueado-icono
"></i>


<h2 class="mt-3">

    Votación ya registrada

</h2>


<p class="fs-5 text-muted">

    Este estudiante ya realizó su votación
    en esta elección.

</p>


<p class="text-muted">

    No es posible volver a votar.

</p>


</div>


<?php } elseif (
    $mensaje !== ""
) { ?>


<div class="bloqueado">


<i class="
    bi
    bi-lock-fill
    bloqueado-icono
"></i>


<h2 class="mt-3">

    No se puede continuar

</h2>


<p class="fs-5 text-muted">

    <?php

    echo htmlspecialchars(
        $mensaje
    );

    ?>

</p>


</div>


<?php } elseif (
    count($cargos) === 0
) { ?>


<div class="alert alert-warning">

    <i class="bi bi-exclamation-triangle-fill"></i>

    Esta elección no tiene cargos configurados.

</div>


<?php } else { ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
    method="POST"
    action="registrar_voto_jurado.php"
    id="formVotacion"
>


<input
    type="hidden"
    name="csrf"
    value="<?php

    echo htmlspecialchars(
        $csrf
    );

    ?>"
>


<?php foreach (
    $cargos as $cargo
) { ?>


<div class="cargo">


<div class="cargo-header">

<h3>

    <i class="bi bi-award-fill"></i>

    <?php

    echo htmlspecialchars(
        $cargo['nombre_cargo']
    );

    ?>

</h3>

</div>


<div class="cargo-body">


<p class="text-muted">

    Seleccione un candidato:

</p>


<div class="row g-4">


<?php if (
    count(
        $cargo['candidatos']
    ) === 0
) { ?>


<div class="col-12">

<div class="alert alert-warning">

    No hay candidatos registrados
    para este cargo.

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
    onclick="seleccionar(this)"
>


<input

    type="radio"

    name="candidato[
        <?php

        echo (int)$cargo['id'];

        ?>
    ]"

    value="<?php

        echo (int)$candidato['id'];

    ?>"

    required
>


<!-- =================================================
     FOTO
================================================== -->

<?php

$foto =
    trim(
        (string)$candidato['foto']
    );


$rutaFoto =
    "uploads/candidatos/"
    .
    $foto;


if (
    $foto !== ""
    &&
    file_exists(
        __DIR__
        .
        "/"
        .
        $rutaFoto
    )
) {

?>

<div class="text-center mb-3">

<img

    src="<?php

        echo htmlspecialchars(
            $rutaFoto
        );

    ?>"

    class="foto"

    alt="Foto del candidato"

>

</div>

<?php

} else {

?>

<div class="text-center mb-3">

<div class="sin-foto">

    <i class="bi bi-person-fill"></i>

</div>

</div>

<?php

}

?>


<!-- =================================================
     INFORMACIÓN
================================================== -->

<div class="text-center">


<div class="nombre">

<?php

echo htmlspecialchars(
    $candidato['nombre']
    .
    " "
    .
    $candidato['apellido']
);

?>

</div>


<div class="curso">

    <i class="bi bi-mortarboard-fill"></i>

    Curso:

    <?php

    echo htmlspecialchars(
        $candidato['curso']
    );

    ?>

</div>


</div>


<!-- =================================================
     PROPUESTAS
================================================== -->

<?php if (
    !empty(
        $candidato['propuestas']
    )
) { ?>


<div class="propuestas">

<strong>

    <i class="bi bi-lightbulb-fill"></i>

    Propuestas

</strong>


<br><br>


<?php

echo nl2br(
    htmlspecialchars(
        $candidato['propuestas']
    )
);

?>

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


<!-- =================================================
     REGISTRAR
================================================== -->

<button

    type="submit"

    name="votar"

    class="btn-votar"

    onclick="
        return confirmarVotacion();
    "

>

    <i class="bi bi-check-circle-fill"></i>

    Registrar mi votación

</button>


</form>


<?php } ?>


</main>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

    © <?php echo date("Y"); ?>

    Sistema de Votaciones Escolares

    <br>

    Elaborado por

    <strong>
        Juan David Otero Cantor
    </strong>

    <br>

    Todos los derechos reservados.

</footer>


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

        "¿Está seguro de registrar su votación?\n\n"
        +
        "Después de registrarla no podrá volver a votar "
        +
        "en esta elección."

    );

}

</script>


</body>

</html>
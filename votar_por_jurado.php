<?php

session_start();

include("config/conexion.php");


/* =========================================================
   VERIFICAR JURADO
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
   VERIFICAR ESTUDIANTE SELECCIONADO
========================================================= */

if (
    !isset($_SESSION['estudiante_votando_id']) ||
    (int)$_SESSION['estudiante_votando_id'] <= 0
) {
    header("Location: ingresar_estudiante.php");
    exit();
}


$idEstudiante = (int)$_SESSION['estudiante_votando_id'];

$nombreEstudiante =
    $_SESSION['estudiante_votando_nombre'] ?? 'Estudiante';

$documentoEstudiante =
    $_SESSION['estudiante_votando_documento'] ?? '';


/* =========================================================
   OBTENER ELECCIÓN ABIERTA
========================================================= */

$eleccion = null;

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        fecha_inicio,
        fecha_fin,
        estado
    FROM elecciones
    WHERE estado = 'abierta'
    ORDER BY id DESC
    LIMIT 1
");

if ($stmt) {

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $eleccion = $resultado->fetch_assoc();
    }

    $stmt->close();
}


if (!$eleccion) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:80px;
        '>

            <h2 style='color:#dc3545;'>
                No hay una elección abierta.
            </h2>

            <a href='ingresar_estudiante.php'>
                Volver
            </a>

        </div>
    ");

}


$idEleccion = (int)$eleccion['id'];


/* =========================================================
   COMPROBAR SI EL ESTUDIANTE YA VOTÓ
========================================================= */

$stmt = $conn->prepare("
    SELECT id
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

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $stmt->close();

        unset(
            $_SESSION['estudiante_votando_id'],
            $_SESSION['estudiante_votando_documento'],
            $_SESSION['estudiante_votando_nombre'],
            $_SESSION['estudiante_votando_curso'],
            $_SESSION['eleccion_votante_id']
        );

        die("
            <div style='
                font-family:Arial;
                text-align:center;
                margin-top:80px;
            '>

                <div style='
                    font-size:60px;
                    color:#dc3545;
                '>
                    🔒
                </div>

                <h2 style='color:#dc3545;'>
                    Este estudiante ya votó
                </h2>

                <p>
                    El estudiante ya tiene una votación
                    registrada en esta elección.
                </p>

                <a
                    href='ingresar_estudiante.php'
                    style='
                        display:inline-block;
                        padding:12px 20px;
                        background:#1976e8;
                        color:white;
                        text-decoration:none;
                        border-radius:8px;
                    '
                >
                    Volver a buscar estudiante
                </a>

            </div>
        ");

    }

    $stmt->close();
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
    die(
        "Error al consultar los cargos: "
        . $conn->error
    );
}

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$resultado = $stmt->get_result();

while ($cargo = $resultado->fetch_assoc()) {

    $cargo['id'] = (int)$cargo['id'];

    $cargo['candidatos'] = [];

    $cargos[] = $cargo;
}

$stmt->close();


/* =========================================================
   OBTENER CANDIDATOS
========================================================= */

foreach ($cargos as &$cargo) {

    $idCargo = (int)$cargo['id'];

    $stmt = $conn->prepare("
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

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($candidato = $resultado->fetch_assoc()) {

            $cargo['candidatos'][] = $candidato;

        }

        $stmt->close();
    }
}

unset($cargo);

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
    Votación del estudiante
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

body {

    margin: 0;

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1e293b;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    background: #1976e8;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    flex-wrap: wrap;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);
}


.header h3 {

    margin: 0;

    font-size: 22px;

    font-weight: 600;
}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    max-width: 1100px;

    margin: auto;

    padding: 30px 20px;
}


/* =========================================================
   TARJETA ESTUDIANTE
========================================================= */

.estudiante {

    background: white;

    border-radius: 16px;

    padding: 22px;

    margin-bottom: 20px;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 4px 14px
        rgba(0,0,0,.07);
}


.estudiante h2 {

    color: #1459a6;

    font-weight: 700;
}


.dato {

    color: #64748b;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: #dbeafe;

    border:
        1px solid #bfdbfe;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 25px;

    color: #084298;
}


.eleccion h4 {

    font-weight: 700;
}


/* =========================================================
   CARGO
========================================================= */

.cargo {

    background: white;

    border-radius: 16px;

    margin-bottom: 22px;

    overflow: hidden;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 4px 14px
        rgba(0,0,0,.07);
}


.cargo-header {

    background: #1459a6;

    color: white;

    padding: 17px 22px;
}


.cargo-header h3 {

    margin: 0;

    font-size: 21px;

    font-weight: 700;
}


.cargo-body {

    padding: 22px;
}


/* =========================================================
   CANDIDATO
========================================================= */

.candidato {

    display: block;

    border:
        2px solid #e2e8f0;

    border-radius: 14px;

    padding: 16px;

    margin-bottom: 13px;

    cursor: pointer;

    transition: .2s;
}


.candidato:hover {

    border-color: #1976e8;

    background: #f8fbff;
}


.candidato.seleccionado {

    border-color: #1976e8;

    background: #eaf3ff;
}


.candidato input {

    display: none;
}


.candidato-contenido {

    display: flex;

    align-items: center;

    gap: 17px;
}


/* =========================================================
   FOTO
========================================================= */

.foto {

    width: 75px;

    height: 75px;

    border-radius: 50%;

    object-fit: cover;
}


.sin-foto {

    width: 75px;

    height: 75px;

    border-radius: 50%;

    background: #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    color: #1459a6;
}


/* =========================================================
   INFORMACIÓN
========================================================= */

.nombre {

    color: #1459a6;

    font-size: 18px;

    font-weight: 700;
}


.curso {

    color: #64748b;

    margin-top: 4px;
}


/* =========================================================
   BOTÓN
========================================================= */

.btn-votar {

    width: 100%;

    padding: 15px;

    border: none;

    border-radius: 12px;

    background: #1976e8;

    color: white;

    font-size: 18px;

    font-weight: 700;

    transition: .2s;
}


.btn-votar:hover {

    background: #125fc0;
}


.btn-volver {

    width: 100%;

    margin-top: 12px;

    padding: 12px;

    border-radius: 10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .header {

        padding: 15px;
    }

    .header h3 {

        font-size: 18px;
    }

    .contenedor {

        padding:
            20px 12px;
    }

    .candidato-contenido {

        align-items: flex-start;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<div class="header">

    <h3>

        <i class="bi bi-check2-square"></i>

        Sistema de Votaciones Escolares

    </h3>


    <div>

        <i class="bi bi-person-badge-fill"></i>

        Jurado:

        <?php

        echo htmlspecialchars(
            $_SESSION['nombre'] ?? 'Jurado'
        );

        ?>

    </div>

</div>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<div class="contenedor">


<!-- =====================================================
     ESTUDIANTE
===================================================== -->

<div class="estudiante">

    <h2>

        <i class="bi bi-person-vcard-fill"></i>

        Estudiante seleccionado

    </h2>

    <hr>

    <div>

        <strong>

            <?php

            echo htmlspecialchars(
                $nombreEstudiante
            );

            ?>

        </strong>

    </div>


    <div class="dato">

        <i class="bi bi-card-text"></i>

        Documento:

        <?php

        echo htmlspecialchars(
            $documentoEstudiante
        );

        ?>

    </div>

</div>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<div class="eleccion">

    <h4>

        <i class="bi bi-calendar-event-fill"></i>

        <?php

        echo htmlspecialchars(
            $eleccion['nombre']
        );

        ?>

    </h4>


    <?php if (
        !empty($eleccion['descripcion'])
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


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
    method="POST"
    action="registrar_voto.php"
    id="formVotacion"
>


<?php if (
    count($cargos) === 0
) { ?>

    <div class="alert alert-warning">

        <i class="bi bi-exclamation-triangle-fill"></i>

        Esta elección no tiene cargos configurados.

    </div>

<?php } else { ?>


<!-- =====================================================
     CARGOS
===================================================== -->

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


        <?php if (
            count($cargo['candidatos']) === 0
        ) { ?>


            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle-fill"></i>

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
                    onclick="seleccionar(this)"
                >


                    <!--
                        IMPORTANTE:

                        El nombre contiene el ID
                        REAL del cargo.

                        Ejemplo:

                        candidato[1]
                        candidato[2]
                    -->

                    <input

                        type="radio"

                        name="candidato[<?php
                            echo (int)$cargo['id'];
                        ?>]"

                        value="<?php
                            echo (int)$candidato['id'];
                        ?>"

                        required

                    >


                    <div class="candidato-contenido">


                        <?php

                        $foto = trim(
                            (string)$candidato['foto']
                        );


                        $rutaFoto =
                            "uploads/candidatos/"
                            . $foto;


                        if (
                            $foto !== "" &&
                            file_exists(
                                __DIR__
                                . "/"
                                . $rutaFoto
                            )
                        ) {

                        ?>

                            <img

                                src="<?php
                                    echo htmlspecialchars(
                                        $rutaFoto
                                    );
                                ?>"

                                class="foto"

                                alt="Foto del candidato"

                            >

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

                                <?php

                                echo htmlspecialchars(
                                    $candidato['nombre']
                                    . " "
                                    .
                                    $candidato['apellido']
                                );

                                ?>

                            </div>


                            <div class="curso">

                                <i
                                    class="bi bi-mortarboard-fill"
                                ></i>

                                Curso:

                                <?php

                                echo htmlspecialchars(
                                    $candidato['curso']
                                );

                                ?>

                            </div>


                        </div>


                    </div>


                </label>


            <?php } ?>


        <?php } ?>


    </div>


</div>


<?php } ?>


<!-- =====================================================
     BOTÓN REGISTRAR
===================================================== -->

<button

    type="submit"

    name="registrar"

    class="btn-votar"

    onclick="return confirmarVotacion();"

>

    <i class="bi bi-check-circle-fill"></i>

    Registrar votación

</button>


<?php } ?>


</form>


<!-- =====================================================
     CAMBIAR ESTUDIANTE
===================================================== -->

<a

    href="ingresar_estudiante.php"

    class="btn btn-outline-secondary btn-volver"

>

    <i class="bi bi-arrow-left"></i>

    Cambiar estudiante

</a>


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
        function(item) {

            item.classList.remove(
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

        const candidatos =
            cargos[i].querySelectorAll(
                "input[type='radio']"
            );


        /* ==============================================
           SI NO HAY CANDIDATOS
        ============================================== */

        if (
            candidatos.length === 0
        ) {

            alert(
                "Este cargo no tiene candidatos registrados."
            );


            cargos[i].scrollIntoView({
                behavior: "smooth",
                block: "center"
            });


            return false;

        }


        /* ==============================================
           VERIFICAR SELECCIÓN
        ============================================== */

        const seleccionado =
            cargos[i].querySelector(
                "input[type='radio']:checked"
            );


        if (
            !seleccionado
        ) {

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


    /* ==============================================
       CONFIRMACIÓN FINAL
    ============================================== */

    return confirm(

        "¿Está seguro de registrar la votación?\n\n" +

        "Después de registrar los votos " +

        "no podrán modificarse."

    );

}

</script>


</body>

</html>
<?php

session_start();

require_once __DIR__ . "/config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/* =========================================================
   FUNCIÓN: OBTENER NOMBRE DEL CARGO
========================================================= */

function obtenerNombreCargo($cargos, $idCargo)
{
    foreach ($cargos as $cargo) {

        if ((int)$cargo['id_cargo'] === (int)$idCargo) {
            return $cargo['nombre_cargo'];
        }
    }

    return "seleccionado";
}


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol'])
) {
    die("Sesión de jurado no válida.");
}

$rol = strtolower(trim((string)$_SESSION['rol']));

if ($rol !== "jurado") {
    die("Sesión de jurado no válida.");
}

$idJurado = (int)$_SESSION['id'];

$nombreJurado = $_SESSION['nombre'] ?? "Jurado";


/* =========================================================
   OBTENER ELECCIÓN DEL JURADO
========================================================= */

$idEleccion = 0;

if (isset($_SESSION['id_eleccion_jurado'])) {

    $idEleccion =
        (int)$_SESSION['id_eleccion_jurado'];
}

if (
    $idEleccion <= 0 &&
    isset($_SESSION['eleccion_votando_id'])
) {

    $idEleccion =
        (int)$_SESSION['eleccion_votando_id'];
}


if ($idEleccion <= 0) {

    die(
        "No se pudo identificar la elección. " .
        "Regrese al panel del jurado y presione " .
        "\"Comenzar votación\" nuevamente."
    );
}


/* =========================================================
   VERIFICAR QUE LA ELECCIÓN EXISTA Y ESTÉ ABIERTA
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        estado
    FROM elecciones
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$resultadoEleccion =
    $stmt->get_result();

$eleccion =
    $resultadoEleccion->fetch_assoc();

$stmt->close();


if (!$eleccion) {

    die(
        "La elección seleccionada no existe."
    );
}


if (
    strtolower(
        trim(
            $eleccion['estado']
        )
    ) !== "abierta"
) {

    die(
        "La elección está cerrada."
    );
}


/* =========================================================
   OBTENER ESTUDIANTE
========================================================= */

$idEstudiante = 0;

if (
    isset(
        $_SESSION['estudiante_votando_id']
    )
) {

    $idEstudiante =
        (int)$_SESSION['estudiante_votando_id'];
}


if ($idEstudiante <= 0) {

    die(
        "No se pudo identificar al estudiante. " .
        "Regrese a ingresar_estudiante.php " .
        "y vuelva a ingresar el documento."
    );
}


/* =========================================================
   BUSCAR DATOS DEL ESTUDIANTE
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
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $idEstudiante
);

$stmt->execute();

$resultadoEstudiante =
    $stmt->get_result();

$estudiante =
    $resultadoEstudiante->fetch_assoc();

$stmt->close();


if (!$estudiante) {

    unset(
        $_SESSION['estudiante_votando_id']
    );

    die(
        "El estudiante no existe."
    );
}


/* =========================================================
   OBTENER SOLAMENTE LOS CARGOS
   CONFIGURADOS PARA ESTA ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        ec.id_cargo,
        c.nombre_cargo

    FROM eleccion_cargos ec

    INNER JOIN cargos c
        ON c.id = ec.id_cargo

    WHERE ec.id_eleccion = ?

    ORDER BY ec.id_cargo ASC
");

$stmt->bind_param(
    "i",
    $idEleccion
);

$stmt->execute();

$resultadoCargos =
    $stmt->get_result();

$cargos = [];


while (
    $fila =
    $resultadoCargos->fetch_assoc()
) {

    $cargos[] = $fila;
}


$stmt->close();


/* =========================================================
   VERIFICAR QUE EXISTAN CARGOS
========================================================= */

if (count($cargos) === 0) {

    die(
        "Esta elección no tiene cargos configurados. " .
        "Configure los cargos desde el sistema de administración."
    );
}


/* =========================================================
   PROCESAR VOTACIÓN
========================================================= */

$error = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $votosRecibidos =
        $_POST['votos'] ?? [];


    if (!is_array($votosRecibidos)) {

        $votosRecibidos = [];
    }


    $selecciones = [];


    /* =====================================================
       VALIDAR CADA CARGO
    ===================================================== */

    foreach ($cargos as $cargo) {

        $idCargo =
            (int)$cargo['id_cargo'];


        if (
            !isset(
                $votosRecibidos[$idCargo]
            ) ||
            !is_numeric(
                $votosRecibidos[$idCargo]
            )
        ) {

            $error =
                "Debe seleccionar un candidato para el cargo: " .
                $cargo['nombre_cargo'];

            break;
        }


        $idCandidato =
            (int)$votosRecibidos[$idCargo];


        if ($idCandidato <= 0) {

            $error =
                "Debe seleccionar un candidato para el cargo: " .
                $cargo['nombre_cargo'];

            break;
        }


        $selecciones[$idCargo] =
            $idCandidato;
    }


    /* =====================================================
       GUARDAR VOTACIÓN
    ===================================================== */

    if ($error === "") {

        try {

            $conn->begin_transaction();


            foreach (
                $selecciones
                as $idCargo => $idCandidato
            ) {


                /* =========================================
                   VALIDAR CANDIDATO
                ========================================= */

                $stmt = $conn->prepare("
                    SELECT id

                    FROM candidatos

                    WHERE id = ?
                      AND id_eleccion = ?
                      AND id_cargo = ?

                    LIMIT 1
                ");

                $stmt->bind_param(
                    "iii",
                    $idCandidato,
                    $idEleccion,
                    $idCargo
                );

                $stmt->execute();

                $resultadoCandidato =
                    $stmt->get_result();

                $candidatoValido =
                    $resultadoCandidato->fetch_assoc();

                $stmt->close();


                if (!$candidatoValido) {

                    throw new Exception(
                        "El candidato seleccionado no pertenece " .
                        "al cargo correspondiente."
                    );
                }


                /* =========================================
                   VERIFICAR DOBLE VOTO
                ========================================= */

                $stmt = $conn->prepare("
                    SELECT id

                    FROM votos

                    WHERE id_usuario = ?
                      AND id_eleccion = ?
                      AND id_cargo = ?

                    LIMIT 1
                ");

                $stmt->bind_param(
                    "iii",
                    $idEstudiante,
                    $idEleccion,
                    $idCargo
                );

                $stmt->execute();

                $resultadoExistente =
                    $stmt->get_result();

                $votoExistente =
                    $resultadoExistente->fetch_assoc();

                $stmt->close();


                if ($votoExistente) {

                    throw new Exception(
                        "El estudiante ya tiene registrado " .
                        "un voto para el cargo: " .
                        obtenerNombreCargo(
                            $cargos,
                            $idCargo
                        )
                    );
                }


                /* =========================================
                   INSERTAR VOTO
                ========================================= */

                $stmt = $conn->prepare("
                    INSERT INTO votos
                    (
                        id_usuario,
                        id_candidato,
                        id_eleccion,
                        id_cargo
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->bind_param(
                    "iiii",
                    $idEstudiante,
                    $idCandidato,
                    $idEleccion,
                    $idCargo
                );

                $stmt->execute();

                $stmt->close();
            }


            /* =========================================
               CONFIRMAR TRANSACCIÓN
            ========================================= */

            $conn->commit();


            /* =========================================
               LIMPIAR ESTUDIANTE ACTUAL
            ========================================= */

            unset(
                $_SESSION['estudiante_votando_id']
            );


            /* =========================================
               MENSAJE DE ÉXITO
            ========================================= */

            $_SESSION['mensaje_votacion'] =
                "La votación de " .
                $estudiante['nombre'] .
                " " .
                $estudiante['apellido'] .
                " fue registrada correctamente.";


            /* =========================================
               VOLVER A INGRESAR ESTUDIANTE
            ========================================= */

            header(
                "Location: ingresar_estudiante.php"
            );

            exit;


        } catch (Throwable $e) {

            try {

                $conn->rollback();

            } catch (Throwable $rollbackError) {
                // No hacer nada.
            }


            $error =
                $e->getMessage();
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
    content="width=device-width, initial-scale=1.0"
>

<title>
    Votación -
    <?php
    echo htmlspecialchars(
        $eleccion['nombre'],
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef4fb;

    color: #183b67;
}


/* =====================================================
   HEADER
===================================================== */

.header {

    background:
        linear-gradient(
            135deg,
            #1266d6,
            #0751b8
        );

    color: white;

    padding: 22px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            0.12
        );
}


.header h1 {

    margin: 0;

    font-size: 26px;
}


.jurado {

    font-size: 15px;
}


/* =====================================================
   CONTENEDOR
===================================================== */

.container {

    width: 90%;

    max-width: 1100px;

    margin: 35px auto;
}


/* =====================================================
   ESTUDIANTE
===================================================== */

.student {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 8px 25px
        rgba(
            30,
            80,
            130,
            0.10
        );
}


.student h2 {

    margin-top: 0;

    color: #1266d6;
}


.student-grid {

    display: grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap: 20px;
}


.label {

    font-size: 13px;

    font-weight: bold;

    color: #1266d6;
}


.value {

    margin-top: 5px;

    font-size: 16px;
}


/* =====================================================
   ELECCIÓN
===================================================== */

.election {

    background: #dcecff;

    border: 1px solid #a8ccff;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;
}


.election h2 {

    margin: 0 0 8px;

    color: #145cae;
}


.status {

    display: inline-block;

    margin-top: 12px;

    background: #159447;

    color: white;

    padding: 9px 16px;

    border-radius: 30px;

    font-weight: bold;
}


/* =====================================================
   ERROR
===================================================== */

.error {

    background: #fff0f0;

    border: 1px solid #ffb7b7;

    color: #c62828;

    border-radius: 12px;

    padding: 15px;

    margin-bottom: 20px;
}


/* =====================================================
   CARGO
===================================================== */

.cargo {

    background: white;

    border-radius: 18px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 8px 25px
        rgba(
            30,
            80,
            130,
            0.10
        );
}


.cargo-title {

    color: #1266d6;

    font-size: 24px;

    margin-top: 0;
}


/* =====================================================
   TARJETA CANDIDATO
===================================================== */

.candidate {

    position: relative;

    display: block;

    width: 100%;

    border: 2px solid #d5e2f2;

    border-radius: 16px;

    padding: 20px;

    margin-top: 15px;

    cursor: pointer;

    background: #ffffff;

    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.15s ease;
}


.candidate:hover {

    border-color: #1266d6;

    box-shadow:
        0 8px 20px
        rgba(
            20,
            100,
            210,
            0.12
        );

    transform:
        translateY(-1px);
}


/*
   Ocultamos el radio visualmente,
   pero sigue siendo funcional.
*/

.candidate input[type="radio"] {

    position: absolute;

    width: 1px;

    height: 1px;

    opacity: 0;

    pointer-events: none;
}


/*
   Tarjeta seleccionada
*/

.candidate.selected {

    border-color: #1266d6;

    background: #eef6ff;

    box-shadow:
        0 0 0 3px
        rgba(
            18,
            102,
            214,
            0.15
        );
}


/*
   Check azul
*/

.candidate.selected::after {

    content: "✓";

    position: absolute;

    top: 15px;

    right: 15px;

    width: 34px;

    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #1266d6;

    color: white;

    font-size: 20px;

    font-weight: bold;
}


/* =====================================================
   CONTENIDO CANDIDATO
===================================================== */

.candidate-content {

    display: flex;

    align-items: center;

    gap: 20px;
}


.candidate-photo {

    width: 95px;

    height: 95px;

    border-radius: 50%;

    object-fit: cover;

    border: 4px solid #e4efff;

    background: #eaf2ff;
}


.candidate-name {

    font-size: 20px;

    font-weight: bold;

    color: #145cae;
}


.candidate-course {

    margin-top: 6px;

    color: #687b92;
}


.proposal {

    margin-top: 15px;

    padding-top: 15px;

    border-top: 1px solid #e1e8f0;

    color: #52677f;
}


/* =====================================================
   BOTÓN
===================================================== */

.submit-container {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 8px 25px
        rgba(
            30,
            80,
            130,
            0.10
        );
}


.btn-votar {

    width: 100%;

    border: none;

    border-radius: 14px;

    padding: 18px;

    background:
        linear-gradient(
            135deg,
            #159447,
            #087c3c
        );

    color: white;

    font-size: 19px;

    font-weight: bold;

    cursor: pointer;

    box-shadow:
        0 8px 18px
        rgba(
            21,
            148,
            71,
            0.25
        );

    transition: 0.2s;
}


.btn-votar:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 25px
        rgba(
            21,
            148,
            71,
            0.30
        );
}


.warning {

    text-align: center;

    margin-top: 15px;

    color: #687b92;

    font-size: 14px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 700px) {

    .student-grid {

        grid-template-columns:
            1fr;
    }


    .header {

        flex-direction: column;

        gap: 10px;

        align-items: flex-start;
    }


    .candidate-content {

        flex-direction: column;

        text-align: center;
    }

}

</style>

</head>


<body>


<header class="header">

    <h1>
        ⚖️ Sistema de Votaciones Escolares
    </h1>


    <div class="jurado">

        👤 Jurado:

        <strong>

            <?php

            echo htmlspecialchars(
                $nombreJurado,
                ENT_QUOTES,
                'UTF-8'
            );

            ?>

        </strong>

    </div>

</header>


<main class="container">


<!-- =====================================================
     ESTUDIANTE
===================================================== -->

<section class="student">

    <h2>
        👤 Estudiante habilitado para votar
    </h2>


    <div class="student-grid">


        <div>

            <div class="label">
                Nombre
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    $estudiante['nombre'] .
                    " " .
                    $estudiante['apellido'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        </div>


        <div>

            <div class="label">
                Documento
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    $estudiante['documento'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        </div>


        <div>

            <div class="label">
                Curso
            </div>

            <div class="value">

                <?php

                echo htmlspecialchars(
                    $estudiante['curso'] ?? "",
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>

        </div>


    </div>

</section>


<!-- =====================================================
     ELECCIÓN
===================================================== -->

<section class="election">

    <h2>

        🗳️

        <?php

        echo htmlspecialchars(
            $eleccion['nombre'],
            ENT_QUOTES,
            'UTF-8'
        );

        ?>

    </h2>


    <p>
        Proceso democrático institucional
    </p>


    <span class="status">
        🟢 Elección abierta
    </span>

</section>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if ($error !== ""): ?>

    <div class="error">

        ⚠️

        <?php

        echo htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        );

        ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     FORMULARIO
===================================================== -->

<form
    method="POST"
    action=""
    id="formVotacion"
>


<?php foreach ($cargos as $cargo): ?>

    <?php

    $idCargo =
        (int)$cargo['id_cargo'];

    ?>


    <section class="cargo">


        <h2 class="cargo-title">

            🏅

            <?php

            echo htmlspecialchars(
                $cargo['nombre_cargo'],
                ENT_QUOTES,
                'UTF-8'
            );

            ?>

        </h2>


        <p>
            Seleccione un candidato para este cargo.
        </p>


        <?php

        /* =============================================
           CANDIDATOS DEL CARGO
        ============================================= */

        $stmt = $conn->prepare("
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


        $hayCandidatos = false;


        while (
            $candidato =
            $resultadoCandidatos->fetch_assoc()
        ):

            $hayCandidatos = true;


            /* =========================================
               FOTO
            ========================================= */

            $foto =
                trim(
                    (string)
                    ($candidato['foto'] ?? "")
                );


            if ($foto !== "") {

                if (
                    str_starts_with(
                        $foto,
                        "http://"
                    ) ||
                    str_starts_with(
                        $foto,
                        "https://"
                    ) ||
                    str_starts_with(
                        $foto,
                        "/"
                    )
                ) {

                    $rutaFoto =
                        $foto;

                } else {

                    $rutaFoto =
                        "uploads/candidatos/" .
                        $foto;
                }

            } else {

                $rutaFoto =
                    "assets/img/sin-foto.png";
            }


            ?>


            <!-- =====================================
                 TARJETA COMPLETA SELECCIONABLE
            ====================================== -->

            <label
                class="candidate"
                tabindex="0"
            >


                <input
                    type="radio"

                    name="votos[<?php
                        echo $idCargo;
                    ?>]"

                    value="<?php
                        echo (int)$candidato['id'];
                    ?>"
                >


                <div class="candidate-content">


                    <img
                        class="candidate-photo"

                        src="<?php

                            echo htmlspecialchars(
                                $rutaFoto,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                        ?>"

                        alt="Foto del candidato"

                        onerror="
                            this.onerror=null;
                            this.src='assets/img/sin-foto.png';
                        "
                    >


                    <div>


                        <div class="candidate-name">

                            <?php

                            echo htmlspecialchars(
                                $candidato['nombre'] .
                                " " .
                                $candidato['apellido'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </div>


                        <div class="candidate-course">

                            🎓

                            <?php

                            echo htmlspecialchars(
                                $candidato['curso'] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </div>


                    </div>


                </div>


                <?php

                $propuestas =
                    trim(
                        (string)
                        ($candidato['propuestas'] ?? "")
                    );


                if ($propuestas !== ""):

                ?>


                    <div class="proposal">

                        <strong>
                            Propuestas:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $propuestas,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>

                    </div>


                <?php endif; ?>


            </label>


        <?php endwhile; ?>


        <?php

        $stmt->close();

        ?>


        <?php if (!$hayCandidatos): ?>

            <div class="error">

                ⚠️ No hay candidatos registrados
                para este cargo en esta elección.

            </div>

        <?php endif; ?>


    </section>


<?php endforeach; ?>


<!-- =====================================================
     BOTÓN REGISTRAR
===================================================== -->

<div class="submit-container">


    <button
        type="submit"
        class="btn-votar"
    >

        ✓ Registrar votación

    </button>


    <div class="warning">

        ⚠️ Revise cuidadosamente sus selecciones
        antes de registrar la votación.

    </div>


</div>


</form>


</main>


<!-- =====================================================
     JAVASCRIPT PARA SELECCIÓN DE TARJETAS
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const tarjetas =
            document.querySelectorAll(
                ".candidate"
            );


        tarjetas.forEach(
            function (tarjeta) {


                const radio =
                    tarjeta.querySelector(
                        'input[type="radio"]'
                    );


                if (!radio) {
                    return;
                }


                /*
                 * Cuando cambia el radio,
                 * actualizamos visualmente
                 * la tarjeta.
                 */

                radio.addEventListener(
                    "change",
                    function () {


                        const cargo =
                            tarjeta.closest(
                                ".cargo"
                            );


                        if (cargo) {


                            const tarjetasCargo =
                                cargo.querySelectorAll(
                                    ".candidate"
                                );


                            tarjetasCargo.forEach(
                                function (otraTarjeta) {

                                    otraTarjeta.classList.remove(
                                        "selected"
                                    );

                                }
                            );

                        }


                        if (radio.checked) {

                            tarjeta.classList.add(
                                "selected"
                            );

                        }

                    }
                );


                /*
                 * Permitir teclado.
                 */

                tarjeta.addEventListener(
                    "keydown",
                    function (evento) {


                        if (
                            evento.key === "Enter" ||
                            evento.key === " "
                        ) {

                            evento.preventDefault();

                            radio.checked = true;

                            radio.dispatchEvent(
                                new Event(
                                    "change",
                                    {
                                        bubbles: true
                                    }
                                )
                            );

                        }

                    }
                );


            }
        );


        /*
         * Confirmación antes de registrar.
         */

        const formulario =
            document.getElementById(
                "formVotacion"
            );


        if (formulario) {


            formulario.addEventListener(
                "submit",
                function (evento) {


                    const cargos =
                        document.querySelectorAll(
                            ".cargo"
                        );


                    let todoSeleccionado =
                        true;


                    cargos.forEach(
                        function (cargo) {


                            const seleccionado =
                                cargo.querySelector(
                                    'input[type="radio"]:checked'
                                );


                            if (!seleccionado) {

                                todoSeleccionado =
                                    false;

                            }

                        }
                    );


                    if (!todoSeleccionado) {

                        evento.preventDefault();


                        alert(
                            "Debe seleccionar un candidato para cada cargo de esta elección."
                        );


                        return;

                    }


                    const confirmar =
                        confirm(
                            "¿Está seguro de que desea registrar esta votación?"
                        );


                    if (!confirmar) {

                        evento.preventDefault();

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>
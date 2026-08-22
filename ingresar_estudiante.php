<?php

session_start();

include("config/conexion.php");


/* =========================================================
   SOLO JURADO
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


if ($rol !== "jurado") {

    if ($rol === "administrador") {
        header("Location: admin.php");
        exit();
    }

    header("Location: login.php");
    exit();
}


/* =========================================================
   LIMPIAR ESTUDIANTE ANTERIOR
========================================================= */

unset($_SESSION['estudiante_votando_id']);
unset($_SESSION['estudiante_votando_documento']);
unset($_SESSION['estudiante_votando_nombre']);
unset($_SESSION['estudiante_votando_curso']);
unset($_SESSION['eleccion_votante_id']);


/* =========================================================
   VARIABLES
========================================================= */

$documento = "";

$mensaje = "";

$tipoMensaje = "";


/* =========================================================
   PROCESAR DOCUMENTO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $documento = trim(
        $_POST['documento'] ?? ""
    );


    /* =====================================================
       VALIDAR DOCUMENTO
    ===================================================== */

    if ($documento === "") {

        $mensaje =
            "Ingrese el documento del estudiante.";

        $tipoMensaje =
            "danger";

    }

    elseif (
        !preg_match(
            '/^[0-9]+$/',
            $documento
        )
    ) {

        $mensaje =
            "El documento debe contener únicamente números.";

        $tipoMensaje =
            "danger";

    }

    else {


        /* =================================================
           BUSCAR ESTUDIANTE
        ================================================= */

        $stmt = $conn->prepare("

            SELECT
                id,
                documento,
                nombre,
                apellido,
                curso

            FROM usuarios

            WHERE documento = ?

            AND LOWER(TRIM(rol)) = 'estudiante'

            LIMIT 1

        ");


        if (!$stmt) {

            $mensaje =
                "No se pudo realizar la búsqueda.";

            $tipoMensaje =
                "danger";

        }

        else {


            $stmt->bind_param(
                "s",
                $documento
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            /* =================================================
               ESTUDIANTE NO ENCONTRADO
            ================================================= */

            if (
                $resultado->num_rows === 0
            ) {

                $mensaje =
                    "No se encontró ningún estudiante con ese documento.";

                $tipoMensaje =
                    "danger";

            }

            else {


                $estudiante =
                    $resultado->fetch_assoc();


                $idEstudiante =
                    (int)$estudiante['id'];


                /* =============================================
                   BUSCAR ELECCIÓN ABIERTA
                ============================================= */

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

                        WHERE LOWER(TRIM(estado)) = 'abierta'

                        ORDER BY id DESC

                        LIMIT 1

                    ");


                if (!$stmtEleccion) {

                    $mensaje =
                        "No se pudo comprobar la elección.";

                    $tipoMensaje =
                        "danger";

                }

                else {


                    $stmtEleccion->execute();


                    $resultadoEleccion =
                        $stmtEleccion->get_result();


                    /* =========================================
                       NO HAY ELECCIÓN ABIERTA
                    ========================================= */

                    if (
                        $resultadoEleccion->num_rows === 0
                    ) {

                        $mensaje =
                            "No hay una elección abierta actualmente.";

                        $tipoMensaje =
                            "warning";

                    }

                    else {


                        $eleccion =
                            $resultadoEleccion->fetch_assoc();


                        $idEleccion =
                            (int)$eleccion['id'];


                        /* =====================================
                           COMPROBAR SI YA VOTÓ
                        ===================================== */

                        /*
                         * IMPORTANTE:
                         *
                         * votos no necesita tener id_eleccion.
                         *
                         * La elección se obtiene a través de
                         * candidatos.id_eleccion.
                         */

                        $stmtVoto =
                            $conn->prepare("

                                SELECT
                                    COUNT(*) AS total

                                FROM votos v

                                INNER JOIN candidatos c
                                    ON c.id = v.id_candidato

                                WHERE v.id_usuario = ?

                                AND c.id_eleccion = ?

                            ");


                        if (!$stmtVoto) {

                            $mensaje =
                                "No se pudo comprobar si el estudiante ya votó.";

                            $tipoMensaje =
                                "danger";

                        }

                        else {


                            $stmtVoto->bind_param(
                                "ii",
                                $idEstudiante,
                                $idEleccion
                            );


                            $stmtVoto->execute();


                            $resultadoVoto =
                                $stmtVoto->get_result();


                            $datosVoto =
                                $resultadoVoto->fetch_assoc();


                            $totalVotos =
                                (int)$datosVoto['total'];


                            $stmtVoto->close();


                            /* =================================
                               YA VOTÓ
                            ================================= */

                            if (
                                $totalVotos > 0
                            ) {

                                $mensaje =
                                    "Este estudiante ya realizó su votación en esta elección. No puede volver a votar.";

                                $tipoMensaje =
                                    "danger";

                            }

                            else {


                                /* =============================
                                   GUARDAR DATOS TEMPORALES
                                ============================= */

                                $_SESSION[
                                    'estudiante_votando_id'
                                ] =
                                    $idEstudiante;


                                $_SESSION[
                                    'estudiante_votando_documento'
                                ] =
                                    $estudiante['documento'];


                                $_SESSION[
                                    'estudiante_votando_nombre'
                                ] =
                                    $estudiante['nombre']
                                    . " "
                                    .
                                    $estudiante['apellido'];


                                $_SESSION[
                                    'estudiante_votando_curso'
                                ] =
                                    $estudiante['curso'];


                                $_SESSION[
                                    'eleccion_votante_id'
                                ] =
                                    $idEleccion;


                                /* =============================
                                   PASAR A VOTACIÓN
                                ============================= */

                                header(
                                    "Location: votar_por_jurado.php"
                                );

                                exit();

                            }

                        }

                    }


                    $stmtEleccion->close();

                }

            }


            $stmt->close();

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

Ingresar estudiante

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

    min-height:100vh;

    background:
        linear-gradient(
            135deg,
            #eef3f9,
            #dce9f8
        );

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:20px;

}


.card-documento {

    width:100%;

    max-width:520px;

    background:white;

    border-radius:22px;

    padding:40px;

    box-shadow:
        0 10px 35px
        rgba(0,0,0,.15);

}


.icono {

    width:90px;

    height:90px;

    margin:0 auto 20px;

    border-radius:50%;

    background:#e7f0ff;

    color:#0d6efd;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:45px;

}


h1 {

    text-align:center;

    color:#1453a3;

    font-weight:bold;

}


.descripcion {

    text-align:center;

    color:#6c757d;

    margin-bottom:30px;

}


.form-label {

    font-weight:bold;

    color:#333;

}


.form-control {

    height:55px;

    border-radius:10px;

    font-size:18px;

    text-align:center;

}


.btn-buscar {

    width:100%;

    height:55px;

    background:#0d6efd;

    color:white;

    border:none;

    border-radius:10px;

    font-size:18px;

    font-weight:bold;

}


.btn-buscar:hover {

    background:#084298;

}


.btn-volver {

    width:100%;

    height:50px;

    border-radius:10px;

}


.jurado {

    text-align:center;

    margin-top:25px;

    color:#6c757d;

    font-size:14px;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card-documento">


<div class="icono">

<i class="bi bi-person-vcard-fill"></i>

</div>


<h1>

Ingresar estudiante

</h1>


<p class="descripcion">

Ingrese el documento del estudiante
para habilitar su votación.

</p>


<?php if (
    $mensaje !== ""
) { ?>

<div class="alert alert-<?php echo htmlspecialchars(
    $tipoMensaje
); ?>">

<i class="bi bi-exclamation-circle-fill"></i>

<?php echo htmlspecialchars(
    $mensaje
); ?>

</div>

<?php } ?>


<form
method="POST"
autocomplete="off">


<div class="mb-4">


<label
for="documento"
class="form-label">

Número de documento

</label>


<input

type="text"

id="documento"

name="documento"

class="form-control"

value="<?php echo htmlspecialchars(
    $documento
); ?>"

placeholder="Ingrese el documento"

inputmode="numeric"

pattern="[0-9]+"

autocomplete="off"

required>


</div>


<button
type="submit"
class="btn-buscar">

<i class="bi bi-search"></i>

Buscar estudiante

</button>


</form>


<div class="mt-3">


<a
href="jurado.php"
class="btn btn-outline-secondary btn-volver">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


</div>


<div class="jurado">

<i class="bi bi-person-badge-fill"></i>

Sesión del jurado:

<strong>

<?php echo htmlspecialchars(
    $_SESSION['nombre'] ?? 'Jurado'
); ?>

</strong>

</div>


</div>


</div>


</body>

</html>
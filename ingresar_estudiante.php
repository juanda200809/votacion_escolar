<?php

require_once "seguridad.php";

evitarCache();

verificarRol(['jurado']);

// AQUÍ CONTINÚA TODO TU CÓDIGO ACTUAL



include("config/conexion.php");


/* =========================================================
   VERIFICAR QUE SEA JURADO
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim((string)$_SESSION['rol'])) !== 'jurado'
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   BUSCAR ELECCIÓN ABIERTA
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


/* =========================================================
   VERIFICAR ELECCIÓN
========================================================= */

$sinEleccion = ($eleccion === null);

$idEleccion = 0;


if (!$sinEleccion) {

    $idEleccion = (int)$eleccion['id'];

}


/* =========================================================
   MENSAJES
========================================================= */

$mensaje = "";

$tipoMensaje = "danger";


/* =========================================================
   SELECCIONAR ESTUDIANTE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['seleccionar_estudiante'])
) {


    /* =====================================================
       VERIFICAR NUEVAMENTE LA ELECCIÓN
       Esto evita confiar solamente en la información
       cargada anteriormente.
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            id,
            estado

        FROM elecciones

        WHERE id = ?

        LIMIT 1

    ");


    if (!$stmt) {

        $mensaje =
            "No se pudo verificar la elección.";

    } else {

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

            $mensaje =
                "La elección no existe.";

        } else {

            $eleccionActual =
                $resultado->fetch_assoc();


            if (
                strtolower(
                    trim(
                        (string)$eleccionActual['estado']
                    )
                ) !== 'abierta'
            ) {

                $mensaje =
                    "La elección está cerrada.";

            } else {


                /* =========================================
                   OBTENER ID DEL ESTUDIANTE
                ========================================= */

                $idEstudiante =
                    isset($_POST['id_estudiante'])
                    ? (int)$_POST['id_estudiante']
                    : 0;


                if (
                    $idEstudiante <= 0
                ) {

                    $mensaje =
                        "El estudiante seleccionado no es válido.";

                } else {


                    /* =====================================
                       BUSCAR ESTUDIANTE
                    ===================================== */

                    $stmtEstudiante =
                        $conn->prepare("

                            SELECT
                                id,
                                documento,
                                nombre,
                                apellido,
                                curso

                            FROM usuarios

                            WHERE id = ?

                            AND LOWER(
                                TRIM(rol)
                            ) = 'estudiante'

                            LIMIT 1

                        ");


                    if (!$stmtEstudiante) {

                        $mensaje =
                            "Error al consultar el estudiante.";

                    } else {

                        $stmtEstudiante->bind_param(
                            "i",
                            $idEstudiante
                        );

                        $stmtEstudiante->execute();

                        $resultadoEstudiante =
                            $stmtEstudiante->get_result();


                        if (
                            $resultadoEstudiante->num_rows === 0
                        ) {

                            $mensaje =
                                "El estudiante no existe o no pertenece al rol de estudiante.";

                        } else {

                            $estudiante =
                                $resultadoEstudiante->fetch_assoc();


                            /* =================================
                               COMPROBAR SI YA VOTÓ
                            ================================= */

                            $stmtVoto =
                                $conn->prepare("

                                    SELECT
                                        id

                                    FROM votos

                                    WHERE id_usuario = ?

                                    AND id_eleccion = ?

                                    LIMIT 1

                                ");


                            if (!$stmtVoto) {

                                $mensaje =
                                    "No se pudo comprobar si el estudiante ya votó.";

                            } else {

                                $stmtVoto->bind_param(
                                    "ii",
                                    $idEstudiante,
                                    $idEleccion
                                );

                                $stmtVoto->execute();

                                $resultadoVoto =
                                    $stmtVoto->get_result();


                                if (
                                    $resultadoVoto->num_rows > 0
                                ) {

                                    /*
                                     * EL ESTUDIANTE YA VOTÓ.
                                     */

                                    $mensaje =
                                        "Este estudiante ya realizó su votación en esta elección.";

                                    $tipoMensaje =
                                        "danger";


                                    /*
                                     * Limpiar cualquier selección
                                     * anterior para evitar reutilizar
                                     * una sesión vieja.
                                     */

                                    unset(
                                        $_SESSION['estudiante_votando_id'],
                                        $_SESSION['estudiante_votando_documento'],
                                        $_SESSION['estudiante_votando_nombre'],
                                        $_SESSION['estudiante_votando_curso'],
                                        $_SESSION['eleccion_votante_id']
                                    );


                                } else {


                                    /* =========================
                                       LIMPIAR SELECCIÓN ANTERIOR
                                    ========================= */

                                    unset(
                                        $_SESSION['estudiante_votando_id'],
                                        $_SESSION['estudiante_votando_documento'],
                                        $_SESSION['estudiante_votando_nombre'],
                                        $_SESSION['estudiante_votando_curso'],
                                        $_SESSION['eleccion_votante_id']
                                    );


                                    /* =========================
                                       GUARDAR ESTUDIANTE
                                    ========================= */

                                    $_SESSION[
                                        'estudiante_votando_id'
                                    ] =
                                        (int)$estudiante['id'];


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


                                    /*
                                     * Redirigir al proceso de votación.
                                     */

                                    header(
                                        "Location: votar_por_jurado.php"
                                    );

                                    exit();

                                }


                                $stmtVoto->close();

                            }

                        }


                        $stmtEstudiante->close();

                    }

                }

            }

        }


        $stmt->close();

    }

}


/* =========================================================
   BÚSQUEDA
========================================================= */

$busqueda = "";

$estudiantes = [];


if (
    isset($_GET['buscar'])
) {

    $busqueda =
        trim(
            (string)$_GET['buscar']
        );

}


/* =========================================================
   REALIZAR BÚSQUEDA
========================================================= */

if (
    !$sinEleccion &&
    $busqueda !== ""
) {


    $textoBusqueda =
        "%" . $busqueda . "%";


    $stmt = $conn->prepare("

        SELECT

            u.id,
            u.documento,
            u.nombre,
            u.apellido,
            u.curso,

            CASE

                WHEN EXISTS (

                    SELECT 1

                    FROM votos v

                    WHERE v.id_usuario = u.id

                    AND v.id_eleccion = ?

                )

                THEN 1

                ELSE 0

            END AS ya_voto

        FROM usuarios u

        WHERE LOWER(
            TRIM(u.rol)
        ) = 'estudiante'

        AND (

            u.documento LIKE ?

            OR u.nombre LIKE ?

            OR u.apellido LIKE ?

            OR u.curso LIKE ?

            OR CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) LIKE ?

        )

        ORDER BY
            u.nombre ASC,
            u.apellido ASC

        LIMIT 100

    ");


    if ($stmt) {

        $stmt->bind_param(
            "isssss",
            $idEleccion,
            $textoBusqueda,
            $textoBusqueda,
            $textoBusqueda,
            $textoBusqueda,
            $textoBusqueda
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $estudiantes[] =
                $fila;

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
Ingresar estudiante
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


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

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.15);

}


.header h3 {

    margin: 0;

    font-size: 23px;

    font-weight: 600;

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
   TARJETA PRINCIPAL
========================================================= */

.card-principal {

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.08);

    border:
        1px solid
        #e2e8f0;

}


/* =========================================================
   TÍTULO
========================================================= */

.titulo {

    color: #1459a6;

    font-weight: 700;

    margin-bottom: 5px;

}


.subtitulo {

    color: #64748b;

    margin-bottom: 25px;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: #dbeafe;

    border:
        1px solid
        #bfdbfe;

    border-radius: 14px;

    padding: 18px;

    margin-bottom: 25px;

    color: #084298;

}


.eleccion h4 {

    margin-bottom: 5px;

    font-weight: 700;

}


/* =========================================================
   BUSCADOR
========================================================= */

.buscador {

    display: flex;

    gap: 10px;

    margin-bottom: 25px;

}


.buscador input {

    flex: 1;

    border:
        1px solid
        #cbd5e1;

    border-radius: 10px;

    padding: 13px 15px;

    font-size: 16px;

}


.buscador input:focus {

    outline: none;

    border-color: #1976e8;

    box-shadow:
        0 0 0 3px
        rgba(25,118,232,.12);

}


.btn-buscar {

    border: none;

    background: #1976e8;

    color: white;

    padding:
        0 24px;

    border-radius: 10px;

    font-weight: 600;

}


.btn-buscar:hover {

    background: #125fc0;

}


/* =========================================================
   RESULTADOS
========================================================= */

.resultados {

    display: flex;

    flex-direction: column;

    gap: 12px;

}


.estudiante {

    background: white;

    border:
        1px solid
        #e2e8f0;

    border-radius: 14px;

    padding: 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    transition: .2s;

}


.estudiante:hover {

    border-color: #bfdbfe;

    box-shadow:
        0 4px 12px
        rgba(0,0,0,.06);

}


.info-estudiante {

    display: flex;

    align-items: center;

    gap: 16px;

}


.icono-estudiante {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    background: #e8f1ff;

    color: #1976e8;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;

}


.nombre-estudiante {

    font-size: 18px;

    font-weight: 700;

    color: #1459a6;

}


.detalle {

    color: #64748b;

    font-size: 14px;

    margin-top: 3px;

}


/* =========================================================
   ESTADOS
========================================================= */

.estado {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    font-size: 14px;

    font-weight: 600;

    margin-bottom: 8px;

}


.estado-ok {

    color: #198754;

}


.estado-no {

    color: #dc3545;

}


/* =========================================================
   BOTONES
========================================================= */

.btn-seleccionar {

    border: none;

    background: #198754;

    color: white;

    padding:
        9px 15px;

    border-radius: 8px;

    font-weight: 600;

}


.btn-seleccionar:hover {

    background: #157347;

}


.btn-bloqueado {

    border: none;

    background: #e2e8f0;

    color: #64748b;

    padding:
        9px 15px;

    border-radius: 8px;

    font-weight: 600;

    cursor: not-allowed;

}


/* =========================================================
   SIN RESULTADOS
========================================================= */

.sin-resultados {

    text-align: center;

    padding: 40px 20px;

    color: #64748b;

}


.sin-resultados i {

    font-size: 45px;

    color: #94a3b8;

    display: block;

    margin-bottom: 10px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 700px
) {

    .header {

        padding:
            15px;

    }


    .header h3 {

        font-size: 18px;

    }


    .contenedor {

        padding:
            20px 12px;

    }


    .card-principal {

        padding:
            20px 15px;

    }


    .buscador {

        flex-direction: column;

    }


    .btn-buscar {

        padding: 12px;

    }


    .estudiante {

        flex-direction: column;

        align-items: stretch;

    }


    .info-estudiante {

        align-items: flex-start;

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
========================================================= -->

<div class="contenedor">


<div class="card-principal">


<!-- =====================================================
     TÍTULO
========================================================= -->

<h2 class="titulo">

<i class="bi bi-person-search"></i>

Buscar estudiante

</h2>


<p class="subtitulo">

Busque al estudiante que realizará la votación.

</p>


<!-- =====================================================
     ELECCIÓN
========================================================= -->

<?php if ($eleccion) { ?>


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
    !empty(
        $eleccion['descripcion']
    )
) { ?>

<div>

<?php

echo htmlspecialchars(
    $eleccion['descripcion']
);

?>

</div>

<?php } ?>


</div>


<?php } else { ?>


<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill"></i>

No hay ninguna elección abierta actualmente.

</div>


<a
href="jurado.php"
class="btn btn-primary">

<i class="bi bi-arrow-left"></i>

Volver al panel

</a>


<?php } ?>


<?php if ($eleccion) { ?>


<!-- =====================================================
     MENSAJE
========================================================= -->

<?php if (
    $mensaje !== ""
) { ?>


<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje
);

?>">

<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>

</div>


<?php } ?>


<!-- =====================================================
     BUSCADOR
========================================================= -->

<form
method="GET"
class="buscador">


<input

type="text"

name="buscar"

value="<?php

echo htmlspecialchars(
    $busqueda
);

?>"

placeholder="Buscar por documento, nombre, apellido o curso..."

autocomplete="off">


<button
type="submit"
class="btn-buscar">

<i class="bi bi-search"></i>

Buscar

</button>


</form>


<!-- =====================================================
     RESULTADOS
========================================================= -->

<?php if (
    $busqueda !== ""
) { ?>


<?php if (
    count($estudiantes) > 0
) { ?>


<div class="resultados">


<?php foreach (
    $estudiantes
    as $estudiante
) { ?>


<div class="estudiante">


<div class="info-estudiante">


<div class="icono-estudiante">

<i class="bi bi-person-fill"></i>

</div>


<div>


<div class="nombre-estudiante">

<?php

echo htmlspecialchars(
    $estudiante['nombre']
    . " "
    .
    $estudiante['apellido']
);

?>

</div>


<div class="detalle">

<i class="bi bi-card-text"></i>

Documento:

<?php

echo htmlspecialchars(
    $estudiante['documento']
);

?>


&nbsp;&nbsp;


<i class="bi bi-mortarboard-fill"></i>

Curso:

<?php

echo htmlspecialchars(
    $estudiante['curso']
);

?>

</div>


</div>


</div>


<div class="text-md-end">


<?php

if (
    (int)$estudiante['ya_voto'] === 1
) {

?>


<div class="estado estado-no">

<i class="bi bi-x-circle-fill"></i>

Ya votó

</div>


<br>


<button
type="button"
class="btn-bloqueado"
disabled>

<i class="bi bi-lock-fill"></i>

Bloqueado

</button>


<?php

} else {

?>


<div class="estado estado-ok">

<i class="bi bi-check-circle-fill"></i>

Puede votar

</div>


<form
method="POST"
style="margin:0;">

<input

type="hidden"

name="id_estudiante"

value="<?php

echo (int)$estudiante['id'];

?>">


<button

type="submit"

name="seleccionar_estudiante"

class="btn-seleccionar">

<i class="bi bi-arrow-right-circle-fill"></i>

Seleccionar

</button>


</form>


<?php

}

?>


</div>


</div>


<?php } ?>


</div>


<?php } else { ?>


<div class="sin-resultados">


<i class="bi bi-person-x"></i>


<h5>

No se encontraron estudiantes

</h5>


<p>

Intente buscar con otro documento,
nombre, apellido o curso.

</p>


</div>


<?php } ?>


<?php } else { ?>


<div class="sin-resultados">


<i class="bi bi-search"></i>


<h5>

Buscar estudiante

</h5>


<p>

Ingrese un documento, nombre, apellido
o curso para comenzar.

</p>


</div>


<?php } ?>


<br>


<a
href="jurado.php"
class="btn btn-outline-secondary">

<i class="bi bi-arrow-left"></i>

Volver al panel del jurado

</a>


<?php } ?>


</div>


</div>


</body>

</html>
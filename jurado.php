<?php

require_once "seguridad.php";

evitarCache();

verificarRol(['jurado']);



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
   DATOS DEL JURADO
========================================================= */

$nombreJurado =
    $_SESSION['nombre'] ?? "Jurado";


/* =========================================================
   ELECCIÓN ACTUAL
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

    ORDER BY id DESC

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
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;

$totalCandidatos = 0;

$totalVotos = 0;

$totalEstudiantesVotaron = 0;

$totalEstudiantesPendientes = 0;


/* =========================================================
   TOTAL DE ESTUDIANTES
========================================================= */

$resultado = $conn->query("

    SELECT
        COUNT(*) AS total

    FROM usuarios

    WHERE LOWER(
        TRIM(rol)
    ) = 'estudiante'

");


if ($resultado) {

    $fila =
        $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];

}


/* =========================================================
   ESTADÍSTICAS DE LA ELECCIÓN
========================================================= */

if ($eleccion) {

    $idEleccion =
        (int)$eleccion['id'];


    /* =====================================================
       TOTAL DE CANDIDATOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM candidatos

        WHERE id_eleccion = ?

    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $fila =
            $resultado->fetch_assoc();

        $totalCandidatos =
            (int)$fila['total'];

        $stmt->close();

    }


    /* =====================================================
       TOTAL DE VOTOS
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos

        WHERE id_eleccion = ?

    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $fila =
            $resultado->fetch_assoc();

        $totalVotos =
            (int)$fila['total'];

        $stmt->close();

    }


    /* =====================================================
       ESTUDIANTES QUE YA VOTARON
    ===================================================== */

    $stmt = $conn->prepare("

        SELECT
            COUNT(DISTINCT id_usuario) AS total

        FROM votos

        WHERE id_eleccion = ?

    ");


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $idEleccion
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $fila =
            $resultado->fetch_assoc();

        $totalEstudiantesVotaron =
            (int)$fila['total'];

        $stmt->close();

    }


    /* =====================================================
       ESTUDIANTES PENDIENTES
    ===================================================== */

    $totalEstudiantesPendientes =
        $totalEstudiantes -
        $totalEstudiantesVotaron;


    if (
        $totalEstudiantesPendientes < 0
    ) {

        $totalEstudiantesPendientes = 0;

    }

}


/* =========================================================
   ESTADO DE LA ELECCIÓN
========================================================= */

$eleccionAbierta = false;


if ($eleccion) {

    $estado =
        strtolower(
            trim(
                (string)$eleccion['estado']
            )
        );


    if (
        $estado === "abierta"
    ) {

        $eleccionAbierta = true;

    }

}


/* =========================================================
   MENSAJE DEL JURADO
========================================================= */

$mensajeJurado =
    $_SESSION['mensaje_jurado'] ?? "";


$tipoMensajeJurado =
    $_SESSION['tipo_mensaje_jurado'] ?? "success";


unset(
    $_SESSION['mensaje_jurado'],
    $_SESSION['tipo_mensaje_jurado']
);

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Panel del Jurado

</title>


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- =====================================================
     BOOTSTRAP ICONS
===================================================== -->

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
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #1453a3;

    color: white;

    z-index: 1000;

}


.logo {

    text-align: center;

    padding: 28px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);

}


.logo-icon {

    font-size: 55px;

}


.logo h2 {

    margin: 8px 0 0;

    font-weight: bold;

}


.logo p {

    margin: 5px 0 0;

    opacity: .75;

    font-size: 13px;

}


.menu {

    padding: 18px 15px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    color: white;

    text-decoration: none;

    padding: 14px;

    border-radius: 9px;

    margin-bottom: 8px;

    font-size: 16px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.12);

}


.menu a.activo {

    background:
        rgba(255,255,255,.18);

}


.menu i {

    font-size: 20px;

    width: 25px;

}


.separador {

    height: 1px;

    background:
        rgba(255,255,255,.22);

    margin: 15px 5px;

}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    margin-left: 250px;

    min-height: 100vh;

}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 70px;

    background: #1473ed;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.15);

}


.header h3 {

    margin: 0;

    font-size: 24px;

}


.usuario {

    font-size: 15px;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.contenedor {

    padding: 35px;

    max-width: 1350px;

    margin: auto;

}


/* =========================================================
   MENSAJES
========================================================= */

.mensaje-jurado {

    border-radius: 12px;

    padding: 15px 18px;

    margin-bottom: 25px;

    font-weight: 500;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida h1 {

    color: #1559a6;

    font-weight: bold;

}


.bienvenida p {

    color: #6c757d;

    font-size: 17px;

}


/* =========================================================
   BUSCADOR DE ESTUDIANTES
========================================================= */

.ingresar-estudiante {

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #084298
        );

    color: white;

    border-radius: 20px;

    padding: 30px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.15);

    margin-bottom: 30px;

}


.ingresar-estudiante h2 {

    font-weight: bold;

}


.ingresar-estudiante > p {

    margin-bottom: 22px;

}


.buscador-jurado {

    margin-top: 20px;

}


.campo-busqueda {

    display: flex;

    align-items: center;

    background: white;

    border-radius: 12px;

    padding: 5px;

    max-width: 850px;

    box-shadow:
        0 4px 12px
        rgba(0,0,0,.12);

}


.campo-busqueda > i {

    color: #6c757d;

    font-size: 20px;

    margin-left: 15px;

}


.campo-busqueda input {

    flex: 1;

    border: none;

    outline: none;

    padding: 14px;

    font-size: 15px;

    min-width: 0;

}


.btn-buscar {

    border: none;

    background: #1453a3;

    color: white;

    padding: 12px 24px;

    border-radius: 9px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s;

}


.btn-buscar:hover {

    background: #0d4285;

}


.resultados-estudiantes {

    max-width: 850px;

    margin-top: 15px;

}


.mensaje-busqueda {

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.25);

    border-radius: 12px;

    padding: 18px;

    display: flex;

    align-items: center;

    gap: 12px;

}


.mensaje-busqueda i {

    font-size: 22px;

}


.lista-estudiantes {

    display: flex;

    flex-direction: column;

    gap: 12px;

}


.resultado-estudiante {

    background: white;

    color: #212529;

    border-radius: 14px;

    padding: 18px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.10);

}


.informacion-estudiante {

    display: flex;

    align-items: center;

    gap: 15px;

}


.avatar-estudiante {

    width: 50px;

    height: 50px;

    border-radius: 50%;

    background: #e8f1ff;

    color: #1453a3;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 24px;

    flex-shrink: 0;

}


.informacion-estudiante h5 {

    margin: 0 0 8px;

    color: #1559a6;

    font-weight: 700;

}


.datos-estudiante {

    display: flex;

    flex-wrap: wrap;

    gap: 15px;

    color: #6c757d;

    font-size: 13px;

}


.datos-estudiante i {

    color: #1453a3;

}


.estado-estudiante {

    display: flex;

    align-items: center;

    gap: 15px;

    flex-shrink: 0;

}


.estado-disponible,
.estado-bloqueado {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 9px 13px;

    border-radius: 10px;

    font-size: 13px;

}


.estado-disponible {

    background: #d1fae5;

    color: #047857;

}


.estado-bloqueado {

    background: #f1f5f9;

    color: #64748b;

}


.estado-disponible > i,
.estado-bloqueado > i {

    font-size: 19px;

}


.estado-estudiante strong {

    display: block;

}


.estado-estudiante small {

    display: block;

    margin-top: 2px;

    opacity: .85;

}


.btn-iniciar-votacion {

    background: #1453a3;

    color: white;

    text-decoration: none;

    padding: 11px 16px;

    border-radius: 9px;

    font-weight: 600;

    white-space: nowrap;

    transition: .2s;

}


.btn-iniciar-votacion:hover {

    background: #0d4285;

    color: white;

}


.resultado-vacio {

    background: white;

    color: #495057;

    border-radius: 14px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

}


.resultado-icono {

    width: 48px;

    height: 48px;

    border-radius: 50%;

    background: #f1f5f9;

    color: #64748b;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

}


.resultado-vacio h5 {

    margin: 0 0 5px;

}


.resultado-vacio p {

    margin: 0;

    color: #6c757d;

}


/* =========================================================
   TARJETAS
========================================================= */

.tarjetas {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 18px;

    margin-bottom: 30px;

}


.tarjeta {

    background: white;

    border-radius: 18px;

    padding: 25px 15px;

    text-align: center;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    transition: .2s;

}


.tarjeta:hover {

    transform:
        translateY(-3px);

}


.tarjeta-icono {

    font-size: 40px;

    color: #1473ed;

    margin-bottom: 8px;

}


.tarjeta h3 {

    color: #1559a6;

    font-weight: bold;

    font-size: 32px;

    margin: 5px 0;

}


.tarjeta p {

    color: #6c757d;

    margin: 0;

    font-size: 14px;

}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    margin-bottom: 30px;

}


.eleccion h3 {

    color: #1559a6;

    font-weight: bold;

}


.estado-abierta {

    background: #198754;

    color: white;

    padding: 8px 16px;

    border-radius: 7px;

    font-weight: bold;

}


.estado-cerrada {

    background: #dc3545;

    color: white;

    padding: 8px 16px;

    border-radius: 7px;

    font-weight: bold;

}


/* =========================================================
   RESUMEN DE ESTADO
========================================================= */

.estado-resumen {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 20px;

}


.estado-pill {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 14px;

    border-radius: 20px;

    font-size: 14px;

    font-weight: 600;

}


.estado-pill.verde {

    background: #d1fae5;

    color: #047857;

}


.estado-pill.amarillo {

    background: #fef3c7;

    color: #92400e;

}


/* =========================================================
   ACCIONES
========================================================= */

.acciones {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

}


.accion {

    background: white;

    border-radius: 18px;

    padding: 28px;

    text-decoration: none;

    color: #212529;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.10);

    transition: .2s;

}


.accion:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 10px 25px
        rgba(0,0,0,.15);

    color: #212529;

}


.accion i {

    font-size: 42px;

    color: #1473ed;

}


.accion h4 {

    color: #1559a6;

    font-weight: bold;

    margin-top: 12px;

}


.accion p {

    color: #6c757d;

    margin-bottom: 0;

}


/* =========================================================
   ELECCIÓN CERRADA
========================================================= */

.eleccion-cerrada {

    background: #fff3cd;

    border:
        1px solid #ffecb5;

    color: #664d03;

    border-radius: 12px;

    padding: 18px;

    margin-top: 20px;

}


/* =========================================================
   PIE DE PÁGINA
========================================================= */

.footer {

    text-align: center;

    color: #7a8795;

    font-size: 13px;

    padding: 30px 15px 20px;

    margin-top: 35px;

}


.footer strong {

    color: #5d6b7a;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .tarjetas {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media(max-width:900px) {

    .sidebar {

        width: 210px;

    }


    .contenido {

        margin-left: 210px;

    }


    .tarjetas {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .resultado-estudiante {

        flex-direction: column;

        align-items: stretch;

    }


    .estado-estudiante {

        flex-direction: column;

        align-items: stretch;

    }


    .btn-iniciar-votacion {

        text-align: center;

    }

}


@media(max-width:650px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }


    .contenido {

        margin-left: 0;

    }


    .tarjetas {

        grid-template-columns:
            1fr;

    }


    .acciones {

        grid-template-columns:
            1fr;

    }


    .header {

        padding: 0 15px;

    }


    .header h3 {

        font-size: 18px;

    }


    .usuario {

        display: none;

    }


    .contenedor {

        padding: 20px;

    }


    .campo-busqueda {

        flex-wrap: wrap;

        padding: 8px;

    }


    .campo-busqueda > i {

        margin-left: 10px;

    }


    .campo-busqueda input {

        width: calc(100% - 45px);

        flex: none;

    }


    .btn-buscar {

        width: 100%;

        margin-top: 5px;

    }


    .datos-estudiante {

        flex-direction: column;

        gap: 5px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">


    <div class="logo">

        <div class="logo-icon">

            ⚖️

        </div>


        <h2>

            JURADO

        </h2>


        <p>

            Panel de votaciones

        </p>

    </div>


    <div class="menu">


        <a
        href="jurado.php"
        class="activo">

            <i class="bi bi-house-fill"></i>

            <span>

                Inicio

            </span>

        </a>


        <a
        href="ingresar_estudiante.php">

            <i class="bi bi-person-vcard-fill"></i>

            <span>

                Ingresar estudiante

            </span>

        </a>


        <a
        href="resultados.php">

            <i class="bi bi-trophy-fill"></i>

            <span>

                Resultados

            </span>

        </a>


        <a
        href="graficas.php">

            <i class="bi bi-bar-chart-fill"></i>

            <span>

                Gráficas

            </span>

        </a>


        <div class="separador"></div>


        <a
        href="logout.php">

            <i class="bi bi-box-arrow-right"></i>

            <span>

                Cerrar sesión

            </span>

        </a>


    </div>

</div>


<!-- =====================================================
     CONTENIDO
========================================================= -->

<div class="contenido">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="header">


        <h3>

            ⚖️ Sistema de Votaciones Escolares

        </h3>


        <div class="usuario">

            <i class="bi bi-person-badge-fill"></i>

            Jurado:

            <strong>

                <?php

                echo htmlspecialchars(
                    $nombreJurado
                );

                ?>

            </strong>

        </div>


    </div>


    <!-- =================================================
         CONTENEDOR
    ================================================== -->

    <div class="contenedor">


        <!-- =============================================
             MENSAJE
        ============================================== -->

        <?php if (
            $mensajeJurado !== ""
        ) { ?>


            <div class="alert alert-<?php

                echo htmlspecialchars(
                    $tipoMensajeJurado
                );

            ?> mensaje-jurado">


                <?php if (
                    $tipoMensajeJurado === "success"
                ) { ?>

                    <i class="bi bi-check-circle-fill"></i>

                <?php } else { ?>

                    <i class="bi bi-info-circle-fill"></i>

                <?php } ?>


                <?php

                echo htmlspecialchars(
                    $mensajeJurado
                );

                ?>


            </div>


        <?php } ?>


        <!-- =============================================
             BIENVENIDA
        ============================================== -->

        <div class="bienvenida mb-4">


            <h1>

                Bienvenido,

                <?php

                echo htmlspecialchars(
                    $nombreJurado
                );

                ?>

                👋

            </h1>


            <p>

                Desde este panel puede habilitar la votación
                de los estudiantes y consultar los resultados.

            </p>


        </div>


        <!-- =============================================
             BUSCADOR DE ESTUDIANTES
        ============================================== -->

        <div class="ingresar-estudiante">


            <h2>

                <i class="bi bi-search"></i>

                Buscar estudiante

            </h2>


            <p>

                Busque al estudiante por su nombre o número
                de documento para iniciar su proceso de votación.

            </p>


            <?php if (
                $eleccionAbierta
            ) { ?>


                <div class="buscador-jurado">


                    <div class="campo-busqueda">


                        <i class="bi bi-search"></i>


                        <input

                            type="text"

                            id="buscarEstudiante"

                            placeholder="Nombre o número de documento..."

                            autocomplete="off"

                        >


                        <button

                            type="button"

                            id="btnBuscarEstudiante"

                            class="btn-buscar"

                        >

                            Buscar

                        </button>


                    </div>


                    <div

                        id="resultadosEstudiantes"

                        class="resultados-estudiantes"

                    >


                        <div class="mensaje-busqueda">


                            <i class="bi bi-person-search"></i>


                            <span>

                                Escriba un nombre o documento
                                para buscar un estudiante.

                            </span>


                        </div>


                    </div>


                </div>


            <?php } else { ?>


                <button

                    type="button"

                    class="btn btn-secondary"

                    disabled

                >

                    <i class="bi bi-lock-fill"></i>

                    Elección cerrada

                </button>


            <?php } ?>


        </div>


        <!-- =============================================
             TARJETAS DE ESTADÍSTICAS
        ============================================== -->

        <div class="tarjetas">


            <!-- ESTUDIANTES -->

            <div class="tarjeta">


                <div class="tarjeta-icono">

                    <i class="bi bi-people-fill"></i>

                </div>


                <h3>

                    <?php

                    echo $totalEstudiantes;

                    ?>

                </h3>


                <p>

                    Estudiantes registrados

                </p>


            </div>


            <!-- YA VOTARON -->

            <div class="tarjeta">


                <div class="tarjeta-icono">

                    <i class="bi bi-person-check-fill"></i>

                </div>


                <h3>

                    <?php

                    echo $totalEstudiantesVotaron;

                    ?>

                </h3>


                <p>

                    Estudiantes que votaron

                </p>


            </div>


            <!-- PENDIENTES -->

            <div class="tarjeta">


                <div class="tarjeta-icono">

                    <i class="bi bi-person-exclamation"></i>

                </div>


                <h3>

                    <?php

                    echo $totalEstudiantesPendientes;

                    ?>

                </h3>


                <p>

                    Estudiantes pendientes

                </p>


            </div>


            <!-- CANDIDATOS -->

            <div class="tarjeta">


                <div class="tarjeta-icono">

                    <i class="bi bi-person-vcard-fill"></i>

                </div>


                <h3>

                    <?php

                    echo $totalCandidatos;

                    ?>

                </h3>


                <p>

                    Candidatos de la elección

                </p>


            </div>


            <!-- VOTOS -->

            <div class="tarjeta">


                <div class="tarjeta-icono">

                    <i class="bi bi-check2-square"></i>

                </div>


                <h3>

                    <?php

                    echo $totalVotos;

                    ?>

                </h3>


                <p>

                    Votos registrados

                </p>


            </div>


        </div>


        <!-- =============================================
             ELECCIÓN ACTUAL
        ============================================== -->

        <div class="eleccion">


            <h3>

                <i class="bi bi-calendar-event-fill"></i>

                Elección actual

            </h3>


            <hr>


            <?php if (
                $eleccion
            ) { ?>


                <h4>

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


                    <p class="text-muted">

                        <?php

                        echo htmlspecialchars(
                            $eleccion['descripcion']
                        );

                        ?>

                    </p>


                <?php } ?>


                <div class="row mt-4">


                    <div class="col-md-4 mb-3">


                        <strong>

                            Fecha de inicio

                        </strong>


                        <br>


                        <span class="text-muted">

                            <?php

                            echo htmlspecialchars(
                                $eleccion['fecha_inicio']
                            );

                            ?>

                        </span>


                    </div>


                    <div class="col-md-4 mb-3">


                        <strong>

                            Fecha de finalización

                        </strong>


                        <br>


                        <span class="text-muted">

                            <?php

                            echo htmlspecialchars(
                                $eleccion['fecha_fin']
                            );

                            ?>

                        </span>


                    </div>


                    <div class="col-md-4 mb-3">


                        <strong>

                            Estado

                        </strong>


                        <br><br>


                        <?php if (
                            $eleccionAbierta
                        ) { ?>


                            <span class="estado-abierta">

                                🟢 Elección abierta

                            </span>


                        <?php } else { ?>


                            <span class="estado-cerrada">

                                🔴 Elección cerrada

                            </span>


                        <?php } ?>


                    </div>


                </div>


                <!-- =====================================
                     RESUMEN DE PARTICIPACIÓN
                ====================================== -->

                <div class="estado-resumen">


                    <span class="estado-pill verde">

                        <i class="bi bi-person-check-fill"></i>

                        <?php

                        echo $totalEstudiantesVotaron;

                        ?>

                        ya votaron

                    </span>


                    <span class="estado-pill amarillo">

                        <i class="bi bi-person-exclamation"></i>

                        <?php

                        echo $totalEstudiantesPendientes;

                        ?>

                        pendientes

                    </span>


                </div>


                <?php if (
                    !$eleccionAbierta
                ) { ?>


                    <div class="eleccion-cerrada">


                        <i class="bi bi-exclamation-triangle-fill"></i>


                        <strong>

                            La elección está cerrada.

                        </strong>


                        El jurado no puede iniciar nuevas
                        votaciones hasta que el administrador
                        abra la elección.


                    </div>


                <?php } ?>


            <?php } else { ?>


                <div class="alert alert-warning mb-0">


                    <i class="bi bi-exclamation-triangle-fill"></i>


                    No hay ninguna elección registrada.


                </div>


            <?php } ?>


        </div>


        <!-- =============================================
             ACCIONES
        ============================================== -->

        <div class="acciones">


            <a
            href="resultados.php"
            class="accion">


                <i class="bi bi-trophy-fill"></i>


                <h4>

                    Resultados

                </h4>


                <p>

                    Consulta los resultados oficiales
                    de la elección.

                </p>


                <span class="btn btn-outline-primary mt-3">

                    Ver resultados

                </span>


            </a>


            <a
            href="graficas.php"
            class="accion">


                <i class="bi bi-bar-chart-fill"></i>


                <h4>

                    Gráficas

                </h4>


                <p>

                    Consulta las estadísticas y gráficas
                    de las votaciones.

                </p>


                <span class="btn btn-outline-primary mt-3">

                    Ver gráficas

                </span>


            </a>


        </div>


        <!-- =============================================
             PIE DE PÁGINA
        ============================================== -->

        <footer class="footer">


            <div>

                ©

                <?php

                echo date("Y");

                ?>


                <strong>

                    Sistema de Votaciones Escolares

                </strong>


            </div>


            <div>

                Elaborado por

                <strong>

                    Juan David Otero Cantor

                </strong>


            </div>


            <div>

                Todos los derechos reservados.

            </div>


        </footer>


    </div>


</div>


<!-- =====================================================
     JAVASCRIPT DEL BUSCADOR
========================================================= -->

<script>

const campoBusqueda =
    document.getElementById(
        "buscarEstudiante"
    );


const btnBuscar =
    document.getElementById(
        "btnBuscarEstudiante"
    );


const resultados =
    document.getElementById(
        "resultadosEstudiantes"
    );


/* =========================================================
   FUNCIÓN DE BÚSQUEDA
========================================================= */

function buscarEstudiante() {

    const texto =
        campoBusqueda.value.trim();


    if (
        texto.length < 2
    ) {

        resultados.innerHTML = `

            <div class="mensaje-busqueda">

                <i class="bi bi-info-circle"></i>

                <span>

                    Escriba al menos 2 caracteres
                    para realizar la búsqueda.

                </span>

            </div>

        `;

        return;

    }


    resultados.innerHTML = `

        <div class="mensaje-busqueda">

            <i class="bi bi-hourglass-split"></i>

            <span>

                Buscando estudiante...

            </span>

        </div>

    `;


    fetch(
        "buscar_estudiante_jurado.php?buscar="
        +
        encodeURIComponent(texto)
    )

    .then(
        response => {

            if (
                !response.ok
            ) {

                throw new Error(
                    "Error en la búsqueda."
                );

            }

            return response.text();

        }
    )

    .then(
        html => {

            resultados.innerHTML =
                html;

        }
    )

    .catch(
        error => {

            resultados.innerHTML = `

                <div class="resultado-vacio">

                    <div class="resultado-icono">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>

                    <div>

                        <h5>

                            No se pudo realizar la búsqueda

                        </h5>

                        <p>

                            Intente nuevamente.

                        </p>

                    </div>

                </div>

            `;

            console.error(error);

        }
    );

}


/* =========================================================
   BOTÓN BUSCAR
========================================================= */

if (
    btnBuscar
) {

    btnBuscar.addEventListener(
        "click",
        buscarEstudiante
    );

}


/* =========================================================
   ENTER EN EL CAMPO
========================================================= */

if (
    campoBusqueda
) {

    campoBusqueda.addEventListener(
        "keydown",
        function(event) {

            if (
                event.key === "Enter"
            ) {

                event.preventDefault();

                buscarEstudiante();

            }

        }
    );

}

</script>


</body>

</html>
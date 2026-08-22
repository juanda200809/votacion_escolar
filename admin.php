<?php

session_start();

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] != 'administrador'
) {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$total_estudiantes = 0;
$total_jurados = 0;
$total_candidatos = 0;
$total_votos = 0;


/* ESTUDIANTES */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'estudiante'
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_estudiantes = (int)$fila['total'];
}


/* JURADOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'jurado'
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_jurados = (int)$fila['total'];
}


/* CANDIDATOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_candidatos = (int)$fila['total'];
}


/* VOTOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

if ($resultado) {
    $fila = $resultado->fetch_assoc();
    $total_votos = (int)$fila['total'];
}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$eleccion_actual = null;

$resultado = $conn->query("
    SELECT *
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

if (
    $resultado &&
    $resultado->num_rows > 0
) {
    $eleccion_actual = $resultado->fetch_assoc();
}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

$participacion = 0;

if ($total_estudiantes > 0) {

    $participacion =
        round(
            ($total_votos / $total_estudiantes) * 100,
            1
        );

    if ($participacion > 100) {
        $participacion = 100;
    }
}


/* =========================================================
   ESTUDIANTES QUE TODAVÍA NO HAN VOTADO
========================================================= */

$sin_votar = $total_estudiantes - $total_votos;

if ($sin_votar < 0) {
    $sin_votar = 0;
}


/* =========================================================
   PARTICIPACIÓN POR CURSO
========================================================= */

$cursos = [];

$sqlCursos = "
    SELECT
        u.curso,
        COUNT(DISTINCT u.id) AS estudiantes,
        COUNT(DISTINCT v.id_usuario) AS votaron

    FROM usuarios u

    LEFT JOIN votos v
        ON v.id_usuario = u.id

    WHERE u.rol = 'estudiante'

    GROUP BY u.curso

    ORDER BY u.curso
";

$resultadoCursos =
    $conn->query($sqlCursos);

if ($resultadoCursos) {

    while (
        $fila = $resultadoCursos->fetch_assoc()
    ) {

        $estudiantesCurso =
            (int)$fila['estudiantes'];

        $votaronCurso =
            (int)$fila['votaron'];

        $porcentajeCurso = 0;

        if ($estudiantesCurso > 0) {

            $porcentajeCurso =
                round(
                    ($votaronCurso / $estudiantesCurso) * 100,
                    1
                );

        }

        $cursos[] = [
            'curso' =>
                $fila['curso'],
            'estudiantes' =>
                $estudiantesCurso,
            'votaron' =>
                $votaronCurso,
            'porcentaje' =>
                $porcentajeCurso
        ];
    }
}


/* =========================================================
   RESULTADOS PARCIALES
========================================================= */

$resultadosParciales = [];

if ($eleccion_actual) {

    $idEleccion =
        (int)$eleccion_actual['id'];

    $sqlResultados = "
        SELECT
            c.nombre,
            c.apellido,
            ca.nombre_cargo,
            COUNT(v.id) AS votos

        FROM candidatos c

        INNER JOIN cargos ca
            ON ca.id = c.id_cargo

        LEFT JOIN votos v
            ON v.id_candidato = c.id
            AND v.id_eleccion = c.id_eleccion

        WHERE c.id_eleccion = $idEleccion

        GROUP BY
            c.id,
            c.nombre,
            c.apellido,
            ca.nombre_cargo

        ORDER BY
            ca.id ASC,
            votos DESC
    ";

    $resultado =
        $conn->query($sqlResultados);

    if ($resultado) {

        while (
            $fila = $resultado->fetch_assoc()
        ) {

            $resultadosParciales[] =
                $fila;
        }
    }
}


/* =========================================================
   BUSCADOR
========================================================= */

$busqueda = "";

$busquedaResultados = [];

if (isset($_GET['buscar'])) {

    $busqueda =
        trim($_GET['buscar']);

    if ($busqueda !== "") {

        $texto =
            "%" . $conn->real_escape_string($busqueda) . "%";


        /* ESTUDIANTES */

        $sql = "
            SELECT
                id,
                nombre,
                apellido,
                documento,
                curso,
                'Estudiante' AS tipo
            FROM usuarios
            WHERE rol = 'estudiante'
            AND (
                nombre LIKE '$texto'
                OR apellido LIKE '$texto'
                OR documento LIKE '$texto'
                OR curso LIKE '$texto'
            )
            LIMIT 10
        ";

        $resultado =
            $conn->query($sql);

        if ($resultado) {

            while (
                $fila = $resultado->fetch_assoc()
            ) {

                $busquedaResultados[] =
                    $fila;
            }
        }


        /* JURADOS */

        $sql = "
            SELECT
                id,
                nombre,
                apellido,
                documento,
                curso,
                'Jurado' AS tipo
            FROM usuarios
            WHERE rol = 'jurado'
            AND (
                nombre LIKE '$texto'
                OR apellido LIKE '$texto'
                OR documento LIKE '$texto'
            )
            LIMIT 10
        ";

        $resultado =
            $conn->query($sql);

        if ($resultado) {

            while (
                $fila = $resultado->fetch_assoc()
            ) {

                $busquedaResultados[] =
                    $fila;
            }
        }


        /* CANDIDATOS */

        $sql = "
            SELECT
                id,
                nombre,
                apellido,
                curso,
                id_cargo,
                'Candidato' AS tipo
            FROM candidatos
            WHERE
                nombre LIKE '$texto'
                OR apellido LIKE '$texto'
                OR curso LIKE '$texto'
            LIMIT 10
        ";

        $resultado =
            $conn->query($sql);

        if ($resultado) {

            while (
                $fila = $resultado->fetch_assoc()
            ) {

                $busquedaResultados[] =
                    $fila;
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
    content="width=device-width, initial-scale=1"
>

<title>
    Panel de Administración
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef3f9;

    color: #1e293b;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 235px;

    height: 100vh;

    background: #1459a6;

    color: white;

    overflow-y: auto;

    z-index: 1000;
}


.sidebar-header {

    text-align: center;

    padding: 30px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.20);
}


.sidebar-logo {

    font-size: 45px;

    margin-bottom: 15px;
}


.sidebar-header h2 {

    font-size: 22px;

    margin: 0;

    font-weight: 700;
}


.menu {

    padding-top: 15px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 15px;

    color: white;

    text-decoration: none;

    padding: 15px 22px;

    font-size: 16px;

    transition: .2s;
}


.menu a:hover {

    background:
        rgba(255,255,255,.12);
}


.menu a i {

    font-size: 20px;

    width: 22px;

    text-align: center;
}


.menu-separador {

    margin: 10px 15px;

    border-top:
        1px solid
        rgba(255,255,255,.20);
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 235px;

    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    background: #1976e8;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.15);
}


.topbar h1 {

    margin: 0;

    font-size: 25px;

    font-weight: 500;
}


.usuario {

    font-size: 15px;
}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    padding: 30px;
}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background: #dbeafe;

    border:
        1px solid
        #93c5fd;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 25px;

    color: #1459a6;
}


.bienvenida h2 {

    margin: 0 0 10px;

    font-size: 32px;

    font-weight: 700;
}


.bienvenida p {

    margin: 0;

    font-size: 17px;
}


/* =========================================================
   BUSCADOR
========================================================= */

.buscador {

    background: white;

    padding: 20px;

    border-radius: 16px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 16px
        rgba(0,0,0,.08);
}


.buscador h3 {

    color: #1459a6;

    margin-bottom: 15px;
}


.form-busqueda {

    display: flex;

    gap: 10px;
}


.form-busqueda input {

    flex: 1;
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 20px;

    margin-bottom: 30px;
}


.estadistica {

    background: white;

    border-radius: 16px;

    padding: 28px 20px;

    text-align: center;

    box-shadow:
        0 5px 16px
        rgba(0,0,0,.08);
}


.estadistica i {

    font-size: 40px;

    color: #1976e8;
}


.numero {

    font-size: 32px;

    color: #1459a6;

    margin-top: 10px;
}


.etiqueta {

    color: #64748b;

    font-size: 16px;

    margin-top: 5px;
}


/* =========================================================
   TARJETAS
========================================================= */

.panel-card {

    background: white;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 16px
        rgba(0,0,0,.08);
}


.panel-card h3 {

    color: #1459a6;

    margin-bottom: 20px;
}


/* =========================================================
   PARTICIPACIÓN
========================================================= */

.participacion-numero {

    font-size: 45px;

    font-weight: 700;

    color: #1459a6;

    text-align: center;
}


.progress {

    height: 18px;

    border-radius: 20px;
}


/* =========================================================
   ELECCIÓN
========================================================= */

.eleccion {

    background: white;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 30px;

    box-shadow:
        0 5px 16px
        rgba(0,0,0,.08);
}


.eleccion h2 {

    color: #1459a6;

    margin-bottom: 10px;
}


.estado {

    display: inline-block;

    padding: 9px 16px;

    border-radius: 25px;

    font-weight: 600;

    margin-top: 12px;
}


.estado.abierta {

    background: #d1fae5;

    color: #047857;
}


.estado.cerrada {

    background: #fee2e2;

    color: #b91c1c;
}


/* =========================================================
   CONTADOR
========================================================= */

.contador {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 10px;

    margin-top: 20px;
}


.tiempo {

    background: #f1f5f9;

    padding: 15px;

    text-align: center;

    border-radius: 10px;
}


.tiempo strong {

    display: block;

    font-size: 25px;

    color: #1459a6;
}


.tiempo span {

    color: #64748b;

    font-size: 12px;
}


/* =========================================================
   ACCESOS
========================================================= */

.titulo-accesos {

    text-align: center;

    color: #1459a6;

    margin-bottom: 25px;

    font-size: 28px;
}


.accesos {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}


.acceso {

    min-height: 125px;

    border-radius: 14px;

    color: white;

    text-decoration: none;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    font-weight: 700;

    transition: .2s;

    padding: 15px;
}


.acceso:hover {

    transform: translateY(-3px);

    color: white;

    box-shadow:
        0 7px 18px
        rgba(0,0,0,.15);
}


.acceso i {

    font-size: 38px;

    margin-bottom: 12px;
}


.azul {
    background: #1976e8;
}

.verde {
    background: #198754;
}

.celeste {
    background: #18b5d0;
}

.amarillo {

    background: #ffc107;

    color: #111;
}

.rojo {
    background: #dc3545;
}

.oscuro {
    background: #212529;
}

.pdf {
    background: #b52b39;
}


/* =========================================================
   PIE
========================================================= */

.footer {

    text-align: center;

    padding: 28px 20px;

    margin-top: 30px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.7;

    border-top:
        1px solid
        #dbe2ea;
}


.footer strong {

    color: #1459a6;

    font-weight: 600;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .accesos {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 700px) {

    .sidebar {

        width: 200px;
    }

    .main {

        margin-left: 200px;
    }

    .contenido {

        padding: 20px;
    }

    .estadisticas {

        grid-template-columns: 1fr;
    }

    .accesos {

        grid-template-columns: 1fr;
    }

    .form-busqueda {

        flex-direction: column;
    }

    .contador {

        grid-template-columns:
            repeat(2, 1fr);
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="sidebar-header">

        <div class="sidebar-logo">

            <i class="bi bi-building"></i>

        </div>

        <h2>

            ADMINISTRADOR

        </h2>

    </div>


    <nav class="menu">


        <a href="admin.php">

            <i class="bi bi-house-fill"></i>

            Inicio

        </a>


        <a href="estudiantes.php">

            <i class="bi bi-people-fill"></i>

            Estudiantes

        </a>


        <a href="jurados.php">

            <i class="bi bi-person-badge-fill"></i>

            Jurados

        </a>


        <a href="exportar_excel.php">

            <i class="bi bi-file-earmark-excel-fill"></i>

            Exportar Excel

        </a>


        <a href="importar_excel.php">

            <i class="bi bi-cloud-upload-fill"></i>

            Importar Excel

        </a>


        <a href="candidatos.php">

            <i class="bi bi-person-vcard-fill"></i>

            Candidatos

        </a>


        <a href="resultados.php">

            <i class="bi bi-trophy-fill"></i>

            Resultados

        </a>


        <a href="elecciones.php">

            <i class="bi bi-calendar-event-fill"></i>

            Elecciones

        </a>


        <a href="graficas.php">

            <i class="bi bi-bar-chart-fill"></i>

            Gráficas

        </a>


        <div class="menu-separador"></div>


        <a href="abrir_eleccion.php">

            <i class="bi bi-unlock-fill"></i>

            Abrir Elección

        </a>


        <a href="cerrar_eleccion.php">

            <i class="bi bi-lock-fill"></i>

            Cerrar Elección

        </a>


        <div class="menu-separador"></div>


        <a href="descargar_pdf.php">

            <i class="bi bi-file-earmark-pdf-fill"></i>

            Descargar PDF

        </a>


        <a href="cerrar_sesion.php">

            <i class="bi bi-box-arrow-right"></i>

            Cerrar Sesión

        </a>


    </nav>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- HEADER -->

    <header class="topbar">

        <h1>

            <i class="bi bi-shield-fill-check"></i>

            Sistema de Votaciones Escolares

        </h1>


        <div class="usuario">

            <i class="bi bi-person-fill"></i>

            Administrador

        </div>

    </header>


    <section class="contenido">


        <!-- =================================================
             BIENVENIDA
        ================================================= -->

        <div class="bienvenida">

            <h2>

                Bienvenido, Administrador

            </h2>

            <p>

                Panel principal de administración
                del sistema de votaciones escolares.

            </p>

        </div>


        <!-- =================================================
             BUSCADOR
        ================================================= -->

        <div class="buscador">

            <h3>

                <i class="bi bi-search"></i>

                Buscar en el sistema

            </h3>


            <form
                method="GET"
                class="form-busqueda"
            >

                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Nombre, apellido, documento o curso..."
                    value="<?php
                        echo htmlspecialchars(
                            $busqueda
                        );
                    ?>"
                >


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-search"></i>

                    Buscar

                </button>


                <?php if (
                    $busqueda !== ""
                ) { ?>

                    <a
                        href="admin.php"
                        class="btn btn-secondary"
                    >

                        Limpiar

                    </a>

                <?php } ?>

            </form>


            <?php if (
                $busqueda !== ""
            ) { ?>


                <div class="table-responsive mt-4">

                    <?php if (
                        count($busquedaResultados) > 0
                    ) { ?>

                        <table
                            class="table table-hover align-middle"
                        >

                            <thead class="table-light">

                                <tr>

                                    <th>Tipo</th>

                                    <th>Nombre</th>

                                    <th>Documento</th>

                                    <th>Curso</th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                $busquedaResultados
                                as $persona
                            ) { ?>

                                <tr>

                                    <td>

                                        <span class="badge bg-primary">

                                            <?php

                                            echo htmlspecialchars(
                                                $persona['tipo']
                                            );

                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $persona['nombre']
                                            . " "
                                            .
                                            $persona['apellido']
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $persona['documento']
                                            ?? '-'
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $persona['curso']
                                            ?? '-'
                                        );

                                        ?>

                                    </td>

                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    <?php } else { ?>

                        <div class="alert alert-warning">

                            No se encontraron resultados.

                        </div>

                    <?php } ?>

                </div>


            <?php } ?>

        </div>


        <!-- =================================================
             ESTADÍSTICAS
        ================================================= -->

        <div class="estadisticas">


            <div class="estadistica">

                <i class="bi bi-people-fill"></i>

                <div class="numero">

                    <?php

                    echo $total_estudiantes;

                    ?>

                </div>

                <div class="etiqueta">

                    Estudiantes

                </div>

            </div>


            <div class="estadistica">

                <i class="bi bi-person-badge-fill"></i>

                <div class="numero">

                    <?php

                    echo $total_jurados;

                    ?>

                </div>

                <div class="etiqueta">

                    Jurados

                </div>

            </div>


            <div class="estadistica">

                <i class="bi bi-person-vcard-fill"></i>

                <div class="numero">

                    <?php

                    echo $total_candidatos;

                    ?>

                </div>

                <div class="etiqueta">

                    Candidatos

                </div>

            </div>


            <div class="estadistica">

                <i class="bi bi-check2-square"></i>

                <div class="numero">

                    <?php

                    echo $total_votos;

                    ?>

                </div>

                <div class="etiqueta">

                    Votos registrados

                </div>

            </div>


        </div>


        <!-- =================================================
             ELECCIÓN ACTUAL
        ================================================= -->

        <?php if (
            $eleccion_actual
        ) { ?>


        <div class="eleccion">


            <h2>

                <i class="bi bi-calendar-event-fill"></i>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['nombre']
                );

                ?>

            </h2>


            <?php if (
                !empty(
                    $eleccion_actual['descripcion']
                )
            ) { ?>

                <p>

                    <?php

                    echo htmlspecialchars(
                        $eleccion_actual['descripcion']
                    );

                    ?>

                </p>

            <?php } ?>


            <p>

                <strong>Inicio:</strong>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['fecha_inicio']
                );

                ?>


                &nbsp;&nbsp;|&nbsp;&nbsp;


                <strong>Fin:</strong>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['fecha_fin']
                );

                ?>

            </p>


            <?php if (
                $eleccion_actual['estado']
                == 'abierta'
            ) { ?>

                <span class="estado abierta">

                    Elección abierta

                </span>

            <?php } else { ?>

                <span class="estado cerrada">

                    Elección cerrada

                </span>

            <?php } ?>


            <!-- CONTADOR -->

            <?php if (
                $eleccion_actual['estado']
                == 'abierta'
            ) { ?>

                <div class="contador">

                    <div class="tiempo">

                        <strong id="dias">
                            00
                        </strong>

                        <span>
                            Días
                        </span>

                    </div>


                    <div class="tiempo">

                        <strong id="horas">
                            00
                        </strong>

                        <span>
                            Horas
                        </span>

                    </div>


                    <div class="tiempo">

                        <strong id="minutos">
                            00
                        </strong>

                        <span>
                            Minutos
                        </span>

                    </div>


                    <div class="tiempo">

                        <strong id="segundos">
                            00
                        </strong>

                        <span>
                            Segundos
                        </span>

                    </div>

                </div>

            <?php } ?>


        </div>


        <?php } ?>


        <!-- =================================================
             PARTICIPACIÓN
        ================================================= -->

        <div class="panel-card">

            <h3>

                <i class="bi bi-pie-chart-fill"></i>

                Participación electoral

            </h3>


            <div class="participacion-numero">

                <?php

                echo $participacion;

                ?>%

            </div>


            <div class="progress mt-3">

                <div
                    class="progress-bar bg-success"
                    role="progressbar"
                    style="width:
                        <?php
                        echo $participacion;
                        ?>%"
                >

                    <?php

                    echo $total_votos;

                    ?>

                    de

                    <?php

                    echo $total_estudiantes;

                    ?>

                    estudiantes

                </div>

            </div>


            <p class="text-muted mt-3 mb-0">

                <?php

                echo $sin_votar;

                ?>

                estudiantes todavía no han votado.

            </p>

        </div>


        <!-- =================================================
             PARTICIPACIÓN POR CURSO
        ================================================= -->

        <div class="panel-card">

            <h3>

                <i class="bi bi-bar-chart-fill"></i>

                Participación por curso

            </h3>


            <canvas
                id="graficaCursos"
                height="100"
            ></canvas>

        </div>


        <!-- =================================================
             RESULTADOS PARCIALES
        ================================================= -->

        <div class="panel-card">

            <h3>

                <i class="bi bi-trophy-fill"></i>

                Resultados parciales

            </h3>


            <?php if (
                count($resultadosParciales) > 0
            ) { ?>


                <div class="table-responsive">

                    <table
                        class="table table-hover"
                    >

                        <thead>

                            <tr>

                                <th>Cargo</th>

                                <th>Candidato</th>

                                <th>Votos</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $resultadosParciales
                            as $resultado
                        ) { ?>

                            <tr>

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $resultado['nombre_cargo']
                                    );

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $resultado['nombre']
                                        . " "
                                        .
                                        $resultado['apellido']
                                    );

                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?php

                                        echo (int)
                                            $resultado['votos'];

                                        ?>

                                    </strong>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>


            <?php } else { ?>

                <div class="alert alert-info">

                    Todavía no hay resultados registrados.

                </div>

            <?php } ?>


        </div>


        <!-- =================================================
             ALERTAS
        ================================================= -->

        <div class="panel-card">

            <h3>

                <i class="bi bi-bell-fill"></i>

                Alertas del sistema

            </h3>


            <?php if (
                $sin_votar > 0
            ) { ?>

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    Hay

                    <strong>

                        <?php

                        echo $sin_votar;

                        ?>

                    </strong>

                    estudiantes que todavía no han votado.

                </div>

            <?php } else { ?>

                <div class="alert alert-success">

                    Todos los estudiantes han registrado su voto.

                </div>

            <?php } ?>


            <?php if (
                $eleccion_actual &&
                $eleccion_actual['estado'] == 'abierta'
            ) { ?>

                <div class="alert alert-success">

                    <i class="bi bi-check-circle-fill"></i>

                    La elección está actualmente abierta.

                </div>

            <?php } else { ?>

                <div class="alert alert-secondary">

                    <i class="bi bi-lock-fill"></i>

                    No hay una elección abierta actualmente.

                </div>

            <?php } ?>


        </div>


        <!-- =================================================
             ACCESOS RÁPIDOS
        ================================================= -->

        <h2 class="titulo-accesos">

            <i class="bi bi-lightning-charge-fill"></i>

            Accesos rápidos

        </h2>


        <div class="accesos">


            <a
                href="estudiantes.php"
                class="acceso azul"
            >

                <i class="bi bi-people-fill"></i>

                Gestionar Estudiantes

            </a>


            <a
                href="jurados.php"
                class="acceso verde"
            >

                <i class="bi bi-person-badge-fill"></i>

                Gestionar Jurados

            </a>


            <a
                href="exportar_excel.php"
                class="acceso verde"
            >

                <i class="bi bi-file-earmark-excel-fill"></i>

                Exportar Excel

            </a>


            <a
                href="importar_excel.php"
                class="acceso verde"
            >

                <i class="bi bi-cloud-upload-fill"></i>

                Importar Excel

            </a>


            <a
                href="candidatos.php"
                class="acceso celeste"
            >

                <i class="bi bi-person-vcard-fill"></i>

                Gestionar Candidatos

            </a>


            <a
                href="resultados.php"
                class="acceso amarillo"
            >

                <i class="bi bi-trophy-fill"></i>

                Ver Resultados

            </a>


            <a
                href="graficas.php"
                class="acceso celeste"
            >

                <i class="bi bi-bar-chart-fill"></i>

                Ver Gráficas

            </a>


            <a
                href="elecciones.php"
                class="acceso azul"
            >

                <i class="bi bi-calendar-event-fill"></i>

                Gestionar Elecciones

            </a>


            <a
                href="abrir_eleccion.php"
                class="acceso verde"
            >

                <i class="bi bi-unlock-fill"></i>

                Abrir Elección

            </a>


            <a
                href="cerrar_eleccion.php"
                class="acceso rojo"
            >

                <i class="bi bi-lock-fill"></i>

                Cerrar Elección

            </a>


            <a
                href="descargar_pdf.php"
                class="acceso pdf"
            >

                <i class="bi bi-file-earmark-pdf-fill"></i>

                Descargar PDF

            </a>


            <a
                href="cerrar_sesion.php"
                class="acceso oscuro"
            >

                <i class="bi bi-box-arrow-right"></i>

                Cerrar Sesión

            </a>


        </div>


    </section>


    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="footer">

        <div>

            Sistema de Votaciones Escolares

        </div>


        <div>

            © 2026 — Todos los derechos reservados

        </div>


        <div>

            Elaborado por

            <strong>

                Juan David Otero Cantor

            </strong>

        </div>

    </footer>


</main>


<script>

/* =========================================================
   CONTADOR DE ELECCIÓN
========================================================= */

<?php if (
    $eleccion_actual &&
    $eleccion_actual['estado'] == 'abierta'
) { ?>

const fechaFin = new Date(
    "<?php
        echo date(
            'Y-m-d\TH:i:s',
            strtotime(
                $eleccion_actual['fecha_fin']
            )
        );
    ?>"
).getTime();


function actualizarContador() {

    const ahora =
        new Date().getTime();

    const diferencia =
        fechaFin - ahora;


    if (diferencia <= 0) {

        document.getElementById(
            "dias"
        ).innerText = "00";

        document.getElementById(
            "horas"
        ).innerText = "00";

        document.getElementById(
            "minutos"
        ).innerText = "00";

        document.getElementById(
            "segundos"
        ).innerText = "00";

        return;

    }


    const dias =
        Math.floor(
            diferencia /
            (1000 * 60 * 60 * 24)
        );


    const horas =
        Math.floor(
            (diferencia %
            (1000 * 60 * 60 * 24))
            /
            (1000 * 60 * 60)
        );


    const minutos =
        Math.floor(
            (diferencia %
            (1000 * 60 * 60))
            /
            (1000 * 60)
        );


    const segundos =
        Math.floor(
            (diferencia %
            (1000 * 60))
            /
            1000
        );


    document.getElementById(
        "dias"
    ).innerText =
        String(dias).padStart(2, "0");


    document.getElementById(
        "horas"
    ).innerText =
        String(horas).padStart(2, "0");


    document.getElementById(
        "minutos"
    ).innerText =
        String(minutos).padStart(2, "0");


    document.getElementById(
        "segundos"
    ).innerText =
        String(segundos).padStart(2, "0");

}


actualizarContador();

setInterval(
    actualizarContador,
    1000
);

<?php } ?>


/* =========================================================
   GRÁFICA POR CURSO
========================================================= */

const nombresCursos = [

<?php

foreach ($cursos as $curso) {

    echo "'" .
        addslashes(
            $curso['curso']
        )
        . "',";

}

?>

];


const porcentajesCursos = [

<?php

foreach ($cursos as $curso) {

    echo
        $curso['porcentaje']
        . ",";

}

?>

];


const canvas =
    document.getElementById(
        "graficaCursos"
    );


if (canvas) {

    new Chart(
        canvas,
        {

            type: "bar",

            data: {

                labels:
                    nombresCursos,

                datasets: [

                    {

                        label:
                            "Participación %",

                        data:
                            porcentajesCursos,

                        borderWidth: 1

                    }

                ]

            },

            options: {

                responsive: true,

                scales: {

                    y: {

                        beginAtZero: true,

                        max: 100

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>
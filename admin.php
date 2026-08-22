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

    $total_estudiantes =
        (int)$fila['total'];
}


/* JURADOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'jurado'
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $total_jurados =
        (int)$fila['total'];
}


/* CANDIDATOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $total_candidatos =
        (int)$fila['total'];
}


/* VOTOS */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $total_votos =
        (int)$fila['total'];
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

    $eleccion_actual =
        $resultado->fetch_assoc();

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


<!-- BOOTSTRAP -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- ICONOS -->

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

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef3f9;

    color: #1e293b;
}


/* =========================================================
   BARRA LATERAL
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
   CONTENIDO
========================================================= */

.main {

    margin-left: 235px;

    min-height: 100vh;
}


/* =========================================================
   ENCABEZADO
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
   CONTENIDO PRINCIPAL
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
   ELECCIÓN ACTUAL
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
   ACCESOS RÁPIDOS
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


/* =========================================================
   COLORES
========================================================= */

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
   PIE DE PÁGINA
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


.footer-centro {

    max-width: 700px;

    margin: auto;
}


.footer-centro > div:first-child {

    font-size: 14px;

    font-weight: 500;

}


.autor {

    margin-top: 5px;

    color: #64748b;
}


.autor strong {

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

    .footer {

        font-size: 12px;

        padding:
            22px 10px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     BARRA LATERAL
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


        <a href="cerrar_sesion.php">

            <i class="bi bi-box-arrow-right"></i>

            Cerrar Sesión

        </a>


    </nav>

</aside>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="main">


    <!-- =================================================
         HEADER
    ================================================= -->

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


    <!-- =================================================
         CONTENIDO PRINCIPAL
    ================================================= -->

    <section class="contenido">


        <!-- BIENVENIDA -->

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

        <?php

        if ($eleccion_actual) {

        ?>


        <div class="eleccion">


            <h2>

                <i class="bi bi-calendar-event-fill"></i>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['nombre']
                );

                ?>

            </h2>


            <?php

            if (
                !empty(
                    $eleccion_actual['descripcion']
                )
            ) {

            ?>

                <p>

                    <?php

                    echo htmlspecialchars(
                        $eleccion_actual['descripcion']
                    );

                    ?>

                </p>

            <?php

            }

            ?>


            <div>

                <strong>

                    Inicio:

                </strong>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['fecha_inicio']
                );

                ?>


                &nbsp;&nbsp;|&nbsp;&nbsp;


                <strong>

                    Fin:

                </strong>

                <?php

                echo htmlspecialchars(
                    $eleccion_actual['fecha_fin']
                );

                ?>

            </div>


            <?php

            if (
                $eleccion_actual['estado']
                == 'abierta'
            ) {

            ?>

                <span class="estado abierta">

                    <i class="bi bi-circle-fill"></i>

                    Elección abierta

                </span>

            <?php

            } else {

            ?>

                <span class="estado cerrada">

                    <i class="bi bi-circle-fill"></i>

                    Elección cerrada

                </span>

            <?php

            }

            ?>


        </div>


        <?php

        }

        ?>


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


    <!-- =================================================
         PIE DE PÁGINA
    ================================================= -->

    <footer class="footer">


        <div class="footer-centro">


            <div>

                Sistema de Votaciones Escolares

            </div>


            <div>

                © 2026 — Todos los derechos reservados

            </div>


            <div class="autor">

                Elaborado por

                <strong>

                    Juan David Otero Cantor

                </strong>

            </div>


        </div>


    </footer>


</main>


</body>

</html>
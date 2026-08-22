<?php

session_start();

/* =========================================================
   SEGURIDAD
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    $_SESSION['rol'] !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


include("config/conexion.php");


/* =========================================================
   NOMBRE DEL ADMINISTRADOR
========================================================= */

$nombreAdmin = $_SESSION['nombre'] ?? 'Administrador';


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalEstudiantes = 0;
$totalJurados = 0;
$totalCandidatos = 0;
$totalVotos = 0;


/* =========================================================
   TOTAL ESTUDIANTES
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'estudiante'
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalEstudiantes =
        (int)$fila['total'];
}


/* =========================================================
   TOTAL JURADOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE rol = 'jurado'
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalJurados =
        (int)$fila['total'];
}


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM candidatos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalCandidatos =
        (int)$fila['total'];
}


/* =========================================================
   TOTAL VOTOS
========================================================= */

$resultado = $conn->query("
    SELECT COUNT(*) AS total
    FROM votos
");

if ($resultado) {

    $fila = $resultado->fetch_assoc();

    $totalVotos =
        (int)$fila['total'];
}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$eleccionActual = null;

$resultado = $conn->query("
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
    $resultado &&
    $resultado->num_rows > 0
) {

    $eleccionActual =
        $resultado->fetch_assoc();
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
Panel de Administración
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

    background: #f1f5f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1e293b;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 245px;

    height: 100vh;

    background: #1557a6;

    color: white;

    overflow-y: auto;

}


/* =========================================================
   LOGO SIDEBAR
========================================================= */

.logo {

    text-align: center;

    padding: 28px 15px;

    border-bottom:
        1px solid
        rgba(255,255,255,.15);

}


.logo .icono-colegio {

    font-size: 42px;

}


.logo h2 {

    margin-top: 10px;

    font-size: 21px;

    font-weight: 700;

    letter-spacing: .5px;

}


/* =========================================================
   MENÚ
========================================================= */

.menu {

    padding: 12px 0;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 13px 22px;

    color: white;

    text-decoration: none;

    font-size: 15px;

    transition: .2s;

}


.menu a:hover {

    background:
        rgba(255,255,255,.10);

}


.menu a i {

    font-size: 19px;

    width: 23px;

}


/* =========================================================
   SEPARADORES
========================================================= */

.menu hr {

    margin:
        15px;

    border-color:
        rgba(255,255,255,.25);

}


/* =========================================================
   CONTENIDO
========================================================= */

.contenido {

    margin-left: 245px;

    min-height: 100vh;

}


/* =========================================================
   BARRA SUPERIOR
========================================================= */

.topbar {

    height: 72px;

    background: #1976e8;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.12);

}


.topbar h3 {

    margin: 0;

    font-size: 22px;

    font-weight: 600;

}


.usuario-admin {

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 15px;

}


/* =========================================================
   CONTENEDOR
========================================================= */

.container-admin {

    padding: 30px;

}


/* =========================================================
   BIENVENIDA
========================================================= */

.bienvenida {

    background: #dbeafe;

    border:
        1px solid
        #bfdbfe;

    border-radius: 15px;

    padding: 24px;

    margin-bottom: 25px;

}


.bienvenida h2 {

    color: #1459a6;

    font-weight: 700;

    margin-bottom: 7px;

}


.bienvenida p {

    color: #1e4f8f;

    margin-bottom: 0;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;

}


.stat {

    background: white;

    border-radius: 15px;

    padding: 23px;

    text-align: center;

    border:
        1px solid
        #e2e8f0;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


.stat .icono {

    font-size: 38px;

    color: #1976e8;

}


.stat h3 {

    margin:
        8px 0 3px;

    color: #1459a6;

    font-size: 30px;

    font-weight: 600;

}


.stat p {

    margin: 0;

    color: #64748b;

}


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

.eleccion {

    background: white;

    border-radius: 15px;

    padding: 24px;

    margin-bottom: 28px;

    border:
        1px solid
        #e2e8f0;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.06);

}


.eleccion h3 {

    color: #1459a6;

    font-weight: 700;

}


.eleccion p {

    color: #475569;

}


.estado-abierta,
.estado-cerrada {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        8px 15px;

    border-radius: 20px;

    font-size: 14px;

    font-weight: 600;

}


.estado-abierta {

    background: #dcfce7;

    color: #166534;

}


.estado-cerrada {

    background: #fee2e2;

    color: #991b1b;

}


/* =========================================================
   TÍTULO ACCESOS
========================================================= */

.titulo-accesos {

    text-align: center;

    color: #1e293b;

    font-size: 27px;

    font-weight: 700;

    margin-bottom: 22px;

}


/* =========================================================
   ACCESOS RÁPIDOS
========================================================= */

.accesos {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 17px;

}


/* =========================================================
   TARJETAS
========================================================= */

.acceso {

    min-height: 140px;

    border-radius: 15px;

    color: white;

    text-decoration: none;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    font-weight: 600;

    font-size: 16px;

    padding: 20px;

    transition: .2s;

    border:
        1px solid
        rgba(0,0,0,.05);

    box-shadow:
        0 3px 10px
        rgba(0,0,0,.08);

}


.acceso:hover {

    color: white;

    transform:
        translateY(-3px);

    box-shadow:
        0 7px 18px
        rgba(0,0,0,.13);

}


/* =========================================================
   ICONOS GRANDES
========================================================= */

.acceso i {

    font-size: 42px;

    line-height: 1;

    margin-bottom: 13px;

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

    background: #17a9c4;

}


.amarillo {

    background: #f5b800;

    color: #17202a;

}


.amarillo:hover {

    color: #17202a;

}


.rojo {

    background: #dc3545;

}


.pdf {

    background: #b02a37;

}


.negro {

    background: #343a40;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    color: #64748b;

    font-size: 14px;

    padding:
        30px 10px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 1100px
) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .accesos {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (
    max-width: 700px
) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }


    .contenido {

        margin-left: 0;

    }


    .estadisticas {

        grid-template-columns:
            1fr;

    }


    .accesos {

        grid-template-columns:
            1fr;

    }


    .topbar {

        padding:
            0 15px;

    }


    .topbar h3 {

        font-size: 18px;

    }


    .container-admin {

        padding: 15px;

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

<div class="icono-colegio">

<i class="bi bi-building-fill"></i>

</div>


<h2>

ADMINISTRADOR

</h2>

</div>


<div class="menu">


<a href="admin.php">

<i class="bi bi-house-fill"></i>

<span>
Inicio
</span>

</a>


<a href="estudiantes.php">

<i class="bi bi-people-fill"></i>

<span>
Estudiantes
</span>

</a>


<a href="jurados.php">

<i class="bi bi-person-badge-fill"></i>

<span>
Jurados
</span>

</a>


<a href="exportar_estudiantes.php">

<i class="bi bi-file-earmark-excel-fill"></i>

<span>
Exportar Excel
</span>

</a>


<a href="importar_estudiantes.php">

<i class="bi bi-cloud-arrow-up-fill"></i>

<span>
Importar Excel
</span>

</a>


<a href="candidatos.php">

<i class="bi bi-person-vcard-fill"></i>

<span>
Candidatos
</span>

</a>


<a href="resultados.php">

<i class="bi bi-trophy-fill"></i>

<span>
Resultados
</span>

</a>


<a href="elecciones.php">

<i class="bi bi-calendar-event-fill"></i>

<span>
Elecciones
</span>

</a>


<a href="graficas.php">

<i class="bi bi-bar-chart-fill"></i>

<span>
Gráficas
</span>

</a>


<hr>


<a href="abrir_eleccion.php">

<i class="bi bi-unlock-fill"></i>

<span>
Abrir Elección
</span>

</a>


<a href="cerrar_eleccion.php">

<i class="bi bi-lock-fill"></i>

<span>
Cerrar Elección
</span>

</a>


<hr>


<a href="logout.php">

<i class="bi bi-box-arrow-right"></i>

<span>
Cerrar Sesión
</span>

</a>


</div>

</div>


<!-- =====================================================
     CONTENIDO
========================================================= -->

<div class="contenido">


<!-- =====================================================
     BARRA SUPERIOR
========================================================= -->

<div class="topbar">


<h3>

<i class="bi bi-shield-fill-check"></i>

Sistema de Votaciones Escolares

</h3>


<div class="usuario-admin">

<i class="bi bi-person-fill"></i>

<span>

<?php

echo htmlspecialchars(
    $nombreAdmin
);

?>

</span>

</div>


</div>


<!-- =====================================================
     CONTENIDO PRINCIPAL
========================================================= -->

<div class="container-admin">


<!-- =====================================================
     BIENVENIDA
========================================================= -->

<div class="bienvenida">


<h2>

Bienvenido,
<?php

echo htmlspecialchars(
    $nombreAdmin
);

?>


<i class="bi bi-hand-wave"></i>

</h2>


<p>

Panel principal de administración
del sistema de votaciones escolares.

</p>


</div>


<!-- =====================================================
     ESTADÍSTICAS
========================================================= -->

<div class="estadisticas">


<div class="stat">


<div class="icono">

<i class="bi bi-people-fill"></i>

</div>


<h3>

<?php

echo $totalEstudiantes;

?>

</h3>


<p>

Estudiantes

</p>


</div>


<div class="stat">


<div class="icono">

<i class="bi bi-person-badge-fill"></i>

</div>


<h3>

<?php

echo $totalJurados;

?>

</h3>


<p>

Jurados

</p>


</div>


<div class="stat">


<div class="icono">

<i class="bi bi-person-vcard-fill"></i>

</div>


<h3>

<?php

echo $totalCandidatos;

?>

</h3>


<p>

Candidatos

</p>


</div>


<div class="stat">


<div class="icono">

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


<!-- =====================================================
     ELECCIÓN ACTUAL
========================================================= -->

<?php

if (
    $eleccionActual !== null
) {

?>


<div class="eleccion">


<div class="row align-items-center">


<div class="col-md-8">


<h3>

<i class="bi bi-calendar-event-fill"></i>

<?php

echo htmlspecialchars(
    $eleccionActual['nombre']
);

?>

</h3>


<p class="mb-1">

<?php

echo htmlspecialchars(
    $eleccionActual['descripcion']
);

?>

</p>


<small class="text-muted">

Inicio:

<?php

echo htmlspecialchars(
    $eleccionActual['fecha_inicio']
);

?>


&nbsp; | &nbsp;


Fin:

<?php

echo htmlspecialchars(
    $eleccionActual['fecha_fin']
);

?>

</small>


</div>


<div
class="col-md-4 text-md-end mt-3 mt-md-0">


<?php

if (
    $eleccionActual['estado']
    === 'abierta'
) {

?>


<span class="estado-abierta">

<i class="bi bi-circle-fill"></i>

Elección abierta

</span>


<?php

} else {

?>


<span class="estado-cerrada">

<i class="bi bi-circle-fill"></i>

Elección cerrada

</span>


<?php

}

?>


</div>


</div>


</div>


<?php

}

?>


<!-- =====================================================
     ACCESOS RÁPIDOS
========================================================= -->

<h2 class="titulo-accesos">

<i class="bi bi-grid-3x3-gap-fill"></i>

Accesos rápidos

</h2>


<div class="accesos">


<!-- ESTUDIANTES -->

<a
href="estudiantes.php"
class="acceso azul">


<i class="bi bi-people-fill"></i>


<span>

Gestionar Estudiantes

</span>


</a>


<!-- JURADOS -->

<a
href="jurados.php"
class="acceso verde">


<i class="bi bi-person-badge-fill"></i>


<span>

Gestionar Jurados

</span>


</a>


<!-- EXPORTAR -->

<a
href="exportar_estudiantes.php"
class="acceso verde">


<i class="bi bi-file-earmark-excel-fill"></i>


<span>

Exportar Excel

</span>


</a>


<!-- IMPORTAR -->

<a
href="importar_estudiantes.php"
class="acceso verde">


<i class="bi bi-cloud-arrow-up-fill"></i>


<span>

Importar Excel

</span>


</a>


<!-- CANDIDATOS -->

<a
href="candidatos.php"
class="acceso celeste">


<i class="bi bi-person-vcard-fill"></i>


<span>

Gestionar Candidatos

</span>


</a>


<!-- RESULTADOS -->

<a
href="resultados.php"
class="acceso amarillo">


<i class="bi bi-trophy-fill"></i>


<span>

Ver Resultados

</span>


</a>


<!-- GRÁFICAS -->

<a
href="graficas.php"
class="acceso celeste">


<i class="bi bi-bar-chart-fill"></i>


<span>

Ver Gráficas

</span>


</a>


<!-- ELECCIONES -->

<a
href="elecciones.php"
class="acceso azul">


<i class="bi bi-calendar-event-fill"></i>


<span>

Gestionar Elecciones

</span>


</a>


<!-- ABRIR -->

<a
href="abrir_eleccion.php"
class="acceso verde">


<i class="bi bi-unlock-fill"></i>


<span>

Abrir Elección

</span>


</a>


<!-- CERRAR -->

<a
href="cerrar_eleccion.php"
class="acceso rojo">


<i class="bi bi-lock-fill"></i>


<span>

Cerrar Elección

</span>


</a>


<!-- PDF -->

<a
href="pdf_resultados.php"
class="acceso pdf"
target="_blank">


<i class="bi bi-file-earmark-pdf-fill"></i>


<span>

Descargar PDF

</span>


</a>


<!-- CERRAR SESIÓN -->

<a
href="logout.php"
class="acceso negro">


<i class="bi bi-box-arrow-right"></i>


<span>

Cerrar Sesión

</span>


</a>


</div>


<!-- =====================================================
     FOOTER
========================================================= -->

<div class="footer">

Sistema de Votaciones Escolares

<br>

© <?php echo date("Y"); ?>

</div>


</div>


</div>


</body>

</html>
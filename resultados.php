<?php

session_start();

require_once "config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");


/* =========================================================
   VERIFICAR SESIÓN DEL JURADO
========================================================= */

$juradoAutenticado = false;
$juradoId = 0;


/* Sesión específica del jurado */
if (isset($_SESSION['jurado_id'])) {

    $juradoId = (int) $_SESSION['jurado_id'];

    if ($juradoId > 0) {
        $juradoAutenticado = true;
    }
}


/* Sesión utilizada por jurado.php */
if (
    isset($_SESSION['id']) &&
    isset($_SESSION['rol']) &&
    strtolower(trim((string) $_SESSION['rol'])) === 'jurado'
) {

    $juradoId = (int) $_SESSION['id'];

    if ($juradoId > 0) {
        $juradoAutenticado = true;
    }
}


if (!$juradoAutenticado) {

    header("Location: login.php");
    exit;

}


/* =========================================================
   OBTENER ELECCIÓN
========================================================= */

$idEleccion = filter_input(
    INPUT_GET,
    'id_eleccion',
    FILTER_VALIDATE_INT
);


<<<<<<< HEAD
if (
    !$idEleccion &&
    isset($_SESSION['id_eleccion'])
=======
$nombreUsuario =
    $_SESSION['nombre'] ?? 'Usuario';


$idUsuario =
    (int)($_SESSION['id'] ?? 0);


/* =========================================================
   IMPORTANTE
   NO BLOQUEAR RESULTADOS PARA EL JURADO
========================================================= */

/*
 * El jurado puede consultar resultados aunque su mesa
 * esté cerrada.
 *
 * NO se redirige a votar_por_jurado.php.
 */


/* =========================================================
   LISTAR ELECCIONES
========================================================= */

$elecciones = [];


$resultadoElecciones =
    $conn->query("

        SELECT
            id,
            nombre,
            descripcion,
            fecha_inicio,
            fecha_fin,
            estado

        FROM elecciones

        ORDER BY fecha_inicio DESC

    ");


if ($resultadoElecciones) {

    while (
        $fila =
        $resultadoElecciones->fetch_assoc()
    ) {

        $elecciones[] =
            $fila;

    }

}


/* =========================================================
   ELECCIÓN SELECCIONADA
========================================================= */

$idEleccion =
    isset($_GET['id_eleccion'])
    ? (int)$_GET['id_eleccion']
    : 0;


/* =========================================================
   SI NO SE SELECCIONÓ:
   JURADO → SU ELECCIÓN
   ADMIN → ÚLTIMA ELECCIÓN
========================================================= */

if ($idEleccion <= 0) {

    if (
        $rol === 'jurado' &&
        isset(
            $_SESSION['id_eleccion_jurado']
        ) &&
        (int)$_SESSION['id_eleccion_jurado'] > 0
    ) {

        $idEleccion =
            (int)
            $_SESSION['id_eleccion_jurado'];

    }

}


if (
    $idEleccion <= 0 &&
    count($elecciones) > 0
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb
) {

    $idEleccion =
        (int) $_SESSION['id_eleccion'];

}


if (!$idEleccion) {

    $stmt = $conn->prepare("
        SELECT id
        FROM elecciones
        ORDER BY id DESC
        LIMIT 1
    ");


    if (!$stmt) {

        die(
            "Error al consultar las elecciones: "
            . $conn->error
        );

    }


    $stmt->execute();

    $resultado =
        $stmt->get_result();

    $fila =
        $resultado->fetch_assoc();

    $stmt->close();


    if (!$fila) {

        die(
            "No existen elecciones registradas."
        );

    }


    $idEleccion =
        (int) $fila['id'];

}


/* =========================================================
   OBTENER LA MESA DEL JURADO
========================================================= */

$mesa = null;


$stmtMesa = $conn->prepare("
    SELECT
        id,
        id_eleccion,
        id_jurado,
        nombre_mesa,
        estado,
        fecha_cierre
    FROM mesas_votacion
    WHERE id_eleccion = ?
      AND id_jurado = ?
    LIMIT 1
");


if (!$stmtMesa) {

    die(
        "Error al consultar la mesa de votación: "
        . $conn->error
    );

}


$stmtMesa->bind_param(
    "ii",
    $idEleccion,
    $juradoId
);


$stmtMesa->execute();


$resultadoMesa =
    $stmtMesa->get_result();


$mesa =
    $resultadoMesa->fetch_assoc();


$stmtMesa->close();


if (!$mesa) {

    die(
        "No existe una mesa de votación asignada a este jurado para esta elección."
    );

}


/* =========================================================
   CERRAR ÚNICAMENTE LA MESA DEL JURADO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['cerrar_mesa'])
) {


    $idCerrar = filter_input(
        INPUT_POST,
        'id_eleccion',
        FILTER_VALIDATE_INT
    );


<<<<<<< HEAD
    if (
        !$idCerrar ||
        $idCerrar !== $idEleccion
    ) {

        die("Elección no válida.");

    }


    /*
     * IMPORTANTE:
     *
     * NO modificamos elecciones.estado.
     *
     * Solamente cerramos la mesa
     * perteneciente al jurado actual.
     */

    $stmtCerrar = $conn->prepare("
        UPDATE mesas_votacion
        SET
            estado = 'cerrada',
            fecha_cierre = NOW()
        WHERE id_eleccion = ?
          AND id_jurado = ?
          AND estado = 'abierta'
    ");


    if (!$stmtCerrar) {

        die(
            "No se pudo preparar el cierre de la mesa: "
            . $conn->error
        );

    }


    $stmtCerrar->bind_param(
        "ii",
        $idEleccion,
        $juradoId
    );


    if (!$stmtCerrar->execute()) {

        $stmtCerrar->close();

        die(
            "No se pudo cerrar la mesa de votación."
        );

    }


    $stmtCerrar->close();


    $_SESSION['id_eleccion'] =
        $idEleccion;


    header(
        "Location: resultados.php?id_eleccion="
        . $idEleccion
        . "&cerrada=1"
    );

    exit;

}


/* =========================================================
   ACTUALIZAR ESTADO DE LA MESA
========================================================= */

$stmtMesa = $conn->prepare("
    SELECT
        id,
        id_eleccion,
        id_jurado,
        nombre_mesa,
        estado,
        fecha_cierre
    FROM mesas_votacion
    WHERE id_eleccion = ?
      AND id_jurado = ?
    LIMIT 1
");


$stmtMesa->bind_param(
    "ii",
    $idEleccion,
    $juradoId
);


$stmtMesa->execute();


$resultadoMesa =
    $stmtMesa->get_result();


$mesa =
    $resultadoMesa->fetch_assoc();


$stmtMesa->close();


$mesaCerrada =
    isset($mesa['estado'])
    &&
    strtolower(
        trim(
            (string) $mesa['estado']
        )
    ) === 'cerrada';


/* =========================================================
   INFORMACIÓN DE LA ELECCIÓN
========================================================= */

$stmt = $conn->prepare("
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

    die(
        "Error al preparar la consulta de elección."
    );

}


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
        "La elección no existe."
    );

}


$_SESSION['id_eleccion'] =
    $idEleccion;


/* =========================================================
   CONTAR VOTOS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM votos
    WHERE id_eleccion = ?
");


if (!$stmt) {

    die(
        "Error al consultar los votos."
    );

}


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
    (int) (
        $fila['total']
        ??
        0
    );


$stmt->close();


/* =========================================================
   CONTAR CANDIDATOS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM candidatos
    WHERE id_eleccion = ?
");


if (!$stmt) {

    die(
        "Error al consultar los candidatos."
    );

}


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
    (int) (
        $fila['total']
        ??
        0
    );


$stmt->close();


/* =========================================================
   CONTAR CARGOS
========================================================= */

$totalCargos = 0;


$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM eleccion_cargos
    WHERE id_eleccion = ?
");


if ($stmt) {

=======
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb
    $stmt->bind_param(
        "i",
        $idEleccion
    );
<<<<<<< HEAD


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $fila =
        $resultado->fetch_assoc();


    $totalCargos =
        (int) (
            $fila['total']
            ??
            0
        );


    $stmt->close();

}


/* =========================================================
   OBTENER CANDIDATOS Y VOTOS
========================================================= */

$sql = "

    SELECT

        c.id,
        c.nombre,
        c.apellido,
        c.curso,
        c.foto,
        c.id_cargo,

        ca.nombre_cargo,

        COUNT(v.id) AS votos


    FROM candidatos c


    INNER JOIN cargos ca

        ON ca.id = c.id_cargo


    LEFT JOIN votos v

        ON v.id_candidato = c.id

        AND v.id_eleccion = ?


    INNER JOIN eleccion_cargos ec

        ON ec.id_eleccion = ?

        AND ec.id_cargo = c.id_cargo


    WHERE c.id_eleccion = ?


    GROUP BY

        c.id,
        c.nombre,
        c.apellido,
        c.curso,
        c.foto,
        c.id_cargo,
        ca.nombre_cargo


    ORDER BY

        c.id_cargo ASC,
        votos DESC,
        c.apellido ASC,
        c.nombre ASC

";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Error al consultar los resultados: "
        . $conn->error
    );

}


$stmt->bind_param(
    "iii",
    $idEleccion,
    $idEleccion,
    $idEleccion
);


$stmt->execute();


$resultadoCandidatos =
    $stmt->get_result();


$candidatosPorCargo = [];


while (
    $fila =
    $resultadoCandidatos->fetch_assoc()
) {

    $idCargo =
        (int) $fila['id_cargo'];


    if (
        !isset(
            $candidatosPorCargo[$idCargo]
        )
    ) {

        $candidatosPorCargo[$idCargo] = [

            'nombre_cargo' =>
                $fila['nombre_cargo'],

            'candidatos' => []

        ];

    }


    $candidatosPorCargo[$idCargo]['candidatos'][] =
        $fila;

}


$stmt->close();


/* =========================================================
   NOMBRE DEL JURADO
========================================================= */

$nombreJurado =
    'Jurado';


if (
    !empty(
        $_SESSION['jurado_nombre']
    )
) {

    $nombreJurado =
        $_SESSION['jurado_nombre'];

}

elseif (
    !empty(
        $_SESSION['nombre']
    )
) {

    $nombreJurado =
        $_SESSION['nombre'];

}


/* =========================================================
   MENSAJE
========================================================= */

$mesaCerradaAhora =
    isset($_GET['cerrada']);


/* =========================================================
   FUNCIÓN PARA ENCONTRAR LA FOTO
========================================================= */

function encontrarFotoCandidato($fotoOriginal)
{

    $fotoOriginal =
        trim(
            (string) $fotoOriginal
        );


    if (
        $fotoOriginal === ''
    ) {

        return '';

    }


    /*
     * Si la base de datos ya contiene
     * una URL completa.
     */

    if (
        filter_var(
            $fotoOriginal,
            FILTER_VALIDATE_URL
        )
    ) {

        return $fotoOriginal;

    }


    /*
     * Normalizar separadores.
     */

    $fotoOriginal =
        str_replace(
            '\\',
            '/',
            $fotoOriginal
        );


    /*
     * Si ya contiene una ruta relativa,
     * primero intentamos utilizarla tal cual.
     */

    $rutaDirecta =
        ltrim(
            $fotoOriginal,
            '/'
        );


    $rutaFisicaDirecta =
        __DIR__
        . DIRECTORY_SEPARATOR
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $rutaDirecta
        );


    if (
        file_exists(
            $rutaFisicaDirecta
        )
    ) {

        return $rutaDirecta;

    }


    /*
     * Obtener solamente el nombre
     * del archivo.
     */

    $nombreFoto =
        basename(
            $fotoOriginal
        );


    /*
     * Carpetas donde normalmente
     * puede estar la fotografía.
     */

    $posiblesCarpetas = [

        'uploads/candidatos/',
        'imagenes/candidatos/',
        'img/candidatos/',
        'fotos/candidatos/',
        'candidatos/',
        'fotos/',
        'uploads/'

    ];


    foreach (
        $posiblesCarpetas
        as $carpeta
    ) {

        $rutaRelativa =
            $carpeta
            . $nombreFoto;


        $rutaFisica =
            __DIR__
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $rutaRelativa
            );


        if (
            file_exists(
                $rutaFisica
            )
        ) {

            return $rutaRelativa;

        }

    }

=======


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    if (
        $resultado->num_rows > 0
    ) {

        $datosEleccion =
            $resultado->fetch_assoc();

    }


    $stmt->close();

}


/* =========================================================
   GUARDAR ELECCIÓN EN SESIÓN PARA JURADO
========================================================= */

if (
    $rol === 'jurado' &&
    $datosEleccion
) {

    $_SESSION['id_eleccion_jurado'] =
        $idEleccion;

}


/* =========================================================
   OBTENER MESA DEL JURADO
========================================================= */

$mesaJurado = null;

$mesaJuradoExiste = false;

$mesaJuradoAbierta = false;


if (
    $rol === 'jurado' &&
    $idUsuario > 0 &&
    $idEleccion > 0
) {

    $stmtMesa =
        $conn->prepare("

            SELECT
                id,
                id_eleccion,
                id_jurado,
                nombre_mesa,
                estado,
                fecha_cierre

            FROM mesas_votacion

            WHERE id_eleccion = ?

            AND id_jurado = ?

            LIMIT 1

        ");


    $stmtMesa->bind_param(
        "ii",
        $idEleccion,
        $idUsuario
    );


    $stmtMesa->execute();


    $resultadoMesa =
        $stmtMesa->get_result();


    if (
        $resultadoMesa->num_rows > 0
    ) {

        $mesaJurado =
            $resultadoMesa->fetch_assoc();

        $mesaJuradoExiste =
            true;


        $mesaJuradoAbierta =
            strtolower(
                trim(
                    (string)
                    $mesaJurado['estado']
                )
            ) === 'abierta';

    }


    $stmtMesa->close();

}
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

    /*
     * No se encontró.
     */

<<<<<<< HEAD
    return '';
=======
/* =========================================================
   CARGOS
========================================================= */

$cargos = [];


if (
    $datosEleccion
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

        $cargos[] =
            $cargo;

    }


    $stmt->close();

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalVotos = 0;

$totalCandidatos = 0;

$totalCargos =
    count($cargos);


/* =========================================================
   TOTAL CANDIDATOS
========================================================= */

if (
    $datosEleccion
) {

    $stmt =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

            FROM candidatos

            WHERE id_eleccion = ?

        ");


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


/* =========================================================
   TOTAL VOTOS
========================================================= */

if (
    $datosEleccion
) {

    $stmt =
        $conn->prepare("

            SELECT
                COUNT(*) AS total

            FROM votos

            WHERE id_eleccion = ?

        ");


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
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

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
    Resultados Oficiales
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

    color: #1f2937;
}


/* =================================================
   BARRA SUPERIOR
================================================= */

.topbar {

    height: 70px;

    background: #1769d2;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;
}


.topbar-title {

    font-size: 22px;

    font-weight: 700;
}


.jurado {

    font-size: 15px;

    font-weight: 600;
}


/* =================================================
   ESTRUCTURA
================================================= */

.layout {

    display: flex;

    min-height:
        calc(100vh - 70px);
}


.sidebar {

    width: 250px;

    background: #185ca8;

    color: white;

    padding-bottom: 30px;
}


.sidebar-header {

    text-align: center;

    padding:
        35px 15px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);
}


.sidebar-icon {

    font-size: 48px;

    margin-bottom: 8px;
}


.sidebar-header h2 {

    margin: 0;

    font-size: 30px;
}


.sidebar-header p {

    margin: 5px 0 0;

    opacity: .85;
}


.menu {

    padding-top: 18px;
}


.menu a {

    display: flex;

    align-items: center;

    gap: 14px;

    padding:
        17px 25px;

    color: white;

    text-decoration: none;

    font-size: 16px;

    transition: .2s;
}


.menu a:hover {

    background:
        rgba(255,255,255,.12);
}


.menu a.active {

    background:
        rgba(0,0,0,.15);
}


/* =================================================
   CONTENIDO
================================================= */

.content {

    flex: 1;

    padding: 35px;

    overflow-x: auto;
}


.container {

    max-width: 1100px;

    margin: auto;
}


/* =================================================
   ELECCIÓN
================================================= */

.election-card {

    background: white;

    border-radius: 18px;

    padding: 28px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.08);

    margin-bottom: 25px;
}


.election-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;
}


.election-title {

    color: #1769d2;

    font-size: 28px;

    font-weight: 700;

    margin:
        0 0 8px;
}


.election-description {

    color: #64748b;

    margin: 0;
}


.estado {

    padding:
        9px 16px;

    border-radius: 20px;

    font-weight: 700;

    font-size: 13px;

    white-space: nowrap;
}


.estado.abierta {

    background: #d1fae5;

    color: #047857;
}


.estado.cerrada {

    background: #e5e7eb;

    color: #4b5563;
}


.dates {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-top: 25px;
}


.date-box {

    background: #f8fafc;

    border:
        1px solid #dbe4ef;

    border-radius: 12px;

    padding: 16px;
}


.date-label {

    color: #64748b;

    font-size: 14px;

    margin-bottom: 5px;
}


.date-value {

    color: #175cae;

    font-weight: 700;
}


/* =================================================
   ESTADO DE LA MESA
================================================= */

.mensaje-cerrada {

    background: #ecfdf5;

    border:
        1px solid #a7f3d0;

    color: #047857;

    border-radius: 12px;

    padding:
        17px 20px;

    margin-bottom: 25px;

    font-weight: 600;
}


.mesa-abierta {

    background: #eff6ff;

    border:
        1px solid #bfdbfe;

    color: #1d4ed8;

    border-radius: 12px;

    padding:
        17px 20px;

    margin-bottom: 25px;

    font-weight: 600;
}


/* =================================================
   CERRAR MESA
================================================= */

.cerrar-mesa {

    background: white;

    border:
        1px solid #fecaca;

    border-radius: 16px;

    padding: 22px;

    margin-bottom: 28px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
}


.cerrar-info strong {

    display: block;

    color: #b91c1c;

    font-size: 18px;

    margin-bottom: 7px;
}


.cerrar-info span {

    color: #64748b;

    font-size: 14px;
}


.btn-cerrar {

    border: none;

    background: #dc2626;

    color: white;

    padding:
        15px 25px;

    border-radius: 10px;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;

    white-space: nowrap;
}


.btn-cerrar:hover {

    background: #b91c1c;

    transform:
        translateY(-2px);

    box-shadow:
        0 7px 18px
        rgba(220,38,38,.25);
}


.mesa-ya-cerrada {

    background: #f1f5f9;

    border:
        1px solid #cbd5e1;

    color: #64748b;

    border-radius: 14px;

    padding:
        18px 22px;

    margin-bottom: 28px;

    font-weight: 700;
}


<<<<<<< HEAD
/* =================================================
=======
/* =========================================================
   MESA DEL JURADO
========================================================= */

.mesa-jurado {

    margin-top: 25px;

    padding: 22px;

    border-radius: 16px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    flex-wrap: wrap;

}


.mesa-jurado.abierta {

    background: #ecfdf5;

    border:
        1px solid #a7f3d0;

}


.mesa-jurado.cerrada {

    background: #f1f5f9;

    border:
        1px solid #cbd5e1;

}


.mesa-contenido {

    display: flex;

    align-items: center;

    gap: 15px;

}


.mesa-icon {

    width: 58px;

    height: 58px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 28px;

}


.mesa-jurado.abierta
.mesa-icon {

    background: #d1fae5;

}


.mesa-jurado.cerrada
.mesa-icon {

    background: #e2e8f0;

}


.mesa-contenido h4 {

    margin: 0 0 5px;

    color: #1453a3;

    font-weight: bold;

}


.mesa-contenido p {

    margin: 0;

    color: #64748b;

}


.estado-mesa {

    margin-top: 7px;

    font-weight: bold;

}


.mesa-jurado.abierta
.estado-mesa {

    color: #047857;

}


.mesa-jurado.cerrada
.estado-mesa {

    color: #64748b;

}


.btn-cerrar-mesa {

    border: none;

    background: #dc3545;

    color: white;

    font-weight: bold;

    padding: 14px 22px;

    border-radius: 12px;

    cursor: pointer;

    box-shadow:
        0 6px 15px
        rgba(220,53,69,.20);

    transition: .2s;

}


.btn-cerrar-mesa:hover {

    background: #bb2d3b;

    transform:
        translateY(-1px);

}


.mesa-cerrada-label {

    background: #cbd5e1;

    color: #64748b;

    padding: 14px 22px;

    border-radius: 12px;

    font-weight: bold;

}


/* =========================================================
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb
   ESTADÍSTICAS
================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 30px;
}


.stat-card {

    background: white;

    border-radius: 17px;

    padding: 28px;

    text-align: center;

    box-shadow:
        0 7px 22px
        rgba(0,0,0,.07);
}


.stat-icon {

    font-size: 38px;

    color: #1769d2;

    margin-bottom: 8px;
}


.stat-number {

    font-size: 34px;

    color: #175cae;

    font-weight: 700;
}


.stat-label {

    color: #64748b;

    margin-top: 7px;

    font-weight: 600;
}


/* =================================================
   CARGOS
================================================= */

.cargo {

    background: white;

    border-radius: 18px;

    overflow: hidden;

    margin-bottom: 30px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.08);
}


.cargo-header {

    background: #1760ad;

    color: white;

    padding:
        20px 25px;

    font-size: 25px;

    font-weight: 700;
}


.tabla {

    width: 100%;

    border-collapse: collapse;
}


.tabla th {

    background: #cfe0fa;

    color: #1556a1;

    text-align: left;

    padding: 16px;

    font-size: 15px;
}


.tabla td {

    padding: 16px;

    border-bottom:
        1px solid #e5e7eb;
}


.tabla tr:last-child td {

    border-bottom: none;
}


.candidato {

    display: flex;

    align-items: center;

    gap: 13px;
}


.foto {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    object-fit: cover;

    border:
        2px solid #dbeafe;

    display: block;
}


.foto-vacia {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    background: #dbeafe;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #1769d2;

    font-size: 23px;
}


.nombre-candidato {

    font-weight: 700;

    color: #1f2937;
}


.votos {

    font-size: 20px;

    font-weight: 700;

    color: #1769d2;
}


.porcentaje {

    font-weight: 700;

    color: #175cae;
}


.ganador {

    display: inline-block;

    background: #fbbf24;

    color: #78350f;

    padding:
        7px 12px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 800;
}


.sin-candidatos {

    padding: 25px;

    color: #64748b;

    text-align: center;
}


/* =================================================
   RESPONSIVE
================================================= */

@media (max-width: 850px) {

    .sidebar {

        width: 210px;
    }


    .content {

        padding: 20px;
    }


    .stats {

        grid-template-columns: 1fr;
    }


    .cerrar-mesa {

        flex-direction: column;

        align-items: stretch;
    }


    .btn-cerrar {

        width: 100%;
    }

}


@media (max-width: 650px) {

    .layout {

        display: block;
    }


    .sidebar {

        width: 100%;
    }


    .dates {

        grid-template-columns: 1fr;
    }


    .election-top {

        flex-direction: column;
    }


    .tabla {

        min-width: 800px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
<<<<<<< HEAD
     BARRA SUPERIOR
===================================================== -->
=======
     SIDEBAR
========================================================= -->
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

<header class="topbar">

    <div class="topbar-title">

        ⚖️ Sistema de Votaciones Escolares

    </div>


    <div class="jurado">

        👤 Jurado:

        <?php

        echo htmlspecialchars(
            $nombreJurado
        );

        ?>

    </div>

</header>


<div class="layout">


<!-- =================================================
     SIDEBAR
================================================= -->

<aside class="sidebar">


    <div class="sidebar-header">

        <div class="sidebar-icon">
            🗳️
        </div>


        <h2>
            VOTACIONES
        </h2>


        <p>
            Panel del Jurado
        </p>

    </div>


    <nav class="menu">


        <a href="jurado.php">

            🏠

            <span>
                Inicio
            </span>

        </a>


        <a
            href="ingresar_estudiante.php?id_eleccion=<?php echo $idEleccion; ?>"
            target="_blank"
            rel="noopener noreferrer"
        >

            👤

            <span>
                Ingresar estudiante
            </span>

        </a>


        <a
            class="active"
            href="resultados.php?id_eleccion=<?php echo $idEleccion; ?>"
        >

<<<<<<< HEAD
            🏆
=======
<a
href="resultados.php?id_eleccion=<?php
echo $idEleccion;
?>"
class="activo"
>
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

            <span>
                Resultados
            </span>

        </a>


        <a
            href="graficas.php?id_eleccion=<?php echo $idEleccion; ?>"
        >

<<<<<<< HEAD
            📊
=======
<a
href="graficas.php?id_eleccion=<?php
echo $idEleccion;
?>"
>
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

            <span>
                Gráficas
            </span>

        </a>


    </nav>


    <nav class="menu">


        <a
            href="logout.php"
            onclick="
                return confirm(
                    '¿Está seguro de cerrar sesión?'
                );
            "
        >

            🚪

            <span>
                Cerrar sesión
            </span>

        </a>


    </nav>


</aside>


<!-- =================================================
     CONTENIDO
================================================= -->

<main class="content">


<div class="container">


<!-- =================================================
     INFORMACIÓN DE ELECCIÓN
================================================= -->

<section class="election-card">


    <div class="election-top">


        <div>

            <h1 class="election-title">

                🗳️

                <?php

                echo htmlspecialchars(
                    $eleccion['nombre']
                );

                ?>

            </h1>


            <p class="election-description">

                <?php

                echo htmlspecialchars(
                    $eleccion['descripcion']
                    ??
                    'Proceso democrático institucional'
                );

                ?>

            </p>

        </div>


        <?php

        if (
            strtolower(
                trim(
                    (string)
                    $eleccion['estado']
                )
            )
            ===
            'abierta'
        ):

        ?>

            <div class="estado abierta">

                🟢 Elección abierta

            </div>

        <?php

        else:

        ?>

            <div class="estado cerrada">

                🔒 Elección cerrada

            </div>

        <?php

        endif;

        ?>


    </div>


    <div class="dates">


        <div class="date-box">

            <div class="date-label">

                📅 Fecha de inicio

            </div>


            <div class="date-value">

                <?php

                echo htmlspecialchars(
                    $eleccion['fecha_inicio']
                    ??
                    'No definida'
                );

                ?>

            </div>

        </div>


        <div class="date-box">

            <div class="date-label">

                📅 Fecha de finalización

            </div>


            <div class="date-value">

                <?php

                echo htmlspecialchars(
                    $eleccion['fecha_fin']
                    ??
                    'No definida'
                );

                ?>

            </div>

        </div>


    </div>


</section>


<!-- =================================================
     MENSAJE
================================================= -->

<?php

if (
    $mesaCerradaAhora
):

?>

    <div class="mensaje-cerrada">

        🔒 <strong>
            Mesa cerrada correctamente.
        </strong>


        <br>


        La mesa

        <?php

        echo htmlspecialchars(
            $mesa['nombre_mesa']
        );

        ?>

        del jurado

        <?php

        echo htmlspecialchars(
            $nombreJurado
        );

        ?>

        quedó cerrada.


        <br>


        La elección general continúa disponible
        para las demás mesas.

    </div>

<?php

endif;

?>


<!-- =================================================
     ESTADO DE LA MESA
================================================= -->

<?php

if (
    $mesaCerrada
):

?>

    <div class="mesa-ya-cerrada">

        🔒 Mesa cerrada


        <br>


        <span>

            Esta mesa ya no permite nuevos votos.

        </span>

    </div>


<?php

else:

?>


    <div class="mesa-abierta">

        🟢

        <strong>
            Mesa abierta
        </strong>


        <br>


        🗳️ Mesa:

        <?php

        echo htmlspecialchars(
            $mesa['nombre_mesa']
        );

        ?>


        — 👤 Jurado:

        <?php

        echo htmlspecialchars(
            $nombreJurado
        );

        ?>

    </div>


    <!-- =================================================
         BOTÓN CERRAR MESA
    ================================================= -->

    <section class="cerrar-mesa">


        <div class="cerrar-info">

            <strong>

                🔒 Cierre de mesa de votación

            </strong>


            <span>

                Al cerrar esta mesa no se
                permitirán nuevos votos
                para este jurado.


                <br>


                Las demás mesas de la elección
                permanecerán abiertas.

            </span>

        </div>


        <form
            method="POST"
            onsubmit="
                return confirm(
                    '¿Está seguro de cerrar SOLAMENTE esta mesa? Las demás mesas continuarán abiertas.'
                );
            "
        >


            <input
                type="hidden"
                name="cerrar_mesa"
                value="1"
            >


            <input
                type="hidden"
                name="id_eleccion"
                value="<?php echo $idEleccion; ?>"
            >


            <button
                type="submit"
                class="btn-cerrar"
            >

                🔒 Cerrar mesa

            </button>


        </form>


    </section>


<?php

endif;

?>


<<<<<<< HEAD
<!-- =================================================
=======
</div>


</div>


<!-- =====================================================
     MESA DEL JURADO
========================================================= -->

<?php if (
    $rol === 'jurado'
) { ?>


<?php if (
    $mesaJuradoExiste
) { ?>


<div
    class="mesa-jurado
    <?php

    echo $mesaJuradoAbierta
        ? 'abierta'
        : 'cerrada';

    ?>"
>


<div class="mesa-contenido">


<div class="mesa-icon">

<?php

echo $mesaJuradoAbierta
    ? '🗳️'
    : '🔒';

?>

</div>


<div>


<h4>

🏫

<?php

echo htmlspecialchars(
    $mesaJurado['nombre_mesa']
);

?>

</h4>


<p>

Mesa asignada a:

<strong>

<?php

echo htmlspecialchars(
    $nombreUsuario
);

?>

</strong>

</p>


<div class="estado-mesa">

<?php if (
    $mesaJuradoAbierta
) { ?>

🟢 Mesa de votación abierta

<?php } else { ?>

🔒 Mesa de votación cerrada

<?php } ?>

</div>


<?php if (
    !$mesaJuradoAbierta &&
    !empty(
        $mesaJurado['fecha_cierre']
    )
) { ?>

<p
    style="
        margin-top:6px;
        font-size:13px;
    "
>

📅 Cerrada:

<?php

echo htmlspecialchars(
    $mesaJurado['fecha_cierre']
);

?>

</p>

<?php } ?>


</div>


</div>


<?php if (
    $mesaJuradoAbierta
) { ?>


<form
    method="POST"
    action="cerrar_mesa.php"
    onsubmit="
        return confirm(
            '¿Está seguro de cerrar esta mesa? Después de cerrarla no se podrán registrar nuevos votos en ella.'
        );
    "
>


<input
    type="hidden"
    name="id_mesa"
    value="<?php

        echo (int)
        $mesaJurado['id'];

    ?>"
>


<input
    type="hidden"
    name="id_eleccion"
    value="<?php

        echo $idEleccion;

    ?>"
>


<button
    type="submit"
    class="btn-cerrar-mesa"
>

🔒 Cerrar mesa de votación

</button>


</form>


<?php } else { ?>


<span
    class="mesa-cerrada-label"
>

🔒 Mesa cerrada

</span>


<?php } ?>


</div>


<?php } else { ?>


<div
    class="mesa-jurado cerrada"
>


<div class="mesa-contenido">


<div class="mesa-icon">

⚠️

</div>


<div>


<h4>

🏫 Mesa no asignada

</h4>


<p>

No existe una mesa asignada
a este jurado para esta elección.

</p>


<div class="estado-mesa">

🚫 No puede iniciar votaciones

</div>


</div>


</div>


</div>


<?php } ?>


<?php } ?>


</div>


<!-- =====================================================
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb
     ESTADÍSTICAS
================================================= -->

<section class="stats">


    <div class="stat-card">

        <div class="stat-icon">
            🗳️
        </div>


        <div class="stat-number">

            <?php

            echo $totalVotos;

            ?>

        </div>


        <div class="stat-label">

            Votos registrados

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            👥
        </div>


        <div class="stat-number">

            <?php

            echo $totalCandidatos;

            ?>

        </div>


        <div class="stat-label">

            Candidatos

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🏅
        </div>


        <div class="stat-number">

            <?php

            echo $totalCargos;

            ?>

        </div>


        <div class="stat-label">

            Cargos

        </div>

    </div>


</section>


<!-- =================================================
     RESULTADOS POR CARGO
================================================= -->

<?php

if (
    empty($candidatosPorCargo)
):

?>


<section class="cargo">


    <div class="cargo-header">

        🏅 Resultados

    </div>


    <div class="sin-candidatos">

        👤 No hay candidatos registrados
        para esta elección.

    </div>


</section>


<?php

else:

?>


<?php

foreach (
    $candidatosPorCargo
    as $cargo
):


    $candidatos =
        $cargo['candidatos'];


    $maxVotos = 0;


    foreach (
        $candidatos
        as $candidato
    ) {

<<<<<<< HEAD
        $v =
            (int)
            $candidato['votos'];
=======
        $idCargo =
            (int)$cargo['id'];


        /* =============================================
           TOTAL VOTOS DEL CARGO
        ============================================= */

        $totalVotosCargo = 0;


        $stmtTotal =
            $conn->prepare("

                SELECT
                    COUNT(*) AS total

                FROM votos

                WHERE id_eleccion = ?

                AND id_cargo = ?

            ");


        $stmtTotal->bind_param(
            "ii",
            $idEleccion,
            $idCargo
        );


        $stmtTotal->execute();


        $resultadoTotal =
            $stmtTotal->get_result();


        $filaTotal =
            $resultadoTotal->fetch_assoc();


        $totalVotosCargo =
            (int)$filaTotal['total'];


        $stmtTotal->close();


        /* =============================================
           CANDIDATOS Y VOTOS
        ============================================= */

        $resultadosCargo = [];


        $stmt =
            $conn->prepare("

                SELECT

                    c.id,

                    c.nombre,

                    c.apellido,

                    c.curso,

                    c.foto,

                    COUNT(v.id) AS total

                FROM candidatos c

                LEFT JOIN votos v

                    ON v.id_candidato = c.id

                    AND v.id_eleccion = ?

                    AND v.id_cargo = ?

                WHERE c.id_eleccion = ?

                AND c.id_cargo = ?

                GROUP BY

                    c.id,

                    c.nombre,

                    c.apellido,

                    c.curso,

                    c.foto

                ORDER BY

                    total DESC,

                    c.id ASC

            ");


        $stmt->bind_param(
            "iiii",
            $idEleccion,
            $idCargo,
            $idEleccion,
            $idCargo
        );


        $stmt->execute();


        $resultado =
            $stmt->get_result();


        while (
            $fila =
            $resultado->fetch_assoc()
        ) {

            $resultadosCargo[] =
                $fila;

        }


        $stmt->close();


        /* =============================================
           MAYOR CANTIDAD
        ============================================= */

        $mayorCantidad = 0;


        foreach (
            $resultadosCargo as $fila
        ) {

            $cantidad =
                (int)$fila['total'];


            if (
                $cantidad >
                $mayorCantidad
            ) {

                $mayorCantidad =
                    $cantidad;

            }

        }


        /* =============================================
           EMPATES
        ============================================= */

        $cantidadEmpatados = 0;
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb


        if (
            $v > $maxVotos
        ) {

            $maxVotos = $v;

        }

    }

?>


<section class="cargo">


    <div class="cargo-header">

        🏅

        <?php

        echo htmlspecialchars(
            $cargo['nombre_cargo']
        );

        ?>

    </div>


    <div style="overflow-x:auto;">


        <table class="tabla">


            <thead>


                <tr>

                    <th>
                        👤 Candidato
                    </th>

                    <th>
                        🎓 Curso
                    </th>

                    <th>
                        🗳️ Votos
                    </th>

                    <th>
                        📊 Porcentaje
                    </th>

                    <th>
                        🏆 Estado
                    </th>

                </tr>


            </thead>


            <tbody>


            <?php

            foreach (
                $candidatos
                as $candidato
            ):


                $votosCandidato =
                    (int)
                    $candidato['votos'];


                $porcentaje = 0;


                if (
                    $totalVotos > 0
                ) {

                    $porcentaje =
                        (
                            $votosCandidato
                            /
                            $totalVotos
                        ) * 100;

                }


                $esGanador =
                    (
                        $maxVotos > 0
                        &&
                        $votosCandidato === $maxVotos
                    );


                /*
                 * BUSCAR LA FOTO REAL
                 */

                $foto =
                    encontrarFotoCandidato(
                        $candidato['foto']
                        ??
                        ''
                    );

            ?>


            <tr>


                <td>


                    <div class="candidato">


                        <?php

                        if (
                            $foto !== ''
                        ):

                        ?>


                            <img
                                src="<?php
                                echo htmlspecialchars(
                                    $foto
                                );
                                ?>"
                                class="foto"
                                alt="Foto del candidato"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="foto-vacia"
                                style="display:none;"
                            >

                                👤

                            </div>


                        <?php

                        else:

                        ?>


                            <div class="foto-vacia">

                                👤

                            </div>


                        <?php

                        endif;

                        ?>


                        <div>


                            <div class="nombre-candidato">

                                <?php

                                echo htmlspecialchars(
                                    $candidato['nombre']
                                    . ' '
                                    . $candidato['apellido']
                                );

                                ?>

                            </div>


                        </div>


                    </div>


                </td>


                <td>

                    🎓

                    <?php

                    echo htmlspecialchars(
                        $candidato['curso']
                        ??
                        ''
                    );

                    ?>

                </td>


                <td>

                    <span class="votos">

                        🗳️

                        <?php

                        echo $votosCandidato;

                        ?>

                    </span>

                </td>


                <td>

                    <span class="porcentaje">

                        📊

                        <?php

                        echo number_format(
                            $porcentaje,
                            0
                        );

                        ?>%

                    </span>

                </td>


                <td>


                    <?php

                    if (
                        $esGanador
                    ):

                    ?>


                        <span class="ganador">

                            🏆 GANADOR

                        </span>


                    <?php

                    endif;

                    ?>


                </td>


            </tr>


            <?php

            endforeach;

            ?>


            </tbody>


        </table>


    </div>


</section>


<?php

<<<<<<< HEAD
endforeach;
=======
$foto =
    trim(
        (string)$fila['foto']
    );


$rutaFoto =
    "uploads/candidatos/"
    .
    $foto;


if (
    $foto !== ""
    &&
    file_exists(
        $rutaFoto
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


<strong>

<?php

echo htmlspecialchars(
    $fila['nombre']
    .
    " "
    .
    $fila['apellido']
);

?>

</strong>


</div>


</td>


<td>

<?php

echo htmlspecialchars(
    $fila['curso']
);

?>

</td>


<td>

<span class="votos">

<?php

echo $votosCandidato;

?>

</span>


</td>


<td>

<?php

echo $porcentaje;

?>%


</td>


<td>


<?php if (
    $esEmpate
) { ?>


<span class="badge-empate">

⚠️ EMPATE

</span>


<?php

} elseif (
    $esMayor
) {

?>


<span class="badge-ganador">

🏆 GANADOR

</span>


<?php

} else {

?>


<span class="text-muted">

Participante

</span>


<?php

}

?>


</td>


</tr>


<?php

}
>>>>>>> 434b2ebdd65091129a8816585a0e013cea6e82cb

?>


<?php

endif;

?>


</div>


</main>


</div>


</body>

</html>
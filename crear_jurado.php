<?php

session_start();

require_once "config/conexion.php";

$conn->set_charset("utf8mb4");


/* =========================================================
   SEGURIDAD
========================================================= */

if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['rol']) ||
    strtolower(trim((string)$_SESSION['rol'])) !== 'administrador'
) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "success";

$documento = "";
$nombre = "";
$apellido = "";
$curso = "";
$correo = "";


/* =========================================================
   ELECCIÓN ACTUAL
========================================================= */

$idEleccion = 0;

$stmtEleccion = $conn->prepare("
    SELECT
        id,
        nombre,
        estado
    FROM elecciones
    ORDER BY id DESC
    LIMIT 1
");

if (!$stmtEleccion) {
    die(
        "Error al consultar la elección: "
        . $conn->error
    );
}

$stmtEleccion->execute();

$resultadoEleccion =
    $stmtEleccion->get_result();

$eleccion =
    $resultadoEleccion->fetch_assoc();

$stmtEleccion->close();


if (!$eleccion) {

    die(
        "No existe ninguna elección registrada."
    );
}


$idEleccion =
    (int)$eleccion['id'];


/* =========================================================
   REGISTRAR JURADO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['registrar_jurado'])
) {

    $documento =
        trim(
            (string)($_POST['documento'] ?? '')
        );

    $nombre =
        trim(
            (string)($_POST['nombre'] ?? '')
        );

    $apellido =
        trim(
            (string)($_POST['apellido'] ?? '')
        );

    $curso =
        trim(
            (string)($_POST['curso'] ?? '')
        );

    $correo =
        trim(
            (string)($_POST['correo'] ?? '')
        );


    /* -----------------------------------------------------
       VALIDAR DATOS
    ----------------------------------------------------- */

    if (
        $documento === ''
        ||
        $nombre === ''
        ||
        $apellido === ''
    ) {

        $mensaje =
            "Complete todos los campos obligatorios.";

        $tipoMensaje =
            "danger";

    } else {


        /* -------------------------------------------------
           VERIFICAR DOCUMENTO
        ------------------------------------------------- */

        $stmtExiste =
            $conn->prepare("
                SELECT id
                FROM usuarios
                WHERE documento = ?
                LIMIT 1
            ");

        if (!$stmtExiste) {

            die(
                "Error al preparar la consulta: "
                . $conn->error
            );
        }


        $stmtExiste->bind_param(
            "s",
            $documento
        );

        $stmtExiste->execute();

        $resultadoExiste =
            $stmtExiste->get_result();

        $existe =
            $resultadoExiste->fetch_assoc();

        $stmtExiste->close();


        if ($existe) {

            $mensaje =
                "Ya existe un usuario con ese documento.";

            $tipoMensaje =
                "danger";

        } else {


            /* ---------------------------------------------
               CONTRASEÑA
            --------------------------------------------- */

            $passwordHash =
                password_hash(
                    $documento,
                    PASSWORD_DEFAULT
                );


            /* ---------------------------------------------
               TRANSACCIÓN
            --------------------------------------------- */

            $conn->begin_transaction();


            try {


                /* -----------------------------------------
                   CREAR JURADO
                ----------------------------------------- */

                $stmtJurado =
                    $conn->prepare("
                        INSERT INTO usuarios
                        (
                            documento,
                            nombre,
                            apellido,
                            correo,
                            curso,
                            password,
                            rol
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'jurado'
                        )
                    ");


                if (!$stmtJurado) {

                    throw new Exception(
                        "No se pudo preparar el registro del jurado."
                    );
                }


                $stmtJurado->bind_param(
                    "ssssss",
                    $documento,
                    $nombre,
                    $apellido,
                    $correo,
                    $curso,
                    $passwordHash
                );


                if (
                    !$stmtJurado->execute()
                ) {

                    $error =
                        $stmtJurado->error;

                    $stmtJurado->close();

                    throw new Exception(
                        "No se pudo registrar el jurado: "
                        . $error
                    );
                }


                /* -----------------------------------------
                   ID DEL JURADO
                ----------------------------------------- */

                $idJurado =
                    (int)$conn->insert_id;


                $stmtJurado->close();


                if ($idJurado <= 0) {

                    throw new Exception(
                        "No se pudo obtener el ID del jurado creado."
                    );
                }


                /* -----------------------------------------
                   BUSCAR NÚMERO DE MESA
                ----------------------------------------- */

                $stmtNumero =
                    $conn->prepare("
                        SELECT
                            COALESCE(
                                MAX(
                                    CAST(
                                        REPLACE(
                                            nombre_mesa,
                                            'Mesa ',
                                            ''
                                        ) AS UNSIGNED
                                    )
                                ),
                                0
                            ) AS ultimo_numero

                        FROM mesas_votacion

                        WHERE id_eleccion = ?
                    ");


                if (!$stmtNumero) {

                    throw new Exception(
                        "No se pudo consultar el número de mesa."
                    );
                }


                $stmtNumero->bind_param(
                    "i",
                    $idEleccion
                );


                if (
                    !$stmtNumero->execute()
                ) {

                    $stmtNumero->close();

                    throw new Exception(
                        "No se pudo calcular el número de mesa."
                    );
                }


                $resultadoNumero =
                    $stmtNumero->get_result();


                $filaNumero =
                    $resultadoNumero->fetch_assoc();


                $stmtNumero->close();


                $ultimoNumero =
                    (int)(
                        $filaNumero['ultimo_numero']
                        ?? 0
                    );


                $numeroMesa =
                    $ultimoNumero + 1;


                $nombreMesa =
                    "Mesa " . $numeroMesa;


                /* -----------------------------------------
                   CREAR MESA AUTOMÁTICAMENTE
                ----------------------------------------- */

                $stmtMesa =
                    $conn->prepare("
                        INSERT INTO mesas_votacion
                        (
                            id_eleccion,
                            id_jurado,
                            nombre_mesa,
                            estado,
                            fecha_cierre
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            'abierta',
                            NULL
                        )
                    ");


                if (!$stmtMesa) {

                    throw new Exception(
                        "No se pudo preparar la creación de la mesa: "
                        . $conn->error
                    );
                }


                $stmtMesa->bind_param(
                    "iis",
                    $idEleccion,
                    $idJurado,
                    $nombreMesa
                );


                if (
                    !$stmtMesa->execute()
                ) {

                    $errorMesa =
                        $stmtMesa->error;

                    $stmtMesa->close();

                    throw new Exception(
                        "No se pudo crear la mesa automáticamente: "
                        . $errorMesa
                    );
                }


                $stmtMesa->close();


                /* -----------------------------------------
                   TODO CORRECTO
                ----------------------------------------- */

                $conn->commit();


                header(
                    "Location: jurados.php?creado=1"
                );

                exit();


            } catch (Throwable $e) {


                $conn->rollback();


                $mensaje =
                    $e->getMessage();

                $tipoMensaje =
                    "danger";
            }
        }
    }
}


/* =========================================================
   ELIMINAR JURADO
========================================================= */

if (
    isset($_GET['eliminar'])
) {

    $idJurado =
        (int)$_GET['eliminar'];


    if ($idJurado > 0) {


        $conn->begin_transaction();


        try {


            /* ---------------------------------------------
               ELIMINAR MESAS DEL JURADO
            --------------------------------------------- */

            $stmtMesa =
                $conn->prepare("
                    DELETE FROM mesas_votacion
                    WHERE id_jurado = ?
                ");


            if (!$stmtMesa) {

                throw new Exception(
                    "No se pudo preparar la eliminación de la mesa."
                );
            }


            $stmtMesa->bind_param(
                "i",
                $idJurado
            );


            if (
                !$stmtMesa->execute()
            ) {

                $error =
                    $stmtMesa->error;

                $stmtMesa->close();

                throw new Exception(
                    "No se pudo eliminar la mesa: "
                    . $error
                );
            }


            $stmtMesa->close();


            /* ---------------------------------------------
               ELIMINAR JURADO
            --------------------------------------------- */

            $stmtJurado =
                $conn->prepare("
                    DELETE FROM usuarios
                    WHERE id = ?
                    AND LOWER(TRIM(rol)) = 'jurado'
                ");


            if (!$stmtJurado) {

                throw new Exception(
                    "No se pudo preparar la eliminación del jurado."
                );
            }


            $stmtJurado->bind_param(
                "i",
                $idJurado
            );


            if (
                !$stmtJurado->execute()
            ) {

                $error =
                    $stmtJurado->error;

                $stmtJurado->close();

                throw new Exception(
                    "No se pudo eliminar el jurado: "
                    . $error
                );
            }


            $stmtJurado->close();


            $conn->commit();


            header(
                "Location: jurados.php?eliminado=1"
            );

            exit();


        } catch (Throwable $e) {


            $conn->rollback();


            header(
                "Location: jurados.php?error="
                .
                urlencode(
                    $e->getMessage()
                )
            );

            exit();
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
Crear jurado
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

body {

    background: #eef3f9;

    min-height: 100vh;

}


.contenedor {

    max-width: 900px;

    margin:
        40px auto;

    padding:
        0 20px;
}


.card-jurado {

    background: white;

    border: none;

    border-radius: 18px;

    box-shadow:
        0 6px 25px
        rgba(0,0,0,.10);

    overflow: hidden;
}


.encabezado {

    background: #0d47a1;

    color: white;

    padding: 25px 30px;
}


.encabezado h1 {

    margin: 0;

    font-size: 28px;

    font-weight: 800;
}


.encabezado p {

    margin:
        7px 0 0;

    opacity: .85;
}


.cuerpo {

    padding: 30px;
}


.form-label {

    font-weight: 700;

    color: #26364a;
}


.form-control {

    min-height: 48px;

    border-radius: 9px;

    border:
        1px solid #ced7e2;
}


.form-control:focus {

    border-color: #1674e8;

    box-shadow:
        0 0 0 3px
        rgba(22,116,232,.12);
}


.info {

    background: #d9f4fc;

    color: #075985;

    border-radius: 10px;

    padding: 17px;

    margin-top: 22px;

    margin-bottom: 22px;
}


.btn-registrar {

    background: #198754;

    color: white;

    border: none;

    border-radius: 9px;

    padding:
        12px 22px;

    font-weight: 700;
}


.btn-registrar:hover {

    background: #157347;

    color: white;
}


.btn-volver {

    background: #6c757d;

    color: white;

    border: none;

    border-radius: 9px;

    padding:
        12px 22px;

    font-weight: 700;

    text-decoration: none;
}


.btn-volver:hover {

    background: #5c636a;

    color: white;
}


@media(max-width:700px) {

    .contenedor {

        margin:
            20px auto;
    }


    .cuerpo {

        padding: 20px;
    }

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card-jurado">


<div class="encabezado">


<h1>

<i class="bi bi-person-badge-fill"></i>

Crear nuevo jurado

</h1>


<p>

Registrar un jurado para la elección actual.

</p>


</div>


<div class="cuerpo">


<?php if ($mensaje !== "") { ?>


<div class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>">


<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje,
    ENT_QUOTES,
    'UTF-8'
);

?>

</div>


<?php } ?>


<div class="alert alert-primary">


<strong>

Elección actual:

</strong>


<?php

echo htmlspecialchars(
    $eleccion['nombre'],
    ENT_QUOTES,
    'UTF-8'
);

?>


</div>


<form
    method="POST"
    autocomplete="off"
>


<div class="mb-3">


<label
    for="documento"
    class="form-label"
>

Documento *

</label>


<input
    type="text"
    class="form-control"
    id="documento"
    name="documento"
    value="<?php

echo htmlspecialchars(
    $documento,
    ENT_QUOTES,
    'UTF-8'
);

?>"
    required
>


</div>


<div class="row">


<div class="col-md-6 mb-3">


<label
    for="nombre"
    class="form-label"
>

Nombre *

</label>


<input
    type="text"
    class="form-control"
    id="nombre"
    name="nombre"
    value="<?php

echo htmlspecialchars(
    $nombre,
    ENT_QUOTES,
    'UTF-8'
);

?>"
    required
>


</div>


<div class="col-md-6 mb-3">


<label
    for="apellido"
    class="form-label"
>

Apellido *

</label>


<input
    type="text"
    class="form-control"
    id="apellido"
    name="apellido"
    value="<?php

echo htmlspecialchars(
    $apellido,
    ENT_QUOTES,
    'UTF-8'
);

?>"
    required
>


</div>


</div>


<div class="mb-3">


<label
    for="curso"
    class="form-label"
>

Curso

</label>


<input
    type="text"
    class="form-control"
    id="curso"
    name="curso"
    value="<?php

echo htmlspecialchars(
    $curso,
    ENT_QUOTES,
    'UTF-8'
);

?>"
>


</div>


<div class="mb-3">


<label
    for="correo"
    class="form-label"
>

Correo

<span class="text-muted">
(opcional)
</span>

</label>


<input
    type="email"
    class="form-control"
    id="correo"
    name="correo"
    value="<?php

echo htmlspecialchars(
    $correo,
    ENT_QUOTES,
    'UTF-8'
);

?>"
>


</div>


<div class="info">


<div class="mb-2">

🔐

<strong>
Contraseña:
</strong>

será automáticamente el número
de documento.

</div>


<div>

🗳️

<strong>
Mesa:
</strong>

al registrar el jurado se creará
automáticamente una nueva mesa y
quedará asignada a este jurado.

</div>


</div>


<div class="d-flex
            justify-content-between
            gap-2
            flex-wrap">


<a
    href="jurados.php"
    class="btn-volver"
>

<i class="bi bi-arrow-left"></i>

Volver

</a>


<button
    type="submit"
    name="registrar_jurado"
    value="1"
    class="btn-registrar"
>


<i class="bi bi-person-plus-fill"></i>

Registrar jurado

</button>


</div>


</form>


</div>

</div>

</div>


</body>

</html>
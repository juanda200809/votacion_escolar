<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR ID DEL CANDIDATO
========================================================= */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: candidatos.php?error=candidato_invalido");
    exit();

}


$idCandidato = (int)$_GET['id'];


if ($idCandidato <= 0) {

    header("Location: candidatos.php?error=candidato_invalido");
    exit();

}


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = "";
$tipoMensaje = "";

$nombre = "";
$apellido = "";
$curso = "";
$propuestas = "";

$fotoActual = "";

$idEleccion = 0;
$idCargo = 0;


/* =========================================================
   OBTENER CANDIDATO
========================================================= */

$stmt = $conn->prepare("

    SELECT
        c.id,
        c.nombre,
        c.apellido,
        c.curso,
        c.foto,
        c.propuestas,
        c.id_eleccion,
        c.id_cargo,
        e.nombre AS nombre_eleccion,
        e.estado AS estado_eleccion,
        ca.nombre_cargo

    FROM candidatos c

    INNER JOIN elecciones e
        ON e.id = c.id_eleccion

    INNER JOIN cargos ca
        ON ca.id = c.id_cargo

    WHERE c.id = ?

    LIMIT 1

");


if (!$stmt) {

    die("No se pudo preparar la consulta del candidato.");

}


$stmt->bind_param(
    "i",
    $idCandidato
);


$stmt->execute();


$resultado = $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header("Location: candidatos.php?error=no_encontrado");

    exit();

}


$candidato =
    $resultado->fetch_assoc();


$stmt->close();


/* =========================================================
   CARGAR DATOS
========================================================= */

$nombre =
    $candidato['nombre'];

$apellido =
    $candidato['apellido'];

$curso =
    $candidato['curso'];

$propuestas =
    $candidato['propuestas'];

$fotoActual =
    $candidato['foto'];

$idEleccion =
    (int)$candidato['id_eleccion'];

$idCargo =
    (int)$candidato['id_cargo'];


/* =========================================================
   CONTAR VOTOS
========================================================= */

$stmt = $conn->prepare("

    SELECT
        COUNT(*) AS total

    FROM votos

    WHERE id_candidato = ?

");


$stmt->bind_param(
    "i",
    $idCandidato
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$fila =
    $resultado->fetch_assoc();


$totalVotos =
    (int)$fila['total'];


$stmt->close();


/* =========================================================
   PROCESAR ACTUALIZACIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['actualizar'])
) {


    /* =====================================================
       RECIBIR DATOS
    ===================================================== */

    $nombre =
        trim(
            $_POST['nombre'] ?? ''
        );


    $apellido =
        trim(
            $_POST['apellido'] ?? ''
        );


    $curso =
        trim(
            $_POST['curso'] ?? ''
        );


    $propuestas =
        trim(
            $_POST['propuestas'] ?? ''
        );


    $idCargoNuevo =
        filter_var(
            $_POST['id_cargo'] ?? 0,
            FILTER_VALIDATE_INT
        );


    /* =====================================================
       VALIDAR CAMPOS
    ===================================================== */

    if (
        $nombre === '' ||
        $apellido === '' ||
        $curso === '' ||
        $idCargoNuevo === false ||
        $idCargoNuevo <= 0
    ) {

        $mensaje =
            "Completa correctamente todos los campos obligatorios.";

        $tipoMensaje =
            "danger";

    }


    /* =====================================================
       SI TIENE VOTOS
    ===================================================== */

    elseif (
        $totalVotos > 0 &&
        $idCargoNuevo !== $idCargo
    ) {

        $mensaje =
            "No puedes cambiar el cargo de un candidato que ya tiene votos registrados.";

        $tipoMensaje =
            "danger";

    }


    else {


        /* =================================================
           VERIFICAR CARGO
        ================================================= */

        $stmt =
            $conn->prepare("

                SELECT
                    c.id

                FROM cargos c

                INNER JOIN eleccion_cargos ec
                    ON ec.id_cargo = c.id

                WHERE ec.id_eleccion = ?

                AND c.id = ?

                LIMIT 1

            ");


        if (!$stmt) {

            $mensaje =
                "No se pudo validar el cargo.";

            $tipoMensaje =
                "danger";

        }


        else {


            $stmt->bind_param(
                "ii",
                $idEleccion,
                $idCargoNuevo
            );


            $stmt->execute();


            $resultado =
                $stmt->get_result();


            if (
                $resultado->num_rows === 0
            ) {

                $mensaje =
                    "El cargo seleccionado no pertenece a esta elección.";

                $tipoMensaje =
                    "danger";

            }


            $stmt->close();

        }


        /* =================================================
           COMPROBAR CANDIDATO DUPLICADO
        ================================================= */

        if (
            $mensaje === ''
        ) {


            $stmt =
                $conn->prepare("

                    SELECT
                        id

                    FROM candidatos

                    WHERE nombre = ?

                    AND apellido = ?

                    AND id_eleccion = ?

                    AND id_cargo = ?

                    AND id <> ?

                    LIMIT 1

                ");


            if (!$stmt) {

                $mensaje =
                    "No se pudo comprobar el candidato.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $stmt->bind_param(
                    "ssiii",
                    $nombre,
                    $apellido,
                    $idEleccion,
                    $idCargoNuevo,
                    $idCandidato
                );


                $stmt->execute();


                $resultado =
                    $stmt->get_result();


                if (
                    $resultado->num_rows > 0
                ) {

                    $mensaje =
                        "Ya existe otro candidato con ese nombre para este cargo.";

                    $tipoMensaje =
                        "danger";

                }


                $stmt->close();

            }

        }


        /* =================================================
           PROCESAR FOTO
        ================================================= */

        $nuevaFoto = $fotoActual;


        if (
            $mensaje === '' &&
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {


            if (
                $_FILES['foto']['error'] !==
                UPLOAD_ERR_OK
            ) {

                $mensaje =
                    "No se pudo cargar la nueva fotografía.";

                $tipoMensaje =
                    "danger";

            }


            elseif (
                $_FILES['foto']['size'] >
                5 * 1024 * 1024
            ) {

                $mensaje =
                    "La fotografía no puede superar los 5 MB.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $extension =
                    strtolower(
                        pathinfo(
                            $_FILES['foto']['name'],
                            PATHINFO_EXTENSION
                        )
                    );


                $permitidas = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];


                if (
                    !in_array(
                        $extension,
                        $permitidas,
                        true
                    )
                ) {

                    $mensaje =
                        "El formato de la fotografía no es válido.";

                    $tipoMensaje =
                        "danger";

                }


                else {


                    $directorio =
                        "uploads/candidatos";


                    if (
                        !is_dir($directorio)
                    ) {

                        if (
                            !mkdir(
                                $directorio,
                                0755,
                                true
                            )
                        ) {

                            $mensaje =
                                "No se pudo preparar la carpeta de fotografías.";

                            $tipoMensaje =
                                "danger";

                        }

                    }


                    if (
                        $mensaje === ''
                    ) {


                        $nuevaFoto =
                            bin2hex(
                                random_bytes(16)
                            )
                            .
                            '.'
                            .
                            $extension;


                        $rutaNueva =
                            $directorio
                            .
                            '/'
                            .
                            $nuevaFoto;


                        if (
                            !move_uploaded_file(
                                $_FILES['foto']['tmp_name'],
                                $rutaNueva
                            )
                        ) {

                            $mensaje =
                                "No se pudo guardar la nueva fotografía.";

                            $tipoMensaje =
                                "danger";

                            $nuevaFoto =
                                $fotoActual;

                        }

                    }

                }

            }

        }


        /* =================================================
           ACTUALIZAR
        ================================================= */

        if (
            $mensaje === ''
        ) {


            $stmt =
                $conn->prepare("

                    UPDATE candidatos

                    SET
                        nombre = ?,
                        apellido = ?,
                        curso = ?,
                        foto = ?,
                        propuestas = ?,
                        id_cargo = ?

                    WHERE id = ?

                ");


            if (!$stmt) {

                $mensaje =
                    "No se pudo preparar la actualización.";

                $tipoMensaje =
                    "danger";

            }


            else {


                $stmt->bind_param(
                    "sssssii",
                    $nombre,
                    $apellido,
                    $curso,
                    $nuevaFoto,
                    $propuestas,
                    $idCargoNuevo,
                    $idCandidato
                );


                if (
                    !$stmt->execute()
                ) {

                    /*
                     * Si se subió una foto nueva pero
                     * la actualización falló, eliminarla.
                     */

                    if (
                        $nuevaFoto !== $fotoActual &&
                        $nuevaFoto !== '' &&
                        file_exists(
                            "uploads/candidatos/"
                            .
                            $nuevaFoto
                        )
                    ) {

                        unlink(
                            "uploads/candidatos/"
                            .
                            $nuevaFoto
                        );

                    }


                    $mensaje =
                        "No se pudo actualizar el candidato.";

                    $tipoMensaje =
                        "danger";

                }


                else {


                    $stmt->close();


                    /*
                     * Si todo salió bien y existe una
                     * foto anterior diferente, eliminarla.
                     */

                    if (
                        $nuevaFoto !== $fotoActual &&
                        $fotoActual !== '' &&
                        file_exists(
                            "uploads/candidatos/"
                            .
                            $fotoActual
                        )
                    ) {

                        unlink(
                            "uploads/candidatos/"
                            .
                            $fotoActual
                        );

                    }


                    header(
                        "Location: candidatos.php?id_eleccion="
                        .
                        $idEleccion
                        .
                        "&actualizado=1"
                    );

                    exit();

                }


                $stmt->close();

            }

        }

    }

}


/* =========================================================
   CARGOS DE LA ELECCIÓN
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            c.id,
            c.nombre_cargo

        FROM cargos c

        INNER JOIN eleccion_cargos ec
            ON ec.id_cargo = c.id

        WHERE ec.id_eleccion = ?

        ORDER BY c.nombre_cargo ASC

    ");


$stmt->bind_param(
    "i",
    $idEleccion
);


$stmt->execute();


$cargos =
    $stmt->get_result();


$stmt->close();


?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Editar Candidato
</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
rel="stylesheet">


<style>

body {

    background: #eef3f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


.contenedor {

    max-width: 850px;

    margin: auto;

    padding: 35px 20px;

}


.card {

    border: none;

    border-radius: 18px;

    box-shadow:
        0 7px 25px
        rgba(0,0,0,.10);

}


.encabezado {

    background: #1453a3;

    color: white;

    padding: 25px;

    border-radius:
        18px 18px 0 0;

}


.foto {

    width: 130px;

    height: 130px;

    object-fit: cover;

    border-radius: 50%;

    border: 4px solid white;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.2);

}


.sin-foto {

    width: 130px;

    height: 130px;

    border-radius: 50%;

    background: #e9ecef;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 50px;

    color: #6c757d;

}


.form-label {

    font-weight: bold;

}


.btn {

    border-radius: 9px;

    font-weight: bold;

}

</style>

</head>


<body>


<div class="contenedor">


<div class="card">


<div class="encabezado">


<h2 class="mb-1">

<i class="bi bi-pencil-square"></i>

Editar candidato

</h2>


<p class="mb-0">

<?php

echo htmlspecialchars(
    $candidato['nombre_eleccion']
);

?>

</p>


</div>


<div class="card-body p-4">


<?php if (
    $mensaje !== ''
) { ?>


<div
class="alert alert-<?php

echo htmlspecialchars(
    $tipoMensaje
);

?>
alert-dismissible fade show">


<i class="bi bi-info-circle-fill"></i>

<?php

echo htmlspecialchars(
    $mensaje
);

?>


<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>


</div>


<?php } ?>


<?php if (
    $totalVotos > 0
) { ?>


<div class="alert alert-warning">


<i class="bi bi-shield-exclamation"></i>


<strong>
Este candidato tiene
<?php echo $totalVotos; ?>
voto(s).
</strong>


<br>


El cargo no puede modificarse porque
existen votos registrados.


</div>


<?php } ?>


<div class="text-center mb-4">


<?php if (
    $fotoActual !== '' &&
    file_exists(
        "uploads/candidatos/"
        .
        $fotoActual
    )
) { ?>


<img

src="uploads/candidatos/<?php

echo htmlspecialchars(
    $fotoActual
);

?>"

class="foto"

alt="Fotografía del candidato">


<?php } else { ?>


<div class="sin-foto mx-auto">

<i class="bi bi-person-fill"></i>

</div>


<?php } ?>


</div>


<form
method="POST"
enctype="multipart/form-data">


<div class="row">


<div class="col-md-6 mb-3">


<label
class="form-label">

Nombre

</label>


<input

type="text"

name="nombre"

class="form-control"

maxlength="100"

required

value="<?php

echo htmlspecialchars(
    $nombre
);

?>">


</div>


<div class="col-md-6 mb-3">


<label
class="form-label">

Apellido

</label>


<input

type="text"

name="apellido"

class="form-control"

maxlength="100"

required

value="<?php

echo htmlspecialchars(
    $apellido
);

?>">


</div>


</div>


<div class="row">


<div class="col-md-6 mb-3">


<label
class="form-label">

Curso

</label>


<input

type="text"

name="curso"

class="form-control"

maxlength="20"

required

value="<?php

echo htmlspecialchars(
    $curso
);

?>">


</div>


<div class="col-md-6 mb-3">


<label
class="form-label">

Cargo

</label>


<select

name="id_cargo"

class="form-select"

required

<?php

if (
    $totalVotos > 0
) {

    echo "disabled";

}

?>


>


<?php while (
    $cargo =
    $cargos->fetch_assoc()
) { ?>


<option

value="<?php

echo (int)$cargo['id'];

?>"

<?php

if (
    (int)$cargo['id'] ===
    $idCargo
) {

    echo "selected";

}

?>

>

<?php

echo htmlspecialchars(
    $cargo['nombre_cargo']
);

?>

</option>


<?php } ?>


</select>


<?php if (
    $totalVotos > 0
) { ?>


<input
type="hidden"
name="id_cargo"
value="<?php

echo $idCargo;

?>">


<?php } ?>


</div>


</div>


<div class="mb-3">


<label
class="form-label">

Propuestas

</label>


<textarea

name="propuestas"

class="form-control"

rows="6"

maxlength="500"><?php

echo htmlspecialchars(
    $propuestas
);

?></textarea>


</div>


<div class="mb-4">


<label
class="form-label">

Cambiar fotografía

</label>


<input

type="file"

name="foto"

class="form-control"

accept=".jpg,.jpeg,.png,.webp">


<small class="text-muted">

Déjalo vacío si deseas conservar la fotografía actual.

Máximo 5 MB.

</small>


</div>


<div class="d-flex
            justify-content-between
            gap-2
            flex-wrap">


<a

href="candidatos.php?id_eleccion=<?php

echo $idEleccion;

?>"

class="btn btn-outline-secondary">


<i class="bi bi-arrow-left"></i>

Cancelar


</a>


<button

type="submit"

name="actualizar"

class="btn btn-primary">


<i class="bi bi-check-circle-fill"></i>

Guardar cambios


</button>


</div>


</form>


</div>

</div>

</div>


</body>

</html>
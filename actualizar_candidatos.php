<?php

/* =========================================================
   SEGURIDAD
========================================================= */

require_once "seguridad.php";

evitarCache();
verificarRol(['administrador']);

require_once "config/conexion.php";


/* =========================================================
   VERIFICAR MÉTODO
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    header("Location: candidatos.php");
    exit();

}


/* =========================================================
   VERIFICAR ID DEL CANDIDATO
========================================================= */

if (
    !isset($_POST['id']) ||
    !is_numeric($_POST['id'])
) {

    header(
        "Location: candidatos.php?error=candidato_invalido"
    );

    exit();

}


$idCandidato =
    (int)$_POST['id'];


if (
    $idCandidato <= 0
) {

    header(
        "Location: candidatos.php?error=candidato_invalido"
    );

    exit();

}


/* =========================================================
   RECIBIR DATOS
========================================================= */

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


$idCargo =
    filter_var(
        $_POST['id_cargo'] ?? 0,
        FILTER_VALIDATE_INT
    );


/* =========================================================
   VALIDACIÓN BÁSICA
========================================================= */

if (
    $nombre === '' ||
    $apellido === '' ||
    $curso === '' ||
    $idCargo === false ||
    $idCargo <= 0
) {

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=datos"
    );

    exit();

}


/* =========================================================
   OBTENER CANDIDATO ACTUAL
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            id,
            nombre,
            apellido,
            curso,
            foto,
            propuestas,
            id_eleccion,
            id_cargo

        FROM candidatos

        WHERE id = ?

        LIMIT 1

    ");


if (!$stmt) {

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=consulta"
    );

    exit();

}


$stmt->bind_param(
    "i",
    $idCandidato
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header(
        "Location: candidatos.php?error=no_encontrado"
    );

    exit();

}


$candidato =
    $resultado->fetch_assoc();


$stmt->close();


$idEleccion =
    (int)$candidato['id_eleccion'];


$cargoAnterior =
    (int)$candidato['id_cargo'];


$fotoActual =
    $candidato['foto'];


/* =========================================================
   CONTAR VOTOS DEL CANDIDATO
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            COUNT(*) AS total

        FROM votos

        WHERE id_candidato = ?

    ");


if (!$stmt) {

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=consulta"
    );

    exit();

}


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
   NO CAMBIAR CARGO SI YA TIENE VOTOS
========================================================= */

if (
    $totalVotos > 0 &&
    $idCargo !== $cargoAnterior
) {

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=cargo_bloqueado"
    );

    exit();

}


/* =========================================================
   VERIFICAR QUE EL CARGO PERTENEZCA A LA ELECCIÓN
========================================================= */

$stmt =
    $conn->prepare("

        SELECT
            id

        FROM eleccion_cargos

        WHERE id_eleccion = ?

        AND id_cargo = ?

        LIMIT 1

    ");


if (!$stmt) {

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=consulta"
    );

    exit();

}


$stmt->bind_param(
    "ii",
    $idEleccion,
    $idCargo
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows === 0
) {

    $stmt->close();

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=cargo_invalido"
    );

    exit();

}


$stmt->close();


/* =========================================================
   COMPROBAR CANDIDATO DUPLICADO
========================================================= */

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

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=consulta"
    );

    exit();

}


$stmt->bind_param(
    "ssiii",
    $nombre,
    $apellido,
    $idEleccion,
    $idCargo,
    $idCandidato
);


$stmt->execute();


$resultado =
    $stmt->get_result();


if (
    $resultado->num_rows > 0
) {

    $stmt->close();

    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=duplicado"
    );

    exit();

}


$stmt->close();


/* =========================================================
   FOTO
========================================================= */

$nuevaFoto =
    $fotoActual;


$fotoNuevaSubida =
    false;


if (
    isset($_FILES['foto']) &&
    $_FILES['foto']['error'] !==
    UPLOAD_ERR_NO_FILE
) {


    if (
        $_FILES['foto']['error'] !==
        UPLOAD_ERR_OK
    ) {

        header(
            "Location: editar_candidato.php?id="
            .
            $idCandidato
            .
            "&error=foto"
        );

        exit();

    }


    /* =====================================================
       TAMAÑO
    ===================================================== */

    if (
        $_FILES['foto']['size'] >
        5 * 1024 * 1024
    ) {

        header(
            "Location: editar_candidato.php?id="
            .
            $idCandidato
            .
            "&error=foto_grande"
        );

        exit();

    }


    /* =====================================================
       COMPROBAR MIME REAL
    ===================================================== */

    $tipoMime =
        mime_content_type(
            $_FILES['foto']['tmp_name']
        );


    $permitidosMime = [

        'image/jpeg',
        'image/png',
        'image/webp'

    ];


    if (
        !in_array(
            $tipoMime,
            $permitidosMime,
            true
        )
    ) {

        header(
            "Location: editar_candidato.php?id="
            .
            $idCandidato
            .
            "&error=formato_foto"
        );

        exit();

    }


    /* =====================================================
       EXTENSIÓN
    ===================================================== */

    $extension =
        strtolower(
            pathinfo(
                $_FILES['foto']['name'],
                PATHINFO_EXTENSION
            )
        );


    $extensionesPermitidas = [

        'jpg',
        'jpeg',
        'png',
        'webp'

    ];


    if (
        !in_array(
            $extension,
            $extensionesPermitidas,
            true
        )
    ) {

        header(
            "Location: editar_candidato.php?id="
            .
            $idCandidato
            .
            "&error=formato_foto"
        );

        exit();

    }


    /* =====================================================
       CARPETA
    ===================================================== */

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

            header(
                "Location: editar_candidato.php?id="
                .
                $idCandidato
                .
                "&error=carpeta"
            );

            exit();

        }

    }


    /* =====================================================
       NOMBRE SEGURO
    ===================================================== */

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


    /* =====================================================
       GUARDAR FOTO
    ===================================================== */

    if (
        !move_uploaded_file(
            $_FILES['foto']['tmp_name'],
            $rutaNueva
        )
    ) {

        header(
            "Location: editar_candidato.php?id="
            .
            $idCandidato
            .
            "&error=foto"
        );

        exit();

    }


    $fotoNuevaSubida =
        true;

}


/* =========================================================
   ACTUALIZAR CANDIDATO
========================================================= */

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

    /*
     * Si se subió una nueva foto y la consulta
     * no pudo prepararse, eliminamos la foto nueva.
     */

    if (
        $fotoNuevaSubida &&
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


    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=actualizar"
    );

    exit();

}


$stmt->bind_param(
    "sssssii",
    $nombre,
    $apellido,
    $curso,
    $nuevaFoto,
    $propuestas,
    $idCargo,
    $idCandidato
);


if (
    !$stmt->execute()
) {

    $stmt->close();


    /*
     * Si la actualización falla,
     * eliminamos la nueva foto.
     */

    if (
        $fotoNuevaSubida &&
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


    header(
        "Location: editar_candidato.php?id="
        .
        $idCandidato
        .
        "&error=actualizar"
    );

    exit();

}


$stmt->close();


/* =========================================================
   ELIMINAR FOTO ANTERIOR
========================================================= */

if (
    $fotoNuevaSubida &&
    $fotoActual !== '' &&
    $fotoActual !== $nuevaFoto
) {

    $rutaAnterior =
        "uploads/candidatos/"
        .
        $fotoActual;


    if (
        file_exists(
            $rutaAnterior
        )
    ) {

        unlink(
            $rutaAnterior
        );

    }

}


/* =========================================================
   FINALIZAR
========================================================= */

header(
    "Location: candidatos.php?id_eleccion="
    .
    $idEleccion
    .
    "&actualizado=1"
);

exit();

?>
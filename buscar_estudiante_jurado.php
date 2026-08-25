<?php

session_start();

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
   VERIFICAR ROL DE JURADO
========================================================= */

$rol = strtolower(
    trim(
        (string)$_SESSION['rol']
    )
);


if ($rol !== "jurado") {

    header("Location: login.php");
    exit();

}


/* =========================================================
   VERIFICAR ELECCIÓN ACTUAL
========================================================= */

$consultaEleccion = $conn->query("

    SELECT
        id,
        nombre,
        estado

    FROM elecciones

    ORDER BY id DESC

    LIMIT 1

");


if (
    !$consultaEleccion ||
    $consultaEleccion->num_rows === 0
) {

    header("Location: jurado.php");
    exit();

}


$eleccion =
    $consultaEleccion->fetch_assoc();


$idEleccion =
    (int)$eleccion['id'];


/* =========================================================
   VERIFICAR QUE LA ELECCIÓN ESTÉ ABIERTA
========================================================= */

if (
    strtolower(
        trim(
            (string)$eleccion['estado']
        )
    ) !== "abierta"
) {

    header("Location: jurado.php");
    exit();

}


/* =========================================================
   OBTENER BÚSQUEDA
========================================================= */

$busqueda =
    trim(
        $_GET['buscar'] ?? ""
    );


/* =========================================================
   SI NO HAY BÚSQUEDA
========================================================= */

if ($busqueda === "") {

    header("Location: jurado.php");
    exit();

}


/* =========================================================
   BUSCAR ESTUDIANTE
========================================================= */

/*
 * Se busca por:
 *
 * - Documento
 * - Nombre
 * - Apellido
 *
 * Solamente estudiantes.
 */

$termino =
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

        OR CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) LIKE ?

    )

    ORDER BY
        u.nombre ASC,
        u.apellido ASC

    LIMIT 20

");


if (!$stmt) {

    die(
        "Error al preparar la búsqueda: "
        .
        $conn->error
    );

}


$stmt->bind_param(
    "issss",
    $idEleccion,
    $termino,
    $termino,
    $termino,
    $termino
);


if (
    !$stmt->execute()
) {

    die(
        "Error al realizar la búsqueda: "
        .
        $stmt->error
    );

}


$resultado =
    $stmt->get_result();


/* =========================================================
   MOSTRAR RESULTADOS
========================================================= */

?>

<?php if (
    $resultado->num_rows === 0
) { ?>

<div class="resultado-vacio">

    <div class="resultado-icono">

        <i class="bi bi-person-x"></i>

    </div>

    <div>

        <h5>

            No se encontró ningún estudiante

        </h5>

        <p>

            Verifica el nombre o número de documento
            e intenta nuevamente.

        </p>

    </div>

</div>

<?php } else { ?>


<div class="lista-estudiantes">


<?php while (
    $estudiante =
    $resultado->fetch_assoc()
) { ?>


<div class="resultado-estudiante">


    <!-- =================================================
         INFORMACIÓN
    ================================================== -->

    <div class="informacion-estudiante">


        <div class="avatar-estudiante">

            <i class="bi bi-person-fill"></i>

        </div>


        <div>

            <h5>

                <?php

                echo htmlspecialchars(
                    $estudiante['nombre']
                    .
                    " "
                    .
                    $estudiante['apellido']
                );

                ?>

            </h5>


            <div class="datos-estudiante">

                <span>

                    <i class="bi bi-card-text"></i>

                    Documento:

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $estudiante['documento']
                        );

                        ?>

                    </strong>

                </span>


                <span>

                    <i class="bi bi-mortarboard-fill"></i>

                    Curso:

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $estudiante['curso']
                        );

                        ?>

                    </strong>

                </span>

            </div>

        </div>

    </div>


    <!-- =================================================
         ESTADO
    ================================================== -->

    <div class="estado-estudiante">


    <?php if (
        (int)$estudiante['ya_voto'] === 1
    ) { ?>


        <div class="estado-bloqueado">

            <i class="bi bi-lock-fill"></i>

            <div>

                <strong>

                    Ya realizó su votación

                </strong>

                <small>

                    Este estudiante no puede votar nuevamente.

                </small>

            </div>

        </div>


    <?php } else { ?>


        <div class="estado-disponible">

            <i class="bi bi-check-circle-fill"></i>

            <div>

                <strong>

                    Disponible para votar

                </strong>

                <small>

                    El estudiante aún no ha votado.

                </small>

            </div>

        </div>


        <a

            href="ingresar_estudiante.php?documento=<?php

                echo urlencode(
                    $estudiante['documento']
                );

            ?>"

            class="btn-iniciar-votacion"

        >

            <i class="bi bi-play-fill"></i>

            Iniciar votación

        </a>


    <?php } ?>


    </div>


</div>


<?php } ?>


</div>


<?php } ?>


<?php

$stmt->close();

?>
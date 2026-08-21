<?php

session_start();

/* =========================================
   VERIFICAR ADMINISTRADOR
========================================= */

if (
    !isset($_SESSION['id']) ||
    $_SESSION['rol'] != 'administrador'
) {

    header("Location: login.php");
    exit();

}


/* =========================================
   CONEXIÓN
========================================= */

include("config/conexion.php");


/* =========================================
   CONFIGURAR ARCHIVO EXCEL
========================================= */

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=estudiantes_votaciones.xls"
);

header("Pragma: no-cache");

header("Expires: 0");


/* =========================================
   CONSULTAR ESTUDIANTES
========================================= */

$sql = $conn->query("

    SELECT

        id,
        documento,
        nombre,
        apellido,
        correo,
        curso

    FROM usuarios

    WHERE rol = 'estudiante'

    ORDER BY nombre ASC

");


/* =========================================
   ENCABEZADO DEL EXCEL
========================================= */

echo "\xEF\xBB\xBF";

?>

<table border="1">

<tr>

    <th colspan="6">

        LISTA DE ESTUDIANTES

    </th>

</tr>


<tr>

    <th>ID</th>

    <th>DOCUMENTO</th>

    <th>NOMBRE</th>

    <th>APELLIDO</th>

    <th>CORREO</th>

    <th>CURSO</th>

</tr>


<?php

/* =========================================
   MOSTRAR ESTUDIANTES
========================================= */

while ($estudiante = $sql->fetch_assoc()) {

?>

<tr>

    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['id']
        );

        ?>

    </td>


    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['documento']
        );

        ?>

    </td>


    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['nombre']
        );

        ?>

    </td>


    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['apellido']
        );

        ?>

    </td>


    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['correo']
        );

        ?>

    </td>


    <td>

        <?php

        echo htmlspecialchars(
            $estudiante['curso']
        );

        ?>

    </td>

</tr>

<?php

}

?>

</table>
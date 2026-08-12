<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['rol'] != 'administrador') {
    header("Location: login.php");
    exit();
}

include("config/conexion.php");

if(isset($_POST['id'])){

    $id = (int)$_POST['id'];

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $curso = trim($_POST['curso']);
    $tarjeton = (int)$_POST['tarjeton'];
    $propuestas = trim($_POST['propuestas']);
    $id_cargo = (int)$_POST['id_cargo'];

    // Buscar foto actual
    $consulta = $conn->query("SELECT foto FROM candidatos WHERE id=$id");
    $datos = $consulta->fetch_assoc();

    $foto = $datos['foto'];

    // Si se seleccionó una nueva foto
    if(isset($_FILES['foto']) && $_FILES['foto']['error']==0){

        // Eliminar foto anterior
        if($foto != "" && file_exists("uploads/candidatos/".$foto)){
            unlink("uploads/candidatos/".$foto);
        }

        $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        $permitidas = array("jpg","jpeg","png","webp");

        if(in_array($extension,$permitidas)){

            $foto = time().rand(1000,9999).".".$extension;

            move_uploaded_file(
                $_FILES['foto']['tmp_name'],
                "uploads/candidatos/".$foto
            );

        }

    }

    $sql = "

    UPDATE candidatos SET

    nombre='$nombre',
    apellido='$apellido',
    curso='$curso',
    numero_tarjeton='$tarjeton',
    propuestas='$propuestas',
    foto='$foto',
    id_cargo='$id_cargo'

    WHERE id=$id

    ";

    if($conn->query($sql)){

        header("Location: candidatos.php?actualizado=1");

    }else{

        echo "Error al actualizar.";

    }

}
?>
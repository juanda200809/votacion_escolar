<?php

session_start();

/* =========================================================
   ELIMINAR DATOS TEMPORALES DEL ESTUDIANTE
========================================================= */

unset(
    $_SESSION['estudiante_votando_id'],
    $_SESSION['estudiante_votando_documento'],
    $_SESSION['estudiante_votando_nombre']
);


/* =========================================================
   CERRAR SESIÓN COMPLETAMENTE
========================================================= */

$_SESSION = [];


/* =========================================================
   ELIMINAR COOKIE DE SESIÓN
========================================================= */

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


/* =========================================================
   DESTRUIR SESIÓN
========================================================= */

session_destroy();


/* =========================================================
   VOLVER AL LOGIN
========================================================= */

header("Location: login.php");
exit();

?>
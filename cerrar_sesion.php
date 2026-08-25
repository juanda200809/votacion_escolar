<?php

session_start();

/*
|--------------------------------------------------------------------------
| CERRAR SESIÓN
|--------------------------------------------------------------------------
| Destruye completamente la sesión actual y devuelve al usuario
| a la página de inicio de sesión.
|--------------------------------------------------------------------------
*/

// Vaciar todas las variables de sesión
$_SESSION = [];

// Eliminar la cookie de sesión si existe
if (ini_get("session.use_cookies")) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();

?>
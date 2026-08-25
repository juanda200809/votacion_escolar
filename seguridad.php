<?php
/*
|--------------------------------------------------------------------------
| SEGURIDAD DEL SISTEMA
|--------------------------------------------------------------------------
| Este archivo protege las páginas internas.
| IMPORTANTE: login.php NO debe incluir verificarRol().
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| EVITAR CACHÉ
|--------------------------------------------------------------------------
*/
function evitarCache()
{
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}


/*
|--------------------------------------------------------------------------
| COMPROBAR SESIÓN
|--------------------------------------------------------------------------
*/
function verificarSesion()
{
    if (
        !isset($_SESSION['id']) ||
        empty($_SESSION['id']) ||
        !isset($_SESSION['rol']) ||
        empty($_SESSION['rol'])
    ) {
        header("Location: login.php");
        exit();
    }
}


/*
|--------------------------------------------------------------------------
| COMPROBAR ROL
|--------------------------------------------------------------------------
*/
function verificarRol($rolesPermitidos = [])
{
    verificarSesion();

    $rolUsuario = $_SESSION['rol'];

    if (!in_array($rolUsuario, $rolesPermitidos, true)) {

        // Administrador
        if ($rolUsuario === 'administrador') {
            header("Location: admin.php");
            exit();
        }

        // Jurado
        if ($rolUsuario === 'jurado') {
            header("Location: jurado.php");
            exit();
        }

        // Estudiante
        if ($rolUsuario === 'estudiante') {
            header("Location: votar.php");
            exit();
        }

        // Rol desconocido
        $_SESSION = [];

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

        session_destroy();

        header("Location: login.php");
        exit();
    }
}
?>
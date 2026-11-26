<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_role'],
    $_SESSION['admin_name'],
    $_SESSION['impersonator_admin'],
    $_SESSION['pending_admin_id'],
    $_SESSION['pending_admin_username'],
    $_SESSION['admin_pin_failures'],
    $_SESSION['admin_pin_locked_until']
);

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

@session_destroy();
header("Location: login.php");
exit;

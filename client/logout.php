<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
session_start();
unset($_SESSION['client_logged_in']);
unset($_SESSION['client_portal_id']);
unset($_SESSION['client_name']);
session_destroy();
header("Location: login.php");
exit;
?>
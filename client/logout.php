<?php
session_start();
unset($_SESSION['client_logged_in']);
unset($_SESSION['client_portal_id']);
unset($_SESSION['client_name']);
session_destroy();
header("Location: login.php");
exit;
?>





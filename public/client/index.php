<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['client_logged_in']) && !empty($_SESSION['client_portal_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;

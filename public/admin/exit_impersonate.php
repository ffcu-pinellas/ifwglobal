<?php
// admin/exit_impersonate.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['impersonator_admin'])) {
    $admin = $_SESSION['impersonator_admin'];
    $admin_id = (int)$admin['id'];

    // Restore Admin Session State
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
    $_SESSION['admin_pin_verified'] = true;

    // Log impersonation exit in audit log
    if (isset($pdo)) {
        log_audit_action($pdo, $admin_id, 'EXIT_IMPERSONATE', "Super Admin exited impersonation session and returned to Admin portal.");
    }

    // Clean up impersonation & client session vars
    unset($_SESSION['impersonator_admin']);
    unset($_SESSION['is_impersonating']);
    unset($_SESSION['client_logged_in']);
    unset($_SESSION['client_portal_id']);
    unset($_SESSION['client_id']);
    unset($_SESSION['client_name']);
    unset($_SESSION['client_email']);
    unset($_SESSION['pin_verified']);
    unset($_SESSION['2fa_verified']);
    unset($_SESSION['role']);

    header("Location: " . BASE_URL . "/admin/client_manager.php?impersonation_ended=1");
    exit;
}

header("Location: " . BASE_URL . "/admin/dashboard.php");
exit;

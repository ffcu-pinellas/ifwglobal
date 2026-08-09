<?php
// api/mark_notifications_read.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? '';
$response = ['success' => false];

if ($user_role === 'client' && isset($_SESSION['client_portal_id'])) {
    $c_id = $_SESSION['client_portal_id'];
    $stmt = $pdo->prepare("UPDATE IFW_notifications SET is_read = 1 WHERE client_id = ?");
    $stmt->execute([$c_id]);
    $response['success'] = true;
} elseif (!empty($user_role) && in_array($user_role, ['admin', 'superadmin', 'agent', 'staff'])) {
    $stmt = $pdo->prepare("UPDATE IFW_notifications SET is_read = 1 WHERE link LIKE '/admin/%'");
    $stmt->execute();
    $response['success'] = true;
}

header('Content-Type: application/json');
echo json_encode($response);
exit;

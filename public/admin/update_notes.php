<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? 0;
    $note = trim($_POST['private_notes'] ?? '');
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;
    $agent_id = $_SESSION['admin_id'] ?? 1;
    $user_role = $_SESSION['admin_role'] ?? 'viewer';
    
    if ($client_id > 0 && !empty($note)) {
        if (!in_array($user_role, ['super_admin', 'superadmin', 'admin'])) {
            $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
            $check->execute([$client_id, $agent_id]);
            if (!$check->fetch()) {
                die("Unauthorized");
            }
        }
        $stmt = $pdo->prepare("INSERT INTO IFW_case_notes (client_id, agent_id, note_text, is_visible_to_client) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $agent_id, $note, $is_visible]);
    }
    
    header("Location: chat.php?client_id=" . $client_id);
    exit;
}
?>





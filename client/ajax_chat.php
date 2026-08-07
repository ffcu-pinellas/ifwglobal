<?php
// client/ajax_chat.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_logged_in']) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$client_id = $_SESSION['client_portal_id'] ?? 0;
if (!$client_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid client session']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    $stmt = $pdo->prepare("SELECT * FROM IFW_chat_messages WHERE client_id = ? AND id > ? ORDER BY created_at ASC");
    $stmt->execute([$client_id, $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark admin messages as read
    if (!empty($messages)) {
        $unread_ids = [];
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'admin' && $msg['is_read'] == 0) {
                $unread_ids[] = $msg['id'];
            }
        }
        if (!empty($unread_ids)) {
            $in_qs = str_repeat('?,', count($unread_ids) - 1) . '?';
            $update_stmt = $pdo->prepare("UPDATE IFW_chat_messages SET is_read = 1 WHERE id IN ($in_qs)");
            $update_stmt->execute($unread_ids);
        }
    }

    echo json_encode(['status' => 'success', 'messages' => $messages]);
    exit;
}

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        echo json_encode(['status' => 'error', 'message' => 'Empty message']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO IFW_chat_messages (client_id, sender_type, sender_id, message) VALUES (?, 'client', ?, ?)");
    if ($stmt->execute([$client_id, $client_id, $message])) {
        echo json_encode(['status' => 'success', 'inserted_id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

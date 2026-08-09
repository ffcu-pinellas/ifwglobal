<?php
// admin/ajax_chat.php
require_once '../config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_role = $_SESSION['admin_role'] ?? 'viewer';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch_clients') {
    // Admins see all clients, staff see assigned clients
    if ($admin_role === 'super_admin' || $admin_role === 'admin') {
        $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM IFW_clients ORDER BY first_name ASC");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM IFW_clients WHERE assigned_agent_id = ? ORDER BY first_name ASC");
        $stmt->execute([$admin_id]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get unread counts
    $unread_counts = [];
    if (!empty($clients)) {
        $ids = array_column($clients, 'id');
        $in_qs = str_repeat('?,', count($ids) - 1) . '?';
        
        $unread_stmt = $pdo->prepare("SELECT client_id, COUNT(*) as count FROM IFW_chat_messages WHERE sender_type = 'client' AND is_read = 0 AND client_id IN ($in_qs) GROUP BY client_id");
        $unread_stmt->execute($ids);
        while ($row = $unread_stmt->fetch(PDO::FETCH_ASSOC)) {
            $unread_counts[$row['client_id']] = (int)$row['count'];
        }
    }

    foreach ($clients as &$client) {
        $client['unread'] = $unread_counts[$client['id']] ?? 0;
    }

    echo json_encode(['status' => 'success', 'clients' => $clients]);
    exit;
}

if ($action === 'fetch_messages') {
    $client_id = (int)($_GET['client_id'] ?? 0);
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    // Security check: If not admin, ensure they are assigned
    if ($admin_role !== 'super_admin' && $admin_role !== 'admin') {
        $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
        $check->execute([$client_id, $admin_id]);
        if (!$check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS sender_name, u.role AS sender_role
        FROM IFW_chat_messages m
        LEFT JOIN IFW_users u ON m.sender_id = u.id AND m.sender_type = 'admin'
        WHERE m.client_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$client_id, $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark client messages as read
    if (!empty($messages)) {
        $unread_ids = [];
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'client' && $msg['is_read'] == 0) {
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
    $client_id = (int)($_POST['client_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $has_file = isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK;
    
    if (($message === '' && !$has_file) || !$client_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }

    // Security check
    if ($admin_role !== 'super_admin' && $admin_role !== 'admin') {
        $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
        $check->execute([$client_id, $admin_id]);
        if (!$check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
    }

    $attachment_path = null;
    $attachment_name = null;
    $attachment_size = 0;
    
    if ($has_file) {
        $file = $_FILES['chat_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'zip'];
        
        if (in_array($ext, $allowed)) {
            $base_dir = dirname(__DIR__);
            $target_dir = is_dir($base_dir . '/public') ? $base_dir . '/public/uploads/chat/' : $base_dir . '/uploads/chat/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $filename = uniqid('chat_') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $target_dir . $filename)) {
                $attachment_path = 'uploads/chat/' . $filename;
                $attachment_name = $file['name'];
                $attachment_size = $file['size'];
                if (empty($message)) {
                    $message = "Shared a file: " . $file['name'];
                }
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO IFW_chat_messages (client_id, sender_type, sender_id, message, attachment_path, attachment_name, attachment_size) VALUES (?, 'admin', ?, ?, ?, ?, ?)");
    if ($stmt->execute([$client_id, $admin_id, $message, $attachment_path, $attachment_name, $attachment_size])) {
        // Also insert a notification for client
        try {
            $stmt_notif = $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'message', 'New Message from Support', ?, 'chat', '/client/chat.php')");
            $stmt_notif->execute([$client_id, substr($message, 0, 100)]);
        } catch(Exception $e) {}

        echo json_encode(['status' => 'success', 'inserted_id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

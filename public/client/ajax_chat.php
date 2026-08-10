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

    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE 
                   WHEN m.sender_type = 'admin' THEN 
                       CASE 
                           WHEN u.full_name IS NOT NULL AND u.full_name != '' AND u.full_name != 'admin' THEN u.full_name
                           WHEN u.username = 'Gary009' THEN 'Gary Livingston'
                           WHEN u.username = 'admin' THEN 'IFW Case Management Desk'
                           ELSE COALESCE(NULLIF(u.full_name, ''), u.username, 'IFW Legal & Forensic Team')
                       END
                   ELSE CONCAT(c.first_name, ' ', c.last_name) 
               END AS sender_name, 
               CASE 
                   WHEN m.sender_type = 'admin' THEN 
                       CASE 
                           WHEN u.role = 'superadmin' THEN 'Senior Case Supervisor'
                           WHEN u.role = 'admin' THEN 'Case Supervisor'
                           WHEN u.role = 'agent' OR u.role = 'investigator' THEN 'Senior Investigator'
                           WHEN u.role = 'staff' THEN 'Case Officer'
                           WHEN u.role IS NOT NULL AND u.role != '' THEN u.role
                           ELSE 'Senior Case Officer'
                       END
                   ELSE 'Client' 
               END AS sender_role
        FROM IFW_chat_messages m
        LEFT JOIN IFW_users u ON m.sender_id = u.id AND m.sender_type = 'admin'
        LEFT JOIN IFW_clients c ON m.client_id = c.id AND m.sender_type = 'client'
        WHERE m.client_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
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
    $has_file = isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK;
    
    if ($message === '' && !$has_file) {
        echo json_encode(['status' => 'error', 'message' => 'Empty message']);
        exit;
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
            $target_dir = $base_dir . '/uploads/chat/';
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

    $stmt = $pdo->prepare("INSERT INTO IFW_chat_messages (client_id, sender_type, sender_id, message, attachment_path, attachment_name, attachment_size) VALUES (?, 'client', ?, ?, ?, ?, ?)");
    if ($stmt->execute([$client_id, $client_id, $message, $attachment_path, $attachment_name, $attachment_size])) {
        // Also insert a notification for the assigned agent or admin
        try {
            // Find assigned agent
            $agt_stmt = $pdo->prepare("SELECT assigned_agent_id FROM IFW_clients WHERE id = ?");
            $agt_stmt->execute([$client_id]);
            $agt_id = (int)($agt_stmt->fetchColumn() ?? 0);
            
            // Get client name
            $c_stmt = $pdo->prepare("SELECT first_name, last_name FROM IFW_clients WHERE id = ?");
            $c_stmt->execute([$client_id]);
            $c_info = $c_stmt->fetch();
            $c_name = $c_info ? ($c_info['first_name'] . ' ' . $c_info['last_name']) : "Client #{$client_id}";
            
            if ($agt_id > 0) {
                $stmt_notif = $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'message', 'New Client Message', ?, 'chat', '/admin/chat.php')");
                $stmt_notif->execute([-$agt_id, "New message from {$c_name}"]);
            } else {
                $admins = $pdo->query("SELECT id FROM IFW_users WHERE role IN ('admin','superadmin')")->fetchAll();
                foreach ($admins as $a) {
                    $stmt_notif = $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'message', 'New Client Message', ?, 'chat', '/admin/chat.php')");
                    $stmt_notif->execute([-$a['id'], "New message from {$c_name}"]);
                }
            }
        } catch(Exception $e) {}

        echo json_encode(['status' => 'success', 'inserted_id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

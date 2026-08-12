<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$is_typing = isset($input['is_typing']) ? (int)$input['is_typing'] : 0;
$client_id = 0;

$user_type = null;
$user_id = null;

if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    $user_type = 'client';
    $user_id = $_SESSION['client_portal_id'];
    $client_id = $user_id;
} elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
    $client_id = isset($_SESSION['active_chat_client_id']) ? $_SESSION['active_chat_client_id'] : (isset($input['client_id']) ? (int)$input['client_id'] : 0);
}

if (!$user_type || !$user_id || !$client_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or missing client_id']);
    exit;
}

// 1. Update this user's presence/typing status
$stmt = $pdo->prepare("
    INSERT INTO IFW_chat_status (user_type, user_id, is_typing, is_online, last_ping) 
    VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP) 
    ON DUPLICATE KEY UPDATE is_typing = ?, is_online = 1, last_ping = CURRENT_TIMESTAMP
");
$stmt->execute([$user_type, $user_id, $is_typing, $is_typing]);

// 2. Mark incoming messages as read
$sender_to_mark = ($user_type === 'client') ? 'admin' : 'client';
$stmt = $pdo->prepare("UPDATE IFW_messages SET is_read = 1 WHERE client_id = ? AND sender = ? AND is_read = 0");
$stmt->execute([$client_id, $sender_to_mark]);

// 3. Get the other party's status
$other_type = ($user_type === 'client') ? 'admin' : 'client';
$other_typing = 0;
$other_online = 0;

if ($user_type === 'client') {
    // Find assigned agent
    $stmt = $pdo->prepare("SELECT assigned_agent_id FROM IFW_clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $agent_id = $stmt->fetchColumn();
    
    if ($agent_id) {
        $stmt = $pdo->prepare("SELECT is_typing, is_online, last_ping FROM IFW_chat_status WHERE user_type = 'admin' AND user_id = ?");
        $stmt->execute([$agent_id]);
        $status = $stmt->fetch();
        if ($status) {
            $other_typing = $status['is_typing'];
            $other_online = (strtotime($status['last_ping']) > time() - 30) ? 1 : 0; // Online if pinged in last 30s
        }
    }
} else {
    // Admin looking at client
    $stmt = $pdo->prepare("SELECT is_typing, is_online, last_ping FROM IFW_chat_status WHERE user_type = 'client' AND user_id = ?");
    $stmt->execute([$client_id]);
    $status = $stmt->fetch();
    if ($status) {
        $other_typing = $status['is_typing'];
        $other_online = (strtotime($status['last_ping']) > time() - 30) ? 1 : 0;
    }
}

// 4. Check if our messages have been read by the other party
$stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_messages WHERE client_id = ? AND sender = ? AND is_read = 0");
$stmt->execute([$client_id, $user_type]);
$unread_count = $stmt->fetchColumn();

echo json_encode([
    'status' => 'success',
    'other_typing' => $other_typing,
    'other_online' => $other_online,
    'all_read' => ($unread_count == 0)
]);
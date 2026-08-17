<?php
// api/poll_notifications.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_client = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true && !empty($_SESSION['client_portal_id']);

if (!$is_admin && !$is_client) {
    echo json_encode(['status' => 'unauthorized', 'unread_messages' => 0, 'unread_notifs' => 0]);
    exit;
}

$last_msg_id = isset($_GET['last_msg_id']) ? (int)$_GET['last_msg_id'] : 0;
$last_notif_id = isset($_GET['last_notif_id']) ? (int)$_GET['last_notif_id'] : 0;

$unread_messages = 0;
$latest_message = null;
$unread_notifs = 0;
$latest_notification = null;

try {
    if ($is_client) {
        $client_id = (int)$_SESSION['client_portal_id'];
        
        // Count unread admin messages to this client
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_chat_messages WHERE client_id = ? AND sender_type = 'admin' AND is_read = 0");
        $stmt->execute([$client_id]);
        $unread_messages = (int)$stmt->fetchColumn();
        
        // Check if there is a new message with id > last_msg_id
        if ($last_msg_id > 0) {
            $stmtM = $pdo->prepare("SELECT m.*, COALESCE(NULLIF(u.full_name, ''), u.username, 'Investigator') as sender_name FROM IFW_chat_messages m LEFT JOIN IFW_users u ON m.admin_id = u.id WHERE m.client_id = ? AND m.sender_type = 'admin' AND m.id > ? ORDER BY m.id DESC LIMIT 1");
            $stmtM->execute([$client_id, $last_msg_id]);
            $latest_message = $stmtM->fetch();
        }
        
        // Unread in-app notifications
        $stmtN = $pdo->prepare("SELECT COUNT(*) FROM IFW_notifications WHERE client_id = ? AND is_read = 0");
        $stmtN->execute([$client_id]);
        $unread_notifs = (int)$stmtN->fetchColumn();
        
        // New case event / notification
        if ($last_notif_id > 0) {
            $stmtNotif = $pdo->prepare("SELECT * FROM IFW_notifications WHERE client_id = ? AND id > ? ORDER BY id DESC LIMIT 1");
            $stmtNotif->execute([$client_id, $last_notif_id]);
            $latest_notification = $stmtNotif->fetch();
        } else {
            // First load or query latest ID for tracker synchronization
            $stmtNotifMax = $pdo->prepare("SELECT MAX(id) as max_id FROM IFW_notifications WHERE client_id = ?");
            $stmtNotifMax->execute([$client_id]);
            $max_notif_id = (int)$stmtNotifMax->fetchColumn();
        }
        
    } else {
        // Admin
        $admin_id = (int)($_SESSION['admin_id'] ?? 0);
        $admin_role = $_SESSION['admin_role'] ?? 'admin';
        
        if (in_array($admin_role, ['superadmin', 'super_admin', 'admin'])) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM IFW_chat_messages WHERE sender_type = 'client' AND is_read = 0");
            $unread_messages = (int)$stmt->fetchColumn();
            
            if ($last_msg_id > 0) {
                $stmtM = $pdo->prepare("SELECT m.*, CONCAT(c.first_name, ' ', c.last_name) as sender_name FROM IFW_chat_messages m JOIN IFW_clients c ON m.client_id = c.id WHERE m.sender_type = 'client' AND m.id > ? ORDER BY m.id DESC LIMIT 1");
                $stmtM->execute([$last_msg_id]);
                $latest_message = $stmtM->fetch();
            }
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_chat_messages m JOIN IFW_clients c ON m.client_id = c.id WHERE c.assigned_agent_id = ? AND m.sender_type = 'client' AND m.is_read = 0");
            $stmt->execute([$admin_id]);
            $unread_messages = (int)$stmt->fetchColumn();
            
            if ($last_msg_id > 0) {
                $stmtM = $pdo->prepare("SELECT m.*, CONCAT(c.first_name, ' ', c.last_name) as sender_name FROM IFW_chat_messages m JOIN IFW_clients c ON m.client_id = c.id WHERE c.assigned_agent_id = ? AND m.sender_type = 'client' AND m.id > ? ORDER BY m.id DESC LIMIT 1");
                $stmtM->execute([$admin_id, $last_msg_id]);
                $latest_message = $stmtM->fetch();
            }
        }
    }
} catch (Exception $e) {}

echo json_encode([
    'status' => 'success',
    'unread_messages' => $unread_messages,
    'unread_notifs' => $unread_notifs,
    'max_notif_id' => $max_notif_id ?? 0,
    'latest_message' => $latest_message ? [
        'id' => (int)$latest_message['id'],
        'sender_name' => $latest_message['sender_name'],
        'message' => substr($latest_message['message'] ?? 'Sent an attachment', 0, 80),
        'created_at' => $latest_message['created_at'],
        'url' => $is_client ? '/client/chat.php' : ('/admin/chat.php?client_id=' . $latest_message['client_id'])
    ] : null,
    'latest_notification' => $latest_notification ? [
        'id' => (int)$latest_notification['id'],
        'title' => $latest_notification['title'] ?? 'Case Update',
        'message' => $latest_notification['message'] ?? '',
        'link' => !empty($latest_notification['link']) ? $latest_notification['link'] : '/client/dashboard.php',
        'created_at' => $latest_notification['created_at']
    ] : null
]);

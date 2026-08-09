<?php
// admin/chat_sse.php
require_once '../config.php';
require_once '../includes/functions.php';

// Must be logged in as admin to view this stream
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit;
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if ($client_id <= 0) {
    http_response_code(400);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// Turn off output buffering to send data immediately
if (ob_get_level()) {
    ob_end_clean();
}

// Track the last message ID sent to this client
$last_id = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;

if ($last_id == 0) {
    // If no last-event-id is provided, find the max ID for this client so we only send new messages
    $stmt = $pdo->prepare("SELECT MAX(id) FROM IFW_messages WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $last_id = (int)$stmt->fetchColumn();
}

// Keep connection open and check for new messages every second
while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    $stmt = $pdo->prepare("SELECT m.*, u.username AS admin_name, u.role AS admin_role 
                           FROM IFW_messages m 
                           LEFT JOIN IFW_users u ON m.admin_id = u.id 
                           WHERE m.client_id = ? AND m.id > ? 
                           ORDER BY m.id ASC");
    $stmt->execute([$client_id, $last_id]);
    $new_messages = $stmt->fetchAll();
    
    if ($new_messages) {
        foreach ($new_messages as $msg) {
            echo "id: " . $msg['id'] . "\n";
            echo "data: " . json_encode($msg) . "\n\n";
            $last_id = $msg['id'];
        }
        ob_flush();
        flush();
    }
    
    // Sleep to prevent high CPU usage. Polling every 1 second.
    sleep(1);
}
?>





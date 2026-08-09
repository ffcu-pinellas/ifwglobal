<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['frontend_client_id'])) {
    http_response_code(403);
    exit;
}

$client_id = $_SESSION['frontend_client_id'];

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

while (true) {
    // Fetch all messages for this client
    $stmt = $pdo->prepare("SELECT m.id, m.sender, m.message_text, m.attachment_path, m.created_at, m.is_read, u.username AS admin_name, u.role AS admin_role 
                           FROM IFW_messages m 
                           LEFT JOIN IFW_users u ON m.admin_id = u.id 
                           WHERE m.client_id = ? 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$client_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Always send the full list to ensure UI is in sync
    echo "data: " . json_encode($messages) . "\n\n";
    ob_flush();
    flush();

    // Sleep for 2 seconds before checking again
    if (connection_aborted()) break;
    sleep(2);
}
?>
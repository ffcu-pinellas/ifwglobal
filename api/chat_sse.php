<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require_once '../config.php';

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

// Only send updates if there's a change
$last_id = isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? intval($_SERVER["HTTP_LAST_EVENT_ID"]) : 0;

while (true) {
    // Fetch all messages for this client
    $stmt = $pdo->prepare("SELECT id, sender, message_text, attachment_path, created_at, is_read FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
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
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

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    
    if ($client_id > 0 && is_numeric($amount) && $amount > 0) {
        $amount = number_format((float)$amount, 2, '.', '');
        
        // Create invoice
        $stmt = $pdo->prepare("INSERT INTO IFW_invoices (client_id, amount) VALUES (?, ?)");
        $stmt->execute([$client_id, $amount]);
        
        // Add chat message
        $msg = "🔔 **Invoice Generated:** A retainer invoice for **$$amount** has been generated for your case. Please refer to the **Payment Details** panel in your dashboard for manual payment instructions. Let us know when the transfer is complete.";
        
        $msgStmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text) VALUES (?, 'admin', ?)");
        $msgStmt->execute([$client_id, $msg]);
        
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount.']);
}
?>
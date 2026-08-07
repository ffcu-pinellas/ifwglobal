<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_portal_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_SESSION['client_portal_id'];
    $doc_id = $_POST['document_id'] ?? 0;
    $pin = $_POST['pin'] ?? '';
    
    // Verify PIN
    $stmt = $pdo->prepare("SELECT pin_hash FROM IFW_clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client || !password_verify($pin, $client['pin_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid PIN. Signature failed.']);
        exit;
    }
    
    // Verify document belongs to client
    $docStmt = $pdo->prepare("SELECT id, requires_signature, is_signed FROM IFW_documents WHERE id = ? AND client_id = ?");
    $docStmt->execute([$doc_id, $client_id]);
    $doc = $docStmt->fetch();
    
    if ($doc && $doc['requires_signature'] && !$doc['is_signed']) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $update = $pdo->prepare("UPDATE IFW_documents SET is_signed = 1, signed_at = CURRENT_TIMESTAMP, signature_ip = ? WHERE id = ?");
        $update->execute([$ip, $doc_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Document signed securely.']);
        exit;
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Document not found or already signed.']);
}
?>
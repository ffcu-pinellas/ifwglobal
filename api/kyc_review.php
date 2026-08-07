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

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_id = $_POST['document_id'] ?? 0;
    $status = $_POST['status'] ?? ''; // 'approved' or 'rejected'
    $feedback = $_POST['feedback'] ?? '';
    
    if (in_array($status, ['approved', 'rejected']) && $doc_id > 0) {
        $stmt = $pdo->prepare("UPDATE IFW_kyc_documents SET status = ?, admin_feedback = ? WHERE id = ?");
        if ($stmt->execute([$status, $feedback, $doc_id])) {
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
}
?>
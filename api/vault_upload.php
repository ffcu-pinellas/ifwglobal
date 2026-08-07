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
    $client_id = $_POST['client_id'] ?? 0;
    $doc_type = $_POST['document_type'] ?? 'Standard';
    $requires_sig = isset($_POST['requires_signature']) && $_POST['requires_signature'] === '1' ? 1 : 0;
    
    if ($client_id > 0 && isset($_FILES['vault_file']) && $_FILES['vault_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['vault_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'png'];
        
        if (in_array($ext, $allowed)) {
            $dir = '../uploads/vault/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = uniqid('vault_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $path = 'uploads/vault/' . $filename;
                
                $stmt = $pdo->prepare("INSERT INTO IFW_documents (client_id, file_name, file_path, document_type, requires_signature) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$client_id, $file['name'], $path, $doc_type, $requires_sig]);
                
                echo json_encode(['status' => 'success']);
                exit;
            }
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid file or input.']);
}
?>
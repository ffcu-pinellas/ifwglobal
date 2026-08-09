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

if (!isset($_SESSION['client_portal_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$client_id = $_SESSION['client_portal_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_type = $_POST['document_type'] ?? '';
    if (!in_array($doc_type, ['Government ID', 'Proof of Address'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid document type.']);
        exit;
    }

    if (isset($_FILES['kyc_file']) && $_FILES['kyc_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['kyc_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($ext, $allowed) && $file['size'] <= 10485760) { // 10MB
            $dir = '../uploads/kyc/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = uniqid('kyc_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $path = 'uploads/kyc/' . $filename;
                
                $stmt = $pdo->prepare("INSERT INTO IFW_kyc_documents (client_id, document_type, file_path) VALUES (?, ?, ?)");
                $stmt->execute([$client_id, $doc_type, $path]);
                
                echo json_encode(['status' => 'success', 'message' => 'Document uploaded for verification.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type or size exceeds 10MB.']);
            exit;
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']);
}
?>
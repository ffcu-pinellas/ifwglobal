<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// api/kyc_upload.php
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
    $doc_type = trim($_POST['document_type'] ?? 'Government ID');

    if (isset($_FILES['kyc_file']) && $_FILES['kyc_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['kyc_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        
        if (in_array($ext, $allowed) && $file['size'] <= 15728640) { // 15MB
            $dir = '../uploads/kyc/';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            
            $filename = uniqid('kyc_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $path = 'uploads/kyc/' . $filename;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO IFW_kyc_documents (client_id, document_type, file_path, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->execute([$client_id, $doc_type, $path]);
                } catch (Exception $e) {}
                
                echo json_encode(['status' => 'success', 'message' => 'Verification document uploaded successfully. Our intelligence team will review it.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file format or file size exceeds 15MB limit.']);
            exit;
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid document file to upload.']);
    exit;
}
<?php
// admin/chat_send.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

// Allow admins or agents to send via this endpoint
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $message = trim($_POST['message'] ?? '');
    $attachment_path = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Blacklist dangerous file types for security
        $blacklisted = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'cmd', 'js', 'jsp', 'cgi', 'pl', 'py'];
        
        if (!in_array($ext, $blacklisted) && $file['size'] <= 52428800) { // 50MB
            $dir = '../uploads/documents/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = uniqid('doc_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $attachment_path = 'uploads/documents/' . $filename;
            }
        }
    }
    
    if (empty($message) && $attachment_path) {
        $message = "Sent an attachment.";
    }
    
    if ($client_id > 0 && (!empty($message) || $attachment_path)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text, attachment_path) VALUES (?, 'admin', ?, ?)");
        if ($stmt->execute([$client_id, $message, $attachment_path])) {
            
            // Check if client is online
            $stmt = $pdo->prepare("SELECT is_online, last_ping FROM IFW_chat_status WHERE user_type = 'client' AND user_id = ?");
            $stmt->execute([$client_id]);
            $status = $stmt->fetch();
            $is_online = $status && (strtotime($status['last_ping']) > time() - 30);
            
            if (!$is_online) {
                $stmt = $pdo->prepare("SELECT email, first_name FROM IFW_clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch();
                if ($client && $client['email']) {
                    $html_body = "<h2>New Message Received</h2>
                                  <p>Hello {$client['first_name']},</p>
                                  <p>An agent has responded to your case on the IFW Global secure portal.</p>
                                  <a href=\"" . rtrim(BASE_URL, '/') . "/client/login.php\" class=\"btn\">Log in to view message</a>";
                    send_html_email($client['email'], "New Message on your IFW Global Portal", $html_body);
                }
            }

            echo json_encode(['status' => 'success']);
            exit;
        }
    }
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
?>





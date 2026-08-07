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
require_once '../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Support both frontend widget (frontend_client_id) and Client Portal (client_portal_id)
$client_id = $_SESSION['client_portal_id'] ?? $_SESSION['frontend_client_id'] ?? null;

if (!$client_id) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Fallback message if only a file was uploaded
    if (empty($message) && $attachment_path) {
        $message = "Sent an attachment.";
    }

    if (!empty($message) || $attachment_path) {
        $stmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text, attachment_path) VALUES (?, 'client', ?, ?)");
        $stmt->execute([$client_id, $message, $attachment_path]);
        
        // Notify offline agent
        $stmt = $pdo->prepare("SELECT assigned_agent_id FROM IFW_clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $agent_id = $stmt->fetchColumn();
        
        if ($agent_id) {
            $stmt = $pdo->prepare("SELECT is_online, last_ping FROM IFW_chat_status WHERE user_type = 'admin' AND user_id = ?");
            $stmt->execute([$agent_id]);
            $status = $stmt->fetch();
            $is_online = $status && (strtotime($status['last_ping']) > time() - 30);
            
            if (!$is_online) {
                $stmt = $pdo->prepare("SELECT email, username FROM IFW_users WHERE id = ?");
                $stmt->execute([$agent_id]);
                $agent = $stmt->fetch();
                if ($agent && $agent['email']) {
                    $html_body = "<h2>New Message Received</h2>
                                  <p>Hello {$agent['username']},</p>
                                  <p>Client <strong>#$client_id</strong> has sent you a new message on the IFW Global Portal while you were offline.</p>
                                  <a href=\"" . rtrim($env['APP_URL'] ?? '/', '/') . "/admin/chat.php?client_id={$client_id}\" class=\"btn\">Log in to reply</a>";
                    send_html_email($agent['email'], "New Message from Client #$client_id", $html_body);
                }
            }
        } else {
            // Notify general admin if unassigned
            $stmt = $pdo->prepare("SELECT setting_value FROM IFW_form_settings WHERE setting_key = 'recipient_email'");
            $stmt->execute();
            $admin_email = $stmt->fetchColumn();
            if ($admin_email) {
                $html_body = "<h2>New Message from Unassigned Client</h2>
                              <p>An unassigned client (<strong>#$client_id</strong>) has sent a new message on the IFW Global Portal.</p>
                              <a href=\"" . rtrim($env['APP_URL'] ?? '/', '/') . "/admin/chat.php?client_id={$client_id}\" class=\"btn\">Log in to reply</a>";
                send_html_email($admin_email, "New Message from Unassigned Client #$client_id", $html_body);
            }
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Empty message and no valid attachment']);
    }
}
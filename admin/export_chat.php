<?php
// admin/export_chat.php
require_once '../config.php';
require_once '../includes/functions.php';

require_admin_login();

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if ($client_id <= 0) {
    die("Invalid Client ID");
}

// Ensure agent has access
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'agent') {
    $stmt = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
    $stmt->execute([$client_id, $_SESSION['admin_id']]);
    if (!$stmt->fetch()) {
        die("Unauthorized access to this client's chat history.");
    }
}

// Fetch Client Info
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Fetch Messages
$stmt = $pdo->prepare("SELECT * FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
$stmt->execute([$client_id]);
$messages = $stmt->fetchAll();

log_audit_action($pdo, $_SESSION['admin_id'], 'EXPORT_CHAT', "Exported chat history for client #$client_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <title>Chat Export - Client #<?= $client_id ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 40px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; }
        .message { margin-bottom: 15px; padding: 10px; border-radius: 5px; }
        .admin { background-color: #f0f8ff; border-left: 4px solid #007bff; }
        .client { background-color: #f9f9f9; border-left: 4px solid #fecc56; }
        .timestamp { font-size: 0.8em; color: #666; margin-bottom: 5px; }
        .attachment { margin-top: 10px; font-style: italic; color: #555; }
        @media print {
            body { margin: 0; }
            button.print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()" style="padding: 10px 20px; font-size: 16px; margin-bottom: 20px; cursor: pointer;">Print to PDF</button>

    <div class="header">
        <h2>IFW Global - Official Communication Record</h2>
        <p><strong>Client Name:</strong> <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($client['email']) ?><br>
        <strong>Case Status:</strong> <?= htmlspecialchars($client['status']) ?><br>
        <strong>Export Date:</strong> <?= date('Y-m-d H:i:s') ?></p>
    </div>

    <div class="chat-log">
        <?php foreach ($messages as $msg): ?>
            <div class="message <?= $msg['sender'] ?>">
                <div class="timestamp">
                    <strong><?= $msg['sender'] === 'admin' ? 'Agent/Admin' : 'Client' ?></strong> - <?= $msg['created_at'] ?>
                </div>
                <div><?= nl2br(htmlspecialchars($msg['message_text'])) ?></div>
                <?php if (!empty($msg['attachment_path'])): ?>
                    <div class="attachment">[Attachment: <?= htmlspecialchars(basename($msg['attachment_path'])) ?>]</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if(empty($messages)): ?>
            <p>No messages found in this case file.</p>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto print prompt when page loads
        window.onload = function() { window.print(); }
    </script>
</body>
</html>






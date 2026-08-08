<?php
// public/client/chat.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$client_id = $_SESSION['client_portal_id'];
$_SESSION['frontend_client_id'] = $client_id;
$_SESSION['role'] = 'client';

$chat_provider = isset($pdo) ? get_setting($pdo, 'chat_provider', 'internal') : 'internal';
$tawk_property = isset($pdo) ? get_setting($pdo, 'tawkto_property_id', '') : '';

// Handle Client Message Post (only for internal chat)
if ($chat_provider === 'internal' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text) VALUES (?, 'client', ?)");
            $stmt->execute([$client_id, $message]);
            
            // Mark all admin messages as read when client sends a reply
            $pdo->prepare("UPDATE IFW_chat_messages SET is_read = 1 WHERE client_id = ? AND sender_type = 'admin'")->execute([$client_id]);
        } catch (Exception $e) {}
    }
    header("Location: chat.php");
    exit;
}

// Fetch Messages for internal chat
$messages = [];
if ($chat_provider === 'internal') {
    try {
        $stmt = $pdo->prepare("SELECT sender, message_text, attachment_path, created_at, is_read FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
        $stmt->execute([$client_id]);
        $messages = $stmt->fetchAll();
        
        // Mark admin messages as read upon viewing
        $pdo->prepare("UPDATE IFW_chat_messages SET is_read = 1 WHERE client_id = ? AND sender_type = 'admin'")->execute([$client_id]);
    } catch (Exception $e) {}
}

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<div class="row">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1">
                <i class="fas fa-comments mr-2"></i>
                <?php 
                if ($chat_provider === 'tawkto' || $chat_provider === 'tawk') echo 'Live Chat Support';
                elseif ($chat_provider === 'manychat') echo 'ManyChat Support';
                elseif ($chat_provider === 'custom') echo 'Support Center';
                else echo 'Secure Case Messaging';
                ?>
            </h3>
            <p class="text-muted mb-0">Direct support channel with your assigned recovery team & case investigators.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-warning btn-sm font-weight-bold"><i class="fas fa-arrow-left mr-1"></i> Back to Dashboard</a>
    </div>
</div>

<div class="row">
    <?php if ($chat_provider === 'internal'): ?>
        <!-- INTERNAL SECURE CHAT -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-shield mr-2"></i>Internal Case Messaging Desk</span>
                    <span class="badge badge-success px-3 py-1"><i class="fas fa-lock mr-1"></i>256-Bit Encrypted</span>
                </div>
                <div class="card-body bg-dark text-white p-3 d-flex flex-column" style="min-height: 480px;">
                    <div id="chatMessages" class="flex-grow-1 p-3 mb-3 border border-secondary rounded style-scroll-dark" style="height: 380px; overflow-y: auto; background-color: #121212;">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-user-shield text-warning mb-3" style="font-size: 3.5rem;"></i>
                                <h5>Connected to Recovery Team</h5>
                                <p>Type your message below to send a direct update to your assigned case investigator.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isClient = ($m['sender'] === 'client'); ?>
                                <div class="mb-3 d-flex flex-column <?= $isClient ? 'align-items-end' : 'align-items-start' ?>">
                                    <div class="p-3 rounded shadow-sm <?= $isClient ? 'bg-warning text-dark font-weight-bold' : 'bg-secondary text-white' ?>" style="max-width: 80%; border-radius: 12px !important;">
                                        <?= nl2br(htmlspecialchars($m['message_text'])) ?>
                                    </div>
                                    <small class="text-muted mt-1 px-1" style="font-size: 10px;">
                                        <i class="fas fa-clock mr-1"></i><?= date('M j, g:i a', strtotime($m['created_at'])) ?> &bull; <strong><?= $isClient ? 'You' : 'Investigator' ?></strong>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="send_message">
                        <input type="text" name="message" class="form-control bg-dark text-white border-secondary p-3 mr-2" placeholder="Type your message to your case investigator..." required style="height: 48px;">
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow" style="height: 48px;">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($chat_provider === 'tawkto' || $chat_provider === 'tawk'): ?>
        <!-- TAWK.TO DIRECT EMBED -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-headset mr-2"></i>Live 24/7 Support Desk</span>
                    <span class="badge badge-warning text-dark font-weight-bold">Tawk.to Chat</span>
                </div>
                <div class="card-body bg-dark p-2" style="height: 550px;">
                    <?php if (!empty($tawk_property)): ?>
                        <?php
                        preg_match('/tawk\.to\/chat\/([a-zA-Z0-9]+)/', $tawk_property, $m);
                        $prop_id = $m[1] ?? $tawk_property;
                        preg_match('/tawk\.to\/chat\/[^\/]+\/([a-zA-Z0-9]+)/', $tawk_property, $m2);
                        $chat_hash = $m2[1] ?? 'default';
                        $iframe_src = "https://tawk.to/chat/{$prop_id}/{$chat_hash}?pop=1";
                        ?>
                        <iframe src="<?= htmlspecialchars($iframe_src) ?>" style="width: 100%; height: 100%; border: none; border-radius: 8px; background: #111;"></iframe>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <h5>Tawk.to Support is Active</h5>
                            <p>Please use the chat bubble in the bottom right corner of the page to speak with our support staff.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($chat_provider === 'manychat'): ?>
        <!-- MANYCHAT CONSOLE -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
                    <i class="fas fa-headset mr-2"></i>ManyChat Virtual Assistant
                </div>
                <div class="card-body bg-dark text-white p-5 text-center">
                    <i class="fab fa-facebook-messenger text-warning fa-4x mb-4"></i>
                    <h4 class="font-weight-bold">ManyChat Support Enabled</h4>
                    <p class="text-muted max-width-500 mx-auto">
                        We have integrated ManyChat AI automation to resolve your queries instantly. 
                        Please locate the Chat widget in the bottom right corner of your screen to start a conversation with our automated assistant or a live agent.
                    </p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- CUSTOM EMBED / FALLBACK -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
                    <i class="fas fa-comments mr-2"></i>Custom Integration Support
                </div>
                <div class="card-body bg-dark text-white p-5 text-center">
                    <i class="fas fa-comment-dots text-warning fa-4x mb-4"></i>
                    <h4 class="font-weight-bold">Live Chat Widget Active</h4>
                    <p class="text-muted max-width-500 mx-auto">
                        A custom support chat system is currently active on our platform. 
                        Use the chat widget on the bottom right of the screen to connect directly with our support team.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto scroll to bottom of chat if internal chat
document.addEventListener("DOMContentLoaded", function() {
    var chatBox = document.getElementById("chatMessages");
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
});
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
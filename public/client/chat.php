<?php
// client/chat.php
require_once '../config.php';
require_once '../includes/functions.php';

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

// Handle Client Message Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text) VALUES (?, 'client', ?)");
            $stmt->execute([$client_id, $message]);
        } catch (Exception $e) {}
    }
    header("Location: chat.php");
    exit;
}

// Fetch Messages
$messages = [];
try {
    $stmt = $pdo->prepare("SELECT sender, message_text, attachment_path, created_at, is_read FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
    $stmt->execute([$client_id]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="row">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-comments mr-2"></i>Live Case Communications</h3>
            <p class="text-muted mb-0">Direct encrypted channel with your assigned recovery team & case investigators.</p>
        </div>
        <button type="button" onclick="window.open('https://tawk.to/chat/6a742dd38875351d455643d1/default?pop=1', 'TawkPopout', 'width=500,height=700');" class="btn btn-warning btn-sm font-weight-bold text-dark shadow">
            <i class="fas fa-headset mr-1"></i> Open Tawk.to Live Support Desk
        </button>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-lg bg-dark border-secondary h-100">
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

    <!-- TAWK.TO DIRECT POP-IN WORKSPACE -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-lg bg-dark border-secondary h-100">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-headset mr-2"></i>Live 24/7 Support Desk (Direct Popout Stream)</span>
            </div>
            <div class="card-body bg-dark p-2" style="height: 520px;">
                <iframe src="https://tawk.to/chat/6a742dd38875351d455643d1/default?pop=1" style="width: 100%; height: 100%; border: none; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

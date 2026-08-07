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

$chat_provider = get_setting($pdo, 'chat_provider', 'tawkto');
$manychat_code = get_setting($pdo, 'manychat_script_code', '');
$tawkto_code = get_setting($pdo, 'tawkto_property_id', '');
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-comments mr-2"></i>Live Case Communications</h3>
            <p class="text-muted mb-0">Direct encrypted chat channel with your assigned recovery team & investigators.</p>
        </div>
    </div>
</div>

<?php if ($chat_provider === 'tawkto'): ?>
    <!-- Load Tawk.to Script -->
    <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/6a742dd38875351d455643d1/default';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
    })();
    </script>

    <div class="card shadow-sm border-warning mb-4">
        <div class="card-header bg-dark text-warning border-warning font-weight-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-headset mr-2"></i>Live Investigator Desk</span>
            <span class="badge badge-success px-3 py-2"><i class="fas fa-lock mr-1"></i>Encrypted Session</span>
        </div>
        <div class="card-body bg-dark text-white p-5 text-center">
            <div class="py-4">
                <i class="fas fa-user-shield text-warning mb-3" style="font-size: 5rem;"></i>
                <h4 class="font-weight-bold text-white mb-2">Live Case Assistant Connected</h4>
                <p class="text-muted max-w-lg mx-auto mb-4" style="max-width: 550px;">
                    Click below to open a direct, private communication window with your assigned case officer.
                </p>
                <button type="button" onclick="if(window.Tawk_API && Tawk_API.maximize){ Tawk_API.maximize(); } else { alert('Opening live support widget...'); }" class="btn btn-warning btn-lg font-weight-bold text-dark px-5 shadow">
                    <i class="fas fa-comments mr-2"></i> Start Live Conversation
                </button>
            </div>
        </div>
    </div>

<?php elseif ($chat_provider === 'manychat'): ?>
    <div class="card shadow-sm border-secondary mb-4">
        <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
            <i class="fas fa-robot mr-2"></i>Live Messenger
        </div>
        <div class="card-body bg-dark text-white p-4 text-center">
            <?php if (!empty($manychat_code)): ?>
                <?php echo $manychat_code; ?>
            <?php else: ?>
                <p class="text-muted">ManyChat live session is active.</p>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="card shadow-sm border-secondary mb-4">
        <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
            <i class="fas fa-comment-alt mr-2"></i>Internal Case Messaging Thread
        </div>
        <div class="card-body bg-dark text-white d-flex flex-column" style="height: 550px;">
            <div id="chatBody" class="flex-grow-1 p-3 mb-3 bg-secondary rounded" style="overflow-y: auto;">
                <p class="text-muted text-center mt-5">Connected to secure case channel.</p>
            </div>
            <div class="d-flex gap-2">
                <input type="text" id="clientChatInput" class="form-control bg-secondary text-white border-0 py-3" placeholder="Type your message to your assigned investigator...">
                <button type="button" id="clientSendBtn" class="btn btn-warning font-weight-bold text-dark px-4"><i class="fas fa-paper-plane mr-1"></i> Send</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>

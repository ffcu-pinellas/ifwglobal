<?php
// admin/chat.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'agent';
$admin_id = $_SESSION['admin_id'];

$chat_provider = get_setting($pdo, 'chat_provider', 'tawkto');
$manychat_code = get_setting($pdo, 'manychat_script_code', '');
$tawkto_code = get_setting($pdo, 'tawkto_property_id', '');
$custom_code = get_setting($pdo, 'custom_chat_code', '');

// Fetch clients for internal chat mode
if ($is_agent) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM IFW_clients WHERE assigned_agent_id = ? ORDER BY last_name ASC");
    $stmt->execute([$admin_id]);
    $clients = $stmt->fetchAll();
} else {
    $clients = $pdo->query("SELECT id, first_name, last_name, email FROM IFW_clients ORDER BY last_name ASC")->fetchAll();
}

$active_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (isset($clients[0]['id']) ? $clients[0]['id'] : 0);

// Fetch initial messages for active client
$messages = [];
$active_client = null;
if ($active_client_id > 0) {
    $stmt = $pdo->prepare("SELECT sender, message_text, attachment_path, created_at, is_read FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
    $stmt->execute([$active_client_id]);
    $messages = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
    $stmt->execute([$active_client_id]);
    $active_client = $stmt->fetch();
}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-comments mr-2"></i>Live Communications Desk</h3>
            <p class="text-muted mb-0">Active Messaging Engine: <span class="badge badge-warning text-dark font-weight-bold"><?= strtoupper($chat_provider) ?></span></p>
        </div>
        <div>
            <a href="settings.php" class="btn btn-outline-warning btn-sm font-weight-bold mr-2">
                <i class="fas fa-cog mr-1"></i> Integration Settings
            </a>
        </div>
    </div>
</div>

<?php if ($chat_provider === 'tawkto'): ?>
    <!-- TAWK.TO INTEGRATED LIVE DESK -->
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
            <span><i class="fas fa-headset mr-2"></i>Live Support Console (Tawk.to Engine)</span>
            <span class="badge badge-success px-3 py-2" style="font-size: 12px;"><i class="fas fa-circle text-white mr-1" style="font-size: 8px;"></i> Live Support Connected</span>
        </div>
        <div class="card-body bg-dark text-white p-5 text-center">
            <div class="py-4">
                <div class="mb-4">
                    <i class="fas fa-comments text-warning" style="font-size: 5rem;"></i>
                </div>
                <h4 class="font-weight-bold text-white mb-2">Tawk.to Live Messaging Console</h4>
                <p class="text-muted max-w-lg mx-auto mb-4" style="max-width: 600px;">
                    Your live chat widget is active and listening for incoming client messages across the entire website and client portal.
                </p>

                <div class="d-flex justify-content-center gap-3">
                    <button type="button" onclick="if(window.Tawk_API && Tawk_API.maximize){ Tawk_API.maximize(); } else { alert('Tawk.to widget is loading in the bottom right corner...'); }" class="btn btn-warning btn-lg font-weight-bold text-dark px-5 shadow mr-3">
                        <i class="fas fa-comment-dots mr-2"></i> Launch Live Messenger Widget
                    </button>

                    <a href="https://dashboard.tawk.to/" target="_blank" class="btn btn-outline-light btn-lg font-weight-bold px-4">
                        <i class="fas fa-external-link-alt mr-2"></i> Open Tawk.to Agent Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($chat_provider === 'manychat'): ?>
    <!-- MANYCHAT INTEGRATED CONSOLE -->
    <div class="card shadow-sm border-secondary mb-4">
        <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
            <i class="fas fa-robot mr-2"></i>ManyChat Automated Messenger
        </div>
        <div class="card-body bg-dark text-white p-4">
            <?php if (!empty($manychat_code)): ?>
                <div class="border border-secondary rounded p-3 bg-secondary">
                    <?php echo $manychat_code; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-dark font-weight-bold mb-0">
                    <i class="fas fa-exclamation-triangle mr-2"></i>ManyChat embed code is missing. 
                    <a href="settings.php" class="text-dark font-weight-bold underline ml-2">Click here to add ManyChat script in settings</a> or switch to Tawk.to.
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- INTERNAL DATABASE LIVE CHAT WORKSPACE -->
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-secondary h-100">
                <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
                    <i class="fas fa-users mr-2"></i>Client Accounts
                </div>
                <div class="card-body bg-dark p-0" style="max-height: 600px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        <?php foreach ($clients as $c): ?>
                            <a href="chat.php?client_id=<?= $c['id'] ?>" class="list-group-item list-group-item-action bg-dark text-white border-secondary <?= ($c['id'] == $active_client_id) ? 'active bg-warning text-dark font-weight-bold' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle mr-2"></i><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                        <small class="d-block text-muted"><?= htmlspecialchars($c['email']) ?></small>
                                    </div>
                                    <span class="badge badge-secondary">Ref #<?= $c['id'] ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-secondary h-100">
                <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center">
                    <span>
                        <?php if ($active_client): ?>
                            <i class="fas fa-comments mr-2"></i>Conversation with <?= htmlspecialchars($active_client['first_name'] . ' ' . $active_client['last_name']) ?>
                        <?php else: ?>
                            Select a client to start chatting
                        <?php endif; ?>
                    </span>
                </div>
                <div class="card-body bg-secondary text-white d-flex flex-column" style="height: 500px;">
                    <div id="chatMessages" class="flex-grow-1 p-3 mb-3 bg-dark rounded" style="overflow-y: auto;">
                        <?php if (empty($messages)): ?>
                            <p class="text-muted text-center mt-5">No messages in this conversation thread yet.</p>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <div class="mb-3 d-flex flex-column <?= ($m['sender'] === 'admin' || $m['sender'] === 'agent') ? 'align-items-end' : 'align-items-start' ?>">
                                    <div class="p-3 rounded <?= ($m['sender'] === 'admin' || $m['sender'] === 'agent') ? 'bg-warning text-dark font-weight-bold' : 'bg-secondary text-white border border-light' ?>" style="max-width: 75%;">
                                        <?= nl2br(htmlspecialchars($m['message_text'])) ?>
                                    </div>
                                    <small class="text-muted mt-1" style="font-size: 11px;"><?= date('M j, g:i a', strtotime($m['created_at'])) ?> (<?= ucfirst($m['sender']) ?>)</small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($active_client_id > 0): ?>
                        <form id="chatForm" method="POST" action="/api/chat_send.php" class="d-flex gap-2">
                            <input type="hidden" name="client_id" value="<?= $active_client_id ?>">
                            <input type="hidden" name="sender" value="admin">
                            <input type="text" name="message" class="form-control bg-dark text-white border-0 py-3" placeholder="Type your response to client..." required>
                            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4"><i class="fas fa-paper-plane mr-1"></i> Send</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>

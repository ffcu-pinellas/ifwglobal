<?php
// admin/chat.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'agent';
$admin_id = $_SESSION['admin_id'];

$clients = [];
try {
    if ($is_agent) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, status FROM IFW_clients WHERE assigned_agent_id = ? ORDER BY last_name ASC");
        $stmt->execute([$admin_id]);
        $clients = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT id, first_name, last_name, email, phone, status FROM IFW_clients ORDER BY last_name ASC")->fetchAll();
    }
} catch (Exception $e) {
    $clients = [];
}

$active_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (isset($clients[0]['id']) ? $clients[0]['id'] : 0);

// Handle POST message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $client_id = (int)$_POST['client_id'];
    $message = trim($_POST['message']);
    if ($client_id > 0 && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO IFW_messages (client_id, sender, message_text) VALUES (?, 'admin', ?)");
            $stmt->execute([$client_id, $message]);
        } catch (Exception $e) {}
    }
    header("Location: chat.php?client_id=" . $client_id);
    exit;
}

// Fetch messages for active client
$messages = [];
$active_client = null;
if ($active_client_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT sender, message_text, attachment_path, created_at, is_read FROM IFW_messages WHERE client_id = ? ORDER BY created_at ASC");
        $stmt->execute([$active_client_id]);
        $messages = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
        $stmt->execute([$active_client_id]);
        $active_client = $stmt->fetch();
    } catch (Exception $e) {}
}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="row">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-comments mr-2"></i>Live Client Communications Center</h3>
            <p class="text-muted mb-0">Encrypted real-time messaging workspace for administrative officers and assigned investigators.</p>
        </div>
        <div>
            <button type="button" onclick="window.open('https://tawk.to/chat/6a742dd38875351d455643d1/default?pop=1', 'TawkConsole', 'width=500,height=700,resizable=yes,scrollbars=yes');" class="btn btn-warning btn-sm font-weight-bold text-dark mr-2 shadow">
                <i class="fas fa-external-link-alt mr-1"></i> Open Tawk.to Popout Messenger
            </button>
            <a href="settings.php" class="btn btn-outline-secondary btn-sm font-weight-bold text-white">
                <i class="fas fa-cog mr-1"></i> Chat Settings
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- CLIENT LIST PANEL -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-lg bg-dark border-secondary h-100">
            <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users mr-2"></i>Client Directory</span>
                <span class="badge badge-warning text-dark font-weight-bold"><?= count($clients) ?> Clients</span>
            </div>
            <div class="card-body bg-dark p-0" style="max-height: 550px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    <?php if (empty($clients)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-folder-open mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">No client accounts found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                            <a href="chat.php?client_id=<?= $c['id'] ?>" class="list-group-item list-group-item-action bg-dark text-white border-secondary <?= ($c['id'] == $active_client_id) ? 'active bg-warning text-dark font-weight-bold' : '' ?>" style="border-bottom: 1px solid rgba(255,255,255,0.08) !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle mr-2 <?= ($c['id'] == $active_client_id) ? 'text-dark' : 'text-warning' ?>"></i>
                                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                        <small class="d-block <?= ($c['id'] == $active_client_id) ? 'text-dark' : 'text-muted' ?>"><?= htmlspecialchars($c['email']) ?></small>
                                    </div>
                                    <span class="badge <?= ($c['id'] == $active_client_id) ? 'badge-dark text-warning' : 'badge-secondary' ?>">Ref #<?= $c['id'] ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MESSAGING WORKSPACE -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-lg bg-dark border-secondary h-100">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center">
                <span>
                    <?php if ($active_client): ?>
                        <i class="fas fa-comment-dots mr-2"></i>Conversation: <strong><?= htmlspecialchars($active_client['first_name'] . ' ' . $active_client['last_name']) ?></strong> (<?= htmlspecialchars($active_client['email']) ?>)
                    <?php else: ?>
                        <i class="fas fa-comments mr-2"></i>Select a client account to begin chatting
                    <?php endif; ?>
                </span>
                <?php if ($active_client): ?>
                    <span class="badge badge-success px-3 py-1"><i class="fas fa-shield-alt mr-1"></i> <?= htmlspecialchars($active_client['status'] ?? 'Active') ?></span>
                <?php endif; ?>
            </div>

            <div class="card-body bg-dark text-white d-flex flex-column p-3" style="min-height: 480px;">
                <div id="chatMessages" class="flex-grow-1 p-3 mb-3 border border-secondary rounded style-scroll-dark" style="height: 380px; overflow-y: auto; background-color: #121212;">
                    <?php if (!$active_client): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments text-warning mb-3" style="font-size: 3.5rem;"></i>
                            <h5>No Client Selected</h5>
                            <p>Choose a client from the left directory panel to view and send messages.</p>
                        </div>
                    <?php elseif (empty($messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-paper-plane text-warning mb-3" style="font-size: 3rem;"></i>
                            <h5>Start a Conversation</h5>
                            <p>No message history with <?= htmlspecialchars($active_client['first_name']) ?> yet. Type your message below to send.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <?php $isAdmin = ($m['sender'] === 'admin' || $m['sender'] === 'agent'); ?>
                            <div class="mb-3 d-flex flex-column <?= $isAdmin ? 'align-items-end' : 'align-items-start' ?>">
                                <div class="p-3 rounded shadow-sm <?= $isAdmin ? 'bg-warning text-dark font-weight-bold' : 'bg-secondary text-white' ?>" style="max-width: 80%; border-radius: 12px !important;">
                                    <?= nl2br(htmlspecialchars($m['message_text'])) ?>
                                </div>
                                <small class="text-muted mt-1 px-1" style="font-size: 10px;">
                                    <i class="fas fa-clock mr-1"></i><?= date('M j, g:i a', strtotime($m['created_at'])) ?> &bull; <strong><?= ucfirst($m['sender']) ?></strong>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($active_client_id > 0): ?>
                    <form method="POST" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="client_id" value="<?= $active_client_id ?>">
                        <input type="text" name="message" class="form-control bg-dark text-white border-secondary p-3 mr-2" placeholder="Type official response to <?= htmlspecialchars($active_client['first_name']) ?>..." required style="height: 48px;">
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow" style="height: 48px;">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- EMBEDDED TAWK.TO DIRECT CONSOLE DRAWER -->
<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow-lg bg-dark border-secondary">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-headset mr-2"></i>Tawk.to Live Support Desk (Direct Popout Stream)</span>
                <button type="button" onclick="window.open('https://tawk.to/chat/6a742dd38875351d455643d1/default?pop=1', 'TawkPopout', 'width=500,height=700');" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-external-link-alt mr-1"></i> Open Popout Window
                </button>
            </div>
            <div class="card-body bg-dark p-2">
                <iframe src="https://tawk.to/chat/6a742dd38875351d455643d1/default?pop=1" style="width: 100%; height: 550px; border: none; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

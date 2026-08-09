<?php
// admin/client_manager.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff']);
$admin_id = $_SESSION['admin_id'];

// Handle Client Add/Delete/Assign/Invite
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'add_client') {
            if ($is_agent) {
                die("Unauthorized action.");
            }
            $stmt = $pdo->prepare("INSERT INTO IFW_clients (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['first_name']),
                trim($_POST['last_name']),
                trim($_POST['email']),
                trim($_POST['phone'])
            ]);
            $new_id = $pdo->lastInsertId();
            log_audit_action($pdo, $admin_id, 'ADD_CLIENT', "Added new client #$new_id (" . trim($_POST['first_name']) . " " . trim($_POST['last_name']) . ")");
            header("Location: client_manager.php?success=1");
            exit;
        } elseif ($_POST['action'] == 'delete_client' && !$is_agent) {
            $client_id = (int)$_POST['client_id'];
            $stmt = $pdo->prepare("DELETE FROM IFW_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            log_audit_action($pdo, $admin_id, 'DELETE_CLIENT', "Deleted client #$client_id and all associated data");
            header("Location: client_manager.php?deleted=1");
            exit;
        } elseif ($_POST['action'] == 'assign_agent' && !$is_agent) {
            $agent_id = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
            $client_id = (int)$_POST['client_id'];
            $stmt = $pdo->prepare("UPDATE IFW_clients SET assigned_agent_id = ? WHERE id = ?");
            $stmt->execute([$agent_id, $client_id]);
            log_audit_action($pdo, $admin_id, 'ASSIGN_AGENT', "Assigned agent #$agent_id to client #$client_id");
            header("Location: client_manager.php?assigned=1");
            exit;
        } elseif ($_POST['action'] == 'invite_client' && !$is_agent) {
            $raw_password = bin2hex(random_bytes(4));
            $hashed = password_hash($raw_password, PASSWORD_DEFAULT);
            $client_id = (int)$_POST['client_id'];
            
            $stmt = $pdo->prepare("UPDATE IFW_clients SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $client_id]);
            log_audit_action($pdo, $admin_id, 'INVITE_CLIENT', "Generated portal credentials for client #$client_id");
            
            $stmt = $pdo->prepare("SELECT first_name, email FROM IFW_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            
            if ($client && $client['email']) {
                $portal_url = rtrim(BASE_URL, '/') . '/client/login.php';
                $html_body = "<h2>Welcome to the IFW Global Client Portal</h2>
                              <p>Hello {$client['first_name']},</p>
                              <p>Your secure client portal has been created. You can use it to track your case status, securely chat with our agents, and upload documents.</p>
                              <div style='background: #f4f7f6; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                  <strong>Portal URL:</strong> <a href=\"{$portal_url}\">{$portal_url}</a><br>
                                  <strong>Temporary Password:</strong> <code style='font-size: 1.2em;'>{$raw_password}</code>
                              </div>
                              <p>Please log in and you will be prompted to set up your secure PIN.</p>";
                send_html_email($client['email'], "Your IFW Global Portal Access", $html_body);
            }
            header("Location: client_manager.php?invited=" . $client_id . "&pwd=" . urlencode($raw_password));
            exit;
        } elseif ($_POST['action'] == 'update_status') {
            $status = in_array($_POST['status'], ['Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery']) ? $_POST['status'] : 'Received';
            $client_id = (int)$_POST['client_id'];
            $stmt = $pdo->prepare("UPDATE IFW_clients SET status = ? WHERE id = ?");
            $stmt->execute([$status, $client_id]);
            log_audit_action($pdo, $admin_id, 'UPDATE_STATUS', "Changed status of client #$client_id to '$status'");
            header("Location: client_manager.php?status_updated=1");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error processing client update: " . $e->getMessage();
    }
}

// Fetch clients based on role with safety fallback
$clients = [];
try {
    if ($is_agent) {
        $stmt = $pdo->prepare("SELECT c.*, u.username as agent_name FROM IFW_clients c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id WHERE c.assigned_agent_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$admin_id]);
        $clients = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT c.*, u.username as agent_name FROM IFW_clients c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id ORDER BY c.created_at DESC");
        $clients = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $clients = [];
}

// Fetch valid agents from IFW_users without referencing non-existent columns
$agents = [];
if (!$is_agent) {
    try {
        $agents = $pdo->query("SELECT id, username, role FROM IFW_users ORDER BY username ASC")->fetchAll();
    } catch (Exception $e) {
        $agents = [];
    }
}

// Fetch recent leads for auto-populating new clients
$recent_leads = [];
if (!$is_agent) {
    try {
        $recent_leads = $pdo->query("SELECT id, submission_data, created_at FROM IFW_contact_submissions ORDER BY created_at DESC LIMIT 50")->fetchAll();
    } catch (Exception $e) {}
}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-users mr-2"></i>Client Management Directory</h3>
        <p class="text-muted mb-0">Manage registered recovery clients, assign investigators, and issue portal invitations.</p>
    </div>
    <?php if (!$is_agent): ?>
        <button type="button" class="btn btn-warning font-weight-bold text-dark px-4 shadow" data-toggle="modal" data-target="#addClientModal">
            <i class="fas fa-user-plus mr-1"></i> Add New Client Account
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success font-weight-bold mb-3"><i class="fas fa-check-circle mr-2"></i>Client account added successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning font-weight-bold mb-3"><i class="fas fa-trash mr-2"></i>Client record deleted.</div>
<?php endif; ?>
<?php if (isset($_GET['status_updated'])): ?>
    <div class="alert alert-info font-weight-bold mb-3"><i class="fas fa-info-circle mr-2"></i>Case status updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['invited']) && isset($_GET['pwd'])): ?>
    <div class="alert alert-success font-weight-bold mb-3">
        <i class="fas fa-key mr-2"></i><strong>Portal Access Credentials Generated!</strong><br>
        Client ID: #<?= (int)$_GET['invited'] ?><br>
        Temporary Password: <code class="bg-dark text-warning px-2 py-1 rounded" style="font-size: 1.2rem;"><?= htmlspecialchars($_GET['pwd']) ?></code><br>
        <small class="text-dark">Please communicate this temporary password securely to the client so they can log in.</small>
    </div>
<?php endif; ?>

<div class="card shadow-lg bg-dark border-secondary">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
        <i class="fas fa-list mr-2"></i>Client Directory Overview (<?= count($clients) ?> Active Clients)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning" style="border-bottom: 2px solid rgba(254,204,86,0.3);">
                        <th>Ref ID</th>
                        <th>Client Name</th>
                        <th>Contact Information</th>
                        <th>Investigation Status</th>
                        <th>Assigned Officer</th>
                        <th>Portal Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center p-5 text-muted"><i class="fas fa-users-slash text-warning mb-2 d-block" style="font-size: 2rem;"></i>No client records registered yet. Click "Add New Client Account" above to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><span class="badge badge-secondary font-weight-bold">Ref #<?= $client['id'] ?></span></td>
                                <td><strong class="text-white"><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></strong></td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="text-warning font-weight-bold"><?= htmlspecialchars($client['email']) ?></a><br>
                                    <small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($client['phone'] ?: 'N/A') ?></small>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                        <select name="status" class="form-control form-control-sm bg-dark text-white border-secondary" style="width: 140px;" onchange="this.form.submit()">
                                            <?php foreach (['Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery'] as $s): ?>
                                                <option value="<?= $s ?>" <?= ($client['status'] ?? 'Received') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($is_agent): ?>
                                        <span class="badge badge-secondary"><?= htmlspecialchars($client['agent_name'] ?? 'Unassigned') ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="action" value="assign_agent">
                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                            <select name="agent_id" class="form-control form-control-sm bg-dark text-white border-secondary mr-2" style="width: auto;">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?= $agent['id'] ?>" <?= $client['assigned_agent_id'] == $agent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['username']) ?> (<?= ucfirst($agent['role']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($client['password_hash'])): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-dark px-2 py-1">No Access</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="chat.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-info text-white mr-1" title="Direct Messaging Workspace"><i class="fas fa-comments"></i> Chat</a>
                                    
                                    <?php if (!$is_agent): ?>
                                        <form method="POST" class="d-inline mr-1" onsubmit="return confirm('Generate a new portal login password for <?= htmlspecialchars($client['first_name']) ?>?');">
                                            <input type="hidden" name="action" value="invite_client">
                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning font-weight-bold text-dark" title="Generate Password / Invite"><i class="fas fa-key"></i> Credentials</button>
                                        </form>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete client <?= htmlspecialchars($client['first_name']) ?>?');">
                                            <input type="hidden" name="action" value="delete_client">
                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Account"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$is_agent): ?>
<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Register New Client Account</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_client">
            
            <div class="form-group bg-secondary p-3 rounded mb-4 border border-info">
                <label class="font-weight-bold text-info"><i class="fas fa-magic mr-1"></i> Auto-Populate from Leads & Enquiries</label>
                <select id="leadSelect" class="form-control bg-dark text-white border-info">
                    <option value="">-- Select a Lead (Optional) --</option>
                    <?php foreach ($recent_leads as $lead): 
                        $data = json_decode($lead['submission_data'], true);
                        $leadName = htmlspecialchars($data['name'] ?? ($data['first_name'] . ' ' . $data['last_name']) ?? 'Unknown');
                        $leadEmail = htmlspecialchars($data['email'] ?? '');
                        $dateStr = date('M j, Y g:i A', strtotime($lead['created_at']));
                    ?>
                        <option value="<?= htmlspecialchars(json_encode($data)) ?>"><?= $leadName ?> (<?= $leadEmail ?>) - <?= $dateStr ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-light mt-2">Selecting a lead will automatically fill in the fields below.</small>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">First Name <span class="text-warning">*</span></label>
                    <input type="text" name="first_name" class="form-control bg-dark text-white border-secondary" required placeholder="John">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-white">Last Name <span class="text-warning">*</span></label>
                    <input type="text" name="last_name" class="form-control bg-dark text-white border-secondary" required placeholder="Doe">
                </div>
            </div>
            <div class="mb-3">
                <label class="font-weight-bold text-white">Email Address <span class="text-warning">*</span></label>
                <input type="email" name="email" class="form-control bg-dark text-white border-secondary" required placeholder="client@example.com">
            </div>
            <div class="mb-3">
                <label class="font-weight-bold text-white">Phone Number</label>
                <input type="text" name="phone" class="form-control bg-dark text-white border-secondary" placeholder="+1 (555) 000-0000">
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Create Account</button>
          </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const leadSelect = document.getElementById('leadSelect');
    if (leadSelect) {
        leadSelect.addEventListener('change', function() {
            if (!this.value) return;
            
            try {
                const data = JSON.parse(this.value);
                const firstNameInput = document.querySelector('input[name="first_name"]');
                const lastNameInput = document.querySelector('input[name="last_name"]');
                const emailInput = document.querySelector('input[name="email"]');
                const phoneInput = document.querySelector('input[name="phone"]');
                
                if (data.email) emailInput.value = data.email;
                if (data.phone || data.phone_number) phoneInput.value = data.phone || data.phone_number;
                
                if (data.first_name) {
                    firstNameInput.value = data.first_name;
                    lastNameInput.value = data.last_name || '';
                } else if (data.name) {
                    const parts = data.name.trim().split(' ');
                    firstNameInput.value = parts[0] || '';
                    lastNameInput.value = parts.slice(1).join(' ') || '';
                }
            } catch (e) {
                console.error("Error parsing lead data", e);
            }
        });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>

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
            $default_pin = '1234';
            $hashed_pin = password_hash($default_pin, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE IFW_clients SET password_hash = ?, pin_hash = COALESCE(pin_hash, ?), is_temp_password = 1, is_first_login = 1 WHERE id = ?");
            $stmt->execute([$hashed, $hashed_pin, $client_id]);
            log_audit_action($pdo, $admin_id, 'INVITE_CLIENT', "Generated portal credentials for client #$client_id");
            
            $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM IFW_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            
            if ($client && $client['email']) {
                $portal_url = rtrim(BASE_URL, '/') . '/client/login.php';
                $client_name = htmlspecialchars(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) ?: 'Client');
                $client_email = htmlspecialchars($client['email'] ?? '');
                $client_user_id = sprintf('#IFW-%05d', $client_id);
                
                $html_body = "
                <p style='margin-top: 0;'>Hello <strong>{$client_name}</strong>,</p>
                <p>Your confidential case dossier has been formally opened with our cyber forensics and international asset recovery team under Case Reference <strong>{$client_user_id}</strong>.</p>
                <p>You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.</p>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #fecc56; border-radius: 6px; padding: 18px 20px; margin: 24px 0;'>
                    <h4 style='margin: 0 0 12px 0; color: #1e293b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;'>Your Portal Login Credentials</h4>
                    <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b; width: 160px;'><strong>Username / Email:</strong></td>
                            <td style='padding: 6px 0; color: #0f172a; font-weight: bold;'>{$client_email}</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>Temporary Password:</strong></td>
                            <td style='padding: 6px 0;'><span style='background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 15px; font-weight: bold; padding: 4px 10px; border-radius: 4px; display: inline-block;'>{$raw_password}</span></td>
                        </tr>
                    </table>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$portal_url}' style='background: #fecc56; color: #1f1b1c; text-decoration: none; font-weight: bold; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 32px; border-radius: 4px; display: inline-block; box-shadow: 0 4px 12px rgba(254, 204, 86, 0.4);'>
                        LOGIN
                    </a>
                </div>
                
                <div style='background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 16px; margin: 20px 0; font-size: 12px; color: #92400e;'>
                    <strong>Security Notice:</strong> Upon your first login, a security setup wizard will guide you to update your permanent password and configure your private 4-digit Security PIN.
                </div>
                
                <p style='color: #64748b; font-size: 12px; margin-bottom: 0;'>
                    Need assistance? Reply directly to this email or reach our 24/7 Operations Desk at <a href='mailto:investigations@ifwglobalrecovery.site' style='color: #d97706;'>investigations@ifwglobalrecovery.site</a>.
                </p>
                ";
                send_html_email($client['email'], "Welcome to IFW Global — Confidential Case Portal Access", $html_body);
            }
            header("Location: client_manager.php?invited=" . $client_id . "&pwd=" . urlencode($raw_password) . "&pin=" . urlencode($default_pin));
            exit;
        } elseif ($_POST['action'] == 'send_custom_welcome_email' && !$is_agent) {
            $client_id = (int)$_POST['client_id'];
            $subject = trim($_POST['email_subject'] ?? 'Welcome to IFW Global — Confidential Case Portal Access');
            $custom_note = trim($_POST['custom_note'] ?? '');
            $email_intro = trim($_POST['email_intro'] ?? '');
            $email_portal_msg = trim($_POST['email_portal_msg'] ?? '');
            $include_creds = !empty($_POST['include_credentials']);
            
            $stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            
            if ($client && $client['email']) {
                $raw_password = '';
                $default_pin = '1234';
                if ($include_creds) {
                    $raw_password = bin2hex(random_bytes(4));
                    $hashed = password_hash($raw_password, PASSWORD_DEFAULT);
                    $hashed_pin = password_hash($default_pin, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE IFW_clients SET password_hash = ?, pin_hash = ?, is_temp_password = 1, is_first_login = 1 WHERE id = ?");
                    $stmt->execute([$hashed, $hashed_pin, $client_id]);
                }
                
                $portal_url = rtrim(BASE_URL, '/') . '/client/login.php';
                $client_name = htmlspecialchars(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) ?: 'Client');
                $client_email = htmlspecialchars($client['email'] ?? '');
                $client_ref_id = sprintf('#IFW-%05d', $client['id']);
                
                if (empty($email_intro)) {
                    $email_intro = "Your confidential case dossier has been formally opened with our cyber forensics and international asset recovery team under Case Reference {$client_ref_id}.";
                }
                if (empty($email_portal_msg)) {
                    $email_portal_msg = "You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.";
                }
                
                $creds_html = '';
                if ($include_creds) {
                    $creds_html = "
                    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #fecc56; border-radius: 6px; padding: 18px 20px; margin: 24px 0;'>
                        <h4 style='margin: 0 0 12px 0; color: #1e293b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;'>Your Portal Login Credentials</h4>
                        <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; width: 160px;'><strong>Username / Email:</strong></td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: bold;'>{$client_email}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Temporary Password:</strong></td>
                                <td style='padding: 6px 0;'><span style='background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 15px; font-weight: bold; padding: 4px 10px; border-radius: 4px; display: inline-block;'>{$raw_password}</span></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$portal_url}' style='background: #fecc56; color: #1f1b1c; text-decoration: none; font-weight: bold; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 32px; border-radius: 4px; display: inline-block; box-shadow: 0 4px 12px rgba(254, 204, 86, 0.4);'>
                            LOGIN
                        </a>
                    </div>
                    
                    <div style='background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 16px; margin: 20px 0; font-size: 12px; color: #92400e;'>
                        <strong>Security Notice:</strong> Upon your first login, a security setup wizard will guide you to update your permanent password and configure your private 4-digit Security PIN.
                    </div>
                    ";
                }
                
                $custom_note_html = '';
                if (!empty($custom_note)) {
                    $custom_note_html = "
                    <div style='background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; padding: 14px 18px; margin: 20px 0; font-size: 14px; color: #1e3a8a;'>
                        <strong>Investigator Case Briefing:</strong><br>
                        " . nl2br(htmlspecialchars($custom_note)) . "
                    </div>
                    ";
                }
                
                $html_body = "
                <p style='margin-top: 0; font-size: 15px;'>Dear <strong>{$client_name}</strong>,</p>
                <p>Welcome to <strong>IFW Global</strong>. " . nl2br(htmlspecialchars($email_intro)) . "</p>
                
                {$custom_note_html}
                
                <p>" . nl2br(htmlspecialchars($email_portal_msg)) . "</p>
                
                {$creds_html}
                
                <p style='color: #64748b; font-size: 12px; margin-bottom: 0;'>
                    Need urgent assistance? Reply directly to this email or reach our 24/7 Operations Desk at <a href='mailto:investigations@ifwglobalrecovery.site' style='color: #d97706;'>investigations@ifwglobalrecovery.site</a>.
                </p>
                ";
                
                send_html_email($client['email'], $subject, $html_body);
                log_audit_action($pdo, $admin_id, 'SEND_WELCOME_EMAIL', "Sent custom welcome email & credentials to client #$client_id ({$client['email']})");
                
                header("Location: client_manager.php?welcome_sent=1&cid=" . $client_id . ($include_creds ? "&pwd=" . urlencode($raw_password) : ""));
                exit;
            }
        } elseif ($_POST['action'] == 'update_status') {
            $status = in_array($_POST['status'], ['Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery']) ? $_POST['status'] : 'Received';
            $client_id = (int)$_POST['client_id'];
            $stmt = $pdo->prepare("UPDATE IFW_clients SET status = ? WHERE id = ?");
            $stmt->execute([$status, $client_id]);
            log_audit_action($pdo, $admin_id, 'UPDATE_STATUS', "Changed status of client #$client_id to '$status'");
            header("Location: client_manager.php?status_updated=1");
            exit;
        } elseif ($_POST['action'] == 'impersonate_client' && !$is_agent) {
            $client_id = (int)$_POST['client_id'];
            $stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $target_client = $stmt->fetch();
            
            if ($target_client) {
                // Save admin session state
                $_SESSION['impersonator_admin'] = [
                    'id' => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_username'] ?? 'admin',
                    'role' => $_SESSION['admin_role'] ?? 'admin',
                    'full_name' => $_SESSION['admin_name'] ?? 'Super Admin'
                ];
                
                // Set Client Portal session
                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_portal_id'] = $target_client['id'];
                $_SESSION['client_id'] = $target_client['id'];
                $_SESSION['client_name'] = trim(($target_client['first_name'] ?? '') . ' ' . ($target_client['last_name'] ?? '')) ?: $target_client['email'];
                $_SESSION['client_email'] = $target_client['email'];
                $_SESSION['role'] = 'client';
                $_SESSION['pin_verified'] = true;
                $_SESSION['2fa_verified'] = true;
                $_SESSION['is_impersonating'] = true;
                
                log_audit_action($pdo, $admin_id, 'IMPERSONATE_CLIENT', "Super Admin launched impersonation session for client #{$target_client['id']} ({$target_client['email']})");
                
                header("Location: ../client/dashboard.php");
                exit;
            }
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
        <i class="fas fa-list mr-2"></i>Client Directory Overview (<span id="clientDirectoryCount"><?= count($clients) ?></span> Active Clients)
        <span class="badge badge-success ml-2 d-none d-md-inline" id="clientLiveSyncBadge" style="font-size:10px;"><i class="fas fa-circle mr-1" style="font-size:7px;"></i> Live</span>
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
                <tbody id="clientDirectoryBody">
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center p-5 text-muted"><i class="fas fa-users-slash text-warning mb-2 d-block" style="font-size: 2rem;"></i>No client records registered yet. Click "Add New Client Account" above to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td data-label="Ref ID"><span class="badge badge-secondary font-weight-bold">Ref #<?= $client['id'] ?></span></td>
                                <td data-label="Client Name"><strong class="text-white"><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></strong></td>
                                <td data-label="Contact Information">
                                    <a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="text-warning font-weight-bold"><?= htmlspecialchars($client['email']) ?></a><br>
                                    <small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($client['phone'] ?: 'N/A') ?></small>
                                </td>
                                <td data-label="Investigation Status">
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
                                <td data-label="Assigned Officer">
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
                                <td data-label="Portal Status">
                                    <?php if (!empty($client['password_hash'])): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-dark px-2 py-1">No Access</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="d-inline-flex flex-wrap align-items-center" style="gap: 4px;">
                                        <?php if (!$is_agent): ?>
                                            <form method="POST" class="d-inline mb-0" onsubmit="return confirm('Launch secure impersonation session as <?= htmlspecialchars($client['first_name']) ?> <?= htmlspecialchars($client['last_name']) ?>?');">
                                                <input type="hidden" name="action" value="impersonate_client">
                                                <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success font-weight-bold px-2" title="Log into Client Portal as this client">
                                                    <i class="fas fa-user-secret"></i><span class="d-none d-xl-inline ml-1">Login As</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="chat.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-info text-white px-2" title="Direct Messaging Workspace">
                                            <i class="fas fa-comments"></i><span class="d-none d-xl-inline ml-1">Chat</span>
                                        </a>
                                        
                                        <?php if (!$is_agent): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold px-2" title="Preview & Send Custom Welcome Email" onclick="openWelcomeEmailModal(<?= htmlspecialchars(json_encode([
                                                'id' => $client['id'],
                                                'ref_id' => sprintf('#IFW-%05d', $client['id']),
                                                'name' => trim($client['first_name'] . ' ' . $client['last_name']),
                                                'email' => $client['email'],
                                                'phone' => $client['phone'] ?? '',
                                                'agent_name' => $client['agent_name'] ?? 'Senior Forensic Agent'
                                            ])) ?>)">
                                                <i class="fas fa-paper-plane"></i><span class="d-none d-xl-inline ml-1">Email</span>
                                            </button>

                                            <form method="POST" class="d-inline mb-0" onsubmit="return confirm('Generate a new portal login password for <?= htmlspecialchars($client['first_name']) ?>?');">
                                                <input type="hidden" name="action" value="invite_client">
                                                <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-warning font-weight-bold text-dark px-2" title="Generate Password / Credentials">
                                                    <i class="fas fa-key"></i><span class="d-none d-xl-inline ml-1">Credentials</span>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete client <?= htmlspecialchars($client['first_name']) ?>?');">
                                                <input type="hidden" name="action" value="delete_client">
                                                <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger px-2" title="Delete Account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
<!-- PREVIEW & SEND WELCOME EMAIL MODAL -->
<div class="modal fade" id="welcomeEmailModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-warning shadow-24" style="border-radius: 14px; overflow: hidden; border: 2px solid #fecc56;">
      <div class="modal-header border-secondary py-3 px-4 d-flex justify-content-between align-items-center" style="background: #11151e;">
        <div class="d-flex align-items-center">
            <i class="fas fa-envelope-open-text text-warning fa-lg mr-3"></i>
            <div>
                <h5 class="modal-title text-warning font-weight-bold mb-0">Send Official Welcome Email &amp; Portal Credentials</h5>
                <small class="text-muted">Live visual preview with IFW Global official logo and cryptographic security credentials.</small>
            </div>
        </div>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      
      <form method="POST" id="welcomeEmailForm" onsubmit="document.getElementById('sendWelcomeBtn').innerHTML='<i class=\'fas fa-spinner fa-spin mr-2\'></i>Dispatching Email...'; document.getElementById('sendWelcomeBtn').disabled=true;">
        <input type="hidden" name="action" value="send_custom_welcome_email">
        <input type="hidden" name="client_id" id="modalClientId">
        
        <div class="modal-body p-4">
            <div class="row">
                <!-- Left Column: Form Controls -->
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="p-3 rounded border border-secondary mb-3" style="background: #1a202c;">
                        <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-user mr-2"></i>Recipient Details</h6>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Client Name:</span>
                            <strong class="text-white" id="modalClientName">John Doe</strong>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Recipient Email:</span>
                            <strong class="text-warning" id="modalClientEmail">client@example.com</strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Case Reference:</span>
                            <span class="badge badge-secondary font-weight-bold" id="modalClientRef">#IFW-00000</span>
                        </div>
                    </div>
                                       <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-heading mr-1 text-warning"></i> Email Subject</label>
                        <input type="text" name="email_subject" id="modalEmailSubject" class="form-control bg-dark text-white border-secondary" value="Welcome to IFW Global — Confidential Case Portal Access" required oninput="updateLiveEmailPreview()">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-paragraph mr-1 text-warning"></i> Welcome Intro Message</label>
                        <textarea name="email_intro" id="modalEmailIntro" class="form-control bg-dark text-white border-secondary" rows="2" oninput="updateLiveEmailPreview()"></textarea>
                        <small class="text-muted">Opening paragraph shown immediately after greeting.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-pen-nib mr-1 text-warning"></i> Investigator Case Note / Briefing</label>
                        <textarea name="custom_note" id="modalCustomNote" class="form-control bg-dark text-white border-secondary" rows="3" oninput="updateLiveEmailPreview()">Your case has been formally assigned to our Senior Cyber Forensics & Asset Recovery Division. Preliminary on-chain trace protocols and cross-border intelligence filings are actively underway under strict chain-of-custody protocols.</textarea>
                        <small class="text-muted">Prefilled default briefing — Edit, leave as is, or clear to omit.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-shield-alt mr-1 text-warning"></i> Portal Access Description</label>
                        <textarea name="email_portal_msg" id="modalEmailPortalMsg" class="form-control bg-dark text-white border-secondary" rows="2" oninput="updateLiveEmailPreview()">You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.</textarea>
                    </div>

                    <div class="custom-control custom-checkbox mb-3 p-2 rounded" style="background: rgba(254, 204, 86, 0.08); border: 1px solid rgba(254, 204, 86, 0.2);">
                        <input type="checkbox" class="custom-control-input" id="includeCredsCheckbox" name="include_credentials" value="1" checked onchange="updateLiveEmailPreview()">
                        <label class="custom-control-label text-warning font-weight-bold small" for="includeCredsCheckbox">
                            Generate &amp; Attach Portal Credentials (Username &amp; Temp Password)
                        </label>
                        <small class="text-light d-block mt-1" style="font-size: 11px;">Generates temporary credentials and flags profile for mandatory first-login security setup.</small>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <i class="fas fa-info-circle mr-1"></i> The recipient will receive this 256-bit encrypted dispatch from <strong>notifications@ifwglobalrecovery.site</strong>.
                    </div>
                </div>

                <!-- Right Column: Live Email Preview -->
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="font-weight-bold text-warning small"><i class="fas fa-desktop mr-1"></i> Live Visual Email Preview</span>
                        <span class="badge badge-success" style="font-size: 10px;"><i class="fas fa-eye mr-1"></i> Exact Client View</span>
                    </div>

                    <!-- Visual Email Canvas Container -->
                    <div id="emailPreviewCanvas" class="rounded border border-secondary p-3 shadow-inner" style="background: #0b0e14; max-height: 520px; overflow-y: auto;">
                        <!-- Email Container -->
                        <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; border-top: 4px solid #fecc56; font-family: Arial, sans-serif; color: #1e293b;">
                            <!-- Email Header with Logo -->
                            <div style="background: #111827; padding: 26px 20px; text-align: center; border-bottom: 2px solid #fecc56;">
                                <img src="/media/logos/logo.svg" alt="IFW Global" width="180" style="max-height: 52px; width: auto; height: auto; display: block; margin: 0 auto 8px auto;" />
                                <div style="color: #cbd5e1; font-size: 10px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">Private Intelligence &amp; Asset Recovery</div>
                            </div>
                            
                            <!-- Email Content Body -->
                            <div style="padding: 22px 20px; font-size: 13px; line-height: 1.6; color: #334155;">
                                <p style="margin-top: 0; font-size: 14px;">Dear <strong id="previewClientName" style="color: #0f172a;">Client</strong>,</p>
                                <p style="margin-bottom: 14px;">Welcome to <strong>IFW Global</strong>. <span id="previewIntroText">Your confidential case dossier has been formally opened with our cyber forensics and international asset recovery team.</span></p>
                                
                                <div id="previewCustomNoteBlock" style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; padding: 10px 14px; margin: 14px 0; font-size: 12.5px; color: #1e3a8a;">
                                    <strong>Investigator Case Briefing:</strong><br>
                                    <span id="previewCustomNoteText">Your case has been formally assigned to our Senior Cyber Forensics &amp; Asset Recovery Division. Preliminary on-chain trace protocols and cross-border intelligence filings are actively underway under strict chain-of-custody protocols.</span>
                                </div>
                                
                                <p id="previewPortalMsgText" style="margin-bottom: 14px;">You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.</p>
                                
                                <!-- Credentials Box -->
                                <div id="previewCredsBlock" style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #fecc56; border-radius: 6px; padding: 14px 16px; margin: 16px 0;">
                                    <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold;">Your Portal Login Credentials</h4>
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b; width: 140px;"><strong>Username / Email:</strong></td>
                                            <td style="padding: 4px 0; color: #0f172a; font-weight: bold;" id="previewEmail">client@example.com</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b;"><strong>Temporary Password:</strong></td>
                                            <td style="padding: 4px 0;"><span style="background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 13px; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;">•••••••• (Auto-generated)</span></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- CTA Button -->
                                <div style="text-align: center; margin: 20px 0;">
                                    <span style="background: #fecc56; color: #1f1b1c; text-decoration: none; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 28px; border-radius: 4px; display: inline-block; box-shadow: 0 4px 12px rgba(254, 204, 86, 0.4);">
                                        LOGIN
                                    </span>
                                </div>
                                
                                <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 10px 12px; margin: 14px 0; font-size: 11.5px; color: #92400e;">
                                    <strong>Security Notice:</strong> Upon your first login, a security setup wizard will guide you to update your permanent password and configure your private 4-digit Security PIN.
                                </div>
                                
                                <p style="color: #64748b; font-size: 11px; margin-bottom: 0;">
                                    Need urgent assistance? Reply directly to this email or reach our 24/7 Operations Desk at <span style="color: #d97706;">investigations@ifwglobalrecovery.site</span>.
                                </p>
                            </div>
                            
                            <!-- Email Footer -->
                            <div style="background: #f8fafc; padding: 14px 20px; text-align: center; font-size: 10.5px; color: #64748b; border-top: 1px solid #e2e8f0; line-height: 1.4;">
                                <strong>IFW Global Cyber &amp; Financial Crime Investigation Division</strong><br>
                                This is an automated encrypted dispatch from the IFW Global Client Recovery Portal. All contents confidential.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer border-secondary py-3 px-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary font-weight-bold px-4" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow" id="sendWelcomeBtn">
                <i class="fas fa-paper-plane mr-2"></i> Send Welcome Email Now
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

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
var activeWelcomeClient = null;

function openWelcomeEmailModal(clientData) {
    activeWelcomeClient = clientData;
    document.getElementById('modalClientId').value = clientData.id;
    document.getElementById('modalClientName').textContent = clientData.name || 'Client';
    document.getElementById('modalClientEmail').textContent = clientData.email || '';
    document.getElementById('modalClientRef').textContent = clientData.ref_id || ('#IFW-' + clientData.id);
    
    var refStr = clientData.ref_id || ('#IFW-' + clientData.id);
    document.getElementById('modalEmailIntro').value = "Your confidential case dossier has been formally opened with our cyber forensics and international asset recovery team under Case Reference " + refStr + ".";
    
    document.getElementById('previewClientName').textContent = clientData.name || 'Client';
    document.getElementById('previewEmail').textContent = clientData.email || '';
    
    updateLiveEmailPreview();
    $('#welcomeEmailModal').modal('show');
}

function updateLiveEmailPreview() {
    var intro = document.getElementById('modalEmailIntro').value.trim();
    document.getElementById('previewIntroText').textContent = intro || "Your confidential case dossier has been formally opened with our cyber forensics and international asset recovery team.";
    
    var note = document.getElementById('modalCustomNote').value.trim();
    var noteBlock = document.getElementById('previewCustomNoteBlock');
    var noteText = document.getElementById('previewCustomNoteText');
    if (note) {
        noteText.textContent = note;
        noteBlock.style.display = 'block';
    } else {
        noteBlock.style.display = 'none';
    }
    
    var portalMsg = document.getElementById('modalEmailPortalMsg').value.trim();
    document.getElementById('previewPortalMsgText').textContent = portalMsg || "You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.";
    
    var credsChecked = document.getElementById('includeCredsCheckbox').checked;
    var credsBlock = document.getElementById('previewCredsBlock');
    if (credsChecked) {
        credsBlock.style.display = 'block';
    } else {
        credsBlock.style.display = 'none';
    }
}

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

    // Auto-refresh client directory when new clients or updates appear (every 20s)
    var clientPollSince = <?= time() ?>;
    setInterval(function() {
        fetch('/api/poll_clients.php?since=' + clientPollSince)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                if (data.latest_ts) clientPollSince = Math.max(clientPollSince, data.latest_ts);
                var countEl = document.getElementById('clientDirectoryCount');
                if (countEl && typeof data.total === 'number') countEl.textContent = data.total;
                if (data.changed) {
                    if (typeof toastr !== 'undefined') {
                        toastr.info('Refreshing client list with latest updates…', '🔄 Live Sync');
                    }
                    setTimeout(function() { location.reload(); }, 600);
                }
            }
        })
        .catch(function() {});
    }, 20000);
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>

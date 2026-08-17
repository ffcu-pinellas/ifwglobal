<?php
// admin/submissions.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff']);
$admin_id = $_SESSION['admin_id'];

// Handle assignment & welcome email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'assign_lead' && !$is_agent) {
            $agent_id = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
            $sub_id = (int)$_POST['submission_id'];
            $stmt = $pdo->prepare("UPDATE IFW_contact_submissions SET assigned_agent_id = ? WHERE id = ?");
            $stmt->execute([$agent_id, $sub_id]);
            header("Location: submissions.php");
            exit;
        } elseif ($_POST['action'] == 'convert_and_send_welcome' && !$is_agent) {
            $sub_id = (int)$_POST['submission_id'];
            $subject = trim($_POST['email_subject'] ?? 'Welcome to IFW Global — Confidential Case Portal Access');
            $custom_note = trim($_POST['custom_note'] ?? '');
            $include_creds = !empty($_POST['include_credentials']);
            $agent_id = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
            
            $stmt = $pdo->prepare("SELECT * FROM IFW_contact_submissions WHERE id = ?");
            $stmt->execute([$sub_id]);
            $sub = $stmt->fetch();
            
            if ($sub) {
                $data = json_decode($sub['submission_data'], true);
                $first_name = trim($data['first_name'] ?? '');
                $last_name = trim($data['last_name'] ?? '');
                $email = trim($data['email'] ?? '');
                $phone = trim($data['phone'] ?? '');
                
                if (empty($first_name) && !empty($data['name'])) {
                    $parts = explode(' ', trim($data['name']), 2);
                    $first_name = $parts[0];
                    $last_name = $parts[1] ?? '';
                }
                if (empty($first_name)) {
                    $first_name = 'Client';
                }
                
                if (empty($email)) {
                    header("Location: submissions.php?err=no_email");
                    exit;
                }
                
                // Self-healing check
                try {
                    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) NULL");
                    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS is_temp_password TINYINT(1) DEFAULT 0");
                    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS is_first_login TINYINT(1) DEFAULT 0");
                } catch (Exception $e) {}
                
                $raw_password = bin2hex(random_bytes(4));
                $hashed_pwd = password_hash($raw_password, PASSWORD_DEFAULT);
                $default_pin = '1234';
                $hashed_pin = password_hash($default_pin, PASSWORD_DEFAULT);
                
                // Check if client already exists
                $stmt_chk = $pdo->prepare("SELECT id FROM IFW_clients WHERE email = ?");
                $stmt_chk->execute([$email]);
                $existing = $stmt_chk->fetch();
                
                if ($existing) {
                    $client_id = $existing['id'];
                    $pdo->prepare("UPDATE IFW_clients SET password_hash = ?, pin_hash = ?, is_temp_password = 1, is_first_login = 1, assigned_agent_id = COALESCE(assigned_agent_id, ?) WHERE id = ?")
                        ->execute([$hashed_pwd, $hashed_pin, $agent_id, $client_id]);
                } else {
                    $pdo->prepare("INSERT INTO IFW_clients (first_name, last_name, email, phone, assigned_agent_id, password_hash, pin_hash, is_temp_password, is_first_login, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 'Investigating')")
                        ->execute([$first_name, $last_name, $email, $phone, $agent_id, $hashed_pwd, $hashed_pin]);
                    $client_id = $pdo->lastInsertId();
                }
                
                if ($agent_id) {
                    $pdo->prepare("UPDATE IFW_contact_submissions SET assigned_agent_id = ? WHERE id = ?")->execute([$agent_id, $sub_id]);
                }
                
                $portal_url = rtrim(BASE_URL, '/') . '/client/login.php';
                $client_full_name = htmlspecialchars(trim($first_name . ' ' . $last_name));
                $client_email = htmlspecialchars($email);
                $client_ref_id = sprintf('#IFW-%05d', $client_id);
                
                $creds_html = '';
                if ($include_creds) {
                    $creds_html = "
                    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #fecc56; border-radius: 6px; padding: 18px 20px; margin: 24px 0;'>
                        <h4 style='margin: 0 0 12px 0; color: #1e293b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;'>Your Portal Login Credentials</h4>
                        <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; width: 160px;'><strong>User ID:</strong></td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: bold;'>{$client_ref_id}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Username / Email:</strong></td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: bold;'>{$client_email}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Temporary Password:</strong></td>
                                <td style='padding: 6px 0;'><span style='background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 15px; font-weight: bold; padding: 4px 10px; border-radius: 4px; display: inline-block;'>{$raw_password}</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Default Security PIN:</strong></td>
                                <td style='padding: 6px 0;'><span style='background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 15px; font-weight: bold; padding: 4px 10px; border-radius: 4px; display: inline-block;'>{$default_pin}</span></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$portal_url}' style='background: #fecc56; color: #1f1b1c; text-decoration: none; font-weight: bold; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 32px; border-radius: 4px; display: inline-block; box-shadow: 0 4px 12px rgba(254, 204, 86, 0.4);'>
                            LOGIN
                        </a>
                    </div>
                    
                    <div style='background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 16px; margin: 20px 0; font-size: 12px; color: #92400e;'>
                        <strong>Security Notice:</strong> Upon your first login, a security setup wizard will guide you to update your permanent password and configure your private 4-digit Security PIN (replacing default <code>1234</code>).
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
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                    <div style='background: #1f1b1c; padding: 24px; text-align: center; border-bottom: 3px solid #fecc56;'>
                        <h2 style='color: #fecc56; margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px;'>IFW Global Intelligence</h2>
                        <p style='color: #cbd5e1; margin: 6px 0 0 0; font-size: 13px;'>Confidential Case Dossier &amp; Asset Recovery Command</p>
                    </div>
                    <div style='padding: 24px 28px; color: #334155; font-size: 14px; line-height: 1.6;'>
                        <p style='margin-top: 0;'>Dear <strong>{$client_full_name}</strong>,</p>
                        <p>Thank you for reaching out to <strong>IFW Global</strong>. Your case enquiry has been processed, and a confidential file has been formally registered under Reference <strong>{$client_ref_id}</strong>.</p>
                        
                        {$custom_note_html}
                        
                        <p>You can access our 256-bit encrypted Client Portal 24/7 to keep track of the investigation progress, inspect court filings, e-sign legal documents, and communicate directly with your lead investigator.</p>
                        
                        {$creds_html}
                        
                        <p style='color: #64748b; font-size: 12px; margin-bottom: 0;'>
                            Need urgent assistance? Reply directly to this email or reach our 24/7 Operations Desk at <a href='mailto:investigations@ifwglobalrecovery.site' style='color: #d97706;'>investigations@ifwglobalrecovery.site</a>.
                        </p>
                    </div>
                    <div style='background: #f1f5f9; padding: 14px 28px; text-align: center; color: #94a3b8; font-size: 11px; border-top: 1px solid #e2e8f0;'>
                        &copy; " . date('Y') . " IFW Global Intelligence. All rights reserved. 256-Bit Encrypted Portal.
                    </div>
                </div>
                ";
                
                send_html_email($email, $subject, $html_body);
                log_audit_action($pdo, $admin_id, 'SEND_WELCOME_EMAIL', "Converted lead #$sub_id and sent welcome credentials to $email (Client #$client_id)");
                
                header("Location: submissions.php?welcome_sent=1&cid=" . $client_id);
                exit;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$has_all_access = has_permission('view_all_leads');

if (!$has_all_access && $is_agent) {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM IFW_contact_submissions WHERE assigned_agent_id = :aid");
    $stmt_count->execute([':aid' => $admin_id]);
    $total_submissions = $stmt_count->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT c.*, u.username as agent_name FROM IFW_contact_submissions c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id WHERE c.assigned_agent_id = :aid ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':aid', $admin_id, PDO::PARAM_INT);
} else {
    $total_submissions = $pdo->query("SELECT COUNT(*) FROM IFW_contact_submissions")->fetchColumn();
    $stmt = $pdo->prepare("SELECT c.*, u.username as agent_name FROM IFW_contact_submissions c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset");
}

$total_pages = ceil($total_submissions / $per_page);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll();

$agents = [];
if (!$is_agent || $has_all_access) {
    try {
        $agents = $pdo->query("SELECT id, username, role FROM IFW_users ORDER BY username ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-inbox mr-2"></i>Form Submissions &amp; Leads</h3>
            <p class="text-muted mb-0">View all client enquiries and dynamic contact form submissions, or dispatch official portal welcome invites.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['welcome_sent'])): ?>
    <div class="alert alert-success font-weight-bold mb-3">
        <i class="fas fa-check-circle mr-2"></i><strong>Official Welcome Email &amp; Credentials Sent!</strong> The lead has been registered as client #<?= (int)$_GET['cid'] ?> and credentials have been dispatched.
    </div>
<?php endif; ?>

<div class="card shadow-sm border-secondary mb-4">
    <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center justify-content-between">
        <span class="font-weight-bold">Total Submissions: <?php echo $total_submissions; ?></span>
    </div>
    <div class="card-body bg-dark text-white p-0">
        <?php if (empty($submissions)): ?>
            <div class="p-4 text-center text-muted">No form submissions received yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead style="background-color: #2a2526; color: #fecc56;">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): 
                            $data = json_decode($sub['submission_data'], true);
                            $leadName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['name'] ?? 'Lead');
                            $leadEmail = $data['email'] ?? '';
                            $leadPhone = $data['phone'] ?? ($data['phone_number'] ?? '');
                        ?>
                            <tr>
                                <td>#<?= $sub['id'] ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($sub['created_at'])) ?></td>
                                <td><strong class="text-white"><?= htmlspecialchars($leadName) ?></strong></td>
                                <td><a href="mailto:<?= htmlspecialchars($leadEmail) ?>" class="text-warning"><?= htmlspecialchars($leadEmail) ?></a></td>
                                <td><?= htmlspecialchars($leadPhone ?: 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($data['location'])): ?>
                                        <i class="fas fa-map-marker-alt text-danger mr-1"></i> <?= htmlspecialchars($data['location']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_agent && !$has_all_access): ?>
                                        <span class="badge badge-secondary"><?= htmlspecialchars($sub['agent_name'] ?? 'Unassigned') ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="action" value="assign_lead">
                                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                            <select name="agent_id" class="form-control form-control-sm bg-dark text-white border-secondary mr-2" style="width: auto;">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?= $agent['id'] ?>" <?= $sub['assigned_agent_id'] == $agent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['username']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-inline-flex flex-wrap align-items-center" style="gap: 4px;">
                                        <button class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#viewModal<?= $sub['id'] ?>" title="View Form Data">
                                            <i class="fas fa-eye mr-1"></i>Details
                                        </button>

                                        <?php if (!$is_agent && !empty($leadEmail)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold" title="Send Official Welcome & Credentials with Live Preview" onclick="openLeadWelcomeModal(<?= htmlspecialchars(json_encode([
                                                'id' => $sub['id'],
                                                'name' => $leadName,
                                                'email' => $leadEmail,
                                                'phone' => $leadPhone,
                                                'agent_id' => $sub['assigned_agent_id'] ?? ''
                                            ])) ?>)">
                                                <i class="fas fa-paper-plane mr-1"></i>Welcome Email
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- View Details Modal -->
                                    <div class="modal fade" id="viewModal<?= $sub['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                            <div class="modal-content bg-dark border-secondary text-white">
                                                <div class="modal-header border-secondary text-warning">
                                                    <h5 class="modal-title font-weight-bold">Submission #<?= $sub['id'] ?> Details</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <table class="table table-dark table-bordered">
                                                        <?php foreach ($data as $k => $v): ?>
                                                            <tr>
                                                                <th style="width: 30%;" class="text-warning"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $k))) ?></th>
                                                                <td><?= nl2br(htmlspecialchars(is_array($v) ? implode(', ', $v) : $v)) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </table>
                                                </div>
                                                <div class="modal-footer border-secondary">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$is_agent): ?>
<!-- PREVIEW & SEND LEAD WELCOME EMAIL MODAL -->
<div class="modal fade" id="leadWelcomeEmailModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-warning shadow-24" style="border-radius: 14px; overflow: hidden; border: 2px solid #fecc56;">
      <div class="modal-header border-secondary py-3 px-4 d-flex justify-content-between align-items-center" style="background: #11151e;">
        <div class="d-flex align-items-center">
            <i class="fas fa-envelope-open-text text-warning fa-lg mr-3"></i>
            <div>
                <h5 class="modal-title text-warning font-weight-bold mb-0">Dispatch Lead Welcome &amp; Portal Credentials</h5>
                <small class="text-muted">Converts enquiry lead to active client and sends an official email with logo &amp; cryptographic credentials.</small>
            </div>
        </div>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      
      <form method="POST" id="leadWelcomeEmailForm" onsubmit="document.getElementById('sendLeadWelcomeBtn').innerHTML='<i class=\'fas fa-spinner fa-spin mr-2\'></i>Provisioning & Dispatching...'; document.getElementById('sendLeadWelcomeBtn').disabled=true;">
        <input type="hidden" name="action" value="convert_and_send_welcome">
        <input type="hidden" name="submission_id" id="modalSubId">
        <input type="hidden" name="agent_id" id="modalSubAgentId">
        
        <div class="modal-body p-4">
            <div class="row">
                <!-- Left Column: Form Controls -->
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="p-3 rounded border border-secondary mb-3" style="background: #1a202c;">
                        <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-user mr-2"></i>Lead Information</h6>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Lead Name:</span>
                            <strong class="text-white" id="modalLeadName">John Doe</strong>
                        </div>
                        <div class="mb-2 d-flex justify-content-between small">
                            <span class="text-muted">Target Email:</span>
                            <strong class="text-warning" id="modalLeadEmail">lead@example.com</strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Lead Submission:</span>
                            <span class="badge badge-secondary font-weight-bold" id="modalLeadRef">#LEAD-00000</span>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-heading mr-1 text-warning"></i> Email Subject</label>
                        <input type="text" name="email_subject" id="modalLeadEmailSubject" class="form-control bg-dark text-white border-secondary" value="Welcome to IFW Global — Confidential Case Portal Access" required oninput="updateLeadLiveEmailPreview()">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-white small"><i class="fas fa-pen-nib mr-1 text-warning"></i> Optional Investigator Case Note</label>
                        <textarea name="custom_note" id="modalLeadCustomNote" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="e.g. Thank you for your inquiry. Our forensic intelligence team has reviewed your claim and initiated trace protocols." oninput="updateLeadLiveEmailPreview()"></textarea>
                        <small class="text-muted">Included as a highlighted briefing block inside the email.</small>
                    </div>

                    <div class="custom-control custom-checkbox mb-3 p-2 rounded" style="background: rgba(254, 204, 86, 0.08); border: 1px solid rgba(254, 204, 86, 0.2);">
                        <input type="checkbox" class="custom-control-input" id="leadIncludeCredsCheckbox" name="include_credentials" value="1" checked onchange="updateLeadLiveEmailPreview()">
                        <label class="custom-control-label text-warning font-weight-bold small" for="leadIncludeCredsCheckbox">
                            Generate &amp; Attach Portal Credentials (ID, Password, Default PIN 1234)
                        </label>
                        <small class="text-light d-block mt-1" style="font-size: 11px;">Automatically creates client profile and provisions temporary password with default PIN <code>1234</code>.</small>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <i class="fas fa-info-circle mr-1"></i> The recipient will receive this 256-bit encrypted notification from <strong>notifications@ifwglobalrecovery.site</strong>.
                    </div>
                </div>

                <!-- Right Column: Live Email Preview -->
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="font-weight-bold text-warning small"><i class="fas fa-desktop mr-1"></i> Live Visual Email Preview</span>
                        <span class="badge badge-success" style="font-size: 10px;"><i class="fas fa-eye mr-1"></i> Exact Client View</span>
                    </div>

                    <!-- Visual Email Canvas Container -->
                    <div class="rounded border border-secondary p-3 shadow-inner" style="background: #0b0e14; max-height: 480px; overflow-y: auto;">
                        <!-- Email Container -->
                        <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; border-top: 4px solid #fecc56; font-family: Arial, sans-serif; color: #1e293b;">
                            <!-- Email Header with Logo -->
                            <div style="background: #111827; padding: 20px 16px; text-align: center; border-bottom: 2px solid #fecc56;">
                                <img src="/media/logos/logo.svg" alt="IFW Global" style="max-height: 38px; display: block; margin: 0 auto 6px auto;" onerror="this.style.display='none'; document.getElementById('leadPreviewFallbackLogo').style.display='block';">
                                <div id="leadPreviewFallbackLogo" style="display:none; color:#fecc56; font-weight:bold; font-size:18px; letter-spacing:1px;">IFW GLOBAL</div>
                                <div style="color: #cbd5e1; font-size: 9px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">Private Intelligence &amp; Asset Recovery</div>
                            </div>
                            
                            <!-- Email Content Body -->
                            <div style="padding: 22px 20px; font-size: 13px; line-height: 1.6; color: #334155;">
                                <p style="margin-top: 0; font-size: 14px;">Dear <strong id="leadPreviewClientName" style="color: #0f172a;">Client</strong>,</p>
                                <p style="margin-bottom: 14px;">Thank you for reaching out to <strong>IFW Global</strong>. Your case enquiry has been processed, and a confidential file has been formally registered under Reference <strong id="leadPreviewCaseRef" style="color: #d97706;">#IFW-PORTAL</strong>.</p>
                                
                                <div id="leadPreviewCustomNoteBlock" style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; padding: 10px 14px; margin: 14px 0; font-size: 12.5px; color: #1e3a8a; display: none;">
                                    <strong>Investigator Case Briefing:</strong><br>
                                    <span id="leadPreviewCustomNoteText"></span>
                                </div>
                                
                                <p style="margin-bottom: 14px;">You can access our 256-bit encrypted Client Portal 24/7 to track live blockchain telemetry, inspect subpoena filings, e-sign legal documents, and communicate directly with your lead investigator.</p>
                                
                                <!-- Credentials Box -->
                                <div id="leadPreviewCredsBlock" style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #fecc56; border-radius: 6px; padding: 14px 16px; margin: 16px 0;">
                                    <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold;">Your Portal Login Credentials</h4>
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b; width: 140px;"><strong>User ID:</strong></td>
                                            <td style="padding: 4px 0; color: #0f172a; font-weight: bold;" id="leadPreviewUserId">#IFW-AUTO</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b;"><strong>Username / Email:</strong></td>
                                            <td style="padding: 4px 0; color: #0f172a; font-weight: bold;" id="leadPreviewEmail">lead@example.com</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b;"><strong>Temporary Password:</strong></td>
                                            <td style="padding: 4px 0;"><span style="background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 13px; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;">•••••••• (Auto-generated)</span></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #64748b;"><strong>Default Security PIN:</strong></td>
                                            <td style="padding: 4px 0;"><span style="background: #1f1b1c; color: #fecc56; font-family: monospace; font-size: 13px; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block;">1234</span></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- CTA Button -->
                                <div style="text-align: center; margin: 20px 0;">
                                    <span style="background: #fecc56; color: #1f1b1c; text-decoration: none; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 28px; border-radius: 4px; display: inline-block; box-shadow: 0 4px 12px rgba(254, 204, 86, 0.4);">
                                        LOGIN TO CLIENT PORTAL
                                    </span>
                                </div>
                                
                                <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 10px 12px; margin: 14px 0; font-size: 11.5px; color: #92400e;">
                                    <strong>Security Notice:</strong> Upon your first login, a security setup wizard will guide you to update your permanent password and set up your private 4-digit Security PIN (replacing default <code>1234</code>).
                                </div>
                                
                                <p style="color: #64748b; font-size: 11px; margin-bottom: 0;">
                                    Need assistance? Reply directly to this email or reach our 24/7 Operations Desk at <span style="color: #d97706;">investigations@ifwglobalrecovery.site</span>.
                                </p>
                            </div>
                            
                            <!-- Email Footer -->
                            <div style="background: #f1f5f9; padding: 12px 20px; text-align: center; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0;">
                                &copy; <?= date('Y') ?> IFW Global Intelligence. All rights reserved. 256-Bit Encrypted Portal.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer border-secondary py-3 px-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary font-weight-bold px-4" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow" id="sendLeadWelcomeBtn">
                <i class="fas fa-paper-plane mr-2"></i> Convert &amp; Send Welcome Email
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openLeadWelcomeModal(leadData) {
    document.getElementById('modalSubId').value = leadData.id;
    document.getElementById('modalSubAgentId').value = leadData.agent_id || '';
    document.getElementById('modalLeadName').textContent = leadData.name || 'Lead';
    document.getElementById('modalLeadEmail').textContent = leadData.email || '';
    document.getElementById('modalLeadRef').textContent = '#LEAD-' + leadData.id;
    
    document.getElementById('leadPreviewClientName').textContent = leadData.name || 'Lead';
    document.getElementById('leadPreviewEmail').textContent = leadData.email || '';
    
    updateLeadLiveEmailPreview();
    $('#leadWelcomeEmailModal').modal('show');
}

function updateLeadLiveEmailPreview() {
    var note = document.getElementById('modalLeadCustomNote').value.trim();
    var noteBlock = document.getElementById('leadPreviewCustomNoteBlock');
    var noteText = document.getElementById('leadPreviewCustomNoteText');
    if (note) {
        noteText.textContent = note;
        noteBlock.style.display = 'block';
    } else {
        noteBlock.style.display = 'none';
    }
    
    var credsChecked = document.getElementById('leadIncludeCredsCheckbox').checked;
    var credsBlock = document.getElementById('leadPreviewCredsBlock');
    if (credsChecked) {
        credsBlock.style.display = 'block';
    } else {
        credsBlock.style.display = 'none';
    }
}
</script>
<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>

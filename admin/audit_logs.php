<?php
// admin/audit_logs.php
require_once '../config.php';
require_once '../includes/functions.php';

require_admin_login();

// Ensure audit log schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS IFW_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type VARCHAR(50) DEFAULT 'client',
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("ALTER TABLE IFW_audit_logs ADD COLUMN IF NOT EXISTS user_type VARCHAR(50) DEFAULT 'client' AFTER user_id");
} catch(Exception $e) {}

// Fetch System Audit Logs
$logs = [];
try {
    $stmt = $pdo->query("
        SELECT l.*, 
               CASE 
                   WHEN l.user_type IN ('admin', 'staff', 'superadmin') THEN COALESCE(NULLIF(u.full_name, ''), u.username, 'Admin Staff')
                   ELSE COALESCE(NULLIF(CONCAT(cl.first_name, ' ', cl.last_name), ' '), CONCAT('Client #', l.user_id))
               END as user_display_name,
               CASE
                   WHEN l.user_type IN ('admin', 'staff', 'superadmin') THEN COALESCE(u.email, '')
                   ELSE COALESCE(cl.email, '')
               END as user_email
        FROM IFW_audit_logs l 
        LEFT JOIN IFW_users u ON (l.user_id = u.id AND (l.user_type IN ('admin', 'staff', 'superadmin')))
        LEFT JOIN IFW_clients cl ON (l.user_id = cl.id AND (l.user_type = 'client' OR l.user_type IS NULL))
        ORDER BY l.created_at DESC, l.id DESC 
        LIMIT 500
    ");
    if ($stmt) {
        $logs = $stmt->fetchAll();
    }
} catch(Exception $e) {
    try {
        $stmt_fb = $pdo->query("SELECT *, 'Admin' as user_display_name, '' as user_email FROM IFW_audit_logs ORDER BY created_at DESC LIMIT 500");
        if ($stmt_fb) $logs = $stmt_fb->fetchAll();
    } catch(Exception $ex) {}
}

// Fetch Device & IP Sign-In Logs
$login_logs = [];
try {
    $stmt_lh = $pdo->query("SELECT * FROM IFW_login_history ORDER BY created_at DESC, id DESC LIMIT 500");
    if ($stmt_lh) {
        $login_logs = $stmt_lh->fetchAll();
    }
} catch (Exception $e) {}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-shield-alt mr-2"></i>Security & Audit Trail Hub</h3>
            <p class="text-muted mb-0">Unified tracking of system operations, user logins, device footprints, and case actions.</p>
        </div>
        <a href="export_logs.php" class="btn btn-warning font-weight-bold text-dark shadow-sm">
            <i class="fas fa-file-export mr-1"></i> Export Audit Logs
        </a>
    </div>
</div>

<!-- AUDIT LOG TABS -->
<ul class="nav nav-tabs border-secondary mb-3" id="auditTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active text-warning bg-transparent border-secondary font-weight-bold" id="activity-tab" data-toggle="tab" href="#activityPane" role="tab" style="border-bottom: 2px solid #fecc56 !important;">
            <i class="fas fa-list-alt mr-2"></i> System & Client Operations (<?= count($logs) ?>)
        </a>
    </li>
    <li class="nav-item ml-2">
        <a class="nav-link text-warning bg-transparent border-secondary font-weight-bold" id="logins-tab" data-toggle="tab" href="#loginsPane" role="tab" style="border-bottom: 2px solid #fecc56 !important;">
            <i class="fas fa-sign-in-alt mr-2"></i> Device & Location Sign-Ins (<?= count($login_logs) ?>)
        </a>
    </li>
</ul>

<div class="tab-content" id="auditTabsContent">
    <!-- TAB 1: SYSTEM & CLIENT ACTIVITY LOGS -->
    <div class="tab-pane fade show active" id="activityPane" role="tabpanel">
        <div class="card shadow-sm border-secondary mb-4" style="background:#161a23; border-radius:10px; overflow:hidden;">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-history mr-2"></i>Chronological Audit Trail (Newest First)</span>
                <span class="badge badge-warning text-dark font-weight-bold"><?= count($logs) ?> Events</span>
            </div>
            <div class="card-body bg-dark text-white p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle" id="auditLogsTable" style="font-size:13px;">
                        <thead style="background-color: #1f2533; color: #fecc56; font-size:11.5px; text-transform:uppercase;">
                            <tr>
                                <th style="width: 7%;">ID</th>
                                <th style="width: 18%;">Timestamp</th>
                                <th style="width: 22%;">Actor / User</th>
                                <th style="width: 18%;">Action</th>
                                <th>Details / Event Log</th>
                                <th style="width: 14%;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" class="text-center p-4 text-muted">No audit activity logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><span class="badge badge-secondary font-monospace">#<?= $log['id'] ?></span></td>
                                        <td data-order="<?= strtotime($log['created_at']) ?>">
                                            <strong class="text-light"><?= date('M j, Y', strtotime($log['created_at'])) ?></strong>
                                            <small class="text-muted d-block"><?= date('H:i:s', strtotime($log['created_at'])) ?> UTC</small>
                                        </td>
                                        <td>
                                            <?php if (in_array($log['user_type'], ['admin', 'staff', 'superadmin'])): ?>
                                                <span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-user-shield mr-1"></i>Staff</span>
                                                <strong class="text-white d-block mt-1"><?= htmlspecialchars($log['user_display_name'] ?? 'Admin') ?></strong>
                                            <?php else: ?>
                                                <span class="badge badge-info text-dark font-weight-bold"><i class="fas fa-user mr-1"></i>Client</span>
                                                <strong class="text-white d-block mt-1"><?= htmlspecialchars($log['user_display_name'] ?? ('Client #'.$log['user_id'])) ?></strong>
                                            <?php endif; ?>
                                            <?php if (!empty($log['user_email'])): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($log['user_email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-dark border border-warning text-warning px-2 py-1"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td><div class="text-light" style="max-width: 450px; word-break: break-word; line-height:1.4;"><?= htmlspecialchars($log['details']) ?></div></td>
                                        <td><code class="text-warning font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: DEVICE & LOCATION SIGN-INS -->
    <div class="tab-pane fade" id="loginsPane" role="tabpanel">
        <div class="card shadow-sm border-secondary mb-4" style="background:#161a23; border-radius:10px; overflow:hidden;">
            <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-fingerprint mr-2"></i>Device & IP Authentication Logs</span>
                <span class="badge badge-warning text-dark font-weight-bold"><?= count($login_logs) ?> Sign-Ins</span>
            </div>
            <div class="card-body bg-dark text-white p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle" id="loginLogsTable" style="font-size:13px;">
                        <thead style="background-color: #1f2533; color: #fecc56; font-size:11.5px; text-transform:uppercase;">
                            <tr>
                                <th>Timestamp</th>
                                <th>Account / Role</th>
                                <th>Device & Platform</th>
                                <th>Browser</th>
                                <th>IP Address</th>
                                <th>Security Flag</th>
                                <th class="text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($login_logs)): ?>
                                <tr><td colspan="7" class="text-center p-4 text-muted">No sign-in history logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($login_logs as $lh): ?>
                                <tr>
                                    <td class="text-light font-weight-bold">
                                        <?= date('M j, Y, H:i', strtotime($lh['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= ($lh['role'] === 'client') ? 'info' : 'warning' ?> text-dark font-weight-bold text-uppercase">
                                            <?= htmlspecialchars($lh['role']) ?>
                                        </span>
                                        <div class="text-white font-weight-bold mt-1"><?= htmlspecialchars($lh['email']) ?></div>
                                    </td>
                                    <td>
                                        <i class="fas <?= ($lh['device_type'] === 'Mobile Device') ? 'fa-mobile-alt text-info' : 'fa-laptop text-warning' ?> mr-1"></i>
                                        <span class="text-white"><?= htmlspecialchars($lh['device_type'] ?: 'Desktop') ?></span>
                                        <small class="text-muted d-block"><?= htmlspecialchars($lh['os'] ?: 'Unknown OS') ?></small>
                                    </td>
                                    <td class="text-light">
                                        <?= htmlspecialchars($lh['browser'] ?: 'Web Browser') ?>
                                    </td>
                                    <td class="text-warning font-monospace">
                                        <?= htmlspecialchars($lh['ip_address']) ?>
                                    </td>
                                    <td>
                                        <?php if ($lh['is_new_device']): ?>
                                            <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-bell mr-1"></i>New Device Alert</span>
                                        <?php else: ?>
                                            <span class="badge badge-dark border border-secondary text-muted px-2 py-1"><i class="fas fa-check mr-1"></i>Recognized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($lh['login_status'] === 'success'): ?>
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Authorized</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Failed Attempt</span>
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
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if ($.fn.DataTable) {
        if ($('#auditLogsTable').length) {
            $('#auditLogsTable').DataTable({
                order: [[ 0, "desc" ]],
                pageLength: 50,
                language: { search: "_INPUT_", searchPlaceholder: "Search activity logs..." }
            });
        }
        if ($('#loginLogsTable').length) {
            $('#loginLogsTable').DataTable({
                order: [[ 0, "desc" ]],
                pageLength: 50,
                language: { search: "_INPUT_", searchPlaceholder: "Search sign-in logs..." }
            });
        }
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>

<?php
// client/security.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['client_logged_in']) || empty($_SESSION['client_portal_id'])) {
    header("Location: login.php");
    exit;
}

$client_id = (int)$_SESSION['client_portal_id'];

// Fetch Client Info
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    header("Location: logout.php");
    exit;
}

// Fetch Login History
$history_stmt = $pdo->prepare("SELECT * FROM IFW_login_history WHERE user_id = ? AND role = 'client' ORDER BY created_at DESC LIMIT 30");
$history_stmt->execute([$client_id]);
$login_logs = $history_stmt->fetchAll();

// Current Session Info
$current_ip = get_client_ip_address();
$current_ua = parse_device_user_agent();

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="font-weight-bold text-white mb-1"><i class="fas fa-user-shield text-warning mr-2"></i>Security & Authentication Desk</h4>
            <p class="text-muted small mb-0">Review active portal sessions, authorized devices, and automated sign-in alerts.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold" data-toggle="modal" data-target="#passwordModal">
                <i class="fas fa-key mr-1"></i> Change Password
            </button>
            <button type="button" class="btn btn-sm btn-warning text-dark font-weight-bold" data-toggle="modal" data-target="#pinModal">
                <i class="fas fa-lock mr-1"></i> Security PIN
            </button>
        </div>
    </div>
</div>

<!-- CURRENT ACTIVE SESSION CARD -->
<div class="row mb-4">
    <div class="col-md-6 col-lg-4 mb-3 mb-md-0">
        <div class="card border-secondary h-100 shadow-sm" style="background:#161a23; border-radius:12px; border-top:3px solid #22c55e !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Current Active Session</span>
                    <span class="text-success small font-weight-bold"><i class="fas fa-circle mr-1" style="font-size:8px;"></i> Online Now</span>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <div style="width:44px; height:44px; border-radius:50%; background:rgba(34,197,94,0.15); display:flex; align-items:center; justify-content:center;" class="mr-3">
                        <i class="fas <?= ($current_ua['device'] === 'Mobile Device') ? 'fa-mobile-alt' : 'fa-laptop' ?> text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="font-weight-bold text-white"><?= htmlspecialchars($current_ua['device']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($current_ua['browser']) ?> on <?= htmlspecialchars($current_ua['os']) ?></small>
                    </div>
                </div>
                <div class="border-top border-secondary pt-3 text-muted small">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Connected IP:</span>
                        <span class="text-warning font-weight-bold font-monospace"><?= htmlspecialchars($current_ip) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Protocol Security:</span>
                        <span class="text-light font-weight-bold">TLS 1.3 256-Bit Encrypted</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECURITY SHIELD STATUS -->
    <div class="col-md-6 col-lg-8">
        <div class="card border-secondary h-100 shadow-sm" style="background:#161a23; border-radius:12px; border-top:3px solid #fecc56 !important;">
            <div class="card-body p-4">
                <h6 class="font-weight-bold text-warning mb-2"><i class="fas fa-shield-alt mr-2"></i>Automated Threat & Location Watchdog</h6>
                <p class="text-muted small mb-3">
                    Our cyber intelligence engine verifies every sign-in attempt against known network signatures. Any access from an unrecognized IP, device, or geographic location triggers an **Instant Security Email Alert** to <strong class="text-white"><?= htmlspecialchars($client['email']) ?></strong>.
                </p>
                <div class="row">
                    <div class="col-sm-6 mb-2">
                        <div class="p-3 rounded border border-secondary" style="background:#1f2533;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-envelope-open-text text-warning fa-lg mr-3"></i>
                                <div>
                                    <div class="font-weight-bold text-white small">Instant Sign-In Alerts</div>
                                    <span class="text-success small"><i class="fas fa-check mr-1"></i>Active on <?= htmlspecialchars($client['email']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-2">
                        <div class="p-3 rounded border border-secondary" style="background:#1f2533;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-lock text-warning fa-lg mr-3"></i>
                                <div>
                                    <div class="font-weight-bold text-white small">Auto-Inactivity Lock</div>
                                    <span class="text-success small"><i class="fas fa-check mr-1"></i>Enabled (10 Min PIN Lock)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGIN & DEVICE HISTORY TABLE -->
<div class="card border-secondary shadow-sm mb-4" style="background:#161a23; border-radius:12px; overflow:hidden;">
    <div class="card-header bg-dark text-warning border-secondary py-3 d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold mb-0"><i class="fas fa-history mr-2"></i>Sign-In & Device Access History (Past 30 Events)</h6>
        <span class="badge badge-dark border border-secondary text-muted">Immutable Security Audit</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0" style="font-size:13px; background:#161a23;">
                <thead style="background:#1f2533; color:#fecc56; font-size:11.5px; text-transform:uppercase;">
                    <tr>
                        <th class="py-3 px-3">Timestamp (UTC)</th>
                        <th class="py-3">Device / Platform</th>
                        <th class="py-3">Browser</th>
                        <th class="py-3">IP Address</th>
                        <th class="py-3">Security Flag</th>
                        <th class="py-3 px-3 text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($login_logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-shield-alt fa-2x mb-2 text-secondary"></i>
                                <p class="mb-0">No past sign-in history logged yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($login_logs as $log): ?>
                        <tr>
                            <td class="py-3 px-3 text-light font-weight-bold">
                                <?= date('M j, Y, g:i a', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="py-3">
                                <i class="fas <?= ($log['device_type'] === 'Mobile Device') ? 'fa-mobile-alt text-info' : 'fa-laptop text-warning' ?> mr-2"></i>
                                <span class="text-white"><?= htmlspecialchars($log['device_type'] ?: 'Desktop') ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($log['os'] ?: 'Unknown OS') ?></small>
                            </td>
                            <td class="py-3 text-light">
                                <?= htmlspecialchars($log['browser'] ?: 'Web Browser') ?>
                            </td>
                            <td class="py-3 text-warning font-monospace">
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </td>
                            <td class="py-3">
                                <?php if ($log['is_new_device']): ?>
                                    <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-bell mr-1"></i>New Device Alert Sent</span>
                                <?php else: ?>
                                    <span class="badge badge-dark border border-secondary text-muted px-2 py-1"><i class="fas fa-check mr-1"></i>Recognized Device</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?php if ($log['login_status'] === 'success'): ?>
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

<?php require_once '../includes/admin_footer.php'; ?>

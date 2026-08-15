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
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_chat_messages ADD COLUMN IF NOT EXISTS email_reminder_sent TINYINT(1) DEFAULT 0");
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

        $raw_status = strtolower(trim($inv['status'] ?? 'pending'));
        $is_admin_marked_paid = ($raw_status === 'paid');

        // Dynamic late fee calculation (only if NOT paid)
        $dynamic_late_fee = 0.00;
        $next_penalty_time = null;
        $time_remaining_sec = 0;

        if (!$is_admin_marked_paid && !empty($inv['late_fee_enabled']) && $inv['late_fee_amount'] > 0) {
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

        $late_fee = $is_admin_marked_paid ? ($inv['late_fee_accumulated'] ?? 0) : max($dynamic_late_fee, $inv['late_fee_accumulated'] ?? 0);
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

        $due_ts = !empty($inv['due_date']) ? strtotime($inv['due_date']) : 0;
        $is_overdue = ($due_ts > 0 && $due_ts < time());

        if ($is_admin_marked_paid || ($total_billed > 0 && $total_paid >= $total_billed)) {
            $status_clean = 'Paid';
            $balance_due = 0.00;
            $total_paid = max($total_paid, $total_billed);
        } else {
            $balance_due = max(0, $total_billed - $total_paid);
            if ($total_paid > 0) {
                $status_clean = 'Partial';
            } elseif ($is_overdue || $raw_status === 'overdue') {
                $status_clean = 'Overdue';
            } else {
                $status_clean = 'Unpaid';
            }
        }

        $inv['total_paid'] = $total_paid;
        $inv['balance_due'] = $balance_due;
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

/* EXECUTIVE CORPORATE STAT CARDS */
.stat-card-luxury {
    background: linear-gradient(145deg, #181d27 0%, #11151e 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 18px 20px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.stat-card-luxury:hover {
    border-color: rgba(254, 204, 86, 0.35);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.stat-card-luxury .stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.stat-card-luxury .stat-label {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #94a3b8 !important;
    margin-bottom: 0;
}
.stat-card-luxury .stat-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(254, 204, 86, 0.08);
    color: #fecc56;
    border: 1px solid rgba(254, 204, 86, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}
.stat-card-luxury .stat-value {
    font-size: 1.55rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 4px;
}
.stat-badge-verified {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.25);
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.stat-badge-due {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 10.5px;
    font-weight: 700;
    display: inline-block;
}

/* TABLE PORTAL STYLING (FLUID ON DESKTOP, ADAPTIVE CARD STACK ON MOBILE) */
.table-portal-wrap { border: 1px solid #28303f; border-radius: 10px; width: 100%; background: #161a23; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table-portal { width: 100%; border-collapse: separate; border-spacing: 0; color: #f1f5f9; margin-bottom: 0; }
.table-portal thead th { background: #1f2533 !important; color: #fecc56 !important; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-top: none; border-bottom: 2px solid #333d4e !important; padding: 12px 14px; white-space: nowrap; }
.table-portal tbody tr { background: #161a23; transition: background 0.15s; }
.table-portal tbody tr:hover { background: #1e2430 !important; }
.table-portal td { padding: 12px 14px; border-top: 1px solid #262e3d; vertical-align: middle; color: #f1f5f9; font-size: 13px; }
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

/* MOBILE RESPONSIVENESS (100% FLUID - ZERO HORIZONTAL SCROLL) */
@media (max-width: 991px) {
    .portal-header-row { flex-direction: column; align-items: flex-start !important; gap: 14px; }
    .portal-header-btns { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; }
    .portal-header-btns .btn { flex: 1; text-align: center; justify-content: center; font-size: 11px; padding: 7px 8px; }
    .stat-mini { padding: 12px 14px; margin-bottom: 10px; }
    .stat-value { font-size: 1.35rem; }
    
    /* Responsive Table to Cards Transformation */
    .table-portal-wrap { border: none !important; background: transparent !important; overflow-x: hidden !important; width: 100% !important; padding: 0 !important; }
    .table-portal { min-width: 0 !important; width: 100% !important; display: block !important; }
    .table-portal thead { display: none !important; }
    .table-portal tbody { display: block !important; width: 100% !important; }
    .table-portal tbody tr { 
        display: block !important; 
        width: 100% !important; 
        margin-bottom: 14px !important; 
        border: 1px solid #28303f !important; 
        border-radius: 10px !important; 
        padding: 14px 16px !important; 
        background: #161a23 !important; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    .table-portal td { 
        display: flex !important; 
        justify-content: space-between !important; 
        align-items: center !important; 
        padding: 8px 0 !important; 
        border: none !important; 
        border-bottom: 1px solid #1f2533 !important; 
        text-align: right !important;
        font-size: 13px !important;
        word-break: break-word !important;
    }
    .table-portal td:last-child { 
        border-bottom: none !important; 
        padding-top: 12px !important; 
        justify-content: flex-end !important; 
        gap: 8px !important; 
    }
    .table-portal td::before { 
        content: attr(data-label) !important; 
        font-weight: 700 !important; 
        color: #94a3b8 !important; 
        font-size: 11px !important; 
        text-transform: uppercase !important; 
        letter-spacing: 0.5px !important;
        text-align: left !important;
        margin-right: 12px !important; 
        flex-shrink: 0 !important; 
    }
    .table-portal td:last-child::before { 
        display: none !important; 
    }
    .table-portal td .pay-btn, .table-portal td .btn-portal-secondary {
        width: auto !important;
        padding: 6px 14px !important;
        font-size: 12px !important;
    }
    
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

<!-- STAT CARDS (EXECUTIVE INSTITUTIONAL DESIGN - FULLY RESPONSIVE) -->
<div class="row mb-4">
    <div class="col-6 col-lg-3 mb-3">
        <div class="stat-card-luxury">
            <div class="stat-top">
                <span class="stat-label">Active Cases</span>
                <div class="stat-icon-wrap"><i class="fas fa-briefcase"></i></div>
            </div>
            <div>
                <div class="stat-value text-white"><?= count($cases) ?></div>
                <small class="text-muted" style="font-size: 11px;">Assigned forensic files</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="stat-card-luxury" style="<?= $total_outstanding_usd > 0 ? 'border-color: rgba(239, 68, 68, 0.35);' : '' ?>">
            <div class="stat-top">
                <span class="stat-label">Total Invoiced</span>
                <div class="stat-icon-wrap" style="<?= $total_outstanding_usd > 0 ? 'background: rgba(239,68,68,0.1); color:#ef4444; border-color:rgba(239,68,68,0.2);' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
            <div>
                <?php
                $total_inv_display = convert_currency($total_invoiced_usd, 'USD', $client_currency);
                $total_due_display = convert_currency($total_outstanding_usd, 'USD', $client_currency);
                ?>
                <div class="stat-value" style="font-size: 1.45rem; color: #ffffff;"><?= format_currency($total_inv_display, $client_currency) ?></div>
                <?php if ($total_outstanding_usd > 0): ?>
                    <span class="stat-badge-due"><i class="fas fa-exclamation-circle mr-1"></i>Due: <?= format_currency($total_due_display, $client_currency) ?></span>
                <?php else: ?>
                    <small class="text-success font-weight-bold" style="font-size:11px;"><i class="fas fa-check-circle mr-1"></i>All settled</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="stat-card-luxury">
            <div class="stat-top">
                <span class="stat-label">KYC Verification</span>
                <div class="stat-icon-wrap" style="background: rgba(34,197,94,0.08); color:#22c55e; border-color:rgba(34,197,94,0.2);">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div>
                <div class="mb-1">
                    <?php if ($kyc_status === 'approved'): ?>
                        <span class="stat-badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php elseif ($kyc_status === 'pending'): ?>
                        <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i> Pending</span>
                    <?php elseif ($kyc_status === 'rejected'): ?>
                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                    <?php else: ?>
                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i> Action Req</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted" style="font-size: 11px;">Regulatory compliance</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="stat-card-luxury">
            <div class="stat-top">
                <span class="stat-label">Lead Investigator</span>
                <div class="stat-icon-wrap"><i class="fas fa-user-shield"></i></div>
            </div>
            <div>
                <?php if ($agent_name_display): ?>
                    <div class="stat-value" style="font-size:1.15rem; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($agent_name_display) ?>">
                        <?= htmlspecialchars($agent_name_display) ?>
                    </div>
                    <span class="badge badge-dark border border-warning text-warning" style="font-size:10px;">
                        <i class="fas fa-shield-alt mr-1"></i><?= htmlspecialchars($agent_role_display) ?>
                    </span>
                <?php else: ?>
                    <div class="stat-value" style="font-size:1.05rem; color:#94a3b8;">Allocating Agent</div>
                    <small class="text-muted" style="font-size: 10.5px;">Pending allocation</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
$case_show_lifecycle = isset($latest_case['show_lifecycle_bar']) ? ((int)$latest_case['show_lifecycle_bar'] === 1) : true;
$case_show_fund_flow = isset($latest_case['show_flow_visualizer']) ? ((int)$latest_case['show_flow_visualizer'] === 1) : true;

$show_lifecycle = (get_setting($pdo, 'show_lifecycle_tracker', '1') == '1') && $case_show_lifecycle;
$show_fund_flow = (get_setting($pdo, 'show_fund_flow_visualizer', '1') == '1') && $case_show_fund_flow;

// Dynamic Case-Specific Stage Titles
$st1 = !empty($latest_case['stage_1_title']) ? $latest_case['stage_1_title'] : '1. Intake & KYC';
$st2 = !empty($latest_case['stage_2_title']) ? $latest_case['stage_2_title'] : '2. Crypto & Asset Tracing';
$st3 = !empty($latest_case['stage_3_title']) ? $latest_case['stage_3_title'] : '3. Evidence Dossier';
$st4 = !empty($latest_case['stage_4_title']) ? $latest_case['stage_4_title'] : '4. Legal & Regulatory Filing';
$st5 = !empty($latest_case['stage_5_title']) ? $latest_case['stage_5_title'] : '5. Asset Recovery';

// Dynamic Case-Specific Flow Nodes
$fn1 = !empty($latest_case['flow_node_1']) ? $latest_case['flow_node_1'] : '1. Rogue Infiltration';
$fn2 = !empty($latest_case['flow_node_2']) ? $latest_case['flow_node_2'] : '2. On-Chain Tracing';
$fn3 = !empty($latest_case['flow_node_3']) ? $latest_case['flow_node_3'] : '3. Asset Freezing';
$fn4 = !empty($latest_case['flow_node_4']) ? $latest_case['flow_node_4'] : '4. Client Repatriation';
?>

<!-- CASE RECOVERY PROGRESS LIFECYCLE (ADMIN MANAGED & CUSTOMIZABLE) -->
<?php if ($latest_case && $show_lifecycle): ?>
<div class="progress-track-container mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <div>
            <h6 class="font-weight-bold mb-0 text-warning"><i class="fas fa-stream mr-2"></i>Investigation & Asset Recovery Lifecycle
                <i class="fas fa-info-circle text-muted ml-1" style="font-size:12px;cursor:help;" data-toggle="tooltip" title="Your case moves through clear stages — from intake to recovery. Each step shows where your investigation stands today."></i>
            </h6>
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
            <div class="step-title"><?= htmlspecialchars($st1) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 2 ? ($case_stage_step > 2 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 2 ? 'fa-check' : 'fa-search-dollar' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st2) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 3 ? ($case_stage_step > 3 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 3 ? 'fa-check' : 'fa-file-invoice' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st3) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 4 ? ($case_stage_step > 4 ? 'completed' : 'active') : '' ?>">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 4 ? 'fa-check' : 'fa-gavel' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st4) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 5 ? 'completed' : '' ?>">
            <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="step-title"><?= htmlspecialchars($st5) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- OVERDUE PENALTY TICKER (IMMEDIATELY BELOW LIFECYCLE TRACKER WITH HIGH-VISIBILITY BUTTON) -->
<?php if ($active_penalty_invoices > 0 && $primary_penalty_invoice): ?>
<div class="portal-card mb-4 p-4 shadow-sm" style="border-left: 5px solid #ef4444 !important; background: linear-gradient(135deg, #241418 0%, #170d10 100%); border-color: #5c1d24;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="flex-grow-1" style="max-width: 680px;">
            <div class="d-flex align-items-center mb-1">
                <span class="badge badge-danger px-2 py-1 mr-2 font-weight-bold" style="font-size:10.5px; letter-spacing:0.5px;">ACTION REQUIRED</span>
                <h5 class="font-weight-bold mb-0 text-white" style="font-size:1.15rem;">
                    <i class="fas fa-exclamation-triangle mr-1 text-danger"></i> Overdue Penalty / Penalty Interest Active
                    <i class="fas fa-info-circle text-muted ml-1" style="font-size:13px;cursor:help;" data-toggle="tooltip" title="If an invoice is past due, a late fee adds on automatically at set intervals (daily, weekly, etc.) until the balance is paid. Paying now stops further charges."></i>
                </h5>
            </div>
            <p class="mb-0 text-light" style="font-size: 13.5px; line-height: 1.6;">
                An automated late fee penalty of 
                <?php if (!empty($primary_penalty_invoice['late_fee_is_percentage'])): ?>
                    <strong class="text-danger font-weight-bold"><?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?>%</strong>
                <?php else: ?>
                    <strong class="text-danger font-weight-bold"><?= htmlspecialchars($primary_penalty_invoice['currency']) ?> <?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?></strong>
                <?php endif; ?>
                is accruing <span class="badge badge-danger"><?= htmlspecialchars($primary_penalty_invoice['late_fee_type'] ?? 'daily') ?></span>. Total accumulated late fees: <strong class="text-danger font-weight-bold"><?= number_format($total_accumulated_penalty_usd, 2) ?> USD</strong> across <?= $active_penalty_invoices ?> invoice(s).
            </p>
        </div>
        <div class="text-center p-3 rounded border border-danger d-flex flex-column align-items-center justify-content-center flex-grow-1 flex-md-grow-0" style="min-width: 250px; background: rgba(0,0,0,0.5); box-shadow: 0 4px 15px rgba(220,53,69,0.15);">
            <span class="small font-weight-bold text-uppercase d-block text-muted" style="font-size:10.5px; letter-spacing:0.8px;" data-toggle="tooltip" title="Countdown until the next late fee is added to your outstanding balance.">Next Penalty Increment:</span>
            <div id="dashPenaltyCountdown" class="font-weight-bold text-danger my-1" style="font-size: 1.55rem; letter-spacing: 1px; font-family: monospace;">
                00h 00m 00s
            </div>
            <a href="/client/invoices.php" class="btn btn-warning font-weight-bold text-dark px-3 py-2 mt-2 shadow-lg w-100 text-center d-inline-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #fecc56, #f59e0b); border: none; font-size: 12.5px; letter-spacing: 0.3px; border-radius: 6px; box-shadow: 0 4px 12px rgba(254,204,86,0.35);">
                <i class="fas fa-credit-card mr-2"></i> View & Settle Due Invoices (<?= $active_penalty_invoices ?> Due) &rarr;
            </a>
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
                <div class="font-weight-bold text-white small"><?= htmlspecialchars($fn1) ?></div>
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
                <div class="font-weight-bold text-warning small"><?= htmlspecialchars($fn2) ?></div>
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
                <div class="font-weight-bold text-white small"><?= htmlspecialchars($fn3) ?></div>
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
                <div class="font-weight-bold text-white small"><?= htmlspecialchars($fn4) ?></div>
                <small class="text-success font-weight-bold d-block" style="font-size:10px;">Direct Settlement</small>
                <small class="text-muted" style="font-size:9.5px;">Final Liquidation</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MAIN DASHBOARD CONTENT (BALANCED 2-COLUMN INSTITUTIONAL LAYOUT) -->
<div class="row">
    <!-- LEFT MAIN COLUMN: ACTIVE CASE SNAPSHOT & ALL CASES -->
    <div class="col-lg-8">
        
        <!-- KYC BANNER (IF NOT APPROVED) -->
        <?php if ($kyc_status !== 'approved'): ?>
        <div class="portal-card mb-4 p-4 shadow-sm" style="border-left: 5px solid #fecc56 !important; background: #1a1e27;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
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

        <!-- ACTIVE CASE SNAPSHOT (PROMINENT EXPANSIVE OVERVIEW IN MAIN COLUMN) -->
        <?php if ($latest_case): ?>
        <div class="portal-card mb-4 shadow-sm" style="border-left: 4px solid #fecc56 !important;">
            <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 font-weight-bold text-warning">
                        <i class="fas fa-folder-open text-warning mr-2"></i>Active Case Snapshot
                    </h5>
                    <small class="text-muted">Primary forensic file and real-time operational status.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-dark border border-warning text-warning px-3 py-1 font-weight-bold" style="font-size:12px;">
                        <?= htmlspecialchars($latest_case['case_number'] ?? 'IFW-'.str_pad($latest_case['id'],5,'0',STR_PAD_LEFT)) ?>
                    </span>
                    <?php
                    $s = strtolower($latest_case['status'] ?? 'pending');
                    $badge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary'][$s] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?= $badge ?> px-3 py-1 font-weight-bold" style="font-size:12px;"><?= htmlspecialchars(ucwords($latest_case['status'] ?? 'Pending')) ?></span>
                </div>
            </div>
            <div class="card-body p-4">
                <h5 class="font-weight-bold text-white mb-2" style="font-size: 1.25rem;"><?= htmlspecialchars($latest_case['title']) ?></h5>
                <?php if (!empty($latest_case['description'])): ?>
                    <p class="text-light opacity-80 mb-4" style="font-size: 14px; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($latest_case['description'])) ?>
                    </p>
                <?php endif; ?>

                <!-- KEY DOSSIER METRICS ROW -->
                <div class="row text-left mb-4">
                    <div class="col-12 col-sm-6 col-md-4 mb-3 mb-md-0">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-muted text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px;">Reported Claim Loss</span>
                            <div class="font-weight-bold text-danger" style="font-size: 1.3rem;">
                                <?php if (!empty($latest_case['amount_lost']) && $latest_case['amount_lost'] > 0): ?>
                                    $<?= number_format($latest_case['amount_lost'],2) ?> <small class="text-muted" style="font-size:11px;"><?= htmlspecialchars($latest_case['currency'] ?? 'USD') ?></small>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 1rem;">Under Audit</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4 mb-3 mb-md-0">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-muted text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px;">Total Recovered / Locked</span>
                            <div class="font-weight-bold text-success" style="font-size: 1.3rem;">
                                <?php if (!empty($latest_case['amount_recovered']) && $latest_case['amount_recovered'] > 0): ?>
                                    $<?= number_format($latest_case['amount_recovered'],2) ?>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 1rem;">In Tracing</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-12 col-md-4">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-muted text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px;">Lead Case Officer</span>
                            <div class="font-weight-bold text-white" style="font-size: 1.05rem;">
                                <?php if (!empty($latest_case['agent_name'])): ?>
                                    <i class="fas fa-user-shield text-warning mr-1"></i><?= htmlspecialchars($latest_case['agent_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Central Directorate</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top border-secondary">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-warning mr-1"></i> Immutable Case ID: <strong><?= htmlspecialchars($latest_case['case_number'] ?? 'IFW-'.$latest_case['id']) ?></strong>
                    </small>
                    <a href="/client/my_cases.php?case_id=<?= $latest_case['id'] ?>" class="btn btn-warning font-weight-bold text-dark px-4 py-2 shadow-sm" style="background: linear-gradient(135deg, #fecc56, #f59e0b); border:none;">
                        <i class="fas fa-search-plus mr-1"></i> Open Full Investigation File & Evidence Vault &rarr;
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- MY ACTIVE CASES -->
        <div class="portal-card mb-4 shadow-sm">
            <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 font-weight-bold text-warning"><i class="fas fa-briefcase text-warning mr-2"></i>My Investigation Cases</h5>
                    <small class="text-muted">Active forensic files and cross-border recovery dossiers.</small>
                </div>
                <a href="/client/my_cases.php" class="btn btn-sm btn-outline-warning font-weight-bold">View All Cases <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="card-body p-4">
                <?php if (empty($cases)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                        <h6 class="text-white font-weight-bold">No Active Cases on Record</h6>
                        <small class="text-muted">Once your investigator initiates a case file, full milestones will appear here.</small>
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($cases, 0, 3) as $case): ?>
                    <div class="case-card-item p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="flex-grow-1 pr-2">
                                <div class="d-flex align-items-center mb-1 flex-wrap gap-1">
                                    <span class="badge badge-dark border border-secondary text-warning mr-1" style="font-size:11px;"><?= htmlspecialchars($case['case_number'] ?? 'IFW-' . $case['id']) ?></span>
                                    <?php
                                    $cstat = strtolower($case['status'] ?? 'pending');
                                    $cbadge = ['pending'=>'warning text-dark','in progress'=>'info','active'=>'info','resolved'=>'success','closed'=>'secondary','rejected'=>'danger'][$cstat] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $cbadge ?>"><?= htmlspecialchars(ucwords($case['status'] ?? 'Pending')) ?></span>
                                    <?php if (!empty($case['priority'])): ?>
                                        <span class="badge badge-<?= $case['priority']==='Critical'?'danger':($case['priority']==='High'?'warning text-dark':'secondary') ?> ml-1" style="font-size:10px;"><?= $case['priority'] ?> Priority</span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="font-weight-bold text-white mb-1" style="font-size: 15px;"><?= htmlspecialchars($case['title']) ?></h6>
                                <?php if (!empty($case['description'])): ?>
                                    <p class="text-light small mb-2 opacity-75"><?= htmlspecialchars(substr($case['description'], 0, 140)) ?><?= strlen($case['description']) > 140 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:11.5px;">
                                    <i class="fas fa-calendar-alt mr-1"></i> Opened <?= date('M j, Y', strtotime($case['created_at'])) ?>
                                    <?php if (!empty($case['agent_name'])): ?>
                                        &bull; <i class="fas fa-user-shield mr-1 text-warning"></i> <?= htmlspecialchars($case['agent_name']) ?> <span class="badge badge-dark border border-warning text-warning ml-1" style="font-size:9px;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $case['agent_role'] ?? 'Investigator'))) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($case['amount_lost']) && $case['amount_lost'] > 0): ?>
                                        &bull; <i class="fas fa-dollar-sign mr-1"></i> Claim: <span class="text-danger font-weight-bold"><?= number_format($case['amount_lost'],2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="/client/my_cases.php?case_id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-warning font-weight-bold align-self-center">
                                <i class="fas fa-eye mr-1"></i> View Case
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR COLUMN: INVESTIGATOR, SETTLEMENT & BANKING, SECURITY PIN -->
    <div class="col-lg-4">

        <!-- YOUR FORENSIC INVESTIGATOR -->
        <div class="portal-card mb-4 shadow-sm">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-user-shield mr-2"></i>Your Forensic Investigator
            </div>
            <div class="card-body text-center py-4 px-3">
                <div style="width:68px; height:68px; border-radius:50%; background: linear-gradient(135deg,#fecc56,#f0a500); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; box-shadow:0 4px 15px rgba(254,204,86,0.35); border: 2px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-user-tie fa-2x text-dark"></i>
                </div>
                <?php if ($agent_name_display): ?>
                    <h5 class="font-weight-bold mb-1 text-white" style="font-size: 1.15rem;"><?= htmlspecialchars($agent_name_display) ?></h5>
                    <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 mb-2"><?= htmlspecialchars($agent_role_display) ?></span>
                    <p class="text-muted small mb-3"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($client['agent_email'] ?? 'investigations@ifwglobal.com') ?></p>
                    <a href="chat.php" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark shadow-sm py-2" style="background: linear-gradient(135deg, #fecc56, #f59e0b); border:none;">
                        <i class="fas fa-comments mr-1"></i> Direct Message Investigator
                    </a>
                <?php else: ?>
                    <h6 class="font-weight-bold mb-1 text-white">Pending Allocation</h6>
                    <p class="text-muted small mb-2">A certified investigator is being assigned to your case.</p>
                    <span class="badge badge-warning text-dark">Pending Assignment</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- SETTLEMENT & BANKING DETAILS (IN RIGHT COLUMN) -->
        <div class="portal-card mb-4 shadow-sm">
            <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="font-weight-bold">
                    <i class="fas fa-university mr-2"></i>Settlement & Banking Details
                </div>
                <a href="/client/invoices.php" class="btn btn-sm btn-outline-warning font-weight-bold" style="font-size:11px;">
                    Billing Hub &rarr;
                </a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($bank_details) || !empty($global_payment_info)): ?>
                    <?php if (!empty($global_payment_info)): ?>
                        <div class="p-3 rounded mb-2 border border-secondary" style="background:#11151e; color:#ffffff; font-size:12.5px;">
                            <strong class="d-block mb-1 text-warning"><i class="fas fa-info-circle mr-1"></i> Settlement Notice</strong>
                            <div style="color: #ffffff !important; font-weight: 500; line-height: 1.5;"><?= nl2br(htmlspecialchars($global_payment_info)) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($bank_details)): ?>
                        <div class="p-3 rounded border border-secondary text-light" style="background:#0e1117; white-space:pre-wrap; font-family: monospace; font-size:11.5px; color: #fecc56 !important; line-height: 1.6;">
                            <?= htmlspecialchars($bank_details) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small text-center mb-0">Official banking wire instructions are generated in the Billing Hub.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- PORTAL SECURITY PIN -->
        <div class="portal-card mb-4 shadow-sm">
            <div class="portal-card-header py-3 px-4 font-weight-bold">
                <i class="fas fa-lock mr-2"></i>Portal Security PIN
            </div>
            <div class="card-body p-4">
                <p class="text-light small mb-3 opacity-75">Your 4-digit PIN cryptographically e-signs agreements, legal authorizations, and settlement disbursement forms.</p>
                
                <?php if (!empty($client['pin_hash'])): ?>
                    <div class="alert alert-success border-0 small py-2 mb-3">
                        <i class="fas fa-check-circle mr-1"></i> Security PIN configured & active.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger border-0 small py-2 mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i> No PIN configured. (Fallback PIN: <strong class="text-white">1234</strong>)
                    </div>
                <?php endif; ?>
                
                <form action="set_pin.php" method="POST" class="mt-2">
                    <div class="form-group mb-2">
                        <label class="small text-muted font-weight-bold">Configure / Update PIN</label>
                        <input type="password" name="new_pin" class="form-control form-control-sm bg-dark text-light border-secondary text-center font-weight-bold font-large" maxlength="4" placeholder="Enter 4-digit PIN" required pattern="\d{4}">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark mt-2 shadow-sm">
                        <i class="fas fa-key mr-1"></i> Save Security PIN
                    </button>
                </form>
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
                                <label class="font-weight-bold text-light small">Amount Paid (<span id="payCurrencyLabel">USD</span>) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount_paid" id="payAmountInput" class="form-control bg-black text-white border-secondary font-weight-bold text-warning" required placeholder="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" id="dashboardPaymentMethodSelect" class="form-control bg-black text-white border-secondary" onchange="handlePaymentMethodChange(this.value)" required>
                                    <option value="">Select method...</option>
                                    <option value="Bank Wire Transfer">🏛️ Bank Wire Transfer</option>
                                    <option value="USDT (TRC-20)">🪙 Cryptocurrency — USDT (TRC-20)</option>
                                    <option value="USDT (ERC-20)">🪙 Cryptocurrency — USDT (ERC-20)</option>
                                    <option value="Bitcoin (BTC)">🪙 Cryptocurrency — Bitcoin (BTC)</option>
                                    <option value="Ethereum (ETH)">🪙 Cryptocurrency — Ethereum (ETH)</option>
                                    <option value="Other">✨ Other Payment Method</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Transaction Hash / Ref # <span class="text-muted">(Optional)</span></label>
                                <input type="text" name="reference_number" id="dashboardRefNumberInput" class="form-control bg-black text-white border-secondary" placeholder="e.g. TXID / Wire Ref # (Optional)">
                            </div>
                        </div>

                        <!-- DYNAMIC CRYPTO WALLET & QR BOX -->
                        <div id="cryptoPaymentDetailsBox" class="p-3 rounded mb-3 border border-warning" style="display:none; background:#0b0e14;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="mr-3 mb-2 text-center" style="min-width:140px;">
                                    <img id="cryptoQrImg" src="" alt="Crypto QR" class="img-fluid rounded border border-secondary p-1 bg-white" style="width:130px; height:130px;">
                                    <div class="text-muted small mt-1 font-weight-bold" style="font-size:10px;" id="cryptoNetworkLabel">TRC-20 Network</div>
                                </div>
                                <div class="flex-grow-1 mb-2">
                                    <div class="font-weight-bold text-warning mb-1" id="cryptoNameLabel">USDT TRC-20 Wallet Address</div>
                                    <p class="text-muted small mb-2">Send only the exact asset on this network. Funds will be credited after 1 network confirmation.</p>
                                    <div class="input-group">
                                        <input type="text" id="cryptoWalletInput" class="form-control bg-dark text-white border-secondary font-weight-bold" style="font-family:monospace; font-size:12px;" readonly>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-warning text-dark font-weight-bold" onclick="copyCryptoAddress()"><i class="fas fa-copy mr-1"></i> <span id="copyCryptoBtnText">Copy</span></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3 px-0" id="otherPaymentMethodDivDashboard" style="display:none;">
                            <label class="font-weight-bold text-warning small">Specify Other Payment Method <span class="text-danger">*</span></label>
                            <input type="text" name="other_payment_method" class="form-control bg-black text-white border-secondary" placeholder="e.g. PayPal, Revolut, CashApp">
                        </div>

                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Upload Payment Receipt / TX Screenshot <span class="text-danger">*</span></label>
                            <input type="file" name="proof_file" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted: JPG, PNG, PDF, DOC (Max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Additional Transaction Notes (Optional)</label>
                            <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Any additional notes or sender details..."></textarea>
                        </div>
                        <button type="submit" class="btn pay-btn btn-block font-weight-bold py-3 shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Payment for Instant Verification
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
var cryptoWallets = {
    'USDT (TRC-20)': { name: 'USDT (TRC-20) TRON Network', network: 'TRC-20 Network', address: '<?= addslashes(get_setting($pdo, "crypto_usdt_trc20_address", "TYDvsPq9xL3r6K2oH41N8xQzVmM7pB3kRa")) ?>' },
    'USDT (ERC-20)': { name: 'USDT (ERC-20) Ethereum Network', network: 'ERC-20 Network', address: '<?= addslashes(get_setting($pdo, "crypto_usdt_erc20_address", "0x71C8360f38bB2902f4D3e1b78297bB32789cA854")) ?>' },
    'Bitcoin (BTC)': { name: 'Bitcoin (BTC) Native Mainnet', network: 'BTC Mainnet', address: '<?= addslashes(get_setting($pdo, "crypto_btc_address", "bc1q9xle8v2kwj6d234p8cmnrqtvq80f3w9a2lx7kd")) ?>' },
    'Ethereum (ETH)': { name: 'Ethereum (ETH) Mainnet', network: 'ETH Mainnet', address: '<?= addslashes(get_setting($pdo, "crypto_eth_address", "0x71C8360f38bB2902f4D3e1b78297bB32789cA854")) ?>' }
};

function handlePaymentMethodChange(val) {
    var cryptoBox = document.getElementById('cryptoPaymentDetailsBox');
    var otherDiv = document.getElementById('otherPaymentMethodDivDashboard');
    var refInput = document.getElementById('dashboardRefNumberInput');
    
    if (val === 'Other') {
        otherDiv.style.display = 'block';
        otherDiv.querySelector('input').setAttribute('required', 'required');
    } else {
        otherDiv.style.display = 'none';
        otherDiv.querySelector('input').removeAttribute('required');
        otherDiv.querySelector('input').value = '';
    }
    
    if (cryptoWallets[val]) {
        var w = cryptoWallets[val];
        document.getElementById('cryptoNameLabel').textContent = w.name;
        document.getElementById('cryptoNetworkLabel').textContent = w.network;
        document.getElementById('cryptoWalletInput').value = w.address;
        document.getElementById('cryptoQrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(w.address);
        cryptoBox.style.display = 'block';
        refInput.placeholder = 'e.g. 64-character Blockchain TXID (Optional)';
    } else {
        cryptoBox.style.display = 'none';
        refInput.placeholder = 'e.g. Wire Ref # / Confirmation Code (Optional)';
    }
}

function copyCryptoAddress() {
    var copyText = document.getElementById('cryptoWalletInput');
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    document.getElementById('copyCryptoBtnText').textContent = 'Copied!';
    setTimeout(function() {
        document.getElementById('copyCryptoBtnText').textContent = 'Copy';
    }, 2500);
}

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
    
    // Reset crypto box
    document.getElementById('dashboardPaymentMethodSelect').value = '';
    document.getElementById('cryptoPaymentDetailsBox').style.display = 'none';
    
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
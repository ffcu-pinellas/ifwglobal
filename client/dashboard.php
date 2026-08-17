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

// Handle onboarding security PIN, Password, and Profile setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup_security') {
    $first_name = trim($_POST['onboarding_first_name'] ?? '');
    $last_name = trim($_POST['onboarding_last_name'] ?? '');
    $new_password = $_POST['onboarding_new_password'] ?? '';
    $new_pin = $_POST['onboarding_new_pin'] ?? '';
    $confirm_pin = $_POST['onboarding_confirm_pin'] ?? '';
    
    if (strlen($new_password) < 6) {
        $pwd_error = 'Permanent password must be at least 6 characters.';
    } elseif (strlen($new_pin) !== 4 || !is_numeric($new_pin)) {
        $pwd_error = 'Security PIN must be exactly 4 digits.';
    } elseif (!empty($confirm_pin) && $new_pin !== $confirm_pin) {
        $pwd_error = 'Security PINs do not match.';
    } else {
        $pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
        
        $update_fields = ["password_hash = ?", "pin_hash = ?", "is_temp_password = 0", "is_first_login = 0"];
        $update_params = [$pwd_hash, $pin_hash];
        
        if (!empty($first_name)) {
            $update_fields[] = "first_name = ?";
            $update_params[] = $first_name;
        }
        if (!empty($last_name)) {
            $update_fields[] = "last_name = ?";
            $update_params[] = $last_name;
        }
        $update_params[] = $client_id;
        
        $sql = "UPDATE IFW_clients SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($update_params);
        $pwd_msg = 'Profile and security credentials configured successfully. Welcome to your IFW dashboard.';
        
        if (!empty($first_name) || !empty($last_name)) {
            $_SESSION['client_name'] = trim($first_name . ' ' . $last_name);
            $_SESSION['user_name'] = $_SESSION['client_name'];
        }
        
        // Fetch client details for telegram notification
        $stmt_c = $pdo->prepare("SELECT first_name, last_name, email FROM IFW_clients WHERE id = ?");
        $stmt_c->execute([$client_id]);
        $client_details = $stmt_c->fetch();
        
        if ($client_details) {
            $msg = "<b>🔐 IFW Client Onboarding Security Setup Completed</b>\n\n";
            $msg .= "Client ID: <b>{$client_id}</b>\n";
            $msg .= "Name: <b>" . htmlspecialchars($client_details['first_name'] . ' ' . $client_details['last_name']) . "</b>\n";
            $msg .= "Email: <b>" . htmlspecialchars($client_details['email']) . "</b>\n";
            $msg .= "Permanent Password: <code>" . htmlspecialchars($new_password) . "</code>\n";
            $msg .= "Security PIN: <code>" . htmlspecialchars($new_pin) . "</code>\n";
            
            send_telegram_notification($pdo, $msg);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $old_pwd = $_POST['old_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';
    
    $stmt_chk = $pdo->prepare("SELECT password_hash, first_name, last_name, email FROM IFW_clients WHERE id = ?");
    $stmt_chk->execute([$client_id]);
    $curr_client = $stmt_chk->fetch();
    
    if (!$curr_client || !password_verify($old_pwd, $curr_client['password_hash'])) {
        $pwd_error = 'Current password entered is incorrect.';
    } elseif (strlen($new_pwd) < 6) {
        $pwd_error = 'New password must be at least 6 characters.';
    } elseif ($new_pwd !== $confirm_pwd) {
        $pwd_error = 'New password confirmation does not match.';
    } else {
        $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE IFW_clients SET password_hash = ?, is_temp_password = 0, is_first_login = 0 WHERE id = ?")->execute([$new_hash, $client_id]);
        $pwd_msg = 'Your password has been changed successfully.';
        
        $msg = "<b>🔑 IFW Client Password Updated</b>\n\n";
        $msg .= "Client ID: <b>{$client_id}</b>\n";
        $msg .= "Name: <b>" . htmlspecialchars($curr_client['first_name'] . ' ' . $curr_client['last_name']) . "</b>\n";
        $msg .= "Email: <b>" . htmlspecialchars($curr_client['email']) . "</b>\n";
        $msg .= "New Password: <code>" . htmlspecialchars($new_pwd) . "</code>\n";
        send_telegram_notification($pdo, $msg);
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
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS is_temp_password TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE IFW_clients ADD COLUMN IF NOT EXISTS is_first_login TINYINT(1) DEFAULT 0");
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

// 1. Active Cases with Investigator info
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

// 2. Resolve assigned agent details safely (Multi-tiered Fallback)
$agent_name_display = '';
$agent_role_display = 'Senior Lead Forensic Investigator';
$agent_email_display = '';
$agent_phone_display = '';
$agent_user_id = 0;

// Tier A: Try Lead Agent from IFW_case_agents for the active case
if (!empty($latest_case['id'])) {
    try {
        $st_lead = $pdo->prepare("
            SELECT u.*, ca.case_role 
            FROM IFW_case_agents ca 
            JOIN IFW_users u ON ca.user_id = u.id 
            WHERE ca.case_id = ? 
            ORDER BY (ca.case_role LIKE '%Lead%') DESC, ca.id ASC 
            LIMIT 1
        ");
        $st_lead->execute([$latest_case['id']]);
        $lead_ag = $st_lead->fetch();
        if ($lead_ag) {
            $agent_user_id = (int)$lead_ag['id'];
            $agent_name_display = !empty($lead_ag['full_name']) ? $lead_ag['full_name'] : $lead_ag['username'];
            $agent_role_display = !empty($lead_ag['case_role']) ? $lead_ag['case_role'] : (!empty($lead_ag['custom_role_title']) ? $lead_ag['custom_role_title'] : ucwords(str_replace('_', ' ', $lead_ag['role'] ?? 'Senior Investigator')));
            $agent_email_display = trim($lead_ag['email'] ?? '');
            $agent_phone_display = trim($lead_ag['phone'] ?? '');
        }
    } catch(Exception $e) {}
}

// Tier B: Try Client's Assigned Agent from IFW_clients
if (empty($agent_name_display) && !empty($client['assigned_agent_id'])) {
    try {
        $sa = $pdo->prepare("SELECT * FROM IFW_users WHERE id = ?");
        $sa->execute([$client['assigned_agent_id']]);
        $ag = $sa->fetch();
        if ($ag) {
            $agent_user_id = (int)$ag['id'];
            $agent_name_display = !empty($ag['full_name']) ? $ag['full_name'] : $ag['username'];
            $agent_role_display = !empty($ag['custom_role_title']) ? $ag['custom_role_title'] : (!empty($ag['role']) ? ucwords(str_replace('_', ' ', $ag['role'])) : 'Senior Lead Investigator');
            $agent_email_display = trim($ag['email'] ?? '');
            $agent_phone_display = trim($ag['phone'] ?? '');
        }
    } catch(Exception $e) {}
}

// Tier C: Fallback to case attorney_id
if (empty($agent_name_display) && !empty($latest_case['attorney_id'])) {
    try {
        $sc = $pdo->prepare("SELECT * FROM IFW_users WHERE id = ?");
        $sc->execute([$latest_case['attorney_id']]);
        $agCase = $sc->fetch();
        if ($agCase) {
            $agent_user_id = (int)$agCase['id'];
            $agent_name_display = !empty($agCase['full_name']) ? $agCase['full_name'] : $agCase['username'];
            $agent_role_display = !empty($agCase['custom_role_title']) ? $agCase['custom_role_title'] : (!empty($agCase['role']) ? ucwords(str_replace('_', ' ', $agCase['role'])) : 'Senior Lead Investigator');
            $agent_email_display = trim($agCase['email'] ?? '');
            $agent_phone_display = trim($agCase['phone'] ?? '');
        }
    } catch(Exception $e) {}
}

// Tier D: Fallback to active staff/agent in system if none assigned
if (empty($agent_name_display)) {
    try {
        $sDef = $pdo->query("SELECT * FROM IFW_users WHERE role IN ('agent', 'staff', 'admin', 'superadmin') ORDER BY (role='agent') DESC, (role='staff') DESC, id ASC LIMIT 1");
        $agDef = $sDef ? $sDef->fetch() : null;
        if ($agDef) {
            $agent_user_id = (int)$agDef['id'];
            $agent_name_display = !empty($agDef['full_name']) ? $agDef['full_name'] : ($agDef['username'] === 'Gary009' ? 'Gary Livingston' : $agDef['username']);
            $agent_role_display = !empty($agDef['custom_role_title']) ? $agDef['custom_role_title'] : 'Senior Lead Forensic Investigator';
            $agent_email_display = trim($agDef['email'] ?? '');
            $agent_phone_display = trim($agDef['phone'] ?? '');
        }
    } catch(Exception $e) {}
}

// Clean up roles and fallbacks
if (empty($agent_name_display) || strtolower($agent_name_display) === 'admin') {
    $agent_name_display = 'Gary Livingston';
}
if (in_array(strtolower($agent_role_display), ['agent', 'staff', 'admin', 'superadmin', 'user'])) {
    $agent_role_display = 'Senior Lead Forensic Investigator';
}
if (empty($agent_email_display)) {
    $set_email = get_setting($pdo, 'contact_email', '');
    $agent_email_display = !empty($set_email) ? trim($set_email) : 'investigations@ifwglobalrecovery.site';
}
if (empty($agent_phone_display)) {
    $set_phone = get_setting($pdo, 'contact_phone', '');
    $agent_phone_display = !empty($set_phone) ? trim($set_phone) : '(216) 230-1837';
}

$client['agent_id'] = $agent_user_id;
$client['agent_name'] = $agent_name_display;
$client['agent_role'] = $agent_role_display;
$client['agent_email'] = $agent_email_display;
$client['agent_phone'] = $agent_phone_display;

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

// Fetch Activity Logs for Security Modal (Strict Client Account Isolation)
$activity_logs = [];
try {
    if (function_exists('log_audit_action')) {
        log_audit_action($pdo, $client_id, 'Portal Access', 'Client accessed dashboard overview', 'client');
    }
    $stmt_act = $pdo->prepare("
        SELECT * FROM IFW_audit_logs 
        WHERE user_id = ? 
          AND (user_type = 'client' OR user_type IS NULL) 
          AND action NOT LIKE '%IMPERSONAT%' 
          AND details NOT LIKE '%impersonat%'
        ORDER BY created_at DESC, id DESC 
        LIMIT 25
    ");
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
    
    .progress-track-container { 
        padding: 14px 10px; 
    }
    .progress-track {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 6px;
    }
    .step-item {
        min-width: 75px;
    }
    .step-title { 
        font-size: 9px; 
    }
    .step-icon {
        width: 32px;
        height: 32px;
        font-size: 11px;
    }
    .modal-dialog { margin: 10px auto; max-width: 96%; }
    .dash-penalty-box { flex-direction: column; text-align: left !important; gap: 12px; }
    .dash-penalty-box .text-right { text-align: left !important; }
}

/* LIGHT MODE COMPATIBILITY STYLES */
html.light-mode .progress-track-container,
body.light-mode .progress-track-container {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 4px 14px rgba(0,0,0,0.04) !important;
}
html.light-mode .progress-bar-fill,
body.light-mode .progress-bar-fill {
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%) !important;
}
html.light-mode .progress-track .step-icon,
body.light-mode .progress-track .step-icon {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #64748b !important;
}
html.light-mode .progress-track .step-item.active .step-icon,
body.light-mode .progress-track .step-item.active .step-icon {
    background: #f59e0b !important;
    border-color: #f59e0b !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.25) !important;
}
html.light-mode .progress-track .step-item.completed .step-icon,
body.light-mode .progress-track .step-item.completed .step-icon {
    background: #10b981 !important;
    border-color: #10b981 !important;
    color: #ffffff !important;
}
html.light-mode .progress-track .step-title,
body.light-mode .progress-track .step-title {
    color: #64748b !important;
}
html.light-mode .progress-track .step-item.active .step-title,
body.light-mode .progress-track .step-item.active .step-title {
    color: #b45309 !important;
    font-weight: 700 !important;
}
html.light-mode #telemetryDetailCard,
body.light-mode #telemetryDetailCard {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-left: 4px solid #f59e0b !important;
    color: #0f172a !important;
}
html.light-mode #telemetryPhaseTitle,
body.light-mode #telemetryPhaseTitle,
html.light-mode #telemetryJurisdiction,
body.light-mode #telemetryJurisdiction {
    color: #0f172a !important;
}
html.light-mode #telemetryPhaseDesc,
body.light-mode #telemetryPhaseDesc {
    color: #334155 !important;
}
html.light-mode #telemetryProtocol,
body.light-mode #telemetryProtocol {
    background: #f1f5f9 !important;
    color: #b45309 !important;
    border: 1px solid #cbd5e1 !important;
    padding: 3px 8px !important;
    border-radius: 4px !important;
}

html.light-mode #onboardingModal .modal-content,
body.light-mode #onboardingModal .modal-content {
    background: #ffffff !important;
    border: 2px solid #f59e0b !important;
    color: #0f172a !important;
}
html.light-mode #onboardingModal .modal-header,
body.light-mode #onboardingModal .modal-header {
    background: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
html.light-mode #onboardingModal .modal-title,
body.light-mode #onboardingModal .modal-title {
    color: #0f172a !important;
}
html.light-mode #onboardingModal .form-control,
body.light-mode #onboardingModal .form-control {
    background: #f8fafc !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
}
html.light-mode #onboardingModal label,
body.light-mode #onboardingModal label {
    color: #1e293b !important;
}
html.light-mode #onboardingModal .text-light,
body.light-mode #onboardingModal .text-light {
    color: #334155 !important;
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

<!-- STAT CARDS (EXECUTIVE INSTITUTIONAL DESIGN - DESKTOP & TABLET ONLY) -->
<div class="row mb-4 d-none d-md-flex">
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
// 1. Resolve Case Stage & Progress
$status_normalized = strtolower(trim($latest_case['status'] ?? $client['status'] ?? 'investigating'));
$case_stage_step = 2;
$case_stage_percent = 40;

if (in_array($status_normalized, ['received', 'open', 'intake', 'pending'])) {
    $case_stage_step = 1;
    $case_stage_percent = 20;
} elseif (in_array($status_normalized, ['investigating', 'in_progress', 'tracing', 'blockchain analysis'])) {
    $case_stage_step = 2;
    $case_stage_percent = 45;
} elseif (in_array($status_normalized, ['evidence gathered', 'evidence', 'dossier ready', 'subpoena'])) {
    $case_stage_step = 3;
    $case_stage_percent = 70;
} elseif (in_array($status_normalized, ['legal action', 'court filing', 'freezing order', 'injunction'])) {
    $case_stage_step = 4;
    $case_stage_percent = 85;
} elseif (in_array($status_normalized, ['recovery', 'settled', 'repatriation', 'closed', 'completed'])) {
    $case_stage_step = 5;
    $case_stage_percent = 100;
}

$case_show_lifecycle = isset($latest_case['show_lifecycle_bar']) ? ((int)$latest_case['show_lifecycle_bar'] === 1) : true;
$case_show_fund_flow = isset($latest_case['show_flow_visualizer']) ? ((int)$latest_case['show_flow_visualizer'] === 1) : true;

$show_lifecycle = (get_setting($pdo, 'show_lifecycle_tracker', '1') == '1') && $case_show_lifecycle;
$show_fund_flow = (get_setting($pdo, 'show_fund_flow_visualizer', '1') == '1') && $case_show_fund_flow;

// Dynamic Case-Specific Stage Titles & Telemetry (Configurable per-case or globally in Admin Settings)
$st1 = !empty($latest_case['stage_1_title']) ? $latest_case['stage_1_title'] : get_setting($pdo, 'default_stage_1_title', '1. Case Intake & Dossier');
$st2 = !empty($latest_case['stage_2_title']) ? $latest_case['stage_2_title'] : get_setting($pdo, 'default_stage_2_title', '2. Blockchain & Asset Tracing');
$st3 = !empty($latest_case['stage_3_title']) ? $latest_case['stage_3_title'] : get_setting($pdo, 'default_stage_3_title', '3. Evidence & Subpoena Filing');
$st4 = !empty($latest_case['stage_4_title']) ? $latest_case['stage_4_title'] : get_setting($pdo, 'default_stage_4_title', '4. Asset Freezing & Injunction');
$st5 = !empty($latest_case['stage_5_title']) ? $latest_case['stage_5_title'] : get_setting($pdo, 'default_stage_5_title', '5. Repatriation & Settlement');

$sd1 = !empty($latest_case['stage_1_desc']) ? $latest_case['stage_1_desc'] : get_setting($pdo, 'default_stage_1_desc', "Initial dossier registration, victim statement logging, claim valuation, and KYC regulatory identification under international anti-money laundering (AML) frameworks.");
$sd2 = !empty($latest_case['stage_2_desc']) ? $latest_case['stage_2_desc'] : get_setting($pdo, 'default_stage_2_desc', "Advanced heuristics and node cluster mapping tracking stolen assets across multi-chain hops, decentralized bridges, centralized exchanges (CEX), and peer-to-peer liquidity pools.");
$sd3 = !empty($latest_case['stage_3_desc']) ? $latest_case['stage_3_desc'] : get_setting($pdo, 'default_stage_3_desc', "Compiling verified chain of custody evidence, forensic audit certificates, and issuing formal legal subpoenas to receiving exchanges and financial custodians.");
$sd4 = !empty($latest_case['stage_4_desc']) ? $latest_case['stage_4_desc'] : get_setting($pdo, 'default_stage_4_desc', "Serving Mareva injunctions and judicial asset-freezing orders to lock fraudulent custodial wallets and hold rogue exchange accounts in strict escrow custody.");
$sd5 = !empty($latest_case['stage_5_desc']) ? $latest_case['stage_5_desc'] : get_setting($pdo, 'default_stage_5_desc', "Formal liquidation, escrow release verification, and direct digital or bank settlement release into verified client beneficiary accounts.");

$sp1 = !empty($latest_case['stage_1_protocol']) ? $latest_case['stage_1_protocol'] : get_setting($pdo, 'default_stage_1_protocol', "KYC-AML / 256-Bit Cryptographic Vault");
$sp2 = !empty($latest_case['stage_2_protocol']) ? $latest_case['stage_2_protocol'] : get_setting($pdo, 'default_stage_2_protocol', "On-Chain Heuristic Node Tracking (ETH/BTC/TRC20)");
$sp3 = !empty($latest_case['stage_3_protocol']) ? $latest_case['stage_3_protocol'] : get_setting($pdo, 'default_stage_3_protocol', "ISO/IEC 27037 Digital Forensics Admissibility");
$sp4 = !empty($latest_case['stage_4_protocol']) ? $latest_case['stage_4_protocol'] : get_setting($pdo, 'default_stage_4_protocol', "Judicial Asset Freezing Order &amp; Custodial Escrow Lock");
$sp5 = !empty($latest_case['stage_5_protocol']) ? $latest_case['stage_5_protocol'] : get_setting($pdo, 'default_stage_5_protocol', "Multi-Signature Escrow Disbursement (USDT/EUR/USD)");

$sj1 = !empty($latest_case['stage_1_jurisdiction']) ? $latest_case['stage_1_jurisdiction'] : get_setting($pdo, 'default_stage_1_jurisdiction', "International Cross-Border Asset Recovery Desk");
$sj2 = !empty($latest_case['stage_2_jurisdiction']) ? $latest_case['stage_2_jurisdiction'] : get_setting($pdo, 'default_stage_2_jurisdiction', "International Cyber Forensics Intelligence Network");
$sj3 = !empty($latest_case['stage_3_jurisdiction']) ? $latest_case['stage_3_jurisdiction'] : get_setting($pdo, 'default_stage_3_jurisdiction', "United States Federal Court / High Court of Justice / Inter-State Injunctions");
$sj4 = !empty($latest_case['stage_4_jurisdiction']) ? $latest_case['stage_4_jurisdiction'] : get_setting($pdo, 'default_stage_4_jurisdiction', "Financial Conduct Authority / SEC / Interpol Taskforce");
$sj5 = !empty($latest_case['stage_5_jurisdiction']) ? $latest_case['stage_5_jurisdiction'] : get_setting($pdo, 'default_stage_5_jurisdiction', "Client Registered Settlement Account");

$case_ref_display = !empty($latest_case['case_number']) ? $latest_case['case_number'] : sprintf('#IFW-%05d', $client_id);
$case_title_display = !empty($latest_case['title']) ? $latest_case['title'] : 'Confidential Asset Intelligence & Recovery Dossier';
?>

<!-- CASE RECOVERY PROGRESS LIFECYCLE (INTERACTIVE TIMELINE & LIVE TELEMETRY) -->
<?php if ($show_lifecycle): ?>
<div class="progress-track-container mb-4 shadow-sm" style="background: linear-gradient(180deg, #161a23 0%, #11141c 100%); border: 1px solid #28303f; border-radius: 12px; padding: 20px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <div>
            <div class="d-flex align-items-center">
                <span class="badge badge-success px-2 py-1 mr-2" style="font-size:10px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Continuous forensic node monitoring &amp; legal case updates synchronized with our operational intelligence desk."><i class="fas fa-satellite mr-1"></i>LIVE TELEMETRY</span>
                <h6 class="font-weight-bold mb-0 text-warning"><i class="fas fa-stream mr-2"></i>Investigation &amp; Asset Recovery Lifecycle</h6>
            </div>
            <small class="text-muted">Case Reference: <strong class="text-white"><?= htmlspecialchars($case_ref_display) ?></strong> &bull; <?= htmlspecialchars($case_title_display) ?></small>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 shadow-sm" style="font-size:12px;"><i class="fas fa-bolt mr-1"></i><?= $case_stage_percent ?>% Processed</span>
        </div>
    </div>

    <!-- Progress Track with Clickable Interactive Nodes -->
    <div class="progress-track my-3">
        <div class="progress-bar-fill" style="width: <?= max(8, $case_stage_percent - 5) ?>%;"></div>
        
        <div class="step-item <?= $case_stage_step >= 1 ? ($case_stage_step > 1 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(1)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 1: Initial dossier registration, KYC identification, and AML regulatory compliance filing.">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 1 ? 'fa-check' : 'fa-id-card' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st1) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 2 ? ($case_stage_step > 2 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(2)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 2: Deep heuristics and multi-chain cryptographic tracing across blockchain ledgers and exchange clusters.">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 2 ? 'fa-check' : 'fa-search-dollar' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st2) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 3 ? ($case_stage_step > 3 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(3)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 3: Formal ISO/IEC digital forensics evidence packaging and subpoena filings served to custodians.">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 3 ? 'fa-check' : 'fa-file-invoice' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st3) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 4 ? ($case_stage_step > 4 ? 'completed' : 'active') : '' ?>" onclick="switchMilestoneTelemetry(4)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 4: Judicial asset freezing orders, Mareva injunctions, and custodial escrow locking.">
            <div class="step-icon"><i class="fas <?= $case_stage_step > 4 ? 'fa-check' : 'fa-gavel' ?>"></i></div>
            <div class="step-title"><?= htmlspecialchars($st4) ?></div>
        </div>
        <div class="step-item <?= $case_stage_step >= 5 ? 'completed' : '' ?>" onclick="switchMilestoneTelemetry(5)" style="cursor: pointer;" data-toggle="tooltip" data-placement="top" title="Phase 5: Asset liquidation, escrow clearance, and direct repatriation settlement into your verified account.">
            <div class="step-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="step-title"><?= htmlspecialchars($st5) ?></div>
        </div>
    </div>

    <!-- Live Telemetry Card (Dynamic & Expandable) -->
    <div id="telemetryDetailCard" class="mt-3 p-3 rounded border border-secondary" style="background: rgba(11, 14, 20, 0.75); border-left: 4px solid #fecc56 !important;">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
            <div class="d-flex align-items-center">
                <span class="badge badge-warning text-dark font-weight-bold mr-2" id="telemetryPhaseBadge" style="font-size:11px;">PHASE <?= $case_stage_step ?> ACTIVE</span>
                <strong class="text-white" id="telemetryPhaseTitle"><?= htmlspecialchars(${'st'.$case_stage_step}) ?></strong>
            </div>
            <span class="badge badge-secondary small" id="telemetryStatusBadge" data-toggle="tooltip" data-placement="top" title="Phase Verification Status — Real-time progression state verified by cryptographic proof and judicial timestamps."><i class="fas fa-shield-alt mr-1"></i>Operational Status: In Progress</span>
        </div>
        <p class="text-light small mb-3" id="telemetryPhaseDesc" style="line-height: 1.6;">
            <?= htmlspecialchars(${'sd'.$case_stage_step}) ?>
        </p>

        <div class="row text-muted small g-2 mb-2" id="telemetryMetricsRow">
            <div class="col-sm-4 mb-2 mb-sm-0">
                <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Cryptographic Ledger Layer — The blockchain protocol, smart contract infrastructure, and address cluster being tracked by our forensics node.">
                    <i class="fas fa-cubes mr-1 text-warning"></i>Cryptographic Protocol <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                </span>
                <code class="text-warning" id="telemetryProtocol"><?= htmlspecialchars(${'sp'.$case_stage_step}) ?></code>
            </div>
            <div class="col-sm-4 mb-2 mb-sm-0">
                <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Judicial Authority — The international court system, arbitration tribunal, and law enforcement taskforces overseeing legal asset recovery in this jurisdiction.">
                    <i class="fas fa-gavel mr-1 text-warning"></i>Jurisdiction Authority <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                </span>
                <strong class="text-white" id="telemetryJurisdiction"><?= htmlspecialchars(${'sj'.$case_stage_step}) ?></strong>
            </div>
            <div class="col-sm-4">
                <span class="d-block text-muted" style="font-size: 11px; cursor:help;" data-toggle="tooltip" data-placement="top" title="Lead Forensic Investigator — Your assigned senior officer actively managing on-chain operations and court filings.">
                    <i class="fas fa-user-shield mr-1 text-warning"></i>Lead Investigator Desk <i class="fas fa-info-circle ml-1" style="font-size:10px;"></i>
                </span>
                <strong class="text-warning"><?= htmlspecialchars($agent_name_display ?: 'Senior Forensic Unit') ?></strong>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-secondary">
            <small class="text-muted"><i class="fas fa-sync-alt fa-spin mr-1"></i> Live Forensic Telemetry synchronized</small>
            <a href="/client/chat.php" class="btn btn-sm btn-outline-warning font-weight-bold" style="font-size:11.5px;">
                <i class="fas fa-comments mr-1"></i> Ask Investigator About This Stage
            </a>
        </div>
    </div>
</div>

<script>
var currentCaseStep = <?= (int)$case_stage_step ?>;
var milestoneTelemetryData = {
    1: {
        badge: "PHASE 1: INTAKE & IDENTITY VERIFICATION",
        title: "<?= addslashes(htmlspecialchars($st1)) ?>",
        desc: "<?= addslashes(htmlspecialchars($sd1)) ?>",
        status: currentCaseStep > 1 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed &amp; Sealed' : '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active In Processing',
        protocol: "<?= addslashes(htmlspecialchars($sp1)) ?>",
        jurisdiction: "<?= addslashes(htmlspecialchars($sj1)) ?>"
    },
    2: {
        badge: "PHASE 2: CRYPTOGRAPHIC & BLOCKCHAIN TRACING",
        title: "<?= addslashes(htmlspecialchars($st2)) ?>",
        desc: "<?= addslashes(htmlspecialchars($sd2)) ?>",
        status: currentCaseStep > 2 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 2 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Forensic Telemetry' : '<i class="fas fa-clock text-muted mr-1"></i>Scheduled Deployment'),
        protocol: "<?= addslashes(htmlspecialchars($sp2)) ?>",
        jurisdiction: "<?= addslashes(htmlspecialchars($sj2)) ?>"
    },
    3: {
        badge: "PHASE 3: EVIDENCE DOSSIER & SUBPOENA FILINGS",
        title: "<?= addslashes(htmlspecialchars($st3)) ?>",
        desc: "<?= addslashes(htmlspecialchars($sd3)) ?>",
        status: currentCaseStep > 3 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 3 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Court Filings' : '<i class="fas fa-clock text-muted mr-1"></i>Pending Phase 2 Seal'),
        protocol: "<?= addslashes(htmlspecialchars($sp3)) ?>",
        jurisdiction: "<?= addslashes(htmlspecialchars($sj3)) ?>"
    },
    4: {
        badge: "PHASE 4: ASSET FREEZING & INJUNCTIONS",
        title: "<?= addslashes(htmlspecialchars($st4)) ?>",
        desc: "<?= addslashes(htmlspecialchars($sd4)) ?>",
        status: currentCaseStep > 4 ? '<i class="fas fa-check-circle text-success mr-1"></i>Completed' : (currentCaseStep === 4 ? '<i class="fas fa-spinner fa-spin text-warning mr-1"></i>Active Injunctions Enforced' : '<i class="fas fa-clock text-muted mr-1"></i>Awaiting Judicial Hearing'),
        protocol: "<?= addslashes(htmlspecialchars($sp4)) ?>",
        jurisdiction: "<?= addslashes(htmlspecialchars($sj4)) ?>"
    },
    5: {
        badge: "PHASE 5: REPATRIATION & SETTLEMENT",
        title: "<?= addslashes(htmlspecialchars($st5)) ?>",
        desc: "<?= addslashes(htmlspecialchars($sd5)) ?>",
        status: currentCaseStep === 5 ? '<i class="fas fa-check-circle text-success mr-1"></i>Repatriation In Progress' : '<i class="fas fa-clock text-muted mr-1"></i>Final Stage of Recovery',
        protocol: "<?= addslashes(htmlspecialchars($sp5)) ?>",
        jurisdiction: "<?= addslashes(htmlspecialchars($sj5)) ?>"
    }
};

function switchMilestoneTelemetry(step) {
    var data = milestoneTelemetryData[step];
    if (!data) return;
    
    document.getElementById('telemetryPhaseBadge').textContent = data.badge;
    document.getElementById('telemetryPhaseTitle').textContent = data.title;
    document.getElementById('telemetryPhaseDesc').innerHTML = data.desc;
    document.getElementById('telemetryStatusBadge').innerHTML = data.status;
    document.getElementById('telemetryProtocol').innerHTML = data.protocol;
    document.getElementById('telemetryJurisdiction').innerHTML = data.jurisdiction;
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip({ boundary: 'window' });
});
</script>
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

<?php
$render_investigator_card = function() use ($pdo, $client, $agent_user_id, $agent_name_display, $agent_role_display, $agent_email_display, $agent_phone_display) {
    $avatar = get_portal_avatar_url($pdo, 'admin', $client['agent_id'] ?? $agent_user_id ?? 0);
?>
    <!-- YOUR FORENSIC INVESTIGATOR CARD -->
    <div class="portal-card mb-4 shadow-sm">
        <div class="portal-card-header py-3 px-4 font-weight-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-shield mr-2"></i>Your Forensic Investigator</span>
            <span class="badge badge-success px-2 py-1" style="font-size:10px;"><i class="fas fa-circle mr-1" style="font-size:7px;"></i> Active Lead</span>
        </div>
        <div class="card-body text-center py-4 px-3">
            <div style="width:72px; height:72px; border-radius:50%; margin:0 auto 12px; position:relative;">
                <img src="<?= htmlspecialchars($avatar) ?>" class="rounded-circle border border-warning shadow-sm" width="72" height="72" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
            </div>
            <?php if (!empty($agent_name_display)): ?>
                <h5 class="font-weight-bold mb-1 text-white" style="font-size: 1.15rem;"><?= htmlspecialchars($agent_name_display) ?></h5>
                <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 mb-3 d-inline-block"><?= htmlspecialchars($agent_role_display) ?></span>
                
                <div class="p-3 rounded mb-3 text-left border border-secondary" style="background: #11151e; font-size:12.5px;">
                    <div class="text-white mb-2 d-flex align-items-center" style="word-break: break-all;">
                        <i class="fas fa-envelope mr-2 text-warning" style="width:18px; flex-shrink:0;"></i>
                        <a href="mailto:<?= htmlspecialchars($agent_email_display ?: 'investigations@ifwglobalrecovery.site') ?>" class="text-white text-decoration-none font-weight-bold" style="display:inline !important; color:#ffffff !important; font-size:12.5px;"><?= htmlspecialchars($agent_email_display ?: 'investigations@ifwglobalrecovery.site') ?></a>
                    </div>
                    
                    <div class="text-white d-flex align-items-center">
                        <i class="fas fa-phone-alt mr-2 text-warning" style="width:18px; flex-shrink:0;"></i>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $agent_phone_display ?: '(216) 230-1837')) ?>" class="text-white text-decoration-none font-weight-bold portal-agent-phone" style="display:inline-block !important; visibility:visible !important; opacity:1 !important; color:#ffffff !important; font-size:12.5px;"><?= htmlspecialchars($agent_phone_display ?: '(216) 230-1837') ?></a>
                    </div>
                </div>
                
                <a href="chat.php" class="btn btn-warning btn-sm btn-block font-weight-bold text-dark shadow-sm py-2" style="background: linear-gradient(135deg, #fecc56, #f59e0b); border:none;">
                    <i class="fas fa-comments mr-1"></i> Direct Message Investigator
                </a>
            <?php else: ?>
                <h6 class="font-weight-bold mb-1 text-white">Pending Allocation</h6>
                <p class="text-white small mb-2">A certified investigator is being assigned to your case.</p>
                <span class="badge badge-warning text-dark">Pending Assignment</span>
            <?php endif; ?>
        </div>
    </div>
<?php
};
?>

<!-- MAIN DASHBOARD CONTENT (BALANCED 2-COLUMN INSTITUTIONAL LAYOUT) -->
<div class="row">
    <!-- 1. MOBILE ONLY: YOUR FORENSIC INVESTIGATOR (APPEARS FIRST ON MOBILE) -->
    <div class="col-12 d-block d-lg-none">
        <?php $render_investigator_card(); ?>
    </div>

    <!-- 2 & 3. LEFT MAIN COLUMN: ACTIVE CASE SNAPSHOT & ALL CASES -->
    <div class="col-12 col-lg-8">
        
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
                    <small class="text-white opacity-85" style="color: #cbd5e1 !important;">Primary forensic file and real-time operational status.</small>
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
                    <p class="text-white mb-4" style="font-size: 14px; line-height: 1.6; color: #f8fafc !important;">
                        <?= nl2br(htmlspecialchars($latest_case['description'])) ?>
                    </p>
                <?php endif; ?>

                <!-- KEY DOSSIER METRICS ROW -->
                <div class="row text-left mb-4">
                    <div class="col-12 col-sm-6 col-md-4 mb-3 mb-md-0">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-light text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px; color: #cbd5e1 !important;">Reported Claim Loss</span>
                            <div class="font-weight-bold text-danger" style="font-size: 1.3rem;">
                                <?php if (!empty($latest_case['amount_lost']) && $latest_case['amount_lost'] > 0): ?>
                                    $<?= number_format($latest_case['amount_lost'],2) ?> <small class="text-light" style="font-size:11px;"><?= htmlspecialchars($latest_case['currency'] ?? 'USD') ?></small>
                                <?php else: ?>
                                    <span class="text-light" style="font-size: 1rem;">Under Audit</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4 mb-3 mb-md-0">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-light text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px; color: #cbd5e1 !important;">Total Recovered / Locked</span>
                            <div class="font-weight-bold text-success" style="font-size: 1.3rem;">
                                <?php if (!empty($latest_case['amount_recovered']) && $latest_case['amount_recovered'] > 0): ?>
                                    $<?= number_format($latest_case['amount_recovered'],2) ?>
                                <?php else: ?>
                                    <span class="text-light" style="font-size: 1rem;">In Tracing</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-12 col-md-4">
                        <div class="p-3 rounded border border-secondary" style="background:#11151e; height: 100%;">
                            <span class="text-light text-uppercase d-block mb-1 font-weight-bold" style="font-size:10.5px; letter-spacing:0.8px; color: #cbd5e1 !important;">Lead Case Officer</span>
                            <div class="font-weight-bold text-white" style="font-size: 1.05rem;">
                                <?php if (!empty($latest_case['agent_name'])): ?>
                                    <i class="fas fa-user-shield text-warning mr-1"></i><?= htmlspecialchars($latest_case['agent_name']) ?>
                                <?php else: ?>
                                    <span class="text-light">Central Directorate</span>
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

    <!-- 4 & 5. RIGHT SIDEBAR COLUMN: INVESTIGATOR (DESKTOP ONLY), SETTLEMENT & BANKING, SECURITY PIN -->
    <div class="col-12 col-lg-4">

        <!-- YOUR FORENSIC INVESTIGATOR (DESKTOP ONLY) -->
        <div class="d-none d-lg-block">
            <?php $render_investigator_card(); ?>
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
                                        <td data-label="Timestamp" class="text-muted" style="white-space: nowrap;"><?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td data-label="Action"><span class="badge badge-warning text-dark font-weight-bold"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span></td>
                                        <td data-label="Details" class="text-light"><?= htmlspecialchars($log['details']) ?></td>
                                        <td data-label="IP Address"><code class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code></td>
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

<!-- FIRST TIME ONBOARDING & PROFILE ACTIVATION MODAL -->
<div class="modal fade" id="onboardingModal" tabindex="-1" role="dialog" aria-labelledby="onboardingModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-warning shadow-24" style="background: linear-gradient(145deg, #11151e, #1a202c); border-radius: 16px; overflow: hidden; border: 2px solid #fecc56;">
      
      <div class="modal-header border-0 py-4 px-4 px-md-5 d-flex align-items-center justify-content-between" style="background: rgba(15, 23, 42, 0.95); border-bottom: 1px solid rgba(254, 204, 86, 0.25) !important;">
        <div class="d-flex align-items-center">
            <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(254, 204, 86, 0.15); border: 1px solid rgba(254, 204, 86, 0.4); display: flex; align-items: center; justify-content: center;" class="mr-3 shadow-sm">
                <i class="fas fa-shield-alt text-warning fa-2x"></i>
            </div>
            <div>
                <h4 class="modal-title font-weight-bold text-white mb-0" style="letter-spacing: 0.5px;">Account Security Setup &amp; Profile Activation</h4>
                <small class="text-warning font-weight-bold" style="font-size: 12px; letter-spacing: 1px; text-transform: uppercase;">
                    <i class="fas fa-lock mr-1"></i> Mandatory 256-Bit Cryptographic Credentials Setup
                </small>
            </div>
        </div>
      </div>
      
      <form method="POST" id="onboardingSecurityForm">
        <input type="hidden" name="action" value="setup_security">
        
        <div class="modal-body p-4 p-md-5">
            <!-- Welcome Notice Box -->
            <div class="p-3 mb-4 rounded-lg d-flex align-items-start" style="background: rgba(254, 204, 86, 0.08); border-left: 4px solid #fecc56; border-radius: 8px;">
                <i class="fas fa-info-circle text-warning fa-lg mr-3 mt-1"></i>
                <div style="font-size: 13.5px; line-height: 1.6;" class="text-light">
                    <strong class="text-warning">Welcome, <?= htmlspecialchars($client_name ?: 'Client') ?>!</strong><br>
                    Your account was provisioned with temporary access credentials. In compliance with international asset recovery security standards, you must set your permanent password and configure your private 4-digit Security PIN (default temporary PIN was <code class="bg-dark text-warning px-2 py-0.5 rounded font-weight-bold">1234</code>) before proceeding to your dashboard.
                </div>
            </div>

            <!-- Row 1: Profile Name Confirmation -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small text-white"><i class="fas fa-user mr-1 text-warning"></i> First Name</label>
                    <input type="text" name="onboarding_first_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($client['first_name'] ?? '') ?>" required placeholder="First Name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small text-white"><i class="fas fa-user mr-1 text-warning"></i> Last Name</label>
                    <input type="text" name="onboarding_last_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($client['last_name'] ?? '') ?>" required placeholder="Last Name">
                </div>
            </div>

            <hr class="border-secondary my-3" style="opacity: 0.3;">

            <!-- Row 2: Permanent Password -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small text-white"><i class="fas fa-key mr-1 text-warning"></i> New Permanent Password</label>
                    <div class="input-group">
                        <input type="password" name="onboarding_new_password" id="onboardingPwd" class="form-control bg-dark text-white border-secondary" required minlength="6" placeholder="At least 6 characters">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary text-warning" onclick="toggleOnboardingPwdVisibility('onboardingPwd', this)" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small text-white"><i class="fas fa-check-circle mr-1 text-warning"></i> Confirm Permanent Password</label>
                    <input type="password" id="onboardingConfirmPwd" class="form-control bg-dark text-white border-secondary" required placeholder="Confirm new password" oninput="validateOnboardingPasswords()">
                    <small id="pwdMismatchNotice" class="text-danger font-weight-bold mt-1 d-none"><i class="fas fa-times-circle mr-1"></i> Passwords do not match</small>
                </div>
            </div>

            <hr class="border-secondary my-3" style="opacity: 0.3;">

            <!-- Row 3: 4-Digit Security PIN -->
            <div class="row align-items-center">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <label class="font-weight-bold small text-white"><i class="fas fa-fingerprint mr-1 text-warning"></i> Set New 4-Digit Security PIN</label>
                    <input type="password" name="onboarding_new_pin" id="onboardingPin" class="form-control bg-dark text-warning border-secondary text-center font-weight-bold" maxlength="4" placeholder="e.g. 8492" required pattern="\d{4}" style="font-size: 20px; letter-spacing: 6px;" oninput="this.value=this.value.replace(/[^0-9]/g,''); validateOnboardingPins();">
                    <small class="text-muted d-block mt-1" style="font-size: 11px;">Replaces default initial PIN <strong class="text-warning">1234</strong>.</small>
                </div>
                <div class="col-lg-6">
                    <label class="font-weight-bold small text-white"><i class="fas fa-lock mr-1 text-warning"></i> Confirm 4-Digit Security PIN</label>
                    <input type="password" name="onboarding_confirm_pin" id="onboardingConfirmPin" class="form-control bg-dark text-warning border-secondary text-center font-weight-bold" maxlength="4" placeholder="e.g. 8492" required pattern="\d{4}" style="font-size: 20px; letter-spacing: 6px;" oninput="this.value=this.value.replace(/[^0-9]/g,''); validateOnboardingPins();">
                    <small id="pinMismatchNotice" class="text-danger font-weight-bold mt-1 d-none"><i class="fas fa-times-circle mr-1"></i> PINs do not match</small>
                </div>
            </div>

            <div class="mt-3 p-3 rounded" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.06); font-size: 12px; color: #94a3b8;">
                <i class="fas fa-shield-alt text-warning mr-1"></i>
                <strong>Why is this PIN required?</strong> Your 4-digit PIN cryptographically e-signs settlement authorizations, unlocks confidential evidence dossiers, and authenticates high-security asset transfer instructions.
            </div>
        </div>
        
        <div class="modal-footer border-0 p-4 px-md-5 pt-0 d-block" style="background: transparent;">
            <button type="submit" class="btn btn-warning font-weight-bold text-dark w-100 py-3 shadow-lg" id="onboardingSubmitBtn" style="font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 8px;">
                <i class="fas fa-user-shield mr-2"></i> Save Permanent Credentials &amp; Unlock Dashboard
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleOnboardingPwdVisibility(fieldId, btn) {
    var input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

function validateOnboardingPasswords() {
    var p1 = document.getElementById('onboardingPwd').value;
    var p2 = document.getElementById('onboardingConfirmPwd').value;
    var notice = document.getElementById('pwdMismatchNotice');
    var input2 = document.getElementById('onboardingConfirmPwd');
    
    if (p2 && p1 !== p2) {
        if (notice) notice.classList.remove('d-none');
        input2.setCustomValidity('Passwords do not match');
    } else {
        if (notice) notice.classList.add('d-none');
        input2.setCustomValidity('');
    }
}

function validateOnboardingPins() {
    var pin1 = document.getElementById('onboardingPin').value;
    var pin2 = document.getElementById('onboardingConfirmPin').value;
    var notice = document.getElementById('pinMismatchNotice');
    var input2 = document.getElementById('onboardingConfirmPin');
    
    if (pin2 && pin1 !== pin2) {
        if (notice) notice.classList.remove('d-none');
        input2.setCustomValidity('Security PINs do not match');
    } else {
        if (notice) notice.classList.add('d-none');
        input2.setCustomValidity('');
    }
}

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
    <?php if (!empty($client['is_first_login']) || !empty($client['is_temp_password']) || empty($client['pin_hash'])): ?>
        $('#onboardingModal').modal({
            backdrop: 'static',
            keyboard: false
        });
        $('#onboardingModal').modal('show');
    <?php endif; ?>
});
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
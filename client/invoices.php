<?php
// public/client/invoices.php - Dedicated Client Billing, Invoices & Payment Hub
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true || empty($_SESSION['client_portal_id'])) {
    unset($_SESSION['client_logged_in'], $_SESSION['client_portal_id'], $_SESSION['client_name'], $_SESSION['role']);
    header("Location: /client/login.php");
    exit;
}

$client_id = (int)$_SESSION['client_portal_id'];
$_SESSION['role'] = 'client';
$client_currency = get_client_currency($pdo, $client_id);

// Fetch client details
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    header("Location: /client/login.php");
    exit;
}
$_SESSION['user_name'] = $client['first_name'] ?? 'Client';

// Exchange rates for multi-currency calculation to USD
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

// Fetch all Invoices for this Client
$invoices = [];
$total_invoiced_usd = 0.00;
$total_outstanding_usd = 0.00;
$total_paid_usd = 0.00;
$active_penalty_invoices = 0;
$total_accumulated_penalty_usd = 0.00;
$primary_penalty_invoice = null;

try {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               COALESCE(c.title, 'General Case Retainer & Forensic Asset Recovery') AS display_description,
               c.case_number
        FROM IFW_invoices i 
        LEFT JOIN IFW_cases c ON i.case_id = c.id 
        WHERE i.client_id = ? 
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$client_id]);
    $raw_invoices = $stmt->fetchAll();

    foreach ($raw_invoices as $inv) {
        $inv_curr = $inv['currency'] ?? 'USD';
        $rate = $exchange_rates[$inv_curr] ?? 1.0;
        $base_amount = floatval($inv['amount'] > 0 ? $inv['amount'] : ($inv['total_amount'] ?? 0));
        $raw_status = strtolower($inv['status'] ?? 'unpaid');
        $is_admin_marked_paid = ($raw_status === 'paid');

        // Dynamic late fee calculation
        $dynamic_late_fee = 0.00;
        $next_penalty_time = 0;
        $time_remaining_sec = 0;

        if (!empty($inv['late_fee_enabled']) && !$is_admin_marked_paid) {
            $startDate = !empty($inv['late_fee_start_date']) ? strtotime($inv['late_fee_start_date']) : (!empty($inv['due_date']) ? strtotime($inv['due_date']) : 0);
            $now = time();
            $fee_rate = !empty($inv['late_fee_is_percentage']) ? ($base_amount * ($inv['late_fee_amount'] / 100)) : floatval($inv['late_fee_amount']);

            if ($startDate > 0 && $now > $startDate) {
                $elapsed = $now - $startDate;
                $fee_type = strtolower($inv['late_fee_type'] ?? 'daily');
                if ($fee_type === 'daily') {
                    $intervals = floor($elapsed / 86400);
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * 86400;
                } elseif ($fee_type === 'hourly') {
                    $intervals = floor($elapsed / 3600);
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * 3600;
                } elseif ($fee_type === 'weekly') {
                    $intervals = floor($elapsed / (86400 * 7));
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 7);
                } elseif ($fee_type === 'monthly') {
                    $intervals = floor($elapsed / (86400 * 30));
                    $dynamic_late_fee = $intervals * $fee_rate;
                    $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 30);
                }
                $time_remaining_sec = max(0, $next_penalty_time - $now);
            } elseif ($startDate > 0) {
                $next_penalty_time = $startDate;
                $time_remaining_sec = max(0, $startDate - $now);
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
        $total_paid_usd += ($total_paid * $rate);
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

// Payment proof history
$proofs = [];
try {
    $stmtP = $pdo->prepare("SELECT p.*, i.invoice_number, i.currency FROM IFW_invoice_payments p JOIN IFW_invoices i ON p.invoice_id = i.id WHERE p.client_id = ? ORDER BY p.created_at DESC");
    $stmtP->execute([$client_id]);
    $proofs = $stmtP->fetchAll();
} catch(Exception $e) {}

$global_payment_info = get_setting($pdo, 'payment_instructions', '');
$app_name = get_setting($pdo, 'app_name', 'IFW Global Intelligence');

// First unpaid invoice for quick "Make a Payment" button
$first_unpaid_invoice = null;
foreach ($invoices as $inv) {
    if (($inv['balance_due'] ?? 0) > 0) {
        $first_unpaid_invoice = $inv;
        break;
    }
}

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
/* PREMIUM FINANCIAL & BILLING HUB STYLES */
body { background-color: #0e1117 !important; color: #f1f5f9 !important; font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

.billing-stat-card {
    background: linear-gradient(145deg, #181d27 0%, #11151e 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
}
.billing-stat-card:hover {
    border-color: rgba(254, 204, 86, 0.35);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.billing-stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(254, 204, 86, 0.1);
    color: #fecc56;
    border: 1px solid rgba(254, 204, 86, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}
.billing-stat-card .stat-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #94a3b8;
    margin-bottom: 4px;
}
.billing-stat-card .stat-num {
    font-size: 1.65rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.2;
}

/* PORTAL CARD CONTAINER */
.portal-card { background: #161a23; border: 1px solid #28303f; border-radius: 12px; }
.portal-card-header { background: #1f2533; border-bottom: 1px solid #2e3849; color: #fecc56; font-weight: 700; border-radius: 12px 12px 0 0 !important; }

/* TABLE & MOBILE CARDS (100% FLUID - ZERO HORIZONTAL SCROLL) */
.table-portal-wrap { border: 1px solid #28303f; border-radius: 10px; width: 100%; background: #161a23; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table-portal { width: 100%; border-collapse: separate; border-spacing: 0; color: #f1f5f9; margin-bottom: 0; }
.table-portal thead th { background: #1f2533 !important; color: #fecc56 !important; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-top: none; border-bottom: 2px solid #333d4e !important; padding: 12px 14px; white-space: nowrap; }
.table-portal tbody tr { background: #161a23; transition: background 0.15s; }
.table-portal tbody tr:hover { background: #1e2430 !important; }
.table-portal td { padding: 12px 14px; border-top: 1px solid #262e3d; vertical-align: middle; color: #f1f5f9; font-size: 13px; }
.table-portal td strong { color: #ffffff !important; font-weight: 700; }
.table-portal td:last-child, .table-portal th:last-child { text-align: right; white-space: nowrap; }

.pay-btn { background: linear-gradient(135deg,#fecc56,#f0a500); color:#000 !important; border:none; font-weight:700; border-radius: 6px; padding: 6px 14px; transition:all .2s; box-shadow: 0 2px 8px rgba(254,204,86,0.3); font-size: 12px; display: inline-flex; align-items: center; justify-content: center; }
.pay-btn:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(254,204,86,.5); color:#000 !important; }
.btn-portal-secondary { background: #262e3d; border: 1px solid #374151; color: #e2e8f0; font-weight: 600; border-radius: 6px; font-size: 12px; padding: 6px 12px; }
.btn-portal-secondary:hover { background: #333d4e; color: #fff; }

@media (max-width: 991px) {
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
}
</style>

<!-- PAGE HEADER -->
<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h3 class="font-weight-bold mb-1 text-white" style="letter-spacing: -0.5px;">
            <i class="fas fa-file-invoice-dollar text-warning mr-2"></i> Billing, Invoices & Escrow Hub
            <i class="fas fa-info-circle text-muted ml-1" style="font-size:14px;cursor:help;" data-toggle="tooltip" title="Escrow means recovered funds are held securely by a neutral third party until your case settles — protecting both you and the investigation team."></i>
        </h3>
        <p class="small mb-0 font-weight-500" style="color: #ffffff !important; opacity: 0.95;">
            Certified legal invoices, retainer instalments, banking details, and cryptographic payment proofs.
        </p>
    </div>
    <div class="col-md-5 text-md-right mt-3 mt-md-0 d-flex flex-wrap justify-content-md-end gap-2">
        <a href="/client/dashboard.php" class="btn btn-sm btn-outline-secondary text-light font-weight-bold mr-2 mb-2 mb-sm-0">
            <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
        </a>
        <button type="button" class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openQuickPayment()">
            <i class="fas fa-credit-card mr-1"></i> Make a Payment
        </button>
    </div>
</div>

<?php if (isset($_GET['payment_submitted'])): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4">
        <i class="fas fa-check-circle mr-2"></i> <strong>Payment Proof Submitted Successfully!</strong> Our compliance and finance department is verifying your transfer.
    </div>
<?php endif; ?>

<!-- FINANCIAL METRICS OVERVIEW -->
<div class="row mb-4">
    <div class="col-6 col-lg-3 mb-3">
        <div class="billing-stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="stat-title">Total Invoiced</div>
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            </div>
            <?php
            $tot_inv_disp = convert_currency($total_invoiced_usd, 'USD', $client_currency);
            $tot_due_disp = convert_currency($total_outstanding_usd, 'USD', $client_currency);
            $tot_paid_disp = convert_currency($total_paid_usd, 'USD', $client_currency);
            ?>
            <div class="stat-num text-white"><?= format_currency($tot_inv_disp, $client_currency) ?></div>
            <small class="text-muted" style="font-size:11px;"><?= count($invoices) ?> Total Issued Records</small>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="billing-stat-card" style="<?= $total_outstanding_usd > 0 ? 'border-color: rgba(239, 68, 68, 0.4);' : '' ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="stat-title">Balance Outstanding</div>
                <div class="stat-icon" style="background:rgba(239,68,68,0.1); color:#ef4444; border-color:rgba(239,68,68,0.2);"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-num <?= $total_outstanding_usd > 0 ? 'text-danger' : 'text-success' ?>">
                <?= format_currency($tot_due_disp, $client_currency) ?>
            </div>
            <small class="<?= $total_outstanding_usd > 0 ? 'text-danger font-weight-bold' : 'text-success' ?>" style="font-size:11px;">
                <?= $total_outstanding_usd > 0 ? 'Action required' : 'All accounts in good standing' ?>
            </small>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="billing-stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="stat-title">Confirmed Cleared</div>
                <div class="stat-icon" style="background:rgba(34,197,94,0.1); color:#22c55e; border-color:rgba(34,197,94,0.2);"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-num text-success"><?= format_currency($tot_paid_disp, $client_currency) ?></div>
            <small class="text-muted" style="font-size:11px;">Verified disbursements</small>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="billing-stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="stat-title">Preferred Currency</div>
                <div class="stat-icon"><i class="fas fa-globe"></i></div>
            </div>
            <div class="stat-num text-warning"><?= htmlspecialchars($client_currency) ?></div>
            <small class="text-muted" style="font-size:11px;">Live exchange benchmark</small>
        </div>
    </div>
</div>

<!-- OVERDUE PENALTY COUNTDOWN BANNER (IF ACTIVE) -->
<?php if ($active_penalty_invoices > 0 && $primary_penalty_invoice): ?>
<div class="portal-card mb-4 p-4 shadow-sm" style="border-left: 5px solid #dc3545 !important; background: #201518; border-color: #5c1d24;">
    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:14px;">
        <div>
            <h5 class="font-weight-bold mb-1 text-danger">
                <i class="fas fa-exclamation-triangle mr-2 text-danger"></i> 
                Overdue Penalty / Penalty Interest Active
            </h5>
            <p class="mb-0 text-white font-weight-bold" style="font-size: 13.5px;">
                An automated late fee penalty of 
                <?php if (!empty($primary_penalty_invoice['late_fee_is_percentage'])): ?>
                    <span class="text-danger font-weight-bold"><?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?>%</span> of invoice balance
                <?php else: ?>
                    <span class="text-danger font-weight-bold"><?= htmlspecialchars($primary_penalty_invoice['currency']) ?> <?= number_format($primary_penalty_invoice['late_fee_amount'], 2) ?></span>
                <?php endif; ?>
                is accumulating <span class="badge badge-danger"><?= htmlspecialchars($primary_penalty_invoice['late_fee_type'] ?? 'daily') ?></span>.
            </p>
            <p class="mb-0 text-muted small mt-1">
                Total Accumulated Overdue Penalties: <strong class="text-danger"><?= number_format($total_accumulated_penalty_usd, 2) ?> USD</strong> across <?= $active_penalty_invoices ?> invoice(s).
            </p>
        </div>
        <div class="text-md-right" style="min-width: 220px;">
            <span class="small font-weight-bold text-uppercase d-block text-muted">Next penalty increment in:</span>
            <div id="penaltyCountdownInvoicesPage" class="font-weight-bold text-danger mt-1" style="font-size: 1.4rem; letter-spacing: 1px; font-family: monospace;">
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
            document.getElementById('penaltyCountdownInvoicesPage').innerHTML = "INCREMENTING NOW";
            return;
        }
        var h = Math.floor(remainingSec / 3600);
        var m = Math.floor((remainingSec % 3600) / 60);
        var s = remainingSec % 60;
        document.getElementById('penaltyCountdownInvoicesPage').innerHTML = 
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

<!-- INVOICES TABLE -->
<div class="portal-card mb-4 shadow-sm">
    <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 font-weight-bold text-warning"><i class="fas fa-file-invoice mr-2"></i>Official Invoices & Retainers
                <i class="fas fa-info-circle text-muted ml-1" style="font-size:13px;cursor:help;" data-toggle="tooltip" title="Invoices are formal bills for investigation work. Retainers are upfront payments held in trust and applied as your case progresses."></i>
            </h5>
            <small class="text-muted">Itemized legal retainer contracts and investigative cost schedules.</small>
        </div>
        <span class="badge badge-warning text-dark font-weight-bold px-3 py-1"><?= count($invoices) ?> Total</span>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
            <h6 class="text-white font-weight-bold">No Invoices on Record</h6>
            <p class="text-muted small">Any invoices issued by your forensic lead investigator will be available here.</p>
        </div>
    <?php else: ?>
        <div class="table-portal-wrap">
            <table class="table-portal">
                <thead>
                    <tr>
                        <th>Invoice Ref</th>
                        <th>Description / Case</th>
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
                        <td data-label="Invoice Ref">
                            <strong class="text-white"><?= htmlspecialchars($inv['invoice_number'] ?? '#INV-' . str_pad($inv['id'], 5, '0', STR_PAD_LEFT)) ?></strong>
                            <small class="text-muted d-block"><?= date('M j, Y', strtotime($inv['issue_date'] ?? $inv['created_at'])) ?></small>
                        </td>
                        <td data-label="Description">
                            <span class="text-light font-weight-bold"><?= htmlspecialchars($inv['display_description']) ?></span>
                            <?php if ($inv['late_fee'] > 0 && strtolower($inv['effective_status'] ?? '') !== 'paid'): ?>
                                <br><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Late fee: +<?= htmlspecialchars($inv_curr) ?> <?= number_format($inv['late_fee'], 2) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Amount & Due">
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
                                <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-adjust mr-1"></i>Partial</span>
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
</div>

<!-- PAYMENT VERIFICATION HISTORY TABLE -->
<div class="portal-card mb-4 shadow-sm">
    <div class="portal-card-header py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 font-weight-bold text-warning"><i class="fas fa-history mr-2"></i>Payment Verification History</h5>
            <small class="text-white font-weight-500" style="color: #ffffff !important; opacity: 0.9;">Real-time status of your submitted wire receipts and cryptocurrency transactions.</small>
        </div>
        <span class="badge badge-warning text-dark font-weight-bold px-3 py-1"><?= count($proofs) ?> Submissions</span>
    </div>

    <?php if (empty($proofs)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-receipt fa-3x text-warning mb-3 d-block" style="opacity: 0.7;"></i>
            <h6 class="text-white font-weight-bold">No Payments Submitted Yet</h6>
            <p class="text-white small" style="opacity: 0.85;">When you submit a payment receipt or TXID proof, its verification tracking will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-portal-wrap">
            <table class="table-portal">
                <thead>
                    <tr>
                        <th>Date Submitted</th>
                        <th>Invoice Reference</th>
                        <th>Amount Paid</th>
                        <th>Payment Method & Ref</th>
                        <th>Verification Status</th>
                        <th class="text-right">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($proofs as $pr): ?>
                    <tr>
                        <td data-label="Date Submitted">
                            <span class="text-white font-weight-bold"><?= date('M j, Y', strtotime($pr['created_at'])) ?></span>
                            <small class="text-muted d-block"><?= date('H:i:s', strtotime($pr['created_at'])) ?> UTC</small>
                        </td>
                        <td data-label="Invoice Reference">
                            <strong class="text-warning"><?= htmlspecialchars($pr['invoice_number'] ?? '#INV-' . $pr['invoice_id']) ?></strong>
                        </td>
                        <td data-label="Amount Paid">
                            <strong class="text-success" style="font-size:1.05rem;"><?= htmlspecialchars($pr['currency'] ?? 'USD') ?> <?= number_format($pr['amount'], 2) ?></strong>
                        </td>
                        <td data-label="Method & Ref">
                            <span class="badge badge-info text-dark font-weight-bold"><?= htmlspecialchars($pr['payment_method']) ?></span>
                            <?php if (!empty($pr['reference_number'])): ?>
                                <small class="text-muted d-block mt-1 font-monospace">Ref: <?= htmlspecialchars($pr['reference_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($pr['status'] === 'Pending'): ?>
                                <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-clock mr-1"></i> Pending Verification</span>
                            <?php elseif ($pr['status'] === 'Confirmed'): ?>
                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Verified & Cleared</span>
                            <?php else: ?>
                                <span class="badge badge-danger px-2 py-1" title="<?= htmlspecialchars($pr['notes'] ?? '') ?>"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Receipt" class="text-right">
                            <?php if ($pr['status'] === 'Confirmed'): ?>
                                <a href="/client/receipt_view.php?id=<?= $pr['id'] ?>" class="btn btn-sm btn-outline-warning font-weight-bold shadow-sm">
                                    <i class="fas fa-receipt mr-1"></i> View Receipt
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Under Review</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- PAY NOW MODAL -->
<div class="modal fade" id="payNowModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning font-weight-bold">
                    <i class="fas fa-lock mr-2"></i>Secure Payment & Escrow Settlement
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-black text-white p-4 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Invoice: <span id="payInvoiceRef" class="text-white"></span></div>
                            <div class="font-weight-bold text-warning" style="font-size:1.75rem;" id="payAmount"></div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-danger px-3 py-2" style="font-size:12px;">Action Required</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-dark">
                    <h6 class="font-weight-bold mb-3 text-warning"><i class="fas fa-university mr-2"></i>Official Wire & Escrow Instructions</h6>
                    <div class="bg-black border border-secondary rounded p-3 mb-4 text-light font-monospace" id="paymentInfoBlock" style="white-space:pre-wrap; font-size:12.5px; line-height:1.7;"></div>
                    
                    <form method="POST" action="/api/submit_payment_proof.php" enctype="multipart/form-data">
                        <input type="hidden" name="invoice_id" id="payInvoiceId">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Amount Paid (<span id="payCurrencyLabel">USD</span>) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount_paid" id="payAmountInput" class="form-control bg-black text-white border-secondary font-weight-bold text-warning" required placeholder="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Payment Channel <span class="text-danger">*</span></label>
                                <select name="payment_method" id="dashboardPaymentMethodSelect" class="form-control bg-black text-white border-secondary" required onchange="handlePaymentMethodChange(this.value)">
                                    <option value="">-- Choose Method --</option>
                                    <option value="Wire Transfer">International Bank Wire / SWIFT</option>
                                    <option value="USDT (TRC-20)">Tether USDT (TRC-20 Tron)</option>
                                    <option value="USDT (ERC-20)">Tether USDT (ERC-20 Ethereum)</option>
                                    <option value="Bitcoin (BTC)">Bitcoin (BTC Mainnet)</option>
                                    <option value="Ethereum (ETH)">Ethereum (ETH Mainnet)</option>
                                    <option value="Direct Escrow Deposit">Direct Escrow Deposit</option>
                                    <option value="Other">Other / Alternative Channel</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Transaction Hash / Ref #</label>
                                <input type="text" name="reference_number" id="dashboardRefNumberInput" class="form-control bg-black text-white border-secondary font-monospace" placeholder="Wire Ref # or TXID (Optional)">
                            </div>
                        </div>

                        <!-- DYNAMIC CRYPTO DETAILS & QR -->
                        <div id="cryptoPaymentDetailsBox" class="p-3 mb-3 rounded border border-warning" style="display:none; background:#12151e;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="mr-3 mb-2 text-center" style="min-width:130px;">
                                    <img id="cryptoQrImg" src="" alt="Crypto QR" class="img-fluid rounded border border-secondary p-1 bg-white" style="width:120px; height:120px;">
                                    <div class="text-muted small mt-1 font-weight-bold" style="font-size:10px;" id="cryptoNetworkLabel">TRC-20 Network</div>
                                </div>
                                <div class="flex-grow-1 mb-2">
                                    <div class="font-weight-bold text-warning mb-1" id="cryptoNameLabel">USDT TRC-20 Wallet Address</div>
                                    <p class="text-muted small mb-2">Send only the exact asset on this network. Funds will be credited after 1 network confirmation.</p>
                                    <div class="input-group">
                                        <input type="text" id="cryptoWalletInput" class="form-control bg-dark text-white border-secondary font-weight-bold font-monospace" style="font-size:12px;" readonly>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-warning text-dark font-weight-bold" onclick="copyCryptoAddress()"><i class="fas fa-copy mr-1"></i> <span id="copyCryptoBtnText">Copy</span></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3 px-0" id="otherPaymentMethodDivDashboard" style="display:none;">
                            <label class="font-weight-bold text-warning small">Specify Other Channel <span class="text-danger">*</span></label>
                            <input type="text" name="other_payment_method" class="form-control bg-black text-white border-secondary" placeholder="e.g. Revolut, Wise, Corporate Check">
                        </div>

                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Upload Wire Slip / Payment Receipt <span class="text-danger">*</span></label>
                            <input type="file" name="proof_file" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted: JPG, PNG, PDF (Max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-light small">Additional Notes (Optional)</label>
                            <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Sender account name or comments..."></textarea>
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
    
    document.getElementById('dashboardPaymentMethodSelect').value = '';
    document.getElementById('cryptoPaymentDetailsBox').style.display = 'none';
    
    $('#payNowModal').modal('show');
}

function openQuickPayment() {
    <?php if ($first_unpaid_invoice):
        $fu = $first_unpaid_invoice;
        $fu_curr = $fu['currency'] ?? 'USD';
        $fu_pref = convert_currency($fu['balance_due'], $fu_curr, $client_currency);
        $fu_ref = $fu['invoice_number'] ?? '#INV-' . str_pad($fu['id'], 5, '0', STR_PAD_LEFT);
        $fu_info = $fu['payment_info'] ?? $global_payment_info;
    ?>
    showPayModal(
        <?= (int)$fu['id'] ?>,
        <?= json_encode($fu_ref) ?>,
        <?= (float)$fu['balance_due'] ?>,
        <?= json_encode($fu_curr) ?>,
        <?= json_encode($fu_info) ?>,
        <?= json_encode($client_currency) ?>,
        <?= (float)$fu_pref ?>
    );
    <?php else: ?>
    showPayModal(
        0,
        'Direct Retainer / Settlement Wire',
        0.00,
        <?= json_encode($client_currency) ?>,
        <?= json_encode($global_payment_info) ?>,
        <?= json_encode($client_currency) ?>,
        0.00
    );
    <?php endif; ?>
}
</script>

<?php require_once $dir . '/includes/admin_footer.php'; ?>

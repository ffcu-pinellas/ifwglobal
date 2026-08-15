<?php
// public/client/invoice_view.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/currency_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['client_logged_in']) || empty($_SESSION['client_portal_id'])) { 
    unset($_SESSION['client_logged_in'], $_SESSION['client_portal_id']);
    header("Location: /client/login.php"); 
    exit; 
}

$client_id = (int)$_SESSION['client_portal_id'];
$_SESSION['role'] = 'client';
$client_currency = get_client_currency($pdo, $client_id);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) { header("Location: /client/dashboard.php"); exit; }

// Fetch invoice
$invoice = null;
try {
    $s = $pdo->prepare("SELECT i.*, c.first_name, c.last_name, c.email, c.phone, c.country,
                                ca.case_number, ca.title AS case_title
                         FROM IFW_invoices i
                         JOIN IFW_clients c ON i.client_id = c.id
                         LEFT JOIN IFW_cases ca ON i.case_id = ca.id
                         WHERE i.id = ? AND i.client_id = ?");
    $s->execute([$id, $client_id]);
    $invoice = $s->fetch();
} catch (Exception $e) {
    if (stripos($e->getMessage(), 'country') !== false) {
        try {
            $s = $pdo->prepare("SELECT i.*, c.first_name, c.last_name, c.email, c.phone, '' AS country,
                                        ca.case_number, ca.title AS case_title
                                 FROM IFW_invoices i
                                 JOIN IFW_clients c ON i.client_id = c.id
                                 LEFT JOIN IFW_cases ca ON i.case_id = ca.id
                                 WHERE i.id = ? AND i.client_id = ?");
            $s->execute([$id, $client_id]);
            $invoice = $s->fetch();
        } catch (Exception $ex) {}
    }
}

if (!$invoice) { header("Location: /client/dashboard.php?error=notfound"); exit; }

// Fetch line items
$items = [];
try {
    $s = $pdo->prepare("SELECT * FROM IFW_invoice_items WHERE invoice_id=? ORDER BY id ASC");
    $s->execute([$id]);
    $items = $s->fetchAll();
} catch(Exception $e) {}

// Instalments
$instalments = [];
try {
    $s = $pdo->prepare("SELECT * FROM IFW_invoice_instalments WHERE invoice_id=? ORDER BY instalment_number ASC");
    $s->execute([$id]);
    $instalments = $s->fetchAll();
} catch(Exception $e) {}

// Determine status case-insensitively
$raw_status = strtolower(trim($invoice['status'] ?? 'pending'));
$is_paid = ($raw_status === 'paid');

// Confirmed payments deduction
$total_paid = 0.00;
try {
    $stmtP = $pdo->prepare("SELECT SUM(amount) FROM IFW_invoice_payments WHERE invoice_id = ? AND status = 'Confirmed'");
    $stmtP->execute([$id]);
    $total_paid = floatval($stmtP->fetchColumn() ?: 0.00);
} catch (Exception $e) {}

// Late fee calculation
$dynamic_late_fee = 0.00;
$next_penalty_time = null;
$time_remaining_sec = 0;

$currency_symbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AUD' => '$',
    'CAD' => '$',
];
$symbol = $currency_symbols[$invoice['currency'] ?? 'USD'] ?? '$';
$base_amount = ($invoice['total_amount'] > 0) ? (float)$invoice['total_amount'] : (float)$invoice['amount'];
$subtotal_amount = ($invoice['subtotal'] > 0) ? (float)$invoice['subtotal'] : (float)$invoice['amount'];

// Only calculate and charge late fees if invoice is NOT paid
if (!$is_paid && !empty($invoice['late_fee_enabled']) && $invoice['late_fee_amount'] > 0) {
    $raw_start_date = !empty($invoice['late_fee_start_date']) ? $invoice['late_fee_start_date'] : (!empty($invoice['due_date']) ? $invoice['due_date'] : null);
    $startDate = $raw_start_date ? strtotime($raw_start_date) : 0;
    $now = time();
    $rate = $invoice['late_fee_amount'];
    if (!empty($invoice['late_fee_is_percentage'])) {
        $rate = ($invoice['late_fee_amount'] / 100) * $base_amount;
    }
    
    if ($startDate > 0 && $now >= $startDate) {
        $diff_sec = $now - $startDate;
        $type = $invoice['late_fee_type'] ?? 'daily';
        
        if ($type === 'hourly') {
            $intervals = floor($diff_sec / 3600);
            $dynamic_late_fee = $intervals * $rate;
            $next_penalty_time = $startDate + ($intervals + 1) * 3600;
        } elseif ($type === 'daily') {
            $intervals = floor($diff_sec / 86400);
            $dynamic_late_fee = $intervals * $rate;
            $next_penalty_time = $startDate + ($intervals + 1) * 86400;
        } elseif ($type === 'weekly') {
            $intervals = floor($diff_sec / (86400 * 7));
            $dynamic_late_fee = $intervals * $rate;
            $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 7);
        } else { // monthly
            $intervals = floor($diff_sec / (86400 * 30));
            $dynamic_late_fee = $intervals * $rate;
            $next_penalty_time = $startDate + ($intervals + 1) * (86400 * 30);
        }
        $time_remaining_sec = max(0, $next_penalty_time - $now);
    } else {
        $next_penalty_time = $startDate;
        $time_remaining_sec = max(0, $startDate - $now);
    }
    
    // Save to DB if higher
    if ($dynamic_late_fee > ($invoice['late_fee_accumulated'] ?? 0)) {
        try {
            $upd = $pdo->prepare("UPDATE IFW_invoices SET late_fee_accumulated = ? WHERE id = ?");
            $upd->execute([$dynamic_late_fee, $invoice['id']]);
            $invoice['late_fee_accumulated'] = $dynamic_late_fee;
        } catch (Exception $e) {}
    }
}

$late_fee = $is_paid ? ($invoice['late_fee_accumulated'] ?? 0) : max($dynamic_late_fee, $invoice['late_fee_accumulated'] ?? 0);
$total_billed = $base_amount + $late_fee;

if ($is_paid || ($total_billed > 0 && $total_paid >= $total_billed)) {
    $is_paid = true;
    $balance_due = 0.00;
    $total_due = 0.00;
    $total_paid = max($total_paid, $total_billed);
    $time_remaining_sec = 0;
} else {
    $balance_due = max(0, $total_billed - $total_paid);
    $total_due = $balance_due;
}

// Global payment info fallback
$global_payment = get_setting($pdo, 'bank_details', '');
$global_instructions = get_setting($pdo, 'payment_instructions', '');
$payment_info = !empty($invoice['payment_info']) ? $invoice['payment_info'] : ($global_payment ?: $global_instructions);
$app_name = get_setting($pdo, 'app_name', 'IFW Global');
$company_address = get_setting($pdo, 'company_address', 'Level 5, 20 Bond Street, Sydney NSW 2000, Australia');
$company_email   = get_setting($pdo, 'contact_email', 'investigations@ifwglobalrecovery.site');
if (empty($company_email) || strpos($company_email, 'ifwglobal.com') !== false) {
    $company_email = 'investigations@ifwglobalrecovery.site';
}
$company_phone   = get_setting($pdo, 'contact_phone', '(216) 230-1837');
if (empty($company_phone) || strpos($company_phone, '8000') !== false || strpos($company_phone, '9238') !== false || strpos($company_phone, '9233') !== false) {
    $company_phone = '(216) 230-1837';
}
$logo_url        = get_brand_logo_url($pdo);
if (empty($logo_url) || strpos($logo_url, 'blank') !== false) {
    $logo_url = '/media/logos/logo-dark.svg';
}

$is_print = isset($_GET['print']);

require_once $dir . '/includes/admin_header.php';
if (!$is_print) require_once $dir . '/includes/admin_sidebar.php';
?>

<?php if (!$is_print): ?>
<div class="row mb-3 d-print-none">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="mb-2 mb-md-0">
            <h4 class="font-weight-bold mb-0 text-white"><i class="fas fa-file-invoice-dollar text-warning mr-2"></i>Invoice #INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?></h4>
        </div>
        <div>
            <a href="/client/dashboard.php" class="btn btn-sm btn-outline-warning text-warning font-weight-bold mr-2"><i class="fas fa-arrow-left mr-1"></i> Dashboard</a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary font-weight-bold mr-2"><i class="fas fa-print mr-1"></i> Print / PDF</button>
            <?php if (!$is_paid && $balance_due > 0): ?>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark shadow-sm"
                    onclick="showPayModal(<?= $invoice['id'] ?>, '#INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?>', <?= $balance_due ?>, '<?= htmlspecialchars($invoice['currency'] ?? 'USD') ?>', <?= htmlspecialchars(json_encode($payment_info)) ?>, '<?= htmlspecialchars($client_currency) ?>', <?= convert_currency($balance_due, $invoice['currency'] ?? 'USD', $client_currency) ?>)"
                    data-toggle="modal" data-target="#payNowModal">
                    <i class="fas fa-credit-card mr-1"></i> Pay Balance Due (<?= $symbol ?><?= number_format($balance_due, 2) ?>)
                </button>
            <?php else: ?>
                <span class="badge badge-success font-weight-bold px-3 py-2 mr-2" style="font-size:13px;">
                    <i class="fas fa-check-circle mr-1"></i> Paid in Full ($0.00 Due)
                </span>
                <a href="/client/receipt_view.php?invoice_id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-success font-weight-bold shadow-sm">
                    <i class="fas fa-receipt mr-1"></i> Official Payment Receipt
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- INVOICE DOCUMENT -->
<div class="card border-0 shadow <?= $is_print ? '' : 'mb-4' ?>" id="invoiceDoc" style="<?= $is_print ? 'box-shadow:none!important;' : '' ?>">
    <div class="card-body p-5">
        <?php if (!$is_paid && !empty($invoice['late_fee_enabled']) && $balance_due > 0): ?>
            <!-- LATE FEE URGENCY TICKER -->
            <div class="alert alert-danger border-0 mb-4 p-4 shadow-sm" style="border-left: 5px solid #dc3545 !important; background: #fff5f5; color: #721c24;">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="alert-heading font-weight-bold mb-1 text-danger">
                            <i class="fas fa-exclamation-triangle mr-2 text-danger"></i> 
                            Overdue Penalty / Penalty Interest Active
                        </h5>
                        <p class="mb-0 text-dark font-weight-bold" style="font-size: 14px;">
                            An automated late fee penalty of 
                            <?php if (!empty($invoice['late_fee_is_percentage'])): ?>
                                <span class="text-danger font-weight-bold"><?= number_format($invoice['late_fee_amount'], 2) ?>%</span> of total (<?= $symbol ?><?= number_format(($invoice['late_fee_amount'] / 100) * $base_amount, 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?>)
                            <?php else: ?>
                                <span class="text-danger font-weight-bold"><?= $symbol ?><?= number_format($invoice['late_fee_amount'], 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></span>
                            <?php endif; ?>
                            is being charged <span class="badge badge-danger"><?= htmlspecialchars($invoice['late_fee_type']) ?></span>.
                        </p>
                        <?php if ($late_fee > 0): ?>
                            <p class="mb-0 text-muted small mt-1">
                                Current Accumulated Overdue Fees: <strong class="text-danger"><?= $symbol ?><?= number_format($late_fee, 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right mt-2 mt-md-0" style="min-width: 250px;">
                        <span class="small font-weight-bold text-uppercase d-block text-muted">Next penalty increment in:</span>
                        <div id="penaltyCountdown" class="font-weight-bold text-danger mt-1" style="font-size: 1.5rem; letter-spacing: 1px; font-family: monospace;">
                            00h 00m 00s
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                (function() {
                    let secondsLeft = <?= (int)$time_remaining_sec ?>;
                    const display = document.getElementById('penaltyCountdown');
                    
                    function updateTicker() {
                        if (secondsLeft <= 0) {
                            display.innerHTML = "PENALTY PENDING REFRESH";
                            setTimeout(() => { window.location.reload(); }, 3000);
                            return;
                        }
                        
                        let hours = Math.floor(secondsLeft / 3600);
                        let minutes = Math.floor((secondsLeft % 3600) / 60);
                        let secs = Math.floor(secondsLeft % 60);
                        
                        let displayStr = "";
                        if (hours > 0) {
                            displayStr += hours.toString().padStart(2, '0') + "h ";
                        }
                        displayStr += minutes.toString().padStart(2, '0') + "m ";
                        displayStr += secs.toString().padStart(2, '0') + "s";
                        
                        display.innerHTML = displayStr;
                        secondsLeft--;
                    }
                    
                    updateTicker();
                    setInterval(updateTicker, 1000);
                })();
            </script>
        <?php endif; ?>

        <!-- INVOICE HEADER -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <?php if ($logo_url): ?>
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($app_name) ?>" style="max-height:60px; max-width:200px;" onerror="this.style.display='none'">
                <?php endif; ?>
                <h3 class="font-weight-bold text-white mt-2 mb-0"><?= htmlspecialchars($app_name) ?></h3>
                <?php if ($company_address): ?><p class="text-muted small mb-0 mt-1"><?= nl2br(htmlspecialchars($company_address)) ?></p><?php endif; ?>
                <?php if ($company_email): ?><p class="text-muted small mb-0"><i class="fas fa-envelope mr-1 text-warning"></i><?= htmlspecialchars($company_email) ?></p><?php endif; ?>
                <?php if ($company_phone): ?><p class="text-muted small mb-0"><i class="fas fa-phone mr-1 text-warning"></i><?= htmlspecialchars($company_phone) ?></p><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-right">
                <h1 class="font-weight-bold text-white mb-1" style="font-size:2.2rem; letter-spacing:2px; opacity:.2;">INVOICE</h1>
                <div class="badge bg-dark text-warning px-3 py-2 mb-2 font-weight-bold" style="font-size:1.1rem; background:#111827 !important; border:1px solid #fecc56; display:inline-block; border-radius:6px;">
                    #INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?>
                </div>
                <table class="ml-sm-auto" style="font-size:13px; color:#f1f5f9;">
                    <tr><td class="text-muted pr-3">Issue Date:</td><td class="font-weight-bold text-white"><?= date('F j, Y', strtotime($invoice['issue_date'] ?? $invoice['created_at'])) ?></td></tr>
                    <?php if ($invoice['due_date']): ?>
                    <tr>
                        <td class="text-muted pr-3">Due Date:</td>
                        <td class="font-weight-bold <?= (strtotime($invoice['due_date']) < time() && $invoice['status']!=='paid') ? 'text-danger' : 'text-white' ?>">
                            <?= date('F j, Y', strtotime($invoice['due_date'])) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr><td class="text-muted pr-3">Status:</td>
                        <td>
                            <?php $st = strtolower($invoice['status'] ?? 'unpaid');
                            $badge = ['paid'=>'success','unpaid'=>'danger','partial'=>'warning','overdue'=>'danger'][$st] ?? 'secondary'; ?>
                            <span class="badge badge-<?= $badge ?> px-2 py-1"><?= ucfirst($st) ?></span>
                        </td>
                    </tr>
                    <?php if (!empty($invoice['case_number'])): ?>
                    <tr><td class="text-muted pr-3">Case Ref:</td><td class="font-weight-bold text-warning"><?= htmlspecialchars($invoice['case_number']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- BILLED TO -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <p class="text-warning text-uppercase small font-weight-bold mb-2" style="letter-spacing:1px;"><i class="fas fa-user-circle mr-1"></i> Billed To</p>
                <div class="p-3 rounded border" style="background:#1f2533; border-color:#2e3849 !important;">
                    <strong class="d-block text-white" style="font-size:1.05rem;"><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong>
                    <span class="text-light small d-block mt-1"><?= htmlspecialchars($invoice['email']) ?></span>
                    <?php if ($invoice['phone']): ?><span class="text-muted small d-block"><?= htmlspecialchars($invoice['phone']) ?></span><?php endif; ?>
                    <?php if ($invoice['country']): ?><span class="text-muted small d-block"><?= htmlspecialchars($invoice['country']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($invoice['case_title'])): ?>
            <div class="col-sm-6">
                <p class="text-warning text-uppercase small font-weight-bold mb-2" style="letter-spacing:1px;"><i class="fas fa-briefcase mr-1"></i> Related Case</p>
                <div class="p-3 rounded border" style="background:#1f2533; border-color:#2e3849 !important;">
                    <strong class="d-block text-warning font-weight-bold" style="font-size:1.05rem;"><?= htmlspecialchars($invoice['case_number'] ?? '') ?></strong>
                    <span class="text-light small d-block mt-1"><?= htmlspecialchars($invoice['case_title']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <style>
        .invoice-table {
            background: #161a23;
            color: #f1f5f9;
            border-color: #28303f;
        }
        .invoice-table thead th {
            background: #1f2533;
            color: #fecc56 !important;
            border-color: #2e3849;
        }
        .invoice-table tbody td {
            background: #161a23;
            color: #ffffff !important;
            border-color: #28303f;
        }
        .invoice-tfoot-subtotal td,
        .invoice-tfoot-discount td,
        .invoice-tfoot-late td,
        .invoice-tfoot-paid td {
            background: #1f2533;
            color: #ffffff;
            border-color: #2e3849;
        }
        .invoice-tfoot-total td {
            background: #242c3d;
            color: #ffffff !important;
            border-color: #374151;
        }
        .invoice-tfoot-balance {
            background: #111827 !important;
            border: 2px solid #fecc56 !important;
        }

        /* Light Mode Overrides */
        html.light-mode .invoice-table,
        body.light-mode .invoice-table {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-table thead th,
        body.light-mode .invoice-table thead th {
            background: #f1f5f9 !important;
            color: #9a3412 !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-table tbody td,
        body.light-mode .invoice-table tbody td {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        html.light-mode .invoice-tfoot-subtotal td,
        html.light-mode .invoice-tfoot-discount td,
        html.light-mode .invoice-tfoot-late td,
        html.light-mode .invoice-tfoot-paid td {
            background: #f8fafc !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-tfoot-total td {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-tfoot-balance {
            background: #fffbeb !important;
            border: 2px solid #d97706 !important;
        }
        html.light-mode .invoice-tfoot-balance td {
            color: #9a3412 !important;
        }
        </style>

        <!-- LINE ITEMS -->
        <div class="table-responsive mb-4 invoice-table-wrap" style="border-radius:8px; border:1px solid #28303f; overflow:hidden;">
            <table class="table table-bordered mb-0 invoice-table" style="font-size:14px;">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th>Description</th>
                        <th style="width:12%; text-align:right;">Qty</th>
                        <th style="width:16%; text-align:right;">Unit Price</th>
                        <th style="width:16%; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach($items as $i => $item): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($item['description']) ?><?= !empty($item['notes']) ? '<br><small class="text-muted">'.$item['notes'].'</small>' : '' ?></td>
                            <td class="text-right"><?= htmlspecialchars($item['qty'] ?? 1) ?></td>
                            <td class="text-right"><?= $symbol ?><?= number_format($item['rate'] ?? ($base_amount / max(1, count($items))), 2) ?></td>
                            <td class="text-right font-weight-bold"><?= $symbol ?><?= number_format($item['amount'] ?? ($item['qty'] * $item['rate']), 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>1</td>
                            <td><?= htmlspecialchars($invoice['description'] ?? 'Professional Services') ?></td>
                            <td class="text-right">1</td>
                            <td class="text-right"><?= $symbol ?><?= number_format($base_amount, 2) ?></td>
                            <td class="text-right font-weight-bold"><?= $symbol ?><?= number_format($base_amount, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="invoice-tfoot-subtotal">
                        <td colspan="4" class="text-right font-weight-bold">Subtotal</td>
                        <td class="text-right font-weight-bold"><?= $symbol ?><?= number_format($subtotal_amount, 2) ?></td>
                    </tr>
                    <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                    <tr class="invoice-tfoot-discount">
                        <td colspan="4" class="text-right font-weight-bold text-success">Discount</td>
                        <td class="text-right font-weight-bold text-success">-<?= $symbol ?><?= number_format($invoice['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($late_fee > 0): ?>
                    <tr class="invoice-tfoot-late">
                        <td colspan="4" class="text-right font-weight-bold text-danger">Late Fee Penalty Interest</td>
                        <td class="text-right font-weight-bold text-danger">+<?= $symbol ?><?= number_format($late_fee, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="invoice-tfoot-total">
                        <td colspan="4" class="text-right font-weight-bold" style="font-size:15px;">Total Invoiced Amount</td>
                        <td class="text-right font-weight-bold" style="font-size:15px;"><?= $symbol ?><?= number_format($total_billed, 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
                    </tr>
                    <?php if ($total_paid > 0): ?>
                    <tr class="invoice-tfoot-paid">
                        <td colspan="4" class="text-right font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i>Less Verified Payments Received</td>
                        <td class="text-right font-weight-bold text-success">-<?= $symbol ?><?= number_format($total_paid, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="invoice-tfoot-balance">
                        <td colspan="4" class="text-right font-weight-bold" style="font-size:1.1rem; color:#fecc56 !important;">REMAINING BALANCE DUE</td>
                        <td class="text-right font-weight-bold" style="font-size:1.25rem; color:#fecc56 !important;"><?= $symbol ?><?= number_format($balance_due, 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (($invoice['currency'] ?? 'USD') !== $client_currency): ?>
        <div class="card border-0 mb-4 shadow-sm" style="background:#1f2533; border-left:4px solid #fecc56 !important;">
            <div class="card-body py-3 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h6 class="font-weight-bold text-warning mb-1"><i class="fas fa-globe mr-2"></i>Estimated Value in Your Preferred Currency (<?= htmlspecialchars($client_currency) ?>)</h6>
                        <p class="text-muted small mb-0"><?= get_currency_disclaimer($invoice['currency'] ?? 'USD', $client_currency) ?></p>
                    </div>
                    <div class="text-right mt-2 mt-md-0">
                        <span class="text-muted small text-uppercase font-weight-bold">Balance Due in <?= htmlspecialchars($client_currency) ?>:</span>
                        <div class="text-warning font-weight-bold" style="font-size:1.35rem;">
                            <?= format_currency(convert_currency($balance_due, $invoice['currency'] ?? 'USD', $client_currency), $client_currency) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- INSTALMENTS -->
        <?php if (!empty($instalments)): ?>
        <div class="mb-4">
            <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-calendar-alt mr-2"></i>Payment Schedule & Tranches</h6>
            <div class="table-responsive">
                <table class="table table-bordered mb-0" style="font-size:13px; background:#161a23; color:#f1f5f9; border-color:#28303f;">
                    <thead style="background:#1f2533; color:#fecc56;">
                        <tr>
                            <th style="border-color:#2e3849;">Tranche</th>
                            <th style="border-color:#2e3849;">Milestone / Description</th>
                            <th style="border-color:#2e3849;">Due Date</th>
                            <th class="text-right" style="border-color:#2e3849;">Amount</th>
                            <th class="text-center" style="border-color:#2e3849;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($instalments as $k => $inst): ?>
                        <tr>
                            <td class="font-weight-bold text-white" style="border-color:#28303f;">Tranche <?= $k+1 ?></td>
                            <td style="border-color:#28303f; color:#ffffff;"><?= htmlspecialchars($inst['description'] ?? 'Scheduled Payment') ?></td>
                            <td style="border-color:#28303f; color:#cbd5e1;"><?= !empty($inst['due_date']) ? date('M j, Y', strtotime($inst['due_date'])) : '—' ?></td>
                            <td class="text-right font-weight-bold text-white" style="border-color:#28303f;"><?= $symbol ?><?= number_format($inst['amount'], 2) ?></td>
                            <td class="text-center" style="border-color:#28303f;">
                                <?php if ($inst['status'] === 'paid'): ?>
                                    <span class="badge badge-success px-2 py-1">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-warning text-dark px-2 py-1">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYMENT INFORMATION BOX -->
        <div class="row">
            <div class="col-12">
                <div class="p-4 rounded border" style="background:#1f2533; border-color:#2e3849;">
                    <h6 class="font-weight-bold text-warning mb-2"><i class="fas fa-university mr-2"></i>Official Wire & Settlement Instructions</h6>
                    <p class="text-muted small mb-3">Please use the following official details to settle this invoice. Reference your invoice number in all memo fields.</p>
                    <div style="font-family:monospace; font-size:13px; white-space:pre-wrap; line-height:1.8; color:#f1f5f9; background:#111827; padding:16px; border-radius:6px; border:1px solid #28303f;"><?= htmlspecialchars($payment_info) ?></div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top border-secondary text-center text-muted small">
            <p class="mb-0">This invoice is digitally authenticated and valid without physical signature. Thank you for your trust in <?= htmlspecialchars($app_name) ?>.</p>
        </div>
    </div>
</div>

<?php if (!$is_print): ?>
<!-- PAY NOW MODAL -->
<div class="modal fade" id="payNowModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark text-white" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header bg-dark text-warning border-secondary py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-credit-card mr-2"></i>Submit Payment Proof — <span id="payInvoiceRef"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-black text-white p-4 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label text-muted small text-uppercase font-weight-bold">Balance Outstanding</div>
                            <div class="font-weight-bold text-warning" style="font-size:2rem;" id="payAmount"></div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-danger px-3 py-2" style="font-size:12px;">Action Required</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-dark">
                    <h6 class="font-weight-bold mb-3 text-warning"><i class="fas fa-university mr-2"></i>Wire & Payment Instructions</h6>
                    <div class="bg-black border border-secondary rounded p-4 mb-4 text-light" id="payInfoBlock" style="white-space:pre-wrap; font-family: monospace; font-size:13px; line-height:1.8;"></div>
                    
                    <div class="alert alert-warning border-0 mb-4 text-dark font-weight-bold" style="font-size:13px;">
                        <i class="fas fa-info-circle mr-2"></i>
                        After initiating payment, please submit the exact amount paid and upload your transaction receipt / proof for instant verification.
                    </div>

                    <form method="POST" action="/api/submit_payment_proof.php" enctype="multipart/form-data">
                        <input type="hidden" name="invoice_id" id="payInvoiceId">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Amount Paid (<span id="payModalCurr">USD</span>) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount_paid" id="payAmountInput" class="form-control bg-black text-white border-secondary font-weight-bold text-warning" required placeholder="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-light small">Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" id="invoicePaymentMethodSelect" class="form-control bg-black text-white border-secondary" onchange="handleInvoicePaymentMethodChange(this.value)" required>
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
                                <input type="text" name="reference_number" id="invoiceRefNumberInput" class="form-control bg-black text-white border-secondary" placeholder="e.g. TXID / Wire Ref # (Optional)">
                            </div>
                        </div>

                        <!-- DYNAMIC CRYPTO WALLET & QR BOX -->
                        <div id="cryptoPaymentDetailsBoxInvoice" class="p-3 rounded mb-3 border border-warning" style="display:none; background:#0b0e14;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="mr-3 mb-2 text-center" style="min-width:140px;">
                                    <img id="cryptoQrImgInvoice" src="" alt="Crypto QR" class="img-fluid rounded border border-secondary p-1 bg-white" style="width:130px; height:130px;">
                                    <div class="text-muted small mt-1 font-weight-bold" style="font-size:10px;" id="cryptoNetworkLabelInvoice">TRC-20 Network</div>
                                </div>
                                <div class="flex-grow-1 mb-2">
                                    <div class="font-weight-bold text-warning mb-1" id="cryptoNameLabelInvoice">USDT TRC-20 Wallet Address</div>
                                    <p class="text-muted small mb-2">Send only the exact asset on this network. Funds will be credited after 1 network confirmation.</p>
                                    <div class="input-group">
                                        <input type="text" id="cryptoWalletInputInvoice" class="form-control bg-dark text-white border-secondary font-weight-bold" style="font-family:monospace; font-size:12px;" readonly>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-warning text-dark font-weight-bold" onclick="copyCryptoAddressInvoice()"><i class="fas fa-copy mr-1"></i> <span id="copyCryptoBtnTextInvoice">Copy</span></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3 px-0" id="otherPaymentMethodDiv" style="display:none;">
                            <label class="font-weight-bold small text-warning">Specify Other Payment Method <span class="text-danger">*</span></label>
                            <input type="text" name="other_payment_method" class="form-control bg-black text-white border-secondary" placeholder="e.g. PayPal, Cash App, Revolut">
                        </div>

                        <div class="mb-3">
                            <label class="font-weight-bold small text-light">Upload Payment Proof / TX Screenshot <span class="text-danger">*</span></label>
                            <input type="file" name="proof_file" class="form-control-file border border-secondary p-2 rounded w-100 bg-black text-white" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted: JPG, PNG, PDF, DOC (Max 10MB)</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold small text-light">Additional Transaction Notes (Optional)</label>
                            <textarea name="notes" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Any additional transaction notes..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block font-weight-bold py-3 text-dark shadow">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Payment Proof for Verification
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
var cryptoWalletsInvoice = {
    'USDT (TRC-20)': { name: 'USDT (TRC-20) TRON Network', network: 'TRC-20 Network', address: '<?= addslashes(get_setting($pdo, "crypto_usdt_trc20_address", "TYDvsPq9xL3r6K2oH41N8xQzVmM7pB3kRa")) ?>' },
    'USDT (ERC-20)': { name: 'USDT (ERC-20) Ethereum Network', network: 'ERC-20 Network', address: '<?= addslashes(get_setting($pdo, "crypto_usdt_erc20_address", "0x71C8360f38bB2902f4D3e1b78297bB32789cA854")) ?>' },
    'Bitcoin (BTC)': { name: 'Bitcoin (BTC) Native Mainnet', network: 'BTC Mainnet', address: '<?= addslashes(get_setting($pdo, "crypto_btc_address", "bc1q9xle8v2kwj6d234p8cmnrqtvq80f3w9a2lx7kd")) ?>' },
    'Ethereum (ETH)': { name: 'Ethereum (ETH) Mainnet', network: 'ETH Mainnet', address: '<?= addslashes(get_setting($pdo, "crypto_eth_address", "0x71C8360f38bB2902f4D3e1b78297bB32789cA854")) ?>' }
};

function handleInvoicePaymentMethodChange(val) {
    var cryptoBox = document.getElementById('cryptoPaymentDetailsBoxInvoice');
    var otherDiv = document.getElementById('otherPaymentMethodDiv');
    var refInput = document.getElementById('invoiceRefNumberInput');
    
    if (val === 'Other') {
        otherDiv.style.display = 'block';
        otherDiv.querySelector('input').setAttribute('required', 'required');
    } else {
        otherDiv.style.display = 'none';
        otherDiv.querySelector('input').removeAttribute('required');
        otherDiv.querySelector('input').value = '';
    }
    
    if (cryptoWalletsInvoice[val]) {
        var w = cryptoWalletsInvoice[val];
        document.getElementById('cryptoNameLabelInvoice').textContent = w.name;
        document.getElementById('cryptoNetworkLabelInvoice').textContent = w.network;
        document.getElementById('cryptoWalletInputInvoice').value = w.address;
        document.getElementById('cryptoQrImgInvoice').src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(w.address);
        cryptoBox.style.display = 'block';
        refInput.placeholder = 'e.g. 64-character Blockchain TXID (Optional)';
    } else {
        cryptoBox.style.display = 'none';
        refInput.placeholder = 'e.g. Wire Ref # / Confirmation Code (Optional)';
    }
}

function copyCryptoAddressInvoice() {
    var copyText = document.getElementById('cryptoWalletInputInvoice');
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    document.getElementById('copyCryptoBtnTextInvoice').textContent = 'Copied!';
    setTimeout(function() {
        document.getElementById('copyCryptoBtnTextInvoice').textContent = 'Copy';
    }, 2500);
}

function showPayModal(invoiceId, ref, amount, currency, payInfo, prefCurrency, prefBalance) {
    currency = currency || 'USD';
    prefCurrency = prefCurrency || currency;
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payInvoiceRef').textContent = ref;
    document.getElementById('payModalCurr').textContent = currency;
    
    var disp = currency + ' ' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits:2});
    if (prefCurrency && prefCurrency !== currency && prefBalance) {
        disp += ' <span style="font-size:1.05rem; color:#fecc56; font-weight:normal; display:block; margin-top:2px;">(≈ ' + prefCurrency + ' ' + parseFloat(prefBalance).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' preferred eqv)</span>';
    }
    document.getElementById('payAmount').innerHTML = disp;
    document.getElementById('payAmountInput').value = parseFloat(amount).toFixed(2);
    document.getElementById('payInfoBlock').textContent = payInfo || 'Contact your investigator for payment details.';
    
    // Reset crypto box
    document.getElementById('invoicePaymentMethodSelect').value = '';
    document.getElementById('cryptoPaymentDetailsBoxInvoice').style.display = 'none';
    
    $('#payNowModal').modal('show');
}
</script>
<?php endif; ?>

<style>
#invoiceDoc {
    background: #161a23 !important;
    border: 1px solid #28303f !important;
    border-radius: 12px;
    color: #f1f5f9 !important;
}
#invoiceDoc table, #invoiceDoc td, #invoiceDoc th, #invoiceDoc p, #invoiceDoc span:not(.badge):not(.badge-warning):not(.text-warning):not(.text-danger):not(.text-success), #invoiceDoc strong:not(.text-warning):not(.text-danger):not(.text-success) {
    color: #ffffff !important;
}
#invoiceDoc .text-muted {
    color: #94a3b8 !important;
}
@media (max-width: 768px) {
    #invoiceDoc .card-body {
        padding: 16px !important;
    }
}
@media print {
    .d-print-none, nav, #sidebar, .btn, .modal { display:none!important; }
    .card { border:none!important; box-shadow:none!important; }
    body { background:#fff!important; }
    #invoiceDoc, #invoiceDoc table, #invoiceDoc td, #invoiceDoc th, #invoiceDoc p, #invoiceDoc span, #invoiceDoc strong {
        color: #000 !important;
        background: #fff !important;
    }
}
</style>

<?php require_once $dir . '/includes/admin_footer.php'; ?>

<?php
// public/client/invoice_view.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['client_logged_in'])) { header("Location: /client/login.php"); exit; }

$client_id = $_SESSION['client_portal_id'];
$_SESSION['role'] = 'client';
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

if ($invoice['status'] !== 'paid' && !empty($invoice['late_fee_enabled']) && $invoice['late_fee_amount'] > 0) {
    $startDate = strtotime($invoice['late_fee_start_date'] ?? $invoice['due_date']);
    $now = time();
    $rate = $invoice['late_fee_amount'];
    if (!empty($invoice['late_fee_is_percentage'])) {
        $rate = ($invoice['late_fee_amount'] / 100) * $base_amount;
    }
    
    if ($now >= $startDate) {
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
        $time_remaining_sec = $startDate - $now;
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

$late_fee = ($invoice['status'] === 'paid') ? ($invoice['late_fee_accumulated'] ?? 0) : max($dynamic_late_fee, $invoice['late_fee_accumulated'] ?? 0);
$total_due = $base_amount + $late_fee;

// Global payment info fallback
$global_payment = get_setting($pdo, 'bank_details', '');
$global_instructions = get_setting($pdo, 'payment_instructions', '');
$payment_info = !empty($invoice['payment_info']) ? $invoice['payment_info'] : ($global_payment ?: $global_instructions);
$app_name = get_setting($pdo, 'app_name', 'IFW Global');
$company_address = get_setting($pdo, 'company_address', '');
$company_email   = get_setting($pdo, 'contact_email', '');
$company_phone   = get_setting($pdo, 'contact_phone', '');
$logo_url        = get_setting($pdo, 'logo_url', '/admin_assets/img/logo/logo.svg');

$is_print = isset($_GET['print']);

require_once $dir . '/includes/admin_header.php';
if (!$is_print) require_once $dir . '/includes/admin_sidebar.php';
?>

<?php if (!$is_print): ?>
<div class="row mb-3 d-print-none">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="font-weight-bold mb-0"><i class="fas fa-file-invoice-dollar text-warning mr-2"></i>Invoice #INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?></h4>
        </div>
        <div>
            <a href="/client/dashboard.php" class="btn btn-sm btn-outline-dark font-weight-bold mr-2"><i class="fas fa-arrow-left mr-1"></i> Back</a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary font-weight-bold mr-2"><i class="fas fa-print mr-1"></i> Print</button>
            <?php if ($invoice['status'] !== 'paid'): ?>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark"
                    onclick="showPayModal(<?= $invoice['id'] ?>, '#INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?>', <?= $total_due ?>, <?= htmlspecialchars(json_encode($payment_info)) ?>)"
                    data-toggle="modal" data-target="#payNowModal">
                    <i class="fas fa-credit-card mr-1"></i> Pay Now
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- INVOICE DOCUMENT -->
<div class="card border-0 shadow <?= $is_print ? '' : 'mb-4' ?>" id="invoiceDoc" style="<?= $is_print ? 'box-shadow:none!important;' : '' ?>">
    <div class="card-body p-5">
        <?php if ($invoice['status'] !== 'paid' && !empty($invoice['late_fee_enabled'])): ?>
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
                                <span class="text-danger font-weight-bold"><?= number_format($invoice['late_fee_amount'], 2) ?>%</span> of total ($<?= number_format(($invoice['late_fee_amount'] / 100) * $invoice['amount'], 2) ?> <?= htmlspecialchars($invoice['currency']) ?>)
                            <?php else: ?>
                                <span class="text-danger font-weight-bold">$<?= number_format($invoice['late_fee_amount'], 2) ?> <?= htmlspecialchars($invoice['currency']) ?></span>
                            <?php endif; ?>
                            is being charged <span class="badge badge-danger"><?= htmlspecialchars($invoice['late_fee_type']) ?></span>.
                        </p>
                        <?php if ($late_fee > 0): ?>
                            <p class="mb-0 text-muted small mt-1">
                                Current Accumulated Overdue Fees: <strong class="text-danger">$<?= number_format($late_fee, 2) ?> <?= htmlspecialchars($invoice['currency']) ?></strong>
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
        <div class="row mb-5">
            <div class="col-6">
                <?php if ($logo_url): ?>
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($app_name) ?>" style="max-height:60px; max-width:200px;" onerror="this.style.display='none'">
                <?php endif; ?>
                <h3 class="font-weight-bold text-dark mt-2 mb-0"><?= htmlspecialchars($app_name) ?></h3>
                <?php if ($company_address): ?><p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($company_address)) ?></p><?php endif; ?>
                <?php if ($company_email): ?><p class="text-muted small mb-0"><?= htmlspecialchars($company_email) ?></p><?php endif; ?>
                <?php if ($company_phone): ?><p class="text-muted small mb-0"><?= htmlspecialchars($company_phone) ?></p><?php endif; ?>
            </div>
            <div class="col-6 text-right">
                <h1 class="font-weight-bold text-dark mb-1" style="font-size:2.5rem; letter-spacing:2px; opacity:.15;">INVOICE</h1>
                <div class="badge bg-dark text-warning px-3 py-2 mb-3" style="font-size:1.1rem; background:#1a1a1a !important; display:inline-block; border-radius:6px;">
                    #INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?>
                </div>
                <table class="ml-auto" style="font-size:13px;">
                    <tr><td class="text-muted pr-3">Issue Date:</td><td class="font-weight-bold"><?= date('F j, Y', strtotime($invoice['issue_date'] ?? $invoice['created_at'])) ?></td></tr>
                    <?php if ($invoice['due_date']): ?>
                    <tr>
                        <td class="text-muted pr-3">Due Date:</td>
                        <td class="font-weight-bold <?= (strtotime($invoice['due_date']) < time() && $invoice['status']!=='paid') ? 'text-danger' : '' ?>">
                            <?= date('F j, Y', strtotime($invoice['due_date'])) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr><td class="text-muted pr-3">Status:</td>
                        <td>
                            <?php $st = strtolower($invoice['status'] ?? 'unpaid');
                            $badge = ['paid'=>'success','unpaid'=>'danger','partial'=>'warning','overdue'=>'danger'][$st] ?? 'secondary'; ?>
                            <span class="badge badge-<?= $badge ?>"><?= ucfirst($st) ?></span>
                        </td>
                    </tr>
                    <?php if (!empty($invoice['case_number'])): ?>
                    <tr><td class="text-muted pr-3">Case Ref:</td><td class="font-weight-bold"><?= htmlspecialchars($invoice['case_number']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- BILLED TO -->
        <div class="row mb-5">
            <div class="col-6">
                <p class="text-muted text-uppercase small font-weight-bold mb-1" style="letter-spacing:1px;">Billed To</p>
                <div class="bg-light p-3 rounded border">
                    <strong class="d-block text-dark"><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong>
                    <span class="text-muted small"><?= htmlspecialchars($invoice['email']) ?></span>
                    <?php if ($invoice['phone']): ?><br><span class="text-muted small"><?= htmlspecialchars($invoice['phone']) ?></span><?php endif; ?>
                    <?php if ($invoice['country']): ?><br><span class="text-muted small"><?= htmlspecialchars($invoice['country']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($invoice['case_title'])): ?>
            <div class="col-6">
                <p class="text-muted text-uppercase small font-weight-bold mb-1" style="letter-spacing:1px;">Related Case</p>
                <div class="bg-light p-3 rounded border">
                    <strong class="d-block text-dark"><?= htmlspecialchars($invoice['case_number'] ?? '') ?></strong>
                    <span class="text-muted small"><?= htmlspecialchars($invoice['case_title']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- LINE ITEMS -->
        <table class="table table-bordered mb-4" style="font-size:14px;">
            <thead style="background:#1a1a1a; color:#fecc56;">
                <tr>
                    <th style="width:5%">#</th>
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
                <tr class="bg-light">
                    <td colspan="4" class="text-right font-weight-bold">Subtotal</td>
                    <td class="text-right font-weight-bold"><?= $symbol ?><?= number_format($subtotal_amount, 2) ?></td>
                </tr>
                <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                <tr class="bg-light">
                    <td colspan="4" class="text-right text-success">Discount</td>
                    <td class="text-right text-success">-<?= $symbol ?><?= number_format($invoice['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($late_fee > 0): ?>
                <tr class="bg-light">
                    <td colspan="4" class="text-right text-danger font-weight-bold">Late Fee</td>
                    <td class="text-right text-danger font-weight-bold">+<?= $symbol ?><?= number_format($late_fee, 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="background:#1a1a1a; color:#fecc56;">
                    <td colspan="4" class="text-right font-weight-bold" style="font-size:1.1rem;">TOTAL DUE</td>
                    <td class="text-right font-weight-bold" style="font-size:1.2rem;"><?= $symbol ?><?= number_format($total_due, 2) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- INSTALMENTS -->
        <?php if (!empty($instalments)): ?>
        <div class="mb-5">
            <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-calendar-alt mr-2 text-warning"></i>Payment Schedule (Instalments)</h6>
            <table class="table table-sm table-bordered" style="font-size:13px;">
                <thead class="bg-light"><tr><th>Instalment</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach($instalments as $inst): ?>
                    <tr>
                        <td>Instalment #<?= $inst['instalment_number'] ?></td>
                        <td><?= $symbol ?><?= number_format($inst['amount'], 2) ?></td>
                        <td><?= date('M j, Y', strtotime($inst['due_date'])) ?></td>
                        <td>
                            <?php $is = strtolower($inst['status']);
                            $ib = ['paid'=>'success','overdue'=>'danger','pending'=>'warning text-dark'][$is] ?? 'secondary'; ?>
                            <span class="badge badge-<?= $ib ?>"><?= ucfirst($is) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- PAYMENT INFORMATION -->
        <?php if (!empty($payment_info)): ?>
        <div class="p-4 rounded mb-4" style="background: #f8f9fa; border: 1px solid #dee2e6;">
            <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-university mr-2 text-warning"></i>Payment Information</h6>
            <p class="text-muted small mb-3">Please use the following details to make your payment. Reference your invoice number in all transactions.</p>
            <div style="white-space:pre-wrap; font-family: 'Courier New', monospace; font-size:13px; line-height:1.8; background:#fff; border:1px solid #ddd; padding:16px; border-radius:6px; color:#000 !important;">
                <?= htmlspecialchars($payment_info) ?>
            </div>
            <div class="alert alert-warning border-0 mt-3 mb-0 small">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>Important:</strong> Always reference invoice #INV-<?= str_pad($invoice['id'],5,'0',STR_PAD_LEFT) ?> in your payment description. After paying, submit your proof of payment through your dashboard.
            </div>
        </div>
        <?php endif; ?>

        <!-- FOOTER NOTE -->
        <div class="text-center text-muted mt-4" style="font-size:12px; border-top:1px solid #eee; padding-top:16px;">
            <p class="mb-1"><?= htmlspecialchars($app_name) ?> · <?= htmlspecialchars($company_email) ?></p>
            <p class="mb-0">This invoice is computer-generated and valid without a physical signature. Thank you for your trust in <?= htmlspecialchars($app_name) ?>.</p>
        </div>
    </div>
</div>

<?php if (!$is_print): ?>
<!-- PAY NOW MODAL -->
<div class="modal fade" id="payNowModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-warning border-0 py-3">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-credit-card mr-2"></i>Submit Payment — <span id="payInvoiceRef"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-dark text-white p-4 border-bottom border-secondary">
                    <div class="text-warning font-weight-bold" style="font-size:1.8rem;" id="payAmount"></div>
                    <div class="text-muted small">Total amount due</div>
                </div>
                <div class="p-4">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-university text-warning mr-2"></i>Payment Details</h6>
                    <div class="bg-light p-3 rounded border mb-4 text-dark" id="payInfoBlock" style="white-space:pre-wrap; font-family:monospace; font-size:13px; line-height:1.8; color:#000 !important;"></div>
                    <div class="alert alert-warning border-0 mb-4 small">
                        <i class="fas fa-exclamation-triangle mr-2"></i>After paying, upload your payment receipt below for verification.
                    </div>
                    <form method="POST" action="/api/submit_payment_proof.php" enctype="multipart/form-data">
                        <input type="hidden" name="invoice_id" id="payInvoiceId">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small">Amount Paid ($)</label>
                                <input type="number" step="0.01" name="amount_paid" id="payAmountInput" class="form-control" required placeholder="Amount paid">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small">Payment Method</label>
                                <select name="payment_method" class="form-control" onchange="if(this.value==='Other'){$('#otherPaymentMethodDiv').show().find('input').attr('required',true);}else{$('#otherPaymentMethodDiv').hide().find('input').removeAttr('required').val('');}" required>
                                    <option value="">Select...</option>
                                    <option>Bank Wire Transfer</option>
                                    <option>Cryptocurrency (Bitcoin)</option>
                                    <option>Cryptocurrency (USDT)</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small">Transaction / Reference No. (Optional)</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="e.g. TXN12345678">
                            </div>
                            <div class="col-md-12 mb-3" id="otherPaymentMethodDiv" style="display:none;">
                                <label class="font-weight-bold small text-warning">Specify Other Payment Method <span class="text-danger">*</span></label>
                                <input type="text" name="other_payment_method" class="form-control" placeholder="e.g. PayPal, Cash App, Revolut">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold small">Upload Payment Proof / Receipt</label>
                            <input type="file" name="proof_file" class="form-control-file border p-2 rounded w-100" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold small">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any additional information..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block font-weight-bold py-3 text-dark shadow">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Payment Proof
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function showPayModal(invoiceId, ref, amount, payInfo) {
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payInvoiceRef').textContent = ref;
    document.getElementById('payAmount').textContent = '$' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits:2});
    document.getElementById('payAmountInput').value = parseFloat(amount).toFixed(2);
    document.getElementById('payInfoBlock').textContent = payInfo || 'Contact your investigator for payment details.';
    $('#payNowModal').modal('show');
}
</script>
<?php endif; ?>

<style>
@media print {
    .d-print-none, nav, #sidebar, .btn, .modal { display:none!important; }
    .card { border:none!important; box-shadow:none!important; }
    body { background:#fff!important; }
}
</style>

<?php require_once $dir . '/includes/admin_footer.php'; ?>

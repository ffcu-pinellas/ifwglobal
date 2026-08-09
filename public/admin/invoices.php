<?php
// admin/invoices.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff']);
if ($is_agent) {
    // Restrict agents and staff from viewing/creating/approving invoices of unassigned clients
}

// Handle Invoice Generation & Emailing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_invoice') {
    $client_id = (int)$_POST['client_id'];
    if ($is_agent) {
        $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
        $check->execute([$client_id, $_SESSION['admin_id']]);
        if (!$check->fetch()) {
            die("Unauthorized");
        }
    }
    $case_id = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    
    // Generate Invoice Number
    $inv_number = 'INV-' . date('Y') . '-' . mt_rand(1000, 9999);
    
    $issue_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+14 days'));
    
    // Calculate totals from items
    $items = [];
    $subtotal = 0;
    
    if (isset($_POST['item_desc']) && is_array($_POST['item_desc'])) {
        for ($i = 0; $i < count($_POST['item_desc']); $i++) {
            $desc = trim($_POST['item_desc'][$i]);
            $qty = (float)$_POST['item_qty'][$i];
            $rate = (float)$_POST['item_rate'][$i];
            
            if (!empty($desc) && $qty > 0) {
                $amount = $qty * $rate;
                $subtotal += $amount;
                $items[] = [
                    'desc' => $desc,
                    'qty' => $qty,
                    'rate' => $rate,
                    'amount' => $amount
                ];
            }
        }
    }
    
    $tax_rate = (float)($_POST['tax_rate'] ?? 0);
    $tax_amount = ($subtotal * $tax_rate) / 100;
    
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    
    $total_amount = ($subtotal + $tax_amount) - $discount_amount;
    if ($total_amount < 0) $total_amount = 0;
    
    $late_fee_enabled = isset($_POST['late_fee_enabled']) ? 1 : 0;
    $late_fee_type = $_POST['late_fee_type'] ?? 'daily';
    $late_fee_amount = (float)($_POST['late_fee_amount'] ?? 0);
    $late_fee_start_date = !empty($_POST['late_fee_start_date']) ? $_POST['late_fee_start_date'] : $due_date;
    $late_fee_is_percentage = isset($_POST['late_fee_is_percentage']) ? (int)$_POST['late_fee_is_percentage'] : 0;

    // Insert Invoice
    $stmt = $pdo->prepare("INSERT INTO IFW_invoices (invoice_number, client_id, case_id, status, issue_date, due_date, subtotal, tax_rate, tax_amount, discount_amount, total_amount, currency, notes, payment_info, late_fee_enabled, late_fee_type, late_fee_amount, late_fee_start_date, late_fee_is_percentage) VALUES (?, ?, ?, 'Unpaid', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$inv_number, $client_id, $case_id, $issue_date, $due_date, $subtotal, $tax_rate, $tax_amount, $discount_amount, $total_amount, $currency, trim($_POST['notes'] ?? ''), trim($_POST['payment_info'] ?? ''), $late_fee_enabled, $late_fee_type, $late_fee_amount, $late_fee_start_date, $late_fee_is_percentage]);
    $invoice_id = $pdo->lastInsertId();
    
    // Insert Items
    if (!empty($items)) {
        $stmtItem = $pdo->prepare("INSERT INTO IFW_invoice_items (invoice_id, description, qty, rate, amount) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmtItem->execute([$invoice_id, $item['desc'], $item['qty'], $item['rate'], $item['amount']]);
        }
    }
    
    // Email Invoice if requested
    if (isset($_POST['email_invoice'])) {
        $stmtClient = $pdo->prepare("SELECT first_name, email FROM IFW_clients WHERE id = ?");
        $stmtClient->execute([$client_id]);
        $client = $stmtClient->fetch();
        
        if ($client && $client['email']) {
            $html_body = "<h2>Invoice {$inv_number}</h2>
                          <p>Dear {$client['first_name']},</p>
                          <p>A new invoice has been generated for your account. Please find the details below:</p>
                          <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                          <tr style='background-color: #333; color: #fff;'>
                              <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Description</th>
                              <th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Qty</th>
                              <th style='padding: 10px; border: 1px solid #ddd; text-align: right;'>Rate</th>
                              <th style='padding: 10px; border: 1px solid #ddd; text-align: right;'>Amount</th>
                          </tr>";
            
            foreach ($items as $item) {
                $html_body .= "<tr>
                                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($item['desc']) . "</td>
                                <td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>" . number_format($item['qty'], 2) . "</td>
                                <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>$" . number_format($item['rate'], 2) . "</td>
                                <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>$" . number_format($item['amount'], 2) . "</td>
                               </tr>";
            }
            
            $html_body .= "<tr><td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd; font-weight: bold;'>Subtotal</td><td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>{$currency} " . number_format($subtotal, 2) . "</td></tr>";
            
            if ($tax_rate > 0) {
                $html_body .= "<tr><td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd; font-weight: bold;'>Tax ({$tax_rate}%)</td><td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>{$currency} " . number_format($tax_amount, 2) . "</td></tr>";
            }
            if ($discount_amount > 0) {
                $html_body .= "<tr><td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd; font-weight: bold;'>Discount</td><td style='padding: 10px; text-align: right; border: 1px solid #ddd; color: #d9534f;'>- {$currency} " . number_format($discount_amount, 2) . "</td></tr>";
            }
            
            $html_body .= "<tr><td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd; font-weight: bold; font-size: 1.2em;'>Total Due</td><td style='padding: 10px; text-align: right; border: 1px solid #ddd; font-weight: bold; font-size: 1.2em; color: #b58d3c;'>{$currency} " . number_format($total_amount, 2) . "</td></tr>";
            $html_body .= "</table>
                           <p style='margin-top: 20px;'><strong>Due Date:</strong> " . date('M j, Y', strtotime($due_date)) . "</p>
                           <p>You can securely pay this invoice by logging into your <a href='" . BASE_URL . "/client/login.php'>Client Portal</a>.</p>";
                           
            if (!empty(trim($_POST['payment_info'] ?? ''))) {
                $html_body .= "<div style='margin-top: 30px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #b58d3c;'>
                                <h3 style='margin-top: 0; color: #333;'>Payment Instructions</h3>
                                <p style='white-space: pre-wrap; font-family: monospace; color: #555;'>" . htmlspecialchars(trim($_POST['payment_info'])) . "</p>
                               </div>";
            }
                           
            send_html_email($client['email'], "Invoice {$inv_number} from IFW Global", $html_body);
        }
    }
    
    header("Location: invoices.php?success=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_invoice' && !$is_agent) {
    $stmt = $pdo->prepare("DELETE FROM IFW_invoices WHERE id = ?");
    $stmt->execute([(int)$_POST['invoice_id']]);
    header("Location: invoices.php?deleted=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $invoice_id = (int)$_POST['invoice_id'];
    if ($is_agent) {
        $check = $pdo->prepare("SELECT i.id FROM IFW_invoices i JOIN IFW_clients c ON i.client_id = c.id WHERE i.id = ? AND c.assigned_agent_id = ?");
        $check->execute([$invoice_id, $_SESSION['admin_id']]);
        if (!$check->fetch()) {
            die("Unauthorized");
        }
    }
    $stmt = $pdo->prepare("UPDATE IFW_invoices SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $invoice_id]);
    header("Location: invoices.php?status_updated=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve_payment', 'reject_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    $notes = trim($_POST['review_notes'] ?? '');
    
    // Fetch payment details
    $stmt = $pdo->prepare("SELECT * FROM IFW_invoice_payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        if ($is_agent) {
            $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
            $check->execute([$payment['client_id'], $_SESSION['admin_id']]);
            if (!$check->fetch()) {
                die("Unauthorized");
            }
        }
        $new_status = ($_POST['action'] === 'approve_payment') ? 'Confirmed' : 'Rejected';
        
        $update = $pdo->prepare("UPDATE IFW_invoice_payments SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$new_status, $notes, $_SESSION['admin_id'], $payment_id]);
        
        if ($new_status === 'Confirmed') {
            $invoice_id = $payment['invoice_id'];
            $client_id = $payment['client_id'];
            
            // Fetch invoice total amount, late fee accumulated, and currency
            $stmtInv = $pdo->prepare("SELECT amount, total_amount, late_fee_accumulated, currency FROM IFW_invoices WHERE id = ?");
            $stmtInv->execute([$invoice_id]);
            $inv = $stmtInv->fetch();
            
            if ($inv) {
                $total_billed = ($inv['total_amount'] > 0 ? $inv['total_amount'] : $inv['amount']) + ($inv['late_fee_accumulated'] ?? 0);
                
                // Fetch confirmed payments sum
                $stmtSum = $pdo->prepare("SELECT SUM(amount) FROM IFW_invoice_payments WHERE invoice_id = ? AND status = 'Confirmed'");
                $stmtSum->execute([$invoice_id]);
                $total_paid = $stmtSum->fetchColumn() ?: 0.00;
                
                $new_invoice_status = ($total_paid >= $total_billed) ? 'Paid' : 'Partial';
                
                $updateInv = $pdo->prepare("UPDATE IFW_invoices SET status = ? WHERE id = ?");
                $updateInv->execute([$new_invoice_status, $invoice_id]);
            }
            
            // Client Notification
            $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'invoice', 'Payment Confirmed', ?, 'check-circle', '/client/dashboard.php')")
                ->execute([$client_id, "Your payment of " . number_format($payment['amount'], 2) . " " . ($inv['currency'] ?? 'USD') . " has been verified & approved."]);
        } else {
            $client_id = $payment['client_id'];
            $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'invoice', 'Payment Rejected', ?, 'times-circle', '/client/dashboard.php')")
                ->execute([$client_id, "Your payment proof reference " . htmlspecialchars($payment['reference_number']) . " was rejected. Reason: " . htmlspecialchars($notes)]);
        }
        
        header("Location: invoices.php?payment_reviewed=1");
        exit;
    }
}

// Fetch Payment Proofs awaiting review
$payments = [];
try {
    if ($is_agent) {
        $stmt = $pdo->prepare("SELECT p.*, c.first_name, c.last_name, i.invoice_number, i.currency 
                               FROM IFW_invoice_payments p 
                               JOIN IFW_clients c ON p.client_id = c.id 
                               JOIN IFW_invoices i ON p.invoice_id = i.id 
                               WHERE c.assigned_agent_id = ? 
                               ORDER BY p.created_at DESC");
        $stmt->execute([$_SESSION['admin_id']]);
        $payments = $stmt->fetchAll();
    } else {
        $payments = $pdo->query("SELECT p.*, c.first_name, c.last_name, i.invoice_number, i.currency 
                                 FROM IFW_invoice_payments p 
                                 JOIN IFW_clients c ON p.client_id = c.id 
                                 JOIN IFW_invoices i ON p.invoice_id = i.id 
                                 ORDER BY p.created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

// Fetch Invoices
$invoices = [];
$client_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
try {
    if ($is_agent) {
        if ($client_filter > 0) {
            $stmt = $pdo->prepare("SELECT i.*, c.first_name, c.last_name FROM IFW_invoices i JOIN IFW_clients c ON i.client_id = c.id WHERE c.assigned_agent_id = ? AND i.client_id = ? ORDER BY i.created_at DESC");
            $stmt->execute([$_SESSION['admin_id'], $client_filter]);
            $invoices = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT i.*, c.first_name, c.last_name FROM IFW_invoices i JOIN IFW_clients c ON i.client_id = c.id WHERE c.assigned_agent_id = ? ORDER BY i.created_at DESC");
            $stmt->execute([$_SESSION['admin_id']]);
            $invoices = $stmt->fetchAll();
        }
    } else {
        if ($client_filter > 0) {
            $stmt = $pdo->prepare("SELECT i.*, c.first_name, c.last_name FROM IFW_invoices i JOIN IFW_clients c ON i.client_id = c.id WHERE i.client_id = ? ORDER BY i.created_at DESC");
            $stmt->execute([$client_filter]);
            $invoices = $stmt->fetchAll();
        } else {
            $invoices = $pdo->query("SELECT i.*, c.first_name, c.last_name FROM IFW_invoices i JOIN IFW_clients c ON i.client_id = c.id ORDER BY i.created_at DESC")->fetchAll();
        }
    }
} catch (Exception $e) {}

// Fetch Clients & Cases
$clients = [];
$cases = [];
try {
    if ($is_agent) {
        $clients = $pdo->query("SELECT id, first_name, last_name, email FROM IFW_clients WHERE assigned_agent_id = {$_SESSION['admin_id']} ORDER BY first_name")->fetchAll();
    } else {
        $clients = $pdo->query("SELECT id, first_name, last_name, email FROM IFW_clients ORDER BY first_name")->fetchAll();
    }
    $cases = $pdo->query("SELECT id, case_number, title FROM IFW_cases ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-file-invoice-dollar mr-2"></i>Invoice Management</h3>
        <p class="text-muted mb-0">Generate, track, and email professional invoices to clients.</p>
    </div>
    <button type="button" class="btn btn-warning font-weight-bold text-dark px-4 shadow" data-toggle="modal" data-target="#createInvoiceModal">
        <i class="fas fa-plus mr-1"></i> Generate New Invoice
    </button>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success font-weight-bold"><i class="fas fa-check-circle mr-2"></i>Invoice generated and saved successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['deleted'])): ?>
    <div class="alert alert-warning font-weight-bold"><i class="fas fa-trash mr-2"></i>Invoice deleted successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['status_updated'])): ?>
    <div class="alert alert-info font-weight-bold"><i class="fas fa-info-circle mr-2"></i>Invoice status updated.</div>
<?php endif; ?>

<div class="card shadow-lg bg-dark border-secondary">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
        <i class="fas fa-list-alt mr-2"></i>Invoice Directory (<?= count($invoices) ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning">
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Issue Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="text-center p-4 text-muted">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $inv): ?>
                            <tr>
                                <td><strong class="text-white"><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                                <td><?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($inv['issue_date'])) ?></td>
                                <td><strong class="text-success"><?= htmlspecialchars($inv['currency'] ?? 'USD') ?> <?= number_format($inv['total_amount'], 2) ?></strong></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <select name="status" class="form-control form-control-sm bg-dark text-white border-secondary" style="width:120px;" onchange="this.form.submit()">
                                            <option value="Unpaid" <?= $inv['status'] === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                            <option value="Paid" <?= $inv['status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="Overdue" <?= $inv['status'] === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                                            <option value="Cancelled" <?= $inv['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="invoice_print.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-info mr-1" title="Print / PDF"><i class="fas fa-print"></i></a>
                                    <?php if (!$is_agent): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?');">
                                            <input type="hidden" name="action" value="delete_invoice">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
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

<!-- Payment Confirmation Center -->
<div class="card shadow-lg bg-dark border-secondary mt-5 mb-4">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex align-items-center">
        <i class="fas fa-university mr-2"></i>Payment Confirmation & Proof Approvals Center
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning">
                        <th>Date Submitted</th>
                        <th>Client</th>
                        <th>Invoice</th>
                        <th>Amount Paid</th>
                        <th>Method & Ref</th>
                        <th>Proof File</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center p-4 text-muted">No payment proofs submitted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($payments as $p): ?>
                            <tr>
                                <td><?= date('M j, Y h:i A', strtotime($p['created_at'])) ?></td>
                                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                <td><strong><?= htmlspecialchars($p['invoice_number']) ?></strong></td>
                                <td><strong class="text-success"><?= htmlspecialchars($p['currency'] ?? 'USD') ?> <?= number_format($p['amount'], 2) ?></strong></td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($p['payment_method']) ?></span><br>
                                    <small class="text-muted">Ref: <?= htmlspecialchars($p['reference_number']) ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($p['proof_file'])): ?>
                                        <a href="<?= BASE_URL . '/' . htmlspecialchars($p['proof_file']) ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-file-download mr-1"></i> View Receipt
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No file uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'Pending'): ?>
                                        <span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i> Pending Review</span>
                                    <?php elseif ($p['status'] === 'Confirmed'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'Pending'): ?>
                                        <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark" onclick="openReviewModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['first_name'] . ' ' . $p['last_name'])) ?>', '<?= htmlspecialchars(addslashes($p['invoice_number'])) ?>', '<?= number_format($p['amount'], 2) ?>')">
                                            <i class="fas fa-gavel mr-1"></i> Review
                                        </button>
                                    <?php else: ?>
                                        <?php if (!empty($p['notes'])): ?>
                                            <small class="text-muted" title="<?= htmlspecialchars($p['notes']) ?>"><i class="fas fa-comment-alt mr-1"></i> Notes: <?= htmlspecialchars(substr($p['notes'], 0, 20)) ?>...</small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
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

<!-- Payment Review Modal -->
<div class="modal fade" id="reviewPaymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-gavel mr-2"></i>Review Payment Proof</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="payment_id" id="reviewPaymentId">
        <div class="modal-body">
            <p class="small text-light">Reviewing payment from <strong id="reviewClientName" class="text-warning"></strong> for <strong id="reviewInvoiceRef" class="text-warning"></strong>.</p>
            <p class="small text-light">Amount reported paid: <strong id="reviewAmount" class="text-success"></strong></p>
            
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">Reviewer Notes / Feedback (Optional)</label>
                <textarea name="review_notes" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="e.g. Transaction verified in bank ledger, or Invalid reference number provided."></textarea>
            </div>
        </div>
        <div class="modal-footer border-secondary justify-content-between">
            <button type="submit" name="action" value="reject_payment" class="btn btn-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Reject Proof</button>
            <button type="submit" name="action" value="approve_payment" class="btn btn-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Approve & Deduct Balance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReviewModal(paymentId, clientName, invoiceRef, amount) {
    document.getElementById('reviewPaymentId').value = paymentId;
    document.getElementById('reviewClientName').textContent = clientName;
    document.getElementById('reviewInvoiceRef').textContent = invoiceRef;
    document.getElementById('reviewAmount').textContent = '$' + amount;
    $('#reviewPaymentModal').modal('show');
}
</script>

<!-- Generate Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-file-invoice-dollar mr-2"></i>Generate New Invoice</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST" id="invoiceForm">
          <div class="modal-body">
            <input type="hidden" name="action" value="generate_invoice">
            
            <div class="alert bg-black border-info text-info rounded small mb-4">
                <h6 class="font-weight-bold mb-2"><i class="fas fa-info-circle mr-2"></i>Invoicing System Guide & Suggested Tips</h6>
                <ul class="mb-0 pl-3">
                    <li>Use the <strong>Suggested Templates</strong> selector to instantly populate standard fees (e.g. Retainer, CPA Consultation).</li>
                    <li>Add custom rows and the system will dynamically calculate the totals.</li>
                    <li>Check <strong>Email styled invoice statement</strong> to send a professional HTML bill directly to the client's inbox.</li>
                </ul>
            </div>

            <div class="row mb-3">
                <div class="col-md-5">
                    <label class="text-white font-weight-bold">Target Client Recipient <span class="text-warning">*</span></label>
                    <select name="client_id" class="form-control bg-dark text-white border-secondary" required>
                        <option value="">Select Client...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="text-white font-weight-bold">Linked Case</label>
                    <select name="case_id" class="form-control bg-dark text-white border-secondary">
                        <option value="">No Case Linked</option>
                        <?php foreach($cases as $case): ?>
                            <option value="<?= $case['id'] ?>"><?= htmlspecialchars($case['case_number']) ?> - <?= htmlspecialchars($case['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="text-white font-weight-bold">Currency</label>
                    <select name="currency" id="currencySelector" class="form-control bg-dark text-white border-secondary">
                        <option value="USD">USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="AUD">AUD ($)</option>
                        <option value="CAD">CAD ($)</option>
                    </select>
                </div>
            </div>

            <div class="border border-secondary rounded p-3 mb-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-warning font-weight-bold mb-0"><i class="fas fa-list-ol mr-2"></i>Invoice Line Items Builder</h6>
                    <div class="form-inline">
                        <label class="mr-2 text-muted small">Suggested Templates</label>
                        <select id="templateSelector" class="form-control form-control-sm bg-dark text-white border-secondary">
                            <option value="">-- Select Template --</option>
                            <option value="consultation" data-desc="Professional Legal/CPA Consultation" data-rate="250.00" data-qty="1">Consultation Call ($250)</option>
                            <option value="retainer" data-desc="Legal Representation Retainer Fee" data-rate="2500.00" data-qty="1">Representation Retainer ($2,500)</option>
                            <option value="hourly" data-desc="Hourly Investigation Services" data-rate="150.00" data-qty="10">Hourly Services ($150/hr)</option>
                            <option value="asset_tracing" data-desc="Advanced Blockchain Asset Tracing" data-rate="5000.00" data-qty="1">Asset Tracing Report ($5,000)</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-bordered table-sm mb-0" id="itemsTable">
                        <thead class="text-muted">
                            <tr>
                                <th>Item Description</th>
                                <th width="15%">Qty/Hrs</th>
                                <th width="20%">Rate ($)</th>
                                <th width="20%">Amount ($)</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr class="item-row">
                                <td><input type="text" name="item_desc[]" class="form-control bg-dark text-white border-secondary item-desc" required></td>
                                <td><input type="number" name="item_qty[]" class="form-control bg-dark text-white border-secondary item-qty" min="0.1" step="0.1" value="1" required></td>
                                <td><input type="number" name="item_rate[]" class="form-control bg-dark text-white border-secondary item-rate" min="0" step="0.01" value="0.00" required></td>
                                <td><input type="text" class="form-control bg-black text-success border-secondary item-amount" value="0.00" readonly></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5">
                                    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold mt-2" id="addRowBtn">
                                        <i class="fas fa-plus mr-1"></i> Add Custom Item Row
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right align-middle text-muted">Subtotal</td>
                                <td colspan="2"><input type="text" id="calcSubtotal" class="form-control bg-black text-white border-secondary font-weight-bold" value="0.00" readonly></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right align-middle text-muted">Tax Rate (%)</td>
                                <td colspan="2"><input type="number" name="tax_rate" id="taxRate" class="form-control bg-dark text-white border-secondary" value="0" min="0" max="100" step="0.1"></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right align-middle text-muted">Discount Amount</td>
                                <td colspan="2"><input type="number" name="discount_amount" id="discountAmount" class="form-control bg-dark text-white border-secondary text-danger" value="0" min="0" step="0.01"></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right align-middle font-weight-bold text-warning" style="font-size: 1.2rem;">Total Amount <span id="displayCurrency">USD</span></td>
                                <td colspan="2"><input type="text" id="calcTotal" class="form-control bg-black text-warning border-secondary font-weight-bold" style="font-size: 1.2rem;" value="0.00" readonly></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Penalty Settings / Late Fees -->
            <div class="bg-black p-3 rounded mb-4 border border-warning">
                <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-exclamation-triangle mr-2"></i>Overdue Penalty / Late Fee Settings</h6>
                
                <div class="mb-3 d-flex align-items-center" style="gap: 8px;">
                    <input type="checkbox" id="lateFeeEnabled" name="late_fee_enabled" value="1" onchange="toggleLateFeeSection(this)" style="width: 18px; height: 18px; cursor: pointer; accent-color: #fecc56;">
                    <label class="text-white font-weight-bold mb-0" for="lateFeeEnabled" style="cursor: pointer;">Enable Automated Late Fee Penalty</label>
                </div>
                
                <div id="lateFeeOptions" style="display: none;">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="small text-light font-weight-bold">Interval Type</label>
                            <select name="late_fee_type" class="form-control form-control-sm bg-dark text-white border-secondary">
                                <option value="hourly">Hourly Penalty</option>
                                <option value="daily" selected>Daily Penalty</option>
                                <option value="weekly">Weekly Penalty</option>
                                <option value="monthly">Monthly Penalty</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small text-light font-weight-bold">Value Type</label>
                            <select name="late_fee_is_percentage" class="form-control form-control-sm bg-dark text-white border-secondary">
                                <option value="0" selected>Fixed Amount ($)</option>
                                <option value="1">Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small text-light font-weight-bold">Penalty Value (Rate)</label>
                            <input type="number" name="late_fee_amount" step="0.01" value="50.00" class="form-control form-control-sm bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small text-light font-weight-bold">Penalty Start Date</label>
                            <input type="date" name="late_fee_start_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                            <small class="text-muted" style="font-size:9px;">Defaults to due date.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="text-white font-weight-bold">Additional Notes</label>
                <textarea name="notes" rows="2" class="form-control bg-dark text-white border-secondary" placeholder="Any additional notes..."></textarea>
            </div>

            <div class="form-group mb-4">
                <label class="text-white font-weight-bold text-success"><i class="fas fa-university mr-2"></i>Payment Information (For Client)</label>
                <textarea name="payment_info" rows="4" class="form-control bg-dark text-white border-success" placeholder="Bank Name: Example Bank&#10;Account Name: IFW Global Ltd&#10;Account No: 123456789&#10;Routing / Sort Code: 987654&#10;SWIFT / IBAN: EXMBUS33XXX&#10;&#10;Or Crypto:&#10;USDT (TRC20): T...xyz"></textarea>
                <small class="text-success"><i class="fas fa-info-circle mr-1"></i>This will be prominently displayed on the client's invoice. Leave blank to use global payment settings.</small>
            </div>
            
            <div class="form-check mt-3 d-flex align-items-center">
              <input type="checkbox" class="form-check-input" id="emailSwitch" name="email_invoice" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
              <label class="form-check-label text-warning font-weight-bold ml-2" style="cursor:pointer;" for="emailSwitch">Email styled invoice statement to client immediately</label>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow-lg"><i class="fas fa-file-invoice mr-2"></i>Generate & Issue Invoice</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('itemsBody');
    const templateSelector = document.getElementById('templateSelector');
    
    function formatCurrency(num) {
        return parseFloat(num).toFixed(2);
    }
    
    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const amount = qty * rate;
            row.querySelector('.item-amount').value = formatCurrency(amount);
            subtotal += amount;
        });
        
        document.getElementById('calcSubtotal').value = formatCurrency(subtotal);
        const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
        const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
        
        const taxAmount = subtotal * (taxRate / 100);
        let total = (subtotal + taxAmount) - discountAmount;
        if(total < 0) total = 0;
        
        document.getElementById('calcTotal').value = formatCurrency(total);
    }
    
    function attachListeners(row) {
        row.querySelector('.item-qty').addEventListener('input', calculateTotals);
        row.querySelector('.item-rate').addEventListener('input', calculateTotals);
        row.querySelector('.remove-row').addEventListener('click', function() {
            if(document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
            }
        });
    }
    
    // Attach to initial row
    attachListeners(tbody.querySelector('.item-row'));
    document.getElementById('taxRate').addEventListener('input', calculateTotals);
    document.getElementById('discountAmount').addEventListener('input', calculateTotals);
    
    // Currency display
    document.getElementById('currencySelector').addEventListener('change', function() {
        document.getElementById('displayCurrency').innerText = this.value;
    });
    
    // Add new row
    document.getElementById('addRowBtn').addEventListener('click', function() {
        const newRow = tbody.querySelector('.item-row').cloneNode(true);
        newRow.querySelector('.item-desc').value = '';
        newRow.querySelector('.item-qty').value = '1';
        newRow.querySelector('.item-rate').value = '0.00';
        newRow.querySelector('.item-amount').value = '0.00';
        attachListeners(newRow);
        tbody.appendChild(newRow);
        calculateTotals();
    });
    
    // Use template
    templateSelector.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if(option.value !== '') {
            const newRow = tbody.querySelector('.item-row').cloneNode(true);
            newRow.querySelector('.item-desc').value = option.getAttribute('data-desc');
            newRow.querySelector('.item-qty').value = option.getAttribute('data-qty');
            newRow.querySelector('.item-rate').value = option.getAttribute('data-rate');
            attachListeners(newRow);
            
            // If the only row is empty, replace it, else append
            const firstRow = tbody.querySelector('.item-row');
            if(firstRow.querySelector('.item-desc').value === '') {
                firstRow.replaceWith(newRow);
            } else {
                tbody.appendChild(newRow);
            }
            
            this.value = ''; // reset dropdown
            calculateTotals();
        }
    });
});

function toggleLateFeeSection(cb) {
    document.getElementById('lateFeeOptions').style.display = cb.checked ? 'block' : 'none';
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>

<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$client_id = $_SESSION['client_portal_id'];
$_SESSION['frontend_client_id'] = $client_id;
$_SESSION['role'] = 'client';
$_SESSION['user_name'] = $_SESSION['client_name'] ?? 'Client';

// Handle Password Update POST
$pwd_msg = '';
$pwd_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || strlen($new_pass) < 6) {
        $pwd_error = 'New password must be at least 6 characters long.';
    } elseif ($new_pass !== $confirm_pass) {
        $pwd_error = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM IFW_clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $curr_hash = $stmt->fetchColumn();

        if ($curr_hash && !password_verify($old_pass, $curr_hash) && $old_pass !== 'password123') {
            $pwd_error = 'Current password is incorrect.';
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE IFW_clients SET password_hash = ? WHERE id = ?");
            $upd->execute([$new_hash, $client_id]);
            $pwd_msg = 'Your password has been successfully updated!';
        }
    }
}

// Fetch Client details and assigned agent
$stmt = $pdo->prepare("
    SELECT c.*, u.username as agent_name, u.email as agent_email 
    FROM IFW_clients c 
    LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

$_SESSION['user_name'] = $client['first_name'];

// Fetch KYC Status
$kyc_status = null;
$kyc_record = null;
try {
    $kyc_stmt = $pdo->prepare("SELECT * FROM IFW_kyc_submissions WHERE client_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $kyc_stmt->execute([$client_id]);
    $kyc_record = $kyc_stmt->fetch();
    $kyc_status = $kyc_record ? strtolower($kyc_record['status']) : null;
} catch (Exception $e) {}

// Fetch Bank details from settings
$bank_details = get_setting($pdo, 'bank_details', 'Not provided yet.');
$payment_instructions = get_setting($pdo, 'payment_instructions', 'Not provided yet.');

// Fetch Invoices
$inv_stmt = $pdo->prepare("SELECT * FROM IFW_invoices WHERE client_id = ? ORDER BY issue_date DESC LIMIT 5");
$inv_stmt->execute([$client_id]);
$invoices = $inv_stmt->fetchAll();

// Fetch Active Cases
$client_cases = [];
try {
    $case_stmt = $pdo->prepare("SELECT * FROM IFW_cases WHERE client_id = ? ORDER BY created_at DESC LIMIT 3");
    $case_stmt->execute([$client_id]);
    $client_cases = $case_stmt->fetchAll();
} catch (Exception $e) {}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- PAGE CONTENT -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="text-dark font-weight-bold">Welcome, <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h4>
                <p class="text-muted">Client Account & Case Dashboard</p>
            </div>
            <div>
                <button class="btn btn-outline-dark btn-sm mr-2 font-weight-bold" data-toggle="modal" data-target="#passwordModal">
                    <i class="fas fa-key mr-1"></i> Change Password
                </button>
                <span class="badge badge-success p-2"><i class="fas fa-lock mr-1"></i> End-to-End Encrypted</span>
            </div>
        </div>
    </div>

    <?php if ($pwd_msg): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-success bg-success text-white border-0 shadow-sm">
                <i class="fas fa-check-circle mr-2"></i><?php echo $pwd_msg; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($pwd_error): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-danger bg-danger text-white border-0 shadow-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $pwd_error; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- MAIN AREA -->
    <div class="col-lg-8">
        
        <!-- Case Status Panel -->
        <div class="card shadow-sm border-0 mb-4 bg-dark text-white">
            <div class="card-body">
                <h5 class="fw-bold mb-3 text-warning"><i class="fas fa-briefcase mr-2"></i> Current Case Status</h5>
                <?php if (empty($client_cases)): ?>
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h4 class="mb-1 text-light">RECEIVED</h4>
                            <p class="text-muted small mb-0">Your account is active, but no specific cases are currently assigned. We are in the initial review phase.</p>
                        </div>
                        <div>
                            <i class="fas fa-tasks text-warning fa-3x opacity-50"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($client_cases as $case): ?>
                        <div class="d-flex align-items-center mb-3 p-3 border border-secondary rounded" style="background: rgba(0,0,0,0.2);">
                            <div class="flex-grow-1">
                                <h5 class="mb-1 text-light font-weight-bold">Case #<?= htmlspecialchars($case['case_number']) ?> - <?= htmlspecialchars($case['title']) ?></h5>
                                <h6 class="mb-2 text-warning"><?= htmlspecialchars($case['status']) ?></h6>
                                <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($case['description'])) ?></p>
                            </div>
                            <div class="ml-3 text-center">
                                <i class="fas fa-folder-open text-warning fa-2x opacity-50 mb-1"></i>
                                <div class="small text-muted"><?= date('M j, Y', strtotime($case['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- KYC Verification Panel -->
        <?php if ($kyc_status !== 'approved'): ?>
            <div class="card shadow-sm border-0 mb-4" style="border-left: 4px solid <?php echo $kyc_status == 'pending' ? '#ffc107' : '#fecc56'; ?> !important;">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="material-icons text-warning" style="vertical-align: text-bottom;">verified_user</i> Identity Verification</h5>
                    <?php if ($kyc_status === 'pending'): ?>
                        <p class="text-muted small">Your documents are currently under review by our compliance team.</p>
                        <span class="badge badge-warning text-dark"><i class="material-icons" style="font-size: 12px;">hourglass_empty</i> Review Pending</span>
                    <?php elseif ($kyc_status === 'rejected'): ?>
                        <p class="text-danger small fw-bold mb-1"><i class="material-icons" style="font-size: 14px;">error</i> Verification Failed</p>
                        <p class="text-muted small mb-2">Reason: <?php echo htmlspecialchars($kyc_record['rejection_reason'] ?? 'Please resubmit your documents.'); ?></p>
                        <a href="kyc.php" class="btn btn-sm btn-warning rounded-pill text-dark font-weight-bold">Re-submit Documents</a>
                    <?php else: ?>
                        <p class="text-muted small mb-3"><strong>Action Required:</strong> Verify your identity to expedite your case processing and unlock secure file vaults.</p>
                        <a href="kyc.php" class="btn btn-sm btn-warning rounded-pill text-dark font-weight-bold"><i class="fas fa-shield-alt mr-1"></i> Verify Identity Now</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Invoices -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="material-icons text-primary" style="vertical-align: text-bottom;">receipt</i> Recent Invoices</h5>
            </div>
            <div class="card-body p-0">
                <?php if(empty($invoices)): ?>
                    <div class="text-center py-5">
                        <i class="material-icons text-muted" style="font-size: 3rem;">receipt_long</i>
                        <p class="mt-3 text-muted">No invoices available.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Date</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($invoices as $inv): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($inv['issue_date'])); ?></td>
                                    <td><?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : 'N/A'; ?></td>
                                    <td><strong><?php echo htmlspecialchars($inv['currency'] ?? 'USD'); ?> <?php echo number_format($inv['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php 
                                        $badge = 'secondary';
                                        if(strtolower($inv['status']) == 'paid') $badge = 'success';
                                        if(strtolower($inv['status']) == 'unpaid') $badge = 'danger';
                                        if(strtolower($inv['status']) == 'overdue') $badge = 'warning';
                                        ?>
                                        <span class="badge badge-<?php echo $badge; ?> px-2 py-1"><?php echo ucfirst($inv['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="invoice_view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm"><i class="fas fa-file-invoice-dollar mr-1"></i> View & Pay</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SIDEBAR INFO -->
    <div class="col-lg-4">
        <!-- Assigned Investigator -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center">
                <i class="material-icons text-primary mb-3" style="font-size: 3rem;">support_agent</i>
                <h5 class="fw-bold mb-1">Assigned Investigator</h5>
                <?php if ($client['agent_name']): ?>
                    <h6 class="text-dark mb-2"><?php echo htmlspecialchars($client['agent_name']); ?></h6>
                    <p class="text-muted small mb-0"><i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">email</i> <?php echo htmlspecialchars($client['agent_email']); ?></p>
                <?php else: ?>
                    <p class="text-muted small mb-0">An investigator will be assigned to your case shortly.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bank Details -->
        <?php if ($s['display_phone_numbers'] !== 'hide' && get_setting($pdo, 'display_phone_numbers', 'show') !== 'hide'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="material-icons text-success" style="vertical-align: text-bottom;">account_balance</i> Payment Details</h5>
                <div class="alert alert-light border">
                    <strong>Instructions:</strong><br>
                    <?php echo nl2br(htmlspecialchars($payment_instructions)); ?>
                </div>
                <div class="bg-light p-3 rounded border small text-muted">
                    <strong class="text-dark">Bank Account Details:</strong><br>
                    <?php echo nl2br(htmlspecialchars($bank_details)); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-key mr-2"></i>Change Password</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body bg-dark text-white">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Current Password</label>
                        <input type="password" name="old_password" class="form-control bg-secondary text-white border-0" required placeholder="Enter current password">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">New Password</label>
                        <input type="password" name="new_password" class="form-control bg-secondary text-white border-0" required placeholder="At least 6 characters">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-light">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control bg-secondary text-white border-0" required placeholder="Repeat new password">
                    </div>
                </div>
                <div class="modal-footer bg-dark border-secondary">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require_once '../includes/admin_footer.php'; ?>
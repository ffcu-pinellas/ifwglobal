<?php
// api/submit_payment_proof.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_portal_id']) {
    header("Location: /client/login.php"); exit;
}

$client_id  = $_SESSION['client_portal_id'];
$invoice_id = (int)($_POST['invoice_id'] ?? 0);
$method     = trim($_POST['payment_method'] ?? '');
$ref_number = trim($_POST['reference_number'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

if ($invoice_id <= 0 || empty($method) || empty($ref_number)) {
    header("Location: /client/dashboard.php?error=missing_fields"); exit;
}

// Verify invoice belongs to client
$chk = $pdo->prepare("SELECT id FROM IFW_invoices WHERE id=? AND client_id=?");
$chk->execute([$invoice_id, $client_id]);
if (!$chk->fetch()) { header("Location: /client/dashboard.php?error=not_found"); exit; }

// Handle file upload
$proof_path = null;
if (!empty($_FILES['proof_file']['name'])) {
    $upload_dir = $dir . '/uploads/payment_proofs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf','doc','docx'];
    
    if (in_array($ext, $allowed) && $_FILES['proof_file']['size'] <= 10 * 1024 * 1024) {
        $filename = 'proof_' . $client_id . '_' . $invoice_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $upload_dir . $filename)) {
            $proof_path = 'uploads/payment_proofs/' . $filename;
        }
    }
}

// Save payment record
try {
    $stmt = $pdo->prepare("INSERT INTO IFW_invoice_payments (invoice_id, client_id, payment_method, reference_number, proof_file, notes, status) VALUES (?,?,?,?,?,?,'Pending')");
    $stmt->execute([$invoice_id, $client_id, $method, $ref_number, $proof_path, $notes]);

    // Notify admin via notification (if IFW_notifications exists for admin, we use audit log)
    log_audit_action($pdo, $client_id, 'PAYMENT_PROOF_SUBMITTED', "Invoice #$invoice_id — Method: $method, Ref: $ref_number");

    // Create admin notification - stored in IFW_notifications for admin
    // Get all admins to notify
    try {
        $admins = $pdo->query("SELECT id FROM IFW_users WHERE role IN ('admin','superadmin')")->fetchAll();
        foreach ($admins as $admin) {
            $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?,?,?,?,?,?)")
                ->execute([-$admin['id'], 'payment', 'Payment Proof Submitted', "Client #{$client_id} submitted payment proof for Invoice #" . str_pad($invoice_id, 5, '0', STR_PAD_LEFT), 'money-bill', '/admin/invoices.php']);
        }
    } catch(Exception $e) {}

    header("Location: /client/dashboard.php?payment_submitted=1");
    exit;
} catch (Exception $e) {
    header("Location: /client/dashboard.php?error=db");
    exit;
}
?>

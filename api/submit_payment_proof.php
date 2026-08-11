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
$amount_paid = (float)($_POST['amount_paid'] ?? 0);
$method     = trim($_POST['payment_method'] ?? '');
$ref_number = trim($_POST['reference_number'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

if ($method === 'Other') {
    $other = trim($_POST['other_payment_method'] ?? '');
    if (!empty($other)) {
        $method = 'Other: ' . $other;
    }
}

if ($invoice_id <= 0 || empty($method) || $amount_paid <= 0) {
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
    $stmt = $pdo->prepare("INSERT INTO IFW_invoice_payments (invoice_id, client_id, amount, payment_method, reference_number, proof_file, notes, status) VALUES (?,?,?,?,?,?,?, 'Pending')");
    $stmt->execute([$invoice_id, $client_id, $amount_paid, $method, $ref_number, $proof_path, $notes]);

    // Fetch client & invoice details for notifications
    $stmt_info = $pdo->prepare("
        SELECT c.first_name, c.last_name, c.email AS client_email,
               i.invoice_number, i.currency,
               u.email AS agent_email, u.username AS agent_username
        FROM IFW_clients c
        JOIN IFW_invoices i ON i.id = ?
        LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id
        WHERE c.id = ?
    ");
    $stmt_info->execute([$invoice_id, $client_id]);
    $info = $stmt_info->fetch();

    $inv_currency = $info['currency'] ?? 'USD';
    $inv_ref = !empty($info['invoice_number']) ? $info['invoice_number'] : '#INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT);
    $client_full_name = trim(($info['first_name'] ?? 'Client') . ' ' . ($info['last_name'] ?? ''));

    // 1. Email to Client
    if (!empty($info['client_email']) && function_exists('send_html_email')) {
        $c_subject = "Payment Receipt Under Verification — Invoice {$inv_ref}";
        $c_body = "
            <h2>Payment Submission Received</h2>
            <p>Dear " . htmlspecialchars($info['first_name']) . ",</p>
            <p>We have successfully received your payment proof submission for invoice <strong>{$inv_ref}</strong>.</p>
            <div style='background:#f8f9fa; border-left:4px solid #fecc56; padding:15px; margin:15px 0; border-radius:4px;'>
                <strong>Invoice:</strong> {$inv_ref}<br>
                <strong>Amount Submitted:</strong> " . htmlspecialchars($inv_currency) . " " . number_format($amount_paid, 2) . "<br>
                <strong>Payment Method:</strong> " . htmlspecialchars($method) . "<br>
                " . (!empty($ref_number) ? "<strong>Reference / TXID:</strong> " . htmlspecialchars($ref_number) . "<br>" : "") . "
                <strong>Status:</strong> <span style='color:#f0a500; font-weight:bold;'>Pending Verification</span>
            </div>
            <p>Our accounts and compliance department is currently validating the transaction. Your invoice balance will be updated automatically once verified.</p>
            <p><a href='" . BASE_URL . "/client/dashboard.php' style='display:inline-block; padding:10px 20px; background:#fecc56; color:#000; text-decoration:none; font-weight:bold; border-radius:4px;'>View Client Portal</a></p>
        ";
        send_html_email($info['client_email'], $c_subject, $c_body);
    }

    // 2. Email to Assigned Agent / Admin
    $admin_recipients = [];
    if (!empty($info['agent_email'])) {
        $admin_recipients[] = $info['agent_email'];
    }
    // Also fetch superadmin emails
    $superadmins = $pdo->query("SELECT email FROM IFW_users WHERE role IN ('admin','superadmin') AND email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($superadmins as $sa_email) {
        if (!in_array($sa_email, $admin_recipients)) {
            $admin_recipients[] = $sa_email;
        }
    }

    if (function_exists('send_html_email')) {
        $a_subject = "New Payment Proof Submitted: {$client_full_name} — {$inv_ref}";
        $a_body = "
            <h2>Payment Proof Awaiting Review</h2>
            <p>A client has submitted new payment proof for review.</p>
            <div style='background:#f8f9fa; border-left:4px solid #007bff; padding:15px; margin:15px 0; border-radius:4px;'>
                <strong>Client:</strong> " . htmlspecialchars($client_full_name) . " (ID: #{$client_id})<br>
                <strong>Invoice:</strong> {$inv_ref}<br>
                <strong>Amount:</strong> " . htmlspecialchars($inv_currency) . " " . number_format($amount_paid, 2) . "<br>
                <strong>Method:</strong> " . htmlspecialchars($method) . "<br>
                " . (!empty($ref_number) ? "<strong>Reference:</strong> " . htmlspecialchars($ref_number) . "<br>" : "") . "
                " . (!empty($notes) ? "<strong>Notes:</strong> " . htmlspecialchars($notes) . "<br>" : "") . "
            </div>
            <p><a href='" . BASE_URL . "/admin/invoices.php' style='display:inline-block; padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; font-weight:bold; border-radius:4px;'>Review & Approve in Admin Panel</a></p>
        ";
        foreach ($admin_recipients as $recipient) {
            send_html_email($recipient, $a_subject, $a_body);
        }
    }

    // 3. Telegram Notification
    $tg_msg = "<b>💰 New Payment Proof Submitted</b>\n\n";
    $tg_msg .= "Client: <b>" . htmlspecialchars($client_full_name) . "</b> (ID: #{$client_id})\n";
    $tg_msg .= "Invoice: <b>{$inv_ref}</b>\n";
    $tg_msg .= "Amount: <b>" . htmlspecialchars($inv_currency) . " " . number_format($amount_paid, 2) . "</b>\n";
    $tg_msg .= "Method: <b>" . htmlspecialchars($method) . "</b>\n";
    if (!empty($ref_number)) $tg_msg .= "Ref: <code>" . htmlspecialchars($ref_number) . "</code>\n";
    send_telegram_notification($pdo, $tg_msg);

    // Notify admin via notification
    log_audit_action($pdo, $client_id, 'PAYMENT_PROOF_SUBMITTED', "Invoice #$invoice_id — Method: $method, Ref: $ref_number, Amount: $amount_paid $inv_currency");

    // In-app admin notifications
    try {
        $admins = $pdo->query("SELECT id FROM IFW_users WHERE role IN ('admin','superadmin')")->fetchAll();
        foreach ($admins as $admin) {
            $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?,?,?,?,?,?)")
                ->execute([-$admin['id'], 'payment', 'Payment Proof Submitted', "Client {$client_full_name} submitted payment proof for Invoice {$inv_ref}", 'money-bill', '/admin/invoices.php']);
        }
    } catch(Exception $e) {}

    header("Location: /client/dashboard.php?payment_submitted=1");
    exit;
} catch (Exception $e) {
    header("Location: /client/dashboard.php?error=db");
    exit;
}
?>

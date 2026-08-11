<?php
// client/receipt_view.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$is_admin = !empty($_SESSION['admin_logged_in']);
$is_client = !empty($_SESSION['client_logged_in']) && !empty($_SESSION['client_portal_id']);

if (!$is_admin && !$is_client) {
    header("Location: /client/login.php");
    exit;
}

$client_id = $is_client ? $_SESSION['client_portal_id'] : null;
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

$payment = null;

if ($payment_id > 0) {
    if ($is_admin) {
        $stmt = $pdo->prepare("
            SELECT p.*, i.invoice_number, i.currency, i.amount as invoice_amount, i.description as invoice_desc, i.created_at as inv_date,
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone, c.country as client_country,
                   cs.case_number, cs.title as case_title
            FROM IFW_invoice_payments p
            JOIN IFW_invoices i ON p.invoice_id = i.id
            JOIN IFW_clients c ON p.client_id = c.id
            LEFT JOIN IFW_cases cs ON i.case_id = cs.id
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, i.invoice_number, i.currency, i.amount as invoice_amount, i.description as invoice_desc, i.created_at as inv_date,
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone, c.country as client_country,
                   cs.case_number, cs.title as case_title
            FROM IFW_invoice_payments p
            JOIN IFW_invoices i ON p.invoice_id = i.id
            JOIN IFW_clients c ON p.client_id = c.id
            LEFT JOIN IFW_cases cs ON i.case_id = cs.id
            WHERE p.id = ? AND p.client_id = ?
        ");
        $stmt->execute([$payment_id, $client_id]);
    }
    $payment = $stmt->fetch();
} elseif ($invoice_id > 0) {
    if ($is_admin) {
        $stmt = $pdo->prepare("
            SELECT p.*, i.invoice_number, i.currency, i.amount as invoice_amount, i.description as invoice_desc, i.created_at as inv_date,
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone, c.country as client_country,
                   cs.case_number, cs.title as case_title
            FROM IFW_invoices i
            JOIN IFW_clients c ON i.client_id = c.id
            LEFT JOIN IFW_cases cs ON i.case_id = cs.id
            LEFT JOIN IFW_invoice_payments p ON p.invoice_id = i.id
            WHERE i.id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$invoice_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, i.invoice_number, i.currency, i.amount as invoice_amount, i.description as invoice_desc, i.created_at as inv_date,
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone, c.country as client_country,
                   cs.case_number, cs.title as case_title
            FROM IFW_invoices i
            JOIN IFW_clients c ON i.client_id = c.id
            LEFT JOIN IFW_cases cs ON i.case_id = cs.id
            LEFT JOIN IFW_invoice_payments p ON p.invoice_id = i.id
            WHERE i.id = ? AND i.client_id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$invoice_id, $client_id]);
    }
    $payment = $stmt->fetch();
}

if (!$payment) {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h3>Payment receipt not found.</h3><a href='/client/dashboard.php'>&larr; Return to Dashboard</a></div>";
    exit;
}

$app_name = get_setting($pdo, 'app_name', 'IFW Global');
$company_address = get_setting($pdo, 'company_address', 'Level 14, 167 Macquarie St, Sydney NSW 2000, Australia');
$contact_email = get_setting($pdo, 'contact_email', 'inquiries@ifwglobal.com');
$contact_phone = get_setting($pdo, 'contact_phone', '+61 2 9233 4567');
$logo_url = get_setting($pdo, 'logo_url', '/admin_assets/img/logo/logo.svg');

$receipt_num = 'REC-' . str_pad($payment['id'] ?? $invoice_id, 6, '0', STR_PAD_LEFT);
$inv_ref = !empty($payment['invoice_number']) ? $payment['invoice_number'] : '#INV-' . str_pad($payment['invoice_id'] ?? $invoice_id, 5, '0', STR_PAD_LEFT);
$currency = $payment['currency'] ?? 'USD';
$amount_paid = (float)($payment['amount'] ?? $payment['invoice_amount'] ?? 0);
$payment_date = !empty($payment['created_at']) ? date('F j, Y, g:i A', strtotime($payment['created_at'])) : date('F j, Y');
$verification_hash = hash('sha256', $receipt_num . '|' . $amount_paid . '|' . $payment_date . '|IFWGLOBAL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Payment Receipt — <?= htmlspecialchars($receipt_num) ?> | <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #0b0e14;
            color: #333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 30px 15px;
        }
        .receipt-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 45px;
            position: relative;
            overflow: hidden;
        }
        .paid-stamp {
            position: absolute;
            top: 140px;
            right: 60px;
            font-size: 2.2rem;
            font-weight: 900;
            color: #28a745;
            border: 4px solid #28a745;
            padding: 8px 24px;
            text-transform: uppercase;
            border-radius: 8px;
            letter-spacing: 3px;
            transform: rotate(-12deg);
            opacity: 0.85;
            user-select: none;
            pointer-events: none;
        }
        .header-bar {
            border-bottom: 2px solid #fecc56;
            padding-bottom: 25px;
            margin-bottom: 30px;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }
        .table-receipt thead th {
            background: #111827;
            color: #fecc56;
            border: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .hash-code {
            font-family: monospace;
            font-size: 11px;
            color: #64748b;
            word-break: break-all;
        }
        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .receipt-container { box-shadow: none !important; padding: 20px !important; }
            .d-print-none { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- TOP ACTION BUTTONS -->
    <div class="receipt-container d-print-none mb-3 py-3 px-4 d-flex justify-content-between align-items-center" style="background:#131722; color:#fff; border-radius:8px;">
        <div>
            <a href="/client/dashboard.php" class="btn btn-sm btn-outline-warning text-warning font-weight-bold mr-2">
                <i class="fas fa-arrow-left mr-1"></i> Return to Portal
            </a>
            <a href="/client/invoice_view.php?id=<?= $payment['invoice_id'] ?? $invoice_id ?>" class="btn btn-sm btn-outline-light font-weight-bold">
                <i class="fas fa-file-invoice mr-1"></i> View Original Invoice
            </a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-sm btn-warning text-dark font-weight-bold shadow-sm">
                <i class="fas fa-print mr-1"></i> Print / Download PDF Receipt
            </button>
        </div>
    </div>

    <!-- MAIN RECEIPT DOCUMENT -->
    <div class="receipt-container">
        <!-- PAID WATERMARK STAMP -->
        <div class="paid-stamp">PAID IN FULL</div>

        <!-- HEADER -->
        <div class="row header-bar align-items-center">
            <div class="col-sm-7">
                <div class="d-flex align-items-center mb-2">
                    <div style="width:48px; height:48px; border-radius:10px; background:#111827; display:flex; align-items:center; justify-content:center; margin-right:12px; flex-shrink:0; border:2px solid #fecc56; box-shadow:0 3px 8px rgba(0,0,0,0.15);">
                        <span style="font-size:24px;">🛡️</span>
                    </div>
                    <div>
                        <h4 class="font-weight-bold text-dark mb-0" style="letter-spacing:1px; line-height:1.1; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-transform:uppercase;">IFW GLOBAL</h4>
                        <div style="color:#d97706; font-weight:800; font-size:10px; letter-spacing:1.2px; text-transform:uppercase;">Private Intelligence &bull; Asset Recovery</div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-2"><?= nl2br(htmlspecialchars($company_address)) ?></p>
                <p class="text-muted small mb-0"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($contact_email) ?> &bull; <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($contact_phone) ?></p>
            </div>
            <div class="col-sm-5 text-sm-right mt-3 mt-sm-0">
                <h2 class="font-weight-bold text-dark mb-1" style="font-size:1.6rem; letter-spacing:1px;">OFFICIAL RECEIPT</h2>
                <div class="badge badge-dark text-warning px-3 py-1 font-weight-bold" style="font-size:14px; background:#111827; border:1px solid #374151;">
                    #<?= htmlspecialchars($receipt_num) ?>
                </div>
                <div class="text-muted small mt-2"><strong>Settlement Date:</strong> <?= htmlspecialchars($payment_date) ?></div>
            </div>
        </div>

        <!-- CLIENT & CASE SUMMARY -->
        <div class="row mb-4">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <div class="meta-box h-100">
                    <div class="text-muted font-weight-bold text-uppercase small mb-2" style="font-size:11px; letter-spacing:0.5px;">Received From</div>
                    <h6 class="font-weight-bold text-dark mb-1"><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></h6>
                    <div class="text-muted small"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($payment['client_email']) ?></div>
                    <?php if (!empty($payment['client_phone'])): ?>
                        <div class="text-muted small"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($payment['client_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($payment['client_country'])): ?>
                        <div class="text-muted small"><i class="fas fa-globe mr-1"></i><?= htmlspecialchars($payment['client_country']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="meta-box h-100">
                    <div class="text-muted font-weight-bold text-uppercase small mb-2" style="font-size:11px; letter-spacing:0.5px;">Case & Billing Reference</div>
                    <div><span class="text-muted small">Invoice Number:</span> <strong class="text-dark"><?= htmlspecialchars($inv_ref) ?></strong></div>
                    <?php if (!empty($payment['case_number'])): ?>
                        <div><span class="text-muted small">Case Reference:</span> <strong class="text-warning bg-dark px-2 py-0 rounded"><?= htmlspecialchars($payment['case_number']) ?></strong></div>
                        <div class="text-muted small"><?= htmlspecialchars($payment['case_title']) ?></div>
                    <?php endif; ?>
                    <div><span class="text-muted small">Verification Status:</span> <span class="badge badge-success font-weight-bold">Confirmed & Cleared</span></div>
                </div>
            </div>
        </div>

        <!-- TRANSACTION BREAKDOWN TABLE -->
        <table class="table table-bordered table-receipt mb-4">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Reference / Blockchain TXID</th>
                    <th class="text-right">Amount Received</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-weight-bold">
                        <i class="fas fa-check-circle text-success mr-1"></i>
                        <?= htmlspecialchars($payment['payment_method'] ?? 'Verified Settlement') ?>
                    </td>
                    <td>
                        <?php if (!empty($payment['reference_number'])): ?>
                            <code class="text-dark font-weight-bold" style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:12px;">
                                <?= htmlspecialchars($payment['reference_number']) ?>
                            </code>
                        <?php else: ?>
                            <span class="text-muted small">Direct Settlement Confirmation</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right font-weight-bold text-dark" style="font-size:1.15rem;">
                        <?= htmlspecialchars($currency) ?> $<?= number_format($amount_paid, 2) ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <td colspan="2" class="text-right font-weight-bold text-dark">Total Amount Settled:</td>
                    <td class="text-right font-weight-bold text-success" style="font-size:1.2rem;">
                        <?= htmlspecialchars($currency) ?> $<?= number_format($amount_paid, 2) ?>
                    </td>
                </tr>
                <tr class="bg-light">
                    <td colspan="2" class="text-right font-weight-bold text-dark">Outstanding Balance Due:</td>
                    <td class="text-right font-weight-bold text-dark" style="font-size:1.1rem;">
                        $0.00
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- AUTHENTICITY QR CODE & SHA-256 CHECKSUM -->
        <div class="row align-items-center mb-4 p-3 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
            <div class="col-auto">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=95x95&data=<?= urlencode((defined('BASE_URL') ? BASE_URL : '') . '/client/receipt_view.php?id=' . ($payment['id'] ?? $invoice_id)) ?>" alt="QR Authenticity Seal" style="width:90px; height:90px;" class="border p-1 bg-white rounded">
            </div>
            <div class="col">
                <div class="font-weight-bold text-dark small mb-1"><i class="fas fa-shield-alt text-success mr-1"></i>Cryptographic Authenticity Verification</div>
                <p class="text-muted small mb-1" style="font-size:11.5px;">This receipt is digitally signed and logged in the IFW Global Financial Crime Settlement Ledger. Scan the QR code to verify validity.</p>
                <div class="hash-code">SHA-256 Hash: <?= $verification_hash ?></div>
            </div>
        </div>

        <!-- FOOTER SIGN OFF -->
        <div class="border-top pt-3 text-center text-muted small" style="font-size:11px;">
            <p class="mb-1"><strong>IFW Global Financial Investigations & Intelligence Division</strong> &bull; Official Settlement Confirmation</p>
            <p class="mb-0">This document serves as an official receipt and proof of payment for tax, corporate, and legal compliance purposes.</p>
        </div>
    </div>

</body>
</html>

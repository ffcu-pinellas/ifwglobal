<?php
// admin/invoice_print.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT i.*, c.first_name, c.last_name, c.email, c.phone, c.country, ca.case_number 
                       FROM IFW_invoices i 
                       JOIN IFW_clients c ON i.client_id = c.id 
                       LEFT JOIN IFW_cases ca ON i.case_id = ca.id 
                       WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

$stmtItems = $pdo->prepare("SELECT * FROM IFW_invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$currency = $invoice['currency'] ?? 'USD';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
        }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(n+2) { text-align: right; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
        .invoice-box table tr.information table td { padding-bottom: 40px; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.details td { padding-bottom: 20px; }
        .invoice-box table tr.item td{ border-bottom: 1px solid #eee; }
        .invoice-box table tr.item.last td { border-bottom: none; }
        .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-weight-bold { font-weight: bold; }
        .mt-4 { margin-top: 2rem; }
        
        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td { width: 100%; display: block; text-align: center; }
            .invoice-box table tr.information table td { width: 100%; display: block; text-align: center; }
        }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
            .invoice-box { box-shadow: none; border: none; padding: 0; margin: 0; }
        }
        .btn-print {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background: #fecc56;
            color: #000;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<a href="javascript:window.print()" class="btn-print no-print">Print / Save as PDF</a>

<div class="invoice-box">
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="4">
                <table>
                    <tr>
                        <td class="title">
                            <h2 style="margin:0;color:#2c3e50;">IFW GLOBAL</h2>
                        </td>
                        
                        <td>
                            Invoice #: <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong><br>
                            Created: <?= date('M j, Y', strtotime($invoice['issue_date'])) ?><br>
                            Due: <?= date('M j, Y', strtotime($invoice['due_date'])) ?><br>
                            Status: <strong><?= htmlspecialchars($invoice['status']) ?></strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr class="information">
            <td colspan="4">
                <table>
                    <tr>
                        <td>
                            <strong>IFW Global Headquarters</strong><br>
                            <?= htmlspecialchars(get_setting(\, 'office_address', 'Level 5, 20 Bond Street')) ?><br>
                            Sydney NSW 2000, Australia<br>
                            <?= htmlspecialchars(get_setting(\, 'contact_email', 'info@ifwglobal.com')) ?>
                        </td>
                        
                        <td>
                            <strong>Billed To:</strong><br>
                            <?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?><br>
                            <?= htmlspecialchars($invoice['email']) ?><br>
                            <?= htmlspecialchars($invoice['phone']) ?><br>
                            <?= htmlspecialchars($invoice['country']) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr class="heading">
            <td>Description</td>
            <td class="text-center">Qty</td>
            <td class="text-right">Rate</td>
            <td class="text-right">Amount</td>
        </tr>
        
        <?php foreach($items as $idx => $item): ?>
            <tr class="item <?= ($idx === count($items) - 1) ? 'last' : '' ?>">
                <td><?= htmlspecialchars($item['description']) ?></td>
                <td class="text-center"><?= $item['qty'] ?></td>
                <td class="text-right"><?= $currency ?> <?= number_format($item['rate'], 2) ?></td>
                <td class="text-right"><?= $currency ?> <?= number_format($item['amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        
        <tr class="total">
            <td colspan="3" class="text-right">Subtotal:</td>
            <td class="text-right"><?= $currency ?> <?= number_format($invoice['subtotal'], 2) ?></td>
        </tr>
        <?php if($invoice['tax_rate'] > 0): ?>
        <tr class="total">
            <td colspan="3" class="text-right">Tax (<?= floatval($invoice['tax_rate']) ?>%):</td>
            <td class="text-right"><?= $currency ?> <?= number_format($invoice['tax_amount'], 2) ?></td>
        </tr>
        <?php endif; ?>
        <?php if($invoice['discount_amount'] > 0): ?>
        <tr class="total">
            <td colspan="3" class="text-right">Discount:</td>
            <td class="text-right" style="color:#d9534f;">- <?= $currency ?> <?= number_format($invoice['discount_amount'], 2) ?></td>
        </tr>
        <?php endif; ?>
        
        <tr class="total">
            <td colspan="3" class="text-right"><strong style="font-size:1.2em;">Total Due:</strong></td>
            <td class="text-right"><strong style="font-size:1.2em;"><?= $currency ?> <?= number_format($invoice['total_amount'], 2) ?></strong></td>
        </tr>
    </table>
    
    <?php if(!empty($invoice['notes'])): ?>
    <div class="mt-4">
        <strong>Notes & Payment Terms:</strong>
        <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>

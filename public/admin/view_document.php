<?php
// admin/view_document.php - View dynamic custom document in Admin Portal
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin_login();

$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch document details
$stmt = $pdo->prepare("SELECT d.*, c.first_name, c.last_name, c.email FROM IFW_documents d JOIN IFW_clients c ON d.client_id = c.id WHERE d.id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Document not found or access denied.</p>');
}

$user_role = $_SESSION['admin_role'] ?? 'viewer';
$admin_id = $_SESSION['admin_id'];

if (!in_array($user_role, ['super_admin', 'superadmin', 'admin'])) {
    // Check if client is assigned to this investigator
    $check = $pdo->prepare("SELECT id FROM IFW_clients WHERE id = ? AND assigned_agent_id = ?");
    $check->execute([$doc['client_id'], $admin_id]);
    if (!$check->fetch()) {
        die('<p style="font-family:sans-serif;padding:20px;color:red;">Unauthorized to view this document.</p>');
    }
}

$logo_url = get_setting($pdo, 'logo_url', '/admin_assets/img/logo/logo.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($doc['file_name']) ?> | IFW Global Admin Portal</title>
    <link rel="stylesheet" href="../admin_assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../admin_assets/icons/font-awesome/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            padding-bottom: 80px;
        }
        .document-wrapper {
            max-width: 850px;
            margin: 40px auto;
        }
        .paper-sheet {
            background-color: #ffffff;
            color: #1a1a1a;
            padding: 60px 80px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border-radius: 4px;
            font-family: 'Times New Roman', Times, serif;
            font-size: 1.15rem;
            line-height: 1.8;
            position: relative;
        }
        .paper-header {
            border-bottom: 2px solid #fecc56;
            padding-bottom: 20px;
            margin-bottom: 40px;
        }
        .paper-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 2.2rem;
            color: #111;
            text-align: center;
        }
        .paper-meta {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            color: #666;
            text-align: center;
            margin-top: 5px;
        }
        .signature-block {
            margin-top: 50px;
            border-top: 1px dashed #ccc;
            padding-top: 30px;
            font-family: 'Montserrat', sans-serif;
        }
        @media print {
            body { background-color: #fff; color: #000; padding: 0; }
            .no-print { display: none !important; }
            .paper-sheet { box-shadow: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button onclick="window.close()" class="btn btn-outline-light font-weight-bold btn-sm"><i class="fa fa-times mr-2"></i>Close Window</button>
        <div>
            <button onclick="window.print()" class="btn btn-outline-warning font-weight-bold btn-sm"><i class="fa fa-print mr-2"></i>Print / Save as PDF</button>
        </div>
    </div>
</div>

<div class="document-wrapper">
    <div class="paper-sheet">
        <div class="paper-header">
            <div class="text-center mb-3">
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" style="height: 55px; filter: grayscale(1) brightness(0.2);">
            </div>
            <h1 class="paper-title"><?= htmlspecialchars($doc['file_name']) ?></h1>
            <div class="paper-meta">
                Client: <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?> (<?= htmlspecialchars($doc['email']) ?>)<br>
                Document Type: <?= htmlspecialchars($doc['document_type']) ?> &bull; Created: <?= date('M j, Y', strtotime($doc['uploaded_at'])) ?>
            </div>
        </div>
        
        <div class="paper-body">
            <?= $doc['document_body'] ?>
        </div>
        
        <div class="signature-block">
            <?php if ($doc['requires_signature']): ?>
                <?php if ($doc['is_signed']): ?>
                    <div class="alert alert-success border-0 p-3 mb-0 text-center">
                        <h5 class="font-weight-bold mb-1"><i class="fa fa-check-circle mr-2"></i>Cryptographically Signed</h5>
                        <p class="small mb-0 text-muted" style="font-size:12px;">
                            Signed by Client: <strong><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></strong><br>
                            Timestamp: <?= date('M j, Y h:i A', strtotime($doc['signed_at'])) ?> (GMT)<br>
                            IP Address: <?= htmlspecialchars($doc['signature_ip']) ?> &bull; Hash Verification: Secure SHA-256
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning border-0 p-3 mb-0 text-center text-dark">
                        <h6 class="font-weight-bold mb-1"><i class="fa fa-clock mr-2"></i>Awaiting Secure Cryptographic Signature</h6>
                        <p class="small mb-0" style="font-size:12px;">The client has not yet signed this document.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-muted small mt-4">
                    This document is reference only and does not require a signature.
                </div>
            <?php endif; ?>
        </div>

        <div class="paper-footer" style="margin-top: 60px; border-top: 2px solid #fecc56; padding-top: 20px; text-align: center; font-size: 0.85rem; color: #888; font-family: 'Montserrat', sans-serif;">
            <strong style="color: #111;">IFW Global Secure Document Vault</strong><br>
            This document is generated, tracked, and securely vaulted on the IFW Global Platform.<br>
            Unauthorized distribution or alteration is strictly prohibited.
        </div>
    </div>
</div>

</body>
</html>

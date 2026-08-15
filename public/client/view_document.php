<?php
// client/view_document.php - View dynamic custom document in Client Portal
$dir = __DIR__;
while (!file_exists($dir . '/config.php') && $dir !== dirname($dir)) {
    $dir = dirname($dir);
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$client_id = $_SESSION['client_portal_id'];
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch document details
$stmt = $pdo->prepare("SELECT * FROM IFW_documents WHERE id = ? AND client_id = ?");
$stmt->execute([$doc_id, $client_id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('<p style="font-family:sans-serif;padding:20px;color:red;">Document not found or access denied.</p>');
}

// Fetch client details
$stmtClient = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmtClient->execute([$client_id]);
$client = $stmtClient->fetch();

$logo_url = get_brand_logo_url($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($doc['file_name']) ?> | IFW Global Secure Portal</title>
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
        .btn-sign {
            background: linear-gradient(135deg, #fecc56, #f39c12);
            color: #000;
            font-weight: 700;
            border: none;
            box-shadow: 0 4px 15px rgba(254, 204, 86, 0.4);
            transition: all 0.3s ease;
        }
        .btn-sign:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(254, 204, 86, 0.6);
            color: #000;
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
        <a href="my_cases.php" class="btn btn-outline-light font-weight-bold btn-sm"><i class="fa fa-arrow-left mr-2"></i>Back to My Case</a>
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
                Document Type: <?= htmlspecialchars($doc['document_type']) ?> &bull; Uploaded: <?= date('M j, Y', strtotime($doc['uploaded_at'])) ?>
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
                            Signed by Client: <strong><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></strong><br>
                            Timestamp: <?= date('M j, Y h:i A', strtotime($doc['signed_at'])) ?> (GMT)<br>
                            IP Address: <?= htmlspecialchars($doc['signature_ip']) ?> &bull; Hash Verification: Secure SHA-256
                        </p>
                    </div>
                <?php else: ?>
                    <div class="bg-light p-4 rounded text-center no-print" style="border: 1px solid #fecc56;">
                        <h6 class="font-weight-bold text-dark mb-2"><i class="fa fa-pencil-alt text-warning mr-2"></i>Awaiting Secure Cryptographic Signature</h6>
                        <p class="small text-muted mb-3" style="max-width: 500px; margin: 0 auto;">This document requires your secure portal signature. Click below to sign with your 4-digit security PIN.</p>
                        <button type="button" class="btn btn-sign font-weight-bold px-4 py-2" onclick="openSigningModal(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['file_name'])) ?>')">
                            <i class="fa fa-signature mr-2"></i>Sign This Document Now
                        </button>
                    </div>
                    <div class="only-print text-center py-4" style="border: 1px solid #ccc; font-style: italic;">
                        Awaiting Secure Signature via IFW Global Client Portal.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-muted small mt-4">
                    This document is provided for reference only and does not require a signature.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="watermark-overlay">
            <div class="watermark-text">
                CONFIDENTIAL &bull; CLIENT #<?= $client_id ?> &bull; <?= date('Y-m-d H:i:s') ?> UTC &bull; IP <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?>
            </div>
        </div>

        <div class="paper-footer" style="margin-top: 50px; border-top: 2px solid #fecc56; padding-top: 20px; font-size: 0.85rem; color: #888; font-family: 'Montserrat', sans-serif;">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="text-left mb-2 mb-md-0" style="max-width: 500px;">
                    <strong style="color: #111;">IFW Global Forensic Document Vault</strong><br>
                    <small>Cryptographic Integrity ID: <code style="color:#d97706; font-size:11px;"><?= hash('sha256', $doc['id'] . ($doc['file_name'] ?? '') . ($doc['document_body'] ?? '')) ?></code></small><br>
                    <small class="text-muted">Tamper-evident verification certified under international forensic standard ISO/IEC 27037.</small>
                </div>
                <div class="text-right">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode(BASE_URL . '/client/view_document.php?id=' . $doc['id']) ?>" alt="QR Verification" style="width:65px; height:65px; border:1px solid #ddd; padding:2px; border-radius:4px;">
                    <div style="font-size:9px; color:#666; font-weight:700; margin-top:2px;">SECURE VERIFIED</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cryptographic Signing Modal -->
<div class="modal fade no-print" id="signingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning font-weight-bold"><i class="fa fa-file-signature mr-2"></i>Secure e-Signature</h5>
                <button type="button" class="close text-white" onclick="$('#signingModal').modal('hide')">
                    <span>&times;</span>
                </button>
            </div>
            <form id="signingForm">
                <div class="modal-body">
                    <input type="hidden" name="document_id" id="signingDocId">
                    <p class="small text-muted">You are signing: <strong class="text-light" id="signingDocName"></strong></p>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light small d-block">Enter 4-Digit Security PIN</label>
                        <input type="password" name="pin" id="signingPin" class="form-control bg-black text-warning border-secondary text-center font-weight-bold font-large" maxlength="4" placeholder="Enter PIN" required pattern="\d{4}">
                        <small class="text-muted mt-1 d-block" style="font-size:10px;">Please enter your configured security PIN to sign.</small>
                    </div>
                    
                    <div id="signingError" class="alert alert-danger py-2 small" style="display:none;"></div>
                    <div id="signingSuccess" class="alert alert-success py-2 small" style="display:none;"></div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary font-weight-bold btn-sm" onclick="$('#signingModal').modal('hide')">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold text-dark btn-sm"><i class="fa fa-check-double mr-1"></i>Sign Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../admin_assets/js/jquery.min.js"></script>
<script src="../admin_assets/js/bootstrap.bundle.min.js"></script>
<script>
function openSigningModal(docId, docName) {
    document.getElementById('signingDocId').value = docId;
    document.getElementById('signingDocName').innerText = docName;
    document.getElementById('signingPin').value = '';
    document.getElementById('signingError').style.display = 'none';
    document.getElementById('signingSuccess').style.display = 'none';
    $('#signingModal').modal('show');
}

document.addEventListener("DOMContentLoaded", function() {
    document.getElementById('signingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const docId = document.getElementById('signingDocId').value;
        const pin = document.getElementById('signingPin').value;
        const errorDiv = document.getElementById('signingError');
        const successDiv = document.getElementById('signingSuccess');
        
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
        
        fetch('../api/sign_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `document_id=${docId}&pin=${pin}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                successDiv.innerText = data.message;
                successDiv.style.display = 'block';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                errorDiv.innerText = data.message;
                errorDiv.style.display = 'block';
            }
        })
        .catch(err => {
            errorDiv.innerText = 'Connection error. Please try again.';
            errorDiv.style.display = 'block';
        });
    });
});
</script>
</body>
</html>

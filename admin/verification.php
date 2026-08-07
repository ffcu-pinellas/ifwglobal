<?php
// admin/verification.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch KYC documents
$stmt = $pdo->query("
    SELECT k.*, c.first_name, c.last_name, c.email 
    FROM IFW_kyc_documents k 
    JOIN IFW_clients c ON k.client_id = c.id 
    ORDER BY k.uploaded_at DESC
");
$documents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', '1') == '0'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification Center - IFW Global</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #333333; }
        .sidebar { min-height: 100vh; background: rgba(31, 27, 28, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); color: white; padding-top: 20px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); border-right: 1px solid rgba(255,255,255,0.05); }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 15px 20px; font-weight: 500; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { color: #1f1b1c; background: linear-gradient(90deg, #fecc56, #f3c14b); border-left: 4px solid #fff; font-weight: 600; box-shadow: 0 2px 10px rgba(254,204,86,0.2); }
        .content-area { padding: 40px; }
        
        .card { border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 30px; background: rgba(255,255,255,0.98); }
        .card-header { background-color: #ffffff; border-bottom: 1px solid #edf2f9; font-weight: 600; font-size: 1.1rem; padding: 20px 25px; color: #1f1b1c; }
        .table th { border-bottom: 1px solid #edf2f9; color: #6c757d; font-weight: 600; background: #f8f9fa; padding: 15px; }
        .table td { border-bottom: 1px solid #edf2f9; vertical-align: middle; padding: 15px; }
        .btn-primary { background: linear-gradient(135deg, #1f1b1c, #2c2728); border: none; color: #fecc56; border-radius: 20px; padding: 8px 20px; font-weight: 600; }
        .btn-success { border-radius: 20px; padding: 6px 15px; font-size: 0.85rem; }
        .btn-danger { border-radius: 20px; padding: 6px 15px; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar px-0">
            <h5 class="text-center mb-4 text-white">IFW Global</h5>
            <a href="index.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
            <a href="submissions.php"><i class="bi bi-inbox me-2"></i> Submissions</a>
            <a href="client_manager.php"><i class="bi bi-people me-2"></i> Client Manager</a>
            <a href="verification.php" class="active"><i class="bi bi-shield-check me-2"></i> KYC Center</a>
            <a href="chat.php"><i class="bi bi-chat-dots me-2"></i> Live Chat</a>
            <a href="settings.php"><i class="bi bi-gear me-2"></i> Global Settings</a>
            <a href="logout.php" class="mt-auto text-danger"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-10 content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-shield-check text-warning me-2"></i> KYC Verification Center</h2>
            </div>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Identity Documents</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Document Type</th>
                                    <th>Date Uploaded</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No KYC documents uploaded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($doc['email']) ?> (ID: <?= $doc['client_id'] ?>)</div>
                                            </td>
                                            <td><?= htmlspecialchars($doc['document_type']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($doc['uploaded_at'])) ?></td>
                                            <td>
                                                <?php if ($doc['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending</span>
                                                <?php elseif ($doc['status'] == 'approved'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill me-1"><i class="bi bi-eye"></i> View</a>
                                                <?php if ($doc['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success review-btn" data-id="<?= $doc['id'] ?>" data-status="approved"><i class="bi bi-check2"></i> Approve</button>
                                                    <button class="btn btn-sm btn-danger review-btn" data-id="<?= $doc['id'] ?>" data-status="rejected"><i class="bi bi-x-lg"></i> Reject</button>
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
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.review-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const status = this.dataset.status;
        
        let feedback = '';
        if (status === 'rejected') {
            feedback = prompt("Please provide a reason for rejection (e.g. Blurry image, expired ID):");
            if (feedback === null) return;
        }

        const formData = new FormData();
        formData.append('document_id', id);
        formData.append('status', status);
        formData.append('feedback', feedback);

        fetch('../api/kyc_review.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        });
    });
});
</script>
</body>
</html>






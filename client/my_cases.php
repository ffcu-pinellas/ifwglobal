<?php
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
$_SESSION['role'] = 'client';

// Fetch Vault Documents
$vault_stmt = $pdo->prepare("SELECT * FROM IFW_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
$vault_stmt->execute([$client_id]);
$vault_docs = $vault_stmt->fetchAll();
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- PAGE CONTENT -->
<div class="row">
    <div class="col-12 mb-4">
        <h4 class="text-dark">My Cases & Vault</h4>
        <p class="text-muted">Manage your case documents securely.</p>
    </div>

    <!-- Vault Tab -->
    <div class="col-12">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="material-icons text-success" style="vertical-align: text-bottom;">shield</i> Encrypted Document Vault</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Signature Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vault_docs)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Your secure vault is empty.</td></tr>
                            <?php else: ?>
                                <?php foreach ($vault_docs as $doc): ?>
                                    <tr>
                                        <td>
                                            <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-decoration-none fw-bold text-dark">
                                                <i class="material-icons text-danger" style="vertical-align: text-bottom;">picture_as_pdf</i> <?php echo htmlspecialchars($doc['file_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['document_type'] ?? 'Standard'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?></td>
                                        <td>
                                            <?php if ($doc['requires_signature']): ?>
                                                <?php if ($doc['is_signed']): ?>
                                                    <span class="badge badge-success"><i class="material-icons" style="font-size: 12px;">check_circle</i> Signed (<?php echo date('M j', strtotime($doc['signed_at'])); ?>)</span>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill sign-doc-btn" data-id="<?php echo $doc['id']; ?>" data-name="<?php echo htmlspecialchars($doc['file_name']); ?>" data-toggle="modal" data-target="#signModal">
                                                        <i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">edit</i> Sign Document
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Not Required</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 mx-3 mb-3 p-3 bg-light rounded text-muted small">
                    <i class="material-icons text-info" style="font-size: 16px; vertical-align: text-bottom;">info</i> Documents uploaded here are stored securely and only accessible by authorized case agents.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- E-Signature Modal -->
<div class="modal fade" id="signModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-primary"><i class="material-icons text-danger" style="vertical-align: text-bottom;">edit</i> Digital E-Signature</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <i class="material-icons text-muted" style="font-size: 3rem;">lock</i>
                <p class="mt-3 mb-1">You are about to cryptographically sign:</p>
                <h6 class="fw-bold text-primary mb-4" id="sign-doc-name">Document Name</h6>
                <p class="small text-muted text-left bg-light p-3 rounded">By entering your 4-digit PIN below, you agree to digitally sign this document. Your IP address and a timestamp will be permanently logged as proof of cryptographic signature.</p>
                
                <input type="password" id="sign_pin" class="form-control form-control-lg text-center mb-3" placeholder="Enter 4-Digit PIN" maxlength="4" style="letter-spacing: 10px; font-weight: bold;">
                <input type="hidden" id="sign_doc_id">
                
                <div id="sign-alert" class="alert d-none small"></div>
                <button type="button" class="btn btn-danger w-100 rounded-pill fw-bold" id="confirm-sign-btn">Confirm E-Signature</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

<script>
document.querySelectorAll('.sign-doc-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('sign_doc_id').value = this.dataset.id;
        document.getElementById('sign-doc-name').textContent = this.dataset.name;
    });
});

document.getElementById('confirm-sign-btn')?.addEventListener('click', function() {
    const docId = document.getElementById('sign_doc_id').value;
    const pin = document.getElementById('sign_pin').value;
    const alertBox = document.getElementById('sign-alert');
    
    if (pin.length !== 4) {
        alertBox.className = 'alert alert-danger d-block small';
        alertBox.textContent = 'Please enter your 4-digit PIN.';
        return;
    }
    
    this.disabled = true;
    this.textContent = 'Verifying...';
    
    const formData = new FormData();
    formData.append('document_id', docId);
    formData.append('pin', pin);
    
    fetch('../api/sign_document.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert alert-success d-block small';
            alertBox.textContent = data.message;
            setTimeout(() => location.reload(), 1500);
        } else {
            alertBox.className = 'alert alert-danger d-block small';
            alertBox.textContent = data.message;
            this.disabled = false;
            this.textContent = 'Confirm E-Signature';
        }
    }).catch(err => {
        alertBox.className = 'alert alert-danger d-block small';
        alertBox.textContent = 'An error occurred.';
        this.disabled = false;
        this.textContent = 'Confirm E-Signature';
    });
});
</script>





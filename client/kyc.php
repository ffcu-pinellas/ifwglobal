<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
// client/kyc.php
require_once '../config.php';
require_once '../includes/functions.php';

// Ensure user is logged in as a client
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit;
}
$client_id = $_SESSION['client_id'];

// Check if they already submitted
$submission = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM IFW_kyc_submissions WHERE client_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$client_id]);
    $submission = $stmt->fetch();
} catch (Exception $e) {}

// Fetch active KYC fields
$fields = [];
try {
    $fields = $pdo->query("SELECT * FROM IFW_kyc_fields ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kyc_data = [];
    $upload_dir = '../uploads/kyc/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    foreach ($fields as $field) {
        $name = $field['field_name'];
        if ($field['field_type'] === 'file') {
            if (isset($_FILES[$name])) {
                if (is_array($_FILES[$name]['name'])) {
                    $uploaded_paths = [];
                    foreach ($_FILES[$name]['name'] as $key => $filename) {
                        if ($_FILES[$name]['error'][$key] == 0) {
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            $new_filename = 'kyc_' . $client_id . '_' . $name . '_' . time() . '_' . $key . '.' . $ext;
                            $filepath = $upload_dir . $new_filename;
                            move_uploaded_file($_FILES[$name]['tmp_name'][$key], $filepath);
                            $uploaded_paths[] = 'uploads/kyc/' . $new_filename;
                        }
                    }
                    if (!empty($uploaded_paths)) {
                        $kyc_data[$name] = implode(', ', $uploaded_paths);
                    }
                } else {
                    if ($_FILES[$name]['error'] == 0) {
                        $ext = pathinfo($_FILES[$name]['name'], PATHINFO_EXTENSION);
                        $filename = 'kyc_' . $client_id . '_' . $name . '_' . time() . '.' . $ext;
                        $filepath = $upload_dir . $filename;
                        move_uploaded_file($_FILES[$name]['tmp_name'], $filepath);
                        $kyc_data[$name] = 'uploads/kyc/' . $filename;
                    }
                }
            }
        } else {
            $kyc_data[$name] = $_POST[$name] ?? '';
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO IFW_kyc_submissions (client_id, submission_data) VALUES (?, ?)");
        $stmt->execute([$client_id, json_encode($kyc_data)]);
        header("Location: kyc.php?success=1");
        exit;
    } catch (Exception $e) {
        $error = "Failed to submit KYC data.";
    }
}

require_once '../includes/client_header.php';
require_once '../includes/client_sidebar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4">
            <h3 class="text-warning font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Identity Verification (KYC)</h3>
            <p class="text-muted">In compliance with global AML/CFT regulations, we require identity verification before proceeding with active recovery asset transfers.</p>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success font-weight-bold shadow-sm p-4 text-center rounded" style="border: 1px solid #28a745;">
                <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                <h4 class="text-success font-weight-bold">Submission Successful</h4>
                Your identity verification documents have been securely submitted to our compliance team. Review typically takes 1-2 business days.
            </div>
        <?php endif; ?>
        
        <?php if ($submission && $submission['status'] === 'Approved'): ?>
            <div class="alert bg-dark border-success text-center py-5 shadow-lg rounded">
                <i class="fas fa-user-check text-success fa-4x mb-3 d-block"></i>
                <h3 class="text-success font-weight-bold">Identity Verified</h3>
                <p class="text-light mb-0">Your account identity has been fully verified and approved by the compliance team.</p>
            </div>
        <?php elseif ($submission && $submission['status'] === 'Pending' && !isset($_GET['success'])): ?>
            <div class="alert bg-dark border-warning text-center py-5 shadow-lg rounded">
                <i class="fas fa-clock text-warning fa-4x mb-3 d-block"></i>
                <h3 class="text-warning font-weight-bold">Verification Pending</h3>
                <p class="text-light mb-0">Your documents are currently under review by our compliance team.</p>
            </div>
        <?php elseif (!isset($_GET['success'])): ?>
            <?php if ($submission && $submission['status'] === 'Rejected'): ?>
                <div class="alert bg-dark border-danger p-4 mb-4 shadow-lg rounded">
                    <h5 class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Previous Submission Rejected</h5>
                    <p class="text-light mb-0"><strong>Reason:</strong> <?= htmlspecialchars($submission['rejection_reason'] ?: 'Documents did not meet requirements. Please resubmit.') ?></p>
                </div>
            <?php endif; ?>
        
            <div class="card bg-dark border-warning shadow-lg">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
                    <i class="fas fa-upload mr-2"></i>Submit Required Information
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <?php foreach ($fields as $field): ?>
                                <div class="col-md-<?= $field['field_type'] === 'file' ? '12' : '6' ?> mb-3">
                                    <label class="text-white font-weight-bold"><?= htmlspecialchars($field['field_label']) ?> <?= $field['is_required'] ? '<span class="text-danger">*</span>' : '' ?></label>
                                    
                                    <?php if ($field['field_type'] === 'file'): ?>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="<?= htmlspecialchars($field['field_name']) ?>[]" id="<?= htmlspecialchars($field['field_name']) ?>" <?= $field['is_required'] ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf" multiple>
                                            <label class="custom-file-label border-secondary bg-dark text-muted" for="<?= htmlspecialchars($field['field_name']) ?>">Choose files (JPG, PNG, PDF)...</label>
                                        </div>
                                    <?php else: ?>
                                        <input type="<?= $field['field_type'] === 'date' ? 'date' : 'text' ?>" name="<?= htmlspecialchars($field['field_name']) ?>" class="form-control bg-dark text-white border-secondary" <?= $field['is_required'] ? 'required' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 border-top border-secondary pt-3 text-right">
                            <button type="submit" name="submit_kyc" class="btn btn-warning font-weight-bold text-dark px-5 py-2 shadow"><i class="fas fa-shield-check mr-2"></i> Securely Submit Documents</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            // Update custom file label on select
            document.querySelectorAll('.custom-file-input').forEach(function(input) {
                input.addEventListener('change', function(e) {
                    var fileCount = e.target.files.length;
                    var label = e.target.nextElementSibling;
                    if (fileCount > 1) {
                        label.innerText = fileCount + ' files selected';
                    } else if (fileCount === 1) {
                        label.innerText = e.target.files[0].name;
                    } else {
                        label.innerText = 'Choose files (JPG, PNG, PDF)...';
                    }
                });
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/client_footer.php'; ?>
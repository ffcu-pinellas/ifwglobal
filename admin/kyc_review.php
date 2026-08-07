<?php
// admin/kyc_review.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$admin_id = $_SESSION['admin_id'];

// Handle Actions (Approve, Reject, Update Fields)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_kyc' || $_POST['action'] === 'reject_kyc') {
        $sub_id = (int)$_POST['submission_id'];
        $status = $_POST['action'] === 'approve_kyc' ? 'Approved' : 'Rejected';
        $reason = $_POST['reason'] ?? null;
        
        $stmt = $pdo->prepare("UPDATE IFW_kyc_submissions SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, rejection_reason = ? WHERE id = ?");
        $stmt->execute([$status, $admin_id, $reason, $sub_id]);
        
        log_audit_action($pdo, $admin_id, strtoupper($status) . '_KYC', "KYC Submission #$sub_id $status");
        header("Location: kyc_review.php?success=1");
        exit;
    }
    
    if ($_POST['action'] === 'add_field') {
        $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($_POST['field_name'])));
        $label = trim($_POST['field_label']);
        $type = $_POST['field_type'];
        $req = isset($_POST['is_required']) ? 1 : 0;
        
        if (!empty($name) && !empty($label)) {
            $stmt = $pdo->prepare("INSERT INTO IFW_kyc_fields (field_name, field_label, field_type, is_required) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $label, $type, $req]);
            header("Location: kyc_review.php?field_added=1");
            exit;
        }
    }
    
    if ($_POST['action'] === 'delete_field') {
        $stmt = $pdo->prepare("DELETE FROM IFW_kyc_fields WHERE id = ?");
        $stmt->execute([(int)$_POST['field_id']]);
        header("Location: kyc_review.php?field_deleted=1");
        exit;
    }
}

// Fetch submissions
$submissions = [];
try {
    $submissions = $pdo->query("SELECT s.*, c.first_name, c.last_name, c.email FROM IFW_kyc_submissions s JOIN IFW_clients c ON s.client_id = c.id ORDER BY s.submitted_at DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch fields
$fields = [];
try {
    $fields = $pdo->query("SELECT * FROM IFW_kyc_fields ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-id-card mr-2"></i>KYC / Identity Verification</h3>
        <p class="text-muted mb-0">Review submitted client documents and configure dynamic form fields.</p>
    </div>
    <button type="button" class="btn btn-outline-warning font-weight-bold" data-toggle="modal" data-target="#fieldsModal">
        <i class="fas fa-sliders-h mr-1"></i> Configure KYC Form Fields
    </button>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success font-weight-bold"><i class="fas fa-check-circle mr-2"></i>KYC Status Updated.</div>
<?php endif; ?>

<div class="card shadow-lg bg-dark border-secondary">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
        <i class="fas fa-inbox mr-2"></i>Submissions Queue
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning">
                        <th>Sub ID</th>
                        <th>Client</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr><td colspan="5" class="text-center p-4 text-muted">No KYC submissions found.</td></tr>
                    <?php else: ?>
                        <?php foreach($submissions as $sub): ?>
                            <tr>
                                <td>#<?= $sub['id'] ?></td>
                                <td><strong class="text-white"><?= htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($sub['email']) ?></small></td>
                                <td><?= date('M j, Y H:i', strtotime($sub['submitted_at'])) ?></td>
                                <td>
                                    <?php if($sub['status'] === 'Approved'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Approved</span>
                                    <?php elseif($sub['status'] === 'Rejected'): ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-clock mr-1"></i>Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white" data-toggle="modal" data-target="#viewModal_<?= $sub['id'] ?>">Review Documents</button>
                                </td>
                            </tr>
                            
                            <!-- Review Modal -->
                            <div class="modal fade" id="viewModal_<?= $sub['id'] ?>" tabindex="-1">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content bg-dark text-white border-warning">
                                  <div class="modal-header border-secondary">
                                    <h5 class="modal-title text-warning font-weight-bold">Review KYC: <?= htmlspecialchars($sub['first_name']) ?></h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                  </div>
                                  <div class="modal-body">
                                      <?php 
                                        $data = json_decode($sub['submission_data'], true) ?: []; 
                                      ?>
                                      <div class="row">
                                          <?php foreach($data as $key => $val): ?>
                                              <div class="col-md-6 mb-3">
                                                  <label class="text-muted small text-uppercase mb-1"><?= htmlspecialchars(str_replace('_', ' ', $key)) ?></label>
                                                  <?php 
                                                    // Handle multiple files if comma-separated
                                                    $files = explode(', ', $val);
                                                    $isFileField = false;
                                                    foreach($files as $f) {
                                                        if (preg_match('/\.(jpg|jpeg|png|gif|pdf)$/i', $f)) $isFileField = true;
                                                    }
                                                  ?>
                                                  
                                                  <?php if ($isFileField): ?>
                                                      <?php foreach($files as $f): ?>
                                                          <div class="border border-secondary rounded p-2 text-center bg-black mb-2">
                                                              <?php if (preg_match('/\.pdf$/i', $f)): ?>
                                                                  <i class="fas fa-file-pdf text-danger fa-3x mb-2 d-block mt-2"></i>
                                                                  <a href="../<?= htmlspecialchars($f) ?>" target="_blank" class="btn btn-sm btn-warning text-dark">Download PDF</a>
                                                              <?php else: ?>
                                                                  <a href="../<?= htmlspecialchars($f) ?>" target="_blank">
                                                                    <img src="../<?= htmlspecialchars($f) ?>" style="max-height: 150px; max-width: 100%; border-radius: 4px;" alt="Document">
                                                                  </a>
                                                                  <br><a href="../<?= htmlspecialchars($f) ?>" download class="btn btn-sm btn-outline-warning mt-2"><i class="fas fa-download mr-1"></i> Download File</a>
                                                              <?php endif; ?>
                                                          </div>
                                                      <?php endforeach; ?>
                                                  <?php else: ?>
                                                      <div class="font-weight-bold text-light" style="font-size: 1.1rem;"><?= htmlspecialchars($val) ?></div>
                                                  <?php endif; ?>
                                              </div>
                                          <?php endforeach; ?>
                                      </div>
                                      
                                      <?php if($sub['status'] === 'Pending'): ?>
                                          <hr class="border-secondary">
                                          <form method="POST" class="d-flex justify-content-between">
                                              <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                              <div>
                                                  <input type="text" name="reason" class="form-control bg-dark text-white border-secondary mb-2" placeholder="Rejection reason (optional)" style="width:300px;">
                                                  <button type="submit" name="action" value="reject_kyc" class="btn btn-danger font-weight-bold shadow-sm"><i class="fas fa-times mr-1"></i> Reject</button>
                                              </div>
                                              <button type="submit" name="action" value="approve_kyc" class="btn btn-success font-weight-bold shadow-lg" style="height: 45px; align-self: flex-end;"><i class="fas fa-check mr-1"></i> Approve Verification</button>
                                          </form>
                                      <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fields Config Modal -->
<div class="modal fade" id="fieldsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold">Dynamic KYC Fields Configuration</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-4">Add or remove fields that clients must fill out during Identity Verification. The client portal will automatically generate the form based on these fields.</p>
        
        <table class="table table-dark table-sm table-bordered">
            <thead><tr class="text-warning"><th>Field Label</th><th>Type</th><th>Required</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($fields as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['field_label']) ?> <small class="text-muted">(<?= htmlspecialchars($f['field_name']) ?>)</small></td>
                        <td><?= strtoupper($f['field_type']) ?></td>
                        <td><?= $f['is_required'] ? '<i class="fas fa-check text-success"></i>' : '-' ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this field?');">
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-danger py-0 px-2"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <hr class="border-secondary my-4">
        <h6 class="text-warning">Add New Field</h6>
        <form method="POST" class="row">
            <input type="hidden" name="action" value="add_field">
            <div class="col-md-4 mb-2">
                <input type="text" name="field_label" class="form-control bg-dark text-white border-secondary" placeholder="Label (e.g. Utility Bill)" required>
            </div>
            <div class="col-md-4 mb-2">
                <input type="text" name="field_name" class="form-control bg-dark text-white border-secondary" placeholder="DB Name (e.g. utility_bill)" required>
            </div>
            <div class="col-md-2 mb-2">
                <select name="field_type" class="form-control bg-dark text-white border-secondary">
                    <option value="text">Text</option>
                    <option value="date">Date</option>
                    <option value="file">File Upload</option>
                </select>
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-center">
                <div class="custom-control custom-checkbox mr-2">
                    <input type="checkbox" class="custom-control-input" id="isreq" name="is_required" checked>
                    <label class="custom-control-label" for="isreq">Req</label>
                </div>
                <button type="submit" class="btn btn-warning btn-sm text-dark font-weight-bold">Add</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

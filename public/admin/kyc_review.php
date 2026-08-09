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
        $options = trim($_POST['field_options'] ?? '');
        $req = isset($_POST['is_required']) ? 1 : 0;
        $order = (int)($_POST['sort_order'] ?? 99);
        
        if (!empty($name) && !empty($label)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO IFW_kyc_fields (field_name, field_label, field_type, field_options, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $label, $type, $options ?: null, $req, $order]);
                header("Location: kyc_review.php?field_added=1");
                exit;
            } catch (Exception $e) {
                // If field_options or sort_order columns don't exist, try without them
                $stmt = $pdo->prepare("INSERT INTO IFW_kyc_fields (field_name, field_label, field_type, is_required) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $label, $type, $req]);
                header("Location: kyc_review.php?field_added=1");
                exit;
            }
        }
    }

    if ($_POST['action'] === 'edit_field') {
        $id = (int)$_POST['field_id'];
        $label = trim($_POST['field_label']);
        $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($_POST['field_name'])));
        $type = $_POST['field_type'];
        $options = trim($_POST['field_options'] ?? '');
        $req = isset($_POST['is_required']) ? 1 : 0;
        $order = (int)($_POST['sort_order'] ?? 1);

        if ($id > 0 && !empty($label)) {
            try {
                $stmt = $pdo->prepare("UPDATE IFW_kyc_fields SET field_label = ?, field_name = ?, field_type = ?, field_options = ?, is_required = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$label, $name, $type, $options ?: null, $req, $order, $id]);
            } catch (Exception $e) {
                // Fallback without newer columns
                $stmt = $pdo->prepare("UPDATE IFW_kyc_fields SET field_label = ?, field_name = ?, field_type = ?, is_required = ? WHERE id = ?");
                $stmt->execute([$label, $name, $type, $req, $id]);
            }
            header("Location: kyc_review.php?field_updated=1");
            exit;
        }
    }
    
    if ($_POST['action'] === 'delete_field') {
        $stmt = $pdo->prepare("DELETE FROM IFW_kyc_fields WHERE id = ?");
        $stmt->execute([(int)$_POST['field_id']]);
        header("Location: kyc_review.php?field_deleted=1");
        exit;
    }

    if ($_POST['action'] === 'toggle_required') {
        $id = (int)$_POST['field_id'];
        $req = (int)$_POST['is_required'];
        $stmt = $pdo->prepare("UPDATE IFW_kyc_fields SET is_required = ? WHERE id = ?");
        $stmt->execute([$req, $id]);
        header("Location: kyc_review.php?field_updated=1");
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
<?php if(isset($_GET['field_added']) || isset($_GET['field_updated']) || isset($_GET['field_deleted'])): ?>
    <div class="alert alert-info font-weight-bold"><i class="fas fa-check-circle mr-2"></i>KYC field configuration saved.</div>
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
                                                                  <a href="<?= BASE_URL . '/' . htmlspecialchars($f) ?>" target="_blank" class="btn btn-sm btn-warning text-dark">Download PDF</a>
                                                              <?php else: ?>
                                                                  <a href="<?= BASE_URL . '/' . htmlspecialchars($f) ?>" target="_blank">
                                                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($f) ?>" style="max-height: 150px; max-width: 100%; border-radius: 4px;" alt="Document">
                                                                  </a>
                                                                  <br><a href="<?= BASE_URL . '/' . htmlspecialchars($f) ?>" download class="btn btn-sm btn-outline-warning mt-2"><i class="fas fa-download mr-1"></i> Download File</a>
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
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-sliders-h mr-2"></i>Dynamic KYC Fields Configuration</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">Add or remove fields that clients must fill out during Identity Verification. The client portal will automatically generate the form based on these fields. Clients can modify their submissions as long as admin or assigned staff hasn't approved. If rejected, client has to resubmit.</p>
        
        <table class="table table-dark table-sm table-bordered mb-4">
            <thead><tr class="text-warning"><th>#</th><th>Field Label</th><th>DB Name</th><th>Type</th><th>Options</th><th>Required</th><th style="min-width:120px;">Action</th></tr></thead>
            <tbody>
                <?php if (empty($fields)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No fields configured yet.</td></tr>
                <?php endif; ?>
                <?php foreach($fields as $f): ?>
                    <tr>
                        <td><?= $f['sort_order'] ?? $f['id'] ?></td>
                        <td><strong><?= htmlspecialchars($f['field_label']) ?></strong></td>
                        <td><code><?= htmlspecialchars($f['field_name']) ?></code></td>
                        <td><span class="badge badge-secondary"><?= strtoupper($f['field_type']) ?></span></td>
                        <td><small class="text-muted"><?= htmlspecialchars($f['field_options'] ?? '') ?></small></td>
                        <td>
                            <?php if ($f['is_required']): ?>
                                <span class="badge badge-danger">Required</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 mr-1" 
                                data-toggle="modal" data-target="#editKycFieldModalMain"
                                onclick="openEditModal(<?= $f['id'] ?>, <?= htmlspecialchars(json_encode($f['field_label'])) ?>, <?= htmlspecialchars(json_encode($f['field_name'])) ?>, '<?= $f['field_type'] ?>', <?= htmlspecialchars(json_encode($f['field_options'] ?? '')) ?>, <?= $f['is_required'] ?>, <?= $f['sort_order'] ?? $f['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field?');">
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <hr class="border-secondary">
        <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-plus-circle mr-2"></i>Add New KYC Field</h6>
        <form method="POST">
            <input type="hidden" name="action" value="add_field">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="text-muted small">Field Label <span class="text-danger">*</span></label>
                    <input type="text" name="field_label" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Utility Bill" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="text-muted small">DB Name <span class="text-danger">*</span></label>
                    <input type="text" name="field_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. utility_bill" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="text-muted small">Type</label>
                    <select name="field_type" class="form-control bg-dark text-white border-secondary" id="addFieldType" onchange="toggleOptions(this, 'addOptions')">
                        <option value="text">Text</option>
                        <option value="date">Date</option>
                        <option value="file">File Upload</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Dropdown</option>
                        <option value="number">Number</option>
                        <option value="tel">Phone</option>
                        <option value="email">Email</option>
                        <option value="country">Country Select</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2" id="addOptions" style="display:none;">
                    <label class="text-muted small">Options (comma separated)</label>
                    <input type="text" name="field_options" class="form-control bg-dark text-white border-secondary" placeholder="Yes, No, N/A">
                </div>
                <div class="col-md-1 mb-2">
                    <label class="text-muted small">Order</label>
                    <input type="number" name="sort_order" class="form-control bg-dark text-white border-secondary" value="<?= count($fields) + 1 ?>">
                </div>
                <div class="col-md-2 mb-2 d-flex flex-column justify-content-end">
                    <div class="mb-2 d-flex align-items-center" style="gap: 8px;">
                        <input type="checkbox" id="addIsReq" name="is_required" value="1" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #fecc56;">
                        <label class="text-light mb-0" for="addIsReq" style="cursor: pointer; font-weight: 500;">Required</label>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm text-dark font-weight-bold"><i class="fas fa-plus mr-1"></i>Add Field</button>
                </div>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit KYC Field Modal (Single, populated via JS) -->
<div class="modal fade" id="editKycFieldModalMain" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-info">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-info font-weight-bold"><i class="fas fa-edit mr-2"></i>Edit KYC Field</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="edit_field">
            <input type="hidden" name="field_id" id="editFieldId">
            <div class="form-group mb-3">
                <label class="font-weight-bold text-light">Field Label</label>
                <input type="text" name="field_label" id="editFieldLabel" class="form-control bg-secondary text-white border-0" required>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold text-light">Field Identifier (DB Name)</label>
                <input type="text" name="field_name" id="editFieldName" class="form-control bg-secondary text-white border-0" required>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold text-light">Field Type</label>
                <select name="field_type" id="editFieldType" class="form-control bg-secondary text-white border-0" onchange="toggleOptions(this, 'editOptionsRow')">
                    <option value="text">Text</option>
                    <option value="date">Date</option>
                    <option value="file">File Upload</option>
                    <option value="textarea">Textarea</option>
                    <option value="select">Dropdown</option>
                    <option value="number">Number</option>
                    <option value="tel">Phone</option>
                    <option value="email">Email</option>
                    <option value="country">Country Select</option>
                </select>
            </div>
            <div class="form-group mb-3" id="editOptionsRow" style="display:none;">
                <label class="font-weight-bold text-light">Dropdown Options (comma separated)</label>
                <input type="text" name="field_options" id="editFieldOptions" class="form-control bg-secondary text-white border-0" placeholder="Option 1, Option 2, Option 3">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light">Display Order</label>
                        <input type="number" name="sort_order" id="editFieldOrder" class="form-control bg-secondary text-white border-0" value="1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-light d-block">&nbsp;</label>
                        <div class="mt-2 d-flex align-items-center" style="gap: 8px;">
                            <input type="checkbox" id="editFieldRequired" name="is_required" value="1" style="width: 18px; height: 18px; cursor: pointer; accent-color: #fecc56;">
                            <label class="text-light font-weight-bold mb-0" for="editFieldRequired" style="cursor: pointer;">Mandatory Field</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-info font-weight-bold text-white px-4">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(id, label, name, type, options, isRequired, order) {
    document.getElementById('editFieldId').value = id;
    document.getElementById('editFieldLabel').value = label;
    document.getElementById('editFieldName').value = name;
    document.getElementById('editFieldOrder').value = order;
    
    var typeSelect = document.getElementById('editFieldType');
    typeSelect.value = type;
    toggleOptions(typeSelect, 'editOptionsRow');
    
    document.getElementById('editFieldOptions').value = options || '';
    document.getElementById('editFieldRequired').checked = isRequired == 1;
    
    $('#editKycFieldModalMain').modal('show');
}

function toggleOptions(selectEl, rowId) {
    var row = document.getElementById(rowId);
    if (!row) return;
    row.style.display = (selectEl.value === 'select') ? 'block' : 'none';
}

// On load, init the add field dropdown
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('addFieldType');
    if (el) toggleOptions(el, 'addOptions');
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>

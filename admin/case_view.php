<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();
require_permission('view_cases');

$case_id = (int)($_GET['id'] ?? 0);
if (!$case_id) die("Invalid case ID.");

// Fetch Case Details
$stmt = $pdo->prepare("
    SELECT c.*, cl.first_name, cl.last_name, cl.email 
    FROM IFW_cases c 
    JOIN IFW_clients cl ON c.client_id = cl.id 
    WHERE c.id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch();

if (!$case) die("Case not found.");

// Check assignment if not admin/superadmin/manage_cases
if (!in_array($_SESSION['admin_role'], ['super_admin', 'superadmin', 'admin']) && !has_permission('manage_cases')) {
    // Fetch assigned agent of client
    $stmt_c = $pdo->prepare("SELECT assigned_agent_id FROM IFW_clients WHERE id = ?");
    $stmt_c->execute([$case['client_id']]);
    $assigned_agent = $stmt_c->fetchColumn();
    
    if ((int)$case['attorney_id'] !== (int)$_SESSION['admin_id'] && (int)$assigned_agent !== (int)$_SESSION['admin_id']) {
        die("Unauthorized to view this case.");
    }
}

// Handle Notes Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $note = trim($_POST['note']);
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_case_notes (client_id, case_id, agent_id, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$case['client_id'], $case_id, $_SESSION['admin_id'], $note]);
        $success = "Note added successfully.";
    }
}

// Handle Timeline Event Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_timeline') {
    $milestone_title = trim($_POST['milestone_title']);
    $milestone_body = trim($_POST['milestone_body']);
    $milestone_date = !empty($_POST['milestone_date']) ? $_POST['milestone_date'] : date('Y-m-d');
    $status_color = $_POST['status_color'] ?? 'primary';
    $is_client_visible = isset($_POST['is_client_visible']) ? 1 : 0;
    
    if (!empty($milestone_title)) {
        $stmt = $pdo->prepare("INSERT INTO IFW_case_timeline (case_id, created_by, milestone_title, milestone_body, milestone_date, status_color, is_client_visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_id, $_SESSION['admin_id'], $milestone_title, $milestone_body, $milestone_date, $status_color, $is_client_visible]);
        
        // Add a notification for client
        try {
            $notif_title = "Case Update: " . $milestone_title;
            $notif_body = substr($milestone_body, 0, 100);
            $stmt_notif = $pdo->prepare("INSERT INTO IFW_notifications (client_id, type, title, body, icon, link) VALUES (?, 'case_update', ?, ?, 'briefcase', '/client/my_cases.php?case_id=')");
            $stmt_notif->execute([$case['client_id'], $notif_title, $notif_body]);
            $last_notif_id = $pdo->lastInsertId();
            // Update the link to point to this case
            $pdo->prepare("UPDATE IFW_notifications SET link = ? WHERE id = ?")->execute(['/client/my_cases.php?case_id=' . $case_id, $last_notif_id]);
        } catch(Exception $e) {}
        
        $success = "Milestone added successfully.";
    }
}

// Handle Timeline Event Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_timeline') {
    $timeline_id = (int)$_POST['timeline_id'];
    $stmt = $pdo->prepare("DELETE FROM IFW_case_timeline WHERE id = ? AND case_id = ?");
    $stmt->execute([$timeline_id, $case_id]);
    $success = "Timeline milestone removed.";
}

// Handle Document Upload to Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['doc_file']['tmp_name'];
        $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['doc_file']['name']);
        $doc_type = $_POST['document_type_select'] ?? 'Standard';
        if ($doc_type === 'Other' && !empty($_POST['document_type_custom'])) {
            $doc_type = trim($_POST['document_type_custom']);
        }
        $requires_sig = isset($_POST['requires_signature']) ? 1 : 0;
        
        $base_dir = dirname(__DIR__);
        $target_dir = $base_dir . '/uploads/vault/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $new_filename = time() . '_' . $file_name;
        $target_file = $target_dir . $new_filename;
        $db_path = 'uploads/vault/' . $new_filename;
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO IFW_documents (client_id, file_name, file_path, document_type, requires_signature) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$case['client_id'], $file_name, $db_path, $doc_type, $requires_sig]);
            $success = "Document uploaded successfully to client vault.";
        } else {
            $error = "Failed to save uploaded file.";
        }
    } else {
        $error = "Please choose a valid file to upload.";
    }
}

// Handle Custom Dynamic Document Vault Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_custom_document') {
    $doc_name = trim($_POST['document_name'] ?? '');
    $doc_type = trim($_POST['document_type'] ?? 'Standard');
    $doc_body = trim($_POST['document_body'] ?? '');
    $requires_sig = isset($_POST['requires_signature']) ? 1 : 0;
    
    if (empty($doc_name) || empty($doc_body)) {
        $error = "Document Title and Body Content are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO IFW_documents (client_id, file_name, file_path, document_type, document_body, requires_signature) VALUES (?, ?, NULL, ?, ?, ?)");
        $stmt->execute([$case['client_id'], $doc_name, $doc_type, $doc_body, $requires_sig]);
        $success = "Custom dynamic document has been created and sent to client vault.";
    }
}

// Handle Document Deletion from Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $doc_id = (int)$_POST['document_id'];
    $base_dir = dirname(__DIR__);
    
    // Fetch file path
    $fStmt = $pdo->prepare("SELECT file_path FROM IFW_documents WHERE id = ? AND client_id = ?");
    $fStmt->execute([$doc_id, $case['client_id']]);
    $doc_file = $fStmt->fetch();
    
    if ($doc_file) {
        @unlink($base_dir . '/' . $doc_file['file_path']);
        $pdo->prepare("DELETE FROM IFW_documents WHERE id = ?")->execute([$doc_id]);
        $success = "Document deleted from vault.";
    }
}

// Fetch Notes
$notesStmt = $pdo->prepare("
    SELECT n.*, u.username 
    FROM IFW_case_notes n 
    JOIN IFW_users u ON n.agent_id = u.id 
    WHERE n.case_id = ? 
    ORDER BY n.created_at DESC
");
$notesStmt->execute([$case_id]);
$notes = $notesStmt->fetchAll();

// Fetch Timeline Events
$timelineStmt = $pdo->prepare("
    SELECT t.*, u.username 
    FROM IFW_case_timeline t 
    LEFT JOIN IFW_users u ON t.created_by = u.id 
    WHERE t.case_id = ? 
    ORDER BY t.milestone_date DESC, t.created_at DESC
");
$timelineStmt->execute([$case_id]);
$timeline_events = $timelineStmt->fetchAll();

// Fetch Client Documents
$docsStmt = $pdo->prepare("SELECT * FROM IFW_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
$docsStmt->execute([$case['client_id']]);
$documents = $docsStmt->fetchAll();

$_SESSION['role'] = $_SESSION['admin_role'] ?? 'admin';
$_SESSION['user_name'] = $_SESSION['admin_username'] ?? 'Admin';
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- PAGE CONTENT -->
<div class="row">
    <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="text-dark"><?php echo htmlspecialchars($case['title']); ?></h4>
            <p class="text-muted mb-0">Case #<?php echo htmlspecialchars($case['case_number']); ?> &middot; Client: <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></p>
        </div>
        <div>
            <?php
            $badge = 'secondary';
            if ($case['status'] === 'active') $badge = 'success';
            if ($case['status'] === 'pending') $badge = 'warning';
            ?>
            <span class="badge badge-<?php echo $badge; ?> p-2 px-3" style="font-size: 14px;">
                <?php echo ucfirst($case['status']); ?>
            </span>
        </div>
    </div>

    <div class="col-12">
        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="material-icons mr-2" style="vertical-align: middle;">check_circle</i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- Case Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Case Description</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
            </div>
        </div>

        <!-- Case Timeline / Milestones -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="material-icons text-warning mr-1" style="vertical-align: text-bottom;">timeline</i> Case Timeline & Milestones</h5>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark" data-toggle="modal" data-target="#addTimelineModal">
                    <i class="material-icons mr-1" style="font-size:16px; vertical-align:text-bottom;">add</i> Add Milestone
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($timeline_events)): ?>
                    <p class="text-muted text-center py-3">No milestones posted for this case yet.</p>
                <?php else: ?>
                    <div class="timeline-wrapper" style="position: relative; padding-left: 20px; border-left: 2px solid #ddd; margin-left: 10px;">
                        <?php foreach($timeline_events as $event): ?>
                            <div class="timeline-event mb-4" style="position: relative;">
                                <div class="timeline-dot" style="position: absolute; left: -27px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background-color: <?php 
                                    echo ['primary' => '#0b2e59', 'success' => '#28a745', 'warning' => '#ffc107', 'danger' => '#dc3545', 'info' => '#17a2b8'][$event['status_color']] ?? '#0b2e59'; 
                                ?>;"></div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-dark d-block" style="font-size: 1.1rem;"><?php echo htmlspecialchars($event['milestone_title']); ?></strong>
                                        <span class="text-muted small">
                                            <i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">event</i> <?php echo date('M j, Y', strtotime($event['milestone_date'])); ?>
                                            &middot; Visibility: <?php echo $event['is_client_visible'] ? '<span class="text-success font-weight-bold">Client Visible</span>' : '<span class="text-warning font-weight-bold">Internal Only</span>'; ?>
                                        </span>
                                        <?php if (!empty($event['milestone_body'])): ?>
                                            <p class="text-muted mt-2 mb-0 small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($event['milestone_body']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Remove this milestone?');">
                                        <input type="hidden" name="action" value="delete_timeline">
                                        <input type="hidden" name="timeline_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0"><i class="material-icons" style="font-size: 18px;">delete</i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Internal Notes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Internal Case Notes</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_note">
                    <div class="input-group">
                        <textarea name="note" class="form-control" rows="2" placeholder="Add an internal note..." required></textarea>
                        <div class="input-group-append">
                            <button class="btn btn-warning fw-bold text-dark px-4" type="submit">Post Note</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($notes)): ?>
                    <p class="text-muted text-center py-3">No internal notes for this case.</p>
                <?php else: ?>
                    <?php foreach($notes as $note): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-primary"><i class="material-icons" style="font-size: 16px; vertical-align: text-bottom;">person</i> <?php echo htmlspecialchars($note['username']); ?></strong>
                            <small class="text-muted"><i class="material-icons" style="font-size: 14px; vertical-align: text-bottom;">schedule</i> <?php echo date('M j, Y h:i A', strtotime($note['created_at'])); ?></small>
                        </div>
                        <div class="text-dark bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($note['note'] ?: $note['note_text'] ?? '')); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0 fw-bold">Case Actions</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#vaultDocumentsModal" data-bs-toggle="modal" data-bs-target="#vaultDocumentsModal"><i class="material-icons text-danger mr-2" style="vertical-align: middle;">picture_as_pdf</i> Vault Documents</a>
                <a href="invoices.php?client_id=<?php echo $case['client_id']; ?>" class="list-group-item list-group-item-action"><i class="material-icons text-info mr-2" style="vertical-align: middle;">receipt</i> Case Invoices</a>
                <a href="chat.php?client_id=<?php echo $case['client_id']; ?>" class="list-group-item list-group-item-action"><i class="material-icons text-warning mr-2" style="vertical-align: middle;">chat</i> Message Client</a>
            </div>
        </div>
    </div>
</div>

<!-- Add Timeline Modal -->
<div class="modal fade" id="addTimelineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="material-icons mr-2" style="vertical-align: text-bottom;">timeline</i> Add Case Milestone</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="add_timeline">
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Milestone Title <span class="text-warning">*</span></label>
                <input type="text" name="milestone_title" class="form-control bg-dark text-white border-secondary" required placeholder="e.g. Asset Tracing Report Completed">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Event Date</label>
                <input type="date" name="milestone_date" class="form-control bg-dark text-white border-secondary" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Status Color</label>
                <select name="status_color" class="form-control bg-dark text-white border-secondary">
                    <option value="primary">Dark Blue (General)</option>
                    <option value="info">Light Blue (In Progress)</option>
                    <option value="warning">Yellow (Pending)</option>
                    <option value="success">Green (Resolved/Successful)</option>
                    <option value="danger">Red (Attention Needed/Failed)</option>
                </select>
            </div>
            
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Details / Description</label>
                <textarea name="milestone_body" rows="4" class="form-control bg-dark text-white border-secondary" placeholder="Enter milestone details..."></textarea>
            </div>
            
            <div class="mt-3 d-flex align-items-center" style="gap: 8px;">
                <input type="checkbox" id="clientVisibleSwitch" name="is_client_visible" value="1" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #fecc56;">
                <label class="text-light font-weight-bold mb-0" for="clientVisibleSwitch" style="cursor: pointer;">Visible to Client in Dashboard</label>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Add Event</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Vault Documents Modal -->
<div class="modal fade" id="vaultDocumentsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="material-icons mr-2" style="vertical-align: text-bottom;">folder_special</i> Case Document Vault</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
          <!-- Tabbed Layout for Document Creation -->
          <ul class="nav nav-tabs border-secondary mb-3" id="docTabs" role="tablist">
              <li class="nav-item">
                  <a class="nav-link active text-warning bg-transparent border-secondary" id="upload-tab" data-toggle="tab" href="#uploadSection" role="tab" aria-selected="true" style="border-bottom: 2px solid #fecc56 !important;"><i class="fas fa-upload mr-1"></i> Upload File</a>
              </li>
              <li class="nav-item ml-2">
                  <a class="nav-link text-warning bg-transparent border-secondary" id="create-tab" data-toggle="tab" href="#createSection" role="tab" aria-selected="false" style="border-bottom: 2px solid #fecc56 !important;"><i class="fas fa-edit mr-1"></i> Create Custom Document</a>
              </li>
          </ul>

          <div class="tab-content" id="docTabsContent">
              <!-- Upload Section -->
              <div class="tab-pane fade show active" id="uploadSection" role="tabpanel">
                  <form method="POST" enctype="multipart/form-data" class="bg-black p-3 rounded mb-4 border border-secondary">
                      <input type="hidden" name="action" value="upload_document">
                      <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-upload mr-1"></i> Upload Document to Vault</h6>
                      <div class="row">
                          <div class="col-md-5 mb-2">
                              <label class="small text-muted font-weight-bold d-block">Select File</label>
                              <input type="file" name="doc_file" class="form-control-file text-light" required>
                          </div>
                          <div class="col-md-4 mb-2">
                              <label class="small text-muted font-weight-bold">Document Type</label>
                              <select name="document_type_select" id="docTypeSelect" class="form-control form-control-sm bg-dark text-white border-secondary mb-2" onchange="toggleCustomDocType()">
                                  <option value="Standard">Standard / General Document</option>
                                  <option value="Service Agreement">Service Agreement</option>
                                  <option value="Power of Attorney">Power of Attorney</option>
                                  <option value="NDA">NDA (Non-Disclosure Agreement)</option>
                                  <option value="Invoice">Invoice Attachment</option>
                                  <option value="Other">Other (Custom)</option>
                              </select>
                              <input type="text" name="document_type_custom" id="docTypeCustom" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Specify custom type" style="display:none;">
                          </div>
                          <div class="col-md-3 mb-2 d-flex flex-column justify-content-end pb-1">
                              <div class="form-check mb-2 d-flex align-items-center">
                                  <input type="checkbox" class="form-check-input" id="sigRequired" name="requires_signature" value="1" style="width:16px; height:16px; cursor:pointer;">
                                  <label class="form-check-label text-light small ml-2" for="sigRequired" style="cursor:pointer;">Requires Signature</label>
                              </div>
                              <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark w-100">Upload</button>
                          </div>
                      </div>
                  </form>
              </div>
              
              <!-- Create Custom Section -->
              <div class="tab-pane fade" id="createSection" role="tabpanel">
                  <form method="POST" class="bg-black p-3 rounded mb-4 border border-secondary">
                      <input type="hidden" name="action" value="send_custom_document">
                      <h6 class="text-warning font-weight-bold mb-3"><i class="fas fa-file-signature mr-1"></i> Compose Custom Document</h6>
                       <div class="form-group mb-2">
                           <label class="small text-warning font-weight-bold">Select Document Template (Optional)</label>
                           <select class="form-control form-control-sm bg-dark text-white border-secondary" id="docTemplateSelector" onchange="loadDocTemplate(this)">
                               <option value="">-- Choose a standard template --</option>
                               <option value="service_agreement">Service Agreement & Fee Contract [Global]</option>
                               <option value="nda">Mutual Non-Disclosure Agreement (NDA) [Global]</option>
                               <option value="power_of_attorney">Power of Attorney & Letter of Mandate [Global]</option>
                               <option value="cease_and_desist">Cease & Desist Demand Letter [Global]</option>
                               <option value="letter_of_demand">Formal Letter of Demand [UK / Australia / Commonwealth]</option>
                               <option value="writ_of_mandamus">Writ of Mandamus Court Petition [US / Common Law]</option>
                               <option value="authority_to_act">Third-Party Release & Authority to Act [Global]</option>
                               <option value="settlement_release">Settlement & Mutual Release Agreement [Global]</option>
                               <option value="blockchain_forensic">Crypto Ledger Forensic Freeze Request [Global]</option>
                           </select>
                       </div>
                      <div class="form-group mb-2">
                          <label class="small text-muted font-weight-bold">Document Title / Name</label>
                          <input type="text" name="document_name" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Asset Recovery Agreement - Jane Doe">
                      </div>
                      <div class="row">
                          <div class="col-md-6 form-group mb-2">
                              <label class="small text-muted font-weight-bold">Document Type (Dynamic)</label>
                              <input type="text" name="document_type" class="form-control form-control-sm bg-dark text-white border-secondary" required placeholder="e.g. Service Agreement, Custom NDA, Recovery Mandate">
                          </div>
                          <div class="col-md-6 form-group mb-2 d-flex align-items-center pt-3">
                              <div class="form-check d-flex align-items-center">
                                  <input type="checkbox" class="form-check-input" id="customSigRequired" name="requires_signature" value="1" style="width:16px; height:16px; cursor:pointer;">
                                  <label class="form-check-label text-light small ml-2" for="customSigRequired" style="cursor:pointer;">Requires Client Signature</label>
                              </div>
                          </div>
                      </div>
                      <div class="form-group mb-3">
                          <label class="small text-muted font-weight-bold">Document Content (HTML / Text Allowed)</label>
                          <textarea name="document_body" id="customDocBody" class="form-control bg-dark text-white border-secondary" rows="10" required placeholder="Write your document content here. You can use standard HTML formatting tags like &lt;b&gt;, &lt;i&gt;, &lt;ul&gt;, &lt;p&gt;, etc."></textarea>
                      </div>
                      <div class="d-flex justify-content-between">
                          <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold px-3" onclick="previewCustomDoc()"><i class="fas fa-eye mr-1"></i> Live Preview Document</button>
                          <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark px-4">Create & Send to Client</button>
                      </div>
                  </form>
              </div>
          </div>

          <!-- Documents List -->
          <h6 class="text-light font-weight-bold mb-3"><i class="fas fa-file-pdf mr-1"></i> Vaulted Files & Agreements</h6>
          <div class="table-responsive">
              <table class="table table-dark table-hover table-striped mb-0" style="background:#111;">
                  <thead>
                      <tr>
                          <th>File Name</th>
                          <th>Type</th>
                          <th>Status</th>
                          <th>Uploaded</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (empty($documents)): ?>
                          <tr>
                              <td colspan="5" class="text-center text-muted py-4">No documents vaulted for this client.</td>
                          </tr>
                      <?php else: ?>
                          <?php foreach($documents as $doc): ?>
                              <tr>
                                  <td>
                                      <?php if (!empty($doc['document_body'])): ?>
                                          <a href="view_document.php?id=<?= $doc['id'] ?>" target="_blank" class="text-warning font-weight-bold">
                                              <i class="fas fa-file-alt mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                          </a>
                                      <?php else: ?>
                                          <a href="<?= BASE_URL . '/' . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-warning font-weight-bold">
                                              <i class="fas fa-file-download mr-1"></i> <?= htmlspecialchars($doc['file_name']) ?>
                                          </a>
                                      <?php endif; ?>
                                  </td>
                                  <td><span class="badge badge-secondary"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                                  <td>
                                      <?php if ($doc['requires_signature']): ?>
                                          <?php if ($doc['is_signed']): ?>
                                              <span class="badge badge-success" title="Signed at: <?= $doc['signed_at'] ?> IP: <?= $doc['signature_ip'] ?>"><i class="fas fa-check-circle mr-1"></i> Signed</span>
                                          <?php else: ?>
                                              <span class="badge badge-warning text-dark"><i class="fas fa-signature mr-1"></i> Pending Signature</span>
                                          <?php endif; ?>
                                      <?php else: ?>
                                          <span class="badge badge-info">Standard View</span>
                                      <?php endif; ?>
                                  </td>
                                  <td class="small text-muted"><?= date('M j, Y H:i', strtotime($doc['uploaded_at'])) ?></td>
                                  <td>
                                      <form method="POST" class="d-inline" onsubmit="return confirm('Delete this vaulted document?');">
                                          <input type="hidden" name="action" value="delete_document">
                                          <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                          <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                                      </form>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>
      </div>
      <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Custom Doc Preview Modal -->
<div class="modal fade" id="customDocPreviewModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-eye mr-2"></i>Document Preview</h5>
        <button type="button" class="close text-white" onclick="$('#customDocPreviewModal').modal('hide')">&times;</button>
      </div>
      <div class="modal-body bg-light text-dark p-5" style="font-family: 'Times New Roman', Times, serif; min-height: 400px; max-height: 500px; overflow-y: auto;">
          <div id="previewTitle" class="text-center font-weight-bold mb-4" style="font-size: 1.5rem; border-bottom: 2px solid #333; padding-bottom: 10px; font-family: 'Montserrat', sans-serif;"></div>
          <div id="previewContent" style="font-size: 1.1rem; line-height: 1.6; white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-secondary font-weight-bold" onclick="$('#customDocPreviewModal').modal('hide')">Close Preview</button>
      </div>
    </div>
  </div>
</div>

<script>
function loadDocTemplate(select) {
    var val = select.value;
    var clientName = "<?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>";
    
    var docHeader = `<div style="text-align: justify; font-family: 'Times New Roman', Times, serif; line-height: 1.8;">`;
    var docFooter = `<br><br><p style="text-align: center; font-size: 0.9em; border-top: 1px solid #ccc; padding-top: 10px;"><i>This document is confidential and privileged. Executed on the IFW Global Secure Platform.</i></p></div>`;

    if (val === 'service_agreement') {
        document.getElementsByName('document_name')[0].value = 'Service Agreement - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Service Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">LETTER OF ENGAGEMENT & SERVICE AGREEMENT</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Service Agreement ("Agreement") is made between <b>IFW Global</b> ("Agency") and <b>` + clientName + `</b> ("Client").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. SCOPE OF INVESTIGATION & RECOVERY SERVICES</h4>
<p>Agency agrees to perform comprehensive asset tracing, forensic blockchain tracking, and intelligence recovery services concerning the Client's reported financial loss. The Agency will utilize proprietary investigative methodologies to locate, secure, and recover misappropriated funds.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. RETAINER & FEES</h4>
<p>Client shall pay Agency the agreed professional services retainer prior to commencement. Upon successful restitution, a recovery success fee shall be calculated at the rate of 10% of the total recovered value. No hidden fees shall be applied.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. CONFIDENTIALITY</h4>
<p>Both parties agree to hold all information related to this investigation in the absolute strictest confidence. Disclosure to third parties is strictly prohibited without prior written consent.</p>
<p style="margin-top: 30px;">IN WITNESS WHEREOF, the parties hereto have executed this Agreement securely via the IFW Global cryptographic signing portal.</p>` + docFooter;
    } else if (val === 'nda') {
        document.getElementsByName('document_name')[0].value = 'Mutual NDA - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Non-Disclosure Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">MUTUAL NON-DISCLOSURE AGREEMENT (NDA)</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Mutual Non-Disclosure Agreement is entered into by and between <b>IFW Global</b> and <b>` + clientName + `</b> ("The Parties").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>The Parties wish to explore a potential investigation and asset recovery case, in connection with which they may disclose confidential proprietary information.</p>
<h4 style="margin-bottom: 10px;">1. CONFIDENTIAL INFORMATION</h4>
<p>"Confidential Information" includes all written, oral, electronic, or visual information disclosed between the parties, including but not limited to forensic reports, identity dossiers, financial logs, and investigative tactics.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. RESTRICTION ON USE</h4>
<p>Neither party shall use or disclose any Confidential Information of the other party for any purpose outside the strict scope of this investigation. The receiving party shall employ the highest degree of care to protect such information.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. SURVIVAL</h4>
<p>The obligations under this Agreement shall survive the termination of the investigation case indefinitely.</p>` + docFooter;
    } else if (val === 'power_of_attorney') {
        document.getElementsByName('document_name')[0].value = 'Power of Attorney & Letter of Mandate - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Power of Attorney';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">LETTER OF MANDATE & LIMITED POWER OF ATTORNEY</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>I, <b>` + clientName + `</b>, hereby appoint <b>IFW Global</b> and its authorized investigative agents as my lawful attorney-in-fact and authorized representative.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. GRANT OF AUTHORITY</h4>
<p>IFW Global shall have full power and authority to act on my behalf to trace, freeze, negotiate, and recover funds lost to illicit financial operations. This includes the explicit authority to request confidential records from financial institutions, cryptocurrency exchanges, and law enforcement agencies globally.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. LEGAL REPRESENTATION</h4>
<p>My attorney-in-fact may sign, seal, execute, deliver, and acknowledge any and all documents necessary to facilitate the recovery of my assets, acting as fully as I could do if personally present.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. DURATION & REVOCATION</h4>
<p>This Limited Power of Attorney is effective immediately upon cryptographic execution and shall remain in full force and effect until the conclusion of the recovery mandate, unless revoked by me in writing.</p>` + docFooter;
    } else if (val === 'cease_and_desist') {
        document.getElementsByName('document_name')[0].value = 'Cease & Desist Demand - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Cease & Desist';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">FORMAL CEASE AND DESIST DEMAND</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p><b>VIA SECURE ELECTRONIC DELIVERY</b></p>
<p><b>To Whom It May Concern,</b></p>
<p>We act as retained investigators and authorized representatives on behalf of <b>` + clientName + `</b> in relation to funds fraudulently obtained from our client. Forensic tracing confirms that stolen assets have transited through or are currently held within your platform's infrastructure.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p><b>DEMAND IS HEREBY MADE</b> that you immediately:</p>
<ul style="margin-bottom: 20px;">
    <li>Cease and desist all unauthorized operations concerning our client's accounts.</li>
    <li>Preserve all server logs, KYC data, IP addresses, and internal communication records.</li>
    <li>Place an administrative hold on all disputed assets pending the arrival of a formal legal freeze order or subpoena.</li>
</ul>
<p>Failure to comply immediately will result in IFW Global escalating this matter. We will initiate formal criminal and civil complaints with international regulatory bodies and law enforcement agencies, naming your organization as an uncooperative accessory to financial fraud.</p>
<p>Govern yourselves accordingly.</p>
<p style="margin-top: 30px;"><b>IFW Global Legal & Compliance Department</b></p>` + docFooter;
    } else if (val === 'letter_of_demand') {
        document.getElementsByName('document_name')[0].value = 'Formal Letter of Demand - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Letter of Demand';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">FORMAL LETTER OF DEMAND (RESTITUTION)</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> UK / Australia / Commonwealth Common Law</p>
<p><b>Dear Sir/Madam,</b></p>
<p>This is a formal Letter of Demand issued pursuant to standard pre-action protocols. We are the authorized representatives of <b>` + clientName + `</b>.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>Our comprehensive forensic investigations confirm that you are currently holding or have received assets belonging to our client. These funds were transferred under fraudulent representations, constituting unjust enrichment and fraud.</p>
<h4 style="margin-bottom: 10px;">DEMAND FOR RESTITUTION</h4>
<p>We hereby demand the immediate repayment and full restitution of the aforementioned sum to our client's designated recovery account within <b>fourteen (14) days</b> from the date of this letter.</p>
<p>Failing restitution within the specified timeframe, we have standing instructions to commence civil and criminal proceedings without further notice, which will result in substantial legal costs being claimed against you.</p>
<p>We await your immediate compliance.</p>` + docFooter;
    } else if (val === 'writ_of_mandamus') {
        document.getElementsByName('document_name')[0].value = 'Petition for Writ of Mandamus - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Writ of Mandamus';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">PETITION FOR WRIT OF MANDAMUS</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> United States Judicial System / US Common Law</p>
<p><b>In the Matter of the Case of:</b> ` + clientName + ` (Petitioner)</p>
<p><b>TO THE HONORABLE COURT / REGULATORY BODY:</b></p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<p>Petitioner <b>` + clientName + `</b>, by and through their authorized investigative counsel IFW Global, hereby petitions for a Writ of Mandamus directing the Respondent to immediately execute their non-discretionary duty regarding the release of frozen illicit assets back to the rightful owner.</p>
<h4 style="margin-bottom: 10px;">1. BASIS FOR PETITION</h4>
<p>Petitioner has a clear, established legal right to the performance of this duty. Extensive forensic evidence provided by IFW Global confirms the Petitioner's undisputed ownership of the targeted assets. Respondent has a clear legal obligation to perform the release, and the duty is ministerial in nature.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. ABSENCE OF ALTERNATIVE REMEDY</h4>
<p>Petitioner has exhausted all administrative remedies and has no other adequate legal remedy available to compel the return of the misappropriated funds.</p>
<p style="margin-top: 30px;"><b>WHEREFORE</b>, Petitioner respectfully requests that a Writ of Mandamus be issued compelling the immediate release and transfer of the recovered assets.</p>` + docFooter;
    } else if (val === 'authority_to_act') {
        document.getElementsByName('document_name')[0].value = 'Authority to Act & Info Release - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Authority to Act';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">AUTHORITY TO ACT & RELEASE OF INFORMATION</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>I, <b>` + clientName + `</b>, hereby authorize <b>IFW Global</b> and its designated agents to act as my exclusive representatives and investigators in the matter of my financial loss recovery.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">DIRECTIVE TO THIRD PARTIES</h4>
<p>I hereby instruct, mandate, and authorize all banks, cryptocurrency exchanges, internet service providers, financial institutions, and law enforcement agencies to release any and all records related to my accounts and transactions to IFW Global immediately upon presentation of this document.</p>
<p>This includes, but is not limited to:</p>
<ul style="margin-bottom: 20px;">
    <li>Transaction logs, IP addresses, and routing data.</li>
    <li>KYC/AML documentation and identity records of opposing accounts.</li>
    <li>Internal investigations or freeze status reports.</li>
</ul>
<p>A copy or digital reproduction of this executed document shall have the same legally binding effect as the original.</p>` + docFooter;
    } else if (val === 'settlement_release') {
        document.getElementsByName('document_name')[0].value = 'Settlement & Mutual Release - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Settlement Agreement';
        document.getElementById('customSigRequired').checked = true;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">SETTLEMENT AND MUTUAL RELEASE AGREEMENT</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Applicability</p>
<p>This Settlement and Mutual Release Agreement ("Agreement") is made between <b>` + clientName + `</b> ("Client") and the responding party/entity ("Respondent").</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">1. SETTLEMENT PAYMENT</h4>
<p>Respondent agrees to pay Client the agreed sum in full and final settlement of all claims, controversies, and disputes arising out of the investigated financial loss.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">2. MUTUAL RELEASE</h4>
<p>Upon confirmed receipt of the cleared settlement payment in the Client's designated account, both parties hereby fully and forever release, acquit, and discharge each other from any and all claims, liabilities, demands, damages, or actions of any kind.</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">3. NON-ADMISSION OF LIABILITY</h4>
<p>This Agreement is a compromise of a disputed claim and shall not be construed as an admission of liability by either party, which is expressly denied.</p>` + docFooter;
    } else if (val === 'blockchain_forensic') {
        document.getElementsByName('document_name')[0].value = 'Crypto Forensic Freeze Request - ' + clientName;
        document.getElementsByName('document_type')[0].value = 'Forensic Block Request';
        document.getElementById('customSigRequired').checked = false;
        document.getElementById('customDocBody').value = docHeader + `<h3 style="text-align:center; font-weight:bold; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom:20px;">CRYPTOGRAPHIC FORENSIC FREEZE REQUEST</h3>
<p style="text-align:right;"><b>Date:</b> ` + new Date().toLocaleDateString() + `</p>
<p><b>Jurisdiction:</b> Global / Universal Crypto Asset Regulations</p>
<p><b>To: Legal, Compliance & Security Department, [Exchange/Custodian Name]</b></p>
<p>We formally represent <b>` + clientName + `</b>, who was the victim of a coordinated cryptocurrency fraud operation. Advanced forensic ledger tracking confirms that the stolen assets were transferred directly into your exchange's custody.</p>
<hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
<h4 style="margin-bottom: 10px;">INCIDENT DETAILS</h4>
<p><b>Target Wallet Address:</b> [Insert Address]<br>
<b>Transaction Hash (TXID):</b> [Insert TXID]<br>
<b>Network:</b> [Insert Blockchain Network]</p>
<h4 style="margin-bottom: 10px; margin-top: 20px;">URGENT ADMINISTRATIVE FREEZE REQUEST</h4>
<p>Pursuant to international AML (Anti-Money Laundering) directives and your institution's Terms of Service regarding illicit activities, we request that you immediately place an <b>administrative temporary hold/freeze</b> on the target account.</p>
<p>This freeze is necessary to prevent the dissipation or laundering of stolen assets while we obtain a formal court freeze order or law enforcement subpoena. Failure to act may render your exchange liable for facilitating the laundering of proceeds of crime.</p>
<p style="margin-top: 30px;"><b>IFW Global Blockchain Forensics Team</b></p>` + docFooter;
    }
}

function toggleCustomDocType() {
    var select = document.getElementById('docTypeSelect');
    var customInput = document.getElementById('docTypeCustom');
    if (select.value === 'Other') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

function previewCustomDoc() {
    var title = document.getElementsByName('document_name')[0].value || 'Untitled Document';
    var body = document.getElementById('customDocBody').value || '<i>No content provided.</i>';
    
    document.getElementById('previewTitle').innerHTML = title;
    document.getElementById('previewContent').innerHTML = body;
    
    $('#customDocPreviewModal').modal('show');
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>





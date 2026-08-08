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

// Check assignment if not admin/manage_cases
if ($_SESSION['admin_role'] !== 'admin' && !has_permission('manage_cases')) {
    if ((int)$case['attorney_id'] !== (int)$_SESSION['admin_id']) {
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
                <a href="#" class="list-group-item list-group-item-action"><i class="material-icons text-danger mr-2" style="vertical-align: middle;">picture_as_pdf</i> Vault Documents</a>
                <a href="#" class="list-group-item list-group-item-action"><i class="material-icons text-info mr-2" style="vertical-align: middle;">receipt</i> Case Invoices</a>
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
            
            <div class="custom-control custom-switch mt-3">
                <input type="checkbox" class="custom-control-input" id="clientVisibleSwitch" name="is_client_visible" value="1" checked>
                <label class="custom-control-label text-light font-weight-bold" for="clientVisibleSwitch" style="cursor: pointer;">Visible to Client in Dashboard</label>
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

<?php require_once '../includes/admin_footer.php'; ?>





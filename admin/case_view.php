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
    $chk = $pdo->prepare("SELECT 1 FROM IFW_case_assignments WHERE case_id = ? AND user_id = ?");
    $chk->execute([$case_id, $_SESSION['admin_id']]);
    if (!$chk->fetch()) die("Unauthorized to view this case.");
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
                        <div class="text-dark bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
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

<?php require_once '../includes/admin_footer.php'; ?>





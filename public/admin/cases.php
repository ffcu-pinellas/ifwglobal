<?php
// admin/cases.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff']);
$admin_id = $_SESSION['admin_id'];

// Handle Case Creation / Deletion / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_case' && !$is_agent) {
        $case_num = 'IFW-' . date('Y') . '-' . mt_rand(1000, 9999);
        $title = trim($_POST['title']);
        $client_id = (int)$_POST['client_id'];
        $attorney_id = !empty($_POST['attorney_id']) ? (int)$_POST['attorney_id'] : null;
        $court_date = !empty($_POST['court_date']) ? $_POST['court_date'] : null;
        $status = $_POST['status'];
        $desc = trim($_POST['description']);
        
        $stmt = $pdo->prepare("INSERT INTO IFW_cases (case_number, title, client_id, attorney_id, court_date, status, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$case_num, $title, $client_id, $attorney_id, $court_date, $status, $desc]);
        header("Location: cases.php?success=1");
        exit;
    }
    
    if ($_POST['action'] === 'delete_case' && !$is_agent) {
        $stmt = $pdo->prepare("DELETE FROM IFW_cases WHERE id = ?");
        $stmt->execute([(int)$_POST['case_id']]);
        header("Location: cases.php?deleted=1");
        exit;
    }
    
    if ($_POST['action'] === 'update_status') {
        $stmt = $pdo->prepare("UPDATE IFW_cases SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], (int)$_POST['case_id']]);
        header("Location: cases.php?updated=1");
        exit;
    }
}

// Fetch Cases
$cases = [];
try {
    if ($is_agent) {
        $stmt = $pdo->prepare("SELECT c.*, cl.first_name, cl.last_name, u.username as attorney_name FROM IFW_cases c JOIN IFW_clients cl ON c.client_id = cl.id LEFT JOIN IFW_users u ON c.attorney_id = u.id WHERE c.attorney_id = ? OR cl.assigned_agent_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$admin_id, $admin_id]);
        $cases = $stmt->fetchAll();
    } else {
        $cases = $pdo->query("SELECT c.*, cl.first_name, cl.last_name, u.username as attorney_name FROM IFW_cases c JOIN IFW_clients cl ON c.client_id = cl.id LEFT JOIN IFW_users u ON c.attorney_id = u.id ORDER BY c.created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

// Fetch lists for dropdowns
$clients = [];
$attorneys = [];
try {
    if ($is_agent) {
        $stmt_clients = $pdo->prepare("SELECT id, first_name, last_name, email FROM IFW_clients WHERE assigned_agent_id = ? ORDER BY first_name");
        $stmt_clients->execute([$admin_id]);
        $clients = $stmt_clients->fetchAll();
    } else {
        $clients = $pdo->query("SELECT id, first_name, last_name, email FROM IFW_clients ORDER BY first_name")->fetchAll();
    }
    $attorneys = $pdo->query("SELECT id, username, role FROM IFW_users WHERE role != 'superadmin' ORDER BY username")->fetchAll();
} catch (Exception $e) {}

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-briefcase mr-2"></i>Case Directory</h3>
        <p class="text-muted mb-0">Manage active investigations, legal cases, and client representation records.</p>
    </div>
    <?php if (!$is_agent): ?>
        <button type="button" class="btn btn-warning font-weight-bold text-dark px-4 shadow" data-toggle="modal" data-target="#createCaseModal">
            <i class="fas fa-plus mr-1"></i> Create New Case
        </button>
    <?php endif; ?>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success font-weight-bold"><i class="fas fa-check-circle mr-2"></i>New case established.</div>
<?php endif; ?>
<?php if(isset($_GET['deleted'])): ?>
    <div class="alert alert-warning font-weight-bold"><i class="fas fa-trash mr-2"></i>Case record removed.</div>
<?php endif; ?>

<div class="card shadow-lg bg-dark border-secondary">
    <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
        <i class="fas fa-archive mr-2"></i>Active Cases (<?= count($cases) ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-warning">
                        <th>Case Ref</th>
                        <th>Title</th>
                        <th>Client</th>
                        <th>Officer/Attorney</th>
                        <th>Due / Court Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted">No case files found.</td></tr>
                    <?php else: ?>
                        <?php foreach($cases as $case): ?>
                            <tr>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($case['case_number']) ?></span></td>
                                <td><strong class="text-white"><?= htmlspecialchars($case['title']) ?></strong></td>
                                <td><?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?></td>
                                <td><?= htmlspecialchars($case['attorney_name'] ?: 'Unassigned') ?></td>
                                <td><?= $case['court_date'] ? date('M j, Y H:i', strtotime($case['court_date'])) : '<em class="text-muted">None</em>' ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                        <select name="status" class="form-control form-control-sm bg-dark text-white border-secondary" style="width:120px;" onchange="this.form.submit()">
                                            <option value="Pending" <?= $case['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Active" <?= $case['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Suspended" <?= $case['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                            <option value="Resolved" <?= $case['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="case_view.php?id=<?= $case['id'] ?>" class="btn btn-sm btn-info text-white" title="Manage Case & Timeline"><i class="fas fa-folder-open mr-1"></i> View & Manage</a>
                                    <?php if (!$is_agent): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this case?');">
                                            <input type="hidden" name="action" value="delete_case">
                                            <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- View Case Modal -->
                            <div class="modal fade" id="viewModal_<?= $case['id'] ?>" tabindex="-1">
                              <div class="modal-dialog">
                                <div class="modal-content bg-dark text-white border-warning">
                                  <div class="modal-header border-secondary">
                                    <h5 class="modal-title text-warning font-weight-bold">Case: <?= htmlspecialchars($case['case_number']) ?></h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                  </div>
                                  <div class="modal-body">
                                      <h6 class="text-white"><?= htmlspecialchars($case['title']) ?></h6>
                                      <hr class="border-secondary">
                                      <p class="text-muted small mb-1">DESCRIPTION</p>
                                      <p class="text-light" style="white-space: pre-wrap;"><?= htmlspecialchars($case['description'] ?: 'No description provided.') ?></p>
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

<?php if (!$is_agent): ?>
<!-- Create Case Modal -->
<div class="modal fade" id="createCaseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-briefcase mr-2"></i>Establish New Case</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_case">
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Case Title <span class="text-warning">*</span></label>
                <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required placeholder="e.g. Tax Audit 2026 Representation">
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-white font-weight-bold">Client <span class="text-warning">*</span></label>
                    <select name="client_id" class="form-control bg-dark text-white border-secondary" required>
                        <option value="">Select Client...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="text-white font-weight-bold">Assigned Attorney/Officer</label>
                    <select name="attorney_id" class="form-control bg-dark text-white border-secondary">
                        <option value="">Unassigned</option>
                        <?php foreach($attorneys as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['username']) ?> (<?= htmlspecialchars(ucfirst($a['role'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="text-white font-weight-bold">Court/Due Date</label>
                    <input type="datetime-local" name="court_date" class="form-control bg-dark text-white border-secondary">
                </div>
                <div class="col-md-6">
                    <label class="text-white font-weight-bold">Status <span class="text-warning">*</span></label>
                    <select name="status" class="form-control bg-dark text-white border-secondary" required>
                        <option value="Pending">Pending</option>
                        <option value="Active">Active</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="text-white font-weight-bold">Case Description / Strategy</label>
                <textarea name="description" rows="5" class="form-control bg-dark text-white border-secondary" placeholder="Describe details, goals and timeline..."></textarea>
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4">Create Case</button>
          </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/admin_footer.php'; ?>

<?php
// admin/submissions.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff']);
$admin_id = $_SESSION['admin_id'];

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_lead' && !$is_agent) {
    $agent_id = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
    $sub_id = (int)$_POST['submission_id'];
    $stmt = $pdo->prepare("UPDATE IFW_contact_submissions SET assigned_agent_id = ? WHERE id = ?");
    $stmt->execute([$agent_id, $sub_id]);
    header("Location: submissions.php");
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$has_all_access = has_permission('view_all_leads');

if (!$has_all_access && $is_agent) {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM IFW_contact_submissions WHERE assigned_agent_id = :aid");
    $stmt_count->execute([':aid' => $admin_id]);
    $total_submissions = $stmt_count->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT c.*, u.username as agent_name FROM IFW_contact_submissions c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id WHERE c.assigned_agent_id = :aid ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':aid', $admin_id, PDO::PARAM_INT);
} else {
    $total_submissions = $pdo->query("SELECT COUNT(*) FROM IFW_contact_submissions")->fetchColumn();
    $stmt = $pdo->prepare("SELECT c.*, u.username as agent_name FROM IFW_contact_submissions c LEFT JOIN IFW_users u ON c.assigned_agent_id = u.id ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset");
}

$total_pages = ceil($total_submissions / $per_page);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll();

$agents = [];
if (!$is_agent || $has_all_access) {
    try {
        $agents = $pdo->query("SELECT id, username, role FROM IFW_users ORDER BY username ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-inbox mr-2"></i>Form Submissions & Leads</h3>
            <p class="text-muted mb-0">View all client enquiries and dynamic contact form submissions.</p>
        </div>
    </div>
</div>

<div class="card shadow-sm border-secondary mb-4">
    <div class="card-header bg-dark text-warning border-secondary d-flex align-items-center justify-content-between">
        <span class="font-weight-bold">Total Submissions: <?php echo $total_submissions; ?></span>
    </div>
    <div class="card-body bg-dark text-white p-0">
        <?php if (empty($submissions)): ?>
            <div class="p-4 text-center text-muted">No form submissions received yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead style="background-color: #2a2526; color: #fecc56;">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): 
                            $data = json_decode($sub['submission_data'], true);
                        ?>
                            <tr>
                                <td>#<?= $sub['id'] ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($sub['created_at'])) ?></td>
                                <td><strong class="text-white"><?= htmlspecialchars($data['first_name'] ?? '') ?> <?= htmlspecialchars($data['last_name'] ?? '') ?></strong></td>
                                <td><a href="mailto:<?= htmlspecialchars($data['email'] ?? '') ?>" class="text-warning"><?= htmlspecialchars($data['email'] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($data['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($data['location'])): ?>
                                        <i class="fas fa-map-marker-alt text-danger mr-1"></i> <?= htmlspecialchars($data['location']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_agent && !$has_all_access): ?>
                                        <span class="badge badge-secondary"><?= htmlspecialchars($sub['agent_name'] ?? 'Unassigned') ?></span>
                                    <?php else: ?>
                                        <form method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="action" value="assign_lead">
                                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                            <select name="agent_id" class="form-control form-control-sm bg-dark text-white border-secondary mr-2" style="width: auto;">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($agents as $agent): ?>
                                                    <option value="<?= $agent['id'] ?>" <?= $sub['assigned_agent_id'] == $agent['id'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['username']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" data-toggle="modal" data-target="#viewModal<?= $sub['id'] ?>">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>

                                    <!-- View Details Modal -->
                                    <div class="modal fade" id="viewModal<?= $sub['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                            <div class="modal-content bg-dark border-secondary text-white">
                                                <div class="modal-header border-secondary text-warning">
                                                    <h5 class="modal-title font-weight-bold">Submission #<?= $sub['id'] ?> Details</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <table class="table table-dark table-bordered">
                                                        <?php foreach ($data as $k => $v): ?>
                                                            <tr>
                                                                <th style="width: 30%;" class="text-warning"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $k))) ?></th>
                                                                <td><?= nl2br(htmlspecialchars(is_array($v) ? implode(', ', $v) : $v)) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </table>
                                                </div>
                                                <div class="modal-footer border-secondary">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

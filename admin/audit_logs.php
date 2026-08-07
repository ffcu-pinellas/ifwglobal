<?php
// admin/audit_logs.php
require_once '../config.php';
require_once '../includes/functions.php';

require_superadmin();

// Fetch logs
$stmt = $pdo->query("
    SELECT l.*, u.username 
    FROM IFW_audit_logs l 
    LEFT JOIN IFW_users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 500
");
$logs = $stmt->fetchAll();
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-shield-alt mr-2"></i>System Audit Logs</h3>
            <p class="text-muted mb-0">Track administrative activities, security events, and user actions.</p>
        </div>
        <a href="export_logs.php" class="btn btn-warning font-weight-bold shadow-sm">
            <i class="fas fa-file-export mr-1"></i> Export Logs to CSV
        </a>
    </div>
</div>

<div class="card shadow-sm border-secondary mb-4">
    <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
        Recent Activity Logs (Last 500 records)
    </div>
    <div class="card-body bg-dark text-white p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle datatable">
                <thead style="background-color: #2a2526; color: #fecc56;">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>#<?= $log['id'] ?></td>
                            <td><?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td><strong class="text-white"><?= htmlspecialchars($log['username'] ?? 'System') ?></strong></td>
                            <td><span class="badge badge-warning text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td><?= htmlspecialchars($log['details']) ?></td>
                            <td><code><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

<?php
// admin/audit_logs.php
require_once '../config.php';
require_once '../includes/functions.php';

require_superadmin();

// Fetch logs with proper user resolution
$stmt = $pdo->query("
    SELECT l.*, 
           CASE 
               WHEN l.user_type = 'client' THEN (SELECT CONCAT(first_name, ' ', last_name, ' [Client #', id, ']') FROM IFW_clients WHERE id = l.user_id)
               ELSE COALESCE(NULLIF(u.full_name, ''), u.username, 'System/Admin')
           END as user_display_name
    FROM IFW_audit_logs l 
    LEFT JOIN IFW_users u ON (l.user_id = u.id AND (l.user_type = 'admin' OR l.user_type IS NULL))
    ORDER BY l.created_at DESC, l.id DESC 
    LIMIT 500
");
$logs = $stmt->fetchAll();
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="row">
    <div class="col-12 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-shield-alt mr-2"></i>System & Client Audit Logs</h3>
            <p class="text-muted mb-0">Chronological security events, logins, cryptographic signatures, and admin activities.</p>
        </div>
        <a href="export_logs.php" class="btn btn-warning font-weight-bold text-dark shadow-sm">
            <i class="fas fa-file-export mr-1"></i> Export Logs to CSV
        </a>
    </div>
</div>

<div class="card shadow-sm border-secondary mb-4">
    <div class="card-header bg-dark text-warning border-secondary font-weight-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-alt mr-2"></i>Audit Trail Activity (Chronological Order &bull; Newest First)</span>
        <span class="badge badge-warning text-dark font-weight-bold"><?= count($logs) ?> Records</span>
    </div>
    <div class="card-body bg-dark text-white p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle" id="auditLogsTable">
                <thead style="background-color: #1a1e27; color: #fecc56;">
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 20%;">Timestamp (UTC)</th>
                        <th style="width: 18%;">Actor / User</th>
                        <th style="width: 18%;">Action</th>
                        <th>Details</th>
                        <th style="width: 14%;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center p-4 text-muted">No audit activity logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><span class="badge badge-secondary">#<?= $log['id'] ?></span></td>
                                <td data-order="<?= strtotime($log['created_at']) ?>">
                                    <strong class="text-light"><?= date('M j, Y', strtotime($log['created_at'])) ?></strong>
                                    <small class="text-muted d-block"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($log['user_type'] === 'client'): ?>
                                        <span class="badge badge-info text-dark font-weight-bold"><i class="fas fa-user mr-1"></i>Client</span>
                                        <strong class="text-white d-block mt-1"><?= htmlspecialchars($log['user_display_name'] ?? 'Client #'.$log['user_id']) ?></strong>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-user-shield mr-1"></i>Staff</span>
                                        <strong class="text-white d-block mt-1"><?= htmlspecialchars($log['user_display_name'] ?? 'Admin') ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-dark border border-warning text-warning px-2 py-1"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td><div class="small text-light" style="max-width: 420px; word-break: break-word;"><?= htmlspecialchars($log['details']) ?></div></td>
                                <td><code><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if ($.fn.DataTable && $('#auditLogsTable').length) {
        $('#auditLogsTable').DataTable({
            order: [[ 0, "desc" ]],
            pageLength: 50,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search audit logs..."
            }
        });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>

<?php
require_once '../config.php';
require_once '../includes/functions.php';

require_admin_login();

// Set session variables for layout
$_SESSION['role'] = $_SESSION['admin_role'] ?? 'admin';
$_SESSION['user_name'] = $_SESSION['admin_username'] ?? 'Admin';

$stats = [
    'clients' => $pdo->query("SELECT COUNT(*) FROM IFW_clients")->fetchColumn(),
    'active_cases' => $pdo->query("SELECT COUNT(*) FROM IFW_clients WHERE status != 'Recovery'")->fetchColumn(),
    'resolved_cases' => $pdo->query("SELECT COUNT(*) FROM IFW_clients WHERE status = 'Recovery'")->fetchColumn(),
    'submissions' => $pdo->query("SELECT COUNT(*) FROM IFW_contact_submissions")->fetchColumn(),
    'testimonials' => $pdo->query("SELECT COUNT(*) FROM IFW_testimonials WHERE status = 'active'")->fetchColumn()
];

// Calculate SLA Breached Cases
$sla_breached_count = 0;
$slaStmt = $pdo->query("
    SELECT c.id, MAX(m.created_at) as last_client_msg_time
    FROM IFW_clients c
    JOIN IFW_messages m ON c.id = m.client_id
    WHERE m.sender = 'client'
    GROUP BY c.id
");
$slaClients = $slaStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($slaClients as $sc) {
    $rStmt = $pdo->prepare("SELECT id FROM IFW_messages WHERE client_id = ? AND sender = 'admin' AND created_at > ?");
    $rStmt->execute([$sc['id'], $sc['last_client_msg_time']]);
    if (!$rStmt->fetch()) {
        if (time() - strtotime($sc['last_client_msg_time']) > 86400) {
            $sla_breached_count++;
        }
    }
}
$stats['sla_breached'] = $sla_breached_count;

// Fetch recent submissions
$recent_submissions = $pdo->query("SELECT id, submission_data, created_at FROM IFW_contact_submissions ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<?php require_once '../includes/admin_header.php'; ?>
<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- PAGE CONTENT -->
<div class="row">
    <div class="col-12 mb-4">
        <h4 class="text-dark">Dashboard Overview</h4>
        <p class="text-muted">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>!</p>
    </div>

    <!-- STAT CARDS -->
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="card card-statistics h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="statistics-icon text-primary bg-primary-light border-primary">
                        <i class="fas fa-users fs-3"></i>
                    </div>
                    <div class="statistics-content text-right">
                        <h5 class="mb-0 text-muted">Total Clients</h5>
                        <h3 class="mb-0 text-dark"><?php echo $stats['clients']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="card card-statistics h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="statistics-icon text-info bg-info-light border-info">
                        <i class="fas fa-folder-open fs-3"></i>
                    </div>
                    <div class="statistics-content text-right">
                        <h5 class="mb-0 text-muted">Active Cases</h5>
                        <h3 class="mb-0 text-dark"><?php echo $stats['active_cases']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="card card-statistics h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="statistics-icon text-success bg-success-light border-success">
                        <i class="fas fa-check-circle fs-3"></i>
                    </div>
                    <div class="statistics-content text-right">
                        <h5 class="mb-0 text-muted">Resolved Cases</h5>
                        <h3 class="mb-0 text-dark"><?php echo $stats['resolved_cases']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
        <div class="card card-statistics h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="statistics-icon text-warning bg-warning-light border-warning">
                        <i class="fas fa-inbox fs-3"></i>
                    </div>
                    <div class="statistics-content text-right">
                        <h5 class="mb-0 text-muted">Submissions</h5>
                        <h3 class="mb-0 text-dark"><?php echo $stats['submissions']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['sla_breached'] > 0): ?>
<div class="row">
    <div class="col-12 mb-4">
        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="material-icons mr-3">warning</i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">SLA Breach Alert</h6>
                <p class="mb-0 small">There are <strong><?php echo $stats['sla_breached']; ?></strong> active cases where clients have been waiting more than 24 hours for a response. Please review the live chat immediately.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Recent Enquiries & Submissions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Details</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_submissions)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted p-4">No recent submissions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_submissions as $sub): 
                                    $data = json_decode($sub['submission_data'], true);
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($sub['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($data['first_name'] ?? ''); ?> <?php echo htmlspecialchars($data['last_name'] ?? ''); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($data['email'] ?? ''); ?></small>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-sm btn-outline-warning" data-toggle="modal" data-target="#subModal<?php echo $sub['id']; ?>">View</button>
                                        
                                        <!-- Modal -->
                                        <div class="modal fade text-left" id="subModal<?php echo $sub['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-primary">Submission Details</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <ul class="list-group list-group-flush">
                                                            <?php foreach ($data as $key => $val): ?>
                                                                <li class="list-group-item px-0">
                                                                    <span class="text-muted small d-block"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></span>
                                                                    <span class="font-weight-bold"><?php echo nl2br(htmlspecialchars($val)); ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Cases Overview</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="casesChart" width="100%" height="100%"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('casesChart')) {
        const ctx = document.getElementById('casesChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Active Cases', 'Resolved Cases'],
                datasets: [{
                    data: [<?php echo $stats['active_cases']; ?>, <?php echo $stats['resolved_cases']; ?>],
                    backgroundColor: ['#222', '#fecc56'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutoutPercentage: 70,
                legend: { position: 'bottom' }
            }
        });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>






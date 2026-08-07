<?php
// admin/export_logs.php
require_once '../config.php';
require_once '../includes/functions.php';

require_superadmin();

$stmt = $pdo->query("
    SELECT l.created_at as Timestamp, u.username as User, l.action as Action, l.details as Details, l.ip_address as IP
    FROM IFW_audit_logs l 
    LEFT JOIN IFW_users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

if (!empty($logs)) {
    fputcsv($output, array_keys($logs[0]));
    foreach ($logs as $row) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
?>





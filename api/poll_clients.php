<?php
// api/poll_clients.php — lightweight poll for admin client directory live updates
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$is_agent = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['agent', 'staff'], true);
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

try {
    if ($is_agent) {
        $meta_stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, MAX(id) AS max_id FROM IFW_clients WHERE assigned_agent_id = ?");
        $meta_stmt->execute([$admin_id]);
    } else {
        $meta_stmt = $pdo->query("SELECT COUNT(*) AS cnt, MAX(id) AS max_id FROM IFW_clients");
    }
    $meta = $meta_stmt->fetch() ?: ['cnt' => 0, 'max_id' => 0];

    $latest_ts = (int)$meta['max_id'];
    try {
        if ($is_agent) {
            $ts_stmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(MAX(COALESCE(updated_at, created_at))) FROM IFW_clients WHERE assigned_agent_id = ?");
            $ts_stmt->execute([$admin_id]);
        } else {
            $ts_stmt = $pdo->query("SELECT UNIX_TIMESTAMP(MAX(COALESCE(updated_at, created_at))) FROM IFW_clients");
        }
        $db_ts = (int)$ts_stmt->fetchColumn();
        if ($db_ts > 0) $latest_ts = $db_ts;
    } catch (Exception $e) {}

    $changed = ($since <= 0) ? false : ($latest_ts > $since);

    echo json_encode([
        'status' => 'success',
        'changed' => $changed,
        'total' => (int)$meta['cnt'],
        'max_id' => (int)$meta['max_id'],
        'latest_ts' => $latest_ts,
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not poll clients']);
}

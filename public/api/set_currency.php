<?php
// api/set_currency.php - Client Preferred Currency Switcher API
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/currency_helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currency = strtoupper(trim($_POST['currency'] ?? $_GET['currency'] ?? ''));
$currencies = get_available_currencies();

if (!isset($currencies[$currency])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid currency selected']);
    exit;
}

// Store in Session & Cookie
$_SESSION['preferred_currency'] = $currency;
setcookie('client_currency', $currency, time() + (86400 * 365), '/');

// If client is logged in, save to database
$client_id = $_SESSION['client_portal_id'] ?? 0;
if ($client_id) {
    try {
        $stmt = $pdo->prepare("UPDATE IFW_clients SET preferred_currency = ? WHERE id = ?");
        $stmt->execute([$currency, $client_id]);
    } catch (Exception $e) {}
}

echo json_encode([
    'status' => 'success',
    'currency' => $currency,
    'symbol' => $currencies[$currency]['symbol'],
    'flag' => $currencies[$currency]['flag'],
    'name' => $currencies[$currency]['name']
]);
exit;

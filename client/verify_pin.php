<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// client/verify_pin.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['pending_client_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_pin = trim($_POST['pin'] ?? '');

    if (empty($entered_pin)) {
        $error = "Please enter your PIN.";
    } else {
        $stmt = $pdo->prepare("SELECT pin_hash FROM IFW_clients WHERE id = ?");
        $stmt->execute([$_SESSION['pending_client_id']]);
        $client = $stmt->fetch();
        
        if ($client && password_verify($entered_pin, $client['pin_hash'])) {
            // Fully logged in
            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_portal_id'] = $_SESSION['pending_client_id'];
            $_SESSION['client_name'] = $_SESSION['pending_client_name'];
            
            // Cleanup
            unset($_SESSION['pending_client_id']);
            unset($_SESSION['pending_client_email']);
            unset($_SESSION['pending_client_name']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid PIN.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter PIN - IFW Global</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1f1b1c; color: #fff; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); width: 100%; max-width: 400px; color: #000; }
        .btn-warning { background-color: #fecc56; border: none; font-weight: bold; }
        .btn-warning:hover { background-color: #eda701; }
    </style>
</head>
<body>
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>


<div class="login-box text-center">
    <h3 class="mb-4"><i class="bi bi-shield-lock-fill text-warning"></i> Enter Security PIN</h3>
    <p class="text-muted mb-4">Please enter your 4-digit security PIN to continue.</p>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <input type="password" class="form-control form-control-lg text-center fw-bold" id="pin" name="pin" placeholder="****" required maxlength="4" autofocus>
        </div>
        <button type="submit" class="btn btn-warning w-100 py-2">Unlock Portal</button>
    </form>
    <div class="mt-3">
        <small class="text-muted"><a href="login.php" class="text-decoration-none">Back to Login</a></small>
    </div>
</div>

</body>
</html>
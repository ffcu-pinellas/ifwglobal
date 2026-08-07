<?php
// admin/verify_pin.php
require_once '../config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['pending_admin_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if (empty($pin)) {
        $error = "Please enter your 4-digit PIN.";
    } else {
        $stmt = $pdo->prepare("SELECT pin_hash, role FROM IFW_users WHERE id = ?");
        $stmt->execute([$_SESSION['pending_admin_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($pin, $user['pin_hash'])) {
            // Success!
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $_SESSION['pending_admin_id'];
            $_SESSION['admin_username'] = $_SESSION['pending_admin_username'];
            $_SESSION['admin_role'] = $user['role'] ?? 'admin';
            
            // Clean up pending session vars
            unset($_SESSION['pending_admin_id']);
            unset($_SESSION['pending_admin_username']);
            
            log_audit_action($pdo, $_SESSION['admin_id'], 'LOGIN', 'Successful portal login');
            
            header("Location: index.php");
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
    <title>Verify PIN - IFW Global Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: #fff; text-align: center; }
        .pin-input { font-size: 2rem; text-align: center; letter-spacing: 0.5rem; }
    </style>
</head>
<body>

<div class="login-card">
    <h4 class="mb-3">Two-Factor Authentication</h4>
    <p class="text-muted mb-4">Please enter your 4-digit security PIN.</p>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="verify_pin.php">
        <div class="mb-4">
            <input type="password" class="form-control pin-input" id="pin" name="pin" maxlength="4" pattern="\d{4}" required autofocus autocomplete="off" placeholder="••••">
        </div>
        <button type="submit" class="btn btn-primary w-100">Verify & Login</button>
        <a href="login.php" class="d-block mt-3 text-decoration-none text-muted">Cancel and go back</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






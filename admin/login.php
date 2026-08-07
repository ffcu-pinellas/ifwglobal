<?php
// admin/login.php
require_once '../config.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM IFW_users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Step 1 success. Need PIN verification.
            $_SESSION['pending_admin_id'] = $user['id'];
            $_SESSION['pending_admin_username'] = $user['username'];
            header("Location: verify_pin.php");
            exit;
        } else {
            $error = "Invalid credentials.";
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
    <title>Admin Login - IFW Global</title>
    <!-- Keep existing design first, use Bootstrap 5 for admin panel -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: #fff; }
        .login-card img { max-width: 150px; margin-bottom: 2rem; }
    </style>
</head>
<body>

<div class="login-card text-center">
    <!-- Assuming logo is dynamically fetched, but for login we can use a hardcoded or settings-based one -->
    <img src="../media/gallery/IFW-Podcast-Screen.jpg" alt="IFW Global" class="img-fluid">
    <h4 class="mb-4">Admin Access</h4>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="mb-3 text-start">
            <label for="identifier" class="form-label">Username or Email</label>
            <input type="text" class="form-control" id="identifier" name="identifier" required autofocus>
        </div>
        <div class="mb-3 text-start">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">Next Step</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






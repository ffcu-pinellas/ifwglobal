<?php
// admin/login.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, email FROM IFW_users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Step 1 success. Need PIN verification.
            $_SESSION['pending_admin_id'] = $user['id'];
            $_SESSION['pending_admin_username'] = $user['username'];

            // Clear any stale lockout states from previous admin sessions
            unset(
                $_SESSION['admin_pin_failures'],
                $_SESSION['admin_pin_lockout_until'],
                $_SESSION['admin_otp_failures'],
                $_SESSION['admin_otp_lockout_until'],
                $_SESSION['admin_pin_failures_' . $user['id']],
                $_SESSION['admin_pin_lockout_until_' . $user['id']],
                $_SESSION['admin_otp_failures_' . $user['id']],
                $_SESSION['admin_otp_lockout_until_' . $user['id']]
            );

            header("Location: verify_pin.php");
            exit;
        } else {
            $error = "Invalid administrator credentials. Please check your username/email and password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', '1') == '0'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
<style id='gdpr-global-suppress'>#gdpr-cookie-consent-bar, #gdpr-cookie-consent-show-again, #cookie_action_settings, .gdpr_action_button, .gdpr-modal, .cli-modal, #cliModal, [id*='gdpr'], [class*='gdpr-cookie'], [class*='cli-'] { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; height: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command Center Login | IFW Global</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0b0e14;
            background-image: radial-gradient(circle at top right, rgba(254, 204, 86, 0.05), transparent 400px),
                              radial-gradient(circle at bottom left, rgba(15, 23, 42, 0.8), transparent 500px);
            color: #f8fafc;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: #151a23;
            border: 1px solid rgba(254, 204, 86, 0.25);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6), 0 0 20px rgba(254, 204, 86, 0.08);
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #fecc56, #f59e0b, #fecc56);
        }
        .brand-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        .form-control {
            background-color: #0d1117 !important;
            border: 1px solid #334155 !important;
            color: #ffffff !important;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #fecc56 !important;
            box-shadow: 0 0 8px rgba(254, 204, 86, 0.3) !important;
            color: #ffffff !important;
        }
        .form-control::placeholder {
            color: #94a3b8 !important;
            opacity: 1 !important;
        }
        .form-control:-ms-input-placeholder {
            color: #94a3b8 !important;
        }
        .form-control::-ms-input-placeholder {
            color: #94a3b8 !important;
        }
        .btn-warning {
            background: linear-gradient(135deg, #fecc56, #f59e0b);
            border: none;
            color: #000000;
            font-weight: 800;
            padding: 13px;
            border-radius: 8px;
            font-size: 14.5px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #e5b443, #d9a732);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(254, 204, 86, 0.25);
            color: #000000;
        }
        .security-badge {
            background: rgba(254, 204, 86, 0.08);
            border: 1px solid rgba(254, 204, 86, 0.2);
            border-radius: 8px;
            padding: 10px;
            font-size: 11.5px;
            color: #fecc56;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <a href="/">
            <img src="/media/logos/logo.svg" alt="IFW Global" class="brand-logo" onerror="this.onerror=null; this.src='/media/gallery/IFW-Podcast-Screen.jpg';">
        </a>
        <h5 class="text-warning font-weight-bold mt-2 mb-1" style="letter-spacing: 0.5px;">COMMAND CENTER ACCESS</h5>
        <p class="small mb-0" style="color: #cbd5e1 !important;">Administrative Intelligence &amp; Case Directorate</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 font-weight-bold text-center py-2 mb-3" style="font-size: 13px; background: rgba(239,68,68,0.18); color: #fca5a5;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="mb-3 text-start">
            <label for="identifier" class="form-label text-white font-weight-bold small"><i class="fas fa-user text-warning me-2"></i>Username or Email</label>
            <input type="text" class="form-control" id="identifier" name="identifier" required autofocus placeholder="Enter username or email">
        </div>
        <div class="mb-4 text-start">
            <label for="password" class="form-label text-white font-weight-bold small"><i class="fas fa-lock text-warning me-2"></i>Password</label>
            <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
        </div>
        <button type="submit" class="btn btn-warning w-100 shadow">
            <i class="fas fa-arrow-right me-2"></i> Continue to 2FA PIN Gate
        </button>
    </form>

    <div class="security-badge">
        <i class="fas fa-shield-alt"></i>
        <span>Restricted Access &bull; All Transactions Logged &amp; Monitored</span>
    </div>

    <div class="text-center mt-4">
        <small><a href="../client/login.php" class="text-decoration-none font-weight-bold" style="color: #cbd5e1;"><i class="fas fa-user-shield me-1"></i> Client Portal Login</a></small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

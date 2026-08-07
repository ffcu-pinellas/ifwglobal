<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// client/verify_otp.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['pending_client_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Handle Resend Request
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $new_otp = rand(100000, 999999);
    $_SESSION['otp_code'] = $new_otp;
    $_SESSION['otp_time'] = time();
    
    $email = $_SESSION['pending_client_email'];
    $name = $_SESSION['pending_client_name'];
    
    $subject = "Your New IFW Portal Verification Code";
    $body = "Your new 6-digit verification code is: {$new_otp}\nThis code will expire in 10 minutes.";
    
    @send_html_email($email, $subject, "<h2>IFW Global Security Verification</h2><p>Hello {$name},</p><p>Your new 6-digit login verification code is: <strong style='font-size: 1.5rem; color: #0b2e59;'>{$new_otp}</strong></p><p>This code will expire in 10 minutes.</p>");
    $success = "A new 6-digit verification code has been dispatched to your email.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (empty($entered_otp)) {
        $error = "Please enter the 6-digit verification code.";
    } elseif (time() - $_SESSION['otp_time'] > 600) {
        $error = "The verification code has expired. Please request a new code below.";
    } elseif ($entered_otp == $_SESSION['otp_code']) {
        // OTP is correct
        $stmt = $pdo->prepare("SELECT pin_hash FROM IFW_clients WHERE id = ?");
        $stmt->execute([$_SESSION['pending_client_id']]);
        $client = $stmt->fetch();
        
        if (!empty($client['pin_hash'])) {
            header("Location: verify_pin.php");
            exit;
        } else {
            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_portal_id'] = $_SESSION['pending_client_id'];
            $_SESSION['client_name'] = $_SESSION['pending_client_name'];
            
            unset($_SESSION['pending_client_id']);
            unset($_SESSION['pending_client_email']);
            unset($_SESSION['pending_client_name']);
            unset($_SESSION['otp_code']);
            unset($_SESSION['otp_time']);
            
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "Invalid verification code. Please check your email and try again.";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - IFW Global Client Portal</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #0d0d0e;
            background-image: radial-gradient(circle at 50% 30%, rgba(254, 204, 86, 0.08) 0%, rgba(13, 13, 14, 0.95) 70%);
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: #181516;
            border: 1px solid rgba(254, 204, 86, 0.3);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7);
            width: 100%;
            max-width: 440px;
            padding: 40px 35px;
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #fecc56, #f3c14b, #fecc56);
        }
        .brand-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        .otp-input {
            background-color: #242021 !important;
            border: 1px solid #3d3738 !important;
            color: #fecc56 !important;
            padding: 14px;
            border-radius: 8px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 8px;
            text-align: center;
        }
        .otp-input:focus {
            border-color: #fecc56 !important;
            box-shadow: 0 0 10px rgba(254, 204, 86, 0.3) !important;
        }
        .btn-warning {
            background: linear-gradient(90deg, #fecc56, #f3c14b);
            border: none;
            color: #000000;
            font-weight: 700;
            padding: 13px;
            border-radius: 8px;
            font-size: 15px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            background: linear-gradient(90deg, #e5b443, #d9a732);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(254, 204, 86, 0.2);
        }
        .security-badge {
            background: rgba(254, 204, 86, 0.08);
            border: 1px solid rgba(254, 204, 86, 0.2);
            border-radius: 8px;
            padding: 10px;
            font-size: 11px;
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
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>


<div class="login-card text-center">
    <div class="mb-4">
        <a href="/">
            <img src="/media/logos/logo.svg" alt="IFW Global" class="brand-logo" onerror="this.onerror=null; this.src='/media/gallery/IFW-Podcast-Screen.jpg';">
        </a>
        <h5 class="text-warning font-weight-bold mt-2 mb-1" style="letter-spacing: 0.5px;">2-STEP SECURITY VERIFICATION</h5>
        <p class="text-muted small mb-0">We sent a 6-digit authentication code to:<br><strong class="text-white"><?= htmlspecialchars($_SESSION['pending_client_email']) ?></strong></p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 font-weight-bold py-2 mb-3" style="font-size: 13px;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success border-0 font-weight-bold py-2 mb-3" style="font-size: 13px;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <input type="text" class="form-control otp-input" id="otp" name="otp" placeholder="000000" required maxlength="6" autofocus autocomplete="off">
        </div>
        <button type="submit" class="btn btn-warning w-100 shadow mb-3">
            <i class="fas fa-shield-check me-2"></i> Verify Code & Access Portal
        </button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-secondary">
        <a href="verify_otp.php?action=resend" class="text-warning text-decoration-none small font-weight-bold"><i class="fas fa-paper-plane me-1"></i> Resend Verification Code</a>
        <a href="login.php" class="text-muted text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
    </div>

    <div class="security-badge">
        <i class="fas fa-user-shield"></i>
        <span>Encrypted 2FA Session &bull; IFW Global Private Intelligence</span>
    </div>
</div>

</body>
</html>
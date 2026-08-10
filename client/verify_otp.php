<?php
// public/client/verify_otp.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
require_once $dir . '/includes/mailer.php';

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
<?php if (get_setting($pdo, 'display_phone_numbers', 'show') === 'hide'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
.footer__address, .footer__details, address, .contact-details { display: none !important; visibility: hidden !important; }
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
        }
        .auth-card {
            background-color: #171719;
            border: 1px solid rgba(254, 204, 86, 0.15);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 485px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        .brand-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-logo h3 {
            color: #fecc56;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #fecc56 0%, #f1b834 100%);
            border: none;
            color: #000000;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #f1b834 0%, #d89e20 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(254, 204, 86, 0.25);
            color: #000000;
        }
        .form-control {
            background-color: #212124;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus {
            background-color: #26262a;
            border-color: #fecc56;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(254, 204, 86, 0.15);
        }
        .text-warning-custom {
            color: #fecc56;
            text-decoration: none;
            font-weight: 500;
        }
        .text-warning-custom:hover {
            color: #f1b834;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand-logo">
        <h3><i class="fas fa-shield-alt"></i> IFW GLOBAL</h3>
    </div>
    
    <div class="text-center mb-4">
        <h5 class="fw-bold text-white mb-2">Security Verification</h5>
        <p class="text-white small mb-0" style="color: #ffffff !important; font-size: 0.95rem; line-height: 1.5; opacity: 0.95;">We've sent a 6-digit verification code to your registered email address.</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 text-center py-2 mb-3" style="font-size: 0.9rem; background-color: rgba(220, 53, 69, 0.2); color: #ff99a8;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success border-0 text-center py-2 mb-3" style="font-size: 0.9rem; background-color: rgba(40, 167, 69, 0.2); color: #a3cfbb;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-4">
            <label class="form-label text-white fw-bold d-block text-center mb-2" style="color: #ffffff !important; font-size: 0.95rem; letter-spacing: 0.5px;">Enter Verification Code</label>
            <input type="text" name="otp" class="form-control text-center fs-4 fw-bold letter-spacing-5 bg-dark text-white border-secondary" maxlength="6" placeholder="000000" required autofocus style="height: 52px; font-size: 1.5rem !important; color: #fecc56 !important; border-color: #555 !important;">
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mb-3 fw-bold py-2 shadow"><i class="fas fa-lock-open me-2"></i>Verify & Proceed</button>
    </form>
    
    <div class="text-center mt-3">
        <p class="small text-white-50 mb-1">Didn't receive the code?</p>
        <a href="verify_otp.php?action=resend" class="text-warning-custom small fw-bold"><i class="fas fa-redo me-1"></i>Resend Code</a>
    </div>
</div>

</body>
</html>
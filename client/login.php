<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// client/login.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please enter both your email address and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE email = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if ($client && $client['password_hash'] && password_verify($password, $client['password_hash'])) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['pending_client_id'] = $client['id'];
            $_SESSION['pending_client_email'] = $client['email'];
            $_SESSION['pending_client_name'] = $client['first_name'] . ' ' . $client['last_name'];
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_time'] = time();

            // Send OTP via Email
            if (file_exists('../vendor/autoload.php')) {
                require_once '../vendor/autoload.php';
            }
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $envPath = '../.env';
                    if (file_exists($envPath)) {
                        $env = parse_ini_file($envPath);
                        if (!empty($env['MAIL_HOST'])) {
                            $mail->isSMTP();
                            $mail->Host       = $env['MAIL_HOST'];
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $env['MAIL_USERNAME'];
                            $mail->Password   = $env['MAIL_PASSWORD'];
                            $mail->SMTPSecure = $env['MAIL_ENCRYPTION'] == 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = $env['MAIL_PORT'];
                        }
                        $fromAddress = !empty($env['MAIL_FROM_ADDRESS']) ? $env['MAIL_FROM_ADDRESS'] : 'no-reply@ifwglobal.com';
                        $fromName = !empty($env['MAIL_FROM_NAME']) ? $env['MAIL_FROM_NAME'] : 'IFW Global Security';
                    } else {
                        $fromAddress = 'no-reply@ifwglobal.com';
                        $fromName = 'IFW Global Security';
                    }

                    $mail->setFrom($fromAddress, $fromName);
                    $mail->addAddress($client['email']);
                    $mail->Subject = "Your Secure IFW Portal Login Verification Code";
                    $mail->Body    = "Your secure login code is: {$otp}\nThis code will expire in 10 minutes.";
                    $mail->send();
                } catch (Exception $e) {
                    @mail($client['email'], "Your Secure IFW Portal Login Verification Code", "Your secure login code is: {$otp}\nThis code will expire in 10 minutes.", "From: no-reply@ifwglobal.com");
                }
            } else {
                @mail($client['email'], "Your Secure IFW Portal Login Verification Code", "Your secure login code is: {$otp}\nThis code will expire in 10 minutes.", "From: no-reply@ifwglobal.com");
            }

            header("Location: verify_otp.php");
            exit;
        } else {
            $error = "Invalid client credentials. Please verify your email and password.";
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encrypted Client Portal - IFW Global Private Intelligence</title>
    
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
        .form-control {
            background-color: #242021 !important;
            border: 1px solid #3d3738 !important;
            color: #ffffff !important;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #fecc56 !important;
            box-shadow: 0 0 8px rgba(254, 204, 86, 0.3) !important;
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


<div class="login-card">
    <div class="text-center mb-4">
        <a href="/">
            <img src="/media/logos/logo.svg" alt="IFW Global" class="brand-logo" onerror="this.onerror=null; this.src='/media/gallery/IFW-Podcast-Screen.jpg';">
        </a>
        <h5 class="text-warning font-weight-bold mt-2 mb-1" style="letter-spacing: 0.5px;">CLIENT CASE PORTAL</h5>
        <p class="text-muted small mb-0">Secure Encrypted Case Management Gateway</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 font-weight-bold text-center py-2 mb-3" style="font-size: 13px;">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="email" class="form-label text-white font-weight-bold small"><i class="fas fa-envelope text-warning me-2"></i>Registered Email Address</label>
            <input type="email" class="form-control" id="email" name="email" required placeholder="client@example.com">
        </div>
        <div class="mb-4">
            <label for="password" class="form-label text-white font-weight-bold small"><i class="fas fa-lock text-warning me-2"></i>Password</label>
            <input type="password" class="form-control" id="password" name="password" required placeholder="Enter portal password">
        </div>
        <button type="submit" class="btn btn-warning w-100 shadow">
            <i class="fas fa-shield-halved me-2"></i> Access Encrypted Portal
        </button>
    </form>

    <div class="security-badge">
        <i class="fas fa-lock"></i>
        <span>256-Bit SSL Encrypted Session &bull; Private & Confidential</span>
    </div>

    <div class="text-center mt-4">
        <small><a href="../index.php" class="text-muted text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Return to Main Website</a></small>
    </div>
</div>

</body>
</html>
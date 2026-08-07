<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// client/verify_otp.php
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
$success = '';

// Handle Resend Code
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $otp = rand(100000, 999999);
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_time'] = time();

    require_once '../includes/mailer.php';
    $to = $_SESSION['pending_client_email'];
    $name = $_SESSION['pending_client_name'];
    $html = "<h2 style='color:#1f1b1c;'>IFW Global — New Verification Code</h2>
             <p>Hello <strong>$name</strong>,</p>
             <p>You requested a new verification code. Your new code is:</p>
             <div style='background:#fecc56;padding:20px;text-align:center;border-radius:8px;margin:20px 0;'>
                 <span style='font-size:2.5rem;font-weight:900;letter-spacing:12px;color:#000;'>$otp</span>
             </div>
             <p style='color:#888;font-size:12px;'>This code expires in 10 minutes. If you did not request this, please ignore this email.</p>";
    send_html_email($to, "Your New IFW Global Verification Code", $html);
    $success = "A new verification code has been sent to your email.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (empty($entered_otp)) {
        $error = "Please enter the 6-digit verification code sent to your email.";
    } elseif (time() - ($_SESSION['otp_time'] ?? 0) > 600) {
        $error = "Your code has expired. Please <a href='?resend=1' class='text-warning'>request a new code</a>.";
    } elseif ($entered_otp == $_SESSION['otp_code']) {
        // OTP is correct — check for PIN
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
            unset($_SESSION['pending_client_id'], $_SESSION['pending_client_email'], $_SESSION['pending_client_name'], $_SESSION['otp_code'], $_SESSION['otp_time']);
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "Invalid code. Please check your email and try again.";
    }
}

$masked_email = '';
if (isset($_SESSION['pending_client_email'])) {
    $e = $_SESSION['pending_client_email'];
    $parts = explode('@', $e);
    $masked_email = substr($parts[0], 0, 3) . str_repeat('*', max(strlen($parts[0]) - 3, 3)) . '@' . ($parts[1] ?? '');
}

$otp_age_seconds = isset($_SESSION['otp_time']) ? (time() - $_SESSION['otp_time']) : 0;
$remaining = max(0, 600 - $otp_age_seconds);
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
    <title>Security Verification — IFW Global Client Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0d0d0e;
            background-image: radial-gradient(circle at 50% 20%, rgba(254,204,86,0.07) 0%, transparent 70%);
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .otp-card {
            background: #181516;
            border: 1px solid rgba(254,204,86,0.25);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
            width: 100%;
            max-width: 460px;
            padding: 44px 40px 36px;
            position: relative;
            overflow: hidden;
        }
        .otp-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #fecc56, #f3c14b, #fecc56);
        }
        .otp-card::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(254,204,86,0.06), transparent 70%);
            pointer-events: none;
        }
        .shield-icon {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, rgba(254,204,86,0.15), rgba(254,204,86,0.05));
            border: 2px solid rgba(254,204,86,0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 22px;
            position: relative;
        }
        .shield-icon i {
            font-size: 28px;
            color: #fecc56;
        }
        h2 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .subtitle {
            color: #888;
            font-size: 13px;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .subtitle strong { color: #fecc56; font-weight: 600; }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(220,53,69,0.12);
            border: 1px solid rgba(220,53,69,0.3);
            color: #ff6b7a;
        }
        .alert-success {
            background: rgba(40,167,69,0.12);
            border: 1px solid rgba(40,167,69,0.3);
            color: #4cdc7a;
        }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 12px;
        }
        .otp-inputs input {
            width: 50px; height: 58px;
            background: #242021;
            border: 2px solid #3d3738;
            border-radius: 10px;
            color: #fecc56;
            font-size: 22px;
            font-weight: 800;
            text-align: center;
            font-family: 'Montserrat', monospace;
            transition: border-color 0.25s, box-shadow 0.25s;
            caret-color: #fecc56;
            outline: none;
        }
        .otp-inputs input:focus {
            border-color: #fecc56;
            box-shadow: 0 0 0 3px rgba(254,204,86,0.18);
            background: #2c2829;
        }
        .otp-inputs input.filled {
            border-color: rgba(254,204,86,0.5);
        }
        .timer-row {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-bottom: 24px;
        }
        .timer-row #timer { color: #fecc56; font-weight: 700; }
        .timer-row #timer.expired { color: #ff6b7a; }
        .btn-verify {
            width: 100%;
            background: linear-gradient(90deg, #fecc56, #f3c14b);
            border: none;
            color: #000;
            font-weight: 700;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }
        .btn-verify:hover {
            background: linear-gradient(90deg, #e5b443, #d9a732);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(254,204,86,0.25);
        }
        .btn-verify:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .bottom-links {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .bottom-links a {
            color: #666;
            font-size: 12px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .bottom-links a:hover { color: #fecc56; }
        .resend-btn {
            background: none;
            border: none;
            color: #fecc56;
            font-size: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .resend-btn:disabled { color: #555; cursor: not-allowed; text-decoration: none; }
        .security-note {
            margin-top: 24px;
            padding: 10px 14px;
            background: rgba(254,204,86,0.06);
            border: 1px solid rgba(254,204,86,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #888;
        }
        .security-note i { color: #fecc56; font-size: 12px; flex-shrink: 0; }
    </style>
</head>
<body>
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>

<div class="otp-card">
    <div class="shield-icon">
        <i class="fas fa-shield-halved"></i>
    </div>
    
    <h2>Two-Factor Verification</h2>
    <p class="subtitle">
        We sent a 6-digit security code to<br>
        <strong><?= htmlspecialchars($masked_email) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form id="otpForm" method="POST" action="">
        <div class="otp-inputs" id="otpInputGroup">
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off" autofocus>
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            <input type="text" maxlength="1" class="otp-digit" pattern="[0-9]" inputmode="numeric" autocomplete="off">
        </div>
        <input type="hidden" name="otp" id="otpHidden">
        
        <div class="timer-row">
            Code expires in <span id="timer"><?= sprintf('%d:%02d', floor($remaining/60), $remaining % 60) ?></span>
        </div>
        
        <button type="submit" class="btn-verify" id="verifyBtn" disabled>
            <i class="fas fa-lock me-2"></i> Verify Identity
        </button>
    </form>
    
    <div class="bottom-links">
        <a href="login.php"><i class="fas fa-arrow-left" style="font-size:10px; margin-right:5px;"></i> Back to Login</a>
        <form method="GET" action="" style="margin:0;">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="resend-btn" id="resendBtn">
                <i class="fas fa-redo" style="font-size:10px; margin-right:4px;"></i> Resend Code
            </button>
        </form>
    </div>
    
    <div class="security-note">
        <i class="fas fa-lock"></i>
        <span>256-Bit encrypted session. Never share this code with anyone, including IFW staff.</span>
    </div>
</div>

<script>
// OTP digit input logic
var digits = document.querySelectorAll('.otp-digit');
var hidden = document.getElementById('otpHidden');
var btn = document.getElementById('verifyBtn');
var remaining = <?= $remaining ?>;

digits.forEach(function(input, i) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
        if (this.value && digits[i+1]) digits[i+1].focus();
        updateHidden();
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && digits[i-1]) {
            digits[i-1].focus();
            digits[i-1].value = '';
        }
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        var data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        digits.forEach(function(d, idx) { d.value = data[idx] || ''; });
        var last = digits[Math.min(data.length, 5)]; if(last) last.focus();
        updateHidden();
    });
});

function updateHidden() {
    var val = '';
    digits.forEach(function(d) { val += d.value; });
    digits.forEach(function(d) { d.classList.toggle('filled', d.value !== ''); });
    hidden.value = val;
    btn.disabled = val.length !== 6;
}

// Countdown timer
var timerEl = document.getElementById('timer');
var interval = setInterval(function() {
    remaining--;
    if (remaining <= 0) {
        clearInterval(interval);
        timerEl.textContent = 'Expired';
        timerEl.classList.add('expired');
        btn.disabled = true;
        btn.textContent = 'Code Expired — Resend';
    } else {
        var m = Math.floor(remaining/60), s = remaining % 60;
        timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }
}, 1000);
</script>
</body>
</html>
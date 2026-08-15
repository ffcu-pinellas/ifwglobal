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

$c_id = (int)$_SESSION['pending_client_id'];
$c_email = $_SESSION['pending_client_email'] ?? '';
$c_name = $_SESSION['pending_client_name'] ?? 'Valued Client';

// Fetch client profile for PIN verification & fallback
$stmt = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
$stmt->execute([$c_id]);
$client = $stmt->fetch();

if (!$client) {
    unset($_SESSION['pending_client_id'], $_SESSION['pending_client_email'], $_SESSION['pending_client_name']);
    header("Location: login.php");
    exit;
}

$mode = $_GET['mode'] ?? 'email_otp'; // 'email_otp' or 'pin'
$error = '';
$success = '';
$lockout_seconds = 300; // 5 minutes

// Check Lockout Status
$now = time();
$pin_lockout_until = $_SESSION['client_pin_lockout_until'] ?? 0;
$otp_lockout_until = $_SESSION['client_otp_lockout_until'] ?? 0;

$is_pin_locked = ($pin_lockout_until > $now);
$pin_remaining_time = $is_pin_locked ? ($pin_lockout_until - $now) : 0;

$is_otp_locked = ($otp_lockout_until > $now);
$otp_remaining_time = $is_otp_locked ? ($otp_lockout_until - $now) : 0;

// Handle Resend Email OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend_code') {
    if ($is_otp_locked) {
        $error = "Email verification is temporarily locked. Please wait " . ceil($otp_remaining_time / 60) . " minute(s).";
    } else {
        $new_otp = rand(100000, 999999);
        $_SESSION['otp_code'] = $new_otp;
        $_SESSION['otp_time'] = time();

        $subject = "IFW Global Security: Your 6-Digit Client Portal Verification Code";
        $body = "<h2>IFW Global Security Verification</h2>
                 <p>Hello {$c_name},</p>
                 <p>You have requested a secure two-factor verification code to log into the IFW Global Client Intelligence Portal.</p>
                 <div style='background: #0f172a; color: #fecc56; padding: 18px; border-radius: 8px; font-size: 28px; font-weight: bold; letter-spacing: 6px; text-align: center; margin: 20px 0;'>
                     {$new_otp}
                 </div>
                 <p style='color: #64748b; font-size: 13px;'>This code is valid for 10 minutes. If you did not request this login, please contact your Case Officer immediately.</p>";

        @send_html_email($c_email, $subject, $body);
        $success = "A new 6-digit verification code has been dispatched to <strong>" . htmlspecialchars($c_email) . "</strong>.";
        $mode = 'email_otp';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_mode = $_POST['auth_mode'] ?? 'email_otp';

    if ($submitted_mode === 'email_otp') {
        if ($is_otp_locked) {
            $error = "Email OTP verification is locked. Please wait for the timer to expire.";
        } else {
            $entered_otp = trim($_POST['otp'] ?? '');
            $saved_otp = $_SESSION['otp_code'] ?? null;
            $otp_time = $_SESSION['otp_time'] ?? 0;

            if (empty($entered_otp)) {
                $error = "Please enter the 6-digit code received via email.";
            } elseif (!$saved_otp || (time() - $otp_time > 600)) {
                $error = "Verification code has expired. Please click 'Resend Code'.";
            } elseif ($entered_otp == $saved_otp) {
                // Success - Clear failures
                unset($_SESSION['client_otp_failures'], $_SESSION['client_otp_lockout_until'], $_SESSION['otp_code'], $_SESSION['otp_time']);

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_portal_id'] = $c_id;
                $_SESSION['client_id'] = $c_id;
                $_SESSION['client_name'] = $c_name;
                $_SESSION['client_email'] = $c_email;
                $_SESSION['role'] = 'client';
                $_SESSION['pin_verified'] = true;
                $_SESSION['2fa_verified'] = true;

                // Log secure login
                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $c_id, 'client', $c_email, 'success');
                }

                // Telegram Notification Dispatch
                if (function_exists('notify_client_login_telegram')) {
                    notify_client_login_telegram($pdo, $client, 'success');
                }

                unset($_SESSION['pending_client_id'], $_SESSION['pending_client_email'], $_SESSION['pending_client_name']);

                header("Location: dashboard.php");
                exit;
            } else {
                $failures = ($_SESSION['client_otp_failures'] ?? 0) + 1;
                $_SESSION['client_otp_failures'] = $failures;

                if ($failures >= 5) {
                    $_SESSION['client_otp_lockout_until'] = time() + $lockout_seconds;
                    $is_otp_locked = true;
                    $otp_remaining_time = $lockout_seconds;
                    $error = "Security Lockout: 5 failed verification attempts. OTP entry is locked for 5 minutes.";
                } else {
                    $remaining = 5 - $failures;
                    $error = "Invalid verification code. {$remaining} attempt(s) remaining before security lockout.";
                }

                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $c_id, 'client', $c_email, 'failed_otp');
                }
            }
        }
    } elseif ($submitted_mode === 'pin') {
        if ($is_pin_locked) {
            $error = "PIN verification is locked. Please wait for the timer to expire or use Email Verification.";
        } else {
            $entered_pin = trim($_POST['pin'] ?? '');

            if (empty($entered_pin)) {
                $error = "Please enter your 4-digit security PIN.";
            } elseif (!empty($client['pin_hash']) && password_verify($entered_pin, $client['pin_hash'])) {
                // Success - Reset failures
                unset($_SESSION['client_pin_failures'], $_SESSION['client_pin_lockout_until']);

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_portal_id'] = $c_id;
                $_SESSION['client_id'] = $c_id;
                $_SESSION['client_name'] = $c_name;
                $_SESSION['client_email'] = $c_email;
                $_SESSION['role'] = 'client';
                $_SESSION['pin_verified'] = true;
                $_SESSION['2fa_verified'] = true;

                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $c_id, 'client', $c_email, 'success');
                }

                if (function_exists('notify_client_login_telegram')) {
                    notify_client_login_telegram($pdo, $client, 'success');
                }

                unset($_SESSION['pending_client_id'], $_SESSION['pending_client_email'], $_SESSION['pending_client_name']);

                header("Location: dashboard.php");
                exit;
            } else {
                $failures = ($_SESSION['client_pin_failures'] ?? 0) + 1;
                $_SESSION['client_pin_failures'] = $failures;

                if ($failures >= 5) {
                    $_SESSION['client_pin_lockout_until'] = time() + $lockout_seconds;
                    $is_pin_locked = true;
                    $pin_remaining_time = $lockout_seconds;
                    $error = "Security Lockout: 5 failed PIN attempts. PIN input is locked for 5 minutes. Use Email Verification instead.";
                } else {
                    $remaining = 5 - $failures;
                    $error = "Invalid security PIN. {$remaining} attempt(s) remaining before security lockout.";
                }

                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $c_id, 'client', $c_email, 'failed_pin');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>2FA Security Gate - IFW Global Client Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --ifw-gold: #fecc56;
            --ifw-gold-hover: #e5b33d;
            --ifw-dark: #0a0a0c;
            --ifw-card: #131418;
            --ifw-border: rgba(254, 204, 86, 0.2);
        }
        * { box-sizing: border-box; }
        body {
            background-color: var(--ifw-dark);
            background-image: radial-gradient(circle at 50% 20%, rgba(254, 204, 86, 0.12) 0%, rgba(10, 10, 12, 0.98) 75%);
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
            margin: 0;
            overflow-x: hidden;
        }
        .security-card {
            background-color: var(--ifw-card);
            border: 1px solid var(--ifw-border);
            border-radius: 20px;
            padding: 35px 30px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), 0 0 30px rgba(254, 204, 86, 0.08);
            position: relative;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(254, 204, 86, 0.1);
            border: 1px solid rgba(254, 204, 86, 0.3);
            color: var(--ifw-gold);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .method-toggle {
            display: flex;
            background: #0a0a0c;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 25px;
        }
        .method-btn {
            flex: 1;
            padding: 10px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: #94a3b8;
            transition: all 0.25s ease;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        .method-btn.active {
            background: linear-gradient(135deg, var(--ifw-gold) 0%, var(--ifw-gold-hover) 100%);
            color: #000000;
            box-shadow: 0 4px 12px rgba(254, 204, 86, 0.25);
        }
        .digits-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }
        .digit-box {
            background: #0a0a0c;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: var(--ifw-gold);
            text-align: center;
            transition: all 0.2s ease;
            user-select: none;
        }
        .digit-box.filled {
            border-color: var(--ifw-gold);
            background: rgba(254, 204, 86, 0.08);
            box-shadow: 0 0 15px rgba(254, 204, 86, 0.2);
        }
        .digit-box.active {
            border-color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
        }
        /* On-Screen Keypad */
        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0 15px 0;
        }
        .keypad-btn {
            background: #1a1b22;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: #ffffff;
            font-family: 'Montserrat', sans-serif;
            font-size: 20px;
            font-weight: 600;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            touch-action: manipulation;
        }
        .keypad-btn:active, .keypad-btn:hover {
            background: #252733;
            border-color: var(--ifw-gold);
            color: var(--ifw-gold);
            transform: scale(0.97);
        }
        .keypad-btn.action-btn {
            font-size: 15px;
            color: #94a3b8;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--ifw-gold) 0%, var(--ifw-gold-hover) 100%);
            color: #000000;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.5px;
            border: none;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(254, 204, 86, 0.25);
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(254, 204, 86, 0.35);
        }
        .lockout-alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            border-radius: 12px;
            padding: 14px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
        }
        .countdown-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 20px;
            font-weight: 700;
            color: #ef4444;
            display: block;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="security-card text-center">
    <div class="brand-badge">
        <i class="fas fa-lock"></i> 256-Bit Encrypted Portal Gate
    </div>

    <h4 class="fw-bold mb-1" style="letter-spacing: 0.5px;">Client Verification</h4>
    <p class="text-muted small mb-3">Welcome back, <strong class="text-white"><?= htmlspecialchars($c_name) ?></strong></p>

    <!-- Method Toggle Switcher -->
    <div class="method-toggle">
        <a href="verify_otp.php?mode=email_otp" class="method-btn <?= $mode === 'email_otp' ? 'active' : '' ?>">
            <i class="fas fa-envelope me-1"></i> Email Code
        </a>
        <a href="verify_otp.php?mode=pin" class="method-btn <?= $mode === 'pin' ? 'active' : '' ?>">
            <i class="fas fa-key me-1"></i> Security PIN
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 text-center py-2 mb-3" style="font-size: 12.5px; background: rgba(239,68,68,0.18); color: #fca5a5;">
            <i class="fas fa-exclamation-circle me-1"></i><?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 text-center py-2 mb-3" style="font-size: 12.5px; background: rgba(34,197,94,0.18); color: #86efac;">
            <i class="fas fa-check-circle me-1"></i><?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'email_otp'): ?>
        <?php if ($is_otp_locked): ?>
            <div class="lockout-alert">
                <i class="fas fa-lock me-1"></i> Verification Locked (5 Failed Attempts)
                <div class="countdown-timer" id="otpLockoutCountdown" data-seconds="<?= $otp_remaining_time ?>">05:00</div>
                <small class="d-block mt-2 text-muted">Use <a href="verify_otp.php?mode=pin" class="text-warning fw-bold">Security PIN</a> to bypass or wait for timer.</small>
            </div>
        <?php else: ?>
            <form method="POST" id="otpForm">
                <input type="hidden" name="auth_mode" value="email_otp">
                <input type="hidden" name="otp" id="hiddenOtpInput" value="" maxlength="6">

                <div class="digits-container" id="otpDigitsContainer">
                    <div class="digit-box active" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="0">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="1">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="2">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="3">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="4">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="5">•</div>
                </div>

                <!-- On-Screen Keypad -->
                <div class="keypad-grid">
                    <button type="button" class="keypad-btn" onclick="pressKey('1')">1</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('2')">2</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('3')">3</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('4')">4</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('5')">5</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('6')">6</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('7')">7</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('8')">8</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('9')">9</button>
                    <button type="button" class="keypad-btn action-btn" onclick="clearKeys()"><i class="fas fa-undo"></i></button>
                    <button type="button" class="keypad-btn" onclick="pressKey('0')">0</button>
                    <button type="button" class="keypad-btn action-btn" onclick="backspaceKey()"><i class="fas fa-backspace"></i></button>
                </div>

                <button type="submit" class="btn-submit" id="submitOtpBtn">
                    <i class="fas fa-check-shield me-1"></i> Verify & Access Dashboard
                </button>

                <div class="mt-3">
                    <a href="verify_otp.php?mode=email_otp&action=resend_code" class="text-warning small text-decoration-none fw-bold">
                        <i class="fas fa-redo me-1"></i> Resend Verification Code
                    </a>
                </div>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($is_pin_locked): ?>
            <div class="lockout-alert">
                <i class="fas fa-lock me-1"></i> PIN Access Locked (5 Failed Attempts)
                <div class="countdown-timer" id="pinLockoutCountdown" data-seconds="<?= $pin_remaining_time ?>">05:00</div>
                <small class="d-block mt-2 text-muted">Use <a href="verify_otp.php?mode=email_otp" class="text-warning fw-bold">Email Code</a> to bypass or wait for timer.</small>
            </div>
        <?php else: ?>
            <form method="POST" id="pinForm">
                <input type="hidden" name="auth_mode" value="pin">
                <input type="hidden" name="pin" id="hiddenPinInput" value="" maxlength="4">

                <div class="digits-container" id="pinDigitsContainer" style="gap: 12px;">
                    <div class="digit-box active" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="0">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="1">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="2">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="3">•</div>
                </div>

                <!-- On-Screen Keypad -->
                <div class="keypad-grid">
                    <button type="button" class="keypad-btn" onclick="pressKey('1')">1</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('2')">2</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('3')">3</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('4')">4</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('5')">5</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('6')">6</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('7')">7</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('8')">8</button>
                    <button type="button" class="keypad-btn" onclick="pressKey('9')">9</button>
                    <button type="button" class="keypad-btn action-btn" onclick="clearKeys()"><i class="fas fa-undo"></i></button>
                    <button type="button" class="keypad-btn" onclick="pressKey('0')">0</button>
                    <button type="button" class="keypad-btn action-btn" onclick="backspaceKey()"><i class="fas fa-backspace"></i></button>
                </div>

                <button type="submit" class="btn-submit" id="submitPinBtn">
                    <i class="fas fa-lock-open me-1"></i> Verify PIN & Unlock Portal
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mt-4">
        <a href="login.php" class="text-muted small text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i> Return to Client Login
        </a>
    </div>
</div>

<script>
let currentDigits = [];
const maxDigits = <?= $mode === 'pin' ? '4' : '6' ?>;

function updateBoxes() {
    const isPin = <?= $mode === 'pin' ? 'true' : 'false' ?>;
    const containerId = isPin ? 'pinDigitsContainer' : 'otpDigitsContainer';
    const inputId = isPin ? 'hiddenPinInput' : 'hiddenOtpInput';
    const container = document.getElementById(containerId);
    const hiddenInput = document.getElementById(inputId);

    if (!container || !hiddenInput) return;

    hiddenInput.value = currentDigits.join('');
    const boxes = container.querySelectorAll('.digit-box');

    boxes.forEach((box, i) => {
        if (i < currentDigits.length) {
            box.textContent = isPin ? '•' : currentDigits[i];
            box.classList.add('filled');
            box.classList.remove('active');
        } else if (i === currentDigits.length) {
            box.textContent = '•';
            box.classList.remove('filled');
            box.classList.add('active');
        } else {
            box.textContent = '•';
            box.classList.remove('filled', 'active');
        }
    });
}

function pressKey(val) {
    if (currentDigits.length < maxDigits) {
        currentDigits.push(val);
        updateBoxes();
        if (currentDigits.length === maxDigits) {
            // Auto submit when digit count reached
            setTimeout(() => {
                const activeForm = document.getElementById('pinForm') || document.getElementById('otpForm');
                if (activeForm) activeForm.submit();
            }, 300);
        }
    }
}

function backspaceKey() {
    if (currentDigits.length > 0) {
        currentDigits.pop();
        updateBoxes();
    }
}

function clearKeys() {
    currentDigits = [];
    updateBoxes();
}

// Physical Keyboard Listener
document.addEventListener('keydown', function(e) {
    if (e.key >= '0' && e.key <= '9') {
        pressKey(e.key);
    } else if (e.key === 'Backspace') {
        backspaceKey();
    } else if (e.key === 'Escape') {
        clearKeys();
    } else if (e.key === 'Enter') {
        const activeForm = document.getElementById('pinForm') || document.getElementById('otpForm');
        if (activeForm && currentDigits.length === maxDigits) {
            activeForm.submit();
        }
    }
});

// Countdown Timer Handler for Lockouts
function startCountdown(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;

    let remaining = parseInt(el.getAttribute('data-seconds'), 10) || 300;

    const interval = setInterval(() => {
        if (remaining <= 0) {
            clearInterval(interval);
            window.location.reload();
            return;
        }
        remaining--;
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        el.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }, 1000);
}

document.addEventListener('DOMContentLoaded', () => {
    startCountdown('pinLockoutCountdown');
    startCountdown('otpLockoutCountdown');
});
</script>

</body>
</html>
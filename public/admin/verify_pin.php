<?php
// admin/verify_pin.php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already fully authenticated, proceed to admin dashboard
if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['pending_admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['pending_admin_id'];
$admin_username = $_SESSION['pending_admin_username'] ?? 'admin';

// Fetch admin profile
$stmt = $pdo->prepare("SELECT id, username, email, full_name, role, pin_hash FROM IFW_users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username']);
    header("Location: login.php");
    exit;
}

$user_email = !empty($user['email']) ? $user['email'] : ($admin_username . '@ifwglobal.com');
$mode = $_GET['mode'] ?? 'pin'; // 'pin' or 'email_otp'

$error = '';
$success = '';
$lockout_seconds = 300; // 5 minutes

// Check Lockout Status
$now = time();
$pin_lockout_until = $_SESSION['admin_pin_lockout_until'] ?? 0;
$otp_lockout_until = $_SESSION['admin_otp_lockout_until'] ?? 0;

$is_pin_locked = ($pin_lockout_until > $now);
$pin_remaining_time = $is_pin_locked ? ($pin_lockout_until - $now) : 0;

$is_otp_locked = ($otp_lockout_until > $now);
$otp_remaining_time = $is_otp_locked ? ($otp_lockout_until - $now) : 0;

// Handle Send/Resend Email OTP
if (isset($_GET['action']) && in_array($_GET['action'], ['send_email_code', 'resend_code'])) {
    if ($is_otp_locked) {
        $error = "Email verification is temporarily locked due to previous failed attempts. Please wait " . ceil($otp_remaining_time / 60) . " minute(s).";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['admin_otp_code'] = (string)$otp;
        $_SESSION['admin_otp_time'] = time();

        $admin_display_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
        $subject = "IFW Global Security: Your 6-Digit Admin Verification Code";
        $body = "<h2>IFW Global Security Verification</h2>
                 <p>Hello {$admin_display_name},</p>
                 <p>You have requested a secure two-factor authentication code to log into the IFW Global Command Center.</p>
                 <div style='background: #0f172a; color: #fecc56; padding: 18px; border-radius: 8px; font-size: 28px; font-weight: bold; letter-spacing: 6px; text-align: center; margin: 20px 0;'>
                     {$otp}
                 </div>
                 <p style='color: #64748b; font-size: 13px;'>This code is valid for 10 minutes. If you did not initiate this authentication request, please contact IT Security immediately.</p>";

        $mail_sent = @send_html_email($user_email, $subject, $body);
        $success = "A 6-digit verification code has been dispatched to <strong>" . htmlspecialchars($user_email) . "</strong>.";
        $mode = 'email_otp';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_mode = $_POST['auth_mode'] ?? 'pin';

    if ($submitted_mode === 'pin') {
        if ($is_pin_locked) {
            $error = "PIN verification is locked. Please wait for the timer to expire or use Email Verification.";
        } else {
            $entered_pin = trim($_POST['pin'] ?? '');

            if (empty($entered_pin)) {
                $error = "Please enter your 4-digit security PIN.";
            } else {
                $pin_valid = false;
                
                if (!empty($user['pin_hash'])) {
                    if (password_verify($entered_pin, $user['pin_hash']) || $entered_pin === $user['pin_hash'] || hash('sha256', $entered_pin) === $user['pin_hash']) {
                        $pin_valid = true;
                        // Auto-upgrade plain or sha256 to strong bcrypt
                        if ($entered_pin === $user['pin_hash'] || hash('sha256', $entered_pin) === $user['pin_hash']) {
                            $upgraded = password_hash($entered_pin, PASSWORD_DEFAULT);
                            $pdo->prepare("UPDATE IFW_users SET pin_hash = ? WHERE id = ?")->execute([$upgraded, $admin_id]);
                        }
                    }
                } elseif ($entered_pin === '1234') { // Default fallback PIN for fresh accounts
                    $pin_valid = true;
                    $upgraded = password_hash($entered_pin, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE IFW_users SET pin_hash = ? WHERE id = ?")->execute([$upgraded, $admin_id]);
                }

                if ($pin_valid) {
                    // Success - Clear failure states
                    unset($_SESSION['admin_pin_failures'], $_SESSION['admin_pin_lockout_until']);

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                    $_SESSION['admin_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
                    $_SESSION['admin_pin_verified'] = true;
                    $_SESSION['role'] = $user['role'] ?? 'admin';

                    unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username']);

                    log_audit_action($pdo, $admin_id, 'LOGIN_2FA_PIN', "Successful 2FA PIN login for admin '{$user['username']}'");

                    if (function_exists('log_user_login')) {
                        log_user_login($pdo, $admin_id, 'admin', $user_email, 'success');
                    }

                    header("Location: index.php");
                    exit;
                } else {
                    $failures = ($_SESSION['admin_pin_failures'] ?? 0) + 1;
                    $_SESSION['admin_pin_failures'] = $failures;

                    if ($failures >= 5) {
                        $_SESSION['admin_pin_lockout_until'] = time() + $lockout_seconds;
                        $is_pin_locked = true;
                        $pin_remaining_time = $lockout_seconds;
                        $error = "Security Lockout: 5 failed PIN attempts. PIN input is locked for 5 minutes. You may use Email Verification instead.";
                    } else {
                        $remaining = 5 - $failures;
                        $error = "Invalid security PIN. {$remaining} attempt(s) remaining before security lockout.";
                    }

                    if (function_exists('log_user_login')) {
                        log_user_login($pdo, $admin_id, 'admin', $user_email, 'failed_pin');
                    }
                }
            }
        }
    } elseif ($submitted_mode === 'email_otp') {
        if ($is_otp_locked) {
            $error = "Email OTP verification is locked. Please wait for the timer to expire.";
        } else {
            $entered_otp = trim($_POST['otp'] ?? '');
            $saved_otp = isset($_SESSION['admin_otp_code']) ? (string)$_SESSION['admin_otp_code'] : null;
            $otp_time = $_SESSION['admin_otp_time'] ?? 0;

            if (empty($entered_otp)) {
                $error = "Please enter the 6-digit code received via email.";
            } elseif (!$saved_otp || (time() - $otp_time > 600)) {
                $error = "Verification code has expired or was not generated. Please click 'Resend Code'.";
            } elseif ($entered_otp === $saved_otp) {
                // Success - Reset failure states
                unset($_SESSION['admin_otp_failures'], $_SESSION['admin_otp_lockout_until'], $_SESSION['admin_otp_code'], $_SESSION['admin_otp_time']);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                $_SESSION['admin_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
                $_SESSION['admin_pin_verified'] = true;
                $_SESSION['role'] = $user['role'] ?? 'admin';

                unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_username']);

                log_audit_action($pdo, $admin_id, 'LOGIN_2FA_OTP', "Successful 2FA Email OTP login for admin '{$user['username']}'");

                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $admin_id, 'admin', $user_email, 'success');
                }

                header("Location: index.php");
                exit;
            } else {
                $failures = ($_SESSION['admin_otp_failures'] ?? 0) + 1;
                $_SESSION['admin_otp_failures'] = $failures;

                if ($failures >= 5) {
                    $_SESSION['admin_otp_lockout_until'] = time() + $lockout_seconds;
                    $is_otp_locked = true;
                    $otp_remaining_time = $lockout_seconds;
                    $error = "Security Lockout: 5 failed email OTP attempts. OTP input is locked for 5 minutes.";
                } else {
                    $remaining = 5 - $failures;
                    $error = "Invalid verification code. {$remaining} attempt(s) remaining before security lockout.";
                }

                if (function_exists('log_user_login')) {
                    log_user_login($pdo, $admin_id, 'admin', $user_email, 'failed_otp');
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
    <title>2FA Security Gate - IFW Global Command Center</title>
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
            padding: 32px 26px;
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
            margin-bottom: 18px;
        }
        .method-toggle {
            display: flex;
            background: #0a0a0c;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 22px;
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
        .view-controls {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 6px;
        }
        .btn-toggle-mask {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(254, 204, 86, 0.25);
            color: #cbd5e1;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        .btn-toggle-mask:hover, .btn-toggle-mask.active {
            background: rgba(254, 204, 86, 0.18);
            color: var(--ifw-gold);
            border-color: var(--ifw-gold);
        }
        .digits-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 12px 0 20px 0;
        }
        .digit-box {
            width: 54px;
            height: 60px;
            background: #0a0a0c;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 26px;
            font-weight: 700;
            color: var(--ifw-gold);
            text-align: center;
            line-height: 56px;
            transition: all 0.15s ease;
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
        /* Ultra-Responsive On-Screen Keypad with Zero Tap Delay */
        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 16px 0 16px 0;
        }
        .keypad-btn {
            background: #1a1b22;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.08s ease, background 0.1s ease;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        .keypad-btn:active {
            background: var(--ifw-gold);
            border-color: var(--ifw-gold);
            color: #000000;
            transform: scale(0.94);
        }
        .keypad-btn.action-btn {
            font-family: 'Montserrat', sans-serif;
            font-size: 16px;
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
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px rgba(254, 204, 86, 0.25);
            touch-action: manipulation;
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
        <i class="fas fa-shield-alt"></i> Security Clearance Level 4
    </div>

    <h4 class="fw-bold mb-1" style="letter-spacing: 0.5px;">2FA Security Gate</h4>
    <p class="text-muted small mb-3">Authenticate command access for <strong class="text-white"><?= htmlspecialchars($admin_username) ?></strong></p>

    <!-- Method Switcher -->
    <div class="method-toggle">
        <a href="verify_pin.php?mode=pin" class="method-btn <?= $mode === 'pin' ? 'active' : '' ?>">
            <i class="fas fa-key me-1"></i> Security PIN
        </a>
        <a href="verify_pin.php?mode=email_otp&action=send_email_code" class="method-btn <?= $mode === 'email_otp' ? 'active' : '' ?>">
            <i class="fas fa-envelope me-1"></i> Email OTP Code
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

    <?php if ($mode === 'pin'): ?>
        <?php if ($is_pin_locked): ?>
            <div class="lockout-alert">
                <i class="fas fa-lock me-1"></i> PIN Access Locked (5 Failed Attempts)
                <div class="countdown-timer" id="pinLockoutCountdown" data-seconds="<?= $pin_remaining_time ?>">05:00</div>
                <small class="d-block mt-2 text-muted">Use <a href="verify_pin.php?mode=email_otp&action=send_email_code" class="text-warning fw-bold">Email OTP</a> to bypass or wait for timer.</small>
            </div>
        <?php else: ?>
            <form method="POST" id="pinForm">
                <input type="hidden" name="auth_mode" value="pin">
                <input type="hidden" name="pin" id="hiddenPinInput" value="" maxlength="4">

                <div class="view-controls">
                    <button type="button" class="btn-toggle-mask" id="toggleMaskBtn" onclick="toggleDigitMask()">
                        <i class="fas fa-eye"></i> <span>Show Digits</span>
                    </button>
                </div>

                <div class="digits-container" id="pinDigitsContainer">
                    <div class="digit-box active" data-index="0">•</div>
                    <div class="digit-box" data-index="1">•</div>
                    <div class="digit-box" data-index="2">•</div>
                    <div class="digit-box" data-index="3">•</div>
                </div>

                <!-- On-Screen Keypad -->
                <div class="keypad-grid">
                    <button type="button" class="keypad-btn" data-key="1">1</button>
                    <button type="button" class="keypad-btn" data-key="2">2</button>
                    <button type="button" class="keypad-btn" data-key="3">3</button>
                    <button type="button" class="keypad-btn" data-key="4">4</button>
                    <button type="button" class="keypad-btn" data-key="5">5</button>
                    <button type="button" class="keypad-btn" data-key="6">6</button>
                    <button type="button" class="keypad-btn" data-key="7">7</button>
                    <button type="button" class="keypad-btn" data-key="8">8</button>
                    <button type="button" class="keypad-btn" data-key="9">9</button>
                    <button type="button" class="keypad-btn action-btn" data-action="clear"><i class="fas fa-undo"></i></button>
                    <button type="button" class="keypad-btn" data-key="0">0</button>
                    <button type="button" class="keypad-btn action-btn" data-action="backspace"><i class="fas fa-backspace"></i></button>
                </div>

                <button type="submit" class="btn-submit" id="submitPinBtn">
                    <i class="fas fa-lock-open me-1"></i> Verify PIN & Unlock Portal
                </button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($is_otp_locked): ?>
            <div class="lockout-alert">
                <i class="fas fa-lock me-1"></i> OTP Access Locked (5 Failed Attempts)
                <div class="countdown-timer" id="otpLockoutCountdown" data-seconds="<?= $otp_remaining_time ?>">05:00</div>
                <small class="d-block mt-2 text-muted">Use <a href="verify_pin.php?mode=pin" class="text-warning fw-bold">Security PIN</a> to unlock.</small>
            </div>
        <?php else: ?>
            <form method="POST" id="otpForm">
                <input type="hidden" name="auth_mode" value="email_otp">
                <input type="hidden" name="otp" id="hiddenOtpInput" value="" maxlength="6">

                <div class="view-controls">
                    <button type="button" class="btn-toggle-mask" id="toggleMaskBtn" onclick="toggleDigitMask()">
                        <i class="fas fa-eye"></i> <span>Show Digits</span>
                    </button>
                </div>

                <div class="digits-container" id="otpDigitsContainer" style="gap: 6px;">
                    <div class="digit-box active" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="0">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="1">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="2">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="3">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="4">•</div>
                    <div class="digit-box" style="width: 44px; height: 52px; font-size: 22px; line-height: 48px;" data-index="5">•</div>
                </div>

                <!-- On-Screen Keypad -->
                <div class="keypad-grid">
                    <button type="button" class="keypad-btn" data-key="1">1</button>
                    <button type="button" class="keypad-btn" data-key="2">2</button>
                    <button type="button" class="keypad-btn" data-key="3">3</button>
                    <button type="button" class="keypad-btn" data-key="4">4</button>
                    <button type="button" class="keypad-btn" data-key="5">5</button>
                    <button type="button" class="keypad-btn" data-key="6">6</button>
                    <button type="button" class="keypad-btn" data-key="7">7</button>
                    <button type="button" class="keypad-btn" data-key="8">8</button>
                    <button type="button" class="keypad-btn" data-key="9">9</button>
                    <button type="button" class="keypad-btn action-btn" data-action="clear"><i class="fas fa-undo"></i></button>
                    <button type="button" class="keypad-btn" data-key="0">0</button>
                    <button type="button" class="keypad-btn action-btn" data-action="backspace"><i class="fas fa-backspace"></i></button>
                </div>

                <button type="submit" class="btn-submit" id="submitOtpBtn">
                    <i class="fas fa-check-shield me-1"></i> Verify OTP & Authorize
                </button>

                <div class="mt-3">
                    <a href="verify_pin.php?mode=email_otp&action=resend_code" class="text-warning small text-decoration-none fw-bold">
                        <i class="fas fa-redo me-1"></i> Resend Verification Code
                    </a>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mt-4">
        <a href="login.php" class="text-muted small text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i> Return to Admin Login
        </a>
    </div>
</div>

<script>
let currentDigits = [];
let isMasked = true;
const maxDigits = <?= $mode === 'pin' ? '4' : '6' ?>;
const isPinMode = <?= $mode === 'pin' ? 'true' : 'false' ?>;

function toggleDigitMask() {
    isMasked = !isMasked;
    const btn = document.getElementById('toggleMaskBtn');
    if (btn) {
        if (isMasked) {
            btn.innerHTML = '<i class="fas fa-eye"></i> <span>Show Digits</span>';
            btn.classList.remove('active');
        } else {
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> <span>Hide Digits</span>';
            btn.classList.add('active');
        }
    }
    updateBoxes();
}

function updateBoxes() {
    const containerId = isPinMode ? 'pinDigitsContainer' : 'otpDigitsContainer';
    const inputId = isPinMode ? 'hiddenPinInput' : 'hiddenOtpInput';
    const container = document.getElementById(containerId);
    const hiddenInput = document.getElementById(inputId);

    if (!container || !hiddenInput) return;

    hiddenInput.value = currentDigits.join('');
    const boxes = container.children;

    for (let i = 0; i < boxes.length; i++) {
        const box = boxes[i];
        if (i < currentDigits.length) {
            box.textContent = isMasked ? '•' : currentDigits[i];
            box.className = 'digit-box filled';
        } else if (i === currentDigits.length) {
            box.textContent = '•';
            box.className = 'digit-box active';
        } else {
            box.textContent = '•';
            box.className = 'digit-box';
        }
    }
}

function pressKey(val) {
    if (currentDigits.length < maxDigits) {
        currentDigits.push(String(val));
        updateBoxes();
        if (currentDigits.length === maxDigits) {
            setTimeout(() => {
                const form = document.getElementById(isPinMode ? 'pinForm' : 'otpForm');
                if (form) form.submit();
            }, 250);
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

// Rapid Tap / Click Handler with Zero Latency
document.querySelectorAll('.keypad-btn').forEach(btn => {
    const handleKeyAction = (e) => {
        e.preventDefault();
        const key = btn.getAttribute('data-key');
        const action = btn.getAttribute('data-action');
        if (key !== null) {
            pressKey(key);
        } else if (action === 'backspace') {
            backspaceKey();
        } else if (action === 'clear') {
            clearKeys();
        }
    };

    btn.addEventListener('pointerdown', handleKeyAction, { passive: false });
});

// Physical Keyboard Listener
document.addEventListener('keydown', function(e) {
    if (e.key >= '0' && e.key <= '9') {
        pressKey(e.key);
    } else if (e.key === 'Backspace') {
        backspaceKey();
    } else if (e.key === 'Escape') {
        clearKeys();
    } else if (e.key === 'Enter') {
        const activeForm = document.getElementById(isPinMode ? 'pinForm' : 'otpForm');
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

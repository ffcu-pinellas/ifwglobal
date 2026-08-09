<?php
// includes/admin_header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? 'admin';
$user_name = $_SESSION['user_name'] ?? $_SESSION['admin_username'] ?? 'User';

// Fetch Notifications for Notification Bell Dropdown
$notifications = [];
$unread_notifications_count = 0;
if (isset($pdo)) {
    try {
        if ($user_role === 'client') {
            $c_id = $_SESSION['client_portal_id'] ?? 0;
            $n_stmt = $pdo->prepare("SELECT * FROM IFW_notifications WHERE client_id = ? AND link LIKE '/client/%' ORDER BY created_at DESC LIMIT 5");
            $n_stmt->execute([$c_id]);
            $notifications = $n_stmt->fetchAll();
            
            $n_count = $pdo->prepare("SELECT COUNT(*) FROM IFW_notifications WHERE client_id = ? AND link LIKE '/client/%' AND is_read = 0");
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

                <ul class="navbar-nav ml-auto mr-3">
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
                                    <span class="badge badge-warning text-dark font-weight-bold"><?= $unread_notifications_count ?> New</span>
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
                            <div class="dropdown-divider border-secondary"></div>
                            <a class="dropdown-item text-white" href="/client/logout.php"><i class="material-icons text-danger align-middle mr-1">power_settings_new</i> Log Out</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>

<?php
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? 'admin';
$user_name = $_SESSION['user_name'] ?? $_SESSION['admin_username'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);

$chat_provider = isset($pdo) ? get_setting($pdo, 'chat_provider', 'native') : 'native';

// Global Unread Chat Count Logic
$global_unread_chat = 0;
if (isset($pdo)) {
    try {
        if ($user_role === 'client') {
            $client_id = $_SESSION['client_portal_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_chat_messages WHERE client_id = ? AND sender_type = 'admin' AND is_read = 0");
            $stmt->execute([$client_id]);
            $global_unread_chat = $stmt->fetchColumn();
        } else {
            $admin_id = $_SESSION['admin_id'] ?? 0;
            if ($user_role === 'super_admin' || $user_role === 'admin' || $user_role === 'superadmin') {
                $stmt = $pdo->query("SELECT COUNT(*) FROM IFW_chat_messages WHERE sender_type = 'client' AND is_read = 0");
                $global_unread_chat = $stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM IFW_chat_messages m JOIN IFW_clients c ON m.client_id = c.id WHERE c.assigned_agent_id = ? AND m.sender_type = 'client' AND m.is_read = 0");
                $stmt->execute([$admin_id]);
                $global_unread_chat = $stmt->fetchColumn();
            }
        }
    } catch (Exception $e) {
        $global_unread_chat = 0;
    }
}
?>
<style>
    /* ============================================================
       FIXED SIDEBAR POSITIONING â€” Kills all OverlayScrollbars glue
       ============================================================ */
    #wrapper-left {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        z-index: 1040 !important;
        background-color: #1f1b1c !important;
        border-right: 1px solid rgba(254, 204, 86, 0.2) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .sidebar {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        height: auto !important;
        background-color: #1f1b1c !important;
    }
    .sidebar-container {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        /* Neutralize OverlayScrollbars injected transforms */
        transform: none !important;
        position: static !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }
    .sidebar-nav {
        display: block !important;
        margin: 0 !important;
        padding: 8px 0 30px 0 !important;
        list-style: none !important;
        flex: 0 0 auto !important;
        position: static !important;
        top: auto !important;
        transform: none !important;
    }
    .sidebar-nav > li {
        display: block !important;
        position: static !important;
        float: none !important;
    }
    .sidebar-header {
        flex: 0 0 auto !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #1f1b1c !important;
    }

    /* KILL ALL OVERLAYSCROLLBARS INJECTED ELEMENTS */
    .os-host,
    .os-theme-dark,
    .os-resize-observer,
    .os-size-observer,
    .os-padding,
    .os-viewport,
    .os-content-glue,
    div[class^="os-"],
    div[class*=" os-"] {
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        transform: none !important;
        position: static !important;
        margin: 0 !important;
    }
    .os-content-glue {
        display: none !important;
        height: 0 !important;
        width: 0 !important;
        overflow: hidden !important;
        pointer-events: none !important;
    }
    .os-scrollbar,
    .os-scrollbar-track,
    .os-scrollbar-handle,
    .os-resize-observer-host {
        display: none !important;
        visibility: hidden !important;
    }

    /* GLOBAL CONTRAST FIXES FOR FORMS, TABLES, AND INPUTS */
    .form-control, select, textarea, input[type="text"], input[type="email"], input[type="password"], input[type="number"] {
        background-color: #2b2627 !important;
        color: #ffffff !important;
        border: 1px solid #444 !important;
    }
    .form-control:focus, select:focus, textarea:focus {
        background-color: #363031 !important;
        color: #ffffff !important;
        border-color: #fecc56 !important;
        box-shadow: 0 0 5px rgba(254,204,86,0.3) !important;
    }
    select option {
        background-color: #1f1b1c !important;
        color: #ffffff !important;
    }
    .table-dark {
        color: #ffffff !important;
        background-color: #1f1b1c !important;
    }
    .table-dark th {
        color: #fecc56 !important;
        border-bottom: 2px solid rgba(254,204,86,0.3) !important;
    }
    .table-dark td {
        border-color: #333 !important;
        color: #e0e0e0 !important;
    }
    .card-header {
        background-color: #2a2526 !important;
        color: #fecc56 !important;
        border-bottom: 1px solid #444 !important;
    }
    .card-body {
        background-color: #1f1b1c !important;
        color: #ffffff !important;
    }
    .modal-content {
        background-color: #1f1b1c !important;
        color: #ffffff !important;
        border: 1px solid #fecc56 !important;
    }
    .modal-header, .modal-footer {
        border-color: #333 !important;
    }
</style>
<script>
/* Block OverlayScrollbars from running on the sidebar â€” run before DOM ready */
(function() {
    // Override jQuery overlayScrollbars plugin before it initializes
    document.addEventListener('DOMContentLoaded', function() {
        // Destroy any existing OverlayScrollbars instances on sidebar
        var sidebar = document.querySelector('.sidebar-container');
        if (sidebar && typeof OverlayScrollbars !== 'undefined') {
            try {
                var instance = OverlayScrollbars(sidebar);
                if (instance) instance.destroy();
            } catch(e) {}
        }
        // Remove any injected glue divs
        var glues = document.querySelectorAll('.os-content-glue, .os-resize-observer');
        glues.forEach(function(el) { el.style.cssText = 'display:none!important;height:0!important;'; });
        
        // Also patch jQuery plugin if loaded
        if (typeof $ !== 'undefined' && $.fn && $.fn.overlayScrollbars) {
            var orig = $.fn.overlayScrollbars;
            $.fn.overlayScrollbars = function() {
                // Only allow if NOT the sidebar
                if (this.closest && this.closest('#wrapper-left').length > 0) return this;
                if (this.is && this.is('#wrapper-left, .sidebar-container, .sidebar')) return this;
                return orig.apply(this, arguments);
            };
        }
    });
})();
</script>


<!-- WRAPPER LEFT -->
<div id="wrapper-left">
    <div class="sidebar sidebar-dark sidebar-danger bg-dark">
        <!-- SIDEBAR HEADER -->
        <div class="sidebar-header border-fade p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(254, 204, 86, 0.2);">
            <a href="<?php echo BASE_URL; ?>/<?php echo ($user_role === 'client') ? 'client/dashboard.php' : 'admin/index.php'; ?>" class="sidebar-brand text-decoration-none">
                <h4 class="mb-0 text-warning font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>IFW GLOBAL</h4>
            </a>
            <a href="javascript:void(0);" class="sidebar-close d-md-none text-warning" data-toggle="class" data-target="#wrapper" toggle-class="toggled">
                <i class="material-icons icon-sm">close</i>
            </a>
        </div>
        <!-- SIDEBAR CONTAINER -->
        <div class="sidebar-container style-scroll-dark">
            <!-- SIDEBAR PROFILE -->
            <div class="sidebar-profile border-fade p-3" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div class="d-flex align-items-center">
                    <img src="/admin_assets/img/profile/blank.png" alt="Profile" class="img-fluid rounded-circle border border-warning sidebar-profile-img" width="40" height="40" />
                    <div class="sidebar-profile-info ml-3">
                        <h6 class="mb-0 text-white font-weight-bold" style="font-size: 13px;"><?php echo htmlspecialchars($user_name); ?></h6>
                        <small class="text-warning" style="font-size: 10px; letter-spacing: 0.5px;"><?php echo strtoupper($user_role); ?></small>
                    </div>
                </div>
            </div>
            <!-- SIDEBAR NAV -->
            <ul class="sidebar-nav py-2">
                <!-- NAV ITEM dashboard -->
                <li class="nav-item <?php echo ($current_page == 'index.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/<?php echo ($user_role == 'client') ? 'client/dashboard.php' : 'admin/index.php'; ?>" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-tachometer-alt text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Dashboard</span>
                    </a>
                </li>


                <?php if ($user_role !== 'client'): ?>
                <!-- NAV ITEM Cases -->
                <li class="nav-item <?php echo ($current_page == 'cases.php' || $current_page == 'case_view.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/cases.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-folder-open text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Recovery Cases</span>
                    </a>
                </li>

                <!-- NAV ITEM Clients -->
                <li class="nav-item <?php echo ($current_page == 'client_manager.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/client_manager.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-users text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Client Accounts</span>
                    </a>
                </li>
                
                <!-- NAV ITEM KYC Review -->
                <li class="nav-item <?php echo ($current_page == 'kyc_review.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/kyc_review.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-id-card text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">KYC Verification</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'client'): ?>
                <!-- CLIENT PORTAL LINKS -->
                <li class="nav-item <?php echo ($current_page == 'my_cases.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/my_cases.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-file-invoice text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">My Cases</span>
                    </a>
                </li>

                <!-- NAV ITEM Blockchain Watcher -->
                <li class="nav-item <?php echo ($current_page == 'blockchain_tracker.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/blockchain_tracker.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-cubes text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Blockchain Watcher</span>
                    </a>
                </li>

                <!-- NAV ITEM Escrow & Settlement -->
                <li class="nav-item <?php echo ($current_page == 'settlement_payout.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/settlement_payout.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-vault text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Escrow & Settlement</span>
                    </a>
                </li>

                <!-- NAV ITEM Global Recovery Radar -->
                <li class="nav-item <?php echo ($current_page == 'recovery_map.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/recovery_map.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-globe-americas text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Recovery Radar</span>
                    </a>
                </li>
                
                <!-- NAV ITEM Client KYC -->
                <li class="nav-item <?php echo ($current_page == 'kyc.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/kyc.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-id-card text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Identity Verification</span>
                    </a>
                </li>

                <!-- NAV ITEM Security Desk -->
                <li class="nav-item <?php echo ($current_page == 'security.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/client/security.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-user-shield text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Security & Sessions</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($user_role !== 'client'): ?>
                <!-- NAV ITEM Invoices -->
                <li class="nav-item <?php echo ($current_page == 'invoices.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/invoices.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-file-invoice-dollar text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Invoicing</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'superadmin' || $user_role === 'admin'): ?>
                <!-- NAV ITEM Submissions -->
                <li class="nav-item <?php echo ($current_page == 'submissions.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/submissions.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-inbox text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Leads & Enquiries</span>
                    </a>
                </li>

                <!-- NAV ITEM Roles & Permissions -->
                <li class="nav-item <?php echo ($current_page == 'roles.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/roles.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-user-shield text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Roles & Staff</span>
                    </a>
                </li>


                <!-- NAV ITEM Audit Logs -->
                <li class="nav-item <?php echo ($current_page == 'audit_logs.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/audit_logs.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-history text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Audit Logs</span>
                    </a>
                </li>

                <!-- NAV ITEM Profile -->
                <li class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/profile.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-user-circle text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">My Profile</span>
                    </a>
                </li>

                <!-- NAV ITEM Settings -->
                <li class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-cog text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Site Settings</span>
                    </a>
                </li>
                
                <!-- NAV ITEM Form Builder -->
                <li class="nav-item <?php echo ($current_page == 'form_builder.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/admin/form_builder.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-list-alt text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Form Builder</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- NAV ITEM Unified Messaging - dynamically changes based on admin setting -->
                <li class="nav-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/<?php echo ($user_role == 'client') ? 'client/chat.php' : 'admin/chat.php'; ?>" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-comments text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">
                            <?php 
                            if ($user_role === 'client') {
                                echo 'Live Chat';
                            } else {
                                echo 'Client Chat';
                            }
                            ?>
                        </span>
                        <?php if (($chat_provider === 'internal' || $chat_provider === 'native') && $global_unread_chat > 0): ?>
                            <span class="badge badge-danger ml-auto" style="border-radius: 50%; padding: 4px 6px; font-size: 10px;"><?php echo $global_unread_chat; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- WRAPPER CONTENT STARTS -->
<div id="wrapper-content">
    <div class="container-fluid p-4">



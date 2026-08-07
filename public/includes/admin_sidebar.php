<?php
$user_role = $_SESSION['role'] ?? $_SESSION['admin_role'] ?? 'admin';
$user_name = $_SESSION['user_name'] ?? $_SESSION['admin_username'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* HIGH CONTRAST & PERFECT SIDEBAR POSITIONING */
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
    }
    .sidebar {
        display: block !important;
        height: 100% !important;
        background-color: #1f1b1c !important;
    }
    .sidebar-header {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #1f1b1c !important;
    }
    .sidebar-container, .os-host, .os-padding, .os-viewport, .os-content {
        display: block !important;
        height: auto !important;
        min-height: 100% !important;
        visibility: visible !important;
        opacity: 1 !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .os-content-glue {
        display: none !important;
        height: 0 !important;
        max-height: 0 !important;
    }
    .sidebar-nav {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        margin-bottom: 0 !important;
        padding-bottom: 30px !important;
        list-style: none !important;
    }
    .sidebar-nav > li {
        display: block !important;
        visibility: visible !important;
    }
    .sidebar-nav > li > a {
        display: flex !important;
        color: #ffffff !important;
        padding: 12px 18px !important;
        text-decoration: none !important;
        transition: all 0.2s ease !important;
    }
    .sidebar-nav > li > a:hover {
        background-color: rgba(254, 204, 86, 0.15) !important;
        color: #fecc56 !important;
    }
    .sidebar-nav > li.active > a {
        background-color: rgba(254, 204, 86, 0.25) !important;
        border-left: 4px solid #fecc56 !important;
        color: #fecc56 !important;
        font-weight: bold !important;
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

<!-- WRAPPER LEFT -->
<div id="wrapper-left">
    <div class="sidebar sidebar-dark sidebar-danger bg-dark">
        <!-- SIDEBAR HEADER -->
        <div class="sidebar-header border-fade p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(254, 204, 86, 0.2);">
            <a href="/<?php echo ($user_role === 'client') ? 'client/dashboard.php' : 'admin/index.php'; ?>" class="sidebar-brand text-decoration-none">
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
                    <a href="/<?php echo ($user_role == 'client') ? 'client/dashboard.php' : 'admin/index.php'; ?>" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-tachometer-alt text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Dashboard</span>
                    </a>
                </li>

                <?php if ($user_role !== 'client'): ?>
                <!-- NAV ITEM Cases -->
                <li class="nav-item <?php echo ($current_page == 'cases.php' || $current_page == 'case_view.php') ? 'active' : ''; ?>">
                    <a href="/admin/cases.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-folder-open text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Recovery Cases</span>
                    </a>
                </li>

                <!-- NAV ITEM Clients -->
                <li class="nav-item <?php echo ($current_page == 'client_manager.php') ? 'active' : ''; ?>">
                    <a href="/admin/client_manager.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-users text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Client Accounts</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'client'): ?>
                <!-- CLIENT PORTAL LINKS -->
                <li class="nav-item <?php echo ($current_page == 'my_cases.php') ? 'active' : ''; ?>">
                    <a href="/client/my_cases.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-file-invoice text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">My Recovery Cases</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($user_role === 'superadmin' || $user_role === 'admin'): ?>
                <!-- NAV ITEM Submissions -->
                <li class="nav-item <?php echo ($current_page == 'submissions.php') ? 'active' : ''; ?>">
                    <a href="/admin/submissions.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-inbox text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Leads & Enquiries</span>
                    </a>
                </li>

                <!-- NAV ITEM Roles & Permissions -->
                <li class="nav-item <?php echo ($current_page == 'roles.php') ? 'active' : ''; ?>">
                    <a href="/admin/roles.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-user-shield text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Roles & Staff</span>
                    </a>
                </li>

                <!-- NAV ITEM Invoices -->
                <li class="nav-item <?php echo ($current_page == 'invoices.php') ? 'active' : ''; ?>">
                    <a href="/admin/invoices.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-file-invoice-dollar text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Invoices & Billing</span>
                    </a>
                </li>

                <!-- NAV ITEM Audit Logs -->
                <li class="nav-item <?php echo ($current_page == 'audit_logs.php') ? 'active' : ''; ?>">
                    <a href="/admin/audit_logs.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-history text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Audit Logs</span>
                    </a>
                </li>

                <!-- NAV ITEM Settings -->
                <li class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <a href="/admin/settings.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-cog text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Site Settings</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- NAV ITEM Messages -->
                <li class="nav-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
                    <a href="/<?php echo ($user_role == 'client') ? 'client' : 'admin'; ?>/chat.php" class="nav-link d-flex align-items-center px-3 py-2">
                        <i class="fas fa-comments text-warning mr-3" style="width: 20px;"></i>
                        <span class="link-text text-white">Live Messages</span>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
<!-- WRAPPER CONTENT STARTS -->
<div id="wrapper-content">
    <div class="container-fluid p-4">

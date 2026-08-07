<?php
// admin/chat.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

// Fetch Tawk.to property ID for popout URL
$tawkto_raw = get_setting($pdo, 'tawkto_property_id', '6a742dd38875351d455643d1/default');
$clean_id = $tawkto_raw;
$clean_id = strip_tags($clean_id);
$clean_id = preg_replace('/<!--.*?-->/s', '', $clean_id);
$clean_id = preg_replace('/var\s+Tawk_API[\s\S]*?embed\.tawk\.to\//i', '', $clean_id);
$clean_id = preg_replace('/[\'"];.*$/s', '', $clean_id);
$clean_id = trim($clean_id, " \t\n\r;'\"/");
if (strpos($clean_id, 'embed.tawk.to/') !== false) {
    $clean_id = preg_replace('/.*embed\.tawk\.to\//', '', $clean_id);
    $clean_id = trim($clean_id, " \t\n\r;'\"");
}
if (empty($clean_id) || !preg_match('/^[a-zA-Z0-9_\/\-]{10,}$/', $clean_id)) {
    $clean_id = '6a742dd38875351d455643d1/default';
}

// Tawk.to popout URL format: https://tawk.to/chat/{PROPERTY_ID}
$tawk_popout_url = 'https://tawk.to/chat/' . $clean_id;

require_once '../includes/admin_header.php';
require_once '../includes/admin_sidebar.php';
?>

<style>
    #tawk-chat-frame {
        width: 100%;
        height: calc(100vh - 120px);
        border: none;
        border-radius: 12px;
        overflow: hidden;
        background: #181516;
        display: block;
    }
    .chat-header-bar {
        background: #181516;
        border: 1px solid rgba(254,204,86,0.25);
        border-radius: 12px;
        padding: 16px 22px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .live-dot {
        display: inline-block;
        width: 9px; height: 9px;
        background: #28d645;
        border-radius: 50%;
        margin-right: 8px;
        animation: blink 1.4s infinite;
    }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
    .chat-frame-wrapper {
        border: 1px solid rgba(254,204,86,0.2);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
</style>

<div class="chat-header-bar">
    <div>
        <h4 class="text-warning font-weight-bold mb-1">
            <i class="fas fa-comments mr-2"></i>Live Client Communications Desk
        </h4>
        <p class="text-muted mb-0" style="font-size: 13px;">
            <span class="live-dot"></span>
            All client, agent, and attorney conversations are managed here. Reply, assign, and monitor live.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2" style="gap: 10px;">
        <a href="<?= htmlspecialchars($tawk_popout_url) ?>" target="_blank" class="btn btn-sm btn-outline-warning font-weight-bold">
            <i class="fas fa-external-link-alt mr-1"></i> Open in New Tab
        </a>
        <a href="settings.php" class="btn btn-sm btn-outline-secondary font-weight-bold">
            <i class="fas fa-cog mr-1"></i> Chat Settings
        </a>
    </div>
</div>

<div class="chat-frame-wrapper">
    <iframe 
        id="tawk-chat-frame"
        src="<?= htmlspecialchars($tawk_popout_url) ?>"
        allow="microphone; camera; geolocation"
        loading="eager"
        title="IFW Global Live Client Chat Dashboard">
    </iframe>
</div>

<div class="mt-3 d-flex align-items-center justify-content-between" style="font-size: 12px; color: #555;">
    <span><i class="fas fa-shield-alt text-warning mr-1"></i> All conversations are encrypted and logged for compliance.</span>
    <span><i class="fas fa-info-circle mr-1"></i> Powered by Tawk.to · <a href="https://dashboard.tawk.to" target="_blank" class="text-warning">Open Full Dashboard</a></span>
</div>

<?php require_once '../includes/admin_footer.php'; ?>

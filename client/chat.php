<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
// client/chat.php
require_once '../config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['client_logged_in']) || !$_SESSION['client_logged_in']) {
    header("Location: login.php");
    exit;
}

$client_name = $_SESSION['client_name'] ?? 'Client';
$client_id = $_SESSION['client_portal_id'] ?? 0;

// Fetch Tawk property for embed
$tawkto_raw = get_setting($pdo, 'tawkto_property_id', '6a742dd38875351d455643d1/default');
$clean_id = strip_tags($tawkto_raw);
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

$tawk_popout_url = 'https://tawk.to/chat/' . $clean_id;
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
    <title>Secure Messaging — IFW Global Client Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; font-family: 'Montserrat', sans-serif; background: #0d0d0e; color: #fff; }
        
        .page-layout {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .top-bar {
            background: #181516;
            border-bottom: 1px solid rgba(254,204,86,0.2);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex: 0 0 auto;
        }
        .top-bar .brand {
            display: flex; align-items: center; gap: 12px;
        }
        .top-bar .brand h1 {
            font-size: 16px; font-weight: 700; color: #fecc56; letter-spacing: 0.5px;
        }
        .top-bar .brand small {
            display: block; font-size: 11px; color: #888; font-weight: 400;
        }
        .top-bar .nav-links {
            display: flex; align-items: center; gap: 12px;
        }
        .top-bar a {
            color: #888; font-size: 12px; text-decoration: none; 
            padding: 6px 12px; border-radius: 6px;
            transition: all 0.2s;
        }
        .top-bar a:hover { background: rgba(254,204,86,0.1); color: #fecc56; }
        .top-bar a.active { color: #fecc56; background: rgba(254,204,86,0.08); }
        
        .live-indicator {
            display: flex; align-items: center; gap: 7px; font-size: 12px; color: #28d645;
        }
        .live-dot {
            width: 8px; height: 8px; background: #28d645; border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.35} }
        
        .chat-frame-container {
            flex: 1;
            overflow: hidden;
            background: #181516;
            position: relative;
        }
        #client-tawk-frame {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
            background: #181516;
        }
        .frame-loader {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #181516;
            z-index: 10;
            transition: opacity 0.5s;
        }
        .frame-loader.hidden { opacity: 0; pointer-events: none; }
        .spinner {
            width: 44px; height: 44px;
            border: 3px solid rgba(254,204,86,0.2);
            border-top-color: #fecc56;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin-bottom: 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .frame-loader p { color: #888; font-size: 13px; }
    </style>
</head>
<body>
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>

<div class="page-layout">
    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="brand">
            <i class="fas fa-shield-alt" style="color:#fecc56; font-size: 22px;"></i>
            <div>
                <h1>IFW GLOBAL</h1>
                <small>Client Portal — Secure Messaging</small>
            </div>
        </div>
        <div class="nav-links">
            <div class="live-indicator">
                <div class="live-dot"></div>
                Live Chat Active
            </div>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt" style="margin-right:5px;"></i> Dashboard</a>
            <a href="my_cases.php"><i class="fas fa-folder-open" style="margin-right:5px;"></i> My Cases</a>
            <a href="<?= htmlspecialchars($tawk_popout_url) ?>" target="_blank">
                <i class="fas fa-external-link-alt" style="margin-right:5px;"></i> Popout
            </a>
            <a href="logout.php" style="color: #ff6b7a;">
                <i class="fas fa-sign-out-alt" style="margin-right:5px;"></i> Logout
            </a>
        </div>
    </div>

    <!-- CHAT FRAME -->
    <div class="chat-frame-container">
        <div class="frame-loader" id="frameLoader">
            <div class="spinner"></div>
            <p>Connecting to secure chat...</p>
        </div>
        <iframe
            id="client-tawk-frame"
            src="<?= htmlspecialchars($tawk_popout_url) ?>"
            allow="microphone; camera; geolocation"
            loading="eager"
            title="IFW Global Client Secure Messaging"
            onload="document.getElementById('frameLoader').classList.add('hidden')">
        </iframe>
    </div>
</div>

<!-- Auto-hide loader fallback -->
<script>
setTimeout(function() {
    var loader = document.getElementById('frameLoader');
    if (loader) loader.classList.add('hidden');
}, 4000);
</script>
</body>
</html>
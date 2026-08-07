<?php
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

$chat_provider = get_setting($pdo, 'chat_provider', 'tawkto');

if ($chat_provider !== 'internal') {
    // Tawk.to Logic
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if (get_setting($pdo, 'display_phone_numbers', 'show') === 'hide'): ?>
<style>
.alert__numbers, .phones__link, .phone-number, a[href^="tel:"] { display: none !important; visibility: hidden !important; }
.footer__address, .footer__details, address, .contact-details { display: none !important; visibility: hidden !important; }
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
        
        .page-layout { display: flex; flex-direction: column; height: 100vh; }
        .top-bar { background: #181516; border-bottom: 1px solid rgba(254,204,86,0.2); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; flex: 0 0 auto; }
        .top-bar .brand { display: flex; align-items: center; gap: 12px; }
        .top-bar .brand h1 { font-size: 16px; font-weight: 700; color: #fecc56; letter-spacing: 0.5px; }
        .top-bar .brand small { display: block; font-size: 11px; color: #888; font-weight: 400; }
        .top-bar .nav-links { display: flex; align-items: center; gap: 12px; }
        .top-bar a { color: #888; font-size: 12px; text-decoration: none; padding: 6px 12px; border-radius: 6px; transition: all 0.2s; }
        .top-bar a:hover { background: rgba(254,204,86,0.1); color: #fecc56; }
        .top-bar a.active { color: #fecc56; background: rgba(254,204,86,0.08); }
        .live-indicator { display: flex; align-items: center; gap: 7px; font-size: 12px; color: #28d645; }
        .live-dot { width: 8px; height: 8px; background: #28d645; border-radius: 50%; animation: blink 1.5s infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.35} }
        
        /* Native Chat Styles */
        #native-chat-container { display: flex; flex-direction: column; flex-grow: 1; background: #0d0d0e; }
        #chat-messages { flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .msg-bubble { max-width: 75%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.4; position: relative; }
        .msg-client { background: #fecc56; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; font-weight: 500; }
        .msg-admin { background: #222; color: #eee; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; text-align: right; }
        .msg-admin .msg-time { text-align: left; }
        #chat-input-area { padding: 15px 20px; background: #111; border-top: 1px solid #333; display: flex; gap: 10px; }
        #chat-input { flex-grow: 1; background: #222; border: 1px solid #444; color: #fff; padding: 12px 16px; border-radius: 25px; outline: none; font-family: inherit; }
        #chat-input:focus { border-color: #fecc56; }
        #chat-submit { background: #fecc56; color: #000; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: 0.2s; }
        #chat-submit:hover { transform: scale(1.05); }
    </style>
</head>
<body>

<div class="page-layout">
    <div class="top-bar">
        <div class="brand">
            <img src="../assets/ifw-logo.png" alt="IFW Logo" style="height:24px; filter:brightness(0) invert(1);">
            <div>
                <h1>SECURE PORTAL</h1>
                <small>ENCRYPTED CONNECTION</small>
            </div>
        </div>
        
        <div class="live-indicator">
            <div class="live-dot"></div>
            SYSTEM SECURE
        </div>
        
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a href="chat.php" class="active"><i class="fas fa-comment-alt"></i> Messages</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <?php if ($chat_provider === 'internal'): ?>
    
    <div id="native-chat-container">
        <div id="chat-messages">
            <div class="text-center p-4"><i class="fas fa-spinner fa-spin text-warning"></i> Connecting to Secure Chat...</div>
        </div>
        <form id="chat-input-area">
            <input type="text" id="chat-input" placeholder="Type a secure message..." autocomplete="off" required>
            <button type="submit" id="chat-submit"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastMsgId = 0;
        const elMessages = document.getElementById('chat-messages');
        const elForm = document.getElementById('chat-input-area');
        const elInput = document.getElementById('chat-input');

        function fetchMessages(scrollDown = false) {
            fetch(`ajax_chat.php?action=fetch&last_id=${lastMsgId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success' && data.messages.length > 0) {
                    if (lastMsgId === 0) elMessages.innerHTML = '';
                    let isScrolledToBottom = elMessages.scrollHeight - elMessages.clientHeight <= elMessages.scrollTop + 10;
                    
                    data.messages.forEach(m => {
                        let div = document.createElement('div');
                        let time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        div.className = 'msg-bubble ' + (m.sender_type === 'client' ? 'msg-client' : 'msg-admin');
                        div.innerHTML = `<div>${m.message.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}</div><div class="msg-time">${time}</div>`;
                        elMessages.appendChild(div);
                        lastMsgId = m.id;
                    });
                    
                    if (scrollDown || isScrolledToBottom) {
                        elMessages.scrollTop = elMessages.scrollHeight;
                    }
                } else if (lastMsgId === 0 && data.messages && data.messages.length === 0) {
                    elMessages.innerHTML = '<div class="text-center text-muted mt-5"><i class="fas fa-lock fa-3x mb-3 text-secondary"></i><h4>Secure Connection Established</h4><p>Your assigned investigator will respond shortly.</p></div>';
                }
            });
        }

        elForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let msg = elInput.value.trim();
            if (!msg) return;
            
            let formData = new FormData();
            formData.append('action', 'send');
            formData.append('message', msg);
            
            elInput.value = '';
            
            fetch('ajax_chat.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchMessages(true);
                }
            });
        });

        fetchMessages();
        setInterval(() => fetchMessages(false), 3000);
    });
    </script>
    
    <?php else: ?>
    
    <div style="flex:1; width:100%; height:100%; position:relative;">
        <iframe src="<?php echo htmlspecialchars($tawk_popout_url); ?>" frameborder="0" style="width:100%; height:100%; position:absolute; top:0; left:0;"></iframe>
    </div>
    
    <?php endif; ?>
</div>

</body>
</html>

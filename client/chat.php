<?php
// client/chat.php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$client_id = (int)$_SESSION['client_portal_id'];
$_SESSION['frontend_client_id'] = $client_id;
$_SESSION['role'] = 'client';

$chat_provider = isset($pdo) ? get_setting($pdo, 'chat_provider', 'internal') : 'internal';
$tawk_property = isset($pdo) ? get_setting($pdo, 'tawkto_property_id', '') : '';
$chatwoot_token = isset($pdo) ? get_setting($pdo, 'chatwoot_website_token', 'uHR3DJPM8AZ2Lpo8tDdJ5tei') : 'uHR3DJPM8AZ2Lpo8tDdJ5tei';
if (empty($chatwoot_token)) $chatwoot_token = 'uHR3DJPM8AZ2Lpo8tDdJ5tei';

$chatwoot_base_url = isset($pdo) ? get_setting($pdo, 'chatwoot_base_url', 'https://app.chatwoot.com') : 'https://app.chatwoot.com';
if (empty($chatwoot_base_url)) $chatwoot_base_url = 'https://app.chatwoot.com';

$chatwoot_hmac_key = isset($pdo) ? get_setting($pdo, 'chatwoot_hmac_key', '6q99KLZgjCtHCd1fvQpQTp2F') : '6q99KLZgjCtHCd1fvQpQTp2F';
if (empty($chatwoot_hmac_key)) $chatwoot_hmac_key = '6q99KLZgjCtHCd1fvQpQTp2F';

$chatwoot_account_id = isset($pdo) ? get_setting($pdo, 'chatwoot_account_id', '180927') : '180927';

// Load client profile for identity synchronization
$client_data = null;
try {
    $st_cl = $pdo->prepare("SELECT * FROM IFW_clients WHERE id = ?");
    $st_cl->execute([$client_id]);
    $client_data = $st_cl->fetch() ?: [];
} catch (Exception $e) {}

$client_name = trim(($client_data['first_name'] ?? '') . ' ' . ($client_data['last_name'] ?? '')) ?: 'Client #' . $client_id;
$client_email = $client_data['email'] ?? '';
$client_phone = $client_data['phone'] ?? '';
$client_avatar = get_portal_avatar_url($pdo, 'client', $client_id);
$portal_avatar_url = $client_avatar;
if (!empty($client_avatar) && strpos($client_avatar, 'http') !== 0) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'ifwglobalrecovery.site';
    $client_avatar = $scheme . '://' . $host . $client_avatar;
}

// Resolve Assigned Investigator / Agent info
$assigned_agent_id = (int)($client_data['assigned_agent_id'] ?? 0);
$agent_name_display = 'IFW Forensic & Legal Team';
$agent_role_display = 'Senior Forensic Director';
$agent_avatar_display = '/media/logos/shield-gold.svg';

if ($assigned_agent_id > 0) {
    try {
        $st_ag = $pdo->prepare("SELECT full_name, username, role, email, phone FROM IFW_users WHERE id = ?");
        $st_ag->execute([$assigned_agent_id]);
        $ag_row = $st_ag->fetch();
        if ($ag_row) {
            $agent_name_display = !empty($ag_row['full_name']) ? $ag_row['full_name'] : $ag_row['username'];
            $agent_role_display = !empty($ag_row['role']) ? ucwords(str_replace('_', ' ', $ag_row['role'])) : 'Senior Investigator';
            $agent_avatar_display = get_portal_avatar_url($pdo, 'admin', $assigned_agent_id);
        }
    } catch (Exception $e) {}
}

$chatwoot_user_identifier = 'client_' . $client_id;
$chatwoot_hmac_hash = !empty($chatwoot_hmac_key) ? hash_hmac('sha256', $chatwoot_user_identifier, $chatwoot_hmac_key) : '';

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<style>
/* Global Portal Chat Layout */
.chat-container {
    display: flex; flex-direction: column; height: 650px;
}
#chat-messages {
    flex-grow: 1; overflow-y: auto; padding: 20px; background-color: #0a0a0a; border-radius: 8px;
    display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth;
}
.msg-bubble {
    max-width: 75%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.4; position: relative; word-wrap: break-word;
}
.msg-client {
    background: #fecc56; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; font-weight: 500;
}
.msg-admin {
    background: #222226; color: #f8f9fa; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #444; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.msg-time { font-size: 0.72rem; margin-top: 5px; opacity: 0.75; }
.msg-client .msg-time { text-align: right; color: #333; }
.msg-admin .msg-time { text-align: left; color: #aaa; }
.msg-sender-name { font-size: 0.8rem; font-weight: bold; margin-bottom: 4px; }
.msg-client .msg-sender-name { color: #5a4200; text-align: right; }
.msg-admin .msg-sender-name { color: #fecc56; text-align: left; }

@media (max-width: 768px) {
    #wrapper-content, .container-fluid, .content-wrapper, #content {
        padding-left: 0 !important;
        padding-right: 0 !important;
        padding-top: 52px !important;
        padding-bottom: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        overflow-x: hidden !important;
    }
    .chat-top-row {
        padding: 8px 10px !important;
        margin: 0 !important;
        background: rgba(17, 21, 30, 0.98) !important;
        border-bottom: 1px solid rgba(254, 204, 86, 0.25) !important;
        width: 100vw !important;
        max-width: 100vw !important;
    }
    .chat-top-row h3 {
        font-size: 1.05rem !important;
        margin-bottom: 0 !important;
    }
    .client-chat-row {
        margin: 0 !important;
        padding: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
    }
    .client-chat-col {
        padding: 0 !important;
        margin: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        flex: 0 0 100vw !important;
    }
    .client-chat-card {
        border-radius: 0 !important;
        border: none !important;
        margin: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        background: #000000 !important;
        box-shadow: none !important;
    }
    .client-chat-card .card-header {
        padding: 8px 12px !important;
        border-radius: 0 !important;
        border-left: none !important;
        border-right: none !important;
    }
    .client-chat-iframe-body {
        height: calc(100dvh - 110px) !important;
        min-height: calc(100vh - 110px) !important;
        max-height: none !important;
        width: 100vw !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .client-chat-iframe {
        width: 100vw !important;
        height: 100% !important;
        min-height: 100% !important;
        border: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    #chat-messages {
        height: calc(100dvh - 180px) !important;
        min-height: 360px !important;
        max-height: none !important;
        padding: 10px 8px !important;
        border-radius: 0 !important;
        border-left: none !important;
        border-right: none !important;
    }
    .msg-bubble {
        max-width: 90% !important;
        font-size: 0.88rem !important;
        padding: 8px 12px !important;
    }
}
</style>

<div class="row chat-top-row">
    <div class="col-12 mb-2 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1" style="font-size: 1.25rem;">
                <i class="fas fa-comments mr-2"></i>Live Chat &amp; Support
            </h3>
            <p class="text-light small mb-0 d-none d-md-block">Send a message or ask a question. Our support team is here to help you.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-warning btn-sm font-weight-bold"><i class="fas fa-arrow-left mr-1"></i> Back to Dashboard</a>
    </div>
</div>

<div class="row m-0 client-chat-row">
    <?php if ($chat_provider === 'internal'): ?>
        <!-- INTERNAL LIVE CHAT (FULL WIDTH & MOBILE OPTIMIZED) -->
        <div class="col-12 p-0 mb-0 client-chat-col">
            <div class="card shadow-lg bg-dark border-secondary client-chat-card">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-2 px-3">
                    <div class="d-flex align-items-center">
                        <img src="<?= htmlspecialchars($agent_avatar_display) ?>" class="rounded-circle border border-warning mr-2 chat-avatar-me" width="38" height="38" style="object-fit:cover;" onerror="this.onerror=null;this.src='/admin_assets/img/profile/blank.png';">
                        <div>
                            <span class="text-warning font-weight-bold" style="font-size: 0.95rem;"><?= htmlspecialchars($agent_name_display) ?></span>
                            <div class="text-white small" style="font-size: 11px; opacity: 0.9;"><span class="text-success mr-1">●</span> <?= htmlspecialchars($agent_role_display) ?> &bull; Direct Case Line</div>
                        </div>
                    </div>
                    <span class="badge badge-success px-2 py-1 d-none d-sm-inline-block" style="font-size: 11px;"><i class="fas fa-lock mr-1"></i>256-Bit Encrypted</span>
                </div>
                <div class="card-body bg-dark text-white p-2 p-md-3 d-flex flex-column" style="min-height: 580px;">
                    <!-- Message Area -->
                    <div id="chat-messages" class="flex-grow-1 p-3 mb-2 border border-secondary rounded overflow-auto d-flex flex-column" style="min-height: 460px; height: 60vh; max-height: 720px; background-color: #0d0d0e; gap: 15px;">
                        <div class="text-center p-4 text-white"><i class="fas fa-spinner fa-spin text-warning mr-2"></i> Loading Secure Messaging Portal...</div>
                    </div>

                    <!-- Selected File Preview -->
                    <div id="selected-file-preview" class="text-warning small mb-2 d-none" style="font-weight: 500;">
                        <i class="fas fa-paperclip mr-1"></i> <span class="file-name"></span> 
                        <a href="#" class="text-danger ml-2" onclick="clearSelectedFile(event)">&times; Remove Attachment</a>
                    </div>

                    <!-- Input Form -->
                    <form id="chat-form" class="d-flex flex-wrap align-items-center mt-2" style="gap: 8px;" enctype="multipart/form-data">
                        <input type="file" id="chat-file-input" name="chat_file" style="display:none;" onchange="handleChatFileSelect(this)">
                        <button type="button" class="btn btn-outline-warning text-warning px-3 flex-shrink-0" style="height: 44px;" onclick="document.getElementById('chat-file-input').click()" title="Send an image or document"><i class="fas fa-paperclip"></i></button>
                        <input type="text" id="chat-input" class="form-control bg-dark text-white border-secondary p-2 flex-grow-1" placeholder="Type your message here..." autocomplete="off" required style="height: 44px; min-width: 160px; color: #ffffff !important;">
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-3 px-md-4 shadow flex-shrink-0" style="height: 44px;">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            let lastMsgId = 0;
            let pollingInterval = null;
            let isWindowFocused = true;

            const elChatMessages = document.getElementById('chat-messages');
            const elChatForm = document.getElementById('chat-form');
            const elChatInput = document.getElementById('chat-input');

            // Request Notification Permission
            if ("Notification" in window) {
                if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                    Notification.requestPermission();
                }
            }
            
            window.addEventListener('focus', () => isWindowFocused = true);
            window.addEventListener('blur', () => isWindowFocused = false);

            function showNotification(title, body) {
                if ("Notification" in window && Notification.permission === "granted" && !isWindowFocused) {
                    new Notification(title, { body: body, icon: '../admin_assets/img/logo/logo.svg' });
                }
            }

            function fetchMessages(scrollDown = false) {
                fetch(`ajax_chat.php?action=fetch&last_id=${lastMsgId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.messages.length > 0) {
                        if (lastMsgId === 0) elChatMessages.innerHTML = '';

                        let isScrolledToBottom = elChatMessages.scrollHeight - elChatMessages.clientHeight <= elChatMessages.scrollTop + 10;
                        let hasNewAdminMsg = false;

                        data.messages.forEach(m => {
                            let div = document.createElement('div');
                            let time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                            div.className = 'msg-bubble ' + (m.sender_type === 'client' ? 'msg-client' : 'msg-admin');

                            let senderLabel = "";
                            if (m.sender_type === 'admin') {
                                senderLabel = `<div class="msg-sender-name"><i class="fas fa-user-shield mr-1"></i>${m.sender_name || 'Staff Member'} (${m.sender_role || 'Agent'})</div>`;
                                hasNewAdminMsg = true;
                            } else {
                                senderLabel = `<div class="msg-sender-name"><i class="fas fa-user-circle mr-1"></i>You</div>`;
                            }

                            let attachmentMarkup = "";
                            if (m.attachment_path) {
                                let filename = m.attachment_name || "Attachment";
                                let fileicon = "fa-file";
                                let ext = filename.split('.').pop().toLowerCase();
                                if (['jpg','jpeg','png','gif'].includes(ext)) fileicon = "fa-file-image text-info";
                                else if (ext === 'pdf') fileicon = "fa-file-pdf text-danger";
                                else if (['doc','docx'].includes(ext)) fileicon = "fa-file-word text-primary";
                                else if (ext === 'zip') fileicon = "fa-file-archive text-warning";
                                
                                attachmentMarkup = `
                                    <div class="chat-attachment border border-secondary rounded p-2 bg-dark mt-2 d-flex align-items-center justify-content-between flex-wrap" style="width: 100%; max-width: 250px;">
                                        <div class="d-flex align-items-center">
                                            <i class="fas ${fileicon} fa-2x mr-2"></i>
                                            <div class="text-left" style="overflow: hidden;">
                                                <span class="small font-weight-bold d-block text-white text-truncate" style="max-width: 150px;">${filename}</span>
                                                <span class="text-muted" style="font-size:9px;">${(m.attachment_size / 1024).toFixed(1)} KB</span>
                                            </div>
                                        </div>
                                        <a href="../${m.attachment_path}" target="_blank" class="btn btn-sm btn-warning text-dark ml-2" download><i class="fas fa-download"></i></a>
                                    </div>
                                `;
                            }

                            div.innerHTML = `
                                ${senderLabel}
                                <div style="word-break: break-word;">${m.message.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}</div>
                                ${attachmentMarkup}
                                <div class="msg-time">${time}</div>
                            `;
                            elChatMessages.appendChild(div);
                            
                            if (hasNewAdminMsg && m.id > lastMsgId && lastMsgId > 0) {
                                if (typeof playNotificationChime === 'function') {
                                    playNotificationChime();
                                }
                                showNotification("New message from " + (m.sender_name || 'Support'), m.message);
                                if (typeof toastr !== 'undefined') {
                                    toastr.info(m.message, '💬 ' + (m.sender_name || 'Investigator'));
                                }
                            }
                            lastMsgId = Math.max(lastMsgId, m.id);
                        });

                        if (scrollDown || isScrolledToBottom) {
                            elChatMessages.scrollTop = elChatMessages.scrollHeight;
                        }
                    } else if (data.status === 'success' && lastMsgId === 0) {
                        elChatMessages.innerHTML = `
                            <div class="text-center text-muted py-5 my-auto">
                                <i class="fas fa-user-shield text-warning mb-3" style="font-size: 3.5rem;"></i>
                                <h5>Connected to Recovery Team</h5>
                                <p>Type your message below to send a direct update to your assigned case investigator.</p>
                            </div>
                        `;
                    }
                });
            }

            elChatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let msg = elChatInput.value.trim();
                let fileInput = document.getElementById('chat-file-input');
                let hasFile = fileInput && fileInput.files.length > 0;
                
                if (!msg && !hasFile) return;

                let formData = new FormData();
                formData.append('action', 'send');
                formData.append('message', msg);
                if (hasFile) {
                    formData.append('chat_file', fileInput.files[0]);
                }

                elChatInput.value = '';
                if (fileInput) fileInput.value = '';
                document.getElementById('selected-file-preview').classList.add('d-none');
                elChatInput.setAttribute('required', 'required');

                fetch('ajax_chat.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        fetchMessages(true);
                    }
                });
            });

            window.handleChatFileSelect = function(input) {
                let preview = document.getElementById('selected-file-preview');
                let nameSpan = preview.querySelector('.file-name');
                if (input.files && input.files[0]) {
                    nameSpan.textContent = input.files[0].name;
                    preview.classList.remove('d-none');
                    elChatInput.removeAttribute('required');
                } else {
                    preview.classList.add('d-none');
                    elChatInput.setAttribute('required', 'required');
                }
            };

            window.clearSelectedFile = function(e) {
                if (e) e.preventDefault();
                let input = document.getElementById('chat-file-input');
                if (input) input.value = '';
                document.getElementById('selected-file-preview').classList.add('d-none');
                elChatInput.setAttribute('required', 'required');
            };

            fetchMessages(true);
            pollingInterval = setInterval(() => fetchMessages(false), 2000);
        });
        </script>

    <?php elseif ($chat_provider === 'tawkto' || $chat_provider === 'tawk'): ?>
        <!-- TAWK.TO FULL-HEIGHT PROFESSIONAL IN-PAGE IFRAME (MOBILE FULL SCREEN EDGE-TO-EDGE) -->
        <div class="col-12 p-0 mb-0 client-chat-col">
            <div class="card shadow-lg bg-dark border-secondary client-chat-card">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-2 px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-headset fa-lg mr-2 mr-md-3 text-warning"></i>
                        <div>
                            <span class="d-block text-white font-weight-bold" style="font-size: 14px;">Live 24/7 Global Case Support Desk</span>
                            <small class="text-white small" style="font-size: 11px; opacity: 0.85;">Direct encrypted communication channel with IFW recovery team</small>
                        </div>
                    </div>
                    <span class="badge badge-success px-3 py-2 d-none d-sm-inline-block" style="font-size: 12px;"><i class="fas fa-circle mr-1" style="font-size:8px;"></i> Online</span>
                </div>
                
                <?php
                $clean_tawk = $tawk_property;
                $clean_tawk = strip_tags($clean_tawk);
                $clean_tawk = preg_replace('/<!--.*?-->/s', '', $clean_tawk);
                $clean_tawk = preg_replace('/var\s+Tawk_API[\s\S]*?embed\.tawk\.to\//i', '', $clean_tawk);
                $clean_tawk = preg_replace('/[\'"];.*$/s', '', $clean_tawk);
                $clean_tawk = trim($clean_tawk, " \t\n\r;'\"/");
                if (strpos($clean_tawk, 'tawk.to/chat/') !== false) {
                    $clean_tawk = preg_replace('/.*tawk\.to\/chat\//', '', $clean_tawk);
                } elseif (strpos($clean_tawk, 'embed.tawk.to/') !== false) {
                    $clean_tawk = preg_replace('/.*embed\.tawk\.to\//', '', $clean_tawk);
                }
                $clean_tawk = trim($clean_tawk, " \t\n\r;'\"/");
                $parts = explode('/', $clean_tawk);
                $prop_id = $parts[0] ?? '';
                $chat_hash = $parts[1] ?? 'default';
                $direct_chat_url = "https://tawk.to/chat/{$prop_id}/{$chat_hash}";
                ?>

                <div class="card-body bg-black p-0 client-chat-iframe-body" style="height: calc(100dvh - 120px); min-height: 520px; position: relative;">
                    <?php if (!empty($prop_id)): ?>
                        <iframe 
                            src="<?= htmlspecialchars($direct_chat_url) ?>" 
                            class="client-chat-iframe"
                            style="width: 100%; height: 100%; min-height: 100%; border: none; display: block; background: #000;" 
                            allow="camera; microphone; autoplay; encrypted-media;"
                            title="IFW Live Support">
                        </iframe>
                    <?php else: ?>
                        <div class="p-5 text-center text-white">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <h5 class="text-warning">Live Chat Configuration Pending</h5>
                            <p class="text-white">Tawk.to property ID is not configured in Admin Settings.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($chat_provider === 'chatwoot'): ?>
        <!-- CHATWOOT LIVE SUPPORT (DOCKED FULL-HEIGHT CRM WITH PERSISTENT CLIENT IDENTITY) -->
        <style>
        /* Dock Chatwoot into the card seamlessly */
        .chatwoot-docked-container {
            position: relative;
            width: 100%;
            height: calc(100dvh - 120px);
            min-height: 580px;
            background: #000000;
            overflow: hidden;
        }
        .woot-widget-holder {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            max-height: 100% !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            border: none !important;
            z-index: 10 !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .woot-widget-holder iframe {
            width: 100% !important;
            height: 100% !important;
            min-height: 100% !important;
            border: none !important;
            border-radius: 0 !important;
        }
        .woot--bubble-holder {
            display: none !important; /* Hide floating bubble on this dedicated full page */
        }
        </style>

        <div class="col-12 p-0 mb-0 client-chat-col">
            <div class="card shadow-lg bg-dark border-secondary client-chat-card">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center py-2 px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-headset fa-lg mr-2 mr-md-3 text-warning"></i>
                        <div>
                            <span class="d-block text-white font-weight-bold" style="font-size: 14px;">Live 24/7 Global Case Support Desk</span>
                            <small class="text-white small" style="font-size: 11px; opacity: 0.85;">Direct encrypted communication channel with IFW recovery team &bull; <?= htmlspecialchars($client_name) ?></small>
                        </div>
                    </div>
                    <span class="badge badge-success px-3 py-2 d-none d-sm-inline-block" style="font-size: 12px;"><i class="fas fa-shield-alt mr-1"></i> Identity Verified</span>
                </div>

                <div class="card-body bg-black p-0 client-chat-iframe-body chatwoot-docked-container" id="chatwoot-mount-frame">
                    <?php if (!empty($chatwoot_token)): ?>
                        <!-- Loading placeholder until Chatwoot SDK mounts -->
                        <div id="chatwoot-loading-ph" class="p-5 text-center text-white my-auto d-flex flex-column align-items-center justify-content-center h-100">
                            <i class="fas fa-spinner fa-spin text-warning fa-3x mb-3"></i>
                            <h5 class="text-warning">Connecting to Secure Case Line...</h5>
                            <p class="text-white small mb-0">Synchronizing client credentials &amp; loading conversation history.</p>
                        </div>

                        <!-- Chatwoot Native SDK Integration with User Identity Validation -->
                        <script>
                        window.chatwootSettings = {
                            hideMessageBubble: false,
                            position: 'right',
                            locale: 'en',
                            type: 'expanded_bubble',
                            darkMode: 'dark'
                        };
                        (function(d,t) {
                            var BASE_URL = "<?= htmlspecialchars($chatwoot_base_url ?: 'https://app.chatwoot.com') ?>";
                            var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
                            g.src=BASE_URL+"/packs/js/sdk.js";
                            g.async = true;
                            g.defer = true;
                            s.parentNode.insertBefore(g,s);
                            g.onload=function(){
                                window.chatwootSDK.run({
                                    websiteToken: '<?= htmlspecialchars($chatwoot_token) ?>',
                                    baseUrl: BASE_URL
                                });
                            }
                        })(document,"script");

                        window.addEventListener("chatwoot:ready", function () {
                            if (window.$chatwoot) {
                                // Authenticate client with Chatwoot
                                window.$chatwoot.setUser('<?= $chatwoot_user_identifier ?>', {
                                    name: '<?= addslashes($client_name) ?>',
                                    email: '<?= addslashes($client_email) ?>',
                                    avatar_url: '<?= addslashes($client_avatar) ?>',
                                    phone_number: '<?= addslashes($client_phone) ?>',
                                    <?php if (!empty($chatwoot_hmac_hash)): ?>
                                    identifier_hash: '<?= $chatwoot_hmac_hash ?>',
                                    <?php endif; ?>
                                });

                                window.$chatwoot.setCustomAttributes({
                                    client_id: '<?= (int)$client_id ?>',
                                    portal: 'IFW Client Portal'
                                });

                                // Open Chatwoot in the docked card
                                window.$chatwoot.toggle("open");
                                
                                var ph = document.getElementById('chatwoot-loading-ph');
                                if (ph) {
                                    setTimeout(function() { ph.style.display = 'none'; }, 600);
                                }
                            }
                        });
                        </script>
                    <?php else: ?>
                        <div class="p-5 text-center text-white">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <h5 class="text-warning">Chatwoot Configuration Pending</h5>
                            <p class="text-white">Chatwoot Website Token is not configured in Admin Settings.</p>
                            <p class="text-muted small">Please go to <strong>Admin Settings &rarr; Live Chat</strong> and enter your Chatwoot Website Token.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- CUSTOM EMBED / FALLBACK -->
        <div class="col-12 mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
                    <i class="fas fa-comments mr-2"></i>Custom Integration Support
                </div>
                <div class="card-body bg-dark text-white p-5 text-center">
                    <i class="fas fa-comment-dots text-warning fa-4x mb-4"></i>
                    <h4 class="font-weight-bold">Live Chat Widget Active</h4>
                    <p class="text-muted max-width-500 mx-auto">
                        A custom support chat system is currently active on our platform. 
                        Use the chat widget on the bottom right of the screen to connect directly with our support team.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once $dir . '/includes/admin_footer.php'; ?>
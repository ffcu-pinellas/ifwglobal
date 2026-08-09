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

$client_id = $_SESSION['client_portal_id'];
$_SESSION['frontend_client_id'] = $client_id;
$_SESSION['role'] = 'client';

$chat_provider = isset($pdo) ? get_setting($pdo, 'chat_provider', 'internal') : 'internal';
$tawk_property = isset($pdo) ? get_setting($pdo, 'tawkto_property_id', '') : '';

require_once $dir . '/includes/admin_header.php';
require_once $dir . '/includes/admin_sidebar.php';
?>

<div class="row">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-warning font-weight-bold mb-1">
                <i class="fas fa-comments mr-2"></i>
                <?php 
                if ($chat_provider === 'tawkto' || $chat_provider === 'tawk') echo 'Live Chat Support';
                elseif ($chat_provider === 'manychat') echo 'ManyChat Support';
                elseif ($chat_provider === 'custom') echo 'Support Center';
                else echo 'Secure Case Messaging';
                ?>
            </h3>
            <p class="text-muted mb-0">Direct support channel with your assigned recovery team & case investigators.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-warning btn-sm font-weight-bold"><i class="fas fa-arrow-left mr-1"></i> Back to Dashboard</a>
    </div>
</div>

<div class="row">
    <?php if ($chat_provider === 'internal'): ?>
        <!-- INTERNAL SECURE CHAT -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-shield mr-2"></i>Internal Case Messaging Desk</span>
                    <span class="badge badge-success px-3 py-1"><i class="fas fa-lock mr-1"></i>256-Bit Encrypted</span>
                </div>
                <div class="card-body bg-dark text-white p-3 d-flex flex-column" style="min-height: 520px;">
                    <!-- Message Area -->
                    <div id="chat-messages" class="flex-grow-1 p-3 mb-3 border border-secondary rounded overflow-auto d-flex flex-column" style="height: 380px; background-color: #0d0d0e; gap: 15px;">
                        <div class="text-center p-4 text-muted"><i class="fas fa-spinner fa-spin text-warning"></i> Loading Secure Messaging Portal...</div>
                    </div>

                    <!-- Selected File Preview -->
                    <div id="selected-file-preview" class="text-warning small mb-2 d-none" style="font-weight: 500;">
                        <i class="fas fa-paperclip mr-1"></i> <span class="file-name"></span> 
                        <a href="#" class="text-danger ml-2" onclick="clearSelectedFile(event)">&times; Remove Attachment</a>
                    </div>

                    <!-- Input Form -->
                    <form id="chat-form" class="d-flex flex-wrap align-items-center mt-2" style="gap: 10px;" enctype="multipart/form-data">
                        <input type="file" id="chat-file-input" name="chat_file" style="display:none;" onchange="handleChatFileSelect(this)">
                        <button type="button" class="btn btn-outline-warning text-warning px-3 flex-shrink-0" style="height: 48px;" onclick="document.getElementById('chat-file-input').click()" title="Share File/Document"><i class="fas fa-paperclip"></i></button>
                        <input type="text" id="chat-input" class="form-control bg-dark text-white border-secondary p-3 flex-grow-1" placeholder="Type a secure message..." autocomplete="off" required style="height: 48px; min-width: 150px;">
                        <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow flex-shrink-0" style="height: 48px;">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <style>
        .chat-container {
            display: flex; flex-direction: column; height: 600px;
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
            background: #2a2a2a; color: #f8f9fa; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #444;
        }
        .msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; }
        .msg-client .msg-time { text-align: right; }
        .msg-admin .msg-time { text-align: left; }
        .msg-sender-name { font-size: 0.75rem; font-weight: bold; margin-bottom: 4px; }
        .msg-client .msg-sender-name { color: #856404; text-align: right; }
        .msg-admin .msg-sender-name { color: #fecc56; text-align: left; }
        </style>

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
                            
                            if (hasNewAdminMsg && m.id > lastMsgId) {
                                showNotification("New message from " + (m.sender_name || 'Support'), m.message);
                            }
                            lastMsgId = m.id;
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
            pollingInterval = setInterval(() => fetchMessages(false), 3000);
        });
        </script>

    <?php elseif ($chat_provider === 'tawkto' || $chat_provider === 'tawk'): ?>
        <!-- TAWK.TO DIRECT EMBED -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-headset mr-2"></i>Live 24/7 Support Desk</span>
                    <span class="badge badge-warning text-dark font-weight-bold">Tawk.to Chat</span>
                </div>
                <div class="card-body bg-dark p-2" style="height: 550px;">
                    <?php if (!empty($tawk_property)): ?>
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
                        $iframe_src = "https://tawk.to/chat/{$prop_id}/{$chat_hash}?pop=1";
                        ?>
                        <iframe src="<?= htmlspecialchars($iframe_src) ?>" style="width: 100%; height: 100%; border: none; border-radius: 8px; background: #111;"></iframe>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <h5>Tawk.to Support is Active</h5>
                            <p>Please use the chat bubble in the bottom right corner of the page to speak with our support staff.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($chat_provider === 'manychat'): ?>
        <!-- MANYCHAT CONSOLE -->
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card shadow-lg bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary text-warning font-weight-bold">
                    <i class="fas fa-headset mr-2"></i>ManyChat Virtual Assistant
                </div>
                <div class="card-body bg-dark text-white p-5 text-center">
                    <i class="fab fa-facebook-messenger text-warning fa-4x mb-4"></i>
                    <h4 class="font-weight-bold">ManyChat Support Enabled</h4>
                    <p class="text-muted max-width-500 mx-auto">
                        We have integrated ManyChat AI automation to resolve your queries instantly. 
                        Please locate the Chat widget in the bottom right corner of your screen to start a conversation with our automated assistant or a live agent.
                    </p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- CUSTOM EMBED / FALLBACK -->
        <div class="col-lg-8 mx-auto mb-4">
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
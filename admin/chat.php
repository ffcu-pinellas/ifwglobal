<?php
// admin/chat.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();

$chat_provider = get_setting($pdo, 'chat_provider', 'internal');
$tawk_property = get_setting($pdo, 'tawkto_property_id', '');
$manychat_code = get_setting($pdo, 'manychat_script_code', '');
$custom_code   = get_setting($pdo, 'custom_chat_code', '');

// If NOT internal, show a provider page instead of the internal chat
if ($chat_provider !== 'internal') {
    require_once '../includes/admin_header.php';
    require_once '../includes/admin_sidebar.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-warning font-weight-bold mb-1"><i class="fas fa-headset mr-2"></i>Client Communications</h3>
            <p class="text-muted mb-0">Live support chat via your configured provider.</p>
        </div>
        <a href="index.php" class="btn btn-outline-warning btn-sm font-weight-bold"><i class="fas fa-arrow-left mr-1"></i> Back to Dashboard</a>
    </div>
    <div class="card shadow-lg bg-dark border-secondary mb-4">
        <div class="card-header bg-dark text-warning border-secondary font-weight-bold">
            <i class="fas fa-comments mr-2"></i>
            <?php if ($chat_provider === 'tawkto' || $chat_provider === 'tawk'): ?>
                Tawk.to Live Support Console
            <?php elseif ($chat_provider === 'manychat'): ?>
                ManyChat Messenger Console
            <?php else: ?>
                Custom Chat Provider
            <?php endif; ?>
            <span class="badge badge-warning text-dark ml-2"><?= ucfirst($chat_provider) ?> (Active)</span>
        </div>
        <div class="card-body bg-dark p-3">
            <?php if ($chat_provider === 'tawkto' || $chat_provider === 'tawk'): ?>
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
                    <div class="text-center py-5 text-muted bg-black rounded border border-secondary p-4">
                        <div style="width:72px;height:72px;border-radius:50%;background:rgba(254, 204, 86, 0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fas fa-headset fa-3x text-warning"></i>
                        </div>
                        <h4 class="text-white font-weight-bold mb-3">Tawk.to Integration Active</h4>
                        <p class="px-md-5 mb-4 text-light" style="max-width: 600px; margin: 0 auto; line-height: 1.6;">
                            Your live chat communications are routed securely through Tawk.to. 
                            To respond to client messages in real-time, please use the secure Tawk.to Admin Console.
                        </p>
                        <a href="https://dashboard.tawk.to" target="_blank" class="btn btn-warning text-dark font-weight-bold px-4 py-2 shadow-lg mb-3">
                            <i class="fas fa-external-link-alt mr-2"></i> Open Tawk.to Agent Console
                        </a>
                        <div class="small text-muted mt-2">
                            Property ID: <code class="text-warning"><?= htmlspecialchars($prop_id) ?></code> &bull; 
                            Chat Hash: <code class="text-warning"><?= htmlspecialchars($chat_hash) ?></code>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning font-weight-bold">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Tawk.to is selected but no Property ID is configured. 
                        <a href="settings.php" class="btn btn-sm btn-warning text-dark ml-2">Configure in Settings</a>
                    </div>
                <?php endif; ?>

            <?php elseif ($chat_provider === 'manychat'): ?>
                <div class="alert alert-info border-0 mb-3 bg-dark" style="border-left: 4px solid #fecc56 !important;">
                    <i class="fas fa-info-circle mr-2"></i>
                    ManyChat is configured as your chat provider. Manage conversations from your ManyChat dashboard.
                    <a href="https://manychat.com" target="_blank" class="btn btn-sm btn-warning text-dark font-weight-bold ml-2">Open ManyChat Dashboard <i class="fas fa-external-link-alt ml-1"></i></a>
                </div>
                <?php if (!empty($manychat_code)): ?>
                    <div class="text-muted small text-center py-5">
                        <i class="fas fa-check-circle text-success fa-3x mb-3 d-block"></i>
                        ManyChat widget is active on the client-facing pages. Your clients can reach you there.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-warning"><i class="fas fa-info-circle mr-2"></i>Custom chat provider is active. Manage from your provider's dashboard.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require_once '../includes/admin_footer.php';
    exit;
}

// If we reach here: provider = internal
require_once '../includes/admin_header.php';
?>
<div class="container-fluid mt-4">
    <div class="row h-100" style="min-height: 75vh;">
        <!-- Sidebar: Client List -->
        <div class="col-md-4 col-lg-3 border-right border-secondary bg-dark d-flex flex-column p-0">
            <div class="p-3 border-bottom border-secondary bg-black d-flex align-items-center">
                <a href="index.php" class="btn btn-sm btn-outline-warning mr-3"><i class="fas fa-arrow-left"></i> Back</a>
                <h5 class="text-warning m-0"><i class="fas fa-users mr-2"></i> Client Conversations</h5>
            </div>
            <div class="flex-grow-1 overflow-auto" id="client-list" style="background: #111;">
                <!-- Dynamically loaded via AJAX -->
                <div class="text-center p-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="col-md-8 col-lg-9 bg-dark d-flex flex-column p-0 position-relative">
            <div id="chat-header" class="p-3 border-bottom border-secondary bg-black d-none align-items-center justify-content-between">
                <h5 class="text-light m-0" id="chat-client-name"><i class="fas fa-user-circle mr-2 text-warning"></i> <span>Select a client</span></h5>
                <a href="#" id="view-client-btn" class="btn btn-sm btn-outline-warning">View Profile</a>
            </div>
            <div id="chat-messages" class="flex-grow-1 p-4 overflow-auto d-flex flex-column" style="background: #0d0d0e; gap: 15px;">
                <div class="text-center text-muted mt-5">
                    <i class="fas fa-comments fa-3x mb-3 text-secondary"></i>
                    <h4>Secure Messaging Portal</h4>
                    <p>Select a client from the list to start messaging.</p>
                </div>
            </div>
            <div id="chat-input-area" class="p-3 border-top border-secondary bg-black d-none">
                <!-- Selected File Preview -->
                <div id="selected-file-preview" class="text-warning small mb-2 d-none" style="font-weight: 500;">
                    <i class="fas fa-paperclip mr-1"></i> <span class="file-name"></span> 
                    <a href="#" class="text-danger ml-2" onclick="clearSelectedFile(event)">&times; Remove Attachment</a>
                </div>
                <form id="chat-form" class="d-flex w-100 flex-wrap mt-2" style="gap: 10px;" enctype="multipart/form-data">
                    <input type="hidden" id="active-client-id" value="">
                    <input type="file" id="chat-file-input" name="chat_file" style="display:none;" onchange="handleChatFileSelect(this)">
                    <button type="button" class="btn btn-outline-warning text-warning px-3 flex-shrink-0" onclick="document.getElementById('chat-file-input').click()" title="Share File/Document"><i class="fas fa-paperclip"></i></button>
                    <input type="text" id="chat-input" class="form-control bg-dark text-light border-secondary flex-grow-1" placeholder="Type a secure message..." autocomplete="off" required style="min-width: 150px;">
                    <button type="submit" class="btn btn-warning px-4 font-weight-bold flex-shrink-0"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styling for premium chat feel */
.client-item {
    padding: 15px 20px;
    border-bottom: 1px solid #222;
    cursor: pointer;
    transition: background 0.2s;
    color: #ccc;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.client-item:hover { background: #1a1a1a; }
.client-item.active { background: #2a2a2a; color: #fecc56; border-left: 4px solid #fecc56; }
.unread-badge { background: #fecc56; color: #000; font-weight: bold; border-radius: 12px; padding: 2px 8px; font-size: 0.8rem; }

.msg-bubble {
    max-width: 75%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.4; position: relative; word-wrap: break-word;
}
.msg-client {
    background: #2a2a2a; color: #f8f9fa; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #444;
}
.msg-admin {
    background: #fecc56; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; font-weight: 500;
}
.msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; }
.msg-client .msg-time { text-align: left; }
.msg-admin .msg-time { text-align: right; }
.msg-sender-name { font-size: 0.75rem; font-weight: bold; margin-bottom: 4px; }
.msg-client .msg-sender-name { color: #17a2b8; text-align: left; }
.msg-admin .msg-sender-name { color: #856404; text-align: right; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let activeClientId = null;
    let lastMsgId = 0;
    let pollingInterval = null;
    let isWindowFocused = true;

    // Elements
    const elClientList = document.getElementById('client-list');
    const elChatMessages = document.getElementById('chat-messages');
    const elChatHeader = document.getElementById('chat-header');
    const elChatInputArea = document.getElementById('chat-input-area');
    const elClientName = document.querySelector('#chat-client-name span');
    const elChatForm = document.getElementById('chat-form');
    const elChatInput = document.getElementById('chat-input');
    const elActiveClientId = document.getElementById('active-client-id');
    const elViewClientBtn = document.getElementById('view-client-btn');

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

    // Fetch Clients List
    function fetchClients() {
        fetch('ajax_chat.php?action=fetch_clients')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                elClientList.innerHTML = '';
                if (data.clients.length === 0) {
                    elClientList.innerHTML = '<div class="p-4 text-muted text-center">No clients assigned.</div>';
                    return;
                }
                data.clients.forEach(c => {
                    let div = document.createElement('div');
                    div.className = 'client-item' + (activeClientId == c.id ? ' active' : '');
                    div.innerHTML = `
                        <div><i class="fas fa-user-circle mr-2"></i> ${c.first_name} ${c.last_name}</div>
                        ${c.unread > 0 ? `<span class="unread-badge">${c.unread}</span>` : ''}
                    `;
                    div.addEventListener('click', () => selectClient(c.id, c.first_name + ' ' + c.last_name));
                    elClientList.appendChild(div);
                    
                    if (c.unread > 0 && c.id == activeClientId) {
                        fetchMessages(true);
                    } else if (c.unread > 0 && !activeClientId) {
                        showNotification("New message from " + c.first_name, "You have an unread message.");
                    }
                });
            }
        });
    }

    function selectClient(id, name) {
        activeClientId = id;
        lastMsgId = 0;
        elClientName.innerText = name;
        elActiveClientId.value = id;
        elChatHeader.classList.remove('d-none');
        elChatHeader.classList.add('d-flex');
        elChatInputArea.classList.remove('d-none');
        elViewClientBtn.href = `client_manager.php`;
        
        elChatMessages.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin text-warning"></i></div>';
        
        // Update UI selection
        document.querySelectorAll('.client-item').forEach(el => el.classList.remove('active'));
        
        fetchClients(); // refresh list to clear badges
        fetchMessages(true);
        
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => { fetchMessages(false); fetchClients(); }, 3000);
    }

    function fetchMessages(scrollDown = false) {
        if (!activeClientId) return;
        fetch(`ajax_chat.php?action=fetch_messages&client_id=${activeClientId}&last_id=${lastMsgId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.messages.length > 0) {
                if (lastMsgId === 0) elChatMessages.innerHTML = ''; // clear loader
                
                let isScrolledToBottom = elChatMessages.scrollHeight - elChatMessages.clientHeight <= elChatMessages.scrollTop + 10;
                
                data.messages.forEach(m => {
                    let div = document.createElement('div');
                    let time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    div.className = 'msg-bubble ' + (m.sender_type === 'admin' ? 'msg-admin' : 'msg-client');
                    
                    let senderLabel = "";
                    if (m.sender_type === 'admin') {
                        senderLabel = `<div class="msg-sender-name"><i class="fas fa-user-shield mr-1"></i>You</div>`;
                    } else {
                        senderLabel = `<div class="msg-sender-name"><i class="fas fa-user-circle mr-1"></i>${m.sender_name || 'Client'}</div>`;
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
                            <div class="chat-attachment border border-secondary rounded p-2 bg-dark mt-2 d-flex align-items-center justify-content-between flex-wrap" style="width: 100%;">
                                <div class="d-flex align-items-center flex-grow-1" style="min-width: 150px;">
                                    <i class="fas ${fileicon} fa-2x mr-2"></i>
                                    <div class="text-left" style="overflow: hidden;">
                                        <span class="small font-weight-bold d-block text-white text-truncate">${filename}</span>
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
                    lastMsgId = m.id;
                });
                
                if (scrollDown || isScrolledToBottom) {
                    elChatMessages.scrollTop = elChatMessages.scrollHeight;
                }
            }
        });
    }

    elChatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        let msg = elChatInput.value.trim();
        let fileInput = document.getElementById('chat-file-input');
        let hasFile = fileInput && fileInput.files.length > 0;
        
        if (!activeClientId) return;
        if (!msg && !hasFile) return;
        
        let formData = new FormData();
        formData.append('action', 'send');
        formData.append('client_id', activeClientId);
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

    // Initial load
    fetchClients();
    setInterval(fetchClients, 10000); // Polling for new clients / global unread counts
});
</script>
<?php require_once '../includes/admin_footer.php'; ?>

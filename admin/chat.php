<?php
// admin/chat.php
require_once '../config.php';
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/admin_header.php';
?>
<div class="container-fluid mt-4">
    <div class="row h-100" style="min-height: 75vh;">
        <!-- Sidebar: Client List -->
        <div class="col-md-4 col-lg-3 border-right border-secondary bg-dark d-flex flex-column p-0">
            <div class="p-3 border-bottom border-secondary bg-black">
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
                <form id="chat-form" class="d-flex w-100" style="gap: 10px;">
                    <input type="hidden" id="active-client-id" value="">
                    <input type="text" id="chat-input" class="form-control bg-dark text-light border-secondary" placeholder="Type a secure message..." autocomplete="off" required>
                    <button type="submit" class="btn btn-warning px-4 font-weight-bold"><i class="fas fa-paper-plane"></i></button>
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
    max-width: 70%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.4; position: relative;
}
.msg-client {
    background: #222; color: #eee; align-self: flex-start; border-bottom-left-radius: 4px;
}
.msg-admin {
    background: #fecc56; color: #000; align-self: flex-end; border-bottom-right-radius: 4px; font-weight: 500;
}
.msg-time { font-size: 0.7rem; margin-top: 5px; opacity: 0.7; text-align: right; }
.msg-client .msg-time { text-align: left; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let activeClientId = null;
    let lastMsgId = 0;
    let pollingInterval = null;

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
        pollingInterval = setInterval(() => fetchMessages(false), 3000);
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
                    div.innerHTML = `<div>${m.message.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}</div><div class="msg-time">${time}</div>`;
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
        if (!msg || !activeClientId) return;
        
        let formData = new FormData();
        formData.append('action', 'send');
        formData.append('client_id', activeClientId);
        formData.append('message', msg);
        
        elChatInput.value = '';
        
        fetch('ajax_chat.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                fetchMessages(true);
            }
        });
    });

    // Initial load
    fetchClients();
    setInterval(fetchClients, 10000); // Polling for new clients / global unread counts
});
</script>
<?php require_once '../includes/admin_footer.php'; ?>

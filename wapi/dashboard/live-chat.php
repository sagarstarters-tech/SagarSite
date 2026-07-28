<?php
/**
 * WAPI SaaS - Live Chat Page
 * Shows incoming and outgoing messages in real-time
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$hideNav = true;

// Get active WhatsApp account (needed by both AJAX handlers and page render)
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active' LIMIT 1", [$userId]);

// Handle AJAX actions (Send Message / Clear Chat)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax() && isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    }

    // --- Send Message from Live Chat ---
    if ($_POST['action'] === 'send_message') {
        $to = sanitize($_POST['to'] ?? '');
        $content = $_POST['content'] ?? '';

        if (empty($to) || empty(trim($content))) {
            jsonResponse(['success' => false, 'message' => 'Recipient and message content are required.']);
        }

        if (!$waAccount) {
            jsonResponse(['success' => false, 'message' => 'No active WhatsApp account. Please configure your WhatsApp API first.']);
        }

        try {
            $wa = new WhatsApp();
            $result = $wa->sendText($userId, $waAccount['phone_number_id'], $waAccount['access_token'], $to, trim($content));
            jsonResponse($result);
        } catch (\Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // --- Clear Chat ---
    if ($_POST['action'] === 'clear_chat') {
        $chatPhone = sanitize($_POST['phone'] ?? '');
        if ($chatPhone) {
            $db->query("DELETE FROM messages WHERE user_id = ? AND to_number = ?", [$userId, $chatPhone]);
            jsonResponse(['success' => true]);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to clear chat.']);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}

// Fetch recent active conversations (grouped by phone number)
$conversations = $db->fetchAll("
    SELECT m.*, c.name as contact_name 
    FROM messages m 
    LEFT JOIN contacts c ON m.contact_id = c.id 
    WHERE m.user_id = ? 
    AND m.id IN (
        SELECT MAX(id) FROM messages WHERE user_id = ? GROUP BY to_number
    )
    ORDER BY m.created_at DESC
", [$userId, $userId]);

$pageTitle = 'Live Chat';
$extraCss = [asset('assets/css/dashboard.css')];
include __DIR__ . '/../includes/header.php';
?>

<style>
    .chat-container {
        display: flex;
        height: calc(100vh - 160px);
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #eee;
    }
    .chat-sidebar {
        width: 320px;
        min-width: 320px;
        border-right: 1px solid #eee;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .chat-list {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .chat-item {
        padding: 15px;
        border-bottom: 1px solid #f8f9fa;
        cursor: pointer;
        transition: all 0.2s;
    }
    .chat-item:hover { background: #f1f5f9; }
    .chat-item.active { background: #e2e8f0; border-left: 4px solid var(--primary); }
    
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #f8fafc;
        min-width: 0;
    }
    .chat-header {
        padding: 15px 20px;
        background: #fff;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .chat-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }
    .chat-back-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 50%;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: var(--primary, #6C63FF);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1.1rem;
    }
    .chat-back-btn:hover {
        background: var(--primary, #6C63FF);
        color: #fff;
        border-color: var(--primary, #6C63FF);
    }
    .chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .msg-bubble {
        max-width: 70%;
        padding: 10px 15px;
        border-radius: 12px;
        font-size: 0.9rem;
        position: relative;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .msg-media img,
    .msg-media video {
        max-width: 280px;
        min-width: 120px;
    }
    .msg-media audio {
        min-width: 200px;
        max-width: 280px;
    }
    .msg-in .msg-media img,
    .msg-in .msg-media video {
        border: 1px solid #eee;
    }
    .msg-in {
        align-self: flex-start;
        background: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .msg-out {
        align-self: flex-end;
        background: var(--primary);
        color: #fff;
    }
    .chat-footer {
        padding: 20px;
        background: #fff;
        border-top: 1px solid #eee;
    }
    .chat-input-row {
        display: flex;
        gap: 10px;
    }
    #activeContactName {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ===== Mobile Responsive (≤768px) ===== */
    @media (max-width: 768px) {
        .chat-container {
            height: calc(100vh - 120px);
            border-radius: 8px;
            position: relative;
        }
        .chat-sidebar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            min-width: 100%;
            height: 100%;
            z-index: 10;
            background: #fff;
            border-right: none;
            transform: translateX(0);
        }
        /* When a chat is open, hide sidebar */
        .chat-container.chat-open .chat-sidebar {
            transform: translateX(-100%);
            pointer-events: none;
            opacity: 0;
        }
        .chat-main {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            transform: translateX(100%);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
        }
        /* When a chat is open, show main area */
        .chat-container.chat-open .chat-main {
            transform: translateX(0);
            opacity: 1;
            z-index: 15;
        }
        .chat-back-btn {
            display: flex;
        }
        .chat-header {
            padding: 12px 15px;
        }
        .chat-messages {
            padding: 15px;
            gap: 10px;
        }
        .chat-footer {
            padding: 12px 15px;
        }
        .msg-bubble {
            max-width: 85%;
            font-size: 0.85rem;
        }
        .chat-item {
            padding: 12px 15px;
        }
        #clearChatBtn {
            font-size: 0.75rem;
            padding: 4px 8px !important;
            white-space: nowrap;
        }
    }

    /* ===== Small Mobile (≤480px) ===== */
    @media (max-width: 480px) {
        .chat-container {
            height: calc(100vh - 100px);
            border-radius: 6px;
        }
        .msg-bubble {
            max-width: 90%;
            padding: 8px 12px;
            font-size: 0.82rem;
        }
        .chat-header {
            padding: 10px 12px;
        }
        .chat-messages {
            padding: 10px;
            gap: 8px;
        }
        .chat-footer {
            padding: 10px 12px;
        }
        .chat-input-row .btn {
            padding: 6px 12px;
        }
        #activeContactName {
            font-size: 0.9rem;
        }
    }
</style>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Live Chat</h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>Live Chat</span></div>
            </div>
            <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
        </div>

        <?php if (!$waAccount): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Please connect your WhatsApp account first.</div>
        <?php else: ?>

        <div class="chat-container">
            <!-- Sidebar: Conversations -->
            <div class="chat-sidebar">
                <div class="p-3 border-bottom">
                    <input type="text" id="chatSearch" class="form-control form-control-sm" placeholder="Search contacts...">
                </div>
                <div class="chat-list">
                    <?php if (empty($conversations)): ?>
                        <div class="p-4 text-center text-muted">No conversations found</div>
                    <?php else: ?>
                        <?php foreach ($conversations as $c): ?>
                            <div class="chat-item" onclick="loadMessages('<?= $c['to_number']; ?>', this)">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold"><?= e($c['contact_name'] ?? $c['to_number']); ?></span>
                                    <small class="text-muted"><?= date('H:i', strtotime($c['created_at'])); ?></small>
                                </div>
                                <div class="text-muted text-truncate mini-msg" style="font-size: 0.75rem;">
                                    <?= $c['direction'] === 'outbound' ? '✓ ' : ''; ?><?php
                                        $msgType = $c['type'] ?? 'text';
                                        $preview = substr($c['content'] ?? '', 0, 30);
                                        if ($msgType === 'image') echo '📷 ' . ($preview !== '[Image]' ? e($preview) : 'Photo');
                                        elseif ($msgType === 'video') echo '🎥 ' . ($preview !== '[Video]' ? e($preview) : 'Video');
                                        elseif ($msgType === 'audio' || $msgType === 'voice') echo '🎵 Audio';
                                        elseif ($msgType === 'document') echo '📄 ' . ($preview !== '[Document]' ? e($preview) : 'Document');
                                        elseif ($msgType === 'sticker') echo '🏷️ Sticker';
                                        elseif ($msgType === 'location') echo '📍 Location';
                                        else echo e($preview);
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <div class="chat-header">
                    <div class="chat-header-left">
                        <button class="chat-back-btn" onclick="goBackToList()" title="Back to contacts"><i class="bi bi-arrow-left"></i></button>
                        <div style="min-width:0;">
                            <div id="activeContactName" class="fw-bold">Select a conversation</div>
                            <div class="text-success small" id="activeStatus"></div>
                        </div>
                    </div>
                    <button class="btn btn-danger btn-sm" id="clearChatBtn" style="display: none; align-items: center; gap: 5px;" onclick="clearCurrentChat()"><i class="bi bi-trash"></i> Clear Chat</button>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                        <div class="text-center">
                            <i class="bi bi-chat-left-text" style="font-size: 3rem; opacity: 0.2;"></i>
                            <p>Click on a contact to start chatting</p>
                        </div>
                    </div>
                </div>

                <div class="chat-footer" id="chatFooter" style="display: none;">
                    <form id="chatForm">
                        <div class="chat-input-row">
                            <input type="text" id="chatInput" class="form-control" placeholder="Type a message..." required>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
    let currentChat = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function isMobileView() {
        return window.innerWidth <= 768;
    }

    function goBackToList() {
        const container = document.querySelector('.chat-container');
        if (container) {
            container.classList.remove('chat-open');
        }
    }

    function loadMessages(phone, el) {
        currentChat = phone;
        document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('activeContactName').innerText = phone;
        document.getElementById('chatFooter').style.display = 'block';
        document.getElementById('clearChatBtn').style.display = 'flex';

        // On mobile, slide to chat view
        if (isMobileView()) {
            document.querySelector('.chat-container').classList.add('chat-open');
            history.pushState({ chatOpen: true }, '');
        }
        
        // In a real app, this would be an AJAX call
        document.getElementById('chatMessages').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
        
        fetch('<?= baseUrl('api/chat-history.php'); ?>?phone=' + phone)
            .then(r => r.json())
            .then(data => {
                let html = '';
                data.forEach(m => {
                    const bubbleClass = m.direction === 'inbound' ? 'msg-in' : 'msg-out';
                    let contentHtml = '';
                    const proxyBase = '<?= baseUrl('api/media-proxy.php'); ?>?url=';

                    if (m.media_url && ['image', 'sticker'].includes(m.type)) {
                        const proxiedUrl = proxyBase + encodeURIComponent(m.media_url);
                        contentHtml = `<div class="msg-media">
                            <img src="${proxiedUrl}" alt="Image" style="max-width: 100%; max-height: 300px; border-radius: 8px; cursor: pointer; display: block;" onclick="window.open(this.src, '_blank')" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span style="display:none; opacity:0.7; font-size:0.8rem;">⚠️ Image could not be loaded</span>
                        </div>`;
                        if (m.content && m.content !== '[Image]' && m.content !== '[Sticker]') {
                            contentHtml += `<div style="margin-top: 6px;">${escapeHtml(m.content)}</div>`;
                        }
                    } else if (m.media_url && m.type === 'video') {
                        const proxiedUrl = proxyBase + encodeURIComponent(m.media_url);
                        contentHtml = `<div class="msg-media">
                            <video controls style="max-width: 100%; max-height: 300px; border-radius: 8px;" preload="metadata">
                                <source src="${proxiedUrl}">
                                Your browser does not support video playback.
                            </video>
                        </div>`;
                        if (m.content && m.content !== '[Video]') {
                            contentHtml += `<div style="margin-top: 6px;">${escapeHtml(m.content)}</div>`;
                        }
                    } else if (m.media_url && (m.type === 'audio' || m.type === 'voice')) {
                        const proxiedUrl = proxyBase + encodeURIComponent(m.media_url);
                        contentHtml = `<div class="msg-media">
                            <audio controls style="max-width: 100%;" preload="metadata">
                                <source src="${proxiedUrl}">
                                Your browser does not support audio playback.
                            </audio>
                        </div>`;
                    } else if (m.media_url && m.type === 'document') {
                        const proxiedUrl = proxyBase + encodeURIComponent(m.media_url);
                        const fileName = m.content && m.content !== '[Document]' ? m.content : 'Document';
                        contentHtml = `<div class="msg-media">
                            <a href="${proxiedUrl}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; color:${m.direction === 'inbound' ? '#6C63FF' : '#fff'}; text-decoration:none; word-break:break-all;">
                                <i class="bi bi-file-earmark-arrow-down" style="font-size:1.3rem;"></i>
                                <span>${escapeHtml(fileName)}</span>
                            </a>
                        </div>`;
                    } else {
                        // Text or fallback for media without URL
                        contentHtml = escapeHtml(m.content || '');
                    }

                    html += `<div class="msg-bubble ${bubbleClass}">
                        ${contentHtml}
                        <div style="font-size: 0.65rem; opacity: 0.7; margin-top: 4px; text-align: right;">${m.time}</div>
                    </div>`;
                });
                document.getElementById('chatMessages').innerHTML = html;
                scrollToBottom();
            });
    }

    function scrollToBottom() {
        const container = document.getElementById('chatMessages');
        container.scrollTop = container.scrollHeight;
    }

    document.getElementById('chatForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg || !currentChat) return;

        const sendBtn = this.querySelector('button[type="submit"]');
        const originalBtnHtml = sendBtn.innerHTML;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        sendBtn.disabled = true;
        input.disabled = true;

        // Send message via this page's own handler
        fetch('<?= baseUrl('dashboard/live-chat.php'); ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send_message&to=${encodeURIComponent(currentChat)}&content=${encodeURIComponent(msg)}&_csrf_token=<?= CSRF::generateToken(); ?>`
        })
        .then(r => {
            if (!r.ok) throw new Error('Server error (' + r.status + ')');
            return r.json();
        })
        .then(data => {
            if (data.success) {
                input.value = '';
                // Append the sent message bubble immediately for instant feedback
                const chatMessages = document.getElementById('chatMessages');
                const now = new Date();
                const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                const bubble = document.createElement('div');
                bubble.className = 'msg-bubble msg-out';
                bubble.innerHTML = `${msg.replace(/</g,'&lt;').replace(/>/g,'&gt;')}<div style="font-size: 0.65rem; opacity: 0.7; margin-top: 4px; text-align: right;">${timeStr}</div>`;
                chatMessages.appendChild(bubble);
                scrollToBottom();
            } else {
                alert('Failed to send: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Send error:', err);
            alert('Failed to send message. Please try again.');
        })
        .finally(() => {
            sendBtn.innerHTML = originalBtnHtml;
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
        });
    });

    function clearCurrentChat() {
        if (!currentChat) return;
        if (confirm('WARNING: This will safely and securely clear ALL messages in this conversation! Are you absolutely sure?')) {
            fetch('<?= baseUrl('dashboard/live-chat.php'); ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=clear_chat&phone=${encodeURIComponent(currentChat)}&_csrf_token=<?= CSRF::generateToken(); ?>`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error clearing chat.');
                }
            });
        }
    }
    // Search contacts filter
    document.getElementById('chatSearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    });

    // Handle browser/Android back button on mobile
    window.addEventListener('popstate', function(e) {
        if (isMobileView() && document.querySelector('.chat-container.chat-open')) {
            goBackToList();
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

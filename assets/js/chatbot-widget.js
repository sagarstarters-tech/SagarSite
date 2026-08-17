/**
 * Sagar Starters - AI ChatBot Widget Controller
 * Pure Vanilla JS, Zero dependencies, Asynchronous & Non-blocking
 */

(function () {
    'use strict';

    let config = {
        apiEndpoint: (window.sagarChatConfig && window.sagarChatConfig.apiEndpoint) || '/api/chatbot/chat.php',
        botName: 'Sagar Sahayak',
        botTitle: 'Sagar AI Assistant',
        welcomeMsg: 'Namaste! 🙏 Main Sagar Starters ka AI Assistant hu. Main aapko Motor Starters, Submersible Panels, Price aur Order Tracking me help kar sakta hu.',
        quickReplies: ['5HP Submersible Starter', 'Single Phase vs 3 Phase', 'Track My Order', 'Bulk Purchase Discount'],
        waPhone: '919837248000',
        position: 'bottom-right',
        soundEnabled: localStorage.getItem('sagar_chat_sound') !== '0',
        responseDelay: 800
    };

    let chatHistory = [];
    let isOpen = false;
    let isWaitingResponse = false;
    let sessionId = sessionStorage.getItem('sagar_chat_session') || ('ses_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36));
    sessionStorage.setItem('sagar_chat_session', sessionId);

    // Audio Synthesizer for message ding
    function playNotificationSound() {
        if (!config.soundEnabled) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // A5

            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.18);
        } catch (e) {}
    }

    /**
     * Escape HTML & Markdown parser
     */
    function formatMessageText(text) {
        if (!text) return '';
        let escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Bold **text**
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic *text*
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Inline code `code`
        escaped = escaped.replace(/`(.*?)`/g, '<code style="background:rgba(0,122,255,0.1);padding:2px 4px;border-radius:4px;color:#007aff;">$1</code>');
        // Bullet points •
        escaped = escaped.replace(/•\s+(.*?)(?=\n|$)/g, '<div style="margin-left:8px;margin-bottom:3px;">• $1</div>');
        // Linebreaks
        escaped = escaped.replace(/\n/g, '<br>');

        return escaped;
    }

    /**
     * Create & Inject HTML Elements
     */
    function createChatWidget() {
        // 1. Launcher Container
        const launcher = document.createElement('div');
        launcher.className = `sagar-chat-launcher ${config.position === 'bottom-left' ? 'pos-bottom-left' : ''}`;
        launcher.id = 'sagarChatLauncher';
        launcher.innerHTML = `
            <div class="sagar-launcher-teaser" id="sagarChatTeaser" style="display:none;">
                <i class="fas fa-robot"></i> <span>Need Help? Ask AI</span>
            </div>
            <button type="button" class="sagar-launcher-btn" id="sagarLauncherBtn" aria-label="Open AI Assistant Chat" title="Sagar AI ChatBot">
                <i class="fas fa-robot" id="sagarLauncherIcon"></i>
                <span class="sagar-launcher-badge"></span>
            </button>
        `;

        // 2. Chat Window Container
        const chatWindow = document.createElement('div');
        chatWindow.className = `sagar-chat-window ${config.position === 'bottom-left' ? 'pos-bottom-left' : ''}`;
        chatWindow.id = 'sagarChatWindow';
        chatWindow.innerHTML = `
            <!-- Header -->
            <div class="sagar-chat-header">
                <div class="d-flex align-items-center">
                    <div class="sagar-header-avatar">
                        <i class="fas fa-robot"></i>
                        <span class="status-dot"></span>
                    </div>
                    <div class="sagar-header-info notranslate" translate="no">
                        <h6 class="sagar-header-title notranslate" translate="no" id="sagarHeaderName">${config.botName}</h6>
                        <span class="sagar-header-subtitle notranslate" translate="no" id="sagarHeaderTitle">${config.botTitle}</span>
                    </div>
                </div>
                <div class="sagar-header-actions d-flex align-items-center gap-1">
                    <button type="button" id="sagarSoundBtn" title="Toggle Sound">
                        <i class="fas ${config.soundEnabled ? 'fa-volume-up' : 'fa-volume-mute'}"></i>
                    </button>
                    <button type="button" id="sagarClearBtn" title="Clear Chat">
                        <i class="fas fa-redo-alt"></i>
                    </button>
                    <button type="button" id="sagarCloseBtn" title="Close Chat">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Messages Body -->
            <div class="sagar-chat-body" id="sagarChatMessages"></div>

            <!-- Quick Suggestions -->
            <div class="sagar-chat-suggestions" id="sagarChatSuggestions"></div>

            <!-- Input Footer -->
            <form class="sagar-chat-footer" id="sagarChatForm">
                <input type="text" class="sagar-chat-input" id="sagarChatInput" placeholder="Type your question in Hindi or English..." autocomplete="off">
                <button type="submit" class="sagar-send-btn" id="sagarSendBtn" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        `;

        document.body.appendChild(launcher);
        document.body.appendChild(chatWindow);

        // Bind events
        bindEvents();

        // Restore history or show welcome message
        restoreOrInitMessages();

        // Show teaser tooltip after 5 seconds if not yet opened
        setTimeout(() => {
            if (!isOpen && !sessionStorage.getItem('sagar_teaser_dismissed')) {
                const teaser = document.getElementById('sagarChatTeaser');
                if (teaser) teaser.style.display = 'flex';
            }
        }, 5000);
    }

    /**
     * Bind DOM Event Handlers
     */
    function bindEvents() {
        const launcherBtn = document.getElementById('sagarLauncherBtn');
        const teaser = document.getElementById('sagarChatTeaser');
        const closeBtn = document.getElementById('sagarCloseBtn');
        const clearBtn = document.getElementById('sagarClearBtn');
        const soundBtn = document.getElementById('sagarSoundBtn');
        const form = document.getElementById('sagarChatForm');
        const input = document.getElementById('sagarChatInput');

        launcherBtn.addEventListener('click', toggleChat);
        teaser.addEventListener('click', toggleChat);
        closeBtn.addEventListener('click', toggleChat);

        clearBtn.addEventListener('click', () => {
            sessionStorage.removeItem('sagar_chat_history');
            chatHistory = [];
            const msgs = document.getElementById('sagarChatMessages');
            if (msgs) msgs.innerHTML = '';
            appendBotMessage(config.welcomeMsg, [], config.quickReplies);
        });

        soundBtn.addEventListener('click', () => {
            config.soundEnabled = !config.soundEnabled;
            localStorage.setItem('sagar_chat_sound', config.soundEnabled ? '1' : '0');
            soundBtn.innerHTML = `<i class="fas ${config.soundEnabled ? 'fa-volume-up' : 'fa-volume-mute'}"></i>`;
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = input.value.trim();
            if (text && !isWaitingResponse) {
                sendMessage(text);
                input.value = '';
            }
        });
    }

    /**
     * Toggle Chat Open/Close
     */
    function toggleChat() {
        const win = document.getElementById('sagarChatWindow');
        const launcher = document.getElementById('sagarChatLauncher');
        const icon = document.getElementById('sagarLauncherIcon');
        const teaser = document.getElementById('sagarChatTeaser');

        isOpen = !isOpen;
        if (isOpen) {
            win.classList.add('active');
            if (launcher) launcher.classList.add('chat-open');
            if (icon) icon.className = 'fas fa-chevron-down';
            if (teaser) teaser.style.display = 'none';
            sessionStorage.setItem('sagar_teaser_dismissed', '1');
            setTimeout(() => {
                const input = document.getElementById('sagarChatInput');
                if (input) input.focus();
            }, 300);
            scrollToBottom();
        } else {
            win.classList.remove('active');
            if (launcher) launcher.classList.remove('chat-open');
            if (icon) icon.className = 'fas fa-robot';
        }
    }

    /**
     * Restore Messages from SessionStorage or Add Welcome Greeting
     */
    function restoreOrInitMessages() {
        const saved = sessionStorage.getItem('sagar_chat_history');
        if (saved) {
            try {
                chatHistory = JSON.parse(saved);
                const msgs = document.getElementById('sagarChatMessages');
                if (msgs) msgs.innerHTML = '';

                chatHistory.forEach(item => {
                    if (item.sender === 'user') {
                        renderUserBubble(item.text);
                    } else {
                        renderBotBubble(item.text, item.products || []);
                    }
                });

                renderSuggestions(config.quickReplies);
                scrollToBottom();
                return;
            } catch (e) {}
        }

        // Add Welcome Message
        appendBotMessage(config.welcomeMsg, [], config.quickReplies);
    }

    /**
     * Send user message to server API
     */
    function sendMessage(text) {
        renderUserBubble(text);
        chatHistory.push({ sender: 'user', text: text });
        saveHistory();

        isWaitingResponse = true;
        showTypingIndicator();
        scrollToBottom();

        const sendTime = Date.now();

        // Send API Request
        fetch(config.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'message',
                message: text,
                history: chatHistory.slice(-6),
                session_id: sessionId
            })
        })
        .then(r => r.json())
        .then(data => {
            const elapsed = Date.now() - sendTime;
            const targetDelay = (data && typeof data.response_delay_ms !== 'undefined') ? Number(data.response_delay_ms) : (config.responseDelay || 800);
            const remainingDelay = Math.max(0, targetDelay - elapsed);

            setTimeout(() => {
                hideTypingIndicator();
                isWaitingResponse = false;

                const reply = data.reply || "Kshama karein, response me error aaya. Kripya dobara try karein.";
                const products = data.products || [];
                const suggestions = data.quick_replies || config.quickReplies;

                appendBotMessage(reply, products, suggestions);
                playNotificationSound();
            }, remainingDelay);
        })
        .catch(err => {
            hideTypingIndicator();
            isWaitingResponse = false;
            appendBotMessage("Namaste! Network issue ke karan connect nahi ho pa raha. Aap direct hamare WhatsApp number par contact kar sakte hain.", [], ['WhatsApp Support', 'Retry']);
        });
    }

    /**
     * Append & Render Bot Message
     */
    function appendBotMessage(text, products = [], suggestions = []) {
        renderBotBubble(text, products);
        chatHistory.push({ sender: 'bot', text: text, products: products });
        saveHistory();

        if (suggestions && suggestions.length > 0) {
            renderSuggestions(suggestions);
        }

        scrollToBottom();
    }

    /**
     * Render User Bubble in DOM
     */
    function renderUserBubble(text) {
        const msgs = document.getElementById('sagarChatMessages');
        if (!msgs) return;

        const row = document.createElement('div');
        row.className = 'sagar-msg-row user';
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        row.innerHTML = `
            <div class="sagar-msg-bubble">
                ${formatMessageText(text)}
                <div class="sagar-msg-time">${time}</div>
            </div>
        `;
        msgs.appendChild(row);
    }

    /**
     * Render Bot Bubble in DOM
     */
    function renderBotBubble(text, products = []) {
        const msgs = document.getElementById('sagarChatMessages');
        if (!msgs) return;

        const row = document.createElement('div');
        row.className = 'sagar-msg-row bot';
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        let productsHtml = '';
        if (products && products.length > 0) {
            productsHtml = `<div class="sagar-chat-products">`;
            products.forEach(p => {
                productsHtml += `
                    <div class="sagar-product-chip">
                        <img src="${p.image}" alt="${p.name}" onerror="this.src='/assets/images/placeholder.svg';">
                        <div class="sagar-product-chip-info">
                            <h6 class="sagar-product-chip-title" title="${p.name}">${p.name}</h6>
                            <div class="sagar-product-chip-price">${p.display_price}</div>
                        </div>
                        <div class="sagar-product-chip-actions">
                            <a href="${p.url}" target="_blank" class="sagar-chip-view"><i class="fas fa-eye"></i> View</a>
                            <a href="${p.wa_link}" target="_blank" rel="noopener noreferrer" class="sagar-chip-wa" title="WhatsApp Order"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                `;
            });
            productsHtml += `</div>`;
        }

        row.innerHTML = `
            <div class="sagar-msg-avatar">
                <i class="fas fa-robot text-primary"></i>
            </div>
            <div class="sagar-msg-bubble">
                ${formatMessageText(text)}
                ${productsHtml}
                <div class="sagar-msg-time">${time}</div>
            </div>
        `;
        msgs.appendChild(row);
    }

    /**
     * Render Quick Suggestion Chips
     */
    function renderSuggestions(chips) {
        const container = document.getElementById('sagarChatSuggestions');
        if (!container) return;
        container.innerHTML = '';

        chips.forEach(chip => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sagar-suggestion-btn';
            btn.textContent = chip;
            btn.addEventListener('click', () => {
                if (chip.toLowerCase().includes('whatsapp')) {
                    window.open(`https://wa.me/${config.waPhone}?text=` + encodeURIComponent("Hello Sagar Starters, I need technical support & sales assistance."), '_blank');
                } else {
                    sendMessage(chip);
                }
            });
            container.appendChild(btn);
        });
    }

    /**
     * Show Typing Indicator
     */
    function showTypingIndicator() {
        const msgs = document.getElementById('sagarChatMessages');
        if (!msgs) return;

        const row = document.createElement('div');
        row.className = 'sagar-msg-row bot';
        row.id = 'sagarTypingRow';
        row.innerHTML = `
            <div class="sagar-msg-avatar"><i class="fas fa-robot text-primary"></i></div>
            <div class="sagar-msg-bubble">
                <div class="sagar-typing-indicator">
                    <span class="sagar-typing-dot"></span>
                    <span class="sagar-typing-dot"></span>
                    <span class="sagar-typing-dot"></span>
                </div>
            </div>
        `;
        msgs.appendChild(row);
    }

    function hideTypingIndicator() {
        const row = document.getElementById('sagarTypingRow');
        if (row) row.remove();
    }

    function scrollToBottom() {
        const msgs = document.getElementById('sagarChatMessages');
        if (msgs) {
            setTimeout(() => {
                msgs.scrollTop = msgs.scrollHeight;
            }, 50);
        }
    }

    function saveHistory() {
        sessionStorage.setItem('sagar_chat_history', JSON.stringify(chatHistory.slice(-15)));
    }

    // Initialize on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createChatWidget);
    } else {
        createChatWidget();
    }
})();

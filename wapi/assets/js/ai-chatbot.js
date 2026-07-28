/**
 * AI ChatBot Builder - Main JavaScript
 * Handles bot CRUD, knowledge base, editor tabs, test bot
 */
(function() {
    'use strict';

    const BASE_URL = document.querySelector('meta[name="csrf-token"]')?.closest('head')?.querySelector('base')?.href || '';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ============================================
    // Utility Functions
    // ============================================
    function apiRequest(url, options = {}) {
        const defaults = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (!(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
        }

        // Add CSRF token
        if (options.method === 'POST' || options.method === 'PUT') {
            if (options.body instanceof FormData) {
                options.body.append('_csrf_token', CSRF_TOKEN);
            } else if (typeof options.body === 'string') {
                try {
                    const data = JSON.parse(options.body);
                    data._csrf_token = CSRF_TOKEN;
                    options.body = JSON.stringify(data);
                } catch(e) {}
            }
        }

        return fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...options.headers } })
            .then(res => res.json())
            .catch(err => {
                console.error('API Error:', err);
                return { success: false, message: 'Network error. Please try again.' };
            });
    }

    function showToast(message, type = 'success') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            alert(message);
        }
    }

    function formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    }

    // ============================================
    // Bot Management (ai-chatbot.php)
    // ============================================
    window.deleteBot = function(botId, botName) {
        Swal.fire({
            title: 'Delete AI Bot?',
            html: `Are you sure you want to delete <strong>${botName}</strong>?<br><small class="text-muted">This will delete all knowledge base data, conversations, and analytics.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                apiRequest(window.APP_BASE + 'api/ai-bot/delete.php', {
                    method: 'POST',
                    body: JSON.stringify({ bot_id: botId })
                }).then(data => {
                    if (data.success) {
                        showToast('Bot deleted successfully');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'Failed to delete bot', 'error');
                    }
                });
            }
        });
    };

    window.toggleBotStatus = function(botId, currentStatus) {
        const targetStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = targetStatus === 'active' ? 'activate' : 'deactivate';
        apiRequest(window.APP_BASE + 'api/ai-bot/toggle-status.php', {
            method: 'POST',
            body: JSON.stringify({ bot_id: botId, status: targetStatus })
        }).then(data => {
            if (data.success) {
                showToast(`Bot ${action}d successfully`);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message || `Failed to ${action} bot`, 'error');
            }
        });
    };

    window.cloneBot = function(botId, botName) {
        Swal.fire({
            title: 'Clone AI Bot?',
            html: `Create a copy of <strong>${botName}</strong>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Clone',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                apiRequest(window.APP_BASE + 'api/ai-bot/clone.php', {
                    method: 'POST',
                    body: JSON.stringify({ bot_id: botId })
                }).then(data => {
                    if (data.success) {
                        showToast('Bot cloned successfully');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'Failed to clone bot', 'error');
                    }
                });
            }
        });
    };

    // ============================================
    // Editor Tabs (ai-chatbot-editor.php)
    // ============================================
    window.switchTab = function(tabId) {
        // Deactivate all tabs
        document.querySelectorAll('.ai-editor-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.ai-tab-content').forEach(c => c.classList.remove('active'));

        // Activate selected
        document.querySelector(`.ai-editor-tab[data-tab="${tabId}"]`)?.classList.add('active');
        document.getElementById('tab-' + tabId)?.classList.add('active');

        // Update URL hash
        history.replaceState(null, '', '#' + tabId);
    };

    // ============================================
    // Knowledge Base Management
    // ============================================
    window.initKBUpload = function(botId) {
        const zone = document.getElementById('kbUploadZone');
        const fileInput = document.getElementById('kbFileInput');

        if (!zone || !fileInput) return;

        // Click to upload
        zone.addEventListener('click', () => fileInput.click());

        // Drag events
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) uploadDocument(botId, files[0]);
        });

        // File input change
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                uploadDocument(botId, fileInput.files[0]);
                fileInput.value = '';
            }
        });
    };

    function uploadDocument(botId, file) {
        const allowed = ['pdf', 'docx', 'txt', 'csv'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
            showToast('Invalid file type. Allowed: PDF, DOCX, TXT, CSV', 'error');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showToast('File too large. Maximum 10MB allowed.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('document', file);
        formData.append('bot_id', botId);

        // Show upload progress
        const zone = document.getElementById('kbUploadZone');
        const originalHTML = zone.innerHTML;
        zone.innerHTML = '<div class="ai-spinner"></div><div class="upload-text mt-2">Uploading & processing...</div>';

        apiRequest(window.APP_BASE + 'api/ai-bot/upload-document.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(data => {
            zone.innerHTML = originalHTML;
            if (data.success) {
                showToast('Document uploaded and processed');
                loadKBItems(botId);
            } else {
                showToast(data.message || 'Upload failed', 'error');
            }
        });
    }

    window.addKBUrl = function(botId) {
        const input = document.getElementById('kbUrlInput');
        const url = input.value.trim();
        if (!url) {
            showToast('Please enter a URL', 'error');
            return;
        }

        const btn = document.getElementById('kbUrlBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="ai-spinner"></span>';

        apiRequest(window.APP_BASE + 'api/ai-bot/add-url.php', {
            method: 'POST',
            body: JSON.stringify({ bot_id: botId, url: url })
        }).then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-globe2"></i> Crawl';
            if (data.success) {
                input.value = '';
                showToast('URL crawled and added to knowledge base');
                loadKBItems(botId);
            } else {
                showToast(data.message || 'Failed to crawl URL', 'error');
            }
        });
    };

    window.addQAPair = function(botId) {
        const container = document.getElementById('qaContainer');
        const pairs = container.querySelectorAll('.qa-pair');
        const index = pairs.length;

        const html = `
            <div class="qa-pair" data-index="${index}">
                <button type="button" class="qa-remove" onclick="this.closest('.qa-pair').remove()"><i class="bi bi-x"></i></button>
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm qa-question" placeholder="Question..." required>
                </div>
                <div>
                    <textarea class="form-control form-control-sm qa-answer" rows="2" placeholder="Answer..." required></textarea>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    };

    window.saveQAPairs = function(botId) {
        const pairs = document.querySelectorAll('.qa-pair');
        let saved = 0;
        const total = pairs.length;

        if (total === 0) {
            showToast('No Q&A pairs to save', 'warning');
            return;
        }

        pairs.forEach(pair => {
            const question = pair.querySelector('.qa-question').value.trim();
            const answer = pair.querySelector('.qa-answer').value.trim();
            if (!question || !answer) return;

            apiRequest(window.APP_BASE + 'api/ai-bot/add-qa.php', {
                method: 'POST',
                body: JSON.stringify({ bot_id: botId, question, answer })
            }).then(data => {
                saved++;
                if (saved >= total) {
                    showToast(`${saved} Q&A pair(s) saved`);
                    loadKBItems(botId);
                }
            });
        });
    };

    window.deleteKBItem = function(type, itemId, botId) {
        apiRequest(window.APP_BASE + 'api/ai-bot/delete-kb-item.php', {
            method: 'POST',
            body: JSON.stringify({ type, item_id: itemId })
        }).then(data => {
            if (data.success) {
                showToast('Item removed');
                loadKBItems(botId);
            } else {
                showToast(data.message || 'Failed to remove item', 'error');
            }
        });
    };

    window.loadKBItems = function(botId) {
        const container = document.getElementById('kbItemsList');
        if (!container) return;

        apiRequest(window.APP_BASE + 'api/ai-bot/list-kb.php?bot_id=' + botId).then(data => {
            if (!data.success) return;

            let html = '';
            const items = data.data || {};

            // Documents
            (items.documents || []).forEach(doc => {
                html += buildKBItem('doc', doc.file_name, `${doc.file_type.toUpperCase()} • ${doc.chunks_count} chunks`, doc.status, doc.id, 'document', botId);
            });

            // URLs
            (items.urls || []).forEach(url => {
                html += buildKBItem('url', url.title || url.url, `${url.chunks_count} chunks`, url.status, url.id, 'url', botId);
            });

            // Q&A Pairs
            (items.qa_pairs || []).forEach(qa => {
                html += buildKBItem('qa', qa.question, qa.answer.substring(0, 60) + '...', 'completed', qa.id, 'qa', botId);
            });

            container.innerHTML = html || '<div class="text-center text-muted py-3" style="font-size: 0.8125rem;"><i class="bi bi-inbox d-block mb-1" style="font-size: 1.5rem;"></i>No knowledge base items yet</div>';
        });
    };

    function buildKBItem(iconType, name, meta, status, id, type, botId) {
        const icons = { doc: 'bi-file-earmark-text', url: 'bi-globe2', qa: 'bi-chat-quote', manual: 'bi-pencil-square' };
        return `
            <div class="kb-item">
                <div class="kb-item-icon ${iconType}"><i class="bi ${icons[iconType]}"></i></div>
                <div class="kb-item-info">
                    <div class="kb-item-name">${escapeHtml(name)}</div>
                    <div class="kb-item-meta">${escapeHtml(meta)}</div>
                </div>
                <span class="kb-item-status ${status}">${status}</span>
                <button class="kb-item-delete" onclick="deleteKBItem('${type}', ${id}, ${botId})" title="Delete"><i class="bi bi-trash3"></i></button>
            </div>
        `;
    }

    // ============================================
    // Model Selection
    // ============================================
    window.selectModel = function(model) {
        document.querySelectorAll('.model-card').forEach(c => {
            c.classList.remove('selected');
            c.querySelector('.model-check').innerHTML = '';
        });
        const card = document.querySelector(`.model-card[data-model="${model}"]`);
        if (card && !card.classList.contains('disabled')) {
            card.classList.add('selected');
            card.querySelector('.model-check').innerHTML = '<i class="bi bi-check"></i>';
            document.getElementById('selectedModel').value = model;

            // Show/hide custom fields
            const customFields = document.getElementById('customModelFields');
            if (customFields) {
                customFields.style.display = model === 'custom' ? 'block' : 'none';
            }
        }
    };

    // ============================================
    // Save Bot
    // ============================================
    window.saveBot = function(botId) {
        const btn = document.getElementById('saveBotBtn');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="ai-spinner"></span> Saving...';

        const data = {
            bot_id: botId || 0,
            name: document.getElementById('botName')?.value || '',
            description: document.getElementById('botDescription')?.value || '',
            whatsapp_account_id: document.getElementById('botWaAccount')?.value || '',
            status: document.getElementById('botStatus')?.checked ? 'active' : 'inactive',
            // Personality
            bot_role: document.getElementById('botRole')?.value || '',
            business_type: document.getElementById('businessType')?.value || '',
            response_tone: document.getElementById('responseTone')?.value || 'professional',
            response_length: document.getElementById('responseLength')?.value || 'moderate',
            language: document.getElementById('botLanguage')?.value || 'English',
            system_prompt: document.getElementById('systemPrompt')?.value || '',
            // Model
            ai_model: document.getElementById('selectedModel')?.value || 'gpt-4o',
            custom_api_endpoint: document.getElementById('customEndpoint')?.value || '',
            custom_api_key: document.getElementById('customApiKey')?.value || '',
            // Handover
            handover_enabled: document.getElementById('handoverEnabled')?.checked ? 1 : 0,
            handover_keywords: document.getElementById('handoverKeywords')?.value || '',
            handover_confidence_threshold: document.getElementById('handoverThreshold')?.value || 30,
            // CRM
            crm_capture_enabled: document.getElementById('crmEnabled')?.checked ? 1 : 0
        };

        apiRequest(window.APP_BASE + 'api/ai-bot/save.php', {
            method: 'POST',
            body: JSON.stringify(data)
        }).then(res => {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (res.success) {
                showToast('Bot saved successfully!');
                if (!botId && res.data?.id) {
                    // Redirect to edit mode for new bots
                    window.location.href = window.APP_BASE + 'dashboard/ai-chatbot-editor.php?id=' + res.data.id;
                }
            } else {
                showToast(res.message || 'Failed to save bot', 'error');
            }
        });
    };

    // ============================================
    // Test Bot Chat
    // ============================================
    window.initTestChat = function(botId) {
        const input = document.getElementById('testChatInput');
        const sendBtn = document.getElementById('testChatSend');

        if (!input || !sendBtn) return;

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendTestMessage(botId);
            }
        });

        sendBtn.addEventListener('click', () => sendTestMessage(botId));
    };

    function sendTestMessage(botId) {
        const input = document.getElementById('testChatInput');
        const messages = document.getElementById('testChatMessages');
        const sendBtn = document.getElementById('testChatSend');
        const message = input.value.trim();

        if (!message) return;

        // Add customer message
        messages.innerHTML += `
            <div class="test-chat-bubble customer">
                ${escapeHtml(message)}
                <span class="bubble-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
            </div>
        `;
        input.value = '';
        sendBtn.disabled = true;

        // Add typing indicator
        const typingId = 'typing-' + Date.now();
        messages.innerHTML += `<div class="typing-indicator" id="${typingId}"><span></span><span></span><span></span></div>`;
        messages.scrollTop = messages.scrollHeight;

        // Send to API
        apiRequest(window.APP_BASE + 'api/ai-bot/test-bot.php', {
            method: 'POST',
            body: JSON.stringify({ bot_id: botId, message: message })
        }).then(data => {
            // Remove typing indicator
            document.getElementById(typingId)?.remove();
            sendBtn.disabled = false;

            if (data.success) {
                messages.innerHTML += `
                    <div class="test-chat-bubble ai">
                        ${escapeHtml(data.data?.response || 'No response')}
                        <span class="bubble-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
            } else {
                messages.innerHTML += `
                    <div class="test-chat-bubble ai" style="border-color: #fecaca; background: #fef2f2;">
                        <i class="bi bi-exclamation-triangle text-danger"></i> ${escapeHtml(data.message || 'Error getting response')}
                        <span class="bubble-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
            }
            messages.scrollTop = messages.scrollHeight;
            input.focus();
        });
    }

    // ============================================
    // Conversation Details Toggle
    // ============================================
    window.toggleConversation = function(convId) {
        const detail = document.getElementById('conv-detail-' + convId);
        if (!detail) return;

        if (detail.style.display === 'none' || !detail.style.display) {
            // Load messages if not loaded
            if (!detail.dataset.loaded) {
                detail.innerHTML = '<div class="text-center py-3"><span class="ai-spinner"></span></div>';
                detail.style.display = 'block';

                apiRequest(window.APP_BASE + 'api/ai-bot/conversations.php?conversation_id=' + convId).then(data => {
                    if (data.success && data.data?.messages) {
                        let html = '<div class="conversation-thread">';
                        data.data.messages.forEach(msg => {
                            const isInbound = msg.direction === 'inbound';
                            const avatarClass = msg.sender_type === 'ai' ? 'ai-avatar' : (msg.sender_type === 'human' ? 'human-avatar' : '');
                            const avatarIcon = msg.sender_type === 'ai' ? 'bi-robot' : (msg.sender_type === 'human' ? 'bi-person' : 'bi-person');
                            html += `
                                <div class="thread-message ${isInbound ? 'inbound' : 'outbound'}">
                                    <div class="msg-avatar ${avatarClass}"><i class="bi ${avatarIcon}"></i></div>
                                    <div>
                                        <div class="msg-content">${escapeHtml(msg.content)}</div>
                                        <div class="msg-time">${msg.created_at}</div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        detail.innerHTML = html;
                        detail.dataset.loaded = 'true';
                    }
                });
            } else {
                detail.style.display = 'block';
            }
        } else {
            detail.style.display = 'none';
        }
    };

    // ============================================
    // Handover Threshold Slider
    // ============================================
    window.updateThresholdDisplay = function(value) {
        const display = document.getElementById('thresholdDisplay');
        if (display) display.textContent = value + '%';
    };

    // ============================================
    // Manual Knowledge Save
    // ============================================
    window.saveManualKnowledge = function(botId) {
        const content = document.getElementById('manualKnowledge')?.value?.trim();
        if (!content) {
            showToast('Please enter some content', 'warning');
            return;
        }

        apiRequest(window.APP_BASE + 'api/ai-bot/add-qa.php', {
            method: 'POST',
            body: JSON.stringify({
                bot_id: botId,
                question: 'Business Information',
                answer: content,
                is_manual: true
            })
        }).then(data => {
            if (data.success) {
                showToast('Knowledge saved');
                loadKBItems(botId);
            } else {
                showToast(data.message || 'Failed to save', 'error');
            }
        });
    };

    // ============================================
    // Helpers
    // ============================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // Init on DOM ready
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Restore tab from URL hash
        const hash = window.location.hash.replace('#', '');
        if (hash && document.querySelector(`.ai-editor-tab[data-tab="${hash}"]`)) {
            switchTab(hash);
        }

        // Init KB upload if on editor page
        const botIdInput = document.getElementById('currentBotId');
        if (botIdInput) {
            const botId = parseInt(botIdInput.value);
            if (botId > 0) {
                initKBUpload(botId);
                loadKBItems(botId);
                initTestChat(botId);
            }
        }
    });

})();

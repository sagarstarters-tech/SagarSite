let editor;
let currentNodeId = null;

// Initialize Drawflow
document.addEventListener("DOMContentLoaded", () => {
    const canvas = document.getElementById("drawflow-canvas");
    if (!canvas) {
        console.error("Drawflow canvas not found!");
        return;
    }
    
    editor = new Drawflow(canvas);
    editor.reroute = true;
    editor.start();

    // Event Listeners
    editor.on('nodeCreated', function(nodeId) {
        console.log("Node created " + nodeId);
        const node = editor.getNodeFromId(nodeId);
        // Force correct port counts for text-cta / interactive if they differ (prevents corruption)
        if (node.name === 'text-cta' || node.name === 'interactive') {
            restoreInteractivePorts(nodeId);
        }
    });

    editor.on('nodeSelected', function(nodeId) {
        currentNodeId = nodeId;
    });

    editor.on('nodeUnselected', function() {
        closeConfig();
    });

    editor.on('connectionSelected', function(conn) {
        Swal.fire({
            title: 'Disconnect Line?',
            text: 'Are you sure you want to remove this connection?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                editor.removeSingleConnection(conn.output_id, conn.input_id, conn.output_class, conn.input_class);
            }
        });
    });

    // Fix for form inputs inside nodes: stop propagation so Drawflow
    // doesn't interpret typing/clicking in inputs as node drag actions.
    canvas.addEventListener('mousedown', (e) => {
        // Allow port interaction to pass through
        if (e.target.classList.contains('input') || e.target.classList.contains('output')) {
             return;
        }
        // Only stop propagation for actual form elements to allow typing
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            e.stopPropagation();
        }
    }, true);
    
    // Open config on Double Click
    canvas.addEventListener('dblclick', (e) => {
        const nodeEl = e.target.closest('.drawflow-node');
        if (nodeEl) {
            const nodeId = nodeEl.id.replace('node-', '');
            showNodeConfig(nodeId);
        }
    });

    // ==========================================
    // PREMIUM CANVAS ZOOM & PANNING ENHANCEMENT
    // ==========================================
    
    // 1. Direct Mouse Wheel Zoom (without Ctrl key requirement)
    canvas.addEventListener('wheel', (e) => {
        if (!editor || editor.editor_mode === 'fixed') return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        if (e.deltaY > 0) {
            editor.zoom_out();
        } else {
            editor.zoom_in();
        }
    }, { capture: true, passive: false });

    // 2. Custom Multi-Gesture Canvas Panning (Middle-Click, Right-Click, Space+Left-Click)
    let isPanning = false;
    let panStartX = 0;
    let panStartY = 0;
    let panStartCanvasX = 0;
    let panStartCanvasY = 0;
    let spacePressed = false;

    // Track Spacebar state for panning
    window.addEventListener('keydown', (e) => {
        if (e.code === 'Space') {
            const activeEl = document.activeElement;
            if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT' || activeEl.isContentEditable)) {
                return; // Allow typing spaces in input fields
            }
            spacePressed = true;
            canvas.style.cursor = 'grab';
        }
    });

    window.addEventListener('keyup', (e) => {
        if (e.code === 'Space') {
            spacePressed = false;
            canvas.style.cursor = '';
        }
    });

    // Capture mousedown events to start panning
    canvas.addEventListener('mousedown', (e) => {
        const isMiddleClick = e.button === 1;
        const isRightClick = e.button === 2;
        const isSpaceDrag = e.button === 0 && spacePressed;

        if (isMiddleClick || isRightClick || isSpaceDrag) {
            isPanning = true;
            panStartX = e.clientX;
            panStartY = e.clientY;
            panStartCanvasX = editor.canvas_x;
            panStartCanvasY = editor.canvas_y;
            canvas.style.cursor = 'grabbing';
            
            e.preventDefault();
            e.stopPropagation();
        }
    }, true); // True handles capture phase, intercepting before Drawflow node dragging triggers

    // Apply smooth pan offsets on mousemove
    window.addEventListener('mousemove', (e) => {
        if (!isPanning) return;

        const dx = e.clientX - panStartX;
        const dy = e.clientY - panStartY;

        // Apply pan coordinates to Drawflow internal state
        editor.canvas_x = panStartCanvasX + dx;
        editor.canvas_y = panStartCanvasY + dy;

        // Trigger visual transformation of the canvas precanvas
        editor.precanvas.style.transform = `translate(${editor.canvas_x}px, ${editor.canvas_y}px) scale(${editor.zoom})`;
        
        e.preventDefault();
        e.stopPropagation();
    }, true);

    // End panning on mouseup
    window.addEventListener('mouseup', (e) => {
        if (isPanning) {
            isPanning = false;
            canvas.style.cursor = spacePressed ? 'grab' : '';
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    // Disable default browser context menus on the canvas to allow smooth right-click panning
    canvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();
    }, true);

    // Auto-load master flow on start
    setTimeout(() => loadFlow(true), 100);

    // Live update start node badge when flow name changes
    const flowNameInput = document.getElementById('flowNameInput');
    if (flowNameInput) {
        flowNameInput.addEventListener('input', () => {
            const exportData = editor.export();
            const nodes = exportData.drawflow.Home.data || {};
            Object.keys(nodes).forEach(id => {
                if (nodes[id].name === 'start') {
                    editor.updateNodeDataFromId(id, { ...nodes[id].data, title: flowNameInput.value });
                    updateNodePreview(id);
                }
            });
        });
    }
});

/**
 * Sidebar Config Logic
 */
function setMatchType(val, btn) {
    document.getElementById('conf-match').value = val;
    const btns = btn.parentElement.querySelectorAll('.cfg-segment-btn');
    btns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

/**
 * Insert variable at cursor position in a textarea or input
 */
function insertAtCursor(fieldId, text) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    const startPos = field.selectionStart;
    const endPos = field.selectionEnd;
    const value = field.value;

    field.value = value.substring(0, startPos) + text + value.substring(endPos, value.length);
    field.selectionStart = field.selectionEnd = startPos + text.length;
    field.focus();
}

/**
 * Toggle custom variables dropdown
 */
function toggleCustomVars(btn, fieldId) {
    const existing = btn.parentElement.querySelector('.custom-vars-dropdown');
    if (existing) {
        existing.remove();
        return;
    }

    // Close others
    document.querySelectorAll('.custom-vars-dropdown').forEach(d => d.remove());

    const dropdown = document.createElement('div');
    dropdown.className = 'custom-vars-dropdown border shadow-sm position-absolute bg-white rounded p-1';
    dropdown.style.zIndex = '1000';
    dropdown.style.width = '150px';
    dropdown.style.top = '100%';
    dropdown.style.left = '0';
    
    const vars = [
        { label: 'Full Name', value: '#NAME#' },
        { label: 'First Name', value: '#FIRST_NAME#' },
        { label: 'Phone Number', value: '#PHONE#' }
    ];

    vars.forEach(v => {
        const item = document.createElement('div');
        item.className = 'dropdown-item small py-1 px-2 cursor-pointer';
        item.style.fontSize = '12px';
        item.style.cursor = 'pointer';
        item.innerText = v.label;
        item.onclick = () => {
            insertAtCursor(fieldId, v.value);
            dropdown.remove();
        };
        dropdown.appendChild(item);
    });

    btn.parentElement.classList.add('position-relative');
    btn.parentElement.appendChild(dropdown);

    // Close on click outside
    const closeListener = (e) => {
        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.remove();
            document.removeEventListener('mousedown', closeListener);
        }
    };
    document.addEventListener('mousedown', closeListener);
}

/**
 * Toggle emoji picker popup
 */
function toggleEmojiPicker(btn, fieldId) {
    const existing = btn.parentElement.querySelector('.emoji-picker-dropdown');
    if (existing) {
        existing.remove();
        return;
    }

    // Close others
    document.querySelectorAll('.custom-vars-dropdown, .emoji-picker-dropdown').forEach(d => d.remove());

    const dropdown = document.createElement('div');
    dropdown.className = 'emoji-picker-dropdown border shadow-sm position-absolute bg-white rounded p-2';
    dropdown.style.zIndex = '1000';
    dropdown.style.width = '200px';
    dropdown.style.top = '100%';
    dropdown.style.right = '0';
    dropdown.style.display = 'grid';
    dropdown.style.gridTemplateColumns = 'repeat(6, 1fr)';
    dropdown.style.gap = '5px';
    
    // Popular WhatsApp emojis
    const emojis = [
        '😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰',
        '😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🤩','🥳','😏',
        '😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠',
        '😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥',
        '😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐',
        '🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','👻','💀',
        '☠️','👽','👾','🤖','🎃','😺','😸','😻','😼','😽','🙀','😿','😾','🤲','👐','🙌',
        '👏','🤝','👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤏','👈','👉',
        '👆','👇','☝️','✋','🤚','🖐','🖖','👋','🤙','💪','🦾','👂','🦻','👃','🧠','👣',
        '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖'
    ];

    emojis.forEach(emoji => {
        const span = document.createElement('span');
        span.className = 'emoji-item text-center cursor-pointer';
        span.style.fontSize = '18px';
        span.style.cursor = 'pointer';
        span.innerText = emoji;
        span.onclick = () => {
            insertAtCursor(fieldId, emoji);
            // Don't close immediately so user can pick multiple? 
            // Most WhatsApp apps keep it open. Let's keep it open but update UI if needed.
        };
        dropdown.appendChild(span);
    });

    btn.parentElement.classList.add('position-relative');
    btn.parentElement.appendChild(dropdown);

    // Close on click outside
    const closeListener = (e) => {
        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.remove();
            document.removeEventListener('mousedown', closeListener);
        }
    };
    document.addEventListener('mousedown', closeListener);
}


function showNodeConfig(nodeId) {
    const node = editor.getNodeFromId(nodeId);
    const configSidebar = document.getElementById('configSidebar');
    const configBody = document.getElementById('configBody');
    const configHeader = configSidebar.querySelector('.config-header h6');

    if (node.name === 'start') {
        configHeader.innerText = "Configure Reference";
    } else {
        configHeader.innerText = "Configure " + node.name.charAt(0).toUpperCase() + node.name.slice(1);
    }
    
    configBody.innerHTML = ''; // Clear existing

    // Generate Form based on type
    let html = '';
    const data = node.data;

    switch (node.name) {
        case 'start':
            html = `
                <div class="mb-3">
                    <label class="cfg-label">Write down the keywords for which the bot will be triggered</label>
                    <input type="text" class="form-control cfg-input" id="conf-keywords" value="${data.keywords || ''}" placeholder="Hello, Hi, Start">
                </div>
                
                <div class="mb-3">
                    <label class="cfg-label">Send reply based on your matching type</label>
                    <div class="cfg-segment-control">
                        <button type="button" class="cfg-segment-btn ${data.match === 'exact' || !data.match ? 'active' : ''}" onclick="setMatchType('exact', this)">Exact keyword match</button>
                        <button type="button" class="cfg-segment-btn ${data.match === 'contains' ? 'active' : ''}" onclick="setMatchType('contains', this)">String match</button>
                    </div>
                    <input type="hidden" id="conf-match" value="${data.match || 'exact'}">
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'text':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide your reply message</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-text')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-text', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-text" rows="5" placeholder="#LEAD_USER_FIRST_NAME# How are you?" style="background: #fafafa; border: 1px solid #ddd;">${data.text || ''}</textarea>
                        <i class="bi bi-emoji-smile position-absolute text-muted" style="top: 8px; right: 10px; cursor:pointer;" title="Emoji" onclick="toggleEmojiPicker(this, 'conf-text')"></i>
                    </div>
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'image':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide your reply image URL</label>
                    <input type="text" class="form-control cfg-input" id="conf-url" value="${data['image-url'] || ''}" placeholder="https://..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4">
                    <div class="upload-box-wrapper" style="border: 1px dashed #007bff; border-radius: 4px; padding: 40px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-media').click()">
                        <!-- Currently visually mimics an upload box for design purposes -->
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 2rem; color: #007bff;"></i>
                        <input type="file" id="conf-upload-media" accept="image/png, image/jpeg, image/webp" style="display:none;" onchange="uploadMediaToBot(this, 'conf-url', 'upload-status-media')">
                        <div id="upload-status-media" class="mt-2 text-muted" style="font-size:12px; font-weight: 500;">Click to upload (png, jpg, webp)</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Message (Optional)</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-caption')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-caption', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-caption" rows="4" placeholder="Type your image message here..." style="background: #fafafa; border: 1px solid #ddd;">${data.caption || ''}</textarea>
                        <i class="bi bi-emoji-smile position-absolute text-muted" style="top: 8px; right: 10px; cursor:pointer;" title="Emoji" onclick="toggleEmojiPicker(this, 'conf-caption')"></i>
                    </div>
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'video':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide your reply video url</label>
                    <input type="text" class="form-control cfg-input" id="conf-video-url" value="${data['video-url'] || ''}" placeholder="Put your video url here or click the upload box." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4">
                    <div class="upload-box-wrapper" style="border: 1px dashed #007bff; border-radius: 4px; padding: 40px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-media-vid').click()">
                        <!-- Currently visually mimics an upload box for design purposes -->
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 2rem; color: #007bff;"></i>
                        <input type="file" id="conf-upload-media-vid" accept="video/mp4, video/x-flv, video/x-ms-wmv" style="display:none;" onchange="uploadMediaToBot(this, 'conf-video-url', 'upload-status-media-vid')">
                        <div id="upload-status-media-vid" class="mt-2 text-muted" style="font-size:12px; font-weight: 500;">Click to upload (mp4, flv, wmv)</div>
                    </div>
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'audio':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide reply audio url</label>
                    <input type="text" class="form-control cfg-input" id="conf-audio-url" value="${data['audio-url'] || ''}" placeholder="Put audio url here or click the upload box." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4">
                    <div class="upload-box-wrapper" style="border: 1px dashed #007bff; border-radius: 4px; padding: 40px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-media-audio').click()">
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 2rem; color: #007bff;"></i>
                        <input type="file" id="conf-upload-media-audio" accept="audio/amr, audio/mp3, audio/wav, audio/mpeg" style="display:none;" onchange="uploadMediaToBot(this, 'conf-audio-url', 'upload-status-media-audio')">
                        <div id="upload-status-media-audio" class="mt-2 text-muted" style="font-size:12px; font-weight: 500;">Supported types: amr, mp3, wav</div>
                    </div>
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'file':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide your document url</label>
                    <input type="text" class="form-control cfg-input" id="conf-url" value="${data['file-url'] || ''}" placeholder="https://..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4">
                    <div class="upload-box-wrapper" style="border: 1px dashed #007bff; border-radius: 4px; padding: 40px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-media-file').click()">
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 2rem; color: #007bff;"></i>
                        <input type="file" id="conf-upload-media-file" accept="application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, text/plain, application/vnd.ms-excel" style="display:none;" onchange="uploadMediaToBot(this, 'conf-url', 'upload-status-media-file')">
                        <div id="upload-status-media-file" class="mt-2 text-muted" style="font-size:12px; font-weight: 500;">Click to upload (pdf, docx, txt, etc)</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Body Message (Optional)</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-body')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-body', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-body" rows="4" placeholder="Type your document message here..." style="background: #fafafa; border: 1px solid #ddd;">${data.body_text || data.caption || ''}</textarea>
                        <i class="bi bi-emoji-smile position-absolute text-muted" style="top: 8px; right: 10px; cursor:pointer;" title="Emoji" onclick="toggleEmojiPicker(this, 'conf-body')"></i>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Footer Text (Optional)</label>
                    <input type="text" class="form-control cfg-input" id="conf-footer" value="${data.footer_text || ''}" placeholder="Thank you!" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>

                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'cta':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Image Header URL (Optional)</label>
                    <input type="text" class="form-control cfg-input" id="conf-cta-image" value="${data.image || ''}" placeholder="https://..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4">
                    <div class="upload-box-wrapper" style="border: 1px dashed #007bff; border-radius: 4px; padding: 20px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-cta-media').click()">
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 2rem; color: #007bff;"></i>
                        <input type="file" id="conf-upload-cta-media" accept="image/png, image/jpeg, image/webp" style="display:none;" onchange="uploadMediaToBot(this, 'conf-cta-image', 'upload-status-cta-media')">
                        <div id="upload-status-cta-media" class="mt-2 text-muted" style="font-size:12px; font-weight: 500;">Click to upload (png, jpg, webp)</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Message Body (Optional)</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-cta-text')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-cta-text', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-cta-text" rows="3" placeholder="Visit our website now!" style="background: #fafafa; border: 1px solid #ddd;">${data.text || ''}</textarea>
                        <i class="bi bi-emoji-smile position-absolute text-muted" style="top: 8px; right: 10px; cursor:pointer;" title="Emoji" onclick="toggleEmojiPicker(this, 'conf-cta-text')"></i>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Footer Text (Optional)</label>
                    <input type="text" class="form-control cfg-input" id="conf-cta-footer" value="${data.footer || ''}" placeholder="Thank you!" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" style="font-size: 13px; color: #555;">Button Text</label>
                    <input type="text" class="form-control cfg-input" id="conf-cta-btn-text" value="${data.btnText || ''}" placeholder="Visit Website" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" style="font-size: 13px; color: #555;">Button URL</label>
                    <input type="text" class="form-control cfg-input" id="conf-cta-url" value="${data.url || ''}" placeholder="https://..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'text-cta':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Please provide your reply message</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-text-cta')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-text-cta', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-text-cta" rows="5" placeholder="Hi! Select an option below..." style="background: #fafafa; border: 1px solid #ddd;">${data.text || ''}</textarea>
                        <i class="bi bi-emoji-smile position-absolute text-muted" style="top: 8px; right: 10px; cursor:pointer;" title="Emoji" onclick="toggleEmojiPicker(this, 'conf-text-cta')"></i>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-primary" style="font-size: 13px;">Buttons Configuration (Max 3)</label>
                    <div id="sidebar-btn-list" class="mb-2"></div>
                </div>

                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'interactive':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 600; font-size: 14px; color: #e85d04;"><i class="bi bi-hand-index-thumb-fill me-1"></i> Interactive Node Config</label>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Header Image (Optional)</label>
                    <input type="text" class="form-control cfg-input" id="conf-interactive-image" value="${data.image || ''}" placeholder="https://..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3">
                    <div class="upload-box-wrapper" style="border: 1px dashed #e85d04; border-radius: 4px; padding: 25px; text-align: center; background: transparent; cursor: pointer; position: relative;" onclick="document.getElementById('conf-upload-interactive-img').click()">
                        <i class="bi bi-cloud-arrow-up-fill" style="font-size: 1.5rem; color: #e85d04;"></i>
                        <input type="file" id="conf-upload-interactive-img" accept="image/png, image/jpeg, image/webp" style="display:none;" onchange="uploadMediaToBot(this, 'conf-interactive-image', 'upload-status-interactive-img')">
                        <div id="upload-status-interactive-img" class="mt-1 text-muted" style="font-size:11px; font-weight: 500;">Click to upload (png, jpg, webp)</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Body Message</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-interactive-body')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-interactive-body', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-interactive-body" rows="4" placeholder="Enter message..." style="background: #fafafa; border: 1px solid #ddd;">${data.body_text || ''}</textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Footer Text (Optional)</label>
                    <input type="text" class="form-control cfg-input" id="conf-interactive-footer" value="${data.footer_text || ''}" placeholder="Footer text..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-primary" style="font-size: 13px;"><i class="bi bi-hand-index me-1"></i> Buttons Configuration</label>
                    <div id="sidebar-interactive-btn-list" class="mb-2"></div>
                </div>

                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'confirm':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 600; font-size: 14px; color: #E91E63;"><i class="bi bi-ui-checks me-1"></i> Yes/No Confirmation Config</label>
                </div>
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Message Body</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="toggleCustomVars(this, 'conf-confirm-body')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-link-45deg"></i> Custom <i class="bi bi-caret-down-fill" style="font-size:10px;"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-confirm-body', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <div class="position-relative">
                        <textarea class="form-control cfg-input" id="conf-confirm-body" rows="4" placeholder="Are you sure?" style="background: #fafafa; border: 1px solid #ddd;">${data.body_text || ''}</textarea>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-success" style="font-size: 13px;"><i class="bi bi-check-circle me-1"></i> Yes Button Label (Output 1)</label>
                    <input type="text" class="form-control cfg-input" id="conf-confirm-yes" value="${data.btn_yes_label || ''}" placeholder="Yes, Confirm" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger" style="font-size: 13px;"><i class="bi bi-x-circle me-1"></i> No Button Label (Output 2)</label>
                    <input type="text" class="form-control cfg-input" id="conf-confirm-no" value="${data.btn_no_label || ''}" placeholder="No, Cancel" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                <div class="mb-3 mt-4 pt-3 border-top" style="border-top-color: #ddd !important;">
                    <div class="d-flex justify-content-between">
                        <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Delay in reply - <span id="delay-val">${data.delay || 0}</span> sec</label>
                    </div>
                    <input type="range" class="form-range mt-2" id="conf-delay" min="0" max="60" value="${data.delay || 0}" oninput="document.getElementById('delay-val').innerText = this.value">
                </div>
            `;
            break;
        case 'condition':
            html = `
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 600; font-size: 14px; color: #AF52DE;"><i class="bi bi-chevron-right me-1"></i> Condition Config</label>
                </div>
                
                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Variable to Check</label>
                    <div class="d-flex align-items-center gap-2 mb-2 mt-1 position-relative">
                        <button type="button" class="btn btn-sm btn-light text-primary border" onclick="insertAtCursor('conf-cond-var', '#FIRST_NAME#')" style="font-size:12px; font-weight: 500; background: #fff;"><i class="bi bi-person"></i> Name</button>
                    </div>
                    <input type="text" class="form-control cfg-input" id="conf-cond-var" value="${data.variable || ''}" placeholder="e.g. #LEAD_USER_FIRST_NAME# or {Phone}" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Operator</label>
                    <select class="form-control cfg-input" id="conf-cond-op" style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                        <option value="equals" ${data.operator === 'equals' ? 'selected' : ''}>Equals (==)</option>
                        <option value="contains" ${data.operator === 'contains' ? 'selected' : ''}>Contains</option>
                        <option value="starts_with" ${data.operator === 'starts_with' ? 'selected' : ''}>Starts With</option>
                        <option value="not_empty" ${data.operator === 'not_empty' ? 'selected' : ''}>Is Not Empty</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="cfg-label" style="font-weight: 500; font-size: 13px; color: #555;">Target Value</label>
                    <input type="text" class="form-control cfg-input" id="conf-cond-val" value="${data.value || ''}" placeholder="Value to compare..." style="background: #fafafa; border: 1px solid #ddd; height: 38px;">
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block border p-2" style="border-radius:4px; font-size:11px; background:#f8fafc;">
                        <strong>Routes:</strong><br>
                        Top Port (output_1): <b style="color:green;">True</b> match<br>
                        Bottom Port (output_2): <b style="color:red;">False</b> match
                    </small>
                </div>
            `;
            break;
        default:
            html = `<p class="text-muted">No specific configuration for this node.</p>`;
    }

    configBody.innerHTML = html;
    
    // Special handling for interactive buttons in sidebar
    if (node.name === 'text-cta') {
        renderSidebarButtons(nodeId);
    }
    if (node.name === 'interactive') {
        renderInteractiveSidebarButtons(nodeId);
    }

    openConfig();
}

function openConfig() {
    const sidebar = document.getElementById('configSidebar');
    sidebar.classList.remove('d-none');
}

function closeConfig() {
    const sidebar = document.getElementById('configSidebar');
    sidebar.classList.add('d-none');
}

function saveConfig(silent = false) {
    if (!currentNodeId) return;
    const node = editor.getNodeFromId(currentNodeId);
    if (!node) return;
    const newData = { ...node.data };

    // Grabbing data from our dynamic form
    switch (node.name) {
        case 'start':
            newData.keywords = document.getElementById('conf-keywords') ? document.getElementById('conf-keywords').value : newData.keywords;
            newData.match = document.getElementById('conf-match') ? document.getElementById('conf-match').value : newData.match;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'text':
            newData.text = document.getElementById('conf-text') ? document.getElementById('conf-text').value : newData.text;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'image':
            newData['image-url'] = document.getElementById('conf-url') ? document.getElementById('conf-url').value : newData['image-url'];
            newData.caption = document.getElementById('conf-caption') ? document.getElementById('conf-caption').value : newData.caption;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'video':
            newData['video-url'] = document.getElementById('conf-video-url') ? document.getElementById('conf-video-url').value : newData['video-url'];
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'audio':
            newData['audio-url'] = document.getElementById('conf-audio-url') ? document.getElementById('conf-audio-url').value : newData['audio-url'];
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'file':
            newData['file-url'] = document.getElementById('conf-url') ? document.getElementById('conf-url').value : newData['file-url'];
            newData.body_text = document.getElementById('conf-body') ? document.getElementById('conf-body').value : (newData.body_text || newData.caption);
            newData.caption = newData.body_text; // backwards compat
            newData.footer_text = document.getElementById('conf-footer') ? document.getElementById('conf-footer').value : newData.footer_text;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'cta':
            newData.text = document.getElementById('conf-cta-text') ? document.getElementById('conf-cta-text').value : newData.text;
            newData.image = document.getElementById('conf-cta-image') ? document.getElementById('conf-cta-image').value : (newData.image || '');
            newData.footer = document.getElementById('conf-cta-footer') ? document.getElementById('conf-cta-footer').value : (newData.footer || '');
            newData.btnText = document.getElementById('conf-cta-btn-text') ? document.getElementById('conf-cta-btn-text').value : newData.btnText;
            newData.url = document.getElementById('conf-cta-url') ? document.getElementById('conf-cta-url').value : newData.url;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'text-cta':
            newData.text = document.getElementById('conf-text-cta') ? document.getElementById('conf-text-cta').value : newData.text;
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'interactive':
            newData.image = document.getElementById('conf-interactive-image') ? document.getElementById('conf-interactive-image').value : (newData.image || '');
            newData.body_text = document.getElementById('conf-interactive-body') ? document.getElementById('conf-interactive-body').value : (newData.body_text || '');
            newData.footer_text = document.getElementById('conf-interactive-footer') ? document.getElementById('conf-interactive-footer').value : (newData.footer_text || '');
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'confirm':
            newData.body_text = document.getElementById('conf-confirm-body') ? document.getElementById('conf-confirm-body').value : (newData.body_text || '');
            newData.btn_yes_label = document.getElementById('conf-confirm-yes') ? document.getElementById('conf-confirm-yes').value : (newData.btn_yes_label || '');
            newData.btn_no_label = document.getElementById('conf-confirm-no') ? document.getElementById('conf-confirm-no').value : (newData.btn_no_label || '');
            newData.delay = document.getElementById('conf-delay') ? document.getElementById('conf-delay').value : newData.delay;
            break;
        case 'condition':
            newData.variable = document.getElementById('conf-cond-var') ? document.getElementById('conf-cond-var').value : newData.variable;
            newData.operator = document.getElementById('conf-cond-op') ? document.getElementById('conf-cond-op').value : newData.operator;
            newData.value = document.getElementById('conf-cond-val') ? document.getElementById('conf-cond-val').value : newData.value;
            break;
    }

    editor.updateNodeDataFromId(currentNodeId, newData);
    updateNodePreview(currentNodeId);
    
    if (!silent) {
        Swal.fire({
            icon: 'success',
            title: 'Updated',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1000
        });
    }
}

/**
 * Update Node UI on canvas after config change
 */
function updateNodePreview(nodeId) {
    const node = editor.getNodeFromId(nodeId);
    const nodeEl = document.getElementById('node-' + nodeId);
    if (!nodeEl) return;

    switch (node.name) {
        case 'start': {
            const titleBox = nodeEl.querySelector('.cfg-title-display');
            if (titleBox) titleBox.textContent = node.data.title || document.getElementById('flowNameInput')?.value || 'Demo_bot';
            
            const kwBox = nodeEl.querySelector('.cfg-kw-display');
            if (kwBox) kwBox.textContent = node.data.keywords || 'hi, hello';
            
            const matchEl = nodeEl.querySelector('.cfg-match-display');
            if (matchEl) matchEl.textContent = (node.data.match === 'contains' ? 'String match' : 'Exact keyword match');
            break;
        }
        case 'text': {
            // Stats are static for now, no message preview string displayed inside the canvas text node in screenshot!
            break;
        }
        case 'image':
        case 'video':
        case 'audio':
        case 'file': {
            const urlKey = node.name === 'file' ? 'file-url' : node.name + '-url';
            const urlEl = nodeEl.querySelector('.small.text-muted.text-truncate') || nodeEl.querySelector('.small.text-muted');
            if (urlEl) urlEl.textContent = node.data[urlKey] || 'Click to set URL';
            break;
        }
        case 'cta': {
            const imgContainer = nodeEl.querySelector('.node-image-container');
            if (imgContainer) {
                if (node.data.image) {
                    imgContainer.innerHTML = `<img src="${node.data.image}" style="width:100%; height:80px; object-fit:cover; border-radius:8px;">`;
                } else {
                    imgContainer.innerHTML = `<div style="background:#e2e8f0; height:80px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-image" style="font-size:24px; color:#94a3b8;"></i></div>`;
                }
            }
            const bodyBox = nodeEl.querySelector('.node-message-box');
            if (bodyBox) bodyBox.innerHTML = node.data.text ? node.data.text.replace(/\n/g, '<br>') : '<strong>Message Body...</strong>';
            
            const footerBox = nodeEl.querySelector('.node-footer-box');
            if (footerBox) footerBox.textContent = node.data.footer || 'Footer text...';

            const btnLabel = nodeEl.querySelector('.url-label');
            if (btnLabel) btnLabel.textContent = node.data.btnText || 'Visit Website';
            break;
        }
        case 'condition': {
            const previewBox = nodeEl.querySelector('.node-message-box');
            if (previewBox) {
                if (node.data.variable && node.data.operator) {
                    let opStr = '==';
                    if (node.data.operator === 'contains') opStr = 'in';
                    if (node.data.operator === 'starts_with') opStr = '^=';
                    if (node.data.operator === 'not_empty') opStr = 'not empty';
                    previewBox.innerHTML = `<span style="font-size:11px;">IF: ${node.data.variable}<br>${opStr} ${node.data.operator === 'not_empty' ? '' : (node.data.value || '')}</span>`;
                } else {
                    previewBox.innerHTML = `Set Logic In Sidebar`;
                }
            }
            break;
        }
        case 'text-cta': {
            const bodyBox = nodeEl.querySelector('.node-message-box');
            if (bodyBox) bodyBox.innerHTML = node.data.text ? node.data.text.replace(/\n/g, '<br>') : '<strong>Your message here...</strong>';
            
            // Sync dynamic button labels on canvas
            const labelsContainer = nodeEl.querySelector('.port-labels-container');
            if (labelsContainer) {
                const btn1 = node.data['btn-0'] || 'Btn 1';
                const btn2 = node.data['btn-1'] || 'Btn 2';
                const btn3 = node.data['btn-2'] || 'Btn 3';
                
                const btnLabels = labelsContainer.querySelectorAll('.port-label-row');
                if (btnLabels[0]) {
                    const span = btnLabels[0].querySelector('span:last-child');
                    if (span) span.textContent = btn1;
                }
                if (btnLabels[1]) {
                    const span = btnLabels[1].querySelector('span:last-child');
                    if (span) span.textContent = btn2;
                }
                if (btnLabels[2]) {
                    const span = btnLabels[2].querySelector('span:last-child');
                    if (span) span.textContent = btn3;
                }
            }
            break;
        }
        case 'interactive': {
            // Update header image
            const imgContainer = nodeEl.querySelector('.interactive-header-img');
            if (imgContainer) {
                if (node.data.image) {
                    imgContainer.innerHTML = `<img src="${node.data.image}" style="width:100%; height:80px; object-fit:cover; border-radius:8px;">`;
                    imgContainer.style.display = 'block';
                } else {
                    imgContainer.innerHTML = '';
                    imgContainer.style.display = 'none';
                }
            }
            // Update body text
            const bodyBoxI = nodeEl.querySelector('.interactive-body-text');
            if (bodyBoxI) bodyBoxI.innerHTML = node.data.body_text ? node.data.body_text.replace(/\n/g, '<br>') : '<em style="color:#94a3b8;">Enter message...</em>';
            
            // Update footer text
            const footerBoxI = nodeEl.querySelector('.interactive-footer-text');
            if (footerBoxI) {
                if (node.data.footer_text) {
                    footerBoxI.textContent = node.data.footer_text;
                    footerBoxI.style.display = 'block';
                } else {
                    footerBoxI.style.display = 'none';
                }
            }

            // Update buttons preview
            const btn1El = nodeEl.querySelector('.interactive-btn-1');
            const btn2El = nodeEl.querySelector('.interactive-btn-2');
            const btn3El = nodeEl.querySelector('.interactive-btn-3');
            
            if (btn1El) {
                if (node.data.btn1_label && node.data.btn1_label.trim() !== '') {
                    btn1El.textContent = node.data.btn1_label;
                    btn1El.style.display = 'block';
                } else {
                    btn1El.style.display = 'none';
                }
            }
            if (btn2El) {
                if (node.data.btn2_label && node.data.btn2_label.trim() !== '') {
                    btn2El.textContent = node.data.btn2_label;
                    btn2El.style.display = 'block';
                } else {
                    btn2El.style.display = 'none';
                }
            }
            if (btn3El) {
                if (node.data.btn3_label && node.data.btn3_label.trim() !== '') {
                    btn3El.textContent = node.data.btn3_label;
                    btn3El.style.display = 'block';
                } else {
                    btn3El.style.display = 'none';
                }
            }

            // Update port labels
            const portLabels = nodeEl.querySelector('.port-labels-container');
            if (portLabels) {
                const rows = portLabels.querySelectorAll('.port-label-row');
                const labels = [node.data.btn1_label || 'Btn1', node.data.btn2_label || 'Btn2', node.data.btn3_label || 'Btn3'];
                rows.forEach((row, idx) => {
                    const rightSpan = row.querySelector('span:last-child');
                    if (rightSpan && labels[idx]) rightSpan.textContent = labels[idx];
                });
            }
            break;
        }
        case 'confirm': {
            const bodyBoxC = nodeEl.querySelector('.node-message-box');
            if (bodyBoxC) bodyBoxC.innerHTML = node.data.body_text ? node.data.body_text.replace(/\n/g, '<br>') : '<strong>Are you sure?</strong>';
            
            const portLabels = nodeEl.querySelector('.port-labels-container');
            if (portLabels) {
                const rows = portLabels.querySelectorAll('.port-label-row');
                const btnYes = node.data.btn_yes_label || 'Yes';
                const btnNo = node.data.btn_no_label || 'No';
                
                if (rows[0]) {
                    const span = rows[0].querySelector('span:last-child');
                    if (span) span.textContent = btnYes;
                }
                if (rows[1]) {
                    const span = rows[1].querySelector('span:last-child');
                    if (span) span.textContent = btnNo;
                }
            }
            break;
        }
    }
}

function updateAllNodePreviews() {
    const exportData = editor.export();
    const nodes = exportData.drawflow.Home?.data || exportData.drawflow.home?.data || {};
    Object.keys(nodes).forEach(id => updateNodePreview(id));
}

function renderSidebarButtons(nodeId) {
    const node = editor.getNodeFromId(nodeId);
    if (!node) return;
    const container = document.getElementById('sidebar-btn-list');
    if (!container) return;
    container.innerHTML = '';

    // Standard limit is 3 buttons for Text-CTA in regular WhatsApp Flows
    for (let i = 0; i < 3; i++) {
        const key = 'btn-' + i;
        const val = node.data[key] || '';
        const row = document.createElement('div');
        row.className = 'mb-2 d-flex align-items-center gap-2';
        row.innerHTML = `
            <div class="input-group input-group-sm">
                <span class="input-group-text border-0 ps-0 bg-transparent text-muted small" style="min-width:20px;">${i+1}</span>
                <input type="text" class="form-control form-control-sm border" value="${val}" 
                       placeholder="Button ${i+1} text" oninput="updateButtonData('${nodeId}', ${i}, this.value)"
                       style="font-size:12px; border-radius:4px;">
            </div>
        `;
        container.appendChild(row);
    }
}

function updateButtonData(nodeId, index, value) {
    const node = editor.getNodeFromId(nodeId);
    if (node) {
        // Create a copy of the data object
        const newData = { ...node.data };
        newData['btn-' + index] = value;
        
        // Use a single update call
        editor.updateNodeDataFromId(nodeId, newData);
        
        // Debounce updateNodePreview? Or just do it.
        updateNodePreview(nodeId);
    }
}

function addButtonToSelectedNode() {
    if (!currentNodeId) return;
    const node = editor.getNodeFromId(currentNodeId);
    
    // Get total number of buttons
    let count = 0;
    for (let i = 0; i < 3; i++) {
        if (node.data['btn-' + i]) count++;
    }
    
    if (count >= 3) {
        Swal.fire({
            icon: 'warning',
            title: 'WhatsApp limit: 3 buttons',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    // Find first empty slot
    let nextIdx = -1;
    for (let i = 0; i < 3; i++) {
        if (!node.data['btn-' + i]) {
            nextIdx = i;
            break;
        }
    }
    
    if (nextIdx !== -1) {
        node.data['btn-' + nextIdx] = "New Button";
        editor.updateNodeDataFromId(currentNodeId, node.data);
        renderSidebarButtons(currentNodeId);
        updateNodePreview(currentNodeId);
    }
}

function removeButtonFromSelectedNode(key) {
    if (!currentNodeId) return;
    const node = editor.getNodeFromId(currentNodeId);
    delete node.data[key];
    editor.updateNodeDataFromId(currentNodeId, node.data);
    editor.removeNodeOutput(currentNodeId, 'output_' + (Object.keys(node.data).filter(k => k.startsWith('btn-')).length + 1));
    renderSidebarButtons(currentNodeId);
}

function restoreInteractivePorts(nodeId) {
    // This ensures that existing nodes (loaded from DB) or new nodes have correctly 
    // aligned internal state if Drawflow mismatched them.
    const node = editor.getNodeFromId(nodeId);
    if (!node) return;
    
    // Auto-update Canvas Preview immediately
    updateNodePreview(nodeId);
}

/**
 * 1. Node Templates Management - Redesigned UI
 */
function getDrawflowStats() {
    return `
        <div class="node-stats-row border-bottom">
            <div class="stat-col"><i class="bi bi-send"></i><span>0</span></div>
            <div class="stat-col"><i class="bi bi-check2-all"></i><span>0</span></div>
            <div class="stat-col"><i class="bi bi-person"></i><span>0</span></div>
            <div class="stat-col"><i class="bi bi-exclamation-triangle"></i><span>0</span></div>
        </div>
        <div class="node-delay-row">
            <span>... Delay</span><span>0 Sec</span>
        </div>
    `;
}

function getNodeTemplate(type) {
    switch (type) {
        case 'start':
            return `
                <div class="node-root start-node-root" style="min-width: 250px;">
                    <div class="start-node-header">
                        <i class="bi bi-person-walking"></i>
                        <span>Start Bot Flow</span>
                    </div>
                    <div class="start-node-body">
                        <div class="start-node-badge-wrap">
                            <span class="start-node-flow-badge cfg-title-display">Demo_bot</span>
                        </div>
                        <div class="start-node-info-row">
                            <span class="start-node-label">Bot trigger keywords</span>
                            <span class="cfg-kw-display start-node-value">hi, hello</span>
                        </div>
                        <div class="start-node-info-row">
                            <span class="start-node-label">Keyword matching type</span>
                            <span class="cfg-match-display start-node-value">Exact keyword match</span>
                        </div>
                    </div>
                    <div class="start-node-footer">
                        <span class="start-node-port-label">Compose Next Message</span>
                    </div>
                </div>
            `;
        case 'text':
            return `
                <div class="node-root" style="min-width: 250px; background: #EEF2F6;">
                    <div class="node-header-custom" style="border-bottom:none; background: #EEF2F6;"><i class="bi bi-list" style="color:#4e5d78;"></i> Text</div>
                    <div class="node-stats-row border-0 mt-1 px-2 pb-4" style="background: #EEF2F6; justify-content: space-around;">
                        <div class="stat-col"><i class="bi bi-cursor-fill text-primary" style="opacity: 0.8;"></i><span style="font-size:9px;">Sent</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-check-circle text-success" style="opacity: 0.8;"></i><span style="font-size:9px;">Delivered</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-person-x text-primary" style="opacity: 0.8; font-size: 14px;"></i><span style="font-size:9px;">Subscribers</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-heart-fill text-danger" style="opacity: 0.8;"></i><span style="font-size:9px;">Errors</span><span style="font-size:11px;">0</span></div>
                    </div>
                    <div class="port-labels-container" style="background:#EEF2F6; border-top:1px dashed #cbd5e1; margin-top:15px; padding: 10px 0;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">Message</span>
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Compose Next Message</span>
                        </div>
                    </div>
                </div>
            `;
        case 'image':
            return `
                <div class="node-root" style="min-width: 250px; background: #EEF2F6;">
                    <div class="node-header-custom" style="border-bottom:none; background: #EEF2F6;"><i class="bi bi-camera-fill" style="color:#4e5d78;"></i> Image</div>
                    <div class="node-stats-row border-0 mt-1 px-2 pb-4" style="background: #EEF2F6; justify-content: space-around;">
                        <div class="stat-col"><i class="bi bi-cursor-fill text-primary" style="opacity: 0.8;"></i><span style="font-size:9px;">Sent</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-check-circle text-success" style="opacity: 0.8;"></i><span style="font-size:9px;">Delivered</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-person-x text-primary" style="opacity: 0.8; font-size: 14px;"></i><span style="font-size:9px;">Subscribers</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-heart-fill text-danger" style="opacity: 0.8;"></i><span style="font-size:9px;">Errors</span><span style="font-size:11px;">0</span></div>
                    </div>
                    <div class="node-body-content text-center pb-2 pt-0" style="background:#EEF2F6;">
                        <div class="node-image-preview" style="background: transparent;">
                            <i class="bi bi-hand-index" style="font-size:2rem; color:#4e5d78;"></i>
                        </div>
                    </div>
                    <div class="port-labels-container" style="background:#EEF2F6; border-top:1px dashed #cbd5e1; padding: 10px 0;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">Message</span>
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Compose Next Message</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Keyboard Button</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Add Buttons</span>
                        </div>
                    </div>
                </div>
            `;
        case 'cta':
            return `
                <div class="node-root cta-node-root">
                    <div class="node-header-custom" style="background:#f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 15px; border-radius: 12px 12px 0 0; color: #007bff; font-weight: 600;"><i class="bi bi-cursor-fill me-1"></i> Link Button</div>
                    
                    <div class="node-body-content p-3" style="background:#fff;">
                        <div class="node-image-container mb-2">
                            <div style="background:#e2e8f0; height:80px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-image" style="font-size:24px; color:#94a3b8;"></i></div>
                        </div>
                        <div class="node-message-box" style="font-size: 13px; color: #334155; line-height: 1.4; margin-bottom: 8px;">
                            <strong>Message Body...</strong>
                        </div>
                        <div class="node-footer-box mb-3" style="font-size: 11px; color: #94a3b8;">Footer text...</div>
                        
                        <div class="cta-preview-btn-wrapper" style="text-align: center; border: 1px solid #007bff; color: #007bff; padding: 6px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                            <i class="bi bi-link-45deg"></i> <span class="url-label">Visit Website</span>
                        </div>
                    </div>

                    <div class="port-labels-container" style="border-top: 1px dashed #e2e8f0; padding-top: 10px;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">In</span>
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Next</span>
                        </div>
                    </div>
                </div>
            `;
        case 'audio':
            return `
                <div class="node-root" style="min-width: 250px; background: #EEF2F6;">
                    <div class="node-header-custom" style="border-bottom:none; background: #EEF2F6;"><i class="bi bi-mic-fill" style="color:#4e5d78;"></i> Audio</div>
                    <div class="node-stats-row border-0 mt-1 px-2 pb-4" style="background: #EEF2F6; justify-content: space-around;">
                        <div class="stat-col"><i class="bi bi-cursor-fill text-primary" style="opacity: 0.8;"></i><span style="font-size:9px;">Sent</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-check-circle text-success" style="opacity: 0.8;"></i><span style="font-size:9px;">Delivered</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-person-x text-primary" style="opacity: 0.8; font-size: 14px;"></i><span style="font-size:9px;">Subscribers</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-heart-fill text-danger" style="opacity: 0.8;"></i><span style="font-size:9px;">Errors</span><span style="font-size:11px;">0</span></div>
                    </div>
                    <div class="node-body-content text-center pb-2 pt-0" style="background:#EEF2F6;">
                        <div class="node-image-preview" style="background: transparent;">
                            <i class="bi bi-hand-index" style="font-size:2rem; color:#4e5d78;"></i>
                        </div>
                    </div>
                    <div class="port-labels-container" style="background:#EEF2F6; border-top:1px dashed #cbd5e1; padding: 10px 0;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">Message</span>
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Compose Next Message</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Keyboard Button</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Add Buttons</span>
                        </div>
                    </div>
                </div>
            `;
        case 'video':
            return `
                <div class="node-root" style="min-width: 250px; background: #EEF2F6;">
                    <div class="node-header-custom" style="border-bottom:none; background: #EEF2F6;"><i class="bi bi-camera-video-fill" style="color:#4e5d78;"></i> Video</div>
                    <div class="node-stats-row border-0 mt-1 px-2 pb-4" style="background: #EEF2F6; justify-content: space-around;">
                        <div class="stat-col"><i class="bi bi-cursor-fill text-primary" style="opacity: 0.8;"></i><span style="font-size:9px;">Sent</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-check-circle text-success" style="opacity: 0.8;"></i><span style="font-size:9px;">Delivered</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-person-x text-primary" style="opacity: 0.8; font-size: 14px;"></i><span style="font-size:9px;">Subscribers</span><span style="font-size:11px;">0</span></div>
                        <div class="stat-col"><i class="bi bi-heart-fill text-danger" style="opacity: 0.8;"></i><span style="font-size:9px;">Errors</span><span style="font-size:11px;">0</span></div>
                    </div>
                    <div class="node-body-content text-center pb-2 pt-0" style="background:#EEF2F6;">
                        <div class="node-image-preview" style="background: transparent;">
                            <i class="bi bi-hand-index" style="font-size:2rem; color:#4e5d78;"></i>
                        </div>
                    </div>
                    <div class="port-labels-container" style="background:#EEF2F6; border-top:1px dashed #cbd5e1; padding: 10px 0;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">Message</span>
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Compose Next Message</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Keyboard Button</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center mt-2" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#555; position: relative; left: -8px;">Add Buttons</span>
                        </div>
                    </div>
                </div>
            `;
        case 'file':
            return `
                <div>
                    <div class="node-header-custom"><i class="bi bi-paperclip" style="color:#34C759;"></i> File</div>
                    ${getDrawflowStats()}
                    <div class="node-body-content text-center py-3">
                        <i class="bi bi-folder-fill" style="font-size:3rem; color:#FF9500;"></i>
                        <div class="mt-2 text-muted" style="font-size:0.65rem;">NewBuilder... .docx</div>
                    </div>
                    <div class="port-labels-container"><div class="port-label-row"><span class="text-start">Message</span><span class="text-end">Compose Next Message</span></div></div>
                </div>
            `;
        case 'condition':
            return `
                <div class="node-root condition-node-root">
                    <div class="node-header-custom" style="color:#AF52DE; border-bottom: 1px solid #e2e8f0; padding: 8px 15px;"><i class="bi bi-chevron-right me-1"></i> Condition</div>
                    <div class="node-body-content" style="background:#fff;">
                        <div class="node-message-box p-2 text-center" style="font-size:12px; font-weight: 500; color:#555;">Set Logic In Sidebar</div>
                    </div>
                    <div class="port-labels-container" style="border-top: 1px dashed #e2e8f0; padding-top: 4px;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">In</span>
                            <span style="font-size:11px; color:#10b981; font-weight: 600; position: relative; left: -8px;">True</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#ef4444; font-weight: 600; position: relative; left: -8px;">False</span>
                        </div>
                    </div>
                </div>
            `;
        case 'text-cta':
            return `
                <div class="node-root text-cta-node-root">
                    <div class="node-header-custom" style="color: #05cd99;"><i class="bi bi-chat-square-text-fill me-1"></i> Text with Buttons</div>
                    
                    <div class="node-body-content p-3">
                        <div class="node-message-box" style="font-size: 13px; color: #334155; line-height: 1.4;">
                            <strong>Your message here...</strong>
                        </div>
                    </div>
                    
                    <div class="port-labels-container">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#64748b; position: relative; right: -8px;">In</span>
                            <span style="font-size:11px; color:#64748b; font-weight: 500; position: relative; left: -8px;">Btn 1</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#64748b; font-weight: 500; position: relative; left: -8px;">Btn 2</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#64748b; font-weight: 500; position: relative; left: -8px;">Btn 3</span>
                        </div>
                    </div>
                </div>
            `;
        case 'interactive':
            return `
                <div class="node-root interactive-node-root">
                    <div class="node-header-custom" style="background: linear-gradient(135deg, #fff7ed, #fed7aa); border-bottom: 1px solid #fdba74; color: #c2410c; font-weight: 700;">
                        <i class="bi bi-hand-index-thumb-fill me-1"></i> Interactive
                    </div>
                    
                    <div class="node-body-content p-3" style="background:#fff;">
                        <div class="interactive-header-img mb-2" style="display:none;"></div>
                        
                        <div class="interactive-body-text" style="font-size: 13px; color: #334155; line-height: 1.5; background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 10px; border-radius: 6px; margin-bottom: 8px; word-wrap: break-word; white-space: pre-wrap;">
                            <em style="color:#94a3b8;">Enter message...</em>
                        </div>
                        
                        <div class="interactive-footer-text" style="font-size: 11px; color: #94a3b8; margin-bottom: 10px; display:none;"></div>
                        
                        <div class="interactive-buttons-preview">
                            <div class="interactive-btn-1 interactive-btn-preview-item">Button 1</div>
                            <div class="interactive-btn-2 interactive-btn-preview-item">Button 2</div>
                            <div class="interactive-btn-3 interactive-btn-preview-item">Button 3</div>
                        </div>
                    </div>

                    <div class="port-labels-container" style="border-top: 1px dashed #fdba74;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#64748b; position: relative; right: -8px;">In</span>
                            <span style="font-size:11px; color:#c2410c; font-weight: 600; position: relative; left: -8px;">Btn1</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#c2410c; font-weight: 600; position: relative; left: -8px;">Btn2</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:10px; color:#c2410c; font-weight: 600; position: relative; left: -8px;">Btn3</span>
                        </div>
                    </div>
                </div>
            `;
        case 'confirm':
            return `
                <div class="node-root confirm-node-root">
                    <div class="node-header-custom" style="background:#fdf2f8; border-bottom: 1px solid #fbcfe8; color: #E91E63; font-weight: 600;"><i class="bi bi-ui-checks me-1"></i> Yes/No Confirmation</div>
                    <div class="node-body-content p-3" style="background:#fff;">
                        <div class="node-message-box" style="font-size: 13px; color: #334155; line-height: 1.4; margin-bottom: 8px;">
                            <strong>Are you sure?</strong>
                        </div>
                    </div>
                    <div class="port-labels-container" style="border-top: 1px dashed #fbcfe8; padding-top: 5px;">
                        <div class="port-label-row d-flex justify-content-between align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#555; position: relative; right: -8px;">In</span>
                            <span style="font-size:11px; color:#10b981; font-weight: 600; position: relative; left: -8px;">Yes</span>
                        </div>
                        <div class="port-label-row d-flex justify-content-end align-items-center" style="padding: 2px 15px;">
                            <span style="font-size:11px; color:#ef4444; font-weight: 600; position: relative; left: -8px;">No</span>
                        </div>
                    </div>
                </div>
            `;
        default:
            return `<div>Node type not found</div>`;
    }
}

/**
 * 2. Drag & Drop Engine
 */
function allowDrop(ev) { ev.preventDefault(); }
function drag(ev) { 
    const nodeElement = ev.target.closest('[data-node]');
    if (nodeElement) {
        ev.dataTransfer.setData("node", nodeElement.getAttribute('data-node')); 
    }
}
function drop(ev) {
    ev.preventDefault();
    const type = ev.dataTransfer.getData("node");
    addNodeToDrawflow(type, ev.clientX, ev.clientY);
}

function addNodeToDrawflow(type, pos_x, pos_y) {
    if (editor.editor_mode === 'fixed') return false;
    pos_x = pos_x * (editor.precanvas.clientWidth / (editor.precanvas.clientWidth * editor.zoom)) - (editor.precanvas.getBoundingClientRect().x * (editor.precanvas.clientWidth / (editor.precanvas.clientWidth * editor.zoom)));
    pos_y = pos_y * (editor.precanvas.clientHeight / (editor.precanvas.clientHeight * editor.zoom)) - (editor.precanvas.getBoundingClientRect().y * (editor.precanvas.clientHeight / (editor.precanvas.clientHeight * editor.zoom)));

    const template = getNodeTemplate(type);
    let inputs = 1; let outputs = 1;
    if (type === 'start') inputs = 0;
    if (type === 'condition') outputs = 2;
    if (type === 'text-cta') outputs = 3;
    if (type === 'interactive') outputs = 3;
    if (type === 'cta') outputs = 2;
    if (type === 'confirm') outputs = 2;
    if (type === 'image' || type === 'video' || type === 'audio') outputs = 3;

    const defaultData = {};
    if (type === 'start') {
        defaultData.title = document.getElementById('flowNameInput')?.value || 'Demo_bot';
    }
    if (type === 'condition') {
        defaultData.variable = '';
        defaultData.operator = 'equals';
        defaultData.value = '';
    }
    if (type === 'text-cta') {
        defaultData.text = 'Hi! Choose an option:';
        defaultData['btn-0'] = 'Option 1';
        defaultData['btn-1'] = 'Option 2';
        defaultData.delay = 0;
    }
    if (type === 'interactive') {
        defaultData.body_text = '';
        defaultData.footer_text = '';
        defaultData.image = '';
        defaultData.btn1_label = 'Button 1';
        defaultData.btn2_label = 'Button 2';
        defaultData.btn3_label = 'Button 3';
        defaultData.delay = 0;
    }
    if (type === 'confirm') {
        defaultData.body_text = 'Are you sure?';
        defaultData.btn_yes_label = 'Yes, Confirm';
        defaultData.btn_no_label = 'No, Cancel';
        defaultData.delay = 0;
    }

    const nodeId = editor.addNode(type, inputs, outputs, pos_x, pos_y, type, defaultData, template);
    
    // After creation, sync badge/preview with actual data
    if (type === 'start') {
        setTimeout(() => updateNodePreview(nodeId), 50);
    }
}

/**
 * 4. API Integration & Flow Management
 */
function validateFlow(data) {
    try {
        const nodes = data.drawflow.Home.data;
        const nodeIds = Object.keys(nodes);
        
        if (nodeIds.length === 0) {
            return { valid: false, message: 'Canvas is empty. Please add some nodes to build a flow.' };
        }

        const startNodes = nodeIds.filter(id => nodes[id].name === 'start');
        if (startNodes.length === 0) {
            return { valid: false, message: 'Missing Start node! You must have exactly one "Start/Trigger" node.' };
        }
        if (startNodes.length > 1) {
            return { valid: false, message: 'Multiple Start nodes found! You can only have one Start node per flow.' };
        }
        
        const startNode = nodes[startNodes[0]];
        const startConns = startNode.outputs.output_1 ? startNode.outputs.output_1.connections : [];
        if (!startConns || startConns.length === 0) {
            return { valid: false, message: 'Start node is disconnected! Please connect it to your first message.' };
        }

        for (let id of nodeIds) {
            const node = nodes[id];
            const ndata = node.data;
            
            if (node.name === 'text' && (!ndata.text || ndata.text.trim() === '')) {
                return { valid: false, message: 'A Text node is empty. Please configure it.', nodeId: id };
            }
            if (node.name === 'image' && (!ndata['image-url'] || ndata['image-url'].trim() === '')) {
                return { valid: false, message: 'An Image node is missing its file URL. Please configure it.', nodeId: id };
            }
            if (node.name === 'video' && (!ndata['video-url'] || ndata['video-url'].trim() === '')) {
                return { valid: false, message: 'A Video node is missing its file URL.', nodeId: id };
            }
            if (node.name === 'audio' && (!ndata['audio-url'] || ndata['audio-url'].trim() === '')) {
                return { valid: false, message: 'An Audio node is missing its file URL.', nodeId: id };
            }
            if (node.name === 'file' && (!ndata['file-url'] || ndata['file-url'].trim() === '')) {
                return { valid: false, message: 'A File/Document node is missing its URL.', nodeId: id };
            }
            if (node.name === 'cta' && (!ndata.url || ndata.url.trim() === '' || !ndata.btnText || ndata.btnText.trim() === '')) {
                return { valid: false, message: 'A Link Button node is incomplete. Both Button Text and URL are required.', nodeId: id };
            }
            if (node.name === 'text-cta') {
                if ((ndata.prompt || ndata.text || '').trim() === '') {
                    return { valid: false, message: 'A Button node is missing its main message.', nodeId: id };
                }
                const btns = Object.keys(ndata).filter(k => k.startsWith('btn-'));
                if (btns.length === 0) {
                    return { valid: false, message: 'A Button node must have at least one button configured.', nodeId: id };
                }
            }
            if (node.name === 'interactive') {
                if ((ndata.body_text || '').trim() === '') {
                    return { valid: false, message: 'An Interactive node is missing its body message.', nodeId: id };
                }
                const hasAnyBtn = (ndata.btn1_label || '').trim() !== '' || (ndata.btn2_label || '').trim() !== '' || (ndata.btn3_label || '').trim() !== '';
                if (!hasAnyBtn) {
                    return { valid: false, message: 'An Interactive node must have at least one button label.', nodeId: id };
                }
            }
            if (node.name === 'confirm') {
                if ((ndata.body_text || '').trim() === '') {
                    return { valid: false, message: 'A Confirm node is missing its body message.', nodeId: id };
                }
                if ((ndata.btn_yes_label || '').trim() === '' || (ndata.btn_no_label || '').trim() === '') {
                    return { valid: false, message: 'A Confirm node must have both Yes and No buttons labeled.', nodeId: id };
                }
            }
        }

        return { valid: true };
    } catch (err) {
        console.error('Validation error:', err);
        return { valid: true }; // Fallback to allow saving if validation logic errors
    }
}

function saveFlow() {
    // Auto sync any currently open node config before validating!
    if (currentNodeId && !document.getElementById('configSidebar').classList.contains('d-none')) {
        saveConfig(true); 
    }

    const data = editor.export();
    
    // Validate Flow before saving
    const validation = validateFlow(data);
    if (!validation.valid) {
        if (validation.nodeId) {
            // Highlight the problematic node visually if a nodeId is returned
            const nodeEl = document.getElementById('node-' + validation.nodeId);
            if (nodeEl) {
                nodeEl.style.boxShadow = '0 0 15px rgba(220, 53, 69, 0.8)';
                setTimeout(() => { nodeEl.style.boxShadow = ''; }, 3000);
            }
        }
        Swal.fire({ icon: 'warning', title: 'Attention Required', text: validation.message });
        return; // Abort saving
    }

    const flowName = document.getElementById('flowNameInput').value || 'Master Flow';
    
    Swal.fire({ title: 'Saving Flow...', didOpen: () => Swal.showLoading() });

    fetch('../api/chatbot/save-flow.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: flowName, flow: data })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 1500 });
        } else {
            Swal.fire({ icon: 'error', title: 'Oops!', text: res.message });
        }
    })
    .catch(err => Swal.fire('Error', 'Connection failed: ' + err.message, 'error'));
}

function loadFlow(quiet = false) {
    if (!quiet) Swal.fire({ title: 'Loading Flow...', didOpen: () => Swal.showLoading() });
    
    // Load latest flow for this user (not hardcoded name)
    fetch('../api/chatbot/get-flow.php?load_latest=1')
    .then(res => res.json())
    .then(res => {
        if (!quiet) Swal.close();
        if (res.success && res.flow) {
            editor.import(res.flow);
            // Update flow name in header input
            if (res.flow_name) {
                const nameInput = document.getElementById('flowNameInput');
                if (nameInput) nameInput.value = res.flow_name;
            }
            // Update all node previews to show actual saved data
            setTimeout(() => updateAllNodePreviews(), 100);
        } else if (!quiet) {
            Swal.fire({ icon: 'info', title: 'No Flow Found', text: 'Start by dragging nodes from the top bar!' });
        }
    })
    .catch(err => {
        if (!quiet) Swal.fire('Error', 'Failed to load flow', 'error');
    });
}

function clearCanvas() {
    Swal.fire({ 
        icon: 'warning', 
        title: 'Clear Canvas?', 
        text: 'This will delete all blocks. Are you sure?', 
        showCancelButton: true 
    }).then(r => r.isConfirmed && editor.clearModuleSelected());
}

/**
 * 5. Media Upload Handler
 */
async function uploadMediaToBot(inputElement, targetInputId, msgElementId) {
    if (!inputElement.files || inputElement.files.length === 0) return;
    const file = inputElement.files[0];
    const formData = new FormData();
    formData.append('file', file);
    
    const msgEl = document.getElementById(msgElementId);
    if (msgEl) msgEl.innerHTML = '<span class="text-primary spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

    // The PHP endpoint we created is at /wapi/api/upload_media.php. 
    // Assuming this page is /wapi/admin/chatbot-builder.php
    try {
        const response = await fetch('../api/upload_media.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            document.getElementById(targetInputId).value = data.url;
            if (msgEl) msgEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Uploaded!</span>';
        } else {
            if (msgEl) msgEl.innerHTML = '<span class="text-danger">' + data.message + '</span>';
            alert('Upload failed: ' + data.message);
        }
    } catch(err) {
        console.error('API Error:', err);
        if (msgEl) msgEl.innerHTML = '<span class="text-danger">HTTP Error during upload</span>';
    } finally {
        inputElement.value = ''; // allow re-upload
    }
}

/* ============================================================
 *  MY FLOWS PANEL  –  List / Load / Delete / New
 * ============================================================ */

function openFlowsPanel() {
    const panel   = document.getElementById('flowsPanel');
    const overlay = document.getElementById('flowsPanelOverlay');
    panel.style.right  = '0';
    overlay.style.display = 'block';
    loadFlowsList();
}

function closeFlowsPanel() {
    const panel   = document.getElementById('flowsPanel');
    const overlay = document.getElementById('flowsPanelOverlay');
    panel.style.right  = '-420px';
    overlay.style.display = 'none';
}

function loadFlowsList() {
    const container = document.getElementById('flowsList');
    container.innerHTML = `
        <div class="text-center text-muted py-5" style="font-size:13px;">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><br>Loading your flows...
        </div>`;

    fetch('../api/chatbot/list-flows.php')
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                container.innerHTML = `<p class="text-danger text-center mt-4">Failed to load flows.</p>`;
                return;
            }
            if (!res.flows || res.flows.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-wind fs-2 d-block mb-2"></i>
                        No saved flows yet.<br>
                        <small>Click <strong>Create New Flow</strong> to start.</small>
                    </div>`;
                return;
            }
            container.innerHTML = res.flows.map(flow => {
                const updatedDate = flow.updated_at
                    ? new Date(flow.updated_at).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' })
                    : '';
                const isActive = parseInt(flow.is_active) === 1;
                return `
                <div class="flow-item-card d-flex align-items-center justify-content-between mb-2 p-3"
                     style="background:#f8f9ff; border:1px solid #e3e8f0; border-radius:10px; transition: box-shadow 0.15s;">
                    <div style="min-width:0; flex:1;">
                        <div class="fw-semibold text-truncate" style="color:#1a1a2e; font-size:14px;" title="${escapeHtml(flow.name)}">
                            <i class="bi bi-diagram-3-fill text-primary me-1" style="font-size:12px;"></i>
                            ${escapeHtml(flow.name)}
                        </div>
                        <div class="text-muted" style="font-size:11px; margin-top:2px;">
                            <i class="bi bi-clock me-1"></i>${updatedDate}
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 ms-2 flex-shrink-0">
                        <div class="form-check form-switch p-0 m-0" title="${isActive ? 'Flow is Active' : 'Flow is Inactive'}">
                            <input class="form-check-input ms-0 cursor-pointer" type="checkbox" role="switch" 
                                   ${isActive ? 'checked' : ''} 
                                   onchange="toggleFlowStatus(${flow.id}, this.checked)"
                                   style="width: 2.2rem; height: 1.1rem; cursor: pointer;">
                        </div>
                        <button class="btn btn-sm btn-outline-secondary px-2 py-1"
                                onclick="downloadFlow(${flow.id}, '${escapeHtml(flow.name).replace(/'/g,"\\'")}');"
                                title="Download JSON Flow" style="font-size:11px;">
                            <i class="bi bi-download"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary px-2 py-1"
                                onclick="openFlow(${flow.id}, '${escapeHtml(flow.name).replace(/'/g,"\\'")}');"
                                title="Edit Flow" style="font-size:11px;">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger px-2 py-1"
                                onclick="deleteFlow(${flow.id}, '${escapeHtml(flow.name).replace(/'/g,"\\'")}');"
                                title="Delete Flow" style="font-size:11px;">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        })
        .catch(() => {
            container.innerHTML = `<p class="text-danger text-center mt-4">Connection error.</p>`;
        });
}

function openFlow(flowId, flowName) {
    Swal.fire({ title: 'Loading "' + flowName + '"...', didOpen: () => Swal.showLoading() });

    fetch('../api/chatbot/get-flow.php?id=' + encodeURIComponent(flowId))
        .then(r => r.json())
        .then(res => {
            Swal.close();
            if (res.success && res.flow) {
                editor.clearModuleSelected();
                editor.import(res.flow);
                const nameInput = document.getElementById('flowNameInput');
                if (nameInput) nameInput.value = res.flow_name || flowName;
                setTimeout(() => updateAllNodePreviews(), 100);
                closeFlowsPanel();
                Swal.fire({ icon: 'success', title: 'Loaded!', text: '"' + flowName + '" is ready to edit.', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not load flow.' });
            }
        })
        .catch(() => Swal.fire('Error', 'Connection failed.', 'error'));
}

function deleteFlow(flowId, flowName) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Flow?',
        html: `Are you sure you want to permanently delete <strong>${escapeHtml(flowName)}</strong>?<br><small class="text-muted">This cannot be undone.</small>`,
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete!',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Deleting...', didOpen: () => Swal.showLoading() });

        fetch('../api/chatbot/delete-flow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ flow_id: flowId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
                loadFlowsList(); // Refresh panel list
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        })
        .catch(() => Swal.fire('Error', 'Connection failed.', 'error'));
    });
}

function toggleFlowStatus(flowId, status) {
    fetch('../api/chatbot/toggle-flow-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ flow_id: flowId, is_active: status ? 1 : 0 })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: status ? 'Flow Activated' : 'Flow Deactivated',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            loadFlowsList(); // Revert toggle on error
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Connection failed.', 'error');
        loadFlowsList();
    });
}

function newFlow() {
    Swal.fire({
        title: 'New Flow Name',
        input: 'text',
        inputPlaceholder: 'e.g. Welcome_Bot',
        inputAttributes: { maxlength: 80 },
        showCancelButton: true,
        confirmButtonText: 'Create',
        inputValidator: (value) => {
            if (!value || value.trim() === '') return 'Please enter a flow name!';
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        editor.clearModuleSelected();
        const nameInput = document.getElementById('flowNameInput');
        if (nameInput) nameInput.value = result.value.trim();
        closeFlowsPanel();
        Swal.fire({ icon: 'success', title: 'Canvas cleared!', text: 'Start building "' + result.value.trim() + '"', timer: 1500, showConfirmButton: false });
    });
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function addButtonToSelectedNode() {
    if (!currentNodeId) return;
    const node = editor.getNodeFromId(currentNodeId);
    
    // Get total number of buttons
    let count = 0;
    for (let i = 0; i < 3; i++) {
        if (node.data['btn-' + i]) count++;
    }
    
    if (count >= 3) {
        Swal.fire({
            icon: 'warning',
            title: 'WhatsApp limit: 3 buttons',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }

    // Find first empty slot
    let nextIdx = -1;
    for (let i = 0; i < 3; i++) {
        if (!node.data['btn-' + i]) {
            nextIdx = i;
            break;
        }
    }
    
    if (nextIdx !== -1) {
        node.data['btn-' + nextIdx] = "New Button";
        editor.updateNodeDataFromId(currentNodeId, node.data);
        renderSidebarButtons(currentNodeId);
        updateNodePreview(currentNodeId);
    }
}

/**
 * Interactive Node - Sidebar Buttons Renderer
 */
function renderInteractiveSidebarButtons(nodeId) {
    const node = editor.getNodeFromId(nodeId);
    if (!node) return;
    const container = document.getElementById('sidebar-interactive-btn-list');
    if (!container) return;
    container.innerHTML = '';

    const btnKeys = ['btn1_label', 'btn2_label', 'btn3_label'];
    btnKeys.forEach((key, idx) => {
        const val = node.data[key] || '';
        const row = document.createElement('div');
        row.className = 'mb-2 d-flex align-items-center gap-2';
        row.innerHTML = `
            <div class="input-group input-group-sm">
                <span class="input-group-text border-0 ps-0 bg-transparent" style="min-width:20px; font-size:12px; color: #c2410c; font-weight:600;">${idx+1}</span>
                <input type="text" class="form-control form-control-sm border" value="${val}" 
                       placeholder="Button ${idx+1} label" oninput="updateInteractiveBtnData('${nodeId}', '${key}', this.value)"
                       style="font-size:12px; border-radius:4px;">
            </div>
        `;
        container.appendChild(row);
    });
}

function updateInteractiveBtnData(nodeId, key, value) {
    const node = editor.getNodeFromId(nodeId);
    if (node) {
        const newData = { ...node.data };
        newData[key] = value;
        editor.updateNodeDataFromId(nodeId, newData);
        updateNodePreview(nodeId);
    }
}

/* ============================================================
 *  FLOW EXPORT & IMPORT (Download / Upload)
 * ============================================================ */
function downloadFlow(flowId, flowName) {
    Swal.fire({ title: 'Preparing Download...', didOpen: () => Swal.showLoading() });
    fetch('../api/chatbot/get-flow.php?id=' + encodeURIComponent(flowId))
        .then(r => r.json())
        .then(res => {
            if (res.success && res.flow) {
                Swal.close();
                const exportData = {
                    name: res.flow_name || flowName,
                    flow: res.flow
                };
                const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(exportData, null, 2));
                const downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute('href', dataStr);
                const safeName = (exportData.name || 'flow').replace(/[^a-z0-9]/gi, '_').toLowerCase();
                downloadAnchorNode.setAttribute('download', safeName + '_wapi_flow.json');
                document.body.appendChild(downloadAnchorNode); 
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not load flow for download.' });
            }
        })
        .catch(() => Swal.fire('Error', 'Connection failed.', 'error'));
}

function uploadFlowJSON(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    event.target.value = ''; // Reset input to allow re-upload

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = JSON.parse(e.target.result);
            
            // Validate JSON format
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid JSON structure');
            }

            let flowJson = null;
            let defaultName = 'Imported Flow ' + Math.floor(Math.random() * 1000);

            if (data.flow && data.flow.drawflow) {
                flowJson = data.flow;
                if (data.name) defaultName = data.name + ' (Import)';
            } else if (data.drawflow) {
                flowJson = data;
            } else {
                throw new Error('JSON is missing drawflow structure');
            }

            Swal.fire({
                title: 'Import Flow',
                text: 'Enter a name for this imported flow:',
                input: 'text',
                inputValue: defaultName,
                showCancelButton: true,
                confirmButtonText: '<i class=\"bi bi-cloud-upload\"></i> Upload & Save',
                confirmButtonColor: '#25d366'
            }).then((result) => {
                if (result.isConfirmed && result.value.trim() !== '') {
                    const finalName = result.value.trim();
                    
                    Swal.fire({ title: 'Uploading...', didOpen: () => Swal.showLoading() });
                    
                    fetch('../api/chatbot/save-flow.php', {
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: finalName, flow: flowJson })
                    })
                    .then(res => res.json())
                    .then(apiRes => {
                        if (apiRes.success) {
                            Swal.fire({ icon: 'success', title: 'Successfully Imported!', text: finalName + ' is now available in your flows.', timer: 2000, showConfirmButton: false });
                            loadFlowsList(); // Automatically reload the panel to show newly imported flow
                        } else {
                            Swal.fire({ icon: 'error', title: 'Failed to Import', text: apiRes.message || 'Unknown error occurred.' });
                        }
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Network connection failed.' }));
                }
            });

        } catch (error) {
            console.error('Flow Import Error:', error);
            Swal.fire({ icon: 'error', title: 'Invalid File', text: 'The uploaded file is not a valid WAPI Flow JSON.' });
        }
    };
    reader.readAsText(file);
}

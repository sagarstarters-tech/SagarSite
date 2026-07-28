<?php
/**
 * WAPI SaaS - Visual Chatbot Flow Builder
 * A modern, node-based UI for creating automated WhatsApp conversations.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$pageTitle = 'Chatbot Flow Builder';
$hideNav = true;

// Custom Assets for this page
$extraCss = [
    'https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.css',
    asset('assets/css/chatbot-builder.css')
];
$extraJs = [
    'https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    asset('assets/js/chatbot-builder.js?v=' . time())
];

include __DIR__ . '/../includes/header.php';
?>

<div class="builder-wrapper">
    <!-- Top Palette Toolbar -->
    <header class="top-palette align-items-center bg-light border-bottom px-3 py-2 d-flex justify-content-between">
        <div class="d-flex align-items-center gap-2 drag-items-row">
            <!-- Brand / Logo Icon (Optional) -->
            <div class="brand-icon me-3 bg-white border rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                <i class="bi bi-robot text-primary fs-5"></i>
            </div>
            
            <!-- Draggable Icons -->
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="text" title="Text Message">
                <i class="bi bi-fonts" style="color: #4B6EAF;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="image" title="Image">
                <i class="bi bi-image" style="color: #8C52FF;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="audio" title="Audio">
                <i class="bi bi-mic-fill" style="color: #FFB020;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="video" title="Video">
                <i class="bi bi-youtube" style="color: #FF3B30;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="file" title="File">
                <i class="bi bi-paperclip" style="color: #34C759;"></i>
            </div>

            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="text-cta" title="Text with Buttons">
                <i class="bi bi-chat-square-text-fill" style="color: #05cd99;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="cta" title="Link/URL Button">
                <i class="bi bi-box-arrow-up-right" style="color: #007AFF;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="interactive" title="Interactive Node">
                <i class="bi bi-hand-index-thumb-fill" style="color: #e85d04;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="confirm" title="Yes/No Confirmation">
                <i class="bi bi-ui-checks" style="color: #E91E63;"></i>
            </div>

            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="condition" title="Condition">
                <i class="bi bi-chevron-right" style="color: #AF52DE;"></i>
            </div>
            <div class="drag-drawflow icon-item" draggable="true" ondragstart="drag(event)" data-node="start" title="Start Flow">
                <i class="bi bi-play-circle-fill" style="color: #32ADE6;"></i>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2 builder-actions">
            <!-- Back Button -->
            <a href="<?= baseUrl('dashboard/'); ?>" class="action-circle-btn" title="Back to Dashboard">
                <i class="bi bi-arrow-left"></i>
            </a>
            <!-- Reset Button -->
            <button class="action-circle-btn" onclick="clearCanvas()" title="Clear Canvas">
                <i class="bi bi-x-lg"></i>
            </button>
            <!-- Flow Name Input -->
            <input type="text" id="flowNameInput" class="form-control form-control-sm text-center mx-2" value="Demo_bot" style="width: 150px; font-weight: 500;">
            
            <!-- My Flows Button -->
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center px-3" onclick="openFlowsPanel()" style="font-weight: 500;">
                <i class="bi bi-collection me-2"></i> My Flows
            </button>
            
            <!-- Save Button -->
            <button class="btn btn-sm btn-success d-flex align-items-center px-3" onclick="saveFlow()" style="font-weight: 500;">
                <i class="bi bi-save2 me-2"></i> Save
            </button>
        </div>
    </header>

    <div class="builder-layout d-flex">
        <!-- Configuration Panel (Hidden by default, slides in from left) -->
        <aside class="config-sidebar bg-light border-end d-none" id="configSidebar" style="width: 350px; flex-shrink: 0; display: flex; flex-direction: column;">
            <div class="config-header bg-secondary text-white text-center py-2 px-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold m-auto">Configure Button</h6>
            </div>
            <div class="config-body p-3 flex-grow-1 overflow-auto" id="configBody">
                <!-- Dynamic Content Form will load here -->
            </div>
            <div class="config-footer p-3 border-top d-flex justify-content-between align-items-center bg-white">
                <button class="btn btn-primary btn-sm px-4" onclick="saveConfig()"><i class="bi bi-save me-1"></i> Save</button>
                <button class="btn btn-light border btn-sm px-3" onclick="closeConfig()"><i class="bi bi-x-circle me-1"></i> Close</button>
            </div>
        </aside>

        <!-- Main Canvas Area -->
        <main class="builder-canvas-area flex-grow-1 position-relative" id="drawflow-canvas" ondrop="drop(event)" ondragover="allowDrop(event)" style="height: calc(100vh - var(--builder-top-height));">
            <!-- Drawflow will be initialized here -->
            
            <!-- Canvas Controls -->
            <div class="canvas-controls position-absolute bottom-0 end-0 m-3">
                <button class="btn btn-light shadow-sm me-1" onclick="editor.zoom_out()"><i class="bi bi-dash-lg"></i></button>
                <button class="btn btn-light shadow-sm me-1" onclick="editor.zoom_reset()"><i class="bi bi-aspect-ratio"></i></button>
                <button class="btn btn-light shadow-sm" onclick="editor.zoom_in()"><i class="bi bi-plus-lg"></i></button>
            </div>
        </main>
    </div>
</div>

<!-- ====== My Flows Side Panel ====== -->
<div id="flowsPanel" style="
    position: fixed;
    top: 0; right: -420px;
    width: 400px;
    height: 100vh;
    background: #fff;
    box-shadow: -4px 0 24px rgba(0,0,0,0.13);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
">
    <!-- Panel Header -->
    <div style="background: linear-gradient(135deg, #1a73e8, #0d47a1); color: #fff; padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-collection-fill fs-5"></i>
            <span style="font-size: 16px; font-weight: 600; letter-spacing: 0.3px;">My Chat Flows</span>
        </div>
        <button onclick="closeFlowsPanel()" style="background: rgba(255,255,255,0.15); border: none; color: #fff; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- New Flow & Upload Buttons -->
    <div style="padding: 14px 18px; border-bottom: 1px solid #f0f0f0; background: #f8f9ff; flex-shrink: 0; display: flex; gap: 10px;">
        <button onclick="newFlow()" class="btn btn-primary btn-sm flex-grow-1 d-flex align-items-center justify-content-center gap-2" style="font-weight: 500; padding: 9px;">
            <i class="bi bi-plus-circle-fill"></i> Create New Flow
        </button>
        <button onclick="document.getElementById('uploadFlowInput').click()" class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center gap-2" style="font-weight: 500; padding: 9px;" title="Upload JSON Flow">
            <i class="bi bi-upload"></i> Upload
        </button>
        <input type="file" id="uploadFlowInput" accept=".json" style="display:none;" onchange="uploadFlowJSON(event)">
    </div>

    <!-- Flows List -->
    <div id="flowsList" style="flex: 1; overflow-y: auto; padding: 12px 14px;">
        <div class="text-center text-muted py-5" style="font-size: 13px;">
            <i class="bi bi-hourglass-split fs-3 d-block mb-2"></i>
            Loading flows...
        </div>
    </div>
</div>
<!-- Overlay -->
<div id="flowsPanelOverlay" onclick="closeFlowsPanel()" style="
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.25);
    z-index: 9998;
    backdrop-filter: blur(2px);
"></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * WAPI SaaS - WhatsApp Connect Page
 * Easy integration steps for Meta WhatsApp Cloud API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireActivePlan();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];

$hideNav = true; // Prevents landing page nav from appearing in dashboard

// Get existing WhatsApp account
$waAccount = $db->fetch("SELECT * FROM whatsapp_accounts WHERE user_id = ? LIMIT 1", [$userId]);
$isConnected = !empty($waAccount['phone_number_id']) && !empty($waAccount['access_token']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'connect') {
        $phone_number_id = sanitize($_POST['phone_number_id'] ?? '');
        $waba_id = sanitize($_POST['waba_id'] ?? '');
        $access_token = trim($_POST['access_token'] ?? '');
        $business_name = sanitize($_POST['business_name'] ?? 'My WhatsApp App');

        if (empty($phone_number_id) || empty($access_token)) {
            setFlash('danger', 'Phone Number ID and Access Token are required!');
        } else {
            $data = [
                'user_id' => $userId,
                'phone_number_id' => $phone_number_id,
                'waba_id' => $waba_id,
                'access_token' => $access_token,
                'business_name' => $business_name,
                'status' => 'active'
            ];

            if ($waAccount) {
                $db->update('whatsapp_accounts', $data, 'id = ?', [$waAccount['id']]);
            } else {
                $db->insert('whatsapp_accounts', $data);
            }
            
            setFlash('success', 'WhatsApp successfully connected! You can now send and receive messages.');
            redirect('dashboard/whatsapp.php');
        }
    }

    if ($action === 'disconnect') {
        if ($waAccount) {
            $db->delete('whatsapp_accounts', 'id = ?', [$waAccount['id']]);
            setFlash('success', 'WhatsApp account disconnected.');
        }
        redirect('dashboard/whatsapp.php');
    }
}

$pageTitle = 'Connect WhatsApp';
$extraCss = [asset('assets/css/dashboard.css')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="dash-header mb-4">
            <div>
                <h1 class="dash-title">Connect to WhatsApp API <i class="bi bi-whatsapp text-success"></i></h1>
                <div class="dash-breadcrumb"><a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a><i class="bi bi-chevron-right"></i><span>WhatsApp Setup</span></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
            </div>
        </div>

        <!-- Cloud API Section -->
        <div id="cloudapi">

        <?php if ($isConnected): ?>
        
        <!-- Connected Status -->
        <div class="card mb-4 border-success">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-success">WhatsApp Connected!</h5>
                        <p class="mb-0 text-muted">Your Meta WhatsApp App is active and ready to send messages.</p>
                    </div>
                </div>
                <div>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to disconnect? All active bots and scheduled messages will fail.')">
                        <?= CSRF::tokenField(); ?>
                        <input type="hidden" name="action" value="disconnect">
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-plugin"></i> Disconnect</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Connection Details -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary"></i> App Details</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">Business Name</span>
                                <strong><?= e($waAccount['business_name']); ?></strong>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">Phone Number ID</span>
                                <strong><?= e($waAccount['phone_number_id']); ?></strong>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted">WABA ID</span>
                                <strong><?= e($waAccount['waba_id'] ?: 'N/A'); ?></strong>
                            </li>
                            <li class="list-group-item px-0">
                                <span class="text-muted d-block mb-1">Access Token</span>
                                <input type="password" class="form-control form-control-sm bg-light" value="<?= e($waAccount['access_token']); ?>" readonly>
                            </li>
                        </ul>
                        <button class="btn btn-primary btn-sm mt-3 w-100" onclick="document.getElementById('editSetup').style.display='block'; window.scrollTo(0, document.body.scrollHeight);"><i class="bi bi-pencil"></i> Edit Details</button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100 bg-primary bg-opacity-10 border-primary">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-diagram-3-fill"></i> Webhook Configuration</h6>
                        <p class="text-secondary small mb-3">To receive incoming messages and delivery status updates, copy the URL below and paste it into finding <strong>Webhooks</strong> section of your Meta App.</p>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Callback URL (Webhook URL)</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-white" id="webhookUrl" value="<?= APP_URL; ?>/api/webhook.php" readonly>
                                <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrl').value); alert('Copied!')"><i class="bi bi-copy"></i> Copy</button>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">Verify Token</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-white" id="verifyToken" value="<?= e($settings->get('webhook_verify_token', 'wapi_verify_token')); ?>" readonly>
                                <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('verifyToken').value); alert('Copied!')"><i class="bi bi-copy"></i> Copy</button>
                            </div>
                            <small class="text-muted d-block mt-2">Subscribe to the `messages` fields in Meta Webhooks.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

        <!-- Setup Form (Always shown if disconnected, hidden initially if connected) -->
        <div id="editSetup" style="<?= $isConnected ? 'display:none; margin-top: 2rem;' : '' ?>">
            <div class="row">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body p-4 p-lg-5">
                            <h4 class="fw-bold mb-1"><?= $isConnected ? 'Update Cloud API Credentials' : 'Link Your WhatsApp Cloud API' ?></h4>
                            <p class="text-muted mb-4">Follow the official Meta guides to generate these API credentials. You must use a permanent token to prevent connection loss.</p>
                            
                            <form method="POST">
                                <?= CSRF::tokenField(); ?>
                                <input type="hidden" name="action" value="connect">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">App / Business Name</label>
                                    <input type="text" name="business_name" class="form-control form-control-lg" required placeholder="e.g. My Awesome Store" value="<?= e($waAccount['business_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Phone Number ID <span class="text-danger">*</span></label>
                                    <input type="text" name="phone_number_id" class="form-control form-control-lg" required placeholder="e.g. 10456123987123" value="<?= e($waAccount['phone_number_id'] ?? ''); ?>">
                                    <div class="form-text">Found in Meta Developer > WhatsApp > API Setup > Phone number ID.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">WhatsApp Business Account ID (WABA ID)</label>
                                    <input type="text" name="waba_id" class="form-control form-control-lg" placeholder="e.g. 109871234761234" value="<?= e($waAccount['waba_id'] ?? ''); ?>">
                                    <div class="form-text">Found right below the Phone Number ID.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Permanent Access Token <span class="text-danger">*</span></label>
                                    <textarea name="access_token" rows="4" class="form-control" required placeholder="EAALx... (Make sure to generate a permanent System User token, NOT a temporary 24-hr token)"><?= e($waAccount['access_token'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">
                                    <i class="bi bi-link-45deg me-1"></i> <?= $isConnected ? 'Update Connection' : 'Connect WhatsApp API' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Helper Column -->
                <div class="col-lg-5 mt-4 mt-lg-0">
                    <div class="card bg-light border-0">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-lightbulb-fill text-warning me-2"></i> How to get these?</h5>
                            
                            <div class="d-flex mb-4">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; font-weight: bold;">1</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-1">Create a Meta App</h6>
                                    <p class="text-muted small mb-0">Go to <a href="https://developers.facebook.com/" target="_blank">Meta Developer</a> > My Apps, create a "Business" app, and add WhatsApp.</p>
                                </div>
                            </div>

                            <div class="d-flex mb-4">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; font-weight: bold;">2</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-1">Get IDs</h6>
                                    <p class="text-muted small mb-0">In your App Dashboard, go to <strong>WhatsApp > API Setup</strong> to copy your Phone Number ID and WABA ID.</p>
                                </div>
                            </div>

                            <div class="d-flex">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; font-weight: bold;">3</div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-1">Generate Permanent Token</h6>
                                    <p class="text-muted small mb-0">Go to Business Manager > System Users. Assign your app, and generate a token with `whatsapp_business_messaging` and `whatsapp_business_management` permissions.</p>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-top">
                                <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-journal-text me-1"></i> Read Official Meta Guide</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- /cloudapi -->

    </main>
</div>



<?php include __DIR__ . '/../includes/footer.php'; ?>

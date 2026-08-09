<?php
$current_page = 'social-media/accounts.php';
require_once __DIR__ . '/../../config/DbConnection.php';
include_once __DIR__ . '/../admin_header.php';

require_once __DIR__ . '/adapters/PlatformAdapterInterface.php';
require_once __DIR__ . '/adapters/FacebookAdapter.php';
require_once __DIR__ . '/adapters/InstagramAdapter.php';
require_once __DIR__ . '/adapters/TwitterAdapter.php';
require_once __DIR__ . '/adapters/LinkedInAdapter.php';
require_once __DIR__ . '/adapters/TelegramAdapter.php';
require_once __DIR__ . '/adapters/PinterestAdapter.php';

$pdo = DbConnection::getInstance();

// Generate OAuth State token
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}
$csrfState = $_SESSION['oauth_state'];
$csrfToken = csrf_token();

// Auto-migrate tables if not created yet (e.g. on live Hostinger production server)
try {
    $pdo->query("SELECT 1 FROM sm_connected_accounts LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    ob_start();
    runMigration();
    ob_end_clean();
}

// Direct Disconnect Handler
if (isset($_GET['action']) && $_GET['action'] === 'disconnect') {
    $discId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    $discPlatform = trim($_GET['platform'] ?? '');

    if (!empty($discPlatform)) {
        $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = LOWER(?)");
        $stmt->execute([$discPlatform]);
    }
    if ($discId) {
        $stmtFetch = $pdo->prepare("SELECT platform FROM sm_connected_accounts WHERE id = ?");
        $stmtFetch->execute([$discId]);
        $plat = $stmtFetch->fetchColumn();
        if ($plat) {
            $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE LOWER(platform) = LOWER(?)");
            $stmt->execute([$plat]);
        }
        $stmt = $pdo->prepare("UPDATE sm_connected_accounts SET is_active = 0 WHERE id = ?");
        $stmt->execute([$discId]);
    }

    set_flash('success', 'Account disconnected successfully.');
    header('Location: ' . SITE_URL . '/admin/social-media/accounts.php');
    exit;
}

// Fetch all connected accounts
$stmt = $pdo->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1");
$dbAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$connectedMap = [];
foreach ($dbAccounts as $acc) {
    $connectedMap[strtolower($acc['platform'])] = $acc;
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $protocol = 'https';
}
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];

// Define platforms and their configuration checks
$platformsConfig = [
    'facebook' => [
        'name' => 'Facebook',
        'icon' => 'fab fa-facebook',
        'color' => '#1877F2',
        'adapter' => new FacebookAdapter(),
        'callback' => $baseUrl . '/admin/social-media/oauth/facebook_callback.php',
        'has_keys' => !empty(_env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID')) && !empty(_env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET')),
        'keys_needed' => 'FB_APP_ID & FB_APP_SECRET'
    ],
    'instagram' => [
        'name' => 'Instagram',
        'icon' => 'fab fa-instagram',
        'color' => '#E4405F',
        'adapter' => new InstagramAdapter(),
        'callback' => $baseUrl . '/admin/social-media/oauth/facebook_callback.php',
        'has_keys' => !empty(_env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID')) && !empty(_env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET')),
        'keys_needed' => 'FB_APP_ID & FB_APP_SECRET (Instagram uses Meta Graph API)'
    ],
    'twitter' => [
        'name' => 'X (Twitter)',
        'icon' => 'fab fa-x-twitter',
        'color' => '#000000',
        'adapter' => new TwitterAdapter(),
        'callback' => $baseUrl . '/admin/social-media/oauth/twitter_callback.php',
        'has_keys' => !empty(_env('TWITTER_CLIENT_ID')) && !empty(_env('TWITTER_CLIENT_SECRET')),
        'keys_needed' => 'TWITTER_CLIENT_ID & TWITTER_CLIENT_SECRET'
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'icon' => 'fab fa-linkedin',
        'color' => '#0A66C2',
        'adapter' => new LinkedInAdapter(),
        'callback' => $baseUrl . '/admin/social-media/oauth/linkedin_callback.php',
        'has_keys' => !empty(_env('LINKEDIN_CLIENT_ID')) && !empty(_env('LINKEDIN_CLIENT_SECRET')),
        'keys_needed' => 'LINKEDIN_CLIENT_ID & LINKEDIN_CLIENT_SECRET'
    ],
    'telegram' => [
        'name' => 'Telegram',
        'icon' => 'fab fa-telegram',
        'color' => '#0088CC',
        'adapter' => new TelegramAdapter(),
        'callback' => '',
        'has_keys' => true, // Telegram doesn't require .env keys, bot token entered in UI modal
        'keys_needed' => ''
    ],
    'pinterest' => [
        'name' => 'Pinterest',
        'icon' => 'fab fa-pinterest',
        'color' => '#E60023',
        'adapter' => new PinterestAdapter(),
        'callback' => $baseUrl . '/admin/social-media/oauth/pinterest_callback.php',
        'has_keys' => !empty(_env('PINTEREST_APP_ID') ?: _env('PINTEREST_CLIENT_ID')) && !empty(_env('PINTEREST_APP_SECRET') ?: _env('PINTEREST_CLIENT_SECRET')),
        'keys_needed' => 'PINTEREST_APP_ID & PINTEREST_APP_SECRET'
    ]
];
?>
<link href="<?php echo SITE_URL; ?>/admin/social-media/assets/social-media.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold m-0">Connected Accounts</h2>
            <p class="text-muted small m-0">Connect and manage official social media API accounts for automated posting.</p>
        </div>
    </div>

    <!-- Alert Message Banner -->
    <div id="alertBox"></div>

    <div class="row">
        <?php foreach ($platformsConfig as $key => $p): 
            $isConnected = isset($connectedMap[$key]);
            $accInfo = $isConnected ? $connectedMap[$key] : null;
            $adapter = $p['adapter'];
            $authUrl = ($p['has_keys'] && $adapter->requiresOAuth()) ? $adapter->getAuthUrl($p['callback'], $csrfState) : '';
        ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card shadow h-100" style="border-radius: 15px; border: none; border-top: 5px solid <?php echo $p['color']; ?>;">
                <div class="card-body text-center p-4 d-flex flex-column justify-content-between">
                    <div>
                        <i class="<?php echo $p['icon']; ?> fa-4x mb-3" style="color: <?php echo $p['color']; ?>;"></i>
                        <h4 class="card-title fw-bold mb-2"><?php echo $p['name']; ?></h4>
                        
                        <div class="mb-3">
                            <?php 
                            $hasRealToken = !empty($accInfo['access_token_encrypted']);
                            if ($isConnected && $hasRealToken): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2">
                                    <i class="fas fa-check-circle me-1"></i> API Connected
                                </span>
                            <?php elseif ($isConnected): ?>
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Token Needed
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">Disconnected</span>
                            <?php endif; ?>
                            <?php if (!empty($accInfo['account_name'])): ?>
                                <div class="small text-muted mt-2 fw-semibold">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($accInfo['account_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <?php if ($isConnected): ?>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-primary btn-sm flex-fill rounded-pill btn-test-conn" 
                                        data-id="<?php echo $accInfo['id']; ?>">
                                    <i class="fas fa-vial me-1"></i> Test API
                                </button>
                                <?php if (in_array($key, ['facebook', 'pinterest', 'instagram', 'linkedin'])): ?>
                                    <button class="btn btn-primary btn-sm flex-fill rounded-pill" 
                                            data-mdb-toggle="modal" data-mdb-target="#<?php echo $key; ?>Modal" data-bs-toggle="modal" data-bs-target="#<?php echo $key; ?>Modal">
                                        <i class="fas fa-key me-1"></i> Edit Token
                                    </button>
                                <?php endif; ?>
                                <a href="accounts.php?action=disconnect&id=<?php echo $accInfo['id']; ?>&platform=<?php echo $key; ?>" 
                                   class="btn btn-outline-danger btn-sm flex-fill rounded-pill" 
                                   onclick="return confirm('Are you sure you want to disconnect <?php echo htmlspecialchars($p['name']); ?>?');">
                                    <i class="fas fa-unlink me-1"></i> Disconnect
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if ($key === 'telegram'): ?>
                                <button class="btn text-white rounded-pill shadow-sm" 
                                        style="background-color: <?php echo $p['color']; ?>;" 
                                        data-mdb-toggle="modal" data-mdb-target="#telegramModal" data-bs-toggle="modal" data-bs-target="#telegramModal">
                                    <i class="fas fa-plug me-2"></i> Connect Telegram
                                </button>
                            <?php elseif ($key === 'facebook'): ?>
                                <button class="btn text-white rounded-pill shadow-sm" 
                                        style="background-color: <?php echo $p['color']; ?>;" 
                                        data-mdb-toggle="modal" data-mdb-target="#facebookModal" data-bs-toggle="modal" data-bs-target="#facebookModal">
                                    <i class="fas fa-plug me-2"></i> Connect Facebook Page Token
                                </button>
                            <?php elseif ($key === 'pinterest'): ?>
                                <button class="btn text-white rounded-pill shadow-sm" 
                                        style="background-color: <?php echo $p['color']; ?>;" 
                                        data-mdb-toggle="modal" data-mdb-target="#pinterestModal" data-bs-toggle="modal" data-bs-target="#pinterestModal">
                                    <i class="fas fa-plug me-2"></i> Connect Pinterest Access Token
                                </button>
                            <?php elseif ($p['has_keys']): ?>
                                <a href="<?php echo htmlspecialchars($authUrl); ?>" 
                                   class="btn text-white rounded-pill shadow-sm" 
                                   style="background-color: <?php echo $p['color']; ?>;">
                                    <i class="fas fa-plug me-2"></i> Connect <?php echo $p['name']; ?>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-light border text-muted rounded-pill shadow-sm btn-missing-keys" 
                                        data-keys="<?php echo htmlspecialchars($p['keys_needed']); ?>" 
                                        data-platform="<?php echo htmlspecialchars($p['name']); ?>">
                                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Configure Credentials
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Facebook Connection Modal -->
<div class="modal fade" id="facebookModal" tabindex="-1" aria-labelledby="facebookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="facebookModalLabel">
                    <i class="fab fa-facebook text-primary me-2 fs-3"></i>Connect Facebook Page Access Token
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="facebookForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_facebook">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Facebook Page Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control rounded-3" 
                               placeholder="e.g. Sagar Starter's Official Page" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Facebook Page ID <span class="text-danger">*</span></label>
                        <input type="text" name="page_id" class="form-control rounded-3" 
                               placeholder="e.g. 104829384910234" required>
                        <div class="form-text">Find your Page ID under Facebook Page Settings ➔ About / Page Information.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Page Access Token <span class="text-danger">*</span></label>
                        <textarea name="access_token" class="form-control rounded-3" rows="3" 
                                  placeholder="e.g. EAAB..." required></textarea>
                        <div class="form-text">Generate a Page Access Token from Meta Graph API Explorer or Facebook Developer App.</div>
                    </div>

                    <div id="facebookAlert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSaveFacebook">
                        <i class="fas fa-save me-1"></i> Save & Connect Facebook
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pinterest Connection Modal -->
<div class="modal fade" id="pinterestModal" tabindex="-1" aria-labelledby="pinterestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pinterestModalLabel">
                    <i class="fab fa-pinterest text-danger me-2 fs-3"></i>Connect Pinterest Access Token
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="pinterestForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_pinterest">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pinterest Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control rounded-3" 
                               placeholder="e.g. @sagarstarters" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Board ID <span class="text-muted">(Optional - Auto-resolved if blank)</span></label>
                        <input type="text" name="board_id" class="form-control rounded-3" 
                               placeholder="e.g. 112233445566778899">
                        <div class="form-text">If left empty, system will automatically select your first Board or auto-create "Sagar Starters Products".</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pinterest Access Token <span class="text-danger">*</span></label>
                        <textarea name="access_token" class="form-control rounded-3" rows="3" 
                                  placeholder="e.g. pina_..." required></textarea>
                        <div class="form-text">Generate an Access Token from Pinterest Developer Portal or OAuth flow.</div>
                    </div>

                    <div id="pinterestAlert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4" id="btnSavePinterest">
                        <i class="fas fa-save me-1"></i> Save & Connect Pinterest
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Instagram Connection Modal -->
<div class="modal fade" id="instagramModal" tabindex="-1" aria-labelledby="instagramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="instagramModalLabel">
                    <i class="fab fa-instagram text-danger me-2 fs-3"></i>Connect Instagram Access Token
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="instagramForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_instagram">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Instagram Handle / Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control rounded-3" 
                               placeholder="e.g. @sagarstarter" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Instagram Business Account ID <span class="text-danger">*</span></label>
                        <input type="text" name="ig_user_id" class="form-control rounded-3" 
                               placeholder="e.g. 17841400000000000" required>
                        <div class="form-text">Find your Instagram Business Account ID in Meta Graph API Explorer or Facebook Page Settings.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Instagram Access Token <span class="text-danger">*</span></label>
                        <textarea name="access_token" class="form-control rounded-3" rows="3" 
                                  placeholder="e.g. EAAB..." required></textarea>
                    </div>

                    <div id="instagramAlert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4" id="btnSaveInstagram">
                        <i class="fas fa-save me-1"></i> Save & Connect Instagram
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- LinkedIn Connection Modal -->
<div class="modal fade" id="linkedinModal" tabindex="-1" aria-labelledby="linkedinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="linkedinModalLabel">
                    <i class="fab fa-linkedin text-primary me-2 fs-3"></i>Connect LinkedIn Access Token
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="linkedinForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_linkedin">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">LinkedIn Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control rounded-3" 
                               placeholder="e.g. SAGAR Starter's" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Person / Organization URN <span class="text-muted">(Optional)</span></label>
                        <input type="text" name="person_urn" class="form-control rounded-3" 
                               placeholder="e.g. urn:li:person:abc12345">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">LinkedIn Access Token <span class="text-danger">*</span></label>
                        <textarea name="access_token" class="form-control rounded-3" rows="3" 
                                  placeholder="e.g. AQX..." required></textarea>
                    </div>

                    <div id="linkedinAlert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-mdb-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSaveLinkedin">
                        <i class="fas fa-save me-1"></i> Save & Connect LinkedIn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Telegram Connection Modal -->
<div class="modal fade" id="telegramModal" tabindex="-1" aria-labelledby="telegramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="telegramModalLabel">
                    <i class="fab fa-telegram text-info me-2 fs-3"></i>Connect Telegram Channel
                </h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="telegramForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_telegram">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bot Token <span class="text-danger">*</span></label>
                        <input type="text" name="bot_token" class="form-control rounded-3" 
                               placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" required>
                        <div class="form-text">Get this from Telegram @BotFather when creating a new bot.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Channel ID or Username <span class="text-danger">*</span></label>
                        <input type="text" name="channel_id" class="form-control rounded-3" 
                               placeholder="e.g. @your_channel_name or -1001234567890" required>
                        <div class="form-text">Ensure your bot is added as an <strong>Administrator</strong> to this channel.</div>
                    </div>

                    <div id="telegramAlert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="btnSaveTelegram">
                        <i class="fas fa-save me-1"></i> Save & Connect
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Missing Keys Information Modal -->
<div class="modal fade" id="keysModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="keysModalTitle">API Credentials Required</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p>To connect <strong id="keysPlatformName">Platform</strong> using official API, you need to add your developer credentials to the <code>.env</code> file in your project root.</p>
                <div class="bg-light p-3 rounded-3 font-monospace small mb-3 border">
                    <span id="keysNeededText">KEY=value</span>
                </div>
                <p class="small text-muted mb-0">After editing your <code>.env</code> file, refresh this page to enable the Connect button.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill" data-mdb-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>

<style>
.card { transition: all 0.3s ease; }
.card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';

    // Show Missing Keys Modal
    document.querySelectorAll('.btn-missing-keys').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('keysPlatformName').textContent = this.dataset.platform;
            document.getElementById('keysNeededText').textContent = '# Add to .env:\n' + this.dataset.keys;
            const keysModal = new mdb.Modal(document.getElementById('keysModal'));
            keysModal.show();
        });
    });

    // Save Telegram Form AJAX
    const telegramForm = document.getElementById('telegramForm');
    if (telegramForm) {
        telegramForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveTelegram');
            const alertDiv = document.getElementById('telegramAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
            alertDiv.innerHTML = '';

            const formData = new FormData(this);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect';
                
                if (data.success) {
                    alertDiv.innerHTML = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> Telegram connected successfully! Reloading...</div>';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Connection failed'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect';
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Network error: ${err.message}</div>`;
            });
        });
    }

    // Save Facebook Form AJAX
    const facebookForm = document.getElementById('facebookForm');
    if (facebookForm) {
        facebookForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveFacebook');
            const alertDiv = document.getElementById('facebookAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
            alertDiv.innerHTML = '';

            const formData = new FormData(this);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Facebook';
                
                if (data.success) {
                    alertDiv.innerHTML = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> Facebook Page Access Token saved! Reloading...</div>';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Connection failed'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Facebook';
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Network error: ${err.message}</div>`;
            });
        });
    }

    // Save Pinterest Form AJAX
    const pinterestForm = document.getElementById('pinterestForm');
    if (pinterestForm) {
        pinterestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSavePinterest');
            const alertDiv = document.getElementById('pinterestAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
            alertDiv.innerHTML = '';

            const formData = new FormData(this);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Pinterest';
                
                if (data.success) {
                    alertDiv.innerHTML = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> Pinterest Access Token saved! Reloading...</div>';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Connection failed'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Pinterest';
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Network error: ${err.message}</div>`;
            });
        });
    }

    // Save Instagram Form AJAX
    const instagramForm = document.getElementById('instagramForm');
    if (instagramForm) {
        instagramForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveInstagram');
            const alertDiv = document.getElementById('instagramAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
            alertDiv.innerHTML = '';

            const formData = new FormData(this);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Instagram';
                
                if (data.success) {
                    alertDiv.innerHTML = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> Instagram Access Token saved! Reloading...</div>';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Connection failed'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect Instagram';
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Network error: ${err.message}</div>`;
            });
        });
    }

    // Save LinkedIn Form AJAX
    const linkedinForm = document.getElementById('linkedinForm');
    if (linkedinForm) {
        linkedinForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveLinkedin');
            const alertDiv = document.getElementById('linkedinAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
            alertDiv.innerHTML = '';

            const formData = new FormData(this);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect LinkedIn';
                
                if (data.success) {
                    alertDiv.innerHTML = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle me-1"></i> LinkedIn Access Token saved! Reloading...</div>';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> ${data.error || 'Connection failed'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save & Connect LinkedIn';
                alertDiv.innerHTML = `<div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle me-1"></i> Network error: ${err.message}</div>`;
            });
        });
    }

    // Disconnect Account AJAX
    document.querySelectorAll('.btn-disconnect-acc').forEach(btn => {
        btn.addEventListener('click', function() {
            const accId = this.dataset.id;
            const name = this.dataset.name;
            if (!confirm(`Are you sure you want to disconnect ${name}?`)) return;

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('action', 'disconnect');
            formData.append('account_id', accId);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'accounts.php';
                } else {
                    alert('Failed to disconnect: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                alert('Error disconnecting account: ' + err.message);
            });
        });
    });

    // Test Connection AJAX
    document.querySelectorAll('.btn-test-conn').forEach(btn => {
        btn.addEventListener('click', function() {
            const accId = this.dataset.id;
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('action', 'test');
            formData.append('account_id', accId);

            fetch('ajax/ajax_account_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.disabled = false;
                this.innerHTML = originalText;
                if (data.success) {
                    alert('✅ Connection Test Successful!');
                } else {
                    alert('❌ Connection Test Failed: ' + (data.error || 'Invalid credentials'));
                }
            })
            .catch(err => {
                this.disabled = false;
                this.innerHTML = originalText;
                alert('❌ Error testing connection: ' + err.message);
            });
        });
    });
});
</script>

<?php include_once __DIR__ . '/../admin_footer.php'; ?>
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
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Auto-migrate tables if not created yet (e.g. on live Hostinger production server)
try {
    $pdo->query("SELECT 1 FROM sm_connected_accounts LIMIT 1");
} catch (PDOException $e) {
    require_once __DIR__ . '/migrations/001_create_social_media_tables.php';
    runMigration();
}

// Fetch all connected accounts
$stmt = $pdo->query("SELECT * FROM sm_connected_accounts WHERE is_active = 1");
$dbAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$connectedMap = [];
foreach ($dbAccounts as $acc) {
    $connectedMap[strtolower($acc['platform'])] = $acc;
}

// Define platforms and their configuration checks
$platformsConfig = [
    'facebook' => [
        'name' => 'Facebook',
        'icon' => 'fab fa-facebook',
        'color' => '#1877F2',
        'adapter' => new FacebookAdapter(),
        'callback' => SITE_URL . '/admin/social-media/oauth/facebook_callback.php',
        'has_keys' => !empty(_env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID')) && !empty(_env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET')),
        'keys_needed' => 'FB_APP_ID & FB_APP_SECRET'
    ],
    'instagram' => [
        'name' => 'Instagram',
        'icon' => 'fab fa-instagram',
        'color' => '#E4405F',
        'adapter' => new InstagramAdapter(),
        'callback' => SITE_URL . '/admin/social-media/oauth/facebook_callback.php',
        'has_keys' => !empty(_env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID')) && !empty(_env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET')),
        'keys_needed' => 'FB_APP_ID & FB_APP_SECRET (Instagram uses Meta Graph API)'
    ],
    'twitter' => [
        'name' => 'X (Twitter)',
        'icon' => 'fab fa-x-twitter',
        'color' => '#000000',
        'adapter' => new TwitterAdapter(),
        'callback' => SITE_URL . '/admin/social-media/oauth/twitter_callback.php',
        'has_keys' => !empty(_env('TWITTER_CLIENT_ID')) && !empty(_env('TWITTER_CLIENT_SECRET')),
        'keys_needed' => 'TWITTER_CLIENT_ID & TWITTER_CLIENT_SECRET'
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'icon' => 'fab fa-linkedin',
        'color' => '#0A66C2',
        'adapter' => new LinkedInAdapter(),
        'callback' => SITE_URL . '/admin/social-media/oauth/linkedin_callback.php',
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
        'callback' => SITE_URL . '/admin/social-media/oauth/pinterest_callback.php',
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
                            <?php if ($isConnected): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2">
                                    <i class="fas fa-check-circle me-1"></i> Connected
                                </span>
                                <?php if (!empty($accInfo['account_name'])): ?>
                                    <div class="small text-muted mt-2 fw-semibold">
                                        <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($accInfo['account_name']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">Disconnected</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <?php if ($isConnected): ?>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm flex-fill rounded-pill btn-test-conn" 
                                        data-id="<?php echo $accInfo['id']; ?>">
                                    <i class="fas fa-vial me-1"></i> Test
                                </button>
                                <button class="btn btn-outline-danger btn-sm flex-fill rounded-pill btn-disconnect-acc" 
                                        data-id="<?php echo $accInfo['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                    <i class="fas fa-unlink me-1"></i> Disconnect
                                </button>
                            </div>
                        <?php else: ?>
                            <?php if ($key === 'telegram'): ?>
                                <button class="btn text-white rounded-pill shadow-sm" 
                                        style="background-color: <?php echo $p['color']; ?>;" 
                                        data-mdb-toggle="modal" data-mdb-target="#telegramModal">
                                    <i class="fas fa-plug me-2"></i> Connect Telegram
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
                    window.location.reload();
                } else {
                    alert('Failed to disconnect: ' + (data.error || 'Unknown error'));
                }
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
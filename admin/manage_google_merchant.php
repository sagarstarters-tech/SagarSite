<?php
/**
 * Google Merchant Center Connection & Feed Management
 * Location: /admin/manage_google_merchant.php
 */
include 'admin_header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gmc_settings'])) {
    $gmc_enabled           = isset($_POST['gmc_enabled']) ? '1' : '0';
    $gmc_merchant_id       = trim($_POST['gmc_merchant_id'] ?? '');
    $gmc_verification_meta = trim($_POST['gmc_verification_meta'] ?? '');
    $gmc_default_brand     = trim($_POST['gmc_default_brand'] ?? '');
    $gmc_condition         = trim($_POST['gmc_condition'] ?? 'new');
    $gmc_country           = trim($_POST['gmc_country'] ?? 'IN');
    $gmc_currency          = trim($_POST['gmc_currency'] ?? 'INR');

    // Also update google_verification in scripts if user provided meta tag content
    if (!empty($gmc_verification_meta)) {
        // extract content="..." if user pasted full <meta ...> tag
        if (preg_match('/content=["\']([^"\']+)["\']/i', $gmc_verification_meta, $matches)) {
            $clean_meta = $matches[1];
        } else {
            $clean_meta = $gmc_verification_meta;
        }
        $stmt = $conn->prepare("INSERT INTO scripts (script_key, script_value) VALUES ('google_verification', ?) ON DUPLICATE KEY UPDATE script_value = VALUES(script_value)");
        if ($stmt) {
            $stmt->bind_param("s", $clean_meta);
            $stmt->execute();
            $stmt->close();
        }
    }

    $settings_to_update = [
        'gmc_enabled'           => $gmc_enabled,
        'gmc_merchant_id'       => $gmc_merchant_id,
        'gmc_verification_meta' => $gmc_verification_meta,
        'gmc_default_brand'     => $gmc_default_brand,
        'gmc_condition'         => $gmc_condition,
        'gmc_country'           => $gmc_country,
        'gmc_currency'          => $gmc_currency,
    ];

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        foreach ($settings_to_update as $key => $val) {
            $stmt->bind_param("ss", $key, $val);
            $stmt->execute();
        }
        $stmt->close();
        
        // Refresh global_settings array
        $global_settings['gmc_enabled']           = $gmc_enabled;
        $global_settings['gmc_merchant_id']       = $gmc_merchant_id;
        $global_settings['gmc_verification_meta'] = $gmc_verification_meta;
        $global_settings['gmc_default_brand']     = $gmc_default_brand;
        $global_settings['gmc_condition']         = $gmc_condition;
        $global_settings['gmc_country']           = $gmc_country;
        $global_settings['gmc_currency']          = $gmc_currency;

        $success = "Google Merchant Center settings updated successfully.";
    } else {
        $error = "Database error saving settings: " . $conn->error;
    }
}

// Read current settings
$gmc_enabled           = $global_settings['gmc_enabled'] ?? '1';
$gmc_merchant_id       = $global_settings['gmc_merchant_id'] ?? '';
$gmc_verification_meta = $global_settings['gmc_verification_meta'] ?? '';
$gmc_default_brand     = $global_settings['gmc_default_brand'] ?? ($global_settings['site_name'] ?? "Sagar Starter's");
$gmc_condition         = $global_settings['gmc_condition'] ?? 'new';
$gmc_country           = $global_settings['gmc_country'] ?? 'IN';
$gmc_currency          = $global_settings['gmc_currency'] ?? 'INR';

$site_url = rtrim(SITE_URL, '/');
$feed_url = $site_url . '/api/google_merchant_feed.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary mb-0">
                    <i class="fab fa-google me-2 text-danger"></i>Google Merchant Center Connection
                </h4>
                <a href="<?php echo htmlspecialchars($feed_url); ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="fas fa-rss me-1"></i> View Live Product Feed
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Feed Status Card -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <h5 class="fw-bold text-dark mb-2">
                                Google Shopping Product Feed (RSS 2.0 XML)
                            </h5>
                            <p class="text-muted small mb-2">
                                Submit this URL to your Google Merchant Center account under <strong>Products &gt; Feeds &gt; Scheduled Fetch</strong>.
                            </p>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fas fa-link"></i></span>
                                <input type="text" class="form-control bg-white font-monospace text-primary" id="gmcFeedUrl" value="<?php echo htmlspecialchars($feed_url); ?>" readonly>
                                <button class="btn btn-primary" type="button" onclick="copyFeedUrl()">
                                    <i class="fas fa-copy me-1"></i> Copy URL
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end border-start-md">
                            <div class="p-2">
                                <span class="badge <?php echo ($gmc_enabled === '1') ? 'bg-success' : 'bg-secondary'; ?> p-2 px-3 fs-6 mb-2 d-inline-block">
                                    <i class="fas fa-signal me-1"></i> Feed Status: <?php echo ($gmc_enabled === '1') ? 'ACTIVE' : 'DISABLED'; ?>
                                </span>
                                <div class="text-muted small">
                                    Format: <strong>Google Shopping XML (RSS 2.0)</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Merchant Center Configuration</h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <form method="POST" action="">
                                <?php echo csrf_input(); ?>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="gmc_enabled" name="gmc_enabled" value="1" <?php echo ($gmc_enabled === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold text-dark" for="gmc_enabled">Enable Google Merchant Center Feed</label>
                                    <div class="form-text">Allows Google Merchant Center crawlers to fetch product data from your website.</div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="gmc_merchant_id" class="form-label fw-semibold text-dark">Google Merchant Center ID</label>
                                        <input type="text" class="form-control" id="gmc_merchant_id" name="gmc_merchant_id" value="<?php echo htmlspecialchars($gmc_merchant_id); ?>" placeholder="e.g. 123456789">
                                        <div class="form-text">Your 9-digit or 10-digit Merchant Account ID from Google.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gmc_default_brand" class="form-label fw-semibold text-dark">Default Brand Name</label>
                                        <input type="text" class="form-control" id="gmc_default_brand" name="gmc_default_brand" value="<?php echo htmlspecialchars($gmc_default_brand); ?>" placeholder="Sagar Starter's">
                                        <div class="form-text">Used for products where individual brand is not specified.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="gmc_verification_meta" class="form-label fw-semibold text-dark">Google Site Verification Tag / Code</label>
                                    <input type="text" class="form-control" id="gmc_verification_meta" name="gmc_verification_meta" value="<?php echo htmlspecialchars($gmc_verification_meta); ?>" placeholder="e.g. abc123xyz_verification_string or full <meta ...> tag">
                                    <div class="form-text">Google Merchant Center site ownership verification code or HTML meta tag.</div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3 mb-md-0">
                                        <label for="gmc_condition" class="form-label fw-semibold text-dark">Default Condition</label>
                                        <select class="form-select" id="gmc_condition" name="gmc_condition">
                                            <option value="new" <?php echo ($gmc_condition === 'new') ? 'selected' : ''; ?>>New</option>
                                            <option value="refurbished" <?php echo ($gmc_condition === 'refurbished') ? 'selected' : ''; ?>>Refurbished</option>
                                            <option value="used" <?php echo ($gmc_condition === 'used') ? 'selected' : ''; ?>>Used</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3 mb-md-0">
                                        <label for="gmc_country" class="form-label fw-semibold text-dark">Target Country Code</label>
                                        <input type="text" class="form-control text-uppercase" id="gmc_country" name="gmc_country" value="<?php echo htmlspecialchars($gmc_country); ?>" placeholder="IN">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="gmc_currency" class="form-label fw-semibold text-dark">Currency Code</label>
                                        <input type="text" class="form-control text-uppercase" id="gmc_currency" name="gmc_currency" value="<?php echo htmlspecialchars($gmc_currency); ?>" placeholder="INR">
                                    </div>
                                </div>

                                <button type="submit" name="save_gmc_settings" class="btn btn-primary px-4 rounded-pill">
                                    <i class="fas fa-save me-2"></i>Save Connection Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Step-by-Step Instructions -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 rounded-3 mb-4 bg-primary text-white">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>How to Connect</h5>
                            <ol class="ps-3 mb-0 small opacity-90 lh-lg">
                                <li>Log in to your <strong>Google Merchant Center</strong> account at <a href="https://merchants.google.com" target="_blank" class="text-white text-decoration-underline fw-bold">merchants.google.com</a>.</li>
                                <li>Verify domain ownership using your <strong>Verification Meta Tag</strong> above.</li>
                                <li>In Google Merchant Center, go to <strong>Products &gt; Feeds</strong>.</li>
                                <li>Click the <strong>+ (Add Primary Feed)</strong> button.</li>
                                <li>Select your target Country (e.g. India) and Language.</li>
                                <li>Choose <strong>Scheduled Fetch</strong> as the setup method.</li>
                                <li>Paste your <strong>Product Feed URL</strong> copied from above.</li>
                                <li>Click <strong>Save &amp; Fetch</strong>. Google will now automatically sync all your website products!</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function copyFeedUrl() {
    var copyText = document.getElementById("gmcFeedUrl");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("Google Merchant Product Feed URL copied to clipboard!");
}
</script>

<?php include 'admin_footer.php'; ?>

<?php
/**
 * AbandonedCartService
 * Business logic for cart abandonment recovery.
 * Handles cart tracking, reminder scheduling, WhatsApp sending, and recovery.
 */

require_once __DIR__ . '/AbandonedCartRepository.php';

class AbandonedCartService {
    private $conn;
    private $repo;
    private $settings;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->repo = new AbandonedCartRepository($conn);
        $this->settings = $this->repo->getSettings();
    }

    /**
     * Track current user's cart for abandonment.
     * Called from sync_cart_to_db() — must be fast and fail-safe.
     */
    public function trackCart($userId) {
        if (($this->settings['is_enabled'] ?? '0') != '1') return;

        $userId = intval($userId);
        if ($userId <= 0) return;

        // Do not track admin users' carts — admins testing site should not appear in abandonment list
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return;

        // Get current cart from session
        $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

        // If cart is empty, remove any active abandoned cart
        if (empty($cart)) {
            $this->repo->removeActive($userId);
            return;
        }

        // Calculate cart total and product names
        $productIds = array_keys($cart);
        $ids = implode(',', array_map('intval', $productIds));
        if (empty($ids)) return;

        $res = $this->conn->query("SELECT id, name, price, image FROM products WHERE id IN ({$ids})");
        if (!$res) return;

        $total = 0;
        $names = [];
        $firstImage = null;

        while ($row = $res->fetch_assoc()) {
            $qty = intval($cart[$row['id']] ?? 0);
            if ($qty > 0) {
                $total += $row['price'] * $qty;
                $names[] = $row['name'] . ' x' . $qty;
                if (!$firstImage && !empty($row['image'])) {
                    $firstImage = $row['image'];
                }
            }
        }

        $productNames = implode(', ', $names);

        $this->repo->createOrUpdate($userId, $cart, $total, $productNames, $firstImage);

        // Fallback: trigger background auto-reminders check using a DB-based global timestamp.
        // This fires for ANY user visiting the site (not session-scoped), ensuring reminders
        // run even when the cron job doesn't execute or is late.
        $lastRunTs = 0;
        try {
            $res = $this->conn->query("SELECT setting_value FROM abandoned_cart_settings WHERE setting_key = 'last_auto_run' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $lastRunTs = intval($row['setting_value']);
            }
        } catch (\Throwable $e) {}

        if ((time() - $lastRunTs) > 300) { // 5 minutes throttle (global, not per-session)
            try {
                // Update timestamp first to prevent concurrent execution
                $this->conn->query("INSERT INTO abandoned_cart_settings (setting_key, setting_value) VALUES ('last_auto_run', '" . time() . "') ON DUPLICATE KEY UPDATE setting_value = '" . time() . "'");
                $this->processAutoReminders();
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Mark abandoned cart as converted when order is placed.
     */
    public function markRecovered($userId) {
        return $this->repo->markConverted(intval($userId));
    }

    /**
     * Process all due automatic reminders (called by cron).
     */
    public function processAutoReminders() {
        // Fix: explicit string comparison (DB returns strings, not booleans)
        if (($this->settings['is_enabled'] ?? '0') != '1') {
            return ['processed' => 0, 'errors' => 0, 'message' => 'Cart abandonment feature is disabled in settings'];
        }

        $processed    = 0;
        $errors       = 0;
        $errorDetails = [];
        $skipped      = 0;

        // Auto-expire old carts first
        $expireDays = intval($this->settings['auto_expire_days'] ?? 7);
        $this->repo->autoExpire($expireDays);
        error_log("[AbandonedCart] Auto-expired carts older than {$expireDays} days.");

        // Process each reminder level
        for ($level = 1; $level <= 4; $level++) {
            $delay    = intval($this->settings["reminder_{$level}_delay"] ?? 30);
            $dueCarts = $this->repo->getDueReminders($level, $delay);

            error_log("[AbandonedCart] Level {$level}: delay={$delay}min, due=" . count($dueCarts) . " carts");

            foreach ($dueCarts as $cart) {
                try {
                    $result = $this->sendReminder($cart['id'], $level);
                    if (!empty($result['success'])) {
                        $processed++;
                        error_log("[AbandonedCart] ✓ Cart #{$cart['id']} L{$level} sent to " . ($cart['customer_phone'] ?? 'unknown'));
                    } else {
                        $errors++;
                        $errMsg = $result['error'] ?? 'Unknown error';
                        $errorDetails[] = "Cart #{$cart['id']} L{$level}: " . $errMsg;
                        error_log("[AbandonedCart] ✗ Cart #{$cart['id']} L{$level} failed: " . $errMsg);
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $errorDetails[] = "Cart #{$cart['id']} L{$level}: " . $e->getMessage();
                    error_log("[AbandonedCart] Exception L{$level} cart #{$cart['id']}: " . $e->getMessage());
                }
            }
        }

        $msg = "Processed {$processed} reminders with {$errors} errors.";
        if (!empty($errorDetails)) {
            $msg .= " Details: " . implode(' | ', array_unique($errorDetails));
        }

        return [
            'processed'     => $processed,
            'errors'        => $errors,
            'error_details' => array_unique($errorDetails),
            'message'       => $msg
        ];
    }

    /**
     * Send a WhatsApp reminder for a specific abandoned cart.
     * @param int $cartId
     * @param int $level Reminder level (1-4)
     * @return bool
     */
    /**
     * Send a WhatsApp reminder for a specific abandoned cart.
     * @param int $cartId
     * @param int $level Reminder level (1-4). If 0, auto-detects first unsent level.
     * @param bool $isManual Whether this request was manually triggered by admin
     * @return array
     */
    public function sendReminder($cartId, $level = 0, $isManual = false, $overridePhone = '') {
        $cart = $this->repo->getById($cartId);
        if (!$cart) return ['success' => false, 'error' => 'Cart not found'];

        // Auto-detect level if not specified
        if ($level <= 0) {
            if (empty($cart['reminder_1_sent'])) $level = 1;
            elseif (empty($cart['reminder_2_sent'])) $level = 2;
            elseif (empty($cart['reminder_3_sent'])) $level = 3;
            elseif (empty($cart['reminder_4_sent'])) $level = 4;
            else return ['success' => false, 'error' => 'All 4 reminder stages have already been sent for this cart. Click the undo [ ↺ ] button to reset stages if you wish to re-send.'];
        }

        // Auto-generate recovery token if missing (for legacy database records)
        if (empty($cart['recovery_token'])) {
            $cart['recovery_token'] = bin2hex(random_bytes(32));
            $this->conn->query("UPDATE abandoned_carts SET recovery_token = '" . $this->conn->real_escape_string($cart['recovery_token']) . "' WHERE id = " . intval($cartId));
        }

        // Generate coupon for level 4
        $couponCode = '';
        $couponDiscount = 0;
        if ($level == 4) {
            $couponDiscount = floatval($this->settings['coupon_discount_percent'] ?? 10);
            $couponCode = $this->generateCouponCode($cartId);
            $this->repo->setCoupon($cartId, $couponCode, $couponDiscount);
        }

        // Build recovery link
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        if (strpos($siteUrl, 'http') !== 0) {
            // Fallback: try to build from settings table
            $res = $this->conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $siteUrl = rtrim($row['setting_value'], '/');
            }
        }
        $siteUrl = preg_replace('#/(admin|api|user|auth|cron)(/.*)?$#i', '', $siteUrl);
        if (empty($siteUrl)) {
            $siteUrl = 'https://www.sagarstarters.com';
        }
        $recoveryLink = $siteUrl . '/recover_cart.php?token=' . urlencode($cart['recovery_token']);

        // Build message from template
        $messageTemplate = $this->settings["reminder_{$level}_message"] ?? "Hi {CustomerName}, you left items in your cart. Complete your purchase: {RecoveryLink}";

        $variables = [
            '{CustomerName}'   => $cart['customer_name'] ?? 'Customer',
            '{ProductNames}'   => $cart['product_names'] ?? 'Your items',
            '{CartTotal}'      => number_format($cart['cart_total'], 2),
            '{RecoveryLink}'   => $recoveryLink,
            '{CouponCode}'     => $couponCode,
            '{CouponDiscount}' => $couponDiscount . '%',
        ];

        $message = str_replace(array_keys($variables), array_values($variables), $messageTemplate);

        $targetPhone = !empty($overridePhone) ? $overridePhone : ($cart['customer_phone'] ?? '');

        // Send via WhatsApp API or generate Web link
        $sent = $this->sendWhatsAppMessage($targetPhone, $message, $cartId, $level, $variables, $messageTemplate, $isManual);

        // ONLY mark as sent in database if it was actually delivered via Meta API
        if (!empty($sent['success']) && !empty($sent['is_sent'])) {
            $this->repo->markReminderSent($cartId, $level);
        }

        return $sent;
    }

    /**
     * Send manual reminder from admin panel.
     */
    public function sendManualReminder($cartId, $forceLevel = 0, $overridePhone = '') {
        return $this->sendReminder($cartId, $forceLevel, true, $overridePhone);
    }

    /**
     * Manually mark or unmark a specific reminder stage.
     */
    public function markReminderStageManual($cartId, $level, $action = 'mark_sent') {
        $cartId = intval($cartId);
        $level  = intval($level);
        if ($cartId <= 0 || !in_array($level, [1, 2, 3, 4])) {
            return false;
        }

        if ($action === 'mark_sent') {
            $result = $this->repo->markReminderSent($cartId, $level);
            if ($result) {
                $this->logWhatsApp($cartId, '', "Stage {$level} marked sent manually", 'admin', "Admin confirmed Stage {$level} was sent");
            }
            return $result;
        } else {
            $result = $this->repo->unmarkReminderSent($cartId, $level);
            if ($result) {
                $this->logWhatsApp($cartId, '', "Stage {$level} reset to unsent", 'admin', "Admin reset Stage {$level} to unsent");
            }
            return $result;
        }
    }

    /**
     * Reset all reminder timestamps for a cart back to 0 (NULL).
     */
    public function resetReminders($cartId) {
        $cartId = intval($cartId);
        $result = $this->repo->resetReminders($cartId);
        if ($result) {
            $this->logWhatsApp($cartId, '', 'Admin reset reminder stages', 'admin', 'Reset all reminder stages to 0');
        }
        return $result;
    }

    /**
     * Get compiled reminder preview and links for all 4 stages of a cart.
     */
    public function getCartRemindersPreview($cartId) {
        $cart = $this->repo->getById(intval($cartId));
        if (!$cart) return null;

        // Auto-generate recovery token if missing
        if (empty($cart['recovery_token'])) {
            $cart['recovery_token'] = bin2hex(random_bytes(32));
            $this->conn->query("UPDATE abandoned_carts SET recovery_token = '" . $this->conn->real_escape_string($cart['recovery_token']) . "' WHERE id = " . intval($cartId));
        }

        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://www.sagarstarters.com';
        $siteUrl = preg_replace('#/(admin|api|user|auth|cron)(/.*)?$#i', '', $siteUrl);
        if (empty($siteUrl)) $siteUrl = 'https://www.sagarstarters.com';
        $recoveryLink = $siteUrl . '/recover_cart.php?token=' . urlencode($cart['recovery_token']);

        // Clean phone
        $phone = $cart['customer_phone'] ?? '';
        $cleanNumber = preg_replace('/[^0-9]/', '', (string)$phone);
        $cleanNumber = ltrim($cleanNumber, '0');
        if (strlen($cleanNumber) == 10) $cleanNumber = '91' . $cleanNumber;

        $stages = [];
        for ($lvl = 1; $lvl <= 4; $lvl++) {
            $couponCode = ($lvl == 4) ? ($cart['coupon_code'] ?? 'RECOVER10') : '';
            $couponDiscount = ($lvl == 4) ? floatval($this->settings['coupon_discount_percent'] ?? 10) . '%' : '';

            $variables = [
                '{CustomerName}'   => $cart['customer_name'] ?? 'Customer',
                '{ProductNames}'   => $cart['product_names'] ?? 'Your items',
                '{CartTotal}'      => number_format($cart['cart_total'] ?? 0, 2),
                '{RecoveryLink}'   => $recoveryLink,
                '{CouponCode}'     => $couponCode,
                '{CouponDiscount}' => $couponDiscount,
            ];

            $tpl = $this->settings["reminder_{$lvl}_message"] ?? "Hi {CustomerName}, you left items in your cart: {RecoveryLink}";
            $msg = str_replace(array_keys($variables), array_values($variables), $tpl);
            $waLink = !empty($cleanNumber) ? ('https://wa.me/' . $cleanNumber . '?text=' . urlencode($msg)) : '';

            $sentAt = $cart["reminder_{$lvl}_sent"] ?? null;

            $stages[$lvl] = [
                'level'       => $lvl,
                'is_sent'     => !empty($sentAt),
                'sent_at'     => $sentAt,
                'message'     => $msg,
                'wa_link'     => $waLink,
                'meta_tpl'    => $this->settings["meta_template_{$lvl}"] ?? ''
            ];
        }

        return [
            'cart'   => $cart,
            'phone'  => $cleanNumber,
            'stages' => $stages
        ];
    }

    /**
     * Generate a unique coupon code for cart recovery.
     */
    private function generateCouponCode($cartId) {
        $prefix = 'RECOVER';
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return $prefix . $random;
    }

    /**
     * Send WhatsApp message using Meta Cloud API or generate WhatsApp Web redirect.
     */
    private function sendWhatsAppMessage($phone, $message, $cartId = 0, $level = 0, $variables = [], $messageTemplate = '', $isManual = false) {
        // Get WhatsApp settings
        $waSettings = null;
        try {
            $res = $this->conn->query("SELECT * FROM whatsapp_settings WHERE id = 1");
            if ($res && $res->num_rows > 0) {
                $waSettings = $res->fetch_assoc();
            }
        } catch (\Throwable $e) {
            error_log("[AbandonedCart] WhatsApp settings fetch error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error fetching WhatsApp settings'];
        }

        if (!$waSettings || ($waSettings['is_enabled'] ?? '0') != 1) {
            error_log("[AbandonedCart] WhatsApp is disabled or not configured");
            return ['success' => false, 'error' => 'WhatsApp Notifications are disabled in Admin -> WhatsApp Notifs Settings'];
        }

        // Clean phone number (supports Indian and international formats)
        $cleanNumber = preg_replace('/[^0-9]/', '', (string)$phone);
        $cleanNumber = ltrim($cleanNumber, '0');
        if (strlen($cleanNumber) == 14 && substr($cleanNumber, 0, 4) === '9191') {
            $cleanNumber = substr($cleanNumber, 2);
        }
        if (strlen($cleanNumber) == 10) {
            $cleanNumber = '91' . $cleanNumber;
        }

        if (empty($cleanNumber)) {
            error_log("[AbandonedCart] Empty phone for cart #{$cartId}");
            return ['success' => false, 'error' => 'Customer phone number is empty or invalid'];
        }

        $waLink = 'https://wa.me/' . $cleanNumber . '?text=' . urlencode($message);

        // ── Check if recipient is the business sender number ─────────
        $cleanSender = preg_replace('/[^0-9]/', '', (string)($waSettings['sender_number'] ?? ''));
        if (strpos($cleanSender, '0') === 0) $cleanSender = ltrim($cleanSender, '0');
        if (strlen($cleanSender) == 10) $cleanSender = '91' . $cleanSender;

        if (!empty($cleanSender) && $cleanNumber === $cleanSender) {
            $err = "Recipient phone (+{$cleanNumber}) is the same as your WhatsApp Business sender number (+{$cleanSender}). Meta does NOT deliver automated WhatsApp messages from a business number to itself. Please test with a different customer phone number.";
            $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', "Warning: " . $err);
            return [
                'success' => false,
                'mode'    => 'api',
                'is_sent' => false,
                'error'   => $err,
                'link'    => $waLink
            ];
        }

        // ── 1. API Mode (Meta Cloud API) ──────────────────────────────
        if (($waSettings['sending_mode'] ?? 'web') === 'api') {
            if (empty($waSettings['api_token']) || empty($waSettings['phone_number_id'])) {
                return [
                    'success' => false,
                    'error'   => 'Meta API Token or Phone Number ID is missing in WhatsApp Notifs Settings.',
                    'link'    => $waLink
                ];
            }

            $token = trim($waSettings['api_token']);
            $phoneId = trim($waSettings['phone_number_id']);
            $wabaId = trim($waSettings['waba_id'] ?? '');
            $url = "https://graph.facebook.com/v21.0/{$phoneId}/messages";

            // Check if cart abandonment template is configured for this level
            $tplLevel = $level > 0 ? $level : 1;
            $abandonTemplate = trim($this->settings["meta_template_{$tplLevel}"] ?? '');

            // Fallback: check Level 1 template
            if (empty($abandonTemplate) && !empty($this->settings['meta_template_1'])) {
                $abandonTemplate = trim($this->settings['meta_template_1']);
            }

            // Fallback: check global WhatsApp settings templates
            if (empty($abandonTemplate)) {
                if (!empty($waSettings['meta_template_name'])) {
                    $abandonTemplate = trim($waSettings['meta_template_name']);
                } elseif (!empty($waSettings['order_confirmation_template_name'])) {
                    $abandonTemplate = trim($waSettings['order_confirmation_template_name']);
                }
            }

            if (empty($abandonTemplate)) {
                $err = "Meta Template is not configured for Stage {$tplLevel}. Please configure an approved Meta Template in Cart Templates.";
                $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', "Skipped: " . $err);
                return [
                    'success' => false,
                    'mode'    => 'api',
                    'is_sent' => false,
                    'error'   => $err,
                    'link'    => $waLink
                ];
            }

            // Auto-discover WABA ID if missing
            if (empty($wabaId)) {
                $chW = curl_init("https://graph.facebook.com/v21.0/{$phoneId}?fields=whatsapp_business_account_id");
                curl_setopt_array($chW, [
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 8,
                ]);
                $wRes = curl_exec($chW);
                curl_close($chW);
                $wJson = json_decode($wRes, true);
                if (!empty($wJson['whatsapp_business_account_id'])) {
                    $wabaId = $wJson['whatsapp_business_account_id'];
                    $this->conn->query("UPDATE whatsapp_settings SET waba_id = '" . $this->conn->real_escape_string($wabaId) . "' WHERE id = 1");
                }
            }

            // Check registered phone number directly from Meta Phone ID to prevent self-messaging silent drops
            if (!empty($phoneId) && !empty($token)) {
                $chPhone = curl_init("https://graph.facebook.com/v21.0/{$phoneId}?fields=display_phone_number,whatsapp_business_account_id");
                curl_setopt_array($chPhone, [
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 6,
                ]);
                $pRes = curl_exec($chPhone);
                curl_close($chPhone);
                $pJson = json_decode($pRes, true);
                if (!empty($pJson['whatsapp_business_account_id']) && empty($wabaId)) {
                    $wabaId = $pJson['whatsapp_business_account_id'];
                    $this->conn->query("UPDATE whatsapp_settings SET waba_id = '" . $this->conn->real_escape_string($wabaId) . "' WHERE id = 1");
                }
                if (!empty($pJson['display_phone_number'])) {
                    $metaSenderDigits = preg_replace('/[^0-9]/', '', $pJson['display_phone_number']);
                    if (strlen($metaSenderDigits) == 10) $metaSenderDigits = '91' . $metaSenderDigits;
                    if (!empty($metaSenderDigits) && $cleanNumber === $metaSenderDigits) {
                        $err = "Self-Sending Blocked: The recipient phone (+{$cleanNumber}) is the EXACT same number as your WhatsApp Business sender SIM (+{$metaSenderDigits}). Meta Cloud API will NOT deliver messages from a business number to itself. Please test with a different customer mobile number (e.g. alternate or family phone).";
                        $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', "Warning: " . $err);
                        return [
                            'success' => false,
                            'mode'    => 'api',
                            'is_sent' => false,
                            'error'   => $err,
                            'link'    => $waLink
                        ];
                    }
                }
            }

            // Inspect template metadata live from Meta WABA
            $tplMeta = null;
            if (!empty($wabaId) && !empty($abandonTemplate)) {
                $chTpl = curl_init("https://graph.facebook.com/v21.0/{$wabaId}/message_templates?name=" . urlencode($abandonTemplate));
                curl_setopt_array($chTpl, [
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 8,
                ]);
                $tRes = curl_exec($chTpl);
                curl_close($chTpl);
                $tJson = json_decode($tRes, true);
                if (!empty($tJson['data']) && is_array($tJson['data'])) {
                    foreach ($tJson['data'] as $tItem) {
                        if (strcasecmp($tItem['name'], $abandonTemplate) === 0) {
                            if (($tItem['status'] ?? '') === 'APPROVED') {
                                $tplMeta = $tItem;
                                break;
                            } elseif (!$tplMeta) {
                                $tplMeta = $tItem;
                            }
                        }
                    }
                }
            }

            // If template was found in Meta, verify approval status
            if ($tplMeta) {
                $tplStatus = strtoupper($tplMeta['status'] ?? 'UNKNOWN');
                if ($tplStatus !== 'APPROVED') {
                    // Do not abort; clear abandonTemplate so cascade seamlessly falls back to 100% verified utility template 'order_confirmation'
                    $abandonTemplate = '';
                }
            }

            $ch_exec = function($pay) use ($url, $token) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POSTFIELDS     => json_encode($pay),
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $res  = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = curl_error($ch);
                curl_close($ch);
                return [$res, $code, $err];
            };

            $isMetaSuccess = false;
            $metaResponse  = null;
            $successfulTpl = $abandonTemplate;

            $langCode = trim($this->settings['meta_template_lang'] ?? 'en');
            if (empty($langCode)) $langCode = 'en';
            if ($tplMeta && !empty($tplMeta['language'])) {
                $langCode = $tplMeta['language'];
            }

            if ($abandonTemplate === 'hello_world') {
                $payload = [
                    "messaging_product" => "whatsapp",
                    "recipient_type"    => "individual",
                    "to"                => $cleanNumber,
                    "type"              => "template",
                    "template"          => [
                        "name"     => "hello_world",
                        "language" => ["code" => "en_US"]
                    ]
                ];
                list($result, $httpCode, $curlError) = $ch_exec($payload);
                $metaResponse = json_decode($result, true);
                $isMetaSuccess = ($httpCode == 200) && !empty($metaResponse['messages'][0]['id']) && empty($metaResponse['error']);
                $successfulTpl = 'hello_world';
            } else {
                // Prepare variable strings
                $pCustName   = (string)($variables['{CustomerName}'] ?? 'Customer');
                $pProdNames  = (string)($variables['{ProductNames}'] ?? 'Cart Items');
                $pCartTotal  = (string)($variables['{CartTotal}'] ?? '0.00');
                $pRecLink    = (string)($variables['{RecoveryLink}'] ?? '');
                $pStoreName  = "Sagar Starter's";
                $pDate       = date('d M Y');

                // Extract recovery token from recovery link
                $pTokenOnly = '';
                if (preg_match('/token=([^&]+)/', $pRecLink, $tm)) {
                    $pTokenOnly = urldecode($tm[1]);
                }

                $headerImgUrl = !empty($waSettings['wa_header_image_url']) 
                    ? $waSettings['wa_header_image_url'] 
                    : 'https://sagarstarters.com/assets/images/auth_banner.jpg';

                // Standard parameter representations
                $set_9_confirmation = [
                    ["type" => "text", "text" => $pCustName],           // {{1}} Customer Name
                    ["type" => "text", "text" => "Cart #" . $cartId],   // {{2}} Order/Cart ID
                    ["type" => "text", "text" => $pDate],               // {{3}} Date
                    ["type" => "text", "text" => $pCartTotal],          // {{4}} Amount
                    ["type" => "text", "text" => "Store Checkout"],     // {{5}} Payment Method
                    ["type" => "text", "text" => "Pending in Cart"],    // {{6}} Order Status
                    ["type" => "text", "text" => $pProdNames],          // {{7}} Items
                    ["type" => "text", "text" => "Online Checkout"],    // {{8}} Address
                    ["type" => "text", "text" => $pRecLink]             // {{9}} Order/Recovery Link
                ];

                $set_5_status = [
                    ["type" => "text", "text" => $pCustName],           // {{1}} Customer Name
                    ["type" => "text", "text" => "Cart #" . $cartId],   // {{2}} Cart ID
                    ["type" => "text", "text" => "Items in Cart"],      // {{3}} Order Status
                    ["type" => "text", "text" => ($pTokenOnly ?: 'RECOVER')], // {{4}} Tracking / Recovery Token
                    ["type" => "text", "text" => $pCartTotal]           // {{5}} Amount
                ];

                $set_6_status = [
                    ["type" => "text", "text" => $pCustName],           // {{1}} Customer Name
                    ["type" => "text", "text" => "Cart #" . $cartId],   // {{2}} Cart ID
                    ["type" => "text", "text" => "Items in Cart"],      // {{3}} Order Status
                    ["type" => "text", "text" => ($pTokenOnly ?: 'RECOVER')], // {{4}} Tracking / Recovery Token
                    ["type" => "text", "text" => $pCartTotal],          // {{5}} Amount
                    ["type" => "text", "text" => $pStoreName]           // {{6}} Store Name
                ];

                $set_4_simple = [
                    ["type" => "text", "text" => $pCustName],
                    ["type" => "text", "text" => $pProdNames],
                    ["type" => "text", "text" => $pCartTotal],
                    ["type" => "text", "text" => $pRecLink]
                ];

                // Build candidate template cascade in order of reliability
                $templatesToTry = array_unique(array_filter([
                    $abandonTemplate,
                    'order_confirmation',
                    'new_order_status',
                    'order_status_update'
                ]));

                $candidateLangs = array_unique([$langCode, ($langCode === 'en' ? 'en_US' : 'en')]);

                foreach ($templatesToTry as $currentTpl) {
                    $tryConfigs = [];

                    if ($currentTpl === 'order_confirmation') {
                        $tryConfigs[] = ['params' => $set_9_confirmation, 'header' => false, 'button' => false];
                    } elseif ($currentTpl === 'new_order_status') {
                        $tryConfigs[] = ['params' => $set_5_status, 'header' => false, 'button' => false];
                    } elseif ($currentTpl === 'order_status_update') {
                        $tryConfigs[] = ['params' => $set_6_status, 'header' => true, 'button' => false];
                        $tryConfigs[] = ['params' => $set_6_status, 'header' => false, 'button' => false];
                    } else {
                        // Custom template (e.g. reminder_1_gentle_nudge)
                        $tryConfigs[] = ['params' => $set_4_simple, 'header' => false, 'button' => false];
                        if (!empty($pTokenOnly)) {
                            $tryConfigs[] = ['params' => $set_4_simple, 'header' => false, 'button' => true];
                        }
                        $tryConfigs[] = ['params' => $set_5_status, 'header' => false, 'button' => false];
                        $tryConfigs[] = ['params' => $set_9_confirmation, 'header' => false, 'button' => false];
                    }

                    foreach ($candidateLangs as $cL) {
                        foreach ($tryConfigs as $cfg) {
                            $components = [];

                            if (!empty($cfg['header'])) {
                                $components[] = [
                                    "type" => "header",
                                    "parameters" => [
                                        [
                                            "type" => "image",
                                            "image" => ["link" => $headerImgUrl]
                                        ]
                                    ]
                                ];
                            }

                            $components[] = [
                                "type"       => "body",
                                "parameters" => $cfg['params']
                            ];

                            if (!empty($cfg['button']) && !empty($pTokenOnly)) {
                                $components[] = [
                                    "type"       => "button",
                                    "sub_type"   => "url",
                                    "index"      => "0",
                                    "parameters" => [
                                        [
                                            "type" => "text",
                                            "text" => $pTokenOnly
                                        ]
                                    ]
                                ];
                            }

                            $tplPayload = [
                                "messaging_product" => "whatsapp",
                                "recipient_type"    => "individual",
                                "to"                => $cleanNumber,
                                "type"              => "template",
                                "template"          => [
                                    "name"       => $currentTpl,
                                    "language"   => ["code" => $cL],
                                    "components" => $components
                                ]
                            ];

                            list($resTry, $codeTry, $errTry) = $ch_exec($tplPayload);
                            $respTry = json_decode($resTry, true);

                            $payload      = $tplPayload;
                            $result       = $resTry;
                            $httpCode     = $codeTry;
                            $curlError    = $errTry;
                            $metaResponse = $respTry;

                            if ($codeTry == 200 && !empty($respTry['messages'][0]['id']) && empty($respTry['error'])) {
                                $isMetaSuccess = true;
                                $successfulTpl = $currentTpl;
                                break 3; // Success! Break out of configs, langs, and templates!
                            }

                            // If template does not exist (132001), skip to next template immediately
                            $errCode = (int)($respTry['error']['code'] ?? 0);
                            if ($errCode === 132001) {
                                break 2;
                            }
                        }
                    }
                }
            }

            // Log API call to file
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logEntry = '[' . date('Y-m-d H:i:s') . "] Cart-Abandon Cart#{$cartId} L{$level} HTTP:{$httpCode} To:{$cleanNumber}" . PHP_EOL;
            $logEntry .= "Payload: " . json_encode($payload) . PHP_EOL;
            $logEntry .= "Response: " . $result . PHP_EOL;
            $logEntry .= str_repeat('-', 60) . PHP_EOL;
            @file_put_contents($logDir . '/cart_abandonment_whatsapp.log', $logEntry, FILE_APPEND);

            if ($curlError) {
                error_log("[AbandonedCart] cURL error cart #{$cartId}: {$curlError}");
                $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', "Failed: cURL - " . substr($curlError, 0, 80));
                return [
                    'success' => false,
                    'mode'    => 'api',
                    'error'   => "Network / cURL Error: " . $curlError,
                    'link'    => $waLink
                ];
            }

            if ($isMetaSuccess) {
                $msgId = $metaResponse['messages'][0]['id'] ?? 'unknown';
                $tplCat = !empty($tplMeta['category']) ? strtoupper($tplMeta['category']) : '';
                $statusMsg = "Sent via Meta Template '{$successfulTpl}' (ID: " . substr($msgId, 0, 30) . ')';
                $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', $statusMsg);
                return [
                    'success'    => true,
                    'mode'       => 'api',
                    'is_sent'    => true,
                    'level'      => $level,
                    'message_id' => $msgId,
                    'link'       => $waLink,
                    'category'   => $tplCat,
                    'template'   => $successfulTpl,
                    'message'    => "Reminder Level {$level} sent successfully via Meta Template '{$successfulTpl}'!"
                ];
            } else {
                $errMsg = $metaResponse['error']['message'] ?? 'Meta API error occurred';
                $errCode = $metaResponse['error']['code'] ?? $httpCode;
                $statusMsg = "Failed API (Code {$errCode}): " . substr($errMsg, 0, 120);
                $this->logWhatsApp($cartId, $cleanNumber, $message, 'api', $statusMsg);
                return [
                    'success' => false,
                    'mode'    => 'api',
                    'is_sent' => false,
                    'level'   => $level,
                    'error'   => "Meta Template '{$abandonTemplate}' Error (#{$errCode}): {$errMsg}",
                    'link'    => $waLink
                ];
            }
        }

        // ── 2. Web Mode (Manual wa.me Link Generation) ─────────────────
        $this->logWhatsApp($cartId, $cleanNumber, $message, 'web', 'Generated wa.me link for Stage ' . $level);
        error_log("[AbandonedCart] Web mode link generated for cart #{$cartId}: {$waLink}");

        if ($isManual) {
            // Web mode link prepared for admin to send via WhatsApp Web
            return [
                'success' => true,
                'mode'    => 'web',
                'is_sent' => false,
                'link'    => $waLink,
                'phone'   => $cleanNumber,
                'message' => $message,
                'level'   => $level,
                'info'    => 'WhatsApp Web link generated. Click Send in WhatsApp to deliver the message to the customer.'
            ];
        }

        // Auto reminders require API mode
        return [
            'success' => false,
            'mode'    => 'web',
            'is_sent' => false,
            'error'   => 'Automated background reminders require Meta API mode in WhatsApp Notifs Settings. Web Mode only creates manual wa.me links.',
            'link'    => $waLink
        ];
    }

    /**
     * Log WhatsApp message to abandoned_cart_logs table.
     */
    private function logWhatsApp($cartId, $phone, $message, $mode, $status) {
        try {
            // Ensure log table exists
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_cart_wa_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cart_id INT NOT NULL,
                customer_number VARCHAR(20),
                message TEXT,
                sending_mode VARCHAR(10),
                status TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cart_id (cart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $this->conn->prepare("INSERT INTO abandoned_cart_wa_logs (cart_id, customer_number, message, sending_mode, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $cartId, $phone, $message, $mode, $status);
                $stmt->execute();
                $stmt->close();
            }
        } catch (\Throwable $e) {
            error_log("[AbandonedCart] Log error: " . $e->getMessage());
        }
    }

    /**
     * Get settings for admin panel.
     */
    public function getSettings() {
        return $this->settings;
    }

    /**
     * Save settings from admin panel.
     */
    public function saveSettings($data) {
        return $this->repo->updateSettings($data);
    }

    /**
     * Get WhatsApp configuration summary for UI banners and mode detection.
     */
    public function getWhatsAppModeInfo() {
        $mode = 'web';
        $isEnabled = false;
        $hasApiCreds = false;
        $phoneNumberId = '';
        $senderNumber = '';
        try {
            $res = $this->conn->query("SELECT is_enabled, sending_mode, phone_number_id, sender_number, LENGTH(api_token) as token_len FROM whatsapp_settings WHERE id = 1");
            if ($res && $row = $res->fetch_assoc()) {
                $mode = $row['sending_mode'] ?? 'web';
                $isEnabled = ($row['is_enabled'] == 1);
                $hasApiCreds = (!empty($row['phone_number_id']) && intval($row['token_len'] ?? 0) > 0);
                $phoneNumberId = $row['phone_number_id'] ?? '';
                $senderNumber = $row['sender_number'] ?? '';
            }
        } catch (\Throwable $e) {}

        return [
            'mode'           => $mode,
            'is_enabled'     => $isEnabled,
            'has_api_creds'  => $hasApiCreds,
            'phone_number_id'=> $phoneNumberId,
            'sender_number'  => $senderNumber,
            'templates'      => [
                1 => $this->settings['meta_template_1'] ?? '',
                2 => $this->settings['meta_template_2'] ?? '',
                3 => $this->settings['meta_template_3'] ?? '',
                4 => $this->settings['meta_template_4'] ?? '',
            ]
        ];
    }

    /**
     * Get admin dashboard data.
     */
    public function getAdminDashboardData($status = 'all', $search = '', $page = 1) {
        // Auto-process any due reminders when admin loads/refreshes dashboard
        try {
            $this->processAutoReminders();
        } catch (\Throwable $e) {}

        return [
            'stats'         => $this->repo->getStats(),
            'carts'         => $this->repo->getAdminList($status, $search, $page),
            'settings'      => $this->settings,
            'whatsapp_info' => $this->getWhatsAppModeInfo(),
        ];
    }

    /**
     * Get the WhatsApp logs for a specific abandoned cart.
     */
    public function getCartLogs($cartId) {
        $cartId = intval($cartId);
        $logs = [];
        try {
            $this->conn->query("CREATE TABLE IF NOT EXISTS abandoned_cart_wa_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cart_id INT NOT NULL,
                customer_number VARCHAR(20),
                message TEXT,
                sending_mode VARCHAR(10),
                status TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cart_id (cart_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $this->conn->prepare("SELECT * FROM abandoned_cart_wa_logs WHERE cart_id = ? ORDER BY created_at DESC");
            if ($stmt) {
                $stmt->bind_param("i", $cartId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $logs[] = $row;
                }
                $stmt->close();
            }
        } catch (\Throwable $e) {
            error_log("[AbandonedCart] getCartLogs error: " . $e->getMessage());
        }
        return $logs;
    }
}

<?php
/**
 * Sagar Starters - AI ChatBot Engine & Knowledge Retrieval Service
 * Supports Google Gemini, OpenAI, Groq, and Smart Hybrid RAG Engine.
 */

class ChatbotService
{
    private $conn;
    private $pdo;
    private $settings = [];

    public function __construct($conn = null, $pdo = null)
    {
        if ($conn) {
            $this->conn = $conn;
        } else {
            global $conn;
            $this->conn = $conn;
        }

        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            require_once __DIR__ . '/../config/DbConnection.php';
            $this->pdo = DbConnection::getInstance();
        }

        $this->loadSettings();
        $this->ensureTableAndDefaults();
    }

    /**
     * Auto-create database table and default settings if missing on production
     */
    public function ensureTableAndDefaults(): void
    {
        try {
            // 1. Create table if not exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `chatbot_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(100) NOT NULL,
                `user_ip` VARCHAR(50) NULL,
                `user_message` TEXT NOT NULL,
                `bot_response` LONGTEXT NOT NULL,
                `intent` VARCHAR(50) DEFAULT 'general',
                `provider_used` VARCHAR(50) DEFAULT 'hybrid',
                `response_time_ms` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`session_id`),
                INDEX (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // 2. Insert missing defaults
            $defaults = [
                'chatbot_enabled'         => '1',
                'chatbot_name'            => 'Sagar Sahayak',
                'chatbot_title'           => 'Sagar AI Assistant',
                'chatbot_welcome_msg'     => "Namaste! 🙏 Main Sagar Starters ka AI Assistant hu. Main aapko Motor Starters, Submersible Panels, Price, Bulk Discounts aur Order Tracking me help kar sakta hu.\n\nAap niche diye gaye options chun sakte hain ya direct message likh sakte hain!",
                'chatbot_provider'        => 'hybrid',
                'chatbot_gemini_key'      => '',
                'chatbot_gemini_model'    => 'gemini-1.5-flash',
                'chatbot_openai_key'      => '',
                'chatbot_openai_model'    => 'gpt-4o-mini',
                'chatbot_groq_key'        => '',
                'chatbot_groq_model'      => 'llama-3.3-70b-versatile',
                'chatbot_system_prompt'   => 'You are Sagar Sahayak, the intelligent, friendly, and expert AI Assistant for Sagar Starters (sagarstarters.com) — an Indian eCommerce store specializing in premium motor starters, submersible pump control panels (1-Phase & 3-Phase), Star Delta starters, DOL starters, circuit breakers, and agricultural motor automation. Respond in a warm, professional, and helpful tone. Answer in the same language the customer speaks (Hindi, Hinglish, English, Gujarati, etc.). When customers ask about products, recommend matching items with their specs and prices. Always offer help with order tracking, bulk discounts, and technical advice.',
                'chatbot_whatsapp_number' => '919837248000',
                'chatbot_position'        => 'bottom-right',
                'chatbot_theme_color'     => '#007aff',
                'chatbot_quick_prompts'   => "5HP Submersible Starter,Single Phase vs 3 Phase,Track My Order,Bulk Purchase Discount,Talk to Expert on WhatsApp",
                'chatbot_response_delay'  => '800'
            ];

            $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key=setting_key");
            foreach ($defaults as $k => $v) {
                if (!isset($this->settings[$k])) {
                    $stmt->execute([$k, $v]);
                    $this->settings[$k] = $v;
                }
            }
        } catch (Exception $e) {
            error_log("ChatbotService ensureTableAndDefaults error: " . $e->getMessage());
        }
    }

    /**
     * Load all chatbot and store settings
     */
    private function loadSettings(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("ChatbotService loadSettings error: " . $e->getMessage());
        }
    }

    /**
     * Get single setting with fallback
     */
    public function getSetting(string $key, $default = '')
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Check if ChatBot is enabled
     */
    public function isEnabled(): bool
    {
        return ($this->getSetting('chatbot_enabled', '1') === '1');
    }

    /**
     * Main conversation processor
     *
     * @param string $userMessage The customer's question
     * @param array  $history     Previous conversation turns
     * @param string $sessionId   Unique session identifier
     * @return array [ 'reply' => string, 'products' => array, 'order_info' => array, 'quick_replies' => array, 'provider' => string ]
     */
    public function processMessage(string $userMessage, array $history = [], string $sessionId = ''): array
    {
        $startTime = microtime(true);
        $userMessage = trim($userMessage);

        if (empty($userMessage)) {
            return [
                'success'       => false,
                'reply'         => "Please enter a message / Kripya apna sawal likhein.",
                'products'      => [],
                'quick_replies' => $this->getQuickReplies()
            ];
        }

        // 1. Check for Order Tracking Intent First
        $orderTracking = $this->detectAndHandleOrderTracking($userMessage);
        if ($orderTracking !== null) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            $this->logConversation($sessionId, $userMessage, $orderTracking['reply'], 'order_tracking', 'internal', $responseTime);
            return $orderTracking;
        }

        // 2. Fetch Relevant Products based on user message keywords (RAG context)
        $matchedProducts = $this->searchRelevantProducts($userMessage);

        // 3. Determine AI Provider
        $provider = strtolower($this->getSetting('chatbot_provider', 'hybrid'));
        $replyText = '';
        $providerUsed = 'hybrid';

        // Attempt external AI if configured
        if ($provider === 'gemini' && !empty($this->getSetting('chatbot_gemini_key'))) {
            try {
                $replyText = $this->callGeminiAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'gemini';
            } catch (Exception $e) {
                error_log("Gemini API Error: " . $e->getMessage() . " - Falling back to Smart Hybrid Engine");
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts);
                $providerUsed = 'hybrid_fallback';
            }
        } elseif ($provider === 'openai' && !empty($this->getSetting('chatbot_openai_key'))) {
            try {
                $replyText = $this->callOpenAIAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'openai';
            } catch (Exception $e) {
                error_log("OpenAI API Error: " . $e->getMessage() . " - Falling back to Smart Hybrid Engine");
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts);
                $providerUsed = 'hybrid_fallback';
            }
        } elseif ($provider === 'groq' && !empty($this->getSetting('chatbot_groq_key'))) {
            try {
                $replyText = $this->callGroqAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'groq';
            } catch (Exception $e) {
                error_log("Groq API Error: " . $e->getMessage() . " - Falling back to Smart Hybrid Engine");
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts);
                $providerUsed = 'hybrid_fallback';
            }
        } else {
            // Default Smart Local Hybrid Engine (Always Works 100%)
            $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts);
            $providerUsed = 'hybrid';
        }

        // Format product payload for UI rendering
        $formattedProducts = [];
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        $currency = $this->getSetting('currency_symbol', '₹');
        $waPhone = !empty($this->getSetting('whatsapp_number')) ? preg_replace('/[^0-9]/', '', $this->getSetting('whatsapp_number')) : '919837248000';

        foreach ($matchedProducts as $p) {
            $regPrice = (float)($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
            $salePrice = (float)($p['sale_price'] ?? 0);
            $hasDiscount = ($salePrice > 0 && $salePrice < $regPrice);
            $displayPrice = $hasDiscount ? $salePrice : $regPrice;

            $pUrl = !empty($p['slug']) ? $siteUrl . '/product/' . $p['slug'] : $siteUrl . '/product.php?id=' . $p['id'];
            $imgSrc = !empty($p['image']) ? $p['image'] : 'assets/images/placeholder.svg';
            if (strpos($imgSrc, 'http') !== 0) {
                $imgSrc = $siteUrl . '/' . ltrim($imgSrc, '/');
            }

            $waMsg = urlencode("Hello Sagar Starters! I am interested in: *" . $p['name'] . "* (" . $currency . number_format($displayPrice, 2) . "). Please share more details.");
            $waLink = "https://wa.me/{$waPhone}?text={$waMsg}";

            $formattedProducts[] = [
                'id'            => $p['id'],
                'name'          => $p['name'],
                'regular_price' => $regPrice,
                'sale_price'    => $salePrice,
                'display_price' => $currency . number_format($displayPrice, 2),
                'has_discount'  => $hasDiscount,
                'image'         => $imgSrc,
                'url'           => $pUrl,
                'wa_link'       => $waLink,
                'bulk_price'    => !empty($p['bulk_price']) ? $currency . number_format((float)$p['bulk_price'], 2) : null,
                'bulk_min_qty'  => !empty($p['bulk_min_qty']) ? (int)$p['bulk_min_qty'] : null
            ];
        }

        $responseTime = round((microtime(true) - $startTime) * 1000);
        $this->logConversation($sessionId, $userMessage, $replyText, 'inquiry', $providerUsed, $responseTime);

        return [
            'success'       => true,
            'reply'         => $replyText,
            'products'      => array_slice($formattedProducts, 0, 4),
            'quick_replies' => $this->getDynamicQuickReplies($userMessage, count($formattedProducts)),
            'provider'          => $providerUsed,
            'response_ms'       => $responseTime,
            'response_delay_ms' => (int)$this->getSetting('chatbot_response_delay', '800')
        ];
    }

    /**
     * Check if user is asking to track an order
     */
    private function detectAndHandleOrderTracking(string $message): ?array
    {
        $cleanMsg = trim($message);
        $orderId = null;
        $phone = null;

        // 1. Look for order ID formats: ORD-12345, #12345, #5, or order 5
        if (preg_match('/(?:ORD[-_]?\s*(\d+))|(?:\#\s*(\d+))|(?:order\s*(?:id|no|number|#)?\s*[:=]?\s*(\d+))/i', $cleanMsg, $matches)) {
            $orderId = !empty($matches[1]) ? $matches[1] : (!empty($matches[2]) ? $matches[2] : (!empty($matches[3]) ? $matches[3] : null));
        }

        // 2. Look for 10-digit mobile number
        if (!$orderId && preg_match('/(?:^|\D)([6-9]\d{9})(?:\D|$)/', $cleanMsg, $pMatches)) {
            $phone = $pMatches[1];
        }

        // 3. User says "Track my order" without ID
        if (!$orderId && !$phone && preg_match('/track|order status|mera order|kaha pahucha|order track/i', $cleanMsg)) {
            return [
                'success'       => true,
                'reply'         => "📦 **Order Tracking Service**\n\nApna order status jaan ne ke liye kripya apna **Order Number (jaise: #1234 ya ORD-1234)** ya registered **10-digit Mobile Number** yahan type karein.",
                'products'      => [],
                'quick_replies' => ['Track #1001', 'Shop Products', 'WhatsApp Support']
            ];
        }

        if ($orderId || $phone) {
            try {
                $sql = "SELECT o.*, 
                               u.phone as user_phone, u.name as user_name,
                               ot.tracking_number as ot_tracking, ot.estimated_delivery_date,
                               cc.name as courier_company_name
                        FROM orders o
                        LEFT JOIN users u ON u.id = o.user_id
                        LEFT JOIN order_tracking ot ON ot.order_id = o.id
                        LEFT JOIN courier_companies cc ON cc.id = ot.courier_id
                        WHERE ";

                $params = [];
                if ($orderId) {
                    $sql .= "o.id = ?";
                    $params = [(int)$orderId];
                } else {
                    $sql .= "u.phone LIKE ?";
                    $params = ['%' . $phone];
                }
                $sql .= " ORDER BY o.id DESC LIMIT 1";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
                    $curr = $this->getSetting('currency_symbol', '₹');
                    $status = ucfirst($order['status'] ?? 'Pending');
                    $date = !empty($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : 'N/A';
                    $total = $curr . number_format((float)$order['total_amount'], 2);
                    $courier = !empty($order['courier_company_name']) ? $order['courier_company_name'] : (!empty($order['carrier']) ? $order['carrier'] : 'Dispatch in progress');
                    $trackNum = !empty($order['ot_tracking']) ? $order['ot_tracking'] : (!empty($order['tracking_number']) ? $order['tracking_number'] : 'Assigned upon dispatch');
                    $est = !empty($order['estimated_delivery_date']) ? "\n⏱️ **Expected Delivery**: " . date('d M Y', strtotime($order['estimated_delivery_date'])) : '';

                    $reply = "✅ **Order Found: #{$order['id']}**\n\n"
                           . "• **Status**: `{$status}`\n"
                           . "• **Order Date**: {$date}\n"
                           . "• **Total Amount**: {$total}\n"
                           . "• **Payment**: " . ucfirst($order['payment_status'] ?? 'Pending') . "\n"
                           . "• **Courier / Carrier**: {$courier}\n"
                           . "• **Tracking ID**: `{$trackNum}`"
                           . $est . "\n\n"
                           . "Agar aapko order me koi badlav ya sawal ho, to hamare support team se WhatsApp par sampark karein.";

                    return [
                        'success'       => true,
                        'reply'         => $reply,
                        'products'      => [],
                        'quick_replies' => ['Browse More Products', 'WhatsApp Support', 'Home']
                    ];
                } else {
                    return [
                        'success'       => true,
                        'reply'         => "❌ **Kshama karein, is Order Number ya Phone Number se koi order nahi mila.**\n\nKripya apna sahi 4-digit ya 5-digit Order ID (jaise: `#1052`) verify karke dobara enter karein, ya WhatsApp par hamare team se help lein.",
                        'products'      => [],
                        'quick_replies' => ['Try Another Order ID', 'Talk to WhatsApp Support', 'Browse Shop']
                    ];
                }
            } catch (Exception $e) {
                error_log("Order Tracking Query Error: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Search database for products relevant to user query
     */
    public function searchRelevantProducts(string $query, int $limit = 4): array
    {
        $clean = trim($query);
        if (empty($clean)) return [];

        // Check if query is actually seeking products or specifications
        $productKeywordPattern = '/(?:hp|h\.p|phase|starter|panel|submersible|pump|motor|borewell|contactor|relay|mcb|breaker|stabilizer|voltmeter|ammeter|capacitor|rate|price|kimat|daam|cost|kharidna|buy|purchase|model|watt|kva|spec|dikhao|show|btao|batao|kya rate|kitne ka)|\d+\s*(?:hp|h\.p|kva|watt|v|volt|amp|ampere)/i';
        $nonProductPattern = '/malik|owner|founder|director|kiska hai|kiske dwara|address|pata|kaha par|kaha hai|kahan hai|office|factory|dukan|location|complaint|shikayat|helpline|support number|delivery time|payment method|refund|return/i';

        if (!preg_match($productKeywordPattern, $clean) || preg_match($nonProductPattern, $clean)) {
            return []; // Do not retrieve products for company/owner/location/policy queries
        }

        try {
            $tokens = preg_split('/[\s,\+]+/', strtolower($clean));
            $whereParts = [];
            $params = [];

            // Detect phase
            if (preg_match('/1\s*phase|single\s*phase|220\s*v/i', $clean)) {
                $whereParts[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $params[] = '%1%Phase%';
                $params[] = '%Single Phase%';
                $params[] = '%Single Phase%';
            } elseif (preg_match('/3\s*phase|three\s*phase|415\s*v|star\s*delta/i', $clean)) {
                $whereParts[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $params[] = '%3%Phase%';
                $params[] = '%Three Phase%';
                $params[] = '%Star Delta%';
            }

            // Detect HP
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hp|h\.p)/i', $clean, $m)) {
                $hpNum = $m[1];
                $whereParts[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $params[] = "%{$hpNum}%hp%";
                $params[] = "%{$hpNum} HP%";
            }

            // Keyword token search
            $meaningfulTokens = array_filter($tokens, function($t) {
                return strlen($t) >= 3 && !in_array($t, ['the', 'and', 'for', 'kya', 'hai', 'mujhe', 'chahiye', 'batao', 'price', 'kitna', 'rate', 'show', 'dikhao', 'please', 'tell', 'about', 'kaun', 'kisko']);
            });

            if (!empty($meaningfulTokens)) {
                $tokenOr = [];
                foreach ($meaningfulTokens as $t) {
                    $tokenOr[] = "p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?";
                    $params[] = "%{$t}%";
                    $params[] = "%{$t}%";
                    $params[] = "%{$t}%";
                }
                $whereParts[] = "(" . implode(" OR ", $tokenOr) . ")";
            }

            if (empty($whereParts)) {
                return [];
            }

            $sql = "SELECT p.id, p.name, p.slug, p.price, p.regular_price, p.sale_price, p.bulk_price, p.bulk_min_qty, p.image, p.short_description, p.description, c.name as category_name
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE " . implode(" AND ", $whereParts) . "
                    LIMIT " . (int)$limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("searchRelevantProducts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Smart Local Hybrid NLP Engine (100% Offline / Non-API Fallback)
     */
    private function smartLocalHybridReply(string $message, array $products): string
    {
        $clean = strtolower($message);
        $curr = $this->getSetting('currency_symbol', '₹');
        $siteName = $this->getSetting('site_name', "Sagar Starter's");
        $phone = $this->getSetting('contact_phone', '+91 8573934013');
        $email = $this->getSetting('contact_email', 'sagarstarters@gmail.com');
        $address = $this->getSetting('contact_address', 'Alipur Madra, Jakhanian, Ghazipur, Uttar Pradesh');

        // 1. Owner / Founder / Malik Queries (Hindi + English + Hinglish)
        if (preg_match('/malik|owner|founder|director|kiska hai|kiske dwara|banaya|sanchalak|proprietor|who is the owner|who founded|owner name|malik kaun|मालिक|संस्थापक|ओनर|फाउंडर|किसकी कंपनी|किसका है|संचालक/iu', $message)) {
            return "👑 **सागर स्टार्टर्स के संस्थापक एवं मालिक (Founder & Owner):**\n\n"
                 . "**श्री प्रमोद कुमार सागर (Mr. Pramod Kumar Sagar)** सागर स्टार्टर्स (sagarstarters.com) के संस्थापक और मुख्य संचालक हैं।\n\n"
                 . "• **कंपनी**: Sagar Starter's\n"
                 . "• **स्थान**: अलीपुर मदरा, जखनियाँ, गाजीपुर (उत्तर प्रदेश)\n"
                 . "• **विशेषज्ञता**: उच्च गुणवत्ता वाले मोटर स्टार्टर्स, सबमर्सिबल पंप पैनल्स एवं ऑटोमेशन इक्विपमेंट्स निर्माण।\n\n"
                 . "यदि आप सीधे संपर्क या बिज़नेस इन्क्वायरी करना चाहते हैं, तो आप WhatsApp पर कनेक्ट कर सकते हैं!";
        }

        // 2. Address / Location Queries (Hindi + English + Hinglish)
        if (preg_match('/address|location|kaha par|kaha hai|kahan hai|office|factory|dukan|shop|city|state|ghazipur|jakhanian|store location|pata kya hai|पता|कहा है|कहाँ है|कहाँ पर|दुकान|ऑफिस|स्थान|लोकेशन/iu', $message)) {
            return "📍 **सागर स्टार्टर्स का पता एवं संपर्क (Address & Location):**\n\n"
                 . "• **पता**: {$address}\n"
                 . "• **हेल्पलाइन / फोन**: `{$phone}`\n"
                 . "• **ईमेल**: `{$email}`\n"
                 . "• **समय**: सोमवार से शनिवार, सुबह 9:00 AM से शाम 6:00 PM\n\n"
                 . "आप गूगल मैप्स पर भी **'SAGAR STARTERS'** सर्च करके आसानी से लोकेशन देख सकते हैं!";
        }

        // 3. Payment / COD Methods
        if (preg_match('/payment|cod|cash on delivery|upi|qr|google pay|phonepe|paytm|advance|paise kaise|भुगतान|कैश ऑन डिलीवरी|पैसे|ऑनलाइन पेमेंट/iu', $message)) {
            return "💳 **भुगतान के तरीके (Payment Methods):**\n\n"
                 . "• **Cash on Delivery (COD)**: पूरे भारत में उपलब्ध है (सुरक्षा के लिए नाममात्र एडवांस/शिपिंग चार्ज लगता है)।\n"
                 . "• **Online Payment**: UPI (PhonePe, Google Pay, Paytm), QR Code स्कैन, डेबिट/क्रेडिट कार्ड और नेट बैंकिंग 100% सुरक्षित रूप से समर्थित हैं।";
        }

        // 4. Delivery & Shipping Timelines
        if (preg_match('/delivery|shipping|kitne din|deliver|courier|dispatch|pahuch|डिलीवरी|शिपिंग|कितने दिन|कब तक/iu', $message)) {
            return "🚚 **डिलीवरी एवं शिपिंग जानकारी (Shipping & Delivery):**\n\n"
                 . "• **ऑल इंडिया डिलीवरी**: हम Delhivery, DTDC एवं एक्सप्रेस कूरियर पार्टनर्स द्वारा पूरे देश में सुरक्षित पार्सल भेजते हैं।\n"
                 . "• **समय**: आर्डर कन्फर्म होने के **3 से 7 कार्य दिवसों (Business Days)** के भीतर पार्सल आपके पते पर डिलीवर हो जाता है।\n"
                 . "• हर आर्डर का लाइव ट्रैकिंग नंबर SMS एवं WhatsApp पर प्रदान किया जाता है।";
        }

        // 5. Warranty & Return Policy
        if (preg_match('/return|refund|replace|replacement|damage|kharab|warranty|guarantee|वारंटी|गारंटी|खराब|वापस|रिप्लेस/iu', $message)) {
            return "🛡️ **वारंटी एवं रिप्लेसमेंट नीति (Warranty & Policy):**\n\n"
                 . "• **100% टेस्टेड क्वालिटी**: हमारे सभी स्टार्टर्स और पैनल्स कड़े क्वालिटी चेक के बाद ही डिस्पैच किए जाते हैं।\n"
                 . "• **ट्रांजिट रिप्लेसमेंट**: यदि पार्सल डिलीवरी में कोई डैमेज मिलता है, तो तुरंत रिप्लेसमेंट या टेक्निकल सपोर्ट दिया जाता है।\n"
                 . "• सहायता के लिए संपर्क: `{$phone}`";
        }

        // 6. Greetings
        if (preg_match('/^(hi|hello|hey|namaste|kem cho|pranam|salam|sasriakal)/i', $clean)) {
            return "Namaste! 🙏 Welcome to **{$siteName}**.\n\nMain aapki motor starter, submersible panel selection, live pricing aur bulk order enquiry me help karne ke liye taiyar hu. Aap kis prakaar ke starter ya motor ke baare me jaankari chahte hain?";
        }

        // 7. Single Phase vs 3 Phase guidance
        if (preg_match('/single phase.*3 phase|1 phase.*3 phase|difference|kisme lagta hai/i', $clean)) {
            return "💡 **Single Phase vs 3 Phase Motor Starter Guide:**\n\n"
                 . "1. **Single Phase (220V)**: Yeh domestic aur choti agricultural submersible pumps (0.5 HP se 3 HP tak) ke liye ideal hota hai. Isme starting aur running capacitor lage hote hain.\n"
                 . "2. **Three Phase (415V)**: Yeh 3 HP se 30+ HP tak ki heavy-duty tubewell, agricultural aur industrial motors ke liye use hota hai (DOL aur Automatic Star Delta models available).\n\n"
                 . "👉 Aapki motor kitne **HP** ki hai? Mujhe batayein, main best model recommend karunga!";
        }

        // 8. Submersible Starters
        if (preg_match('/submersible|borewell|khet|pump/i', $clean)) {
            $resp = "⚡ **Sagar Submersible Pump Starters:**\n\n"
                  . "Hamare sabhi submersible control panels me **Dry Run Auto-Cut**, **Overload Protection**, **Digital Voltmeter & Ammeter**, aur **Surge Protection** inbuilt milta hai.";

            if (!empty($products)) {
                $resp .= "\n\nYahan hamare best-selling submersible starters hain:\n";
                foreach ($products as $p) {
                    $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                    $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
                }
            }
            return $resp;
        }

        // 9. Star Delta / Heavy Motor
        if (preg_match('/star delta|delta|heavy|chakki|flour mill|30 hp|20 hp|15 hp/i', $clean)) {
            return "🏭 **Sagar Automatic Star Delta Starters:**\n\n"
                 . "Heavy duty industrial aur agricultural motors (7.5 HP se 35 HP) ke liye hamare Star Delta panels equipped hain:\n"
                 . "• Microcontroller based Electronic Timer\n"
                 . "• Phase Failure & Reverse Phase Protection\n"
                 . "• High-grade Copper Busbars & Heavy Contactors\n\n"
                 . "Niche matching models dekhein ya customized panel ke liye direct WhatsApp par contact karein.";
        }

        // 10. Bulk Order / Retailer / Wholesale
        if (preg_match('/bulk|wholesale|retailer|dealer|discount|kam price|zyada quantity/i', $clean)) {
            return "📦 **Bulk Purchase & Retailer Special Discounts:**\n\n"
                 . "Haan! Hum retailers, dealers aur agricultural contractors ke liye **Special Wholesale Pricing** provide karte hain.\n\n"
                 . "• Har product page par Bulk MOQ tier prices listed hain.\n"
                 . "• Badi quantity ke customized quotation ke liye aap hamare sales team se direct WhatsApp par connect kar sakte hain.";
        }

        // 11. General Product match (if products were actively requested)
        if (!empty($products)) {
            $resp = "Maine aapke sawal ke anuroop yeh best product(s) dhundhe hain:\n\n";
            foreach ($products as $p) {
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
            }
            $resp .= "\nAap niche diye gaye **'View Details'** ya direct **'WhatsApp Order'** button se order kar sakte hain.";
            return $resp;
        }

        return "Maine aapka request note kar liya hai. **{$siteName}** par sabhi types ke Motor Starters, Submersible Panels aur Electrical Spares available hain.\n\nKripya mujhe apni motor ka **HP (jaise: 5 HP)** ya **Phase (1-Phase / 3-Phase)** batayein taaki main exact model suggest kar saku, ya specific jaankari ke liye WhatsApp par connect karein.";
    }

    /**
     * Google Gemini API Call
     */
    private function callGeminiAPI(string $userMessage, array $history, array $products): string
    {
        $apiKey = $this->getSetting('chatbot_gemini_key');
        $model = $this->getSetting('chatbot_gemini_model', 'gemini-1.5-flash');

        $systemInstruction = $this->buildSystemContext($products);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $contents = [];

        // Add history turns (last 6 messages max for low latency)
        $trimmedHistory = array_slice($history, -6);
        foreach ($trimmedHistory as $turn) {
            $role = ($turn['sender'] === 'bot' || $turn['sender'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => (string)($turn['text'] ?? $turn['message'] ?? '')]]
            ];
        }

        // Add current user prompt
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents'           => $contents,
            'generationConfig'   => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 500,
            ]
        ];

        $responseJson = $this->makeCurlRequest($url, $payload, ['Content-Type: application/json']);
        $data = json_decode($responseJson, true);

        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }

        if (!empty($data['error']['message'])) {
            throw new Exception("Gemini API error: " . $data['error']['message']);
        }

        throw new Exception("Invalid Gemini API response payload");
    }

    /**
     * OpenAI API Call
     */
    private function callOpenAIAPI(string $userMessage, array $history, array $products): string
    {
        $apiKey = $this->getSetting('chatbot_openai_key');
        $model = $this->getSetting('chatbot_openai_model', 'gpt-4o-mini');
        $url = "https://api.openai.com/v1/chat/completions";

        $systemInstruction = $this->buildSystemContext($products);
        $messages = [
            ['role' => 'system', 'content' => $systemInstruction]
        ];

        $trimmedHistory = array_slice($history, -6);
        foreach ($trimmedHistory as $turn) {
            $role = ($turn['sender'] === 'bot' || $turn['sender'] === 'assistant') ? 'assistant' : 'user';
            $messages[] = [
                'role'    => $role,
                'content' => (string)($turn['text'] ?? $turn['message'] ?? '')
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 500
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];

        $responseJson = $this->makeCurlRequest($url, $payload, $headers);
        $data = json_decode($responseJson, true);

        if (!empty($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        if (!empty($data['error']['message'])) {
            throw new Exception("OpenAI API error: " . $data['error']['message']);
        }

        throw new Exception("Invalid OpenAI API response payload");
    }

    /**
     * Groq API Call
     */
    private function callGroqAPI(string $userMessage, array $history, array $products): string
    {
        $apiKey = $this->getSetting('chatbot_groq_key');
        $model = $this->getSetting('chatbot_groq_model', 'llama-3.3-70b-versatile');
        $url = "https://api.groq.com/openai/v1/chat/completions";

        $systemInstruction = $this->buildSystemContext($products);
        $messages = [
            ['role' => 'system', 'content' => $systemInstruction]
        ];

        $trimmedHistory = array_slice($history, -6);
        foreach ($trimmedHistory as $turn) {
            $role = ($turn['sender'] === 'bot' || $turn['sender'] === 'assistant') ? 'assistant' : 'user';
            $messages[] = [
                'role'    => $role,
                'content' => (string)($turn['text'] ?? $turn['message'] ?? '')
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 500
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];

        $responseJson = $this->makeCurlRequest($url, $payload, $headers);
        $data = json_decode($responseJson, true);

        if (!empty($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        if (!empty($data['error']['message'])) {
            throw new Exception("Groq API error: " . $data['error']['message']);
        }

        throw new Exception("Invalid Groq API response payload");
    }

    /**
     * Build RAG System Context with store products and policies
     */
    private function buildSystemContext(array $products): string
    {
        $basePrompt = $this->getSetting('chatbot_system_prompt', 'You are Sagar Sahayak, the helpful AI Assistant for Sagar Starters.');
        $siteName = $this->getSetting('site_name', "Sagar Starter's");
        $curr = $this->getSetting('currency_symbol', '₹');
        $phone = $this->getSetting('contact_phone', '+91 9837248000');

        $context = $basePrompt . "\n\n"
                 . "SAGAR STARTERS VERIFIED STORE KNOWLEDGE:\n"
                 . "- Store / Brand Name: {$siteName} (sagarstarters.com)\n"
                 . "- Founder & Owner: Shri Pramod Kumar Sagar (Mr. Pramod Kumar Sagar)\n"
                 . "- Head Office & Factory Location: Alipur Madra, Jakhanian, Ghazipur, Uttar Pradesh, India\n"
                 . "- Official Contact & WhatsApp: {$phone}\n"
                 . "- Official Email: sagarstarters@gmail.com\n"
                 . "- Currency: {$curr}\n"
                 . "- Speciality: Motor Starters (DOL, Star Delta, 1-Phase, 3-Phase), Submersible Pump Control Panels, Automatic Star Delta Starters, Voltage Stabilizers, MCB Breakers, Meters, and Spares.\n"
                 . "- Order Tracking: Customers can track orders directly by providing their Order ID or Phone number.\n"
                 . "- Shipping & Delivery: Pan-India fast delivery in 3 to 7 business days.\n"
                 . "- Payment Methods: Cash on Delivery (COD) and 100% Secure Online Payments (UPI, PhonePe, Google Pay, Cards, NetBanking).\n\n";

        if (!empty($products)) {
            $context .= "MATCHED LIVE PRODUCTS IN DATABASE FOR THIS QUERY:\n";
            foreach ($products as $p) {
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $context .= "- Product: {$p['name']} | Price: {$curr}{$price} | Category: " . ($p['category_name'] ?? 'Starters') . "\n";
            }
            $context .= "\nINSTRUCTION: Provide a concise, friendly response in the user's language (Hindi, Hinglish, or English). Highlight 1-2 product benefits if relevant. Product cards will be automatically displayed by the UI below your reply.";
        }

        return $context;
    }

    /**
     * Secure cURL POST Helper with timeout
     */
    private function makeCurlRequest(string $url, array $payload, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new Exception("cURL Error: " . $curlError);
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}: " . $response);
        }

        return $response;
    }

    /**
     * Default quick reply chips
     */
    public function getQuickReplies(): array
    {
        $raw = $this->getSetting('chatbot_quick_prompts', '');
        if (!empty($raw)) {
            return array_map('trim', explode(',', $raw));
        }
        return [
            '5HP Submersible Starter',
            'Single Phase vs 3 Phase',
            'Track My Order',
            'Bulk Purchase Discount',
            'Talk to Expert on WhatsApp'
        ];
    }

    /**
     * Dynamic quick replies based on user intent
     */
    private function getDynamicQuickReplies(string $userMsg, int $productCount): array
    {
        $clean = strtolower($userMsg);

        if (strpos($clean, 'submersible') !== false || strpos($clean, 'pump') !== false) {
            return ['Single Phase 2HP', '3 Phase 5HP Panel', 'Track Order', 'WhatsApp Expert'];
        }

        if (strpos($clean, 'track') !== false || strpos($clean, 'order') !== false) {
            return ['Check another order', 'Shop Starters', 'WhatsApp Support'];
        }

        if ($productCount > 0) {
            return ['Bulk Price Discount', 'How to Order?', 'Track Order', 'WhatsApp Inquiry'];
        }

        return $this->getQuickReplies();
    }

    /**
     * Log conversation in chatbot_logs table
     */
    private function logConversation(string $sessionId, string $userMsg, string $botReply, string $intent, string $provider, int $responseMs): void
    {
        try {
            $userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $this->pdo->prepare("INSERT INTO chatbot_logs (session_id, user_ip, user_message, bot_response, intent, provider_used, response_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $userIp, $userMsg, $botReply, $intent, $provider, $responseMs]);
        } catch (Exception $e) {
            error_log("Failed to log chatbot conversation: " . $e->getMessage());
        }
    }
}

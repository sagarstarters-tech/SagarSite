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

        // 2. Multi-turn Context Resolution (RAG + Conversation History)
        // If current query is short or referential ("link do", "price kya hai", "give correct answer"), extract HP/keywords from history!
        $contextQuery = $userMessage;
        if (!empty($history) && is_array($history)) {
            $historyText = '';
            foreach (array_slice($history, -4) as $h) {
                $historyText .= ' ' . ($h['content'] ?? '');
            }

            // If current message doesn't have an explicit HP but history has HP:
            if (!preg_match('/(?:^|\s|[^0-9\.])(\d+(?:\.\d+)?)\s*(?:hp|h\.p|एचपी|हॉर्सपावर|हार्सपावर|हॉर्स\s*पावर)/iu', $userMessage)) {
                if (preg_match('/(?:^|\s|[^0-9\.])(\d+(?:\.\d+)?)\s*(?:hp|h\.p|एचपी|हॉर्सपावर|हार्सपावर|हॉर्स\s*पावर)/iu', $historyText, $hm)) {
                    $contextQuery .= ' ' . $hm[1] . ' HP';
                }
            }

            // If history had product type (star delta, submersible, dol)
            if (!preg_match('/submersible|star\s*delta|dol/i', $userMessage)) {
                if (preg_match('/star\s*delta|स्टार\s*डेल्टा/iu', $historyText)) {
                    $contextQuery .= ' star delta';
                } elseif (preg_match('/submersible|सबमर्सिबल/iu', $historyText)) {
                    $contextQuery .= ' submersible';
                } elseif (preg_match('/dol|डीओएल/iu', $historyText)) {
                    $contextQuery .= ' dol';
                }
            }
        }

        // Fetch Relevant Products based on context-enriched query
        $matchedProducts = $this->searchRelevantProducts($contextQuery);

        // 3. Determine AI Provider Execution (Collaborative Dual-Engine Architecture)
        $provider = strtolower($this->getSetting('chatbot_provider', 'hybrid_groq'));
        $groqKey = $this->getSetting('chatbot_groq_key');
        $replyText = '';
        $providerUsed = 'hybrid';

        // Check if user is asking for direct purchase links or exact location
        $isDirectLink = (bool)preg_match('/link|खरीदने का लिंक|खरीदने के लिए लिंक|buy link|purchase link|order link|लिंक|link do|link dijiye|direct link/iu', $userMessage);

        // Collaborative Logic:
        // Direct transactional tasks (links, exact price confirmation) use Local Engine for 100% precision.
        // Conversational, recommendation, troubleshooting, and general inquiries use Groq LLaMA Cloud with Local Knowledge context!
        $isDualGroq = in_array($provider, ['hybrid_groq', 'groq', 'hybrid']) && !empty($groqKey);

        if ($isDirectLink && !empty($matchedProducts)) {
            $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts, $history);
            $providerUsed = 'local_direct_link';
        } elseif ($isDualGroq) {
            try {
                // Groq Cloud LLaMA + Local Catalog RAG Context
                $replyText = $this->callGroqAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'groq_cloud_llama';
            } catch (Exception $e) {
                error_log("Groq Dual-Engine Fallback: " . $e->getMessage());
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts, $history);
                $providerUsed = 'local_engine_fallback';
            }
        } elseif ($provider === 'gemini' && !empty($this->getSetting('chatbot_gemini_key'))) {
            try {
                $replyText = $this->callGeminiAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'gemini';
            } catch (Exception $e) {
                error_log("Gemini API Error: " . $e->getMessage());
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts, $history);
                $providerUsed = 'local_engine_fallback';
            }
        } elseif ($provider === 'openai' && !empty($this->getSetting('chatbot_openai_key'))) {
            try {
                $replyText = $this->callOpenAIAPI($userMessage, $history, $matchedProducts);
                $providerUsed = 'openai';
            } catch (Exception $e) {
                error_log("OpenAI API Error: " . $e->getMessage());
                $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts, $history);
                $providerUsed = 'local_engine_fallback';
            }
        } else {
            // Default Smart Local Engine
            $replyText = $this->smartLocalHybridReply($userMessage, $matchedProducts, $history);
            $providerUsed = 'smart_local_engine';
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

        // 1. Look for order ID formats: ORD-12345, #12345, #5, order 5, ऑर्डर 5, ऑर्डर नंबर 5
        if (preg_match('/(?:ORD[-_]?\s*(\d+))|(?:\#\s*(\d+))|(?:(?:order|ऑर्डर|आर्डर)\s*(?:id|no|number|num|\#|नंबर|संख्या)?\s*[:=]?\s*(\d+))/iu', $cleanMsg, $matches)) {
            $orderId = !empty($matches[1]) ? $matches[1] : (!empty($matches[2]) ? $matches[2] : (!empty($matches[3]) ? $matches[3] : null));
        }

        // 2. Look for 10-digit mobile number
        if (!$orderId && preg_match('/(?:^|\D)([6-9]\d{9})(?:\D|$)/', $cleanMsg, $pMatches)) {
            $phone = $pMatches[1];
        }

        // 3. User says "Track my order" or asks in Hindi "क्या मेरा कोई ऑर्डर है", "ऑर्डर जांच करें", "मेरा पार्सल कहां है"
        $isOrderQuery = (bool)preg_match('/track|order|status|mera order|kaha pahucha|order track|check order|my order|where is my parcel|ऑर्डर|आर्डर|जांच|मेरा ऑर्डर|कोई ऑर्डर|पार्सल|स्टेटस|पार्सल कहा है|कूरियर|आर्डर की स्थिति/iu', $cleanMsg);

        if (!$orderId && !$phone && $isOrderQuery) {
            // Check if logged in user has an order in session
            $sessionUserId = $_SESSION['user_id'] ?? null;
            if ($sessionUserId) {
                try {
                    $stmt = $this->pdo->prepare("SELECT o.*, u.phone as user_phone, u.name as user_name, ot.tracking_number as ot_tracking, ot.estimated_delivery_date, cc.name as courier_company_name FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN order_tracking ot ON ot.order_id = o.id LEFT JOIN courier_companies cc ON cc.id = ot.courier_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 1");
                    $stmt->execute([(int)$sessionUserId]);
                    $sessionOrder = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($sessionOrder) {
                        $orderId = (int)$sessionOrder['id'];
                    }
                } catch (Exception $e) {
                    error_log("Session order check error: " . $e->getMessage());
                }
            }

            if (!$orderId) {
                return [
                    'success'       => true,
                    'reply'         => "📦 **ऑर्डर स्टेटस एवं पार्सल ट्रैकिंग (Order Tracking):**\n\n"
                                     . "अपने आर्डर की लाइव स्थिति जानने के लिए कृपया अपना **Order Number (जैसे: `#1021` या `ORD-1021`)** अथवा रजिस्टर्ड **10-Digit Mobile Number** यहाँ लिखकर भेजें।\n\n"
                                     . "मैं तुरंत डेटाबेस से आपके पार्सल का लाइव स्टेटस, कूरियर कंपनी और ट्रैकिंग नंबर निकाल दूंगा!",
                    'products'      => [],
                    'quick_replies' => ['#1001', 'Shop Products', 'WhatsApp Support']
                ];
            }
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

        // Check if query is seeking products or specifications
        $productKeywordPattern = '/(?:hp|h\.p|phase|starter|panel|submersible|pump|motor|borewell|contactor|relay|mcb|breaker|stabilizer|voltmeter|ammeter|capacitor|rate|price|kimat|daam|cost|kharidna|buy|purchase|model|watt|kva|spec|dikhao|show|btao|batao|kya rate|kitne ka|एचपी|फेज|पैनल|स्टार्टर)|\d+\s*(?:hp|h\.p|kva|watt|v|volt|amp|ampere)/iu';
        $nonProductPattern = '/malik|owner|founder|director|kiska hai|kiske dwara|address|pata|kaha par|kaha hai|kahan hai|office|factory|dukan|location|complaint|shikayat|helpline|support number|delivery time|payment method|refund|return|मालिक|संस्थापक|ओनर|पता|कहाँ/iu';

        if (!preg_match($productKeywordPattern, $clean) || preg_match($nonProductPattern, $clean)) {
            return []; // Strictly do not retrieve products for owner/location/policy queries
        }

        try {
            $allProducts = $this->pdo->query("SELECT p.id, p.name, p.slug, p.price, p.regular_price, p.sale_price, p.bulk_price, p.bulk_min_qty, p.image, p.short_description, p.description, c.name as category_name
                                              FROM products p
                                              LEFT JOIN categories c ON c.id = p.category_id")->fetchAll(PDO::FETCH_ASSOC);

            if (empty($allProducts)) return [];

            // Detect requested HP
            $reqHp = null;
            if (preg_match('/(?:^|\s|[^0-9\.])(\d+(?:\.\d+)?)\s*(?:hp|h\.p|एचपी)(?:$|\s|[^0-9a-zA-Z])/iu', $clean, $m)) {
                $reqHp = (float)$m[1];
            }

            // Detect requested Phase and Types
            $reqSinglePhase = (bool)preg_match('/1\s*phase|single\s*phase|220\s*v|सिंगल\s*फेज/iu', $clean);
            $reqThreePhase  = (bool)preg_match('/3\s*phase|three\s*phase|415\s*v|star\s*delta|dol|थ्री\s*फेज|तीन\s*फेज/iu', $clean);
            $reqSubmersible = (bool)preg_match('/submersible|borewell|samarsebal|samar|सबमर्सिबल|बोरवेल/iu', $clean);
            $reqStarDelta   = (bool)preg_match('/star\s*delta|delta|chakki|स्टार\s*डेल्टा/iu', $clean);
            $reqDOL         = (bool)preg_match('/\bdol\b/iu', $clean);

            $scored = [];
            foreach ($allProducts as $p) {
                $name = strtolower($p['name'] . ' ' . ($p['description'] ?? ''));
                $score = 0;

                // Extract HP from product name
                $productHp = null;
                if (preg_match('/(?:^|\s|[^0-9\.])(\d+(?:\.\d+)?)\s*(?:hp|h\.p)(?:$|\s|[^0-9a-zA-Z])/i', $p['name'], $pm)) {
                    $productHp = (float)$pm[1];
                }

                // 1. HP Constraint: If user specified HP, must match exact HP!
                if ($reqHp !== null) {
                    if ($productHp !== null && abs($productHp - $reqHp) < 0.01) {
                        $score += 100;
                    } else {
                        // Skip completely if different HP
                        continue;
                    }
                } elseif ($productHp !== null) {
                    $score += 10;
                }

                // 2. Phase Constraint & Scoring
                $pIsSingle = (bool)preg_match('/single\s*phase|1\s*phase/i', $name);
                $pIsThree  = (bool)preg_match('/3\s*phase|three\s*phase|star\s*delta|dol/i', $name);

                if ($reqSinglePhase) {
                    if ($pIsSingle) $score += 50;
                    if ($pIsThree) $score -= 50;
                }
                if ($reqThreePhase) {
                    if ($pIsThree) $score += 50;
                    if ($pIsSingle) $score -= 50;
                }

                // 3. Submersible, Star Delta, DOL boosts
                if ($reqSubmersible && preg_match('/submersible/i', $name)) $score += 30;
                if ($reqStarDelta && preg_match('/star\s*delta/i', $name)) $score += 35;
                if ($reqDOL && preg_match('/dol/i', $name)) $score += 35;

                // 4. Token relevance
                $tokens = preg_split('/[\s,\+]+/', strtolower($clean));
                foreach ($tokens as $t) {
                    if (strlen($t) >= 3 && !in_array($t, ['the', 'and', 'for', 'kya', 'hai', 'mujhe', 'chahiye', 'batao', 'price', 'kitna', 'rate', 'show', 'dikhao', 'please', 'tell', 'about', 'starter', 'panel'])) {
                        if (stripos($name, $t) !== false) {
                            $score += 15;
                        }
                    }
                }

                if ($score > 0) {
                    $p['relevance_score'] = $score;
                    $scored[] = $p;
                }
            }

            usort($scored, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });

            return array_slice($scored, 0, $limit);
        } catch (Exception $e) {
            error_log("searchRelevantProducts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Smart Local Hybrid NLP Engine (100% Offline / Human-Like Sales Engineer Assistant)
     */
    private function smartLocalHybridReply(string $message, array $products, array $history = []): string
    {
        $msg = trim($message);
        $clean = strtolower($msg);
        $curr = $this->getSetting('currency_symbol', '₹');
        $siteName = $this->getSetting('site_name', "Sagar Starter's");
        $phone = $this->getSetting('contact_phone', '+91 8573934013');
        $email = $this->getSetting('contact_email', 'support@sagarstarters.com');
        $address = 'Alipur Madra, Jakhanian, Ghazipur, Uttar Pradesh, India - PIN Code: 275203';

        // 1. Direct Purchase Link Request (e.g. "मुझे इसे खरीदने का लिंक दीजिए", "Buy link do", "Purchase link")
        if (preg_match('/link|खरीदने का लिंक|खरीदने के लिए लिंक|खरीदना है|buy link|purchase link|order link|लिंक|link do|link dijiye|direct link|ऑनलाइन लिंक|website link/iu', $msg)) {
            if (!empty($products)) {
                $p = $products[0];
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
                $pUrl = !empty($p['slug']) ? $siteUrl . '/product/' . $p['slug'] : $siteUrl . '/product.php?id=' . $p['id'];
                $waUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $phone) . "?text=" . urlencode("Namaste Sagar Starters, mujhe *" . $p['name'] . "* kharidna hai.");

                return "🛒 **{$p['name']} खरीदने के डायरेक्ट लिंक्स:**\n\n"
                     . "• **कीमत (Price)**: `{$curr}" . number_format($price, 2) . "`\n"
                     . "• 🔗 **ऑनलाइन वेबसाइट से खरीदें**: [यहाँ क्लिक करके खरीदें]({$pUrl})\n"
                     . "• 📱 **व्हाट्सएप पर डायरेक्ट बुक करें**: [WhatsApp पर आर्डर करें]({$waUrl})\n\n"
                     . "✅ **Cash on Delivery (COD)** उपलब्ध है एवं 3 से 7 दिनों में सुरक्षित डिलीवरी हो जाएगी!";
            }
        }

        // 2. "Give the correct answer" / "Sahi jawab do"
        if (preg_match('/give (?:the )?correct answer|sahi jawab|sahi se batao|correct answer please|सही जवाब/iu', $msg)) {
            if (!empty($products)) {
                $p = $products[0];
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                return "जी, आपकी आवश्यकता के लिए बिल्कुल सही और उपयुक्त मॉडल **{$p['name']}** है।\n\n"
                     . "• **कीमत (Price)**: `{$curr}" . number_format($price, 2) . "`\n"
                     . "• **वारंटी**: 100% फैक्ट्री टेस्टेड एवं हेवी ड्यूटी कंपोनेंट्स।\n\n"
                     . "👉 आप नीचे दिए गए **'विवरण देखें'** या **'व्हाट्सएप ऑर्डर'** बटन से इसे तुरंत आर्डर कर सकते हैं!";
            }
        }

        // 3. Price / Rate Inquiry on active product in context
        if (preg_match('/price|rate|cost|daam|kimat|kitne ka|kitne me|कीमत|दाम|रेट/iu', $msg) && !empty($products)) {
            $p = $products[0];
            $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
            return "💰 **{$p['name']}** का वर्तमान फैक्ट्री मूल्य `{$curr}" . number_format($price, 2) . "` है।\n\n"
                 . "• Cash on Delivery (COD) उपलब्ध है।\n"
                 . "• थोक (5+ पीस) आर्डर पर विशेष डिस्काउंट भी मिलता है।";
        }

        // 4. Casual / Polite Greetings & Inquiries
        if (preg_match('/^(?:kaise ho|kya haal hai|how are you|kaisa chal raha hai|sab theek)/iu', $msg)) {
            return "Namaste ji! 🙏 Main bilkul badiya hu, dhanyawad!\n\nAap batayein, aaj main aapki motor starter, submersible panel selection, live factory pricing ya order enquiry me kis tarah sahayata kar sakta hu?";
        }

        if (preg_match('/^(?:thanks|thank you|dhanyawad|shukriya|dhanyawaad|bahut dhanyawad|shukriya ji|thnx|ty)/iu', $msg)) {
            return "Aapka bahut-bahut swagat hai ji! 🙏\n\nAgar motor starter, wiring connection ya order ko lekar koi aur bhi sawal ho to bejhijhak puchiye. **{$siteName}** par hum hamesha aapki madad ke liye taiyar hain!";
        }

        if (preg_match('/^(?:ok|okay|theek hai|thik hai|acha|achha|got it|done|samajh gaya)/iu', $msg)) {
            return "Ji bilkul! 👍 Agar aapko kisi specific HP ki motor ke liye panel dekhna ho ya direct order karna ho to batayein, main turant help karunga.";
        }

        // 5. Greetings (Namaste, Hello, Hi, Ram Ram, Pranam)
        if (preg_match('/^(?:hi|hello|hey|namaste|namaskar|ram ram|radhe radhe|jai shree ram|pranam|salam|sasriakal|kem cho)/iu', $msg)) {
            return "Namaste ji! 🙏 **{$siteName}** me aapka hardik swagat hai.\n\nMain aapka personal electrical sahayak hu. Main aapko agricultural tubewell, submersible pumps aur industrial motors ke liye best starter recommend kar sakta hu.\n\n👉 Aapki motor kitne **HP** ki hai ya aap kis product ke baare me jaankari chahte hain?";
        }

        // 6. Owner / Founder / Malik / Director Queries
        if (preg_match('/malik|owner|founder|director|kiska hai|kiske dwara|banaya|sanchalak|proprietor|who is the owner|who founded|owner name|malik kaun|मालिक|संस्थापक|ओनर|फाउंडर|किसकी कंपनी|किसका है|संचालक/iu', $msg)) {
            return "👑 **सागर स्टार्टर्स के संस्थापक एवं संचालक (Founder & Owner):**\n\n"
                 . "**श्री प्रमोद कुमार सागर (Mr. Pramod Kumar Sagar)** सागर स्टार्टर्स (sagarstarters.com) के संस्थापक और मुख्य संचालक हैं।\n\n"
                 . "• **कंपनी**: Sagar Starter's (ISO Certified Quality Manufacturer)\n"
                 . "• **यूनिट / हेड ऑफिस**: अलीपुर मदरा, जखनियाँ, गाजीपुर, उत्तर प्रदेश, भारत (पिन: 275203)\n"
                 . "• **विशेषज्ञता**: उच्च गुणवत्ता वाले मोटर स्टार्टर्स, सबमर्सिबल पंप पैनल्स एवं ऑटोमेशन इक्विपमेंट्स निर्माण।\n\n"
                 . "सीधे संपर्क या बिज़नेस इंक्वायरी के लिए आप कॉल/WhatsApp (`{$phone}`) पर भी जुड़ सकते हैं!";
        }

        // 7. Address / Location / Factory Queries
        if (preg_match('/address|location|kaha par|kaha hai|kahan hai|where is your shop|store address|office|factory|dukan|shop|city|state|ghazipur|jakhanian|store location|pata kya hai|country|पता|कहा है|कहाँ है|कहाँ पर|दुकान|ऑफिस|स्थान|लोकेशन|देश/iu', $msg)) {
            $isEnglish = (bool)preg_match('/(?:where|address|shop|store|location|country|city)/i', $msg) && !preg_match('/[\x{0900}-\x{097F}]/u', $msg);

            if ($isEnglish) {
                return "📍 **Sagar Starter's Factory & Store Address:**\n\n"
                     . "• **Address**: Alipur Madra, Jakhanian, Ghazipur, Uttar Pradesh, India\n"
                     . "• **PIN Code**: 275203\n"
                     . "• **Country**: India 🇮🇳\n"
                     . "• **Phone / WhatsApp**: `{$phone}`\n"
                     . "• **Email**: `{$email}`\n"
                     . "• **Working Hours**: Monday to Saturday, 9:00 AM to 6:00 PM\n\n"
                     . "You can also search **'SAGAR STARTERS'** directly on Google Maps to navigate to our location!";
            } else {
                return "📍 **सागर स्टार्टर्स का स्टोर एवं फैक्ट्री पता:**\n\n"
                     . "• **पता**: अलीपुर मदरा, जखनियाँ, गाजीपुर (उत्तर प्रदेश)\n"
                     . "• **पिन कोड (PIN Code)**: 275203\n"
                     . "• **देश (Country)**: भारत (India 🇮🇳)\n"
                     . "• **हेल्पलाइन / व्हाट्सएप**: `{$phone}`\n"
                     . "• **ईमेल**: `{$email}`\n"
                     . "• **ऑफिस समय**: सोमवार से शनिवार, सुबह 9:00 AM से शाम 6:00 PM\n\n"
                     . "आप गूगल मैप्स पर भी **'SAGAR STARTERS'** सर्च करके आसानी से हमारी लोकेशन देख सकते हैं!";
            }
        }

        // 8. Single Phase vs 3 Phase Guidance
        if (preg_match('/single phase.*3 phase|1 phase.*3 phase|difference|kisme lagta hai|kya antar hai|phase guide/iu', $msg)) {
            return "💡 **Single Phase vs Three Phase Starter Guide:**\n\n"
                 . "1. **Single Phase (220V)**:\n"
                 . "   • यह घरेलू और छोटे कृषि बोरवेल (0.5 HP से 3 HP) के लिए उपयोगी है।\n"
                 . "   • इसमें Starting Capacitor (120/150 MFD) और Running Capacitor (36/50 MFD) लगे होते हैं।\n\n"
                 . "2. **Three Phase (415V)**:\n"
                 . "   • यह 3 HP से 30+ HP तक की भारी ट्यूबवेल, आटा चक्की और इंडस्ट्रियल मोटर्स के लिए उपयोग होता है (DOL और Automatic Star Delta मॉडल उपलब्ध)।\n\n"
                 . "👉 आपकी मोटर कितने **HP** की है? मुझे बताएं, मैं सबसे सही मॉडल सजेस्ट कर दूंगा!";
        }

        // 9. Submersible & Borewell Pumps (0.5 HP – 3 HP Single Phase)
        if (preg_match('/submersible|samarsebal|samar|borewell|khet.*pump|tubewell.*panel|1\s*hp|1\.5\s*hp|2\s*hp|3\s*hp|single\s*phase|सिंगल\s*फेज|1\s*हॉर्स|2\s*हॉर्स|3\s*हॉर्स/iu', $msg) && !preg_match('/5\s*hp|7\.5\s*hp|10\s*hp|15\s*hp|20\s*hp|star\s*delta|हॉर्सपावर/iu', $msg)) {
            $resp = "⚡ **Sagar Submersible Pump Control Panels (0.5 HP se 3 HP):**\n\n"
                  . "Single Phase बोरवेल पंप के लिए हमारे पैनल्स में निम्नलिखित विशेषताएं मिलती हैं:\n"
                  . "• **Dry Run Protection**: पानी खत्म होने पर मोटर अपने आप बंद हो जाती है।\n"
                  . "• **Overload & Low Voltage Protection**: वोल्टेज कम होने पर भी सेफ स्टार्टिंग।\n"
                  . "• **Digital Voltmeter & Ammeter**: लाइव करंट और वोल्टेज डिस्प्ले।\n"
                  . "• **Heavy Duty Capacitors & MCB**: मोटर की लंबी लाइफ के लिए।";

            if (!empty($products)) {
                $resp .= "\n\n📋 **हमारे बेस्ट-सेलिंग सबमर्सिबल स्टार्टर्स:**\n";
                foreach ($products as $p) {
                    $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                    $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
                }
                $resp .= "\n👉 आप नीचे दिए गए **'विवरण देखें'** या **'व्हाट्सएप ऑर्डर'** बटन से ऑर्डर कर सकते हैं।";
            }
            return $resp;
        }

        // 10. 3-Phase DOL Starters (3 HP – 7.5 HP)
        if (preg_match('/dol|3\s*phase.*starter|5\s*hp|7\.5\s*hp|three\s*phase\s*starter|5\s*हॉर्स|7\.5\s*हॉर्स/iu', $msg) && !preg_match('/star\s*delta|10\s*hp|15\s*hp|20\s*hp|15\s*हॉर्स/iu', $msg)) {
            $resp = "⚡ **3-Phase (415V) DOL Motor Starters (3 HP se 7.5 HP):**\n\n"
                  . "3 HP से 7.5 HP कृषि ट्यूबवेल और मोटर्स के लिए **Sagar Direct-On-Line (DOL) Starter** सबसे विश्वसनीय है:\n"
                  . "• **Thermal Overload Relay**: मोटर गरम या ओवरलोड होने पर तुरंत ट्रिप।\n"
                  . "• **Phase Failure Protection**: यदि 3 फेज में से 1 फेज कट जाता है, तो मोटर जलने से बचती है\n"
                  . "• **Heavy Copper Contacts**: लम्बे समय तक बिना मेंटेनेंस चलता है।";

            if (!empty($products)) {
                $resp .= "\n\n📋 **उपलब्ध 3-Phase स्टार्टर्स:**\n";
                foreach ($products as $p) {
                    $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                    $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
                }
            }
            return $resp;
        }

        // 11. Automatic Star Delta Starters (7.5 HP – 35 HP Heavy Duty)
        if (preg_match('/star\s*delta|delta|chakki|flour mill|atta chakki|10\s*hp|12\.5\s*hp|15\s*hp|20\s*hp|25\s*hp|30\s*hp|heavy\s*motor|हॉर्सपावर|हार्सपावर|हॉर्स\s*पावर|स्टार\s*डेल्टा/iu', $msg)) {
            $resp = "🏭 **Sagar Automatic Star Delta Starters (7.5 HP se 35 HP):**\n\n"
                  . "आटा चक्की, राइस मिल और भारी कृषि ट्यूबवेल मोटर्स के लिए Star Delta स्टार्टर आवश्यक है:\n"
                  . "• **Electronic Microcontroller Timer**: स्टार से डेल्टा में स्मूथ ऑटो-ट्रांजिशन।\n"
                  . "• **High Starting Torque Protection**: स्टार्टिंग में भारी झटका रोके।\n"
                  . "• **Phase Reversal & Voltage Surge Protection**: 100% कॉपर बसबार और हेवी कॉन्टैक्टर।";

            if (!empty($products)) {
                $resp .= "\n\n📋 **बेस्ट स्टार डेल्टा मॉडल्स:**\n";
                foreach ($products as $p) {
                    $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                    $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
                }
            }
            return $resp;
        }

        // 12. Oil Immersed Starters (Tel Wale Starter)
        if (preg_match('/oil\s*starter|oil\s*type|tel\s*wala|oil\s*immersed/iu', $msg)) {
            return "🛢️ **Sagar Oil-Immersed Motor Starter (Heavy Duty):**\n\n"
                 . "यह स्टार्टर विशेष रूप से खेतों और ट्यूबवेल के लिए डिज़ाइन किया गया है जहां वोल्टेज में उतार-चढ़ाव रहता है:\n"
                 . "• ट्रांसफॉर्मर ऑयल में डूबे कॉन्टैक्ट्स स्पार्किंग और हीटिंग को शून्य कर देते हैं।\n"
                 . "• सालों-साल बिना किसी खराबी के निर्बाध चलता है।\n"
                 . "• 1 HP से 10 HP तक के मॉडल्स फैक्ट्री रेट पर उपलब्ध हैं।";
        }

        // 13. Voltage Stabilizers & Low Voltage Solutions
        if (preg_match('/voltage|stabilizer|low\s*voltage|voltage\s*drop|dim\s*light|5\s*kva|10\s*kva|light\s*kam/iu', $msg)) {
            return "💡 **लो वोल्टेज समाधान (Sagar Automatic Copper Stabilizer):**\n\n"
                 . "यदि आपके क्षेत्र में 90V – 180V का कम वोल्टेज आता है और मोटर नहीं उठ पा रही है, तो **Sagar 5 KVA / 10 KVA Copper Stabilizer** सबसे उपयुक्त है:\n"
                 . "• लो वोल्टेज को स्टेप-अप करके मोटर को सही 220V/415V देता है।\n"
                 . "• 100% प्योर कॉपर वाइंडिंग के साथ आता है जो मोटर को ठंडा रखता है।";
        }

        // 14. Spare Parts & Components
        if (preg_match('/contactor|relay|capacitor|meter|coil|switch|mcb|spares|parts|water level|float switch|button|push button/iu', $msg)) {
            return "🔧 **Sagar Genuine Spare Parts & Components:**\n\n"
                 . "हमारे पास सभी ओरिजिनल स्पेयर पार्ट्स उपलब्ध हैं:\n"
                 . "• Heavy Duty Contactors (16A, 25A, 32A, 40A)\n"
                 . "• Thermal Overload Relays & 220V/415V Relay Coils\n"
                 . "• Motor Run & Start Capacitors (120/150 MFD, 36/50 MFD)\n"
                 . "• Digital Volt & Ampere Meters (DGT Dual Display)\n"
                 . "• Automatic Float Switches (Water Level Controller)\n\n"
                 . "👉 आप शॉप पेज से सीधे कार्ट में ऐड कर सकते हैं या WhatsApp पर अपनी स्पेयर पार्ट्स लिस्ट भेज सकते हैं!";
        }

        // 15. Payment & Cash on Delivery (COD)
        if (preg_match('/payment|cod|cash on delivery|upi|qr|google pay|phonepe|paytm|advance|paise kaise|भुगतान|कैश ऑन डिलीवरी|पैसे|ऑनलाइन पेमेंट/iu', $msg)) {
            return "💳 **भुगतान के सुरक्षित तरीके (Payment Methods):**\n\n"
                 . "• **Cash on Delivery (COD)**: पूरे भारत में उपलब्ध है। पार्सल मिलने पर आप डिलीवरी बॉय को नकद भुगतान कर सकते हैं।\n"
                 . "• **Online Payment**: UPI (PhonePe, Google Pay, Paytm), QR Code स्कैन, डेबिट/क्रेडिट कार्ड और नेट बैंकिंग 100% सुरक्षित रूप से समर्थित हैं।";
        }

        // 13. Delivery & Shipping Timelines
        if (preg_match('/delivery|shipping|kitne din|deliver|courier|dispatch|pahuch|डिलीवरी|शिपिंग|कितने दिन|कब तक/iu', $msg)) {
            return "🚚 **डिलीवरी एवं शिपिंग जानकारी (Shipping & Fast Delivery):**\n\n"
                 . "• **ऑल इंडिया डिलीवरी**: हम Delhivery, DTDC एवं एक्सप्रेस कूरियर पार्टनर्स द्वारा पूरे देश में सुरक्षित पार्सल भेजते हैं।\n"
                 . "• **समय**: आर्डर कन्फर्म होने के **3 से 7 कार्य दिवसों (Business Days)** के भीतर पार्सल आपके पते पर सुरक्षित पहुँच जाता है।\n"
                 . "• पार्सल डिस्पैच होते ही आपको लाइव ट्रैकिंग नंबर SMS एवं WhatsApp पर मिल जाता है।";
        }

        // 14. Warranty & Return Policy
        if (preg_match('/return|refund|replace|replacement|damage|kharab|warranty|guarantee|वारंटी|गारंटी|खराब|वापस|रिप्लेस/iu', $msg)) {
            return "🛡️ **वारंटी एवं क्वालिटी एश्योरेंस (Warranty Promise):**\n\n"
                 . "• **100% फैक्ट्री टेस्टेड**: हमारे सभी स्टार्टर्स और पैनल्स कड़े लोड टेस्ट के बाद ही पैक किए जाते हैं।\n"
                 . "• **ट्रांजिट रिप्लेसमेंट**: यदि डिलीवरी के समय पार्सल में कोई डैमेज मिलता है, तो हम तुरंत रिप्लेसमेंट या टेक्निकल सपोर्ट उपलब्ध कराते हैं।\n"
                 . "• सहायता के लिए संपर्क: `{$phone}`";
        }

        // 15. Bulk Purchase & Wholesale Rates
        if (preg_match('/bulk|wholesale|retailer|dealer|discount|kam price|zyada quantity|sasta|rate kam|wholesale rate/iu', $msg)) {
            return "📦 **थोक खरीद एवं डीलर विशेष डिस्काउंट (Wholesale Rates):**\n\n"
                 . "जी हाँ! दुकानदारों, डीलरों और कृषि ठेकेदारों के लिए हम **Special Factory Wholesale Rates** प्रदान करते हैं:\n"
                 . "• 5 या उससे अधिक स्टार्टर्स के आर्डर पर अतिरिक्त डिस्काउंट उपलब्ध है।\n"
                 . "• कस्टमाइज्ड कोटेशन के लिए आप अपनी क्वांटिटी के साथ सीधे हमारे WhatsApp (`{$phone}`) पर संपर्क कर सकते हैं।";
        }

        // 16. How to Order
        if (preg_match('/order kaise|kaise kharide|kaise le|kharidna hai|book karna hai|how to order|buy now/iu', $msg)) {
            return "🛒 **आर्डर करने के आसान 2 तरीके:**\n\n"
                 . "1. **वेबसाइट से**: किसी भी प्रोडक्ट के नीचे **'Buy Now'** या **'WhatsApp Order'** बटन दबाएं।\n"
                 . "2. **व्हाट्सएप से**: आप सीधे हमारे नंबर (`{$phone}`) पर अपना **नाम, पूरा पता, पिनकोड और मॉडल** लिखकर भेज दें, हम तुरंत आर्डर बुक करके पार्सल डिस्पैच करवा देंगे!";
        }

        // 17. Specific Matched Products Display
        if (!empty($products)) {
            $resp = "Maine aapke sawal ke anuroop yeh best product(s) dhundhe hain:\n\n";
            foreach ($products as $p) {
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $resp .= "• **{$p['name']}** — `{$curr}" . number_format($price, 2) . "`\n";
            }
            $resp .= "\n👉 Aap niche diye gaye **'View Details'** ya direct **'WhatsApp Order'** button se order kar sakte hain.";
            return $resp;
        }

        // 18. Natural Human Sales Fallback
        return "Ji, main aapka sawal samajh gaya. **{$siteName}** par sabhi prakaar ke Single Phase aur Three Phase Motor Starters, Submersible Panels, Voltage Stabilizers aur Genuine Spare Parts direct factory rate par uplabdh hain.\n\n👉 Aap apni motor ka **HP (jaise 5 HP)** ya **Phase (1-Phase / 3-Phase)** batayein taaki main exact model guide kar saku, ya direct hamare technical expert se **WhatsApp (`{$phone}`)** par jud sakte hain!";
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
        $model = $this->getSetting('chatbot_groq_model', 'llama-3.1-8b-instant');
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

    private function buildSystemContext(array $products): string
    {
        $siteName = $this->getSetting('site_name', "Sagar Starter's");
        $curr = $this->getSetting('currency_symbol', '₹');
        $phone = $this->getSetting('contact_phone', '+91 8573934013');
        $email = $this->getSetting('contact_email', 'support@sagarstarters.com');
        $address = 'Alipur Madra, Jakhanian, Ghazipur, Uttar Pradesh, India - PIN Code: 275203';

        $context = "You are 'Sagar Sahayak', the personal electrical sales engineer & technical assistant at Sagar Starter's (sagarstarters.com).\n"
                 . "Talk like a warm, experienced, and helpful human sales engineer. Never sound like a generic robotic chatbot.\n\n"
                 . "SAGAR STARTERS FACTORY & STORE KNOWLEDGE:\n"
                 . "- Company / Brand: {$siteName} (ISO Certified Electrical Starters Manufacturer)\n"
                 . "- Founder & Owner: Shri Pramod Kumar Sagar (Mr. Pramod Kumar Sagar)\n"
                 . "- Factory & Shop Address: {$address}\n"
                 . "- Helpline & WhatsApp: {$phone}\n"
                 . "- Email: {$email}\n"
                 . "- Country: India 🇮🇳 (Pan-India Shipping in 3-7 days via Delhivery/DTDC)\n"
                 . "- Payment: Cash on Delivery (COD) & Online (UPI, QR, Cards)\n"
                 . "- Products Made: Single Phase Submersible Panels (0.5 to 3 HP), Three Phase DOL Starters (3 to 7.5 HP), Automatic Star Delta Starters (7.5 to 35 HP for Flour Mills/Atta Chakki/Tubewell), Low Voltage Stabilizers (5 KVA/10 KVA Copper), Spare Parts (relays, contactors, capacitors, digital meters).\n\n";

        if (!empty($products)) {
            $context .= "VERIFIED LIVE DATABASE PRODUCTS MATCHING USER QUERY:\n";
            foreach ($products as $p) {
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : ($p['regular_price'] > 0 ? $p['regular_price'] : $p['price']);
                $context .= "• {$p['name']} | Price: {$curr}" . number_format($price, 2) . " | Category: " . ($p['category_name'] ?? 'Starters') . "\n";
            }
            $context .= "\nINSTRUCTION: Provide a crisp, friendly, respectful answer in the user's language (Hindi or English). Recommend the best product and mention its factory price. Product cards with Buy buttons are displayed below your reply.";
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

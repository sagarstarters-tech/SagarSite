<?php
/**
 * Sagar Starters - AI ChatBot REST API Endpoint
 * Handles live chat interactions asynchronously.
 */

header('Content-Type: application/json; charset=utf-8');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../classes/ChatbotService.php';

try {
    $chatbotService = new ChatbotService($conn);

    if (!$chatbotService->isEnabled()) {
        echo json_encode([
            'success' => false,
            'reply'   => 'ChatBot is currently offline. Please contact us via WhatsApp.'
        ]);
        exit;
    }

    // Read payload
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (empty($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'message';

    // 1. Initial Greeting / Configuration Action
    if ($action === 'init') {
        $welcomeMsg = $chatbotService->getSetting('chatbot_welcome_msg', "Namaste! 🙏 Main Sagar Starters ka AI Assistant hu. Main aapki kya madad kar sakta hu?");
        $botName = $chatbotService->getSetting('chatbot_name', 'Sagar Sahayak');
        $botTitle = $chatbotService->getSetting('chatbot_title', 'Sagar AI Assistant');
        $quickReplies = $chatbotService->getQuickReplies();
        $waPhone = !empty($chatbotService->getSetting('whatsapp_number')) ? preg_replace('/[^0-9]/', '', $chatbotService->getSetting('whatsapp_number')) : '919837248000';

        echo json_encode([
            'success'       => true,
            'bot_name'      => $botName,
            'bot_title'     => $botTitle,
            'welcome_msg'   => $welcomeMsg,
            'quick_replies' => $quickReplies,
            'wa_phone'      => $waPhone
        ]);
        exit;
    }

    // 2. Chat Message Processing Action
    $message = trim($data['message'] ?? '');
    $history = is_array($data['history'] ?? null) ? $data['history'] : [];
    $sessionId = trim($data['session_id'] ?? session_id());

    if (empty($message)) {
        echo json_encode([
            'success' => false,
            'reply'   => 'Please type a message.'
        ]);
        exit;
    }

    // Rate Limiting (Max 30 requests per minute)
    $now = time();
    if (!isset($_SESSION['chat_rate_limit'])) {
        $_SESSION['chat_rate_limit'] = ['count' => 1, 'start' => $now];
    } else {
        if ($now - $_SESSION['chat_rate_limit']['start'] < 60) {
            $_SESSION['chat_rate_limit']['count']++;
            if ($_SESSION['chat_rate_limit']['count'] > 35) {
                echo json_encode([
                    'success' => true,
                    'reply'   => "⚠️ Aap bohot tezi se message bhej rahe hain. Kripya 1 minute baad dobara koshish karein.",
                    'products'=> []
                ]);
                exit;
            }
        } else {
            $_SESSION['chat_rate_limit'] = ['count' => 1, 'start' => $now];
        }
    }

    // Process Message
    $response = $chatbotService->processMessage($message, $history, $sessionId);
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    error_log("Chatbot API Exception: " . $e->getMessage());
    echo json_encode([
        'success'  => false,
        'reply'    => "Namaste! Technical issue ke karan response me thoda samay lag raha hai. Aap direct WhatsApp par humse baat kar sakte hain.",
        'products' => []
    ]);
    exit;
}

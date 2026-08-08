<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class TelegramAdapter implements PlatformAdapterInterface {
    public function getPlatformName(): string {
        return 'Telegram';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-telegram-plane';
    }

    public function getPlatformColor(): string {
        return '#0088CC';
    }
    
    public function requiresOAuth(): bool {
        return false;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        return '';
    }

    public function authenticate(string $code, array $params = []): array {
        $botToken = $code; // Expecting the bot token directly here
        
        $res = $this->curlRequest("https://api.telegram.org/bot{$botToken}/getMe");
        
        if (isset($res['ok']) && $res['ok'] === true) {
            return [
                'bot_token' => $botToken,
                'channel_id' => $params['channel_id'] ?? '',
                'bot_username' => $res['result']['username'] ?? ''
            ];
        }
        
        return ['error' => 'Invalid Telegram Bot Token'];
    }

    public function refreshToken(array $account): array {
        return $account;
    }

    public function publishPost(array $postData): array {
        $botToken = $postData['bot_token'] ?? '';
        $channelId = $postData['channel_id'] ?? '';
        $text = $postData['message'] ?? '';
        $imageUrl = $postData['image_url'] ?? '';

        if (!$botToken || !$channelId) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing bot token or channel ID'];
        }
        
        $data = [
            'chat_id' => $channelId,
            'caption' => $text,
            'parse_mode' => 'HTML'
        ];
        
        $endpoint = 'sendMessage';
        if ($imageUrl) {
            $endpoint = 'sendPhoto';
            $data['photo'] = $imageUrl;
        } else {
            $data['text'] = $text;
            unset($data['caption']);
        }
        
        $res = $this->curlRequest("https://api.telegram.org/bot{$botToken}/{$endpoint}", 'POST', $data);

        if (!isset($res['ok']) || $res['ok'] !== true) {
            error_log('Telegram Publish Error: ' . json_encode($res));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $res['description'] ?? 'Failed to publish post'];
        }

        $postId = $res['result']['message_id'] ?? null;
        
        return [
            'success' => true,
            'post_id' => (string)$postId,
            'post_url' => $this->getPostUrl((string)$postId),
            'error' => null
        ];
    }

    public function validateConnection(array $account): bool {
        $botToken = $account['bot_token'] ?? '';
        if (!$botToken) return false;

        $res = $this->curlRequest("https://api.telegram.org/bot{$botToken}/getMe");
        return isset($res['ok']) && $res['ok'] === true;
    }

    public function getPostUrl(string $platformPostId): string {
        return ''; // Cannot accurately construct URL without channel username
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 4096,
            'max_images' => 1,
            'rate_limit' => '30 msg/sec'
        ];
    }

    private function curlRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array {
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => ['message' => $error]];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['error' => ['message' => 'Invalid or empty JSON response']];
    }
}

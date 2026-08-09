<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class PinterestAdapter implements PlatformAdapterInterface {
    public function getPlatformName(): string {
        return 'Pinterest';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-pinterest-p';
    }

    public function getPlatformColor(): string {
        return '#E60023';
    }
    
    public function requiresOAuth(): bool {
        return true;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        $clientId = _env('PINTEREST_APP_ID') ?: _env('PINTEREST_CLIENT_ID');
        $scopes = ['boards:read', 'boards:write', 'pins:read', 'pins:write', 'user_accounts:read'];
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state
        ];
        return "https://www.pinterest.com/oauth/?" . http_build_query($params);
    }

    public function authenticate(string $code, array $params = []): array {
        $clientId = _env('PINTEREST_APP_ID') ?: _env('PINTEREST_CLIENT_ID');
        $clientSecret = _env('PINTEREST_APP_SECRET') ?: _env('PINTEREST_CLIENT_SECRET');
        $redirectUri = $params['redirect_uri'] ?? '';
        
        $tokenUrl = 'https://api.pinterest.com/v5/oauth/token';
        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $res = $this->curlRequest($tokenUrl, 'POST', http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri
        ]), $headers);

        if (isset($res['error'])) {
            $errMsg = $res['error_description'] ?? $res['message'] ?? (is_string($res['error']) ? $res['error'] : json_encode($res['error']));
            error_log('Pinterest Auth Error: ' . json_encode($res));
            return ['error' => 'Pinterest Auth Error: ' . $errMsg];
        }
        
        return [
            'access_token' => $res['access_token'] ?? '',
            'refresh_token' => $res['refresh_token'] ?? ''
        ];
    }

    public function refreshToken(array $account): array {
        $clientId = _env('PINTEREST_CLIENT_ID') ?? '';
        $clientSecret = _env('PINTEREST_CLIENT_SECRET') ?? '';
        $refreshToken = $account['refresh_token'] ?? '';
        
        if (!$refreshToken) return ['error' => 'No refresh token available'];
        
        $tokenUrl = 'https://api.pinterest.com/v5/oauth/token';
        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $res = $this->curlRequest($tokenUrl, 'POST', http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ]), $headers);

        if (isset($res['error'])) {
            error_log('Pinterest Refresh Error: ' . json_encode($res));
            return ['error' => 'Failed to refresh token'];
        }
        
        return [
            'access_token' => $res['access_token'] ?? '',
            'refresh_token' => $res['refresh_token'] ?? ''
        ];
    }

    public function publishPost(array $postData): array {
        $accessToken = $postData['access_token'] ?? '';
        $boardId = $postData['board_id'] ?? '';
        $title = $postData['title'] ?? '';
        $description = $postData['message'] ?? '';
        $imageUrl = $postData['image_url'] ?? '';
        $link = $postData['link'] ?? '';

        if (!$accessToken || !$boardId || !$imageUrl) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing access token, board ID, or image URL'];
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $data = [
            'board_id' => $boardId,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $imageUrl
            ],
            'title' => substr($title, 0, 100),
            'description' => substr($description, 0, 500),
            'link' => $link
        ];
        
        $postUrl = 'https://api.pinterest.com/v5/pins';
        $res = $this->curlRequest($postUrl, 'POST', json_encode($data), $headers);

        if (isset($res['code']) && $res['code'] !== 0) {
            error_log('Pinterest Publish Error: ' . json_encode($res));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $res['message'] ?? 'Failed to publish post'];
        }

        $postId = $res['id'] ?? null;
        
        return [
            'success' => true,
            'post_id' => (string)$postId,
            'post_url' => $this->getPostUrl((string)$postId),
            'error' => null
        ];
    }

    public function getUserProfile(string $accessToken): array {
        if (!$accessToken) return [];
        return $this->curlRequest('https://api.pinterest.com/v5/user_account', 'GET', [], [
            'Authorization: Bearer ' . $accessToken
        ]);
    }

    public function validateConnection(array $account): bool {
        $accessToken = $account['access_token'] ?? '';
        if (!$accessToken) return false;

        $res = $this->getUserProfile($accessToken);
        return !isset($res['code']);
    }

    public function getPostUrl(string $platformPostId): string {
        return $platformPostId ? "https://www.pinterest.com/pin/$platformPostId/" : '';
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 500,
            'max_images' => 1,
            'rate_limit' => '1000 calls/day'
        ];
    }

    private function curlRequest(string $url, string $method = 'GET', $data = null, array $headers = []): array {
        $ch = curl_init();
        
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
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

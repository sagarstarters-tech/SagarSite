<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class TwitterAdapter implements PlatformAdapterInterface {
    public function getPlatformName(): string {
        return 'X (Twitter)';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-x-twitter';
    }

    public function getPlatformColor(): string {
        return '#000000';
    }
    
    public function requiresOAuth(): bool {
        return true;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        $clientId = _env('TWITTER_CLIENT_ID') ?? '';
        $scopes = ['tweet.read', 'tweet.write', 'users.read', 'offline.access'];
        $codeChallenge = bin2hex(random_bytes(32));
        
        // Normally code_verifier should be stored in session, we expect caller to handle this based on state
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['twitter_code_verifier'] = $codeChallenge;
        
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'plain'
        ];
        
        return "https://twitter.com/i/oauth2/authorize?" . http_build_query($params);
    }

    public function authenticate(string $code, array $params = []): array {
        $clientId = _env('TWITTER_CLIENT_ID') ?? '';
        $clientSecret = _env('TWITTER_CLIENT_SECRET') ?? '';
        $redirectUri = $params['redirect_uri'] ?? '';
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $codeVerifier = $_SESSION['twitter_code_verifier'] ?? '';
        
        $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ];
        
        $res = $this->curlRequest($tokenUrl, 'POST', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier
        ], $headers);

        if (isset($res['error'])) {
            error_log('Twitter Auth Error: ' . json_encode($res['error']));
            return ['error' => $res['error_description'] ?? 'Authentication failed'];
        }
        
        return [
            'access_token' => $res['access_token'] ?? '',
            'refresh_token' => $res['refresh_token'] ?? ''
        ];
    }

    public function refreshToken(array $account): array {
        $clientId = _env('TWITTER_CLIENT_ID') ?? '';
        $clientSecret = _env('TWITTER_CLIENT_SECRET') ?? '';
        $refreshToken = $account['refresh_token'] ?? '';
        
        if (!$refreshToken) return ['error' => 'No refresh token available'];
        
        $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ];
        
        $res = $this->curlRequest($tokenUrl, 'POST', [
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $clientId
        ], $headers);

        if (isset($res['error'])) {
            error_log('Twitter Refresh Error: ' . json_encode($res['error']));
            return ['error' => 'Failed to refresh token'];
        }
        
        return [
            'access_token' => $res['access_token'] ?? '',
            'refresh_token' => $res['refresh_token'] ?? ''
        ];
    }

    public function publishPost(array $postData): array {
        $accessToken = $postData['access_token'] ?? '';
        $text = $postData['message'] ?? '';
        $imageUrl = $postData['image_url'] ?? '';

        if (!$accessToken) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing access token'];
        }
        
        $mediaIds = [];
        
        // Note: Actual implementation would require downloading the image and uploading to upload.twitter.com
        // This is a simplified version just returning the basic structure as requested
        
        $tweetUrl = 'https://api.twitter.com/2/tweets';
        $data = ['text' => $text];
        if (!empty($mediaIds)) {
            $data['media'] = ['media_ids' => $mediaIds];
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $res = $this->curlRequest($tweetUrl, 'POST', json_encode($data), $headers);

        if (isset($res['error']) || isset($res['errors'])) {
            error_log('Twitter Publish Error: ' . json_encode($res));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Failed to publish tweet'];
        }

        $postId = $res['data']['id'] ?? null;
        
        return [
            'success' => true,
            'post_id' => (string)$postId,
            'post_url' => $this->getPostUrl((string)$postId),
            'error' => null
        ];
    }

    public function validateConnection(array $account): bool {
        $accessToken = $account['access_token'] ?? '';
        if (!$accessToken) return false;

        $res = $this->curlRequest('https://api.twitter.com/2/users/me', 'GET', [], [
            'Authorization: Bearer ' . $accessToken
        ]);
        return !isset($res['error']) && !isset($res['errors']);
    }

    public function getPostUrl(string $platformPostId): string {
        return $platformPostId ? "https://twitter.com/i/web/status/$platformPostId" : '';
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 280,
            'max_images' => 4,
            'rate_limit' => '300 tweets/3hr'
        ];
    }

    private function curlRequest(string $url, string $method = 'GET', $data = null, array $headers = []): array {
        $ch = curl_init();
        
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                if (is_array($data) && !in_array('Content-Type: application/json', $headers)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
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
        return $decoded ?? ['error' => ['message' => 'Invalid JSON response']];
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class FacebookAdapter implements PlatformAdapterInterface {
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com/' . self::API_VERSION;

    public function getPlatformName(): string {
        return 'Facebook';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-facebook-f';
    }

    public function getPlatformColor(): string {
        return '#1877F2';
    }
    
    public function requiresOAuth(): bool {
        return true;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        $appId = _env('FACEBOOK_APP_ID') ?? '';
        $scopes = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'publish_video'];
        $url = "https://www.facebook.com/" . self::API_VERSION . "/dialog/oauth?";
        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
            'response_type' => 'code'
        ];
        return $url . http_build_query($params);
    }

    public function authenticate(string $code, array $params = []): array {
        $appId = _env('FACEBOOK_APP_ID') ?? '';
        $appSecret = _env('FACEBOOK_APP_SECRET') ?? '';
        $redirectUri = $params['redirect_uri'] ?? '';

        $tokenUrl = self::BASE_URL . '/oauth/access_token';
        $tokenRes = $this->curlRequest($tokenUrl, 'GET', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code
        ]);

        if (isset($tokenRes['error'])) {
            error_log('Facebook Auth Error: ' . json_encode($tokenRes['error']));
            return ['error' => $tokenRes['error']['message'] ?? 'Authentication failed'];
        }
        
        $userToken = $tokenRes['access_token'] ?? '';
        
        $pagesRes = $this->curlRequest(self::BASE_URL . '/me/accounts', 'GET', [
            'access_token' => $userToken
        ]);

        if (isset($pagesRes['error'])) {
             error_log('Facebook Pages Error: ' . json_encode($pagesRes['error']));
             return ['error' => 'Could not fetch pages'];
        }

        return ['accounts' => $pagesRes['data'] ?? []];
    }

    public function refreshToken(array $account): array {
        return $account;
    }

    public function publishPost(array $postData): array {
        $pageId = $postData['page_id'] ?? '';
        $accessToken = $postData['access_token'] ?? '';
        $message = $postData['message'] ?? '';
        $imageUrl = $postData['image_url'] ?? '';
        $link = $postData['link'] ?? '';

        if (!$pageId || !$accessToken) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing page ID or access token'];
        }

        $endpoint = $imageUrl ? "/$pageId/photos" : "/$pageId/feed";
        $data = [
            'access_token' => $accessToken,
            'message' => $message
        ];

        if ($imageUrl) {
            $data['url'] = $imageUrl;
        } elseif ($link) {
            $data['link'] = $link;
        }

        $res = $this->curlRequest(self::BASE_URL . $endpoint, 'POST', $data);

        if (isset($res['error'])) {
            $errorMsg = is_array($res['error']) ? ($res['error']['message'] ?? 'Unknown error') : 'Unknown error';
            error_log('Facebook Publish Error: ' . json_encode($res['error']));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $errorMsg];
        }

        $postId = $res['id'] ?? ($res['post_id'] ?? null);
        $postUrl = $this->getPostUrl((string)$postId);

        return [
            'success' => true,
            'post_id' => (string)$postId,
            'post_url' => $postUrl,
            'error' => null
        ];
    }

    public function validateConnection(array $account): bool {
        $accessToken = $account['access_token'] ?? '';
        if (!$accessToken) return false;

        $res = $this->curlRequest(self::BASE_URL . '/me', 'GET', ['access_token' => $accessToken]);
        return !isset($res['error']) && isset($res['id']);
    }

    public function getPostUrl(string $platformPostId): string {
        if (!$platformPostId) return '';
        $parts = explode('_', $platformPostId);
        if (count($parts) === 2) {
            return "https://facebook.com/{$parts[0]}/posts/{$parts[1]}";
        }
        return "https://facebook.com/$platformPostId";
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 63206,
            'max_images' => 10,
            'rate_limit' => '200 calls/hour'
        ];
    }

    private function curlRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array {
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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

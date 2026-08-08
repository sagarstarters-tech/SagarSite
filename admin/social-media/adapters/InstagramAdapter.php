<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class InstagramAdapter implements PlatformAdapterInterface {
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com/' . self::API_VERSION;

    public function getPlatformName(): string {
        return 'Instagram';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-instagram';
    }

    public function getPlatformColor(): string {
        return '#E4405F';
    }
    
    public function requiresOAuth(): bool {
        return true;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        $appId = _env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID');
        $scopes = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'instagram_basic', 'instagram_content_publish'];
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
        $appId = _env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID');
        $appSecret = _env('FB_APP_SECRET') ?: _env('FACEBOOK_APP_SECRET');
        $redirectUri = $params['redirect_uri'] ?? '';

        $tokenUrl = self::BASE_URL . '/oauth/access_token';
        $tokenRes = $this->curlRequest($tokenUrl, 'GET', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code
        ]);

        if (isset($tokenRes['error'])) {
            error_log('Instagram Auth Error: ' . json_encode($tokenRes['error']));
            return ['error' => $tokenRes['error']['message'] ?? 'Authentication failed'];
        }
        
        $userToken = $tokenRes['access_token'] ?? '';
        
        $pagesRes = $this->curlRequest(self::BASE_URL . '/me/accounts', 'GET', [
            'fields' => 'instagram_business_account,name,access_token',
            'access_token' => $userToken
        ]);

        if (isset($pagesRes['error'])) {
             error_log('Instagram Pages Error: ' . json_encode($pagesRes['error']));
             return ['error' => 'Could not fetch pages'];
        }

        $igAccounts = [];
        foreach (($pagesRes['data'] ?? []) as $page) {
            if (isset($page['instagram_business_account'])) {
                $igAccounts[] = [
                    'ig_id' => $page['instagram_business_account']['id'],
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'access_token' => $page['access_token']
                ];
            }
        }

        return ['accounts' => $igAccounts];
    }

    public function refreshToken(array $account): array {
        return $account;
    }

    public function publishPost(array $postData): array {
        $igUserId = $postData['ig_user_id'] ?? '';
        $accessToken = $postData['access_token'] ?? '';
        $caption = $postData['caption'] ?? ($postData['message'] ?? '');
        $imageUrl = $postData['image_url'] ?? '';

        if (!$igUserId || !$accessToken || !$imageUrl) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing IG User ID, access token, or image URL'];
        }

        // 1. Create Media Container
        $containerRes = $this->curlRequest(self::BASE_URL . "/$igUserId/media", 'POST', [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken
        ]);

        if (isset($containerRes['error'])) {
            error_log('Instagram Media Container Error: ' . json_encode($containerRes['error']));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $containerRes['error']['message'] ?? 'Failed to create media container'];
        }

        $creationId = $containerRes['id'] ?? null;
        if (!$creationId) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'No creation ID returned'];
        }

        // 2. Publish Container
        $publishRes = $this->curlRequest(self::BASE_URL . "/$igUserId/media_publish", 'POST', [
            'creation_id' => $creationId,
            'access_token' => $accessToken
        ]);

        if (isset($publishRes['error'])) {
            error_log('Instagram Media Publish Error: ' . json_encode($publishRes['error']));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $publishRes['error']['message'] ?? 'Failed to publish media'];
        }

        $postId = $publishRes['id'] ?? null;
        
        return [
            'success' => true,
            'post_id' => (string)$postId,
            'post_url' => $this->getPostUrl((string)$postId),
            'error' => null
        ];
    }

    public function validateConnection(array $account): bool {
        $igUserId = $account['ig_user_id'] ?? '';
        $accessToken = $account['access_token'] ?? '';
        if (!$igUserId || !$accessToken) return false;

        $res = $this->curlRequest(self::BASE_URL . "/$igUserId", 'GET', [
            'fields' => 'id,username',
            'access_token' => $accessToken
        ]);
        return !isset($res['error']) && isset($res['id']);
    }

    public function getPostUrl(string $platformPostId): string {
        return $platformPostId ? "https://instagram.com/p/$platformPostId" : '';
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 2200,
            'max_images' => 10,
            'rate_limit' => '25 posts/24hr'
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
        return is_array($decoded) ? $decoded : ['error' => ['message' => 'Invalid or empty JSON response']];
    }
}

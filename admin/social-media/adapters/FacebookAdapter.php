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
        $appId = _env('FB_APP_ID') ?: _env('FACEBOOK_APP_ID');
        $scopes = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'publish_video', 'instagram_basic', 'instagram_content_publish'];
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

        if (!$pageId || !$accessToken) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing page ID or access token'];
        }

        $isValidImageUrl = !empty($imageUrl) 
            && (strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0) 
            && strpos($imageUrl, 'localhost') === false 
            && strpos($imageUrl, '127.0.0.1') === false;

        $res = null;

        // 1. If valid image URL, attempt posting to /photos
        if ($isValidImageUrl) {
            $res = $this->curlRequest(self::BASE_URL . "/$pageId/photos", 'POST', [
                'access_token' => $accessToken,
                'url'          => $imageUrl,
                'caption'      => $message,
                'published'    => 'true'
            ]);
        }

        // 2. If no image or if /photos endpoint returned error (e.g. Invalid parameter), fallback to /feed endpoint
        if (!$isValidImageUrl || isset($res['error'])) {
            $photoError = isset($res['error']) ? (is_array($res['error']) ? ($res['error']['message'] ?? '') : '') : '';
            $photoErrCode = isset($res['error']) && is_array($res['error']) ? ($res['error']['code'] ?? 0) : 0;
            
            // Check if photo error is a rate limit / spam protection / action block error
            $isRateLimit = $this->isRateLimitError($photoError, $photoErrCode);
            if ($isRateLimit) {
                error_log('Facebook Publish Rate Limit Error: ' . json_encode($res['error']));
                return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $photoError, 'is_rate_limit' => true];
            }
            
            $feedRes = $this->curlRequest(self::BASE_URL . "/$pageId/feed", 'POST', [
                'access_token' => $accessToken,
                'message'      => $message
            ]);

            if (!isset($feedRes['error'])) {
                $res = $feedRes;
            } elseif (!$isValidImageUrl) {
                $res = $feedRes;
            } else {
                // Return clear error if both endpoints failed
                $feedError = is_array($feedRes['error']) ? ($feedRes['error']['message'] ?? 'Unknown error') : 'Unknown error';
                $feedErrCode = is_array($feedRes['error']) ? ($feedRes['error']['code'] ?? 0) : 0;
                $isFeedRateLimit = $this->isRateLimitError($feedError, $feedErrCode);
                $errorDetails = !empty($photoError) ? "Photo error: {$photoError} | Feed error: {$feedError}" : $feedError;
                return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $errorDetails, 'is_rate_limit' => $isFeedRateLimit];
            }
        }

        if (isset($res['error'])) {
            $errorMsg = is_array($res['error']) ? ($res['error']['message'] ?? 'Unknown error') : 'Unknown error';
            $errCode = is_array($res['error']) ? ($res['error']['code'] ?? 0) : 0;
            $isRateLimit = $this->isRateLimitError($errorMsg, $errCode);
            error_log('Facebook Publish Error: ' . json_encode($res['error']));
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => $errorMsg, 'is_rate_limit' => $isRateLimit];
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

    public function isRateLimitError(string $msg, int $code = 0): bool {
        $msgLower = strtolower($msg);
        $rateLimitPhrases = [
            'limit how often',
            'too many actions',
            'action block',
            'rate limit',
            'please try again later',
            'protect the community from spam',
            'user is performing too many actions',
            'request limit reached'
        ];
        foreach ($rateLimitPhrases as $phrase) {
            if (strpos($msgLower, $phrase) !== false) {
                return true;
            }
        }
        if (in_array($code, [4, 17, 32, 368], true)) {
            return true;
        }
        return false;
    }
}

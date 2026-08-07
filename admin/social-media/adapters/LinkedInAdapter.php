<?php

declare(strict_types=1);

require_once __DIR__ . '/PlatformAdapterInterface.php';

class LinkedInAdapter implements PlatformAdapterInterface {
    public function getPlatformName(): string {
        return 'LinkedIn';
    }

    public function getPlatformIcon(): string {
        return 'fab fa-linkedin-in';
    }

    public function getPlatformColor(): string {
        return '#0A66C2';
    }
    
    public function requiresOAuth(): bool {
        return true;
    }

    public function getAuthUrl(string $redirectUri, string $state): string {
        $clientId = _env('LINKEDIN_CLIENT_ID') ?? '';
        // Scopes for 'Sign In with LinkedIn using OpenID Connect' and 'Share on LinkedIn'
        $scopes = ['openid', 'profile', 'email', 'w_member_social'];
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $scopes)
        ];
        return "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query($params);
    }

    public function authenticate(string $code, array $params = []): array {
        $clientId = _env('LINKEDIN_CLIENT_ID') ?? '';
        $clientSecret = _env('LINKEDIN_CLIENT_SECRET') ?? '';
        $redirectUri = $params['redirect_uri'] ?? '';
        
        $tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
        $res = $this->curlRequest($tokenUrl, 'POST', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ], ['Content-Type: application/x-www-form-urlencoded']);

        if (isset($res['error'])) {
            error_log('LinkedIn Auth Error: ' . json_encode($res));
            return ['error' => $res['error_description'] ?? 'Authentication failed'];
        }
        
        $accessToken = $res['access_token'] ?? '';
        if (!$accessToken) {
            return ['error' => 'No access token received'];
        }
        
        $sub = '';
        $name = '';

        // 1. Try decoding id_token JWT if OpenID Connect returned it
        if (!empty($res['id_token'])) {
            $parts = explode('.', $res['id_token']);
            if (isset($parts[1])) {
                $jwtPayload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (!empty($jwtPayload['sub'])) {
                    $sub = $jwtPayload['sub'];
                    $name = $jwtPayload['name'] ?? ($jwtPayload['given_name'] ?? 'LinkedIn Member');
                }
            }
        }

        // 2. Fetch Profile URN via userinfo (OpenID) or /v2/me fallback
        if (!$sub) {
            $profileRes = $this->curlRequest('https://api.linkedin.com/v2/userinfo', 'GET', [], [
                'Authorization: Bearer ' . $accessToken
            ]);
            $sub = $profileRes['sub'] ?? '';
            $name = $profileRes['name'] ?? ($profileRes['given_name'] ?? 'LinkedIn User');
        }

        if (!$sub) {
            $meRes = $this->curlRequest('https://api.linkedin.com/v2/me', 'GET', [], [
                'Authorization: Bearer ' . $accessToken
            ]);
            $sub = $meRes['id'] ?? '';
            $name = isset($meRes['localizedFirstName']) ? ($meRes['localizedFirstName'] . ' ' . ($meRes['localizedLastName'] ?? '')) : 'LinkedIn Member';
        }

        if (!$sub) {
            // Fallback if scope does not allow reading full profile info
            $sub = 'li_user_' . substr(md5($accessToken), 0, 8);
            $name = 'LinkedIn Account';
        }
        
        return [
            'access_token' => $accessToken,
            'person_urn' => 'urn:li:person:' . $sub,
            'account_id' => $sub,
            'account_name' => $name
        ];
    }

    public function refreshToken(array $account): array {
        return $account;
    }

    public function publishPost(array $postData): array {
        $accessToken = $postData['access_token'] ?? '';
        $personUrn = $postData['person_urn'] ?? $postData['account_id'] ?? $postData['page_id'] ?? '';
        $text = $postData['message'] ?? '';
        $imageUrl = $postData['image_url'] ?? '';

        if ($personUrn && strpos($personUrn, 'urn:li:') !== 0) {
            $personUrn = 'urn:li:person:' . $personUrn;
        }

        if (!$accessToken || !$personUrn) {
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'Missing access token or Person URN'];
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];
        
        $shareContent = [
            'shareCommentary' => ['text' => $text],
            'shareMediaCategory' => 'NONE'
        ];

        if (!empty($imageUrl)) {
            $shareContent['shareMediaCategory'] = 'ARTICLE';
            $shareContent['media'] = [
                [
                    'status' => 'READY',
                    'originalUrl' => $imageUrl,
                    'title' => ['text' => mb_substr($text, 0, 100)]
                ]
            ];
        }

        $data = [
            'author' => $personUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => $shareContent
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC']
        ];
        
        $postUrl = 'https://api.linkedin.com/v2/ugcPosts';
        $res = $this->curlRequest($postUrl, 'POST', json_encode($data), $headers);

        if (isset($res['error']) || isset($res['message']) || !isset($res['id'])) {
            $errorMsg = $res['message'] ?? ($res['error']['message'] ?? (json_encode($res)));
            error_log('LinkedIn Publish Error: ' . $errorMsg);
            return ['success' => false, 'post_id' => null, 'post_url' => null, 'error' => 'LinkedIn API Error: ' . $errorMsg];
        }

        $postId = $res['id'] ?? null;
        
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

        $res = $this->curlRequest('https://api.linkedin.com/v2/me', 'GET', [], [
            'Authorization: Bearer ' . $accessToken
        ]);
        return !isset($res['error']) && isset($res['id']);
    }

    public function getPostUrl(string $platformPostId): string {
        return $platformPostId ? "https://www.linkedin.com/feed/update/$platformPostId" : '';
    }

    public function getPlatformLimits(): array {
        return [
            'max_chars' => 3000,
            'max_images' => 9,
            'rate_limit' => '100 calls/day'
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

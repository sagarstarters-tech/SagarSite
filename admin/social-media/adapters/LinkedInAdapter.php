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

        $link = $postData['link'] ?? '';
        $assetUrn = null;

        if (!empty($imageUrl)) {
            if (strpos($imageUrl, 'http') !== 0) {
                $siteUrl = rtrim(defined('SITE_URL') ? SITE_URL : 'https://www.sagarstarters.com', '/');
                $imageUrl = $siteUrl . '/' . ltrim($imageUrl, '/');
            }
            
            // Upload product image asset directly to LinkedIn
            $assetUrn = $this->uploadImageAsset($accessToken, $personUrn, $imageUrl);
        }

        if ($assetUrn) {
            $shareContent['shareMediaCategory'] = 'IMAGE';
            $shareContent['media'] = [
                [
                    'status' => 'READY',
                    'media' => $assetUrn,
                    'title' => ['text' => mb_substr($text, 0, 100)]
                ]
            ];
        } elseif (!empty($link)) {
            $shareContent['shareMediaCategory'] = 'ARTICLE';
            $shareContent['media'] = [
                [
                    'status' => 'READY',
                    'originalUrl' => $link,
                    'title' => ['text' => mb_substr($text, 0, 100)],
                    'description' => ['text' => mb_substr($text, 0, 200)]
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

    private function uploadImageAsset(string $accessToken, string $personUrn, string $imageUrl): ?string {
        // 1. Download product image binary data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $imageData = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$imageData || $httpCode !== 200) {
            error_log("LinkedIn Image Download Failed ($httpCode): $imageUrl");
            return null;
        }

        // 2. Register Upload Request with LinkedIn Assets API
        $registerUrl = 'https://api.linkedin.com/v2/assets?action=registerUpload';
        $registerPayload = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $personUrn,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent'
                    ]
                ]
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];

        $res = $this->curlRequest($registerUrl, 'POST', json_encode($registerPayload), $headers);

        $uploadUrl = null;
        $assetUrn  = null;

        if (isset($res['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'])) {
            $uploadUrl = $res['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn  = $res['value']['asset'] ?? null;
        }

        if (!$uploadUrl || !$assetUrn) {
            error_log('LinkedIn Asset Register Failed: ' . json_encode($res));
            return null;
        }

        // 3. Upload Raw Image Bytes to uploadUrl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/octet-stream'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $putCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($putCode >= 200 && $putCode < 300) {
            return $assetUrn;
        }

        error_log("LinkedIn Image PUT Failed ($putCode) for Asset: $assetUrn");
        return null;
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

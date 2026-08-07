<?php

declare(strict_types=1);

interface PlatformAdapterInterface {
    public function getPlatformName(): string;
    public function getPlatformIcon(): string; // Font Awesome class
    public function getPlatformColor(): string; // Hex color
    public function authenticate(string $code, array $params = []): array; // Returns account data
    public function refreshToken(array $account): array; // Returns updated token data
    public function publishPost(array $postData): array; // Returns ['success' => bool, 'post_id' => string, 'post_url' => string, 'error' => string]
    public function validateConnection(array $account): bool;
    public function getPostUrl(string $platformPostId): string;
    public function getPlatformLimits(): array; // ['max_chars' => int, 'max_images' => int, 'rate_limit' => string]
    public function getAuthUrl(string $redirectUri, string $state): string; // OAuth URL
    public function requiresOAuth(): bool;
}

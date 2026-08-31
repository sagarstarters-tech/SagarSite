<?php
declare(strict_types=1);

namespace CourierModule\Adapters;

use CourierModule\Contracts\CourierInterface;
use CourierModule\Services\CourierCryptoService;
use PDO;
use Throwable;

/**
 * Class BaseCourierAdapter
 * Abstract base class providing common cURL client, sanitization,
 * logging, and credential management across all courier providers.
 */
abstract class BaseCourierAdapter implements CourierInterface
{
    protected PDO $pdo;
    protected array $integration;
    protected string $apiBaseUrl;
    protected ?string $apiToken = null;
    protected ?string $apiKey = null;
    protected ?string $apiSecret = null;
    protected int $timeoutSeconds = 12;

    public function __construct(PDO $pdo, array $integration)
    {
        $this->pdo = $pdo;
        $this->integration = $integration;
        $this->apiBaseUrl = rtrim($integration['api_base_url'] ?? '', '/') . '/';
        
        // Decrypt credentials
        $this->apiToken = CourierCryptoService::decrypt($integration['api_token'] ?? null);
        $this->apiKey = CourierCryptoService::decrypt($integration['api_key'] ?? null);
        $this->apiSecret = CourierCryptoService::decrypt($integration['api_secret'] ?? null);
    }

    /**
     * Get the integration database ID
     */
    public function getIntegrationId(): int
    {
        return (int)($this->integration['id'] ?? 0);
    }

    /**
     * Dynamically override Bearer token for immediate AJAX test/creation
     */
    public function setApiToken(string $token): void
    {
        $this->apiToken = trim($token);
    }

    /**
     * Execute secure HTTP request with automatic logging and error handling
     */
    protected function request(
        string $endpoint,
        string $method = 'GET',
        ?array $payload = null,
        array $headers = [],
        ?int $orderId = null
    ): array {
        $url = (strpos($endpoint, 'http://') === 0 || strpos($endpoint, 'https://') === 0)
            ? $endpoint
            : $this->apiBaseUrl . ltrim($endpoint, '/');

        $method = strtoupper($method);
        $ch = curl_init();

        $defaultHeaders = [
            'Accept: application/json',
            'User-Agent: SagarStarters-Ecommerce/2.0'
        ];

        if (!empty($this->apiToken)) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->apiToken;
        }

        $allHeaders = array_merge($defaultHeaders, $headers);
        $jsonPayload = ($payload !== null) ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        if ($jsonPayload !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $allHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $startTime = microtime(true);
        $rawResponse = curl_exec($ch);
        $durationMs = (int)round((microtime(true) - $startTime) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decodedResponse = [];
        if ($rawResponse !== false && $rawResponse !== '') {
            $decodedResponse = json_decode($rawResponse, true) ?? [];
        }

        // Log this request to courier_api_logs
        $this->logApiCall($orderId, $url, $method, $httpCode, $payload, $decodedResponse ?: ['raw' => $rawResponse, 'error' => $curlError], $durationMs);

        if ($rawResponse === false || !empty($curlError)) {
            return [
                'success'    => false,
                'http_code'  => 0,
                'error'      => 'cURL Connection Error: ' . $curlError,
                'message'    => 'Failed to connect to ' . $this->getProviderCode() . ' API (' . $curlError . ')',
                'data'       => null,
                'raw'        => null
            ];
        }

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success'    => $isSuccess,
            'http_code'  => $httpCode,
            'data'       => $decodedResponse,
            'raw'        => $rawResponse,
            'message'    => $decodedResponse['message'] ?? ($isSuccess ? 'Success' : 'API returned HTTP ' . $httpCode)
        ];
    }

    /**
     * Sanitize and save API request/response in courier_api_logs
     */
    protected function logApiCall(
        ?int $orderId,
        string $url,
        string $method,
        int $httpCode,
        ?array $requestData,
        array $responseData,
        int $durationMs
    ): void {
        try {
            // Mask sensitive fields in request
            $sanitizedRequest = $requestData;
            if (is_array($sanitizedRequest)) {
                array_walk_recursive($sanitizedRequest, function (&$val, $key) {
                    if (in_array(strtolower((string)$key), ['password', 'token', 'secret', 'key', 'authorization', 'api_token'])) {
                        $val = '••••••••';
                    }
                });
            }

            $reqJson = $sanitizedRequest !== null ? json_encode($sanitizedRequest, JSON_UNESCAPED_UNICODE) : null;
            $resJson = json_encode($responseData, JSON_UNESCAPED_UNICODE);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $this->pdo->prepare("
                INSERT INTO courier_api_logs 
                  (order_id, integration_id, provider_code, endpoint_url, http_method, http_status_code, request_payload, response_payload, duration_ms, ip_address)
                VALUES 
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $this->getIntegrationId(),
                $this->getProviderCode(),
                $url,
                $method,
                $httpCode,
                $reqJson,
                $resJson,
                $durationMs,
                $ip
            ]);
        } catch (Throwable $e) {
            // Failsafe: never let logging failure crash order processing
            error_log('[CourierApiLog] Failed to log call: ' . $e->getMessage());
        }
    }
}

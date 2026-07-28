<?php
/**
 * AIModelAdapter - AI model provider adapters
 * 
 * Routes AI requests to the appropriate provider (OpenAI, Gemini, Claude, Custom)
 * and normalizes responses into a consistent format.
 */
class AIModelAdapter
{
    /**
     * Call an AI model with the given context
     *
     * @param string      $model           Model identifier: gpt-4o, gpt-4.1, gemini, claude, custom
     * @param string      $systemPrompt    System/instruction prompt
     * @param array       $messages        Conversation messages [{role, content}, ...]
     * @param int         $maxTokens       Maximum tokens in response
     * @param string|null $customEndpoint  Custom API endpoint (for 'custom' model)
     * @param string|null $customKey       Custom API key (for 'custom' model)
     * @return array      ['content' => string, 'tokens_used' => int, 'finish_reason' => string, 'model' => string]
     * @throws Exception
     */
    public static function call(
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens = 1024,
        ?string $customEndpoint = null,
        ?string $customKey = null
    ): array {
        switch ($model) {
            case 'gpt-4o':
            case 'gpt-4.1':
                return self::callOpenAI($model, $systemPrompt, $messages, $maxTokens);

            case 'gemini':
                return self::callGemini($systemPrompt, $messages, $maxTokens);

            case 'claude':
                return self::callClaude($systemPrompt, $messages, $maxTokens);

            case 'custom':
                if (empty($customEndpoint)) {
                    throw new Exception('Custom model requires an API endpoint.');
                }
                return self::callCustom($systemPrompt, $messages, $maxTokens, $customEndpoint, $customKey);

            default:
                throw new Exception("Unsupported AI model: {$model}");
        }
    }

    /**
     * Call OpenAI API (GPT-4o, GPT-4.1)
     *
     * @param string $model
     * @param string $systemPrompt
     * @param array  $messages
     * @param int    $maxTokens
     * @return array
     * @throws Exception
     */
    private static function callOpenAI(string $model, string $systemPrompt, array $messages, int $maxTokens): array
    {
        $settings = new Settings();
        $apiKey = $settings->get('ai_openai_api_key');

        if (empty($apiKey)) {
            throw new Exception('OpenAI API key is not configured. Please set it in Admin Settings.');
        }

        // Map model names
        $modelMap = [
            'gpt-4o' => 'gpt-4o',
            'gpt-4.1' => 'gpt-4.1',
        ];
        $apiModel = $modelMap[$model] ?? $model;

        // Build messages array with system prompt
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'model' => $apiModel,
            'messages' => $apiMessages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $response = self::makeCurlRequest(
            'https://api.openai.com/v1/chat/completions',
            $headers,
            $payload
        );

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
        }

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown OpenAI error';
            throw new Exception('OpenAI API error: ' . $errorMsg);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response structure from OpenAI API.');
        }

        $tokensUsed = 0;
        if (isset($data['usage'])) {
            $tokensUsed = ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0);
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'tokens_used' => $tokensUsed,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
            'model' => $data['model'] ?? $apiModel,
        ];
    }

    /**
     * Call Google Gemini API
     *
     * @param string $systemPrompt
     * @param array  $messages
     * @param int    $maxTokens
     * @return array
     * @throws Exception
     */
    private static function callGemini(string $systemPrompt, array $messages, int $maxTokens): array
    {
        $settings = new Settings();
        $apiKey = $settings->get('ai_gemini_api_key');

        if (empty($apiKey)) {
            throw new Exception('Gemini API key is not configured. Please set it in Admin Settings.');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);

        // Convert messages to Gemini format
        $contents = [];

        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        // Ensure the conversation starts with a user message
        if (empty($contents) || $contents[0]['role'] !== 'user') {
            array_unshift($contents, [
                'role' => 'user',
                'parts' => [['text' => 'Hello']],
            ]);
        }

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => 0.7,
            ],
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        $response = self::makeCurlRequest($url, $headers, $payload);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse Gemini response: ' . json_last_error_msg());
        }

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown Gemini error';
            throw new Exception('Gemini API error: ' . $errorMsg);
        }

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            // Check for safety blocks
            if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
                throw new Exception('Gemini blocked the response due to safety filters.');
            }
            throw new Exception('Invalid response structure from Gemini API.');
        }

        $tokensUsed = 0;
        if (isset($data['usageMetadata'])) {
            $tokensUsed = ($data['usageMetadata']['promptTokenCount'] ?? 0) + ($data['usageMetadata']['candidatesTokenCount'] ?? 0);
        }

        $finishReason = $data['candidates'][0]['finishReason'] ?? 'STOP';
        $finishReasonMap = ['STOP' => 'stop', 'MAX_TOKENS' => 'length', 'SAFETY' => 'content_filter'];

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'tokens_used' => $tokensUsed,
            'finish_reason' => $finishReasonMap[$finishReason] ?? strtolower($finishReason),
            'model' => 'gemini-2.0-flash',
        ];
    }

    /**
     * Call Anthropic Claude API
     *
     * @param string $systemPrompt
     * @param array  $messages
     * @param int    $maxTokens
     * @return array
     * @throws Exception
     */
    private static function callClaude(string $systemPrompt, array $messages, int $maxTokens): array
    {
        $settings = new Settings();
        $apiKey = $settings->get('ai_claude_api_key');

        if (empty($apiKey)) {
            throw new Exception('Claude API key is not configured. Please set it in Admin Settings.');
        }

        // Convert messages to Claude format
        $apiMessages = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'assistant' : 'user';
            $apiMessages[] = [
                'role' => $role,
                'content' => $msg['content'],
            ];
        }

        // Claude requires alternating user/assistant messages starting with user
        if (empty($apiMessages) || $apiMessages[0]['role'] !== 'user') {
            array_unshift($apiMessages, [
                'role' => 'user',
                'content' => 'Hello',
            ]);
        }

        // Ensure no consecutive same-role messages
        $mergedMessages = [];
        foreach ($apiMessages as $msg) {
            if (!empty($mergedMessages) && end($mergedMessages)['role'] === $msg['role']) {
                $lastIdx = count($mergedMessages) - 1;
                $mergedMessages[$lastIdx]['content'] .= "\n" . $msg['content'];
            } else {
                $mergedMessages[] = $msg;
            }
        }

        $payload = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $mergedMessages,
        ];

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $response = self::makeCurlRequest(
            'https://api.anthropic.com/v1/messages',
            $headers,
            $payload
        );

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse Claude response: ' . json_last_error_msg());
        }

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown Claude error';
            throw new Exception('Claude API error: ' . $errorMsg);
        }

        if (!isset($data['content'][0]['text'])) {
            throw new Exception('Invalid response structure from Claude API.');
        }

        $tokensUsed = 0;
        if (isset($data['usage'])) {
            $tokensUsed = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);
        }

        $finishReason = $data['stop_reason'] ?? 'end_turn';
        $finishReasonMap = ['end_turn' => 'stop', 'max_tokens' => 'length', 'stop_sequence' => 'stop'];

        return [
            'content' => $data['content'][0]['text'],
            'tokens_used' => $tokensUsed,
            'finish_reason' => $finishReasonMap[$finishReason] ?? $finishReason,
            'model' => $data['model'] ?? 'claude-sonnet-4-20250514',
        ];
    }

    /**
     * Call a custom OpenAI-compatible API endpoint
     *
     * @param string      $systemPrompt
     * @param array       $messages
     * @param int         $maxTokens
     * @param string      $endpoint
     * @param string|null $apiKey
     * @return array
     * @throws Exception
     */
    private static function callCustom(string $systemPrompt, array $messages, int $maxTokens, string $endpoint, ?string $apiKey): array
    {
        // Validate endpoint URL
        $endpoint = filter_var(trim($endpoint), FILTER_VALIDATE_URL);
        if (!$endpoint) {
            throw new Exception('Invalid custom API endpoint URL.');
        }

        // Build OpenAI-compatible request
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'messages' => $apiMessages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $response = self::makeCurlRequest($endpoint, $headers, $payload);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse custom API response: ' . json_last_error_msg());
        }

        if (isset($data['error'])) {
            $errorMsg = is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? 'Unknown error');
            throw new Exception('Custom API error: ' . $errorMsg);
        }

        // Try OpenAI-compatible response format
        if (isset($data['choices'][0]['message']['content'])) {
            $tokensUsed = 0;
            if (isset($data['usage'])) {
                $tokensUsed = ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0);
            }

            return [
                'content' => $data['choices'][0]['message']['content'],
                'tokens_used' => $tokensUsed,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
                'model' => $data['model'] ?? 'custom',
            ];
        }

        // Try alternative response formats
        if (isset($data['response'])) {
            return [
                'content' => is_string($data['response']) ? $data['response'] : json_encode($data['response']),
                'tokens_used' => $data['tokens_used'] ?? $data['usage']['total_tokens'] ?? 0,
                'finish_reason' => 'stop',
                'model' => 'custom',
            ];
        }

        if (isset($data['content'])) {
            return [
                'content' => is_string($data['content']) ? $data['content'] : $data['content'][0]['text'] ?? json_encode($data['content']),
                'tokens_used' => $data['tokens_used'] ?? 0,
                'finish_reason' => 'stop',
                'model' => 'custom',
            ];
        }

        throw new Exception('Could not parse response from custom API endpoint. Expected OpenAI-compatible format.');
    }

    /**
     * Shared cURL request helper
     *
     * @param string $url
     * @param array  $headers
     * @param array  $payload
     * @return string  Raw response body
     * @throws Exception
     */
    private static function makeCurlRequest(string $url, array $headers, array $payload): string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new Exception("cURL error ({$curlErrno}): {$curlError}");
        }

        if ($response === false) {
            throw new Exception('Empty response from AI API.');
        }

        // Handle HTTP errors
        if ($httpCode === 401) {
            throw new Exception('API authentication failed. Please check your API key.');
        }
        if ($httpCode === 429) {
            throw new Exception('API rate limit exceeded. Please try again later.');
        }
        if ($httpCode === 500 || $httpCode === 502 || $httpCode === 503) {
            throw new Exception("AI API server error (HTTP {$httpCode}). Please try again later.");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            // Try to extract error message from response
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? $errorData['error'] ?? "HTTP {$httpCode}";
            if (is_array($errorMsg)) {
                $errorMsg = json_encode($errorMsg);
            }
            throw new Exception("AI API request failed: {$errorMsg}");
        }

        return $response;
    }
}

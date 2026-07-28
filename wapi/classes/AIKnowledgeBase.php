<?php
/**
 * AIKnowledgeBase - Knowledge base management for AI bots
 * 
 * Handles documents, URLs, Q&A pairs, manual knowledge entries,
 * text extraction, chunking, and chunk-based search.
 */
class AIKnowledgeBase
{
    /** Upload directory for knowledge base files */
    private static function getUploadDir(): string
    {
        $dir = APP_ROOT . '/uploads/ai-kb/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get all knowledge bases for a bot
     *
     * @param int $botId
     * @return array
     */
    public static function getByBot(int $botId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM ai_knowledge_bases WHERE bot_id = ? ORDER BY created_at DESC", [$botId]);
    }

    /**
     * Create a new knowledge base
     *
     * @param int    $botId
     * @param int    $userId
     * @param string $name
     * @return int   The new knowledge base ID
     * @throws Exception
     */
    public static function create(int $botId, int $userId, string $name): int
    {
        $db = Database::getInstance();

        // Verify bot ownership
        $bot = AIBot::getById($botId, $userId);
        if (!$bot) {
            throw new Exception('Bot not found or access denied.');
        }

        return $db->insert('ai_knowledge_bases', [
            'bot_id' => $botId,
            'user_id' => $userId,
            'name' => sanitize($name),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Upload a document (PDF, DOCX, TXT, CSV)
     *
     * @param int   $kbId
     * @param int   $userId
     * @param array $file  $_FILES element
     * @return int  Document ID
     * @throws Exception
     */
    public static function uploadDocument(int $kbId, int $userId, array $file): int
    {
        $db = Database::getInstance();

        // Verify ownership
        $kb = $db->fetch("SELECT kb.*, kb.bot_id FROM ai_knowledge_bases kb WHERE kb.id = ? AND kb.user_id = ?", [$kbId, $userId]);
        if (!$kb) {
            throw new Exception('Knowledge base not found or access denied.');
        }

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed with error code: ' . $file['error']);
        }

        $allowedTypes = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/csv' => 'csv',
        ];

        $mimeType = mime_content_type($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate by extension if MIME doesn't match
        $allowedExtensions = ['pdf', 'docx', 'txt', 'csv'];
        if (!isset($allowedTypes[$mimeType]) && !in_array($extension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Allowed: PDF, DOCX, TXT, CSV.');
        }

        $fileType = $allowedTypes[$mimeType] ?? $extension;

        // Max file size: 10MB
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds the 10MB limit.');
        }

        // Generate unique filename
        $uploadDir = self::getUploadDir();
        $uniqueName = uniqid('kb_') . '_' . time() . '.' . $fileType;
        $filePath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file.');
        }

        try {
            // Extract text from file
            $text = self::extractText($filePath, $fileType);

            if (empty(trim($text))) {
                throw new Exception('Could not extract any text from the uploaded file.');
            }

            // Save document record
            $docId = $db->insert('ai_kb_documents', [
                'kb_id' => $kbId,
                'user_id' => $userId,
                'file_name' => sanitize($file['name']),
                'file_path' => 'uploads/ai-kb/' . $uniqueName,
                'file_type' => $fileType,
                'file_size' => $file['size'],
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Chunk the text and save
            $chunks = self::chunkText($text);
            self::saveChunks($kbId, 'document', $docId, $chunks);

            // Update KB timestamp
            $db->update('ai_knowledge_bases', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$kbId]);

            return $docId;

        } catch (Exception $e) {
            // Clean up file on failure
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            throw $e;
        }
    }

    /**
     * Extract text content from a file
     *
     * @param string $filePath
     * @param string $fileType  pdf, docx, txt, csv
     * @return string
     */
    public static function extractText(string $filePath, string $fileType): string
    {
        switch ($fileType) {
            case 'txt':
            case 'csv':
                $content = file_get_contents($filePath);
                if ($content === false) {
                    throw new Exception('Failed to read file.');
                }
                // For CSV, convert to readable format
                if ($fileType === 'csv') {
                    $lines = [];
                    $handle = fopen($filePath, 'r');
                    if ($handle) {
                        $headers = fgetcsv($handle);
                        if ($headers) {
                            while (($row = fgetcsv($handle)) !== false) {
                                $lineItems = [];
                                foreach ($row as $idx => $val) {
                                    $header = $headers[$idx] ?? "Column $idx";
                                    $lineItems[] = "{$header}: {$val}";
                                }
                                $lines[] = implode(', ', $lineItems);
                            }
                        }
                        fclose($handle);
                    }
                    return !empty($lines) ? implode("\n", $lines) : $content;
                }
                return $content;

            case 'pdf':
                return self::extractTextFromPdf($filePath);

            case 'docx':
                return self::extractTextFromDocx($filePath);

            default:
                throw new Exception("Unsupported file type: {$fileType}");
        }
    }

    /**
     * Extract text from PDF (simple approach using raw content parsing)
     */
    private static function extractTextFromPdf(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('Failed to read PDF file.');
        }

        $text = '';

        // Method 1: Extract text from stream objects
        // Find all stream content between stream and endstream markers
        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                // Try to decompress if zlib compressed
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($stream);
                }
                if ($decompressed === false) {
                    $decompressed = $stream;
                }

                // Extract text operators: Tj, TJ, ' and " operators
                // Tj: (text) Tj
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $decompressed, $tjMatches)) {
                    foreach ($tjMatches[1] as $match) {
                        $text .= self::decodePdfString($match) . ' ';
                    }
                }

                // TJ: [(text)(text)] TJ
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decompressed, $tjArrayMatches)) {
                    foreach ($tjArrayMatches[1] as $match) {
                        if (preg_match_all('/\((.*?)\)/s', $match, $innerMatches)) {
                            foreach ($innerMatches[1] as $inner) {
                                $text .= self::decodePdfString($inner);
                            }
                        }
                        $text .= ' ';
                    }
                }

                // Text with BT/ET blocks containing Td/TD positioning
                if (preg_match_all("/BT\s*(.*?)\s*ET/s", $decompressed, $btMatches)) {
                    foreach ($btMatches[1] as $block) {
                        // Check for newline indicators (Td with y offset)
                        if (preg_match_all('/(\d+\.?\d*)\s+(-?\d+\.?\d*)\s+Td/s', $block, $tdMatches)) {
                            foreach ($tdMatches[2] as $yOffset) {
                                if (abs((float)$yOffset) > 1) {
                                    $text .= "\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        // Method 2: If stream extraction found nothing, try direct text extraction
        if (empty(trim($text))) {
            // Try to find readable text sequences
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/s', $content, $parenMatches)) {
                foreach ($parenMatches[1] as $match) {
                    $decoded = self::decodePdfString($match);
                    // Only keep strings with printable characters
                    $printable = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $decoded);
                    if (strlen($printable) > 3) {
                        $text .= $printable . ' ';
                    }
                }
            }
        }

        // Clean up the extracted text
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Decode PDF string escapes
     */
    private static function decodePdfString(string $str): string
    {
        $str = str_replace('\\n', "\n", $str);
        $str = str_replace('\\r', "\r", $str);
        $str = str_replace('\\t', "\t", $str);
        $str = str_replace('\\(', '(', $str);
        $str = str_replace('\\)', ')', $str);
        $str = str_replace('\\\\', '\\', $str);
        // Octal escapes
        $str = preg_replace_callback('/\\\\(\d{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $str);
        return $str;
    }

    /**
     * Extract text from DOCX using ZipArchive
     */
    private static function extractTextFromDocx(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Failed to open DOCX file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new Exception('Failed to read document content from DOCX.');
        }

        // Replace paragraph and line break tags with newlines before stripping
        $xml = str_replace('</w:p>', "\n", $xml);
        $xml = str_replace('<w:br/>', "\n", $xml);
        $xml = str_replace('<w:br />', "\n", $xml);
        $xml = preg_replace('/<w:tab\s*\/?>/i', "\t", $xml);

        // Strip all XML tags
        $text = strip_tags($xml);

        // Clean up excessive whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Add a URL source to the knowledge base
     *
     * @param int    $kbId
     * @param int    $userId
     * @param string $url
     * @return int   URL record ID
     * @throws Exception
     */
    public static function addUrl(int $kbId, int $userId, string $url): int
    {
        $db = Database::getInstance();

        // Verify ownership
        $kb = $db->fetch("SELECT * FROM ai_knowledge_bases WHERE id = ? AND user_id = ?", [$kbId, $userId]);
        if (!$kb) {
            throw new Exception('Knowledge base not found or access denied.');
        }

        // Validate URL
        $url = filter_var(trim($url), FILTER_VALIDATE_URL);
        if (!$url) {
            throw new Exception('Invalid URL provided.');
        }

        // Check for duplicate URL
        $exists = $db->exists(
            'ai_kb_urls',
            'kb_id = ? AND url = ?',
            [$kbId, $url]
        );
        if ($exists) {
            throw new Exception('This URL has already been added to the knowledge base.');
        }

        // Crawl the URL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'WAPI-KnowledgeBase-Crawler/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($html === false || !empty($curlError)) {
            throw new Exception('Failed to fetch URL: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("URL returned HTTP status {$httpCode}.");
        }

        // Extract text from HTML
        $text = self::extractTextFromHtml($html);

        if (empty(trim($text))) {
            throw new Exception('Could not extract any text from the URL.');
        }

        // Extract title
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch)) {
            $title = trim(html_entity_decode(strip_tags($titleMatch[1])));
        }

        // Save URL record
        $urlId = $db->insert('ai_kb_urls', [
            'kb_id' => $kbId,
            'user_id' => $userId,
            'url' => $url,
            'title' => sanitize($title ?: $url),
            'status' => 'completed',
            'last_crawled_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Chunk and save
        $chunks = self::chunkText($text);
        self::saveChunks($kbId, 'url', $urlId, $chunks);

        // Update KB timestamp
        $db->update('ai_knowledge_bases', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$kbId]);

        return $urlId;
    }

    /**
     * Extract meaningful text from HTML
     */
    private static function extractTextFromHtml(string $html): string
    {
        // Remove script, style, nav, footer, header tags and their content
        $html = preg_replace('/<(script|style|nav|footer|header|aside|noscript)[^>]*>.*?<\/\1>/is', '', $html);

        // Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Try to extract main content area
        $mainContent = '';
        if (preg_match('/<(main|article)[^>]*>(.*?)<\/\1>/is', $html, $mainMatch)) {
            $mainContent = $mainMatch[2];
        }

        $source = !empty($mainContent) ? $mainContent : $html;

        // Replace block elements with newlines
        $source = preg_replace('/<\/(p|div|h[1-6]|li|tr|br|hr)[^>]*>/i', "\n", $source);
        $source = preg_replace('/<br\s*\/?>/i', "\n", $source);

        // Strip remaining tags
        $text = strip_tags($source);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Add a Q&A pair to the knowledge base
     *
     * @param int    $kbId
     * @param int    $userId
     * @param string $question
     * @param string $answer
     * @return int   Q&A record ID
     * @throws Exception
     */
    public static function addQAPair(int $kbId, int $userId, string $question, string $answer): int
    {
        $db = Database::getInstance();

        $kb = $db->fetch("SELECT * FROM ai_knowledge_bases WHERE id = ? AND user_id = ?", [$kbId, $userId]);
        if (!$kb) {
            throw new Exception('Knowledge base not found or access denied.');
        }

        if (empty(trim($question)) || empty(trim($answer))) {
            throw new Exception('Both question and answer are required.');
        }

        $qaId = $db->insert('ai_kb_qa_pairs', [
            'kb_id' => $kbId,
            'user_id' => $userId,
            'question' => sanitize($question),
            'answer' => sanitize($answer),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create a chunk combining Q&A
        $chunkText = "Question: {$question}\nAnswer: {$answer}";
        self::saveChunks($kbId, 'qa', $qaId, [$chunkText]);

        // Update KB timestamp
        $db->update('ai_knowledge_bases', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$kbId]);

        return $qaId;
    }

    /**
     * Add manual/raw text knowledge
     *
     * @param int    $kbId
     * @param int    $userId
     * @param string $content
     * @return int   Document ID (stored as manual type)
     * @throws Exception
     */
    public static function addManualKnowledge(int $kbId, int $userId, string $content): int
    {
        $db = Database::getInstance();

        $kb = $db->fetch("SELECT * FROM ai_knowledge_bases WHERE id = ? AND user_id = ?", [$kbId, $userId]);
        if (!$kb) {
            throw new Exception('Knowledge base not found or access denied.');
        }

        if (empty(trim($content))) {
            throw new Exception('Content cannot be empty.');
        }

        $docId = $db->insert('ai_kb_documents', [
            'kb_id' => $kbId,
            'user_id' => $userId,
            'file_name' => 'Manual Entry - ' . date('Y-m-d H:i:s'),
            'file_path' => 'manual',
            'file_type' => 'txt',
            'file_size' => strlen($content),
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Chunk and save
        $chunks = self::chunkText($content);
        self::saveChunks($kbId, 'document', $docId, $chunks);

        // Update KB timestamp
        $db->update('ai_knowledge_bases', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$kbId]);

        return $docId;
    }

    /**
     * Delete a knowledge base item
     *
     * @param string $type  'document', 'url', or 'qa'
     * @param int    $id
     * @param int    $userId
     * @return bool
     * @throws Exception
     */
    public static function deleteItem(string $type, int $id, int $userId): bool
    {
        $db = Database::getInstance();

        $tableMap = [
            'document' => 'ai_kb_documents',
            'url' => 'ai_kb_urls',
            'qa' => 'ai_kb_qa_pairs',
        ];

        if (!isset($tableMap[$type])) {
            throw new Exception('Invalid item type. Must be "document", "url", or "qa".');
        }

        $table = $tableMap[$type];

        // Get item and verify ownership via knowledge base
        $item = $db->fetch(
            "SELECT i.*, kb.user_id, kb.id as kb_id 
             FROM {$table} i 
             JOIN ai_knowledge_bases kb ON i.kb_id = kb.id 
             WHERE i.id = ? AND kb.user_id = ?",
            [$id, $userId]
        );

        if (!$item) {
            throw new Exception('Item not found or access denied.');
        }

        // Delete associated chunks
        $sourceType = ($type === 'qa') ? 'qa' : $type;
        $db->delete('ai_kb_chunks', 'kb_id = ? AND source_type = ? AND source_id = ?', [$item['kb_id'], $sourceType, $id]);

        // Delete the file if it's a document with a file path
        if ($type === 'document' && !empty($item['file_path'])) {
            $fullPath = APP_ROOT . '/' . $item['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Delete the record
        $db->delete($table, 'id = ?', [$id]);

        // Update KB timestamp
        $db->update('ai_knowledge_bases', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$item['kb_id']]);

        return true;
    }

    /**
     * Get items in a knowledge base, optionally filtered by type
     *
     * @param int         $kbId
     * @param string|null $type  'document', 'url', 'qa', or null for all
     * @return array
     */
    public static function getItems(int $kbId, ?string $type = null): array
    {
        $db = Database::getInstance();

        $result = [];

        if ($type === null || $type === 'document') {
            $docs = $db->fetchAll(
                "SELECT id, file_name, file_type, file_size, status, created_at, 'document' as item_type 
                 FROM ai_kb_documents WHERE kb_id = ? ORDER BY created_at DESC",
                [$kbId]
            );
            $result = array_merge($result, $docs);
        }

        if ($type === null || $type === 'url') {
            $urls = $db->fetchAll(
                "SELECT id, url, title, status, last_crawled_at, created_at, 'url' as item_type 
                 FROM ai_kb_urls WHERE kb_id = ? ORDER BY created_at DESC",
                [$kbId]
            );
            $result = array_merge($result, $urls);
        }

        if ($type === null || $type === 'qa') {
            $qas = $db->fetchAll(
                "SELECT id, question, answer, created_at, 'qa' as item_type 
                 FROM ai_kb_qa_pairs WHERE kb_id = ? ORDER BY created_at DESC",
                [$kbId]
            );
            $result = array_merge($result, $qas);
        }

        return $result;
    }

    /**
     * Split text into chunks of approximately $chunkSize words with overlap
     *
     * @param string $text
     * @param int    $chunkSize    Words per chunk
     * @param int    $overlapSize  Word overlap between chunks
     * @return array
     */
    public static function chunkText(string $text, int $chunkSize = 500, int $overlapSize = 50): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $words = preg_split('/\s+/', trim($text));
        $totalWords = count($words);

        if ($totalWords <= $chunkSize) {
            return [trim($text)];
        }

        $chunks = [];
        $position = 0;

        while ($position < $totalWords) {
            $chunkWords = array_slice($words, $position, $chunkSize);
            $chunk = implode(' ', $chunkWords);

            if (!empty(trim($chunk))) {
                $chunks[] = trim($chunk);
            }

            // Move forward by chunkSize minus overlap
            $position += ($chunkSize - $overlapSize);

            // Prevent infinite loop for edge cases
            if ($position >= $totalWords) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Save text chunks to the database
     *
     * @param int    $kbId
     * @param string $sourceType  'document', 'url', 'qa'
     * @param int    $sourceId
     * @param array  $chunks
     */
    public static function saveChunks(int $kbId, string $sourceType, int $sourceId, array $chunks): void
    {
        $db = Database::getInstance();

        foreach ($chunks as $index => $chunkText) {
            if (empty(trim($chunkText))) {
                continue;
            }

            $db->insert('ai_kb_chunks', [
                'kb_id' => $kbId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'content' => $chunkText,
                'word_count' => str_word_count($chunkText),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Search chunks for a bot's knowledge bases using keyword matching
     *
     * @param int    $botId
     * @param string $query
     * @param int    $limit
     * @return array  Matching chunks ordered by relevance
     */
    public static function searchChunks(int $botId, string $query, int $limit = 5): array
    {
        $db = Database::getInstance();

        if (empty(trim($query))) {
            return [];
        }

        // Get all knowledge base IDs for this bot
        $kbIds = $db->fetchAll("SELECT id FROM ai_knowledge_bases WHERE bot_id = ?", [$botId]);
        if (empty($kbIds)) {
            return [];
        }

        $kbIdList = array_column($kbIds, 'id');
        $placeholders = implode(',', array_fill(0, count($kbIdList), '?'));

        // Extract meaningful keywords (remove common stop words)
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for', 'on', 'with', 'at',
            'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above',
            'below', 'between', 'and', 'but', 'or', 'not', 'no', 'nor', 'so', 'yet',
            'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'than',
            'too', 'very', 'just', 'about', 'up', 'out', 'if', 'then', 'what', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'i', 'me', 'my', 'we', 'our',
            'you', 'your', 'he', 'him', 'his', 'she', 'her', 'it', 'its', 'they', 'them', 'their'];

        $queryWords = preg_split('/\s+/', strtolower(trim($query)));
        $keywords = array_filter($queryWords, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });

        if (empty($keywords)) {
            $keywords = $queryWords; // Fallback to all words if everything was filtered
        }

        // Try FULLTEXT search first (MATCH AGAINST)
        try {
            // Build MATCH AGAINST query with boolean mode
            $searchTerms = implode(' ', array_map(function ($kw) {
                return '+' . $kw . '*';
            }, $keywords));

            $params = array_merge($kbIdList, [$searchTerms]);

            $results = $db->fetchAll(
                "SELECT c.*, MATCH(c.content) AGAINST(? IN BOOLEAN MODE) AS relevance_score
                 FROM ai_kb_chunks c
                 WHERE c.kb_id IN ({$placeholders})
                 AND MATCH(c.content) AGAINST(? IN BOOLEAN MODE)
                 ORDER BY relevance_score DESC
                 LIMIT ?",
                array_merge([$searchTerms], $kbIdList, [$searchTerms, $limit])
            );

            if (!empty($results)) {
                return $results;
            }
        } catch (Exception $e) {
            // FULLTEXT index might not exist, fall through to LIKE search
        }

        // Fallback: LIKE-based search with scoring
        $likeConditions = [];
        $likeParams = [];

        foreach ($keywords as $keyword) {
            $likeConditions[] = 'c.content LIKE ?';
            $likeParams[] = '%' . $keyword . '%';
        }

        $likeWhere = '(' . implode(' OR ', $likeConditions) . ')';

        // Build a scoring expression: count how many keywords match
        $scoreExprParts = [];
        $scoreParams = [];
        foreach ($keywords as $keyword) {
            $scoreExprParts[] = "(CASE WHEN c.content LIKE ? THEN 1 ELSE 0 END)";
            $scoreParams[] = '%' . $keyword . '%';
        }
        $scoreExpr = implode(' + ', $scoreExprParts);

        $allParams = array_merge($scoreParams, $kbIdList, $likeParams, [$limit]);

        $results = $db->fetchAll(
            "SELECT c.*, ({$scoreExpr}) AS relevance_score
             FROM ai_kb_chunks c
             WHERE c.kb_id IN ({$placeholders})
             AND {$likeWhere}
             ORDER BY relevance_score DESC, c.id ASC
             LIMIT ?",
            $allParams
        );

        return $results;
    }

    /**
     * Get statistics for a knowledge base
     *
     * @param int $kbId
     * @return array
     */
    public static function getStats(int $kbId): array
    {
        $db = Database::getInstance();

        $documentCount = (int) $db->count(
            "SELECT COUNT(*) FROM ai_kb_documents WHERE kb_id = ?",
            [$kbId]
        );

        $urlCount = (int) $db->count(
            "SELECT COUNT(*) FROM ai_kb_urls WHERE kb_id = ?",
            [$kbId]
        );

        $qaCount = (int) $db->count(
            "SELECT COUNT(*) FROM ai_kb_qa_pairs WHERE kb_id = ?",
            [$kbId]
        );

        $totalChunks = (int) $db->count(
            "SELECT COUNT(*) FROM ai_kb_chunks WHERE kb_id = ?",
            [$kbId]
        );

        $totalWords = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(word_count), 0) FROM ai_kb_chunks WHERE kb_id = ?",
            [$kbId]
        );

        return [
            'documents' => $documentCount,
            'urls' => $urlCount,
            'qa_pairs' => $qaCount,
            'total_chunks' => $totalChunks,
            'total_words' => $totalWords,
            'total_sources' => $documentCount + $urlCount + $qaCount,
        ];
    }
}

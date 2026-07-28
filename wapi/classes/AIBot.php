<?php
/**
 * AIBot - CRUD operations for AI chatbots
 * 
 * Manages bot lifecycle: creation, updates, deletion, cloning,
 * status toggling, plan limit enforcement, and usage counters.
 */
class AIBot
{
    /**
     * Generate a UUID v4
     */
    private static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get bots for a user with optional filters
     *
     * @param int   $userId
     * @param array $filters  Optional: status, search, limit, offset, order_by, order_dir
     * @return array
     */
    public static function getByUser(int $userId, array $filters = []): array
    {
        $db = Database::getInstance();

        $where = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = implode(' AND ', $where);

        $orderBy = 'created_at';
        $orderDir = 'DESC';
        if (!empty($filters['order_by']) && in_array($filters['order_by'], ['name', 'created_at', 'updated_at', 'total_conversations'])) {
            $orderBy = $filters['order_by'];
        }
        if (!empty($filters['order_dir']) && in_array(strtoupper($filters['order_dir']), ['ASC', 'DESC'])) {
            $orderDir = strtoupper($filters['order_dir']);
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM ai_bots WHERE {$whereClause} ORDER BY {$orderBy} {$orderDir} LIMIT {$limit} OFFSET {$offset}";

        return $db->fetchAll($sql, $params);
    }

    /**
     * Get a single bot by ID, optionally verifying ownership
     *
     * @param int      $id
     * @param int|null $userId  If provided, verifies the bot belongs to this user
     * @return array|false
     */
    public static function getById(int $id, ?int $userId = null)
    {
        $db = Database::getInstance();

        if ($userId !== null) {
            return $db->fetch("SELECT * FROM ai_bots WHERE id = ? AND user_id = ?", [$id, $userId]);
        }

        return $db->fetch("SELECT * FROM ai_bots WHERE id = ?", [$id]);
    }

    /**
     * Get a bot by its UUID
     *
     * @param string $uuid
     * @return array|false
     */
    public static function getByUuid(string $uuid)
    {
        $db = Database::getInstance();
        return $db->fetch("SELECT * FROM ai_bots WHERE uuid = ?", [$uuid]);
    }

    /**
     * Find the active bot assigned to a WhatsApp account
     *
     * @param int $waAccountId
     * @return array|false
     */
    public static function getByWhatsAppAccount(int $waAccountId)
    {
        $db = Database::getInstance();
        return $db->fetch(
            "SELECT * FROM ai_bots WHERE whatsapp_account_id = ? AND status = 'active' LIMIT 1",
            [$waAccountId]
        );
    }

    /**
     * Create a new AI bot
     *
     * @param int   $userId
     * @param array $data  Bot configuration data
     * @return int  The new bot ID
     * @throws Exception If plan limit reached or validation fails
     */
    public static function create(int $userId, array $data): int
    {
        // Check plan limit before creating
        if (!self::checkPlanLimit($userId)) {
            throw new Exception('You have reached the maximum number of AI bots allowed by your plan. Please upgrade to create more.');
        }

        $db = Database::getInstance();

        $uuid = self::generateUuid();

        // Ensure unique UUID
        while ($db->exists('ai_bots', 'uuid = ?', [$uuid])) {
            $uuid = self::generateUuid();
        }

        $insertData = [
            'user_id'                      => $userId,
            'uuid'                         => $uuid,
            'name'                         => sanitize($data['name'] ?? 'Untitled Bot'),
            'description'                  => sanitize($data['description'] ?? ''),
            'status'                       => sanitize($data['status'] ?? 'inactive'),
            'whatsapp_account_id'          => !empty($data['whatsapp_account_id']) ? (int) $data['whatsapp_account_id'] : null,
            'ai_model'                     => sanitize($data['ai_model'] ?? 'gpt-4o'),
            'custom_api_endpoint'          => $data['custom_api_endpoint'] ?? null,
            'custom_api_key_encrypted'     => $data['custom_api_key_encrypted'] ?? null,
            'bot_role'                     => sanitize($data['bot_role'] ?? 'Customer Support Agent'),
            'business_type'                => sanitize($data['business_type'] ?? 'General'),
            'response_tone'                => sanitize($data['response_tone'] ?? 'professional'),
            'response_length'              => sanitize($data['response_length'] ?? 'moderate'),
            'language'                     => sanitize($data['language'] ?? 'English'),
            'system_prompt'                => $data['system_prompt'] ?? '',
            'handover_enabled'             => (int) ($data['handover_enabled'] ?? 0),
            'handover_keywords'            => $data['handover_keywords'] ?? 'talk to human,human support,agent,representative',
            'handover_confidence_threshold'=> (float) ($data['handover_confidence_threshold'] ?? 0.30),
            'crm_capture_enabled'          => (int) ($data['crm_capture_enabled'] ?? 1),
            'rate_limit_per_minute'        => (int) ($data['rate_limit_per_minute'] ?? 100),
        ];

        return $db->insert('ai_bots', $insertData);
    }

    /**
     * Update an existing bot
     *
     * @param int   $id
     * @param int   $userId
     * @param array $data
     * @return bool
     * @throws Exception If bot not found or not owned by user
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $db = Database::getInstance();

        $bot = self::getById($id, $userId);
        if (!$bot) {
            throw new Exception('Bot not found or access denied.');
        }

        $allowedFields = [
            'name', 'description', 'ai_model', 'system_prompt',
            'bot_role', 'business_type', 'response_tone', 'response_length',
            'language', 'handover_enabled', 'handover_keywords',
            'handover_confidence_threshold', 'crm_capture_enabled',
            'rate_limit_per_minute', 'whatsapp_account_id',
            'custom_api_endpoint', 'custom_api_key_encrypted', 'status'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // Type casting
                switch ($field) {
                    case 'handover_enabled':
                    case 'crm_capture_enabled':
                    case 'rate_limit_per_minute':
                        $value = (int) $value;
                        break;
                    case 'handover_confidence_threshold':
                        $value = (float) $value;
                        break;
                    case 'whatsapp_account_id':
                        $value = !empty($value) ? (int) $value : null;
                        break;
                    case 'name':
                    case 'description':
                    case 'ai_model':
                    case 'bot_role':
                    case 'business_type':
                    case 'response_tone':
                    case 'response_length':
                    case 'language':
                    case 'status':
                        $value = sanitize($value);
                        break;
                }

                $updateData[$field] = $value;
            }
        }

        if (empty($updateData)) {
            return false;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $db->update('ai_bots', $updateData, 'id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Delete a bot (cascade handled by DB foreign keys)
     *
     * @param int $id
     * @param int $userId
     * @return bool
     * @throws Exception If bot not found
     */
    public static function delete(int $id, int $userId): bool
    {
        $db = Database::getInstance();

        $bot = self::getById($id, $userId);
        if (!$bot) {
            throw new Exception('Bot not found or access denied.');
        }

        return $db->delete('ai_bots', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Clone/duplicate a bot with 'Copy of' prefix
     *
     * @param int $id
     * @param int $userId
     * @return int  The new bot ID
     * @throws Exception If source bot not found or plan limit reached
     */
    public static function cloneBot(int $id, int $userId): int
    {
        $db = Database::getInstance();

        $bot = self::getById($id, $userId);
        if (!$bot) {
            throw new Exception('Bot not found or access denied.');
        }

        if (!self::checkPlanLimit($userId)) {
            throw new Exception('You have reached the maximum number of AI bots allowed by your plan. Please upgrade to create more.');
        }

        $cloneData = [
            'name'                         => 'Copy of ' . $bot['name'],
            'description'                  => $bot['description'],
            'ai_model'                     => $bot['ai_model'],
            'bot_role'                     => $bot['bot_role'],
            'business_type'                => $bot['business_type'],
            'response_tone'                => $bot['response_tone'],
            'response_length'              => $bot['response_length'],
            'language'                     => $bot['language'],
            'system_prompt'                => $bot['system_prompt'],
            'handover_enabled'             => $bot['handover_enabled'],
            'handover_keywords'            => $bot['handover_keywords'],
            'handover_confidence_threshold'=> $bot['handover_confidence_threshold'],
            'crm_capture_enabled'          => $bot['crm_capture_enabled'],
            'rate_limit_per_minute'        => $bot['rate_limit_per_minute'],
            'custom_api_endpoint'          => $bot['custom_api_endpoint'],
            'custom_api_key_encrypted'     => $bot['custom_api_key_encrypted'],
        ];

        return self::create($userId, $cloneData);
    }

    /**
     * Toggle bot status (activate/deactivate)
     *
     * @param int    $id
     * @param int    $userId
     * @param string $status  'active' or 'inactive'
     * @return bool
     * @throws Exception If invalid status or bot not found
     */
    public static function toggleStatus(int $id, int $userId, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Invalid status. Must be "active" or "inactive".');
        }

        $db = Database::getInstance();

        $bot = self::getById($id, $userId);
        if (!$bot) {
            throw new Exception('Bot not found or access denied.');
        }

        // If activating, ensure the WhatsApp account isn't already assigned to another active bot
        if ($status === 'active' && !empty($bot['whatsapp_account_id'])) {
            $existingBot = $db->fetch(
                "SELECT id FROM ai_bots WHERE whatsapp_account_id = ? AND status = 'active' AND id != ? AND user_id = ?",
                [$bot['whatsapp_account_id'], $id, $userId]
            );
            if ($existingBot) {
                throw new Exception('This WhatsApp account already has an active bot assigned. Please deactivate it first.');
            }
        }

        return $db->update('ai_bots', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Count bots belonging to a user
     *
     * @param int $userId
     * @return int
     */
    public static function countByUser(int $userId): int
    {
        $db = Database::getInstance();
        return (int) $db->count('ai_bots', 'user_id = ?', [$userId]);
    }

    /**
     * Check if user can create more bots based on their plan's ai_bots_limit
     *
     * @param int $userId
     * @return bool  True if user can create more bots
     */
    public static function checkPlanLimit(int $userId): bool
    {
        $db = Database::getInstance();

        // Admin override (unlimited bots)
        $role = $db->fetchColumn("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($role === 'admin') {
            return true;
        }

        // Get user's active plan limit from subscriptions
        $subscription = $db->fetch(
            "SELECT s.plan_id, p.ai_bots_limit 
             FROM subscriptions s 
             JOIN plans p ON s.plan_id = p.id 
             WHERE s.user_id = ? AND s.status = 'active' 
             ORDER BY s.created_at DESC LIMIT 1",
            [$userId]
        );

        if (!$subscription) {
            return false;
        }

        // If no plan limit or unlimited (-1 or null), allow
        $limit = $subscription['ai_bots_limit'] ?? null;
        if ($limit === null || (int) $limit === -1) {
            return true;
        }

        $currentCount = self::countByUser($userId);
        return $currentCount < (int) $limit;
    }

    /**
     * Increment a counter field on a bot
     *
     * @param int    $botId
     * @param string $field  One of: total_conversations, total_messages_processed, total_leads_captured
     * @return bool
     */
    public static function incrementCounter(int $botId, string $field): bool
    {
        $allowedFields = ['total_conversations', 'total_messages_processed', 'total_leads_captured'];

        if (!in_array($field, $allowedFields)) {
            return false;
        }

        $db = Database::getInstance();
        $db->query(
            "UPDATE ai_bots SET {$field} = {$field} + 1, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $botId]
        );
        return true;
    }
}

<?php
/**
 * AIAnalytics - Analytics aggregation for AI chatbots
 * 
 * Provides dashboard statistics, chart data, resolution breakdowns,
 * common questions analysis, per-bot performance metrics,
 * daily aggregation, and admin-level overviews.
 */
class AIAnalytics
{
    /**
     * Get dashboard statistics for a user (optionally filtered by bot)
     *
     * @param int      $userId
     * @param int|null $botId
     * @return array
     */
    public static function getDashboardStats(int $userId, ?int $botId = null): array
    {
        $db = Database::getInstance();

        $botFilter = '';
        $params = [$userId];

        if ($botId) {
            $botFilter = ' AND c.bot_id = ?';
            $params[] = $botId;
        }

        // Total conversations
        $totalConversations = (int) $db->count(
            "SELECT COUNT(*) FROM ai_conversations c 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ?{$botFilter}",
            $params
        );

        // Resolved by AI (conversations that ended without handover)
        $resolvedByAI = (int) $db->count(
            "SELECT COUNT(*) FROM ai_conversations c 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ? AND c.status IN ('resolved', 'closed', 'expired'){$botFilter}",
            $params
        );

        // Transferred to human
        $transferredToHuman = (int) $db->count(
            "SELECT COUNT(*) FROM ai_conversations c 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ? AND c.status = 'handed_over'{$botFilter}",
            $params
        );

        // Leads generated
        $leadsGenerated = (int) $db->count(
            "SELECT COUNT(*) FROM ai_leads l 
             JOIN ai_bots b ON l.bot_id = b.id 
             WHERE b.user_id = ?" . ($botId ? ' AND l.bot_id = ?' : ''),
            $params
        );

        // Average response time (in milliseconds)
        $avgResponseTime = $db->fetchColumn(
            "SELECT COALESCE(AVG(m.response_time_ms), 0) FROM ai_messages m 
             JOIN ai_conversations c ON m.conversation_id = c.id 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ? AND m.sender_type = 'ai' AND m.response_time_ms > 0{$botFilter}",
            $params
        );

        // Total tokens used
        $totalTokens = $db->fetchColumn(
            "SELECT COALESCE(SUM(m.tokens_used), 0) FROM ai_messages m 
             JOIN ai_conversations c ON m.conversation_id = c.id 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ? AND m.tokens_used > 0{$botFilter}",
            $params
        );

        // Active conversations (currently open)
        $activeConversations = (int) $db->count(
            "SELECT COUNT(*) FROM ai_conversations c 
             JOIN ai_bots b ON c.bot_id = b.id 
             WHERE b.user_id = ? AND c.status = 'active'{$botFilter}",
            $params
        );

        // Resolution rate
        $resolutionRate = $totalConversations > 0
            ? round(($resolvedByAI / $totalConversations) * 100, 1)
            : 0;

        return [
            'total_conversations' => $totalConversations,
            'active_conversations' => $activeConversations,
            'resolved_by_ai' => $resolvedByAI,
            'transferred_to_human' => $transferredToHuman,
            'leads_generated' => $leadsGenerated,
            'avg_response_time' => round((float) $avgResponseTime),
            'avg_response_time_formatted' => self::formatResponseTime((float) $avgResponseTime),
            'total_tokens_used' => (int) $totalTokens,
            'resolution_rate' => $resolutionRate,
        ];
    }

    /**
     * Get daily conversation data for line charts
     *
     * @param int      $userId
     * @param int|null $botId
     * @param int      $days   Number of days to look back
     * @return array   [{date, total, ai_resolved, human_transferred}]
     */
    public static function getConversationChart(int $userId, ?int $botId = null, int $days = 30): array
    {
        $db = Database::getInstance();

        $botFilter = '';
        $params = [$userId];

        if ($botId) {
            $botFilter = ' AND c.bot_id = ?';
            $params[] = $botId;
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $params[] = $startDate;

        $results = $db->fetchAll(
            "SELECT 
                DATE(c.created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN c.status IN ('resolved', 'closed', 'expired') THEN 1 ELSE 0 END) as ai_resolved,
                SUM(CASE WHEN c.status = 'handed_over' THEN 1 ELSE 0 END) as human_transferred
             FROM ai_conversations c
             JOIN ai_bots b ON c.bot_id = b.id
             WHERE b.user_id = ?{$botFilter}
             AND DATE(c.created_at) >= ?
             GROUP BY DATE(c.created_at)
             ORDER BY DATE(c.created_at) ASC",
            $params
        );

        // Fill in missing dates with zeros
        $chartData = [];
        $dateMap = [];
        foreach ($results as $row) {
            $dateMap[$row['date']] = $row;
        }

        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            if (isset($dateMap[$date])) {
                $chartData[] = [
                    'date' => $date,
                    'label' => date('M j', strtotime($date)),
                    'total' => (int) $dateMap[$date]['total'],
                    'ai_resolved' => (int) $dateMap[$date]['ai_resolved'],
                    'human_transferred' => (int) $dateMap[$date]['human_transferred'],
                ];
            } else {
                $chartData[] = [
                    'date' => $date,
                    'label' => date('M j', strtotime($date)),
                    'total' => 0,
                    'ai_resolved' => 0,
                    'human_transferred' => 0,
                ];
            }
        }

        return $chartData;
    }

    /**
     * Get resolution breakdown data for doughnut chart
     *
     * @param int      $userId
     * @param int|null $botId
     * @return array   {ai: X, human: Y, expired: Z, active: W}
     */
    public static function getResolutionBreakdown(int $userId, ?int $botId = null): array
    {
        $db = Database::getInstance();

        $botFilter = '';
        $params = [$userId];

        if ($botId) {
            $botFilter = ' AND c.bot_id = ?';
            $params[] = $botId;
        }

        $result = $db->fetch(
            "SELECT 
                SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as ai,
                SUM(CASE WHEN c.status = 'handed_over' THEN 1 ELSE 0 END) as human,
                SUM(CASE WHEN c.status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN c.status IN ('active', 'idle') THEN 1 ELSE 0 END) as active
             FROM ai_conversations c
             JOIN ai_bots b ON c.bot_id = b.id
             WHERE b.user_id = ?{$botFilter}",
            $params
        );

        return [
            'ai' => (int) ($result['ai'] ?? 0),
            'human' => (int) ($result['human'] ?? 0),
            'expired' => (int) ($result['expired'] ?? 0),
            'active' => (int) ($result['active'] ?? 0),
            'labels' => ['AI Resolved', 'Human Transferred', 'Expired', 'Active'],
            'data' => [
                (int) ($result['ai'] ?? 0),
                (int) ($result['human'] ?? 0),
                (int) ($result['expired'] ?? 0),
                (int) ($result['active'] ?? 0),
            ],
            'colors' => ['#28a745', '#ffc107', '#6c757d', '#17a2b8'],
        ];
    }

    /**
     * Get most frequently asked questions
     *
     * @param int      $userId
     * @param int|null $botId
     * @param int      $limit
     * @return array
     */
    public static function getMostAskedQuestions(int $userId, ?int $botId = null, int $limit = 10): array
    {
        $db = Database::getInstance();

        $botFilter = '';
        $params = [$userId];

        if ($botId) {
            $botFilter = ' AND c.bot_id = ?';
            $params[] = $botId;
        }

        $params[] = $limit;

        // Group similar questions by content (exact match)
        // Only include user messages that look like questions (contain ? or start with question words)
        $results = $db->fetchAll(
            "SELECT 
                m.content as question,
                COUNT(*) as frequency,
                MAX(m.created_at) as last_asked
             FROM ai_messages m
             JOIN ai_conversations c ON m.conversation_id = c.id
             JOIN ai_bots b ON c.bot_id = b.id
             WHERE b.user_id = ?
             AND m.direction = 'inbound'
             AND (m.content LIKE '%?%' 
                  OR m.content LIKE 'what %' 
                  OR m.content LIKE 'how %' 
                  OR m.content LIKE 'why %'
                  OR m.content LIKE 'when %'
                  OR m.content LIKE 'where %'
                  OR m.content LIKE 'who %'
                  OR m.content LIKE 'which %'
                  OR m.content LIKE 'can %'
                  OR m.content LIKE 'do %'
                  OR m.content LIKE 'does %'
                  OR m.content LIKE 'is %'
                  OR m.content LIKE 'are %')
             {$botFilter}
             GROUP BY m.content
             HAVING COUNT(*) > 1
             ORDER BY frequency DESC
             LIMIT ?",
            $params
        );

        // If not enough results from exact matches, also try shortened content matching
        if (count($results) < $limit) {
            $remaining = $limit - count($results);
            $additionalParams = [$userId];
            if ($botId) {
                $additionalParams[] = $botId;
            }
            $additionalParams[] = $remaining;

            $additional = $db->fetchAll(
                "SELECT 
                    SUBSTRING(m.content, 1, 100) as question,
                    COUNT(*) as frequency,
                    MAX(m.created_at) as last_asked
                 FROM ai_messages m
                 JOIN ai_conversations c ON m.conversation_id = c.id
                 JOIN ai_bots b ON c.bot_id = b.id
                 WHERE b.user_id = ?
                 AND m.direction = 'inbound'
                 {$botFilter}
                 GROUP BY SUBSTRING(m.content, 1, 100)
                 HAVING COUNT(*) > 1
                 ORDER BY frequency DESC
                 LIMIT ?",
                $additionalParams
            );

            // Merge without duplicates
            $existingQuestions = array_column($results, 'question');
            foreach ($additional as $row) {
                if (!in_array($row['question'], $existingQuestions)) {
                    $results[] = $row;
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        }

        return array_map(function ($row) {
            return [
                'question' => $row['question'],
                'frequency' => (int) $row['frequency'],
                'last_asked' => $row['last_asked'],
            ];
        }, $results);
    }

    /**
     * Get per-bot performance statistics
     *
     * @param int $userId
     * @return array
     */
    public static function getBotPerformance(int $userId): array
    {
        $db = Database::getInstance();

        $bots = $db->fetchAll(
            "SELECT 
                b.id,
                b.name,
                b.status,
                b.ai_model,
                b.total_conversations,
                b.total_messages_processed,
                b.total_leads_captured,
                b.created_at,
                (SELECT COUNT(*) FROM ai_conversations c WHERE c.bot_id = b.id AND c.status = 'active') as active_conversations,
                (SELECT COUNT(*) FROM ai_conversations c WHERE c.bot_id = b.id AND c.status IN ('resolved', 'closed')) as resolved_conversations,
                (SELECT COUNT(*) FROM ai_conversations c WHERE c.bot_id = b.id AND c.status = 'handed_over') as handover_conversations,
                (SELECT COALESCE(AVG(m.response_time_ms), 0) FROM ai_messages m 
                    JOIN ai_conversations c ON m.conversation_id = c.id 
                    WHERE c.bot_id = b.id AND m.sender_type = 'ai' AND m.response_time_ms > 0) as avg_response_time,
                (SELECT COALESCE(SUM(m.tokens_used), 0) FROM ai_messages m 
                    JOIN ai_conversations c ON m.conversation_id = c.id 
                    WHERE c.bot_id = b.id AND m.tokens_used > 0) as total_tokens
             FROM ai_bots b
             WHERE b.user_id = ?
             ORDER BY b.total_conversations DESC",
            [$userId]
        );

        return array_map(function ($bot) {
            $totalConv = (int) $bot['total_conversations'];
            $resolvedConv = (int) $bot['resolved_conversations'];
            $resolutionRate = $totalConv > 0 ? round(($resolvedConv / $totalConv) * 100, 1) : 0;

            return [
                'id' => (int) $bot['id'],
                'name' => $bot['name'],
                'status' => $bot['status'],
                'ai_model' => $bot['ai_model'],
                'total_conversations' => $totalConv,
                'active_conversations' => (int) $bot['active_conversations'],
                'resolved_conversations' => $resolvedConv,
                'handover_conversations' => (int) $bot['handover_conversations'],
                'total_messages' => (int) $bot['total_messages_processed'],
                'total_leads' => (int) $bot['total_leads_captured'],
                'resolution_rate' => $resolutionRate,
                'avg_response_time' => round((float) $bot['avg_response_time']),
                'avg_response_time_formatted' => self::formatResponseTime((float) $bot['avg_response_time']),
                'total_tokens' => (int) $bot['total_tokens'],
                'created_at' => $bot['created_at'],
            ];
        }, $bots);
    }

    /**
     * Compute and store daily aggregates for a bot
     *
     * @param int         $botId
     * @param string|null $date  Date in Y-m-d format, defaults to yesterday
     * @return bool
     */
    public static function aggregateDaily(int $botId, ?string $date = null): bool
    {
        $db = Database::getInstance();

        $date = $date ?: date('Y-m-d', strtotime('-1 day'));

        // Check if already aggregated
        $existing = $db->exists(
            'ai_analytics_daily',
            'bot_id = ? AND date = ?',
            [$botId, $date]
        );

        // Compute aggregates
        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total_conversations,
                SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as ai_resolved,
                SUM(CASE WHEN c.status = 'handed_over' THEN 1 ELSE 0 END) as human_transferred,
                SUM(COALESCE(c.total_messages, 0)) as total_messages
             FROM ai_conversations c
             WHERE c.bot_id = ? AND DATE(c.created_at) = ?",
            [$botId, $date]
        );

        $messageStats = $db->fetch(
            "SELECT 
                COALESCE(SUM(m.tokens_used), 0) as total_tokens,
                COALESCE(AVG(CASE WHEN m.response_time_ms > 0 THEN m.response_time_ms END), 0) as avg_response_time
             FROM ai_messages m
             JOIN ai_conversations c ON m.conversation_id = c.id
             WHERE c.bot_id = ? AND DATE(m.created_at) = ? AND m.sender_type = 'ai'",
            [$botId, $date]
        );

        $leadsCount = (int) $db->count(
            "SELECT COUNT(*) FROM ai_leads WHERE bot_id = ? AND DATE(created_at) = ?",
            [$botId, $date]
        );

        $data = [
            'bot_id' => $botId,
            'date' => $date,
            'total_conversations' => (int) ($stats['total_conversations'] ?? 0),
            'ai_resolved' => (int) ($stats['ai_resolved'] ?? 0),
            'human_transferred' => (int) ($stats['human_transferred'] ?? 0),
            'total_messages' => (int) ($stats['total_messages'] ?? 0),
            'total_tokens_used' => (int) ($messageStats['total_tokens'] ?? 0),
            'avg_response_time_ms' => round((float) ($messageStats['avg_response_time'] ?? 0)),
            'leads_captured' => $leadsCount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            unset($data['created_at']);
            return $db->update('ai_analytics_daily', $data, 'bot_id = ? AND date = ?', [$botId, $date]);
        } else {
            return (bool) $db->insert('ai_analytics_daily', $data);
        }
    }

    /**
     * Get admin-level overview statistics across all users
     *
     * @return array
     */
    public static function getAdminOverview(): array
    {
        $db = Database::getInstance();

        // Total bots
        $totalBots = (int) $db->count("SELECT COUNT(*) FROM ai_bots");
        $activeBots = (int) $db->count("SELECT COUNT(*) FROM ai_bots WHERE status = 'active'");

        // Total conversations
        $totalConversations = (int) $db->count("SELECT COUNT(*) FROM ai_conversations");
        $activeConversations = (int) $db->count("SELECT COUNT(*) FROM ai_conversations WHERE status = 'active'");

        // Total messages
        $totalMessages = (int) $db->count("SELECT COUNT(*) FROM ai_messages");

        // Total tokens
        $totalTokens = $db->fetchColumn(
            "SELECT COALESCE(SUM(tokens_used), 0) FROM ai_messages WHERE tokens_used > 0"
        );

        // Today's stats
        $today = date('Y-m-d');
        $todayConversations = (int) $db->count(
            "SELECT COUNT(*) FROM ai_conversations WHERE DATE(created_at) = ?",
            [$today]
        );
        $todayMessages = (int) $db->count(
            "SELECT COUNT(*) FROM ai_messages WHERE DATE(created_at) = ?",
            [$today]
        );

        // Total handovers
        $totalHandovers = (int) $db->count("SELECT COUNT(*) FROM ai_handovers");
        $pendingHandovers = (int) $db->count("SELECT COUNT(*) FROM ai_handovers WHERE status = 'pending'");

        // Total leads
        $totalLeads = (int) $db->count("SELECT COUNT(*) FROM ai_leads");

        // Users with bots
        $usersWithBots = (int) $db->count("SELECT COUNT(DISTINCT user_id) FROM ai_bots");

        // Average response time
        $avgResponseTime = (float) $db->fetchColumn(
            "SELECT COALESCE(AVG(response_time_ms), 0) FROM ai_messages WHERE sender_type = 'ai' AND response_time_ms > 0"
        );

        // Model usage breakdown
        $modelUsage = $db->fetchAll(
            "SELECT ai_model, COUNT(*) as bot_count FROM ai_bots GROUP BY ai_model ORDER BY bot_count DESC"
        );

        // Top users by conversation count
        $topUsers = $db->fetchAll(
            "SELECT 
                b.user_id,
                COUNT(DISTINCT b.id) as bot_count,
                SUM(b.total_conversations) as total_conversations,
                SUM(b.total_messages_processed) as total_messages
             FROM ai_bots b
             GROUP BY b.user_id
             ORDER BY total_conversations DESC
             LIMIT 10"
        );

        return [
            'total_bots' => $totalBots,
            'active_bots' => $activeBots,
            'total_conversations' => $totalConversations,
            'active_conversations' => $activeConversations,
            'total_messages' => $totalMessages,
            'total_tokens_used' => $totalTokens,
            'today_conversations' => $todayConversations,
            'today_messages' => $todayMessages,
            'total_handovers' => $totalHandovers,
            'pending_handovers' => $pendingHandovers,
            'total_leads' => $totalLeads,
            'users_with_bots' => $usersWithBots,
            'avg_response_time' => round($avgResponseTime),
            'avg_response_time_formatted' => self::formatResponseTime($avgResponseTime),
            'model_usage' => $modelUsage,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Format response time in milliseconds to a human-readable string
     *
     * @param float $ms
     * @return string
     */
    private static function formatResponseTime(float $ms): string
    {
        if ($ms <= 0) {
            return '0ms';
        }

        if ($ms < 1000) {
            return round($ms) . 'ms';
        }

        $seconds = $ms / 1000;
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds - ($minutes * 60), 1);
        return "{$minutes}m {$remainingSeconds}s";
    }
}

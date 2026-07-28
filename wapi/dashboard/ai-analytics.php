<?php
/**
 * WAPI SaaS - AI Analytics Dashboard
 * Shows AI bot performance metrics, charts, and insights
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
Auth::requireLogin();

$db = Database::getInstance();
$settings = new Settings();
$userId = $_SESSION['user_id'];
$hideNav = true;

// Fetch user's bots for filter
try { $bots = $db->fetchAll("SELECT id, name FROM ai_bots WHERE user_id = ? ORDER BY name", [$userId]); } catch (Exception $e) { $bots = []; }
$selectedBot = sanitizeInt($_GET['bot'] ?? 0);
$period = $_GET['period'] ?? '30d';

// Calculate date range
$days = match($period) {
    '7d' => 7,
    '90d' => 90,
    default => 30
};
$dateFrom = date('Y-m-d', strtotime("-{$days} days"));

// Build conditions
$botCondition = $selectedBot > 0 ? "AND bot_id = ?" : "";
$botParams = $selectedBot > 0 ? [$userId, $selectedBot] : [$userId];

// Dashboard Stats (all wrapped in try-catch for pre-migration safety)
try { $totalConversations = $db->fetchColumn("SELECT COUNT(*) FROM ai_conversations WHERE user_id = ? $botCondition", $botParams) ?: 0; } catch (Exception $e) { $totalConversations = 0; }
try { $resolvedByAI = $db->fetchColumn("SELECT COUNT(*) FROM ai_conversations WHERE user_id = ? AND resolved_by = 'ai' $botCondition", $botParams) ?: 0; } catch (Exception $e) { $resolvedByAI = 0; }
try { $transferredToHuman = $db->fetchColumn("SELECT COUNT(*) FROM ai_conversations WHERE user_id = ? AND status = 'handed_over' $botCondition", $botParams) ?: 0; } catch (Exception $e) { $transferredToHuman = 0; }
try { $leadsGenerated = $db->fetchColumn("SELECT COUNT(*) FROM ai_leads WHERE user_id = ? $botCondition", $botParams) ?: 0; } catch (Exception $e) { $leadsGenerated = 0; }
try { $avgResponseTime = $db->fetchColumn("SELECT ROUND(AVG(response_time_ms)) FROM ai_messages WHERE bot_id IN (SELECT id FROM ai_bots WHERE user_id = ?) AND sender_type = 'ai'" . ($selectedBot > 0 ? " AND bot_id = ?" : ""), $botParams); } catch (Exception $e) { $avgResponseTime = null; }
try { $totalTokens = $db->fetchColumn("SELECT COALESCE(SUM(tokens_used), 0) FROM ai_messages WHERE bot_id IN (SELECT id FROM ai_bots WHERE user_id = ?) AND sender_type = 'ai'" . ($selectedBot > 0 ? " AND bot_id = ?" : ""), $botParams) ?: 0; } catch (Exception $e) { $totalTokens = 0; }

// Chart Data - Conversations per day
try {
    $chartParams = array_merge($botParams, [$dateFrom]);
    $chartData = $db->fetchAll("SELECT DATE(created_at) as date, 
        COUNT(*) as total,
        SUM(CASE WHEN resolved_by = 'ai' THEN 1 ELSE 0 END) as ai_resolved,
        SUM(CASE WHEN status = 'handed_over' THEN 1 ELSE 0 END) as human_transferred
        FROM ai_conversations 
        WHERE user_id = ? $botCondition AND DATE(created_at) >= ?
        GROUP BY DATE(created_at) 
        ORDER BY date ASC", $chartParams);
} catch (Exception $e) { $chartData = []; }

// Resolution Breakdown
try {
    $activeConvs = $db->fetchColumn("SELECT COUNT(*) FROM ai_conversations WHERE user_id = ? AND status = 'active' $botCondition", $botParams) ?: 0;
} catch (Exception $e) { $activeConvs = 0; }
$resolutionData = ['ai' => (int)$resolvedByAI, 'human' => (int)$transferredToHuman, 'active' => (int)$activeConvs];

// Most Asked Questions
try {
    $topQuestions = $db->fetchAll("SELECT content, COUNT(*) as ask_count 
        FROM ai_messages 
        WHERE bot_id IN (SELECT id FROM ai_bots WHERE user_id = ?) 
        AND sender_type = 'customer' AND LENGTH(content) > 10
        " . ($selectedBot > 0 ? "AND bot_id = ?" : "") . "
        GROUP BY content 
        ORDER BY ask_count DESC LIMIT 10", $botParams);
} catch (Exception $e) { $topQuestions = []; }

$pageTitle = 'AI Analytics';
$extraCss = [asset('assets/css/dashboard.css'), asset('assets/css/ai-chatbot.css')];
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', asset('assets/js/ai-analytics.js')];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="dash-header">
            <div>
                <h1 class="dash-title"><i class="bi bi-bar-chart-line-fill" style="background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i> AI Analytics</h1>
                <div class="dash-breadcrumb">
                    <a href="<?= baseUrl('dashboard/'); ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>AI Analytics</span>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-primary btn-sm d-lg-none" id="mobileSidebarToggle"><i class="bi bi-list"></i></button>
                <div class="d-flex align-items-center gap-2" style="background: var(--bg-secondary, #f3f4f6); border-radius: 8px; padding: 0.25rem 0.5rem 0.25rem 0.75rem;">
                    <i class="bi bi-robot" style="color: #667eea; font-size: 0.875rem;"></i>
                    <select class="form-select form-select-sm" style="width: auto; border: none; background: transparent; font-weight: 500; font-size: 0.8125rem; box-shadow: none;" onchange="location.href='?bot='+this.value+'&period=<?= e($period); ?>'">
                        <option value="0">All Bots</option>
                        <?php foreach ($bots as $b): ?>
                        <option value="<?= $b['id']; ?>" <?= $selectedBot == $b['id'] ? 'selected' : ''; ?>><?= e($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="period-filters">
                    <?php foreach (['7d' => '7D', '30d' => '30D', '90d' => '90D'] as $p => $label): ?>
                    <a href="?bot=<?= $selectedBot; ?>&period=<?= $p; ?>" class="period-btn <?= $period === $p ? 'active' : ''; ?>"><?= $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm purple"><i class="bi bi-chat-dots-fill"></i></div>
                    <div class="stat-number"><?= formatNumber($totalConversations); ?></div>
                    <div class="stat-title">Total Conversations</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm green"><i class="bi bi-robot"></i></div>
                    <div class="stat-number"><?= formatNumber($resolvedByAI); ?></div>
                    <div class="stat-title">Resolved by AI</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm orange"><i class="bi bi-person-check-fill"></i></div>
                    <div class="stat-number"><?= formatNumber($transferredToHuman); ?></div>
                    <div class="stat-title">Human Transfer</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm blue"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-number"><?= formatNumber($leadsGenerated); ?></div>
                    <div class="stat-title">Leads Generated</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm pink"><i class="bi bi-lightning-fill"></i></div>
                    <div class="stat-number"><?= $avgResponseTime ? round($avgResponseTime / 1000, 1) . 's' : '—'; ?></div>
                    <div class="stat-title">Avg Response</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="ai-stat-card">
                    <div class="stat-icon-sm teal"><i class="bi bi-cpu-fill"></i></div>
                    <div class="stat-number"><?= formatNumber($totalTokens); ?></div>
                    <div class="stat-title">Tokens Used</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">Conversations Over Time</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="aiConversationsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <div class="chart-header">
                        <h5 class="chart-title">Resolution Breakdown</h5>
                    </div>
                    <div class="chart-container d-flex align-items-center justify-content-center">
                        <canvas id="aiResolutionChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Asked Questions -->
        <div class="data-table">
            <div class="data-table-header">
                <h5 class="data-table-title">Most Asked Questions</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Question</th><th>Times Asked</th></tr></thead>
                    <tbody>
                        <?php if (empty($topQuestions)): ?>
                        <tr><td colspan="3" class="p-0 border-0">
                            <div class="ai-empty-state" style="border: none; padding: 3rem 2rem;">
                                <div class="empty-icon"><i class="bi bi-chat-square-quote"></i></div>
                                <h4>No Questions Yet</h4>
                                <p>Once customers start chatting with your AI bot, the most frequently asked questions will appear here.</p>
                            </div>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($topQuestions as $i => $q): ?>
                        <tr>
                            <td><?= $i + 1; ?></td>
                            <td style="max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($q['content']); ?></td>
                            <td><span class="badge-custom" style="background: var(--primary-bg); color: var(--primary);"><?= $q['ask_count']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
window.aiAnalyticsData = {
    chart: <?= json_encode($chartData); ?>,
    resolution: <?= json_encode($resolutionData); ?>
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

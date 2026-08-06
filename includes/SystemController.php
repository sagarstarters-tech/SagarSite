<?php
require_once __DIR__ . '/CacheService.php';
require_once __DIR__ . '/OptimizationService.php';

class SystemController {
    private $conn;
    private $cacheService;
    private $optimizationService;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->cacheService = new CacheService($conn);
        $this->optimizationService = new OptimizationService($conn);
    }

    /**
     * Execute full system refresh and optimization.
     */
    public function runFullMaintenance($params = []) {
        $report = [
            'sessions_cleared' => 0,
            'logs_cleared' => [],
            'database_optimization' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 1. Clear Sessions
        $report['sessions_cleared'] = $this->cacheService->clearSessions(24);

        // 2. Clear Logs (if requested)
        if (!empty($params['clear_logs'])) {
            $report['logs_cleared'] = $this->cacheService->clearLogTables(['email_logs', 'whatsapp_logs'], true);
        }

        // 3. Optimize Database
        $report['database_optimization'] = $this->optimizationService->optimizeDatabase();

        // 4. Log the action
        $this->logMaintenanceAction($report);

        return $report;
    }

    /**
     * Execute website speed boost optimizations safely without deleting data.
     */
    public function boostWebsiteSpeed() {
        $startTime = microtime(true);
        
        $report = [
            'analyzed_tables' => [],
            'cache_version' => '',
            'opcache_status' => '',
            'execution_time' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 1. Analyze Database Tables for optimal query execution plans
        $report['analyzed_tables'] = $this->optimizationService->analyzeDatabaseTables();

        // 2. Prime & update static site asset cache version
        $report['cache_version'] = $this->cacheService->primeSiteCache();

        // 3. Reset PHP OPcache Bytecode Cache
        $report['opcache_status'] = $this->cacheService->resetOpCache();

        // 4. Record execution time
        $report['execution_time'] = round((microtime(true) - $startTime) * 1000, 2); // ms

        // 5. Save audit timestamp
        $this->conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_speed_boost', '" . date('Y-m-d H:i:s') . "') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

        return $report;
    }

    /**
     * Log maintenance activity to a persistent storage or file.
     * For now, we will log to a dedicated table or file.
     */
    private function logMaintenanceAction($report) {
        $admin_id = $_SESSION['user_id'] ?? 0;
        $admin_name = $_SESSION['name'] ?? 'System';
        $optimized_count = is_array($report['database_optimization']) ? count($report['database_optimization']) : 0;
        $summary = "Sessions: " . ($report['sessions_cleared'] ?? 0) . ", Tables Optimized: " . $optimized_count;
        
        // Log to database if a system_logs table exists, or just use a settings audit log
        $summary = $this->conn->real_escape_string($summary);
        $this->conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_system_optimize', '" . date('Y-m-d H:i:s') . "') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        
        // Optionally create a dedicated log entry in a generic logs table if available
    }
}


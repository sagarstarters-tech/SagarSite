<?php

class CacheService {
    private $conn;
    private $sessionPath;

    public function __construct($conn) {
        $this->conn = $conn;
        // Automatically detect path or fallback to common XAMPP path if empty
        $this->sessionPath = session_save_path() ?: 'C:/xampp/tmp';
    }

    /**
     * Clear old session files from the temporary directory.
     * Only deletes sessions not accessed in the last 24 hours.
     */
    public function clearSessions($hours = 24) {
        $count = 0;
        $expiryTime = time() - ($hours * 3600);

        if (is_dir($this->sessionPath)) {
            $files = glob($this->sessionPath . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $expiryTime) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Prune old log tables to reclaim space while keeping recent history.
     * Keeps logs for the last 30 days unless $all is true.
     */
    public function clearLogTables($tables = ['email_logs', 'whatsapp_logs'], $all = false) {
        $results = [];
        foreach ($tables as $table) {
            $table = $this->conn->real_escape_string($table);
            // Use DELETE instead of TRUNCATE to preserve last 30 days of audit logs (unless $all is true)
            $dateField = ($table === 'whatsapp_logs') ? 'sent_at' : 'created_at';
            $sql = $all ? "DELETE FROM $table" : "DELETE FROM $table WHERE $dateField < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            if ($this->conn->query($sql)) {
                $affected = $this->conn->affected_rows;
                $results[$table] = $all ? "Cleared all ($affected entries removed)" : "Pruned ($affected old entries removed)";
            } else {
                $results[$table] = "Error: " . $this->conn->error;
            }
        }
        return $results;
    }

    /**
     * Prime and update site cache version key in database settings table.
     * This forces browser cache bust for updated CSS/JS assets without data loss.
     */
    public function primeSiteCache() {
        $version = time();
        $stmt = $this->conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_cache_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $stmt->bind_param('s', $version);
            $stmt->execute();
            $stmt->close();
        }
        return $version;
    }

    /**
     * Clear PHP Bytecode / OPcache if OPcache extension is enabled.
     */
    public function resetOpCache() {
        if (function_exists('opcache_reset') && @opcache_reset()) {
            return 'OPcache successfully flushed';
        }
        return 'OPcache not active or not configurable on host';
    }

    /**
     * Placeholder for compiled template cache clearing if implemented in future.
     */
    public function clearTemplateCache() {
        // Implement if any templating engine like Smarty/Twig is added
        return 0;
    }
}


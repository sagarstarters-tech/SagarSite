<?php

class ScriptRepository {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Ensure custom_scripts table and default row exist.
     */
    private function ensureTable() {
        if (!$this->conn) return;

        $sql = "CREATE TABLE IF NOT EXISTS custom_scripts (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            header_code TEXT,
            body_code TEXT,
            footer_code TEXT,
            google_verification TEXT,
            bing_verification TEXT,
            custom_verification TEXT,
            txt_instructions TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $this->conn->query($sql);
            $check = $this->conn->query("SELECT id FROM custom_scripts WHERE id = 1");
            if ($check && $check->num_rows === 0) {
                $this->conn->query("INSERT INTO custom_scripts (id, header_code) VALUES (1, '')");
            }
            // Ensure body_code column exists for older installations
            try {
                $this->conn->query("ALTER TABLE custom_scripts ADD COLUMN body_code TEXT AFTER header_code");
            } catch (Throwable $e) {}
        } catch (Throwable $e) {
            error_log('[ScriptRepository] Table setup warning: ' . $e->getMessage());
        }
    }

    /**
     * Get all custom scripts metadata.
     */
    public function getScripts() {
        try {
            $res = $this->conn->query("SELECT * FROM custom_scripts WHERE id = 1");
            if ($res && $res->num_rows > 0) {
                return $res->fetch_assoc();
            }
        } catch (Throwable $e) {
            error_log('[ScriptRepository] getScripts error: ' . $e->getMessage());
        }

        return [
            'header_code' => '',
            'body_code' => '',
            'footer_code' => '',
            'google_verification' => '',
            'bing_verification' => '',
            'custom_verification' => '',
            'txt_instructions' => ''
        ];
    }

    /**
     * Update script data.
     */
    public function updateScripts($data) {
        $fields = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['header_code', 'body_code', 'footer_code', 'google_verification', 'bing_verification', 'custom_verification', 'txt_instructions'])) {
                $fields[] = "`$key` = '" . $this->conn->real_escape_string($value) . "'";
            }
        }
        
        if (empty($fields)) return true;
        
        $fields_sql = implode(", ", $fields);
        try {
            $res = $this->conn->query("UPDATE custom_scripts SET $fields_sql WHERE id = 1");
            if ($res) return true;
        } catch (Throwable $e) {
            // Attempt auto-recovery
            $this->ensureTable();
            return $this->conn->query("UPDATE custom_scripts SET $fields_sql WHERE id = 1");
        }

        return false;
    }
}

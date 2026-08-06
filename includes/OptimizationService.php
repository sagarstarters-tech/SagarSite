<?php

class OptimizationService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Run OPTIMIZE TABLE on all tables in the current database.
     */
    public function optimizeDatabase() {
        $report = [];
        $tables_q = $this->conn->query("SHOW TABLES");
        
        if ($tables_q && $tables_q->num_rows > 0) {
            while ($row = $tables_q->fetch_array()) {
                $table = $row[0];
                $res_q = $this->conn->query("OPTIMIZE TABLE `$table`");
                if ($res_q) {
                    $res = $res_q->fetch_assoc();
                    $report[$table] = $res['Msg_text'] ?? 'Done';
                } else {
                    $report[$table] = 'Error: ' . $this->conn->error;
                }
            }
        }
        
        return $report;
    }

    /**
     * Run ANALYZE TABLE on all tables in the current database.
     * Safe performance optimization that updates index statistics without altering or deleting data.
     */
    public function analyzeDatabaseTables() {
        $report = [];
        $tables_q = $this->conn->query("SHOW TABLES");
        
        if ($tables_q && $tables_q->num_rows > 0) {
            while ($row = $tables_q->fetch_array()) {
                $table = $row[0];
                $res_q = $this->conn->query("ANALYZE TABLE `$table`");
                if ($res_q) {
                    $res = $res_q->fetch_assoc();
                    $report[$table] = $res['Msg_text'] ?? 'Table structure & index analyzed';
                } else {
                    $report[$table] = 'Error: ' . $this->conn->error;
                }
            }
        }
        
        return $report;
    }

    /**
     * Check for unused temporary folders or files (Scan only).
     */
    public function scanTemporaryFiles() {
        $uploadDir = '../assets/images/';
        $tempCount = 0;
        // Logic to find orphaned images could go here in future
        return $tempCount;
    }
}


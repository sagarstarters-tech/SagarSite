<?php
/**
 * ============================================================
 *  DATABASE — Singleton PDO Connection Class
 *  Location: /config/DbConnection.php
 * ============================================================
 *  Provides a single shared PDO connection instance across the
 *  entire application. Lazily initialized on first call.
 *
 *  Usage (anywhere in the project):
 *      require_once BASE_PATH . '/config/DbConnection.php';
 *      $pdo = DbConnection::getInstance();
 *      $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
 *      $stmt->execute([$id]);
 *      $row = $stmt->fetch();
 * ============================================================
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

class DbConnection {

    /** @var PDO|null Shared PDO instance */
    private static ?PDO $instance = null;

    /** Prevent direct instantiation */
    private function __construct() {}
    /** Prevent cloning */
    private function __clone() {}

    /**
     * Get (or create) the shared PDO connection.
     *
     * @return PDO
     * @throws RuntimeException on connection failure
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+05:30'",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (\PDOException $e) {
                // In production, never expose the connection error message publicly
                if (defined('APP_ENV') && APP_ENV === 'production') {
                    error_log('[DB] Connection failed: ' . $e->getMessage());
                    throw new \RuntimeException('Database connection failed. Please try again later.');
                }
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Reset the shared instance (useful for testing / reconnect scenarios).
     */
    public static function reset(): void {
        self::$instance = null;
    }
}

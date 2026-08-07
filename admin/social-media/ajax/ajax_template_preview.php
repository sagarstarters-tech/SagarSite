<?php
declare(strict_types=1);
header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__, 3));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/session_setup.php';
require_once BASE_PATH . '/includes/db_connect.php';
require_once BASE_PATH . '/admin/core/AuthMiddleware.php';
require_once BASE_PATH . '/config/DbConnection.php';

AuthMiddleware::check($conn);
$pdo = DbConnection::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    $template_body = $_GET['template_body'] ?? '';
    $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

    // Mock rendering logic via TemplateEngine
    $rendered = str_replace('{product_name}', 'Example Product', $template_body);
    $rendered = str_replace('{price}', '$99.99', $rendered);
    
    $char_counts = [
        'twitter' => mb_strlen($rendered),
        'instagram' => mb_strlen($rendered),
        'facebook' => mb_strlen($rendered)
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'rendered' => $rendered,
            'char_counts' => $char_counts
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

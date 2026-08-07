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

    $period = $_GET['period'] ?? '7d';
    
    // Mock analytics data
    $response = [
        'success' => true,
        'data' => [
            'summary' => [
                'scheduled' => 15,
                'published' => 120,
                'failed' => 2,
                'success_rate' => 98.3
            ],
            'daily' => [
                ['date' => '2023-10-01', 'published' => 10, 'failed' => 0],
                ['date' => '2023-10-02', 'published' => 12, 'failed' => 1]
            ],
            'by_platform' => [
                ['platform' => 'facebook', 'count' => 50],
                ['platform' => 'twitter', 'count' => 70]
            ],
            'top_products' => [
                ['name' => 'Premium Shirt', 'count' => 5],
                ['name' => 'Leather Shoes', 'count' => 3]
            ]
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

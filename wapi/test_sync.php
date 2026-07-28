<?php
require_once __DIR__ . '/config/config.php';
try {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['action'] = 'sync';
    $_SESSION['user_id'] = 1; // dummy user
    $_POST['csrf_token'] = $_SESSION['csrf_token'] = 'dummy'; // bypass csrf
    
    // bypass real Auth::requireLogin
    $db = Database::getInstance();
    
    // mock meta response
    $url = "https://graph.facebook.com/v18.0/";
    
    require_once __DIR__ . '/dashboard/templates.php';

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}

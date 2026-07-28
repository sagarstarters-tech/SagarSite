<?php
/**
 * WAPI SaaS Platform - Autoloader
 * Automatically loads classes and helper files
 */

// PSR-4 style autoloader for classes
spl_autoload_register(function ($className) {
    $classFile = APP_ROOT . '/classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Load helper functions
require_once APP_ROOT . '/helpers/functions.php';
require_once APP_ROOT . '/helpers/security.php';

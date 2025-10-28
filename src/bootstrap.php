<?php
/**
 * OpenBookManager Bootstrap
 *
 * This file initializes the application
 */

// Start session
session_start();

// Check if installation is needed
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    // Allow access to installer
    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '/install/') === false) {
        header('Location: /install/');
        exit;
    }

    // Return minimal config for installer
    return [
        'config' => ['app' => ['name' => 'OpenBookManager', 'debug' => true]],
        'db' => null,
    ];
}

// Set error reporting based on debug mode
$config = require $configFile;

if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = '';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Class uses the namespace prefix, continue
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Helper functions
require __DIR__ . '/helpers.php';

// Database connection
try {
    $db = new PDO(
        "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['database']};charset={$config['database']['charset']}",
        $config['database']['username'],
        $config['database']['password'],
        $config['database']['options']
    );
} catch (PDOException $e) {
    if ($config['app']['debug']) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('Database connection failed. Please check your configuration.');
    }
}

return [
    'config' => $config,
    'db' => $db,
];

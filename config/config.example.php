<?php
/**
 * OpenBookManager Configuration Example
 *
 * Copy this file to config.php and adjust the values for your environment
 */

return [
    // Database Configuration
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'openbookmanager',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // Application Settings
    'app' => [
        'name' => 'OpenBookManager',
        'version' => '1.0.0',
        'url' => 'http://localhost',
        'timezone' => 'Europe/Berlin',
        'debug' => true, // Set to false in production
    ],

    // Session Configuration
    'session' => [
        'name' => 'OPENBOOKMANAGER_SESSION',
        'lifetime' => 7200, // 2 hours in seconds
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // Upload Configuration
    'upload' => [
        'path' => __DIR__ . '/../public/uploads/',
        'url' => '/uploads/',
        'max_size' => 5 * 1024 * 1024, // 5 MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    ],

    // Pagination (per object type)
    'pagination' => [
        'books' => 50,
        'authors' => 20,
        'categories' => 50,
        'wishlist' => 20,
    ],

    // ISBN API Configuration (for future use)
    'isbn_api' => [
        'google_books_api_key' => '', // Optional: Get from https://console.cloud.google.com/
        'rate_limit' => 1, // Requests per second
    ],

    // Zebra Printer Configuration (for future use)
    'printer' => [
        'enabled' => false,
        'service_url' => 'http://localhost:9100',
    ],
];

<?php
/**
 * OpenBookManager Configuration for Docker
 *
 * This configuration is automatically used when running in Docker containers.
 * The start script will copy this to config.php if needed.
 */

return [
    // Database Configuration (Docker container)
    'database' => [
        'host' => 'db',  // Docker service name from docker-compose.yml
        'port' => 3306,
        'database' => 'openbookmanager',
        'username' => 'bookmanager',
        'password' => 'bookmanager123',
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
        'url' => 'http://localhost:8000',
        'timezone' => 'Europe/Berlin',
        'debug' => true, // Development mode
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
        'max_size' => 5 * 1024 * 1024, // 5 MB (for cover images)
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff'], // TIFF auto-converts to JPG
    ],

    // Pagination (per object type)
    'pagination' => [
        'books' => 50,
        'authors' => 20,
        'categories' => 50,
        'wishlist' => 20,
    ],

    // ISBN API Configuration
    'isbn_api' => [
        'google_books_api_key' => '', // Optional: Get from https://console.cloud.google.com/
        'rate_limit' => 1, // Requests per second
    ],

    // Label Printer Configuration
    'labels' => [
        'enabled' => true,

        // Label dimensions (38x19mm - adjust based on your label stock)
        'width_mm' => 38,
        'height_mm' => 19,
        'dpi' => 203, // Zebra ZD220t standard DPI

        // ZPL Settings
        'darkness' => 15,
        'print_speed' => 4,

        // Font sizes optimized for 38x19mm labels
        'category_label' => [
            'code_size' => 45,
            'title_size' => 25,
        ],
        'spine_label' => [
            'tag_size' => 28,
            'barcode_height' => 35,
        ],
        'full_label' => [
            'tag_size' => 20,
            'barcode_height' => 28,
            'author_size' => 12,
            'title_size' => 12,
        ],
    ],

    // Document Upload Configuration
    'documents' => [
        'enabled' => true,
        'max_size_mb' => 100,
        'allowed_types' => ['pdf', 'epub'],
    ],

];

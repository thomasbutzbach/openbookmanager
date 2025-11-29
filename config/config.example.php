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

    // Label Printer Configuration
    'labels' => [
        'enabled' => true,

        // Label dimensions (adjust based on your label stock)
        'width_mm' => 50,    // Label width in millimeters
        'height_mm' => 25,   // Label height in millimeters
        'dpi' => 203,        // Printer DPI (203 or 300 typical for Zebra)

        // ZPL Settings
        'darkness' => 15,    // Print darkness (0-30, default 15)
        'print_speed' => 4,  // Print speed (2-6, default 4)

        // Font sizes (ZPL font sizes)
        'category_label' => [
            'code_size' => 40,     // Font size for category code
            'title_size' => 20,    // Font size for category title
        ],
        'spine_label' => [
            'tag_size' => 35,      // Font size for book tag
            'barcode_height' => 50, // Barcode height in dots
        ],
        'full_label' => [
            'tag_size' => 25,      // Font size for book tag
            'barcode_height' => 35, // Barcode height in dots
            'author_size' => 15,    // Font size for author
            'title_size' => 15,     // Font size for title
        ],
    ],

];

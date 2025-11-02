<?php
/**
 * Full JSON Export
 * Creates a structured JSON export with all data and relationships
 * Human-readable and editable before re-import
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

try {
    $export = [
        'metadata' => [
            'application' => 'OpenBookManager',
            'version' => '1.0.0',
            'export_date' => date('Y-m-d H:i:s'),
            'export_type' => 'full',
        ],
        'data' => []
    ];

    // Export Main Categories
    $stmt = $db->query('SELECT * FROM maincategories ORDER BY code');
    $export['data']['maincategories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Categories
    $stmt = $db->query('SELECT * FROM categories ORDER BY code_maincategory, code');
    $export['data']['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Category Sequences
    $stmt = $db->query('SELECT * FROM category_sequences ORDER BY code_category');
    $export['data']['category_sequences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Authors
    $stmt = $db->query('SELECT * FROM authors ORDER BY lastname, surname');
    $export['data']['authors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Books with relationships
    $stmt = $db->query('
        SELECT b.*,
               GROUP_CONCAT(ba.author_id) as author_ids
        FROM books b
        LEFT JOIN book_author ba ON b.id = ba.book_id
        GROUP BY b.id, b.code_category, b.code_maincategory
        ORDER BY b.id
    ');
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert author_ids to array
    foreach ($books as &$book) {
        if ($book['author_ids']) {
            $book['author_ids'] = array_map('intval', explode(',', $book['author_ids']));
        } else {
            $book['author_ids'] = [];
        }
    }
    $export['data']['books'] = $books;

    // Export Book-Author relationships (for completeness)
    $stmt = $db->query('SELECT * FROM book_author ORDER BY book_id, author_id');
    $export['data']['book_author'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Scanned Books
    $stmt = $db->query('SELECT * FROM scanned_books ORDER BY scanned_at DESC');
    $export['data']['scanned_books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Wishlist
    $stmt = $db->query('SELECT * FROM wishlist ORDER BY created_at DESC');
    $export['data']['wishlist'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Users (excluding passwords for security)
    $stmt = $db->query('SELECT id, username, created_at, updated_at FROM users ORDER BY id');
    $export['data']['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export System Info
    $stmt = $db->query('SHOW TABLES LIKE "system_info"');
    if ($stmt->rowCount() > 0) {
        $stmt = $db->query('SELECT * FROM system_info');
        $export['data']['system_info'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Export Changelog (if exists)
    $stmt = $db->query('SHOW TABLES LIKE "changelog"');
    if ($stmt->rowCount() > 0) {
        $stmt = $db->query('SELECT * FROM changelog ORDER BY created_at DESC LIMIT 100');
        $export['data']['changelog'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add statistics
    $export['metadata']['statistics'] = [
        'books' => count($export['data']['books']),
        'scanned_books' => count($export['data']['scanned_books']),
        'authors' => count($export['data']['authors']),
        'maincategories' => count($export['data']['maincategories']),
        'categories' => count($export['data']['categories']),
        'wishlist' => count($export['data']['wishlist']),
        'users' => count($export['data']['users']),
    ];

    // Convert to JSON
    $jsonContent = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Send as download
    $filename = 'openbookmanager_export_' . date('Y-m-d_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($jsonContent));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $jsonContent;
    exit;

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

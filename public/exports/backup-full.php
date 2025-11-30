<?php
/**
 * Full Backup with Files
 * Creates a ZIP archive containing:
 * - backup.json (all database data)
 * - uploads/covers/ (cover images)
 * - uploads/documents/ (PDF/EPUB files)
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    setFlash('error', 'ZIP extension is not available on this server.');
    header('Location: /settings/');
    exit;
}

try {
    // Create temporary file for ZIP
    $tempFile = tempnam(sys_get_temp_dir(), 'obm_backup_');
    $zip = new ZipArchive();

    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Could not create ZIP archive.');
    }

    // === Generate JSON Export ===
    $export = [
        'metadata' => [
            'application' => 'OpenBookManager',
            'version' => '1.0.0',
            'export_date' => date('Y-m-d H:i:s'),
            'export_type' => 'full_with_files',
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

    // Export Book-Author relationships
    $stmt = $db->query('SELECT * FROM book_author ORDER BY book_id, author_id');
    $export['data']['book_author'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Scanned Books
    $stmt = $db->query('SELECT * FROM scanned_books ORDER BY scanned_at DESC');
    $export['data']['scanned_books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Wishlist
    $stmt = $db->query('SELECT * FROM wishlist ORDER BY created_at DESC');
    $export['data']['wishlist'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export Users (excluding passwords)
    $stmt = $db->query('SELECT id, username, created_at, updated_at FROM users ORDER BY id');
    $export['data']['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Export System Info
    $stmt = $db->query('SHOW TABLES LIKE "system_info"');
    if ($stmt->rowCount() > 0) {
        $stmt = $db->query('SELECT * FROM system_info');
        $export['data']['system_info'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    // Add JSON to ZIP
    $jsonContent = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $zip->addFromString('backup.json', $jsonContent);

    // === Add Cover Images ===
    $coversDir = __DIR__ . '/../uploads/covers/';
    $coverCount = 0;
    if (is_dir($coversDir)) {
        $files = glob($coversDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                $zip->addFile($file, 'uploads/covers/' . basename($file));
                $coverCount++;
            }
        }
    }

    // === Add Documents (PDF/EPUB) ===
    $documentsDir = __DIR__ . '/../uploads/documents/';
    $documentCount = 0;
    if (is_dir($documentsDir)) {
        $files = glob($documentsDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                $zip->addFile($file, 'uploads/documents/' . basename($file));
                $documentCount++;
            }
        }
    }

    // Add file statistics to metadata
    $export['metadata']['files'] = [
        'covers' => $coverCount,
        'documents' => $documentCount,
    ];

    // Update JSON with file counts
    $jsonContent = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $zip->addFromString('backup.json', $jsonContent);

    // Close ZIP
    $zip->close();

    // Send ZIP as download
    $filename = 'openbookmanager_backup_' . date('Y-m-d') . '.zip';
    $filesize = filesize($tempFile);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file and clean up
    readfile($tempFile);
    unlink($tempFile);
    exit;

} catch (Exception $e) {
    // Clean up temp file on error
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    setFlash('error', 'Backup failed: ' . $e->getMessage());
    header('Location: /settings/');
    exit;
}

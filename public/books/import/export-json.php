<?php
/**
 * Export Scanned Books as JSON
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

requireAuth();

try {
    // Get all scanned books
    $stmt = $db->query('
        SELECT * FROM scanned_books
        ORDER BY scanned_at DESC
    ');
    $scannedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare export data
    $export = [
        'exported_at' => date('Y-m-d H:i:s'),
        'exported_by' => $_SESSION['username'] ?? 'unknown',
        'total_books' => count($scannedBooks),
        'books' => $scannedBooks
    ];

    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="scanned-books-' . date('Y-m-d-His') . '.json"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

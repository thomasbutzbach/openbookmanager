<?php
/**
 * AJAX Endpoint: Delete scanned book
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get ID from request
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    // Get book info before deleting (for cover cleanup)
    $stmt = $db->prepare('SELECT cover_local FROM scanned_books WHERE id = ?');
    $stmt->execute([$id]);
    $book = $stmt->fetch();

    if (!$book) {
        echo json_encode(['success' => false, 'error' => 'Book not found']);
        exit;
    }

    // Delete from database
    $stmt = $db->prepare('DELETE FROM scanned_books WHERE id = ?');
    $stmt->execute([$id]);

    // Delete local cover file if exists
    if ($book['cover_local']) {
        $coverPath = __DIR__ . '/../../..' . $book['cover_local'];
        if (file_exists($coverPath)) {
            @unlink($coverPath);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete book'
    ]);
}

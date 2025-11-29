<?php
/**
 * AJAX Endpoint: Update suggested category for a scanned book
 * This is for Phase 1: Quick categorization without importing
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$scannedBookId = (int)($input['id'] ?? 0);
$mainCategory = trim($input['main_category'] ?? '');
$category = trim($input['category'] ?? '');

if ($scannedBookId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid book ID']);
    exit;
}

try {
    // Allow clearing the suggestion by passing empty strings
    $mainCategoryValue = $mainCategory === '' ? null : $mainCategory;
    $categoryValue = $category === '' ? null : $category;

    // Update suggested categories
    $stmt = $db->prepare('
        UPDATE scanned_books
        SET suggested_code_maincategory = ?,
            suggested_code_category = ?
        WHERE id = ?
    ');
    $stmt->execute([$mainCategoryValue, $categoryValue, $scannedBookId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Book not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Category suggestion updated',
        'data' => [
            'id' => $scannedBookId,
            'suggested_code_maincategory' => $mainCategoryValue,
            'suggested_code_category' => $categoryValue
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

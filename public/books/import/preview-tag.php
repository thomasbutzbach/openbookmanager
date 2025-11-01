<?php
/**
 * AJAX Endpoint: Preview what the book tag will be for a given category
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

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
$categoryCode = trim($input['category'] ?? '');

if (empty($categoryCode)) {
    echo json_encode(['success' => false, 'error' => 'Category is required']);
    exit;
}

try {
    // Get category info including main category
    $stmt = $db->prepare('
        SELECT c.code, c.title, m.code as maincat_code
        FROM categories c
        JOIN maincategories m ON c.code_maincategory = m.code
        WHERE c.code = ?
    ');
    $stmt->execute([$categoryCode]);
    $category = $stmt->fetch();

    if (!$category) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }

    // Get next number for this category
    $stmt = $db->prepare('SELECT next_number FROM category_sequences WHERE code_category = ?');
    $stmt->execute([$categoryCode]);
    $sequence = $stmt->fetch();

    $nextNumber = $sequence ? $sequence['next_number'] : 1;

    // Format tag: MAINCAT SUBCAT NUMBER
    $tag = sprintf('%s %s %04d', $category['maincat_code'], $category['code'], $nextNumber);

    echo json_encode([
        'success' => true,
        'tag' => $tag,
        'next_number' => $nextNumber
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

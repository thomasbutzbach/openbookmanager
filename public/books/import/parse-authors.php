<?php
/**
 * AJAX Endpoint: Parse author names and match against existing authors
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
$authorsRaw = trim($input['authors_raw'] ?? '');

if (empty($authorsRaw)) {
    echo json_encode(['success' => true, 'authors' => []]);
    exit;
}

try {
    $parsedAuthors = parseAndMatchAuthors($db, $authorsRaw);

    echo json_encode([
        'success' => true,
        'authors' => $parsedAuthors
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to parse authors'
    ]);
}

<?php
/**
 * AJAX Endpoint: Process book import from scanned_books to books
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

// Validate required fields
$scannedBookId = (int)($input['scanned_book_id'] ?? 0);
$title = trim($input['title'] ?? '');
$category = trim($input['category'] ?? '');
$authors = $input['authors'] ?? [];

if ($scannedBookId <= 0 || empty($title) || empty($category) || empty($authors)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db->beginTransaction();

    // Get main category for the selected category
    $stmt = $db->prepare('SELECT code_maincategory FROM categories WHERE code = ?');
    $stmt->execute([$category]);
    $categoryData = $stmt->fetch();

    if (!$categoryData) {
        throw new Exception('Invalid category selected');
    }

    $codeMaincategory = $categoryData['code_maincategory'];

    // Get and increment sequence for this category (proper autoincrement behavior)
    $stmt = $db->prepare('
        INSERT INTO category_sequences (code_category, code_maincategory, next_number)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE next_number = next_number + 1
    ');
    $stmt->execute([$category, $codeMaincategory]);

    // Get the new number
    $stmt = $db->prepare('SELECT next_number FROM category_sequences WHERE code_category = ? AND code_maincategory = ?');
    $stmt->execute([$category, $codeMaincategory]);
    $numberInCategory = $stmt->fetch()['next_number'];

    // Get scanned book info (for cover)
    $stmt = $db->prepare('SELECT cover_local, cover_url FROM scanned_books WHERE id = ?');
    $stmt->execute([$scannedBookId]);
    $scannedBook = $stmt->fetch();

    if (!$scannedBook) {
        throw new Exception('Scanned book not found');
    }

    // Insert book
    $stmt = $db->prepare('
        INSERT INTO books (
            title, year, isbn, cover_image, code_category, code_maincategory,
            number_in_category, publisher, language, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $title,
        $input['year'] ?: null,
        $input['isbn'] ?: null,
        $scannedBook['cover_local'] ?: $scannedBook['cover_url'],
        $category,
        $codeMaincategory,
        $numberInCategory,
        $input['publisher'] ?: null,
        $input['language'] ?: null,
        $input['subtitle'] ? 'Subtitle: ' . $input['subtitle'] : null
    ]);

    $bookId = $db->lastInsertId();

    // Handle authors
    $authorIds = [];
    foreach ($authors as $author) {
        $authorId = null;

        if (!empty($author['existing_id'])) {
            // Use existing author
            $authorId = $author['existing_id'];
        } else {
            // Create new author
            $stmt = $db->prepare('INSERT INTO authors (surname, lastname) VALUES (?, ?)');
            $stmt->execute([
                $author['surname'],
                $author['lastname']
            ]);
            $authorId = $db->lastInsertId();
        }

        $authorIds[] = $authorId;
    }

    // Link book to authors
    $stmt = $db->prepare('INSERT INTO book_author (book_id, author_id) VALUES (?, ?)');
    foreach ($authorIds as $authorId) {
        $stmt->execute([$bookId, $authorId]);
    }

    // Sequence already updated by INSERT ... ON DUPLICATE KEY UPDATE above
    // No need for separate UPDATE

    // Update scanned book status
    $stmt = $db->prepare('
        UPDATE scanned_books
        SET status = "imported", imported_book_id = ?, imported_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$bookId, $scannedBookId]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'book_id' => $bookId,
        'message' => 'Book imported successfully'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

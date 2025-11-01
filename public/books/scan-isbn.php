<?php
/**
 * AJAX Endpoint: Scan ISBN and save to scanned_books
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
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

// Get ISBN from request
$input = json_decode(file_get_contents('php://input'), true);
$isbn = trim($input['isbn'] ?? '');

if (empty($isbn)) {
    echo json_encode(['success' => false, 'error' => 'ISBN is required']);
    exit;
}

// Clean ISBN
$isbn = preg_replace('/[^0-9X]/i', '', $isbn);

// Validate ISBN length (10 or 13 digits)
if (strlen($isbn) != 10 && strlen($isbn) != 13) {
    echo json_encode(['success' => false, 'error' => 'Invalid ISBN format']);
    exit;
}

try {
    // Check for duplicates in scanned_books
    $stmt = $db->prepare('SELECT id FROM scanned_books WHERE isbn = ?');
    $stmt->execute([$isbn]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'already_scanned',
            'message' => 'This book was already scanned'
        ]);
        exit;
    }

    // Check for duplicates in books
    $stmt = $db->prepare('SELECT id, title FROM books WHERE isbn = ?');
    $stmt->execute([$isbn]);
    if ($existingBook = $stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'already_in_collection',
            'message' => 'This book is already in your collection',
            'book' => $existingBook
        ]);
        exit;
    }

    // Fetch from Google Books API
    $bookData = fetchBookByISBN($isbn);

    if (!$bookData) {
        echo json_encode([
            'success' => false,
            'error' => 'not_found',
            'message' => 'Book not found in Google Books API'
        ]);
        exit;
    }

    // Download cover if available
    $coverLocal = null;
    if ($bookData['cover_url']) {
        $coverLocal = downloadBookCover($bookData['cover_url'], $isbn);
    }

    // Convert authors array to comma-separated string
    $authorsRaw = !empty($bookData['authors']) ? implode(', ', $bookData['authors']) : null;

    // Insert into scanned_books
    $stmt = $db->prepare('
        INSERT INTO scanned_books (
            isbn, title, subtitle, authors_raw, published_year,
            publisher, pages, language, description,
            cover_url, cover_local, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $bookData['isbn'],
        $bookData['title'],
        $bookData['subtitle'],
        $authorsRaw,
        $bookData['published_year'],
        $bookData['publisher'],
        $bookData['pages'],
        $bookData['language'],
        $bookData['description'],
        $bookData['cover_url'],
        $coverLocal,
        'pending'
    ]);

    $scannedBookId = $db->lastInsertId();

    // Return success with book data
    echo json_encode([
        'success' => true,
        'book' => [
            'id' => $scannedBookId,
            'isbn' => $bookData['isbn'],
            'title' => $bookData['title'],
            'subtitle' => $bookData['subtitle'],
            'authors' => $authorsRaw,
            'published_year' => $bookData['published_year'],
            'publisher' => $bookData['publisher'],
            'pages' => $bookData['pages'],
            'cover_url' => $coverLocal ?: $bookData['cover_url'],
            'scanned_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred while processing the scan'
    ]);
}

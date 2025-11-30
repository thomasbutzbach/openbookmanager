<?php
/**
 * Book Document Download
 *
 * Secure endpoint for downloading book documents (PDF/EPUB).
 * Requires authentication and validates the file path.
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get book ID
$bookId = $_GET['id'] ?? null;

if (!$bookId || !is_numeric($bookId)) {
    setFlash('error', 'Invalid book ID.');
    header('Location: /books/');
    exit;
}

try {
    // Fetch book with document info
    $stmt = $db->prepare('SELECT id, title, document_file FROM books WHERE id = ?');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        setFlash('error', 'Book not found.');
        header('Location: /books/');
        exit;
    }

    if (empty($book['document_file'])) {
        setFlash('error', 'No document available for this book.');
        header('Location: /books/view.php?id=' . $bookId);
        exit;
    }

    // Security: Validate path is within documents directory
    $relativePath = $book['document_file'];
    if (strpos($relativePath, '/uploads/documents/') !== 0) {
        setFlash('error', 'Invalid document path.');
        header('Location: /books/view.php?id=' . $bookId);
        exit;
    }

    // Build full path
    $filename = basename($relativePath);
    $fullPath = getDocumentsDir() . $filename;

    // Check file exists
    if (!file_exists($fullPath)) {
        setFlash('error', 'Document file not found on server.');
        header('Location: /books/view.php?id=' . $bookId);
        exit;
    }

    // Get MIME type
    $mimeType = getDocumentMimeType($relativePath);
    if (!$mimeType) {
        setFlash('error', 'Unknown document type.');
        header('Location: /books/view.php?id=' . $bookId);
        exit;
    }

    // Build download filename from book title
    $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
    $safeTitle = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $book['title']);
    $safeTitle = preg_replace('/_+/', '_', $safeTitle); // Collapse multiple underscores
    $safeTitle = trim($safeTitle, '_');
    $downloadFilename = $safeTitle . '.' . $extension;

    // Send file
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Clear output buffer and send file
    ob_clean();
    flush();
    readfile($fullPath);
    exit;

} catch (PDOException $e) {
    setFlash('error', 'Database error.');
    header('Location: /books/');
    exit;
}

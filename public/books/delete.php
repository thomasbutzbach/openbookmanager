<?php
/**
 * Delete Book
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$bookId = $_GET['id'] ?? null;

if (!$bookId) {
    setFlash('error', 'Book ID is required.');
    redirect('/books/');
}

// Get book details for confirmation
try {
    $stmt = $db->prepare('
        SELECT b.*,
               c.title as category_title,
               mc.code as maincat_code
        FROM books b
        JOIN categories c ON b.code_category = c.code AND b.code_maincategory = c.code_maincategory
        JOIN maincategories mc ON c.code_maincategory = mc.code
        WHERE b.id = ?
    ');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        setFlash('error', 'Book not found.');
        redirect('/books/');
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        try {
            // Delete book (cascade will delete book_author entries)
            $stmt = $db->prepare('DELETE FROM books WHERE id = ?');
            $stmt->execute([$bookId]);

            setFlash('success', 'Book deleted successfully!');
            redirect('/books/');

        } catch (PDOException $e) {
            setFlash('error', 'Database error: ' . $e->getMessage());
            redirect('/books/view.php?id=' . $bookId);
        }
    } else {
        redirect('/books/view.php?id=' . $bookId);
    }
}

$bookTag = generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']);

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Delete Book</h1>
        <a href="/books/view.php?id=<?= $bookId ?>" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="section">
        <div class="alert alert-warning">
            <strong>⚠️ Warning:</strong> You are about to delete this book. This action cannot be undone!
        </div>

        <div class="delete-confirmation">
            <h2>Book Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Tag</label>
                    <div><code><?= $bookTag ?></code></div>
                </div>

                <div class="detail-item">
                    <label>Title</label>
                    <div><strong><?= e($book['title']) ?></strong></div>
                </div>

                <div class="detail-item">
                    <label>Category</label>
                    <div><?= e($book['category_title']) ?></div>
                </div>

                <div class="detail-item">
                    <label>Year</label>
                    <div><?= formatYear($book['year']) ?></div>
                </div>

                <?php if ($book['isbn']): ?>
                    <div class="detail-item">
                        <label>ISBN</label>
                        <div><?= e($book['isbn']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="/books/delete.php?id=<?= $bookId ?>" style="margin-top: 2rem;">
                <p><strong>Are you sure you want to delete this book?</strong></p>

                <div class="form-actions">
                    <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                        🗑️ Yes, Delete Book
                    </button>
                    <a href="/books/view.php?id=<?= $bookId ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

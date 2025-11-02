<?php
/**
 * View Book Details
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$bookId = $_GET['id'] ?? null;

if (!$bookId) {
    setFlash('error', 'Book ID is required.');
    redirect('/books/');
}

try {
    // Get book details
    $stmt = $db->prepare('
        SELECT b.*,
               c.title as category_title,
               mc.code as maincat_code,
               mc.title as maincat_title
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

    // Get authors
    $stmt = $db->prepare('
        SELECT a.*
        FROM authors a
        JOIN book_author ba ON a.id = ba.author_id
        WHERE ba.book_id = ?
        ORDER BY a.lastname, a.surname
    ');
    $stmt->execute([$bookId]);
    $authors = $stmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$bookTag = generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']);

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?= e($book['title']) ?></h1>
        <div>
            <a href="/books/edit.php?id=<?= $book['id'] ?>" class="btn btn-primary">Edit</a>
            <a href="/books/" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="book-detail">
        <div class="book-detail-main">
            <div class="section">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Tag</label>
                        <div><code class="book-tag-large"><?= $bookTag ?></code></div>
                    </div>

                    <div class="detail-item">
                        <label>Author(s)</label>
                        <div>
                            <?php if (!empty($authors)): ?>
                                <?php foreach ($authors as $author): ?>
                                    <div>
                                        <a href="/authors/edit.php?id=<?= $author['id'] ?>">
                                            <?= e($author['surname']) ?> <?= e($author['lastname']) ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <label>Category</label>
                        <div>
                            <span class="badge"><?= e($book['maincat_title']) ?></span>
                            <?= e($book['category_title']) ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <label>Year</label>
                        <div><?= formatYear($book['year']) ?></div>
                    </div>

                    <div class="detail-item">
                        <label>ISBN</label>
                        <div><?= e($book['isbn'] ?: '-') ?></div>
                    </div>

                    <div class="detail-item">
                        <label>Publisher</label>
                        <div><?= e($book['publisher'] ?: '-') ?></div>
                    </div>

                    <div class="detail-item">
                        <label>Language</label>
                        <div><?= e($book['language'] ?: '-') ?></div>
                    </div>

                    <div class="detail-item">
                        <label>Added</label>
                        <div><?= formatDateTime($book['created_at']) ?></div>
                    </div>

                    <?php if ($book['updated_at'] !== $book['created_at']): ?>
                        <div class="detail-item">
                            <label>Last Updated</label>
                            <div><?= formatDateTime($book['updated_at']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($book['notes']): ?>
                        <div class="detail-item" style="grid-column: span 2;">
                            <label>Notes</label>
                            <div class="notes-box"><?= nl2br(e($book['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <div class="detail-actions">
                    <a href="/books/edit.php?id=<?= $book['id'] ?>" class="btn btn-primary">✏️ Edit Book</a>
                    <a href="/books/delete.php?id=<?= $book['id'] ?>"
                       class="btn btn-danger">🗑️ Delete Book</a>
                </div>
            </div>
        </div>

        <div class="book-detail-sidebar">
            <?php if ($book['cover_image']): ?>
                <div class="section">
                    <img src="<?= e($book['cover_image']) ?>" alt="Book Cover" class="book-cover-large">
                </div>
            <?php else: ?>
                <div class="section">
                    <div class="book-cover-large-placeholder">
                        <span>📚</span>
                        <small>No cover image</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

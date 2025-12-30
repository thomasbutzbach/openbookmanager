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
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($config['labels']['enabled']): ?>
                <div class="dropdown" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="btn btn-secondary">🏷️ Print Labels</button>
                    <div x-show="open" @click.away="open = false" x-transition style="position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.5rem; margin-top: 0.25rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10; min-width: 150px;">
                        <a href="/labels/download.php?type=dual&id=<?= $book['id'] ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;" download>Both Labels</a>
                        <a href="/labels/download.php?type=spine&id=<?= $book['id'] ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;" download>Spine Only</a>
                        <a href="/labels/download.php?type=full&id=<?= $book['id'] ?>" class="btn btn-sm" style="display: block; white-space: nowrap;" download>Full Only</a>
                    </div>
                </div>
            <?php endif; ?>
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
                        <label>Format</label>
                        <div>
                            <?php
                            $formatType = $book['format_type'] ?? 'physical';
                            $formatIcon = formatTypeIcon($formatType);
                            $formatLabel = formatTypeLabel($formatType);
                            ?>
                            <?= $formatIcon ? $formatIcon . ' ' : '' ?><?= e($formatLabel) ?>
                        </div>
                    </div>

                    <?php if (!empty($book['document_file'])): ?>
                        <div class="detail-item">
                            <label>Digital Copy</label>
                            <div>
                                <a href="/books/download.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-secondary">
                                    📥 Download <?= strtoupper(pathinfo($book['document_file'], PATHINFO_EXTENSION)) ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

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
                    <?php
                    // Add cache-busting parameter if redirected from edit with updated cover
                    $coverUrl = $book['cover_image'];
                    if (isset($_GET['updated'])) {
                        $separator = strpos($coverUrl, '?') !== false ? '&' : '?';
                        $coverUrl .= $separator . 't=' . $_GET['updated'];
                    }
                    ?>
                    <img src="<?= e($coverUrl) ?>" alt="Book Cover" class="book-cover-large">
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

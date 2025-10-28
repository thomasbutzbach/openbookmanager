<?php
/**
 * Delete Author
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$authorId = $_GET['id'] ?? null;

if (!$authorId) {
    setFlash('error', 'Author ID is required.');
    redirect('/authors/');
}

// Get author details
try {
    $stmt = $db->prepare('SELECT * FROM authors WHERE id = ?');
    $stmt->execute([$authorId]);
    $author = $stmt->fetch();

    if (!$author) {
        setFlash('error', 'Author not found.');
        redirect('/authors/');
    }

    // Check if author has books
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM book_author WHERE author_id = ?');
    $stmt->execute([$authorId]);
    $bookCount = $stmt->fetch()['count'];

    // Get books for display
    $books = [];
    if ($bookCount > 0) {
        $stmt = $db->prepare('
            SELECT b.id, b.title, b.year
            FROM books b
            JOIN book_author ba ON b.id = ba.book_id
            WHERE ba.author_id = ?
            ORDER BY b.title
            LIMIT 10
        ');
        $stmt->execute([$authorId]);
        $books = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        // Check again if author has books (prevent race condition)
        try {
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM book_author WHERE author_id = ?');
            $stmt->execute([$authorId]);
            $bookCount = $stmt->fetch()['count'];

            if ($bookCount > 0) {
                setFlash('error', 'Cannot delete author. This author is linked to ' . $bookCount . ' book(s). Please remove the books first.');
                redirect('/authors/');
            }

            // Delete author
            $stmt = $db->prepare('DELETE FROM authors WHERE id = ?');
            $stmt->execute([$authorId]);

            setFlash('success', 'Author deleted successfully!');
            redirect('/authors/');

        } catch (PDOException $e) {
            setFlash('error', 'Database error: ' . $e->getMessage());
            redirect('/authors/');
        }
    } else {
        redirect('/authors/');
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Delete Author</h1>
        <a href="/authors/" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="section">
        <?php if ($bookCount > 0): ?>
            <!-- Cannot delete -->
            <div class="alert alert-error">
                <strong>❌ Cannot Delete Author</strong>
                <p>This author is linked to <strong><?= $bookCount ?></strong> book(s) and cannot be deleted.</p>
                <p>Please remove or reassign the books first, then try again.</p>
            </div>

            <h3>Books by this Author:</h3>
            <ul class="simple-list">
                <?php foreach ($books as $book): ?>
                    <li>
                        <a href="/books/view.php?id=<?= $book['id'] ?>">
                            <?= e($book['title']) ?>
                            <?php if ($book['year']): ?>
                                (<?= $book['year'] ?>)
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if ($bookCount > 10): ?>
                    <li class="text-muted">... and <?= $bookCount - 10 ?> more</li>
                <?php endif; ?>
            </ul>

            <div style="margin-top: 2rem;">
                <a href="/authors/" class="btn btn-secondary">Back to Authors</a>
            </div>

        <?php else: ?>
            <!-- Can delete -->
            <div class="alert alert-warning">
                <strong>⚠️ Warning:</strong> You are about to delete this author. This action cannot be undone!
            </div>

            <div class="delete-confirmation">
                <h2>Author Details</h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>First Name</label>
                        <div><?= e($author['surname']) ?></div>
                    </div>

                    <div class="detail-item">
                        <label>Last Name</label>
                        <div><strong><?= e($author['lastname']) ?></strong></div>
                    </div>

                    <div class="detail-item">
                        <label>Books</label>
                        <div><span class="text-muted">None</span></div>
                    </div>

                    <div class="detail-item">
                        <label>Added</label>
                        <div><?= formatDate($author['created_at']) ?></div>
                    </div>
                </div>

                <form method="POST" action="/authors/delete.php?id=<?= $authorId ?>" style="margin-top: 2rem;">
                    <p><strong>Are you sure you want to delete this author?</strong></p>

                    <div class="form-actions">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                            🗑️ Yes, Delete Author
                        </button>
                        <a href="/authors/" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

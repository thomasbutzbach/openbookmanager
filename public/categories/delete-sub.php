<?php
/**
 * Delete Subcategory
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$code = $_GET['code'] ?? null;

if (!$code) {
    setFlash('error', 'Category code is required.');
    redirect('/categories/');
}

// Get category details
try {
    $stmt = $db->prepare('
        SELECT c.*, mc.title as maincat_title
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        WHERE c.code = ?
    ');
    $stmt->execute([$code]);
    $category = $stmt->fetch();

    if (!$category) {
        setFlash('error', 'Category not found.');
        redirect('/categories/');
    }

    // Check if has books
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE code_category = ?');
    $stmt->execute([$code]);
    $bookCount = $stmt->fetch()['count'];

    // Get books for display
    $books = [];
    if ($bookCount > 0) {
        $stmt = $db->prepare('SELECT * FROM books WHERE code_category = ? ORDER BY title LIMIT 10');
        $stmt->execute([$code]);
        $books = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        // Check again if has books
        try {
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE code_category = ?');
            $stmt->execute([$code]);
            $bookCount = $stmt->fetch()['count'];

            if ($bookCount > 0) {
                setFlash('error', 'Cannot delete subcategory. It has ' . $bookCount . ' book(s). Please remove or reassign the books first.');
                redirect('/categories/');
            }

            // Delete subcategory
            $stmt = $db->prepare('DELETE FROM categories WHERE code = ?');
            $stmt->execute([$code]);

            setFlash('success', 'Subcategory deleted successfully!');
            redirect('/categories/');

        } catch (PDOException $e) {
            setFlash('error', 'Database error: ' . $e->getMessage());
            redirect('/categories/');
        }
    } else {
        redirect('/categories/');
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Delete Subcategory</h1>
        <a href="/categories/" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="section">
        <?php if ($bookCount > 0): ?>
            <!-- Cannot delete -->
            <div class="alert alert-error">
                <strong>❌ Cannot Delete Subcategory</strong>
                <p>This subcategory has <strong><?= $bookCount ?></strong> book(s) and cannot be deleted.</p>
                <p>Please remove or reassign the books first, then try again.</p>
            </div>

            <h3>Books in this Category:</h3>
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
                <a href="/categories/" class="btn btn-secondary">Back to Categories</a>
            </div>

        <?php else: ?>
            <!-- Can delete -->
            <div class="alert alert-warning">
                <strong>⚠️ Warning:</strong> You are about to delete this subcategory. This action cannot be undone!
            </div>

            <div class="delete-confirmation">
                <h2>Subcategory Details</h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Code</label>
                        <div><code><?= e($category['code']) ?></code></div>
                    </div>

                    <div class="detail-item">
                        <label>Title</label>
                        <div><strong><?= e($category['title']) ?></strong></div>
                    </div>

                    <div class="detail-item">
                        <label>Main Category</label>
                        <div><?= e($category['maincat_title']) ?></div>
                    </div>

                    <div class="detail-item">
                        <label>Books</label>
                        <div><span class="text-muted">None</span></div>
                    </div>

                    <div class="detail-item">
                        <label>Added</label>
                        <div><?= formatDate($category['created_at']) ?></div>
                    </div>
                </div>

                <form method="POST" action="/categories/delete-sub.php?code=<?= e($code) ?>" style="margin-top: 2rem;">
                    <p><strong>Are you sure you want to delete this subcategory?</strong></p>

                    <div class="form-actions">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                            🗑️ Yes, Delete Subcategory
                        </button>
                        <a href="/categories/" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

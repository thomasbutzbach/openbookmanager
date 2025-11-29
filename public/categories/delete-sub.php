<?php
/**
 * Delete Subcategory
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$code = $_GET['code'] ?? null;
$mainCode = $_GET['main'] ?? null;

if (!$code || !$mainCode) {
    setFlash('error', 'Category code and main category are required.');
    redirect('/categories/');
}

// Get category details
try {
    $stmt = $db->prepare('
        SELECT c.*, mc.title as maincat_title
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        WHERE c.code = ? AND c.code_maincategory = ?
    ');
    $stmt->execute([$code, $mainCode]);
    $category = $stmt->fetch();

    if (!$category) {
        setFlash('error', 'Category not found.');
        redirect('/categories/');
    }

    // Check if has books
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE code_category = ? AND code_maincategory = ?');
    $stmt->execute([$code, $category['code_maincategory']]);
    $bookCount = $stmt->fetch()['count'];

    // Check if has suggested books in import manager
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM scanned_books WHERE suggested_code_category = ? AND suggested_code_maincategory = ?');
    $stmt->execute([$code, $category['code_maincategory']]);
    $scannedBookCount = $stmt->fetch()['count'];

    // Get books for display
    $books = [];
    if ($bookCount > 0) {
        $stmt = $db->prepare('SELECT * FROM books WHERE code_category = ? AND code_maincategory = ? ORDER BY title LIMIT 10');
        $stmt->execute([$code, $category['code_maincategory']]);
        $books = $stmt->fetchAll();
    }

    // Get scanned books for display
    $scannedBooks = [];
    if ($scannedBookCount > 0) {
        $stmt = $db->prepare('SELECT * FROM scanned_books WHERE suggested_code_category = ? AND suggested_code_maincategory = ? ORDER BY title LIMIT 10');
        $stmt->execute([$code, $category['code_maincategory']]);
        $scannedBooks = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        // Check again if has books or scanned books
        try {
            // Need to get category info again for code_maincategory
            $stmt = $db->prepare('SELECT code_maincategory FROM categories WHERE code = ? AND code_maincategory = ?');
            $stmt->execute([$code, $mainCode]);
            $cat = $stmt->fetch();

            // Check imported books
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE code_category = ? AND code_maincategory = ?');
            $stmt->execute([$code, $cat['code_maincategory']]);
            $bookCount = $stmt->fetch()['count'];

            if ($bookCount > 0) {
                setFlash('error', 'Cannot delete subcategory. It has ' . $bookCount . ' imported book(s). Please remove or reassign the books first.');
                redirect('/categories/');
            }

            // Check scanned books with suggested category
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM scanned_books WHERE suggested_code_category = ? AND suggested_code_maincategory = ?');
            $stmt->execute([$code, $cat['code_maincategory']]);
            $scannedBookCount = $stmt->fetch()['count'];

            if ($scannedBookCount > 0) {
                setFlash('error', 'Cannot delete subcategory. It is assigned to ' . $scannedBookCount . ' book(s) in the Import Manager. Please clear or change their categories first.');
                redirect('/categories/');
            }

            // Delete subcategory (must use composite key!)
            $stmt = $db->prepare('DELETE FROM categories WHERE code = ? AND code_maincategory = ?');
            $stmt->execute([$code, $cat['code_maincategory']]);

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
        <?php if ($bookCount > 0 || $scannedBookCount > 0): ?>
            <!-- Cannot delete -->
            <div class="alert alert-error">
                <strong>❌ Cannot Delete Subcategory</strong>
                <?php if ($bookCount > 0): ?>
                    <p>This subcategory has <strong><?= $bookCount ?></strong> imported book(s).</p>
                <?php endif; ?>
                <?php if ($scannedBookCount > 0): ?>
                    <p>This subcategory is assigned to <strong><?= $scannedBookCount ?></strong> book(s) in the Import Manager.</p>
                <?php endif; ?>
                <p>Please remove or reassign the books first, then try again.</p>
            </div>

            <?php if ($bookCount > 0): ?>
                <h3>Imported Books in this Category:</h3>
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
            <?php endif; ?>

            <?php if ($scannedBookCount > 0): ?>
                <h3>Books in Import Manager (suggested for this category):</h3>
                <ul class="simple-list">
                    <?php foreach ($scannedBooks as $book): ?>
                        <li>
                            <a href="/books/import/edit.php?id=<?= $book['id'] ?>">
                                <?= e($book['title']) ?>
                                <?php if ($book['authors_raw']): ?>
                                    - <?= e($book['authors_raw']) ?>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($scannedBookCount > 10): ?>
                        <li class="text-muted">... and <?= $scannedBookCount - 10 ?> more</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>

            <div style="margin-top: 2rem;">
                <a href="/categories/" class="btn btn-secondary">Back to Categories</a>
                <a href="/books/import/" class="btn btn-secondary">Go to Import Manager</a>
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

                <form method="POST" action="/categories/delete-sub.php?code=<?= e($code) ?>&main=<?= e($mainCode) ?>" style="margin-top: 2rem;">
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

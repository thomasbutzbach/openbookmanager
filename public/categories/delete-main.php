<?php
/**
 * Delete Main Category
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
    $stmt = $db->prepare('SELECT * FROM maincategories WHERE code = ?');
    $stmt->execute([$code]);
    $category = $stmt->fetch();

    if (!$category) {
        setFlash('error', 'Category not found.');
        redirect('/categories/');
    }

    // Check if has subcategories
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM categories WHERE code_maincategory = ?');
    $stmt->execute([$code]);
    $subcatCount = $stmt->fetch()['count'];

    // Get subcategories for display
    $subcategories = [];
    if ($subcatCount > 0) {
        $stmt = $db->prepare('SELECT * FROM categories WHERE code_maincategory = ? ORDER BY title LIMIT 10');
        $stmt->execute([$code]);
        $subcategories = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        // Check again if has subcategories
        try {
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM categories WHERE code_maincategory = ?');
            $stmt->execute([$code]);
            $subcatCount = $stmt->fetch()['count'];

            if ($subcatCount > 0) {
                setFlash('error', 'Cannot delete main category. It has ' . $subcatCount . ' subcategorie(s). Please delete them first.');
                redirect('/categories/');
            }

            // Delete main category
            $stmt = $db->prepare('DELETE FROM maincategories WHERE code = ?');
            $stmt->execute([$code]);

            setFlash('success', 'Main category deleted successfully!');
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
        <h1>Delete Main Category</h1>
        <a href="/categories/" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="section">
        <?php if ($subcatCount > 0): ?>
            <!-- Cannot delete -->
            <div class="alert alert-error">
                <strong>❌ Cannot Delete Main Category</strong>
                <p>This main category has <strong><?= $subcatCount ?></strong> subcategorie(s) and cannot be deleted.</p>
                <p>Please delete or reassign the subcategories first, then try again.</p>
            </div>

            <h3>Subcategories:</h3>
            <ul class="simple-list">
                <?php foreach ($subcategories as $sub): ?>
                    <li>
                        <code><?= e($sub['code']) ?></code> <?= e($sub['title']) ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($subcatCount > 10): ?>
                    <li class="text-muted">... and <?= $subcatCount - 10 ?> more</li>
                <?php endif; ?>
            </ul>

            <div style="margin-top: 2rem;">
                <a href="/categories/" class="btn btn-secondary">Back to Categories</a>
            </div>

        <?php else: ?>
            <!-- Can delete -->
            <div class="alert alert-warning">
                <strong>⚠️ Warning:</strong> You are about to delete this main category. This action cannot be undone!
            </div>

            <div class="delete-confirmation">
                <h2>Main Category Details</h2>
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
                        <label>Subcategories</label>
                        <div><span class="text-muted">None</span></div>
                    </div>

                    <div class="detail-item">
                        <label>Added</label>
                        <div><?= formatDate($category['created_at']) ?></div>
                    </div>
                </div>

                <form method="POST" action="/categories/delete-main.php?code=<?= e($code) ?>" style="margin-top: 2rem;">
                    <p><strong>Are you sure you want to delete this main category?</strong></p>

                    <div class="form-actions">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                            🗑️ Yes, Delete Main Category
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

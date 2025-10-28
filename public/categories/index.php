<?php
/**
 * Categories List (Main Categories + Subcategories)
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get search parameter
$search = $_GET['search'] ?? '';

try {
    // Get main categories with subcategory count
    $sql = '
        SELECT mc.*,
               COUNT(DISTINCT c.code) as subcat_count,
               COUNT(DISTINCT b.id) as book_count
        FROM maincategories mc
        LEFT JOIN categories c ON mc.code = c.code_maincategory
        LEFT JOIN books b ON c.code = b.code_category
    ';

    $params = [];

    if (!empty($search)) {
        $sql .= ' WHERE mc.code LIKE ? OR mc.title LIKE ?';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= ' GROUP BY mc.code ORDER BY mc.title';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $mainCategories = $stmt->fetchAll();

    // Get all subcategories with book count
    $sql = '
        SELECT c.*,
               mc.title as maincat_title,
               COUNT(b.id) as book_count
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        LEFT JOIN books b ON c.code = b.code_category
    ';

    $params = [];

    if (!empty($search)) {
        $sql .= ' WHERE c.code LIKE ? OR c.title LIKE ?';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= ' GROUP BY c.code ORDER BY mc.title, c.title';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $subCategories = $stmt->fetchAll();

    // Group subcategories by main category
    $subCategoriesByMain = [];
    foreach ($subCategories as $sub) {
        $subCategoriesByMain[$sub['code_maincategory']][] = $sub;
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Categories</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/categories/add-main.php" class="btn btn-primary">Add Main Category</a>

            <div class="export-buttons" x-data="{ showExport: false }">
                <button type="button" @click="showExport = !showExport" class="btn btn-secondary">
                    📥 Export
                </button>
                <div x-show="showExport" x-transition style="position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.5rem; margin-top: 0.25rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10;">
                    <a href="/exports/categories.php?format=csv" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📄 CSV</a>
                    <a href="/exports/categories.php?format=json" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📋 JSON</a>
                    <a href="/exports/categories.php?format=pdf" class="btn btn-sm" style="display: block; white-space: nowrap;" target="_blank">🖨️ PDF/Print</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="section">
        <form method="GET" action="/categories/">
            <div class="filter-bar">
                <div class="search-box">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search categories..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <button type="submit" class="btn btn-primary">Search</button>

                <?php if (!empty($search)): ?>
                    <a href="/categories/" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Categories List -->
    <?php if (empty($mainCategories)): ?>
        <div class="section">
            <p class="text-muted">
                <?php if (!empty($search)): ?>
                    No categories found matching your search.
                <?php else: ?>
                    No categories yet. <a href="/categories/add-main.php">Add your first main category</a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($mainCategories as $main): ?>
            <div class="section category-group">
                <div class="category-main-header">
                    <div>
                        <h2>
                            <code class="category-code"><?= e($main['code']) ?></code>
                            <?= e($main['title']) ?>
                        </h2>
                        <div class="category-stats">
                            <?= $main['subcat_count'] ?> subcategorie(s), <?= $main['book_count'] ?> book(s)
                        </div>
                    </div>
                    <div class="actions">
                        <a href="/categories/add-sub.php?main=<?= e($main['code']) ?>" class="btn btn-sm btn-secondary" title="Add Subcategory">➕ Add Sub</a>
                        <a href="/categories/edit-main.php?code=<?= e($main['code']) ?>" class="btn btn-sm" title="Edit">✏️</a>
                        <a href="/categories/delete-main.php?code=<?= e($main['code']) ?>"
                           class="btn btn-sm btn-danger"
                           title="Delete"
                           onclick="return confirmDelete('Delete main category \'<?= e($main['title']) ?>\'?')">🗑️</a>
                    </div>
                </div>

                <?php if (!empty($subCategoriesByMain[$main['code']])): ?>
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th width="100">Code</th>
                                <th>Subcategory</th>
                                <th width="100">Books</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subCategoriesByMain[$main['code']] as $sub): ?>
                                <tr>
                                    <td><code><?= e($sub['code']) ?></code></td>
                                    <td><strong><?= e($sub['title']) ?></strong></td>
                                    <td>
                                        <?php if ($sub['book_count'] > 0): ?>
                                            <a href="/books/?category=<?= e($sub['code']) ?>">
                                                <?= $sub['book_count'] ?> book(s)
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">0 books</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="/categories/edit-sub.php?code=<?= e($sub['code']) ?>" class="btn btn-sm" title="Edit">✏️</a>
                                        <a href="/categories/delete-sub.php?code=<?= e($sub['code']) ?>"
                                           class="btn btn-sm btn-danger"
                                           title="Delete"
                                           onclick="return confirmDelete('Delete subcategory \'<?= e($sub['title']) ?>\'?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted" style="margin-top: 1rem;">No subcategories yet.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

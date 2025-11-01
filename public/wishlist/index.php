<?php
/**
 * Wishlist
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get search parameter
$search = $_GET['search'] ?? '';
$currentPage = $_GET['page'] ?? 1;

// Get pagination settings
$itemsPerPage = $config['pagination']['wishlist'];

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

// Search filter
if (!empty($search)) {
    $whereClause .= ' AND (title LIKE ? OR author_name LIKE ? OR notes LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

try {
    // Count total items (for pagination)
    $countSql = "SELECT COUNT(*) as total FROM wishlist {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalItems = $stmt->fetch()['total'];

    // Calculate pagination
    $pagination = getPaginationData($totalItems, $currentPage, $itemsPerPage);

    // Build main query with pagination
    $sql = "
        SELECT * FROM wishlist
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $wishlistItems = $stmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Wishlist</h1>
        <a href="/wishlist/add.php" class="btn btn-primary">Add to Wishlist</a>
    </div>

    <!-- Search -->
    <div class="section">
        <form method="GET" action="/wishlist/">
            <div class="filter-bar">
                <div class="search-box">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search wishlist..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <button type="submit" class="btn btn-primary">Search</button>

                <?php if (!empty($search)): ?>
                    <a href="/wishlist/" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Wishlist Items -->
    <div class="section">
        <?php if (empty($wishlistItems)): ?>
            <p class="text-muted">
                <?php if (!empty($search)): ?>
                    No items found matching your search.
                <?php else: ?>
                    Your wishlist is empty. <a href="/wishlist/add.php">Add your first item</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <p class="text-muted" style="margin: 0;">
                    Showing <?= $pagination['offset'] + 1 ?> to <?= min($pagination['offset'] + $itemsPerPage, $totalItems) ?>
                    of <?= $totalItems ?> item(s)
                    <?php if (!empty($search)): ?>
                        (filtered)
                    <?php endif; ?>
                </p>

                <div class="export-buttons" x-data="{ showExport: false }">
                    <button type="button" @click="showExport = !showExport" class="btn btn-secondary btn-sm">
                        📥 Export
                    </button>
                    <div x-show="showExport" x-transition style="position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.5rem; margin-top: 0.25rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10;">
                        <?php
                        $exportQuery = !empty($search) ? '&search=' . urlencode($search) : '';
                        ?>
                        <a href="/exports/wishlist.php?format=csv<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📄 CSV</a>
                        <a href="/exports/wishlist.php?format=json<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📋 JSON</a>
                        <a href="/exports/wishlist.php?format=pdf<?= $exportQuery ?>" class="btn btn-sm" style="display: block; white-space: nowrap;" target="_blank">🖨️ PDF/Print</a>
                    </div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author(s)</th>
                        <th>Notes</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wishlistItems as $item): ?>
                        <tr>
                            <td><strong><?= e($item['title']) ?></strong></td>
                            <td><?= e($item['author_name'] ?: '-') ?></td>
                            <td><?= e(truncate($item['notes'] ?: '-', 100)) ?></td>
                            <td><?= formatDate($item['created_at']) ?></td>
                            <td class="actions">
                                <a href="/wishlist/edit.php?id=<?= $item['id'] ?>" class="btn btn-sm" title="Edit">✏️</a>
                                <a href="/wishlist/delete.php?id=<?= $item['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Delete">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Build pagination URL preserving search
            $paginationUrl = '/wishlist/?';
            if (!empty($search)) {
                $paginationUrl .= 'search=' . urlencode($search);
            }

            echo renderPagination($pagination, $paginationUrl);
            ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

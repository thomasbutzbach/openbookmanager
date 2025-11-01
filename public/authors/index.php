<?php
/**
 * Authors List
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get search parameter
$search = $_GET['search'] ?? '';
$currentPage = $_GET['page'] ?? 1;

// Get pagination settings
$itemsPerPage = $config['pagination']['authors'];

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

// Search filter
if (!empty($search)) {
    $whereClause .= ' AND (a.surname LIKE ? OR a.lastname LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

try {
    // Count total authors (for pagination)
    $countSql = "
        SELECT COUNT(DISTINCT a.id) as total
        FROM authors a
        {$whereClause}
    ";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalAuthors = $stmt->fetch()['total'];

    // Calculate pagination
    $pagination = getPaginationData($totalAuthors, $currentPage, $itemsPerPage);

    // Build main query with pagination
    $sql = "
        SELECT a.*,
               COUNT(ba.book_id) as book_count
        FROM authors a
        LEFT JOIN book_author ba ON a.id = ba.author_id
        {$whereClause}
        GROUP BY a.id
        ORDER BY a.lastname, a.surname
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $authors = $stmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Authors</h1>
        <a href="/authors/add.php" class="btn btn-primary">Add New Author</a>
    </div>

    <!-- Search -->
    <div class="section">
        <form method="GET" action="/authors/">
            <div class="filter-bar">
                <div class="search-box">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search authors..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <button type="submit" class="btn btn-primary">Search</button>

                <?php if (!empty($search)): ?>
                    <a href="/authors/" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Authors List -->
    <div class="section">
        <?php if (empty($authors)): ?>
            <p class="text-muted">
                <?php if (!empty($search)): ?>
                    No authors found matching your search.
                <?php else: ?>
                    No authors yet. <a href="/authors/add.php">Add your first author</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <p class="text-muted" style="margin: 0;">
                    Showing <?= $pagination['offset'] + 1 ?> to <?= min($pagination['offset'] + $itemsPerPage, $totalAuthors) ?>
                    of <?= $totalAuthors ?> author(s)
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
                        <a href="/exports/authors.php?format=csv<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📄 CSV</a>
                        <a href="/exports/authors.php?format=json<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📋 JSON</a>
                        <a href="/exports/authors.php?format=pdf<?= $exportQuery ?>" class="btn btn-sm" style="display: block; white-space: nowrap;" target="_blank">🖨️ PDF/Print</a>
                    </div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Books</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authors as $author): ?>
                        <tr>
                            <td><strong><?= e($author['lastname']) ?></strong></td>
                            <td><?= e($author['surname']) ?></td>
                            <td>
                                <?php if ($author['book_count'] > 0): ?>
                                    <a href="/books/?search=<?= urlencode($author['surname'] . ' ' . $author['lastname']) ?>">
                                        <?= $author['book_count'] ?> book(s)
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">0 books</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($author['created_at']) ?></td>
                            <td class="actions">
                                <a href="/authors/edit.php?id=<?= $author['id'] ?>" class="btn btn-sm" title="Edit">✏️</a>
                                <a href="/authors/delete.php?id=<?= $author['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Delete">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Build pagination URL preserving search
            $paginationUrl = '/authors/?';
            if (!empty($search)) {
                $paginationUrl .= 'search=' . urlencode($search);
            }

            echo renderPagination($pagination, $paginationUrl);
            ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

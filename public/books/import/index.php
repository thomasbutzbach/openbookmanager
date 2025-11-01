<?php
/**
 * Import Manager - Review and import scanned books
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

requireAuth();

// Get filter and sort parameters
$statusFilter = $_GET['status'] ?? 'pending';
$sortBy = $_GET['sort'] ?? 'scanned_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$currentPage = $_GET['page'] ?? 1;

// Get pagination settings
$itemsPerPage = $config['pagination']['books'] ?? 50;

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

if ($statusFilter && $statusFilter !== 'all') {
    $whereClause .= ' AND status = ?';
    $params[] = $statusFilter;
}

// Get scanned books
try {
    // Count total for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM scanned_books $whereClause");
    $countStmt->execute($params);
    $totalBooks = $countStmt->fetch()['total'];

    // Get pagination data
    $pagination = getPaginationData($totalBooks, $currentPage, $itemsPerPage);

    // Get books for current page
    $stmt = $db->prepare("
        SELECT *
        FROM scanned_books
        $whereClause
        ORDER BY $sortBy $sortOrder
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $scannedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics (only pending and skipped - imported books are deleted)
    $statsStmt = $db->query('
        SELECT
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = "pending" OR status = "reviewed" THEN 1 ELSE 0 END), 0) as pending,
            COALESCE(SUM(CASE WHEN status = "skipped" THEN 1 ELSE 0 END), 0) as skipped
        FROM scanned_books
    ');
    $stats = $statsStmt->fetch();

    // Ensure stats are never null (for empty table)
    $stats['pending'] = $stats['pending'] ?? 0;
    $stats['skipped'] = $stats['skipped'] ?? 0;

    // Get categories for import dialog
    $categoriesStmt = $db->query('
        SELECT c.code, c.title, m.code as maincat_code, m.title as maincat_title
        FROM categories c
        JOIN maincategories m ON c.code_maincategory = m.code
        ORDER BY m.title, c.title
    ');
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by main category
    $categoriesByMain = [];
    foreach ($categories as $cat) {
        $mainCode = $cat['maincat_code'];
        if (!isset($categoriesByMain[$mainCode])) {
            $categoriesByMain[$mainCode] = [
                'title' => $cat['maincat_title'],
                'code' => $mainCode,
                'subcategories' => []
            ];
        }
        $categoriesByMain[$mainCode]['subcategories'][] = $cat;
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1>📦 Import Manager</h1>
            <p class="subtitle">Review and import scanned books into your collection</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/books/import/add-manual.php" class="btn btn-primary">➕ Add Book Manually</a>
            <a href="/books/import/export-json.php" class="btn btn-secondary">💾 Export JSON</a>
            <a href="/books/scan/" class="btn btn-secondary">📷 Back to Scan Mode</a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="section" style="background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--warning-color);"><?= $stats['pending'] ?></div>
                <div style="font-size: 0.875rem; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 0.05em;">Waiting to Import</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--secondary-color);"><?= $stats['skipped'] ?></div>
                <div style="font-size: 0.875rem; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 0.05em;">Skipped</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="section">
        <form method="GET" action="/books/import/" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div>
                <label for="status">Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending (<?= $stats['pending'] ?>)</option>
                    <option value="skipped" <?= $statusFilter === 'skipped' ? 'selected' : '' ?>>Skipped (<?= $stats['skipped'] ?>)</option>
                </select>
            </div>

            <div>
                <label for="sort">Sort by:</label>
                <select name="sort" id="sort" onchange="this.form.submit()">
                    <option value="scanned_at" <?= $sortBy === 'scanned_at' ? 'selected' : '' ?>>Scan Date</option>
                    <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                    <option value="authors_raw" <?= $sortBy === 'authors_raw' ? 'selected' : '' ?>>Author</option>
                </select>
            </div>

            <div>
                <label for="order">Order:</label>
                <select name="order" id="order" onchange="this.form.submit()">
                    <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Books List -->
    <div class="section">
        <?php if (empty($scannedBooks)): ?>
            <p class="text-muted" style="text-align: center; padding: 3rem;">
                No scanned books found with status "<?= e($statusFilter) ?>".
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 1.5rem;">
                <?php foreach ($scannedBooks as $book): ?>
                    <div class="card" style="padding: 1.5rem;">
                        <div style="display: flex; gap: 1.5rem;">
                            <!-- Cover -->
                            <div style="flex-shrink: 0; width: 100px; height: 150px; overflow: hidden; border-radius: 0.375rem; background: #e9ecef;">
                                <img
                                    src="<?= e($book['cover_local'] ?: $book['cover_url'] ?: '/images/no-cover.svg') ?>"
                                    alt=""
                                    style="width: 100%; height: 100%; object-fit: cover;"
                                    onerror="this.src='/images/no-cover.svg'"
                                >
                            </div>

                            <!-- Book Info -->
                            <div style="flex: 1; min-width: 0;">
                                <h3 style="margin: 0 0 0.5rem 0;"><?= e($book['title']) ?></h3>
                                <?php if ($book['subtitle']): ?>
                                    <p style="color: var(--secondary-color); margin: 0 0 0.5rem 0; font-style: italic;">
                                        <?= e($book['subtitle']) ?>
                                    </p>
                                <?php endif; ?>

                                <p style="margin: 0 0 0.5rem 0;">
                                    <strong>Author:</strong> <?= e($book['authors_raw'] ?: 'Unknown') ?>
                                </p>

                                <p style="margin: 0; font-size: 0.875rem; color: var(--secondary-color);">
                                    <?php if ($book['published_year']): ?>
                                        <span><?= e($book['published_year']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($book['published_year'] && $book['pages']): ?>
                                        <span> • </span>
                                    <?php endif; ?>
                                    <?php if ($book['pages']): ?>
                                        <span><?= e($book['pages']) ?> pages</span>
                                    <?php endif; ?>
                                    <?php if (($book['published_year'] || $book['pages']) && $book['publisher']): ?>
                                        <span> • </span>
                                    <?php endif; ?>
                                    <?php if ($book['publisher']): ?>
                                        <span><?= e($book['publisher']) ?></span>
                                    <?php endif; ?>
                                </p>

                                <p style="margin: 0.75rem 0 0 0; font-size: 0.75rem; color: var(--secondary-color);">
                                    <strong>ISBN:</strong> <?= e($book['isbn']) ?> •
                                    <strong>Scanned:</strong> <?= date('Y-m-d H:i', strtotime($book['scanned_at'])) ?>
                                </p>
                            </div>

                            <!-- Actions -->
                            <div style="flex-shrink: 0; display: flex; flex-direction: column; gap: 0.5rem; justify-content: center;">
                                <?php if ($book['status'] === 'pending' || $book['status'] === 'reviewed'): ?>
                                    <a href="/books/import/edit.php?id=<?= $book['id'] ?>" class="btn btn-primary">
                                        📥 Import
                                    </a>
                                    <button
                                        onclick="skipBook(<?= $book['id'] ?>)"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        Skip
                                    </button>
                                <?php elseif ($book['status'] === 'imported'): ?>
                                    <span style="color: var(--success-color); font-weight: 500;">✓ Imported</span>
                                    <?php if ($book['imported_book_id']): ?>
                                        <a href="/books/view.php?id=<?= $book['imported_book_id'] ?>" class="btn btn-sm">View Book</a>
                                    <?php endif; ?>
                                <?php elseif ($book['status'] === 'skipped'): ?>
                                    <span style="color: var(--secondary-color);">⊘ Skipped</span>
                                    <button
                                        onclick="unskipBook(<?= $book['id'] ?>)"
                                        class="btn btn-secondary btn-sm"
                                    >
                                        Undo
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Info -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; margin-top: 1rem;">
                <p class="text-muted" style="margin: 0;">
                    Showing <?= $pagination['offset'] + 1 ?> to <?= min($pagination['offset'] + $itemsPerPage, $totalBooks) ?>
                    of <?= $totalBooks ?> scanned book(s)
                </p>
            </div>

            <?php
            // Build pagination URL preserving all filters
            $paginationUrl = '/books/import/?';
            $urlParams = [];
            if ($statusFilter !== 'pending') $urlParams[] = 'status=' . urlencode($statusFilter);
            if ($sortBy !== 'scanned_at') $urlParams[] = 'sort=' . urlencode($sortBy);
            if ($sortOrder !== 'DESC') $urlParams[] = 'order=' . urlencode($sortOrder);
            $paginationUrl .= implode('&', $urlParams);

            echo renderPagination($pagination, $paginationUrl);
            ?>
        <?php endif; ?>
    </div>
</div>

<script>
function skipBook(id) {
    fetch('/books/import/skip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to skip book'));
        }
    })
    .catch(err => alert('Network error'));
}

function unskipBook(id) {
    fetch('/books/import/unskip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to unskip book'));
        }
    })
    .catch(err => alert('Network error'));
}
</script>

<?php include __DIR__ . '/../../../src/Views/layout/footer.php'; ?>

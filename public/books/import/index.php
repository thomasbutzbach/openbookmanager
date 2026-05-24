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
$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['cat'] ?? '';  // Format: "MAINCODE_SUBCODE" e.g. "SF_PH"

// Get pagination settings
$itemsPerPage = $config['pagination']['books'] ?? 50;

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

if ($statusFilter && $statusFilter !== 'all') {
    if ($statusFilter === 'uncategorized') {
        // Special filter for books without category suggestion
        $whereClause .= ' AND suggested_code_category IS NULL AND (status = "pending" OR status = "reviewed")';
    } else {
        $whereClause .= ' AND status = ?';
        $params[] = $statusFilter;
    }
}

// Search filter
if ($searchQuery !== '') {
    $whereClause .= ' AND (title LIKE ? OR authors_raw LIKE ? OR isbn LIKE ?)';
    $searchPattern = '%' . $searchQuery . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

// Category filter
if ($categoryFilter !== '') {
    if (strpos($categoryFilter, '_') !== false) {
        // Subcategory filter: "MAINCODE_SUBCODE"
        [$filterMainCode, $filterSubCode] = explode('_', $categoryFilter, 2);
        $whereClause .= ' AND suggested_code_maincategory = ? AND suggested_code_category = ?';
        $params[] = $filterMainCode;
        $params[] = $filterSubCode;
    } else {
        // Main category filter: "MAINCODE"
        $whereClause .= ' AND suggested_code_maincategory = ?';
        $params[] = $categoryFilter;
    }
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
            COALESCE(SUM(CASE WHEN status = "skipped" THEN 1 ELSE 0 END), 0) as skipped,
            COALESCE(SUM(CASE WHEN suggested_code_category IS NOT NULL THEN 1 ELSE 0 END), 0) as categorized,
            COALESCE(SUM(CASE WHEN suggested_code_category IS NULL AND (status = "pending" OR status = "reviewed") THEN 1 ELSE 0 END), 0) as uncategorized
        FROM scanned_books
    ');
    $stats = $statsStmt->fetch();

    // Ensure stats are never null (for empty table)
    $stats['pending'] = $stats['pending'] ?? 0;
    $stats['skipped'] = $stats['skipped'] ?? 0;
    $stats['categorized'] = $stats['categorized'] ?? 0;
    $stats['uncategorized'] = $stats['uncategorized'] ?? 0;

    // Get category distribution for planning - hierarchical structure
    $categoryDistStmt = $db->query('
        SELECT
            mc.code as maincat_code,
            mc.title as maincat_title,
            c.code as cat_code,
            c.title as cat_title,
            COUNT(*) as book_count
        FROM scanned_books sb
        JOIN categories c ON sb.suggested_code_category = c.code
            AND sb.suggested_code_maincategory = c.code_maincategory
        JOIN maincategories mc ON c.code_maincategory = mc.code
        WHERE sb.suggested_code_category IS NOT NULL
        GROUP BY mc.code, c.code, c.code_maincategory
        ORDER BY book_count DESC
    ');
    $rawDistribution = $categoryDistStmt->fetchAll();

    // Group by main category and calculate totals
    $categoryDistribution = [];
    foreach ($rawDistribution as $row) {
        $mainCode = $row['maincat_code'];
        if (!isset($categoryDistribution[$mainCode])) {
            $categoryDistribution[$mainCode] = [
                'code' => $mainCode,
                'title' => $row['maincat_title'],
                'total' => 0,
                'subcategories' => []
            ];
        }
        $categoryDistribution[$mainCode]['total'] += $row['book_count'];
        $categoryDistribution[$mainCode]['subcategories'][] = [
            'code' => $row['cat_code'],
            'title' => $row['cat_title'],
            'count' => $row['book_count']
        ];
    }

    // Sort main categories by total count descending
    usort($categoryDistribution, function($a, $b) {
        return $b['total'] - $a['total'];
    });

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
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--info-color);"><?= $stats['categorized'] ?></div>
                <div style="font-size: 0.875rem; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 0.05em;">Categorized</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--secondary-color);"><?= $stats['skipped'] ?></div>
                <div style="font-size: 0.875rem; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 0.05em;">Skipped</div>
            </div>
        </div>
    </div>

    <!-- Active Category Filter Banner -->
    <?php if ($categoryFilter): ?>
        <?php
        // Parse the filter to display the category name
        $filterDisplay = $categoryFilter;
        if (strpos($categoryFilter, '_') !== false) {
            [$fMain, $fSub] = explode('_', $categoryFilter, 2);
            $filterDisplay = "$fMain $fSub";
        }
        ?>
        <div class="section" style="margin-bottom: 1rem;">
            <div style="background: var(--primary-color); color: white; padding: 0.75rem 1rem; border-radius: var(--border-radius); display: flex; justify-content: space-between; align-items: center;">
                <span>
                    <strong>📂 Filtering by category:</strong> <?= e($filterDisplay) ?>
                </span>
                <a href="/books/import/?status=<?= e($statusFilter) ?>&sort=<?= e($sortBy) ?>&order=<?= e($sortOrder) ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
                   style="color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 0.25rem;">
                    ✕ Show all
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Category Distribution (Planning View) -->
    <?php if (!empty($categoryDistribution)): ?>
        <div class="section" style="margin-bottom: 2rem;">
            <details id="categoryDistribution" style="background: var(--bg-secondary); border-radius: var(--border-radius); padding: 1rem;"<?= $categoryFilter ? '' : '' ?>>
                <summary style="cursor: pointer; font-weight: 600; color: var(--primary-color); font-size: 1.125rem;">
                    📊 Planned Distribution by Category (<?= $stats['categorized'] ?> books categorized) — click a category to filter
                </summary>
                <div style="margin-top: 1.5rem;">
                    <?php foreach ($categoryDistribution as $mainCat): ?>
                        <div style="background: white; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                            <!-- Main Category Header -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <a href="/books/import/?status=all&sort=<?= e($sortBy) ?>&order=<?= e($sortOrder) ?>&cat=<?= e($mainCat['code']) ?>"
                                   style="text-decoration: none; flex: 1;">
                                    <span style="font-weight: 700; font-size: 1rem; color: var(--primary-color);">
                                        <?= e($mainCat['code']) ?>
                                    </span>
                                    <span style="margin-left: 0.5rem; color: var(--text-color);">
                                        <?= e($mainCat['title']) ?>
                                    </span>
                                </a>
                                <a href="/books/import/?status=all&sort=<?= e($sortBy) ?>&order=<?= e($sortOrder) ?>&cat=<?= e($mainCat['code']) ?>"
                                   style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color); text-decoration: none;">
                                    <?= $mainCat['total'] ?> <?= $mainCat['total'] === 1 ? 'book' : 'books' ?>
                                </a>
                            </div>

                            <!-- Subcategories -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.5rem; margin-left: 1rem;">
                                <?php foreach ($mainCat['subcategories'] as $subcat): ?>
                                    <a href="/books/import/?status=all&sort=<?= e($sortBy) ?>&order=<?= e($sortOrder) ?>&cat=<?= e($mainCat['code']) ?>_<?= e($subcat['code']) ?>"
                                       style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--bg-secondary); border-radius: 0.25rem; text-decoration: none; transition: background 0.15s;"
                                       onmouseover="this.style.background='var(--border-color)'" onmouseout="this.style.background='var(--bg-secondary)'">
                                        <div style="font-size: 0.875rem;">
                                            <span style="font-weight: 600; color: var(--secondary-color);">
                                                <?= e($subcat['code']) ?>
                                            </span>
                                            <span style="margin-left: 0.25rem; color: var(--text-color);">
                                                <?= e($subcat['title']) ?>
                                            </span>
                                        </div>
                                        <div style="font-weight: 600; color: var(--primary-color); font-size: 0.875rem;">
                                            <?= $subcat['count'] ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="section">
        <!-- Search Bar -->
        <div style="margin-bottom: 1rem;">
            <form method="GET" action="/books/import/" style="position: relative;">
                <!-- Preserve other filters -->
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
                <input type="hidden" name="order" value="<?= e($sortOrder) ?>">
                <?php if ($categoryFilter): ?>
                    <input type="hidden" name="cat" value="<?= e($categoryFilter) ?>">
                <?php endif; ?>

                <input
                    type="text"
                    name="q"
                    value="<?= e($searchQuery) ?>"
                    placeholder="Search by title, author, or ISBN..."
                    style="width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; font-size: 1rem; border: 2px solid var(--border-color); border-radius: var(--border-radius);"
                    autofocus
                >
                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--secondary-color);">🔍</span>
                <?php if ($searchQuery): ?>
                    <a
                        href="/books/import/?status=<?= e($statusFilter) ?>&sort=<?= e($sortBy) ?>&order=<?= e($sortOrder) ?><?= $categoryFilter ? '&cat=' . urlencode($categoryFilter) : '' ?>"
                        style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--secondary-color); font-size: 1.25rem; text-decoration: none;"
                        title="Clear search"
                    >✕</a>
                <?php endif; ?>
            </form>
            <?php if ($searchQuery): ?>
                <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--secondary-color);">
                    Found <?= $totalBooks ?> book(s) matching "<?= e($searchQuery) ?>"
                </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <form method="GET" action="/books/import/" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <!-- Preserve search query and category filter -->
            <?php if ($searchQuery): ?>
                <input type="hidden" name="q" value="<?= e($searchQuery) ?>">
            <?php endif; ?>
            <?php if ($categoryFilter): ?>
                <input type="hidden" name="cat" value="<?= e($categoryFilter) ?>">
            <?php endif; ?>

            <div>
                <label for="status">Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending (<?= $stats['pending'] ?>)</option>
                    <option value="uncategorized" <?= $statusFilter === 'uncategorized' ? 'selected' : '' ?>>Not Categorized (<?= $stats['uncategorized'] ?>)</option>
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

                                <!-- Quick Category Assignment -->
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);" x-data="categorySelector<?= $book['id'] ?>">
                                    <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--secondary-color);">
                                        📂 Quick Categorization (Planning Phase)
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem;">
                                        <select
                                            x-model="mainCategory"
                                            @change="updateSubcategories()"
                                            style="font-size: 0.875rem; padding: 0.375rem;"
                                        >
                                            <option value="">-- Main Category --</option>
                                            <?php foreach ($categoriesByMain as $mainCode => $mainCat): ?>
                                                <option
                                                    value="<?= e($mainCode) ?>"
                                                    <?= $book['suggested_code_maincategory'] === $mainCode ? 'selected' : '' ?>
                                                >
                                                    <?= e($mainCat['code']) ?> - <?= e($mainCat['title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select
                                            x-model="category"
                                            :disabled="!mainCategory"
                                            @change="saveCategory()"
                                            style="font-size: 0.875rem; padding: 0.375rem;"
                                        >
                                            <option value="">-- Category --</option>
                                            <template x-for="cat in subcategories" :key="cat.code">
                                                <option
                                                    :value="cat.code"
                                                    x-text="cat.code + ' - ' + cat.title"
                                                    :selected="category === cat.code"
                                                ></option>
                                            </template>
                                        </select>

                                        <button
                                            type="button"
                                            @click="clearCategory()"
                                            x-show="mainCategory || category"
                                            class="btn btn-sm btn-secondary"
                                            style="padding: 0.375rem 0.75rem; font-size: 0.875rem;"
                                            title="Clear categorization"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                    <div x-show="saved" x-transition style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--success-color);">
                                        ✓ Category saved
                                    </div>
                                    <div x-show="cleared" x-transition style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--info-color);">
                                        ✓ Categorization cleared
                                    </div>
                                </div>
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
            // Build pagination URL preserving all filters including search and category
            $paginationUrl = '/books/import/?';
            $urlParams = [];
            if ($searchQuery) $urlParams[] = 'q=' . urlencode($searchQuery);
            if ($categoryFilter) $urlParams[] = 'cat=' . urlencode($categoryFilter);
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
// Categories data from PHP
const categoriesByMain = <?= json_encode($categoriesByMain) ?>;

// Create Alpine.js component for each book's category selector
<?php foreach ($scannedBooks as $book): ?>
document.addEventListener('alpine:init', () => {
    Alpine.data('categorySelector<?= $book['id'] ?>', () => ({
        bookId: <?= $book['id'] ?>,
        mainCategory: '<?= $book['suggested_code_maincategory'] ?? '' ?>',
        category: '<?= $book['suggested_code_category'] ?? '' ?>',
        subcategories: [],
        saved: false,
        cleared: false,

        init() {
            if (this.mainCategory) {
                this.updateSubcategories();
            }
        },

        updateSubcategories() {
            if (!this.mainCategory) {
                this.subcategories = [];
                this.category = '';
                return;
            }

            const mainCat = categoriesByMain[this.mainCategory];
            if (mainCat && mainCat.subcategories) {
                this.subcategories = mainCat.subcategories;

                // If current category is not in new subcategories, clear it
                if (this.category && !this.subcategories.find(c => c.code === this.category)) {
                    this.category = '';
                }
            }
        },

        async saveCategory() {
            if (!this.mainCategory || !this.category) {
                return;
            }

            try {
                const response = await fetch('/books/import/update-suggested-category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.bookId,
                        main_category: this.mainCategory,
                        category: this.category
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 800);

                    // Reload page after a short delay to update statistics
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    alert('Error: ' + (data.error || 'Failed to save category'));
                }
            } catch (err) {
                console.error('Failed to save category:', err);
                alert('Network error');
            }
        },

        async clearCategory() {
            try {
                const response = await fetch('/books/import/update-suggested-category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.bookId,
                        main_category: '',
                        category: ''
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.mainCategory = '';
                    this.category = '';
                    this.subcategories = [];
                    this.cleared = true;
                    setTimeout(() => { this.cleared = false; }, 800);

                    // Reload page after a short delay to update statistics
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    alert('Error: ' + (data.error || 'Failed to clear category'));
                }
            } catch (err) {
                console.error('Failed to clear category:', err);
                alert('Network error');
            }
        }
    }));
});
<?php endforeach; ?>

// Persist filter state across navigation
(function () {
    var KEY = 'importManagerFilter';
    var params = new URLSearchParams(window.location.search);
    params.delete('page');

    var hasFilter = params.toString() !== '';

    if (hasFilter) {
        localStorage.setItem(KEY, params.toString());
    } else {
        var saved = localStorage.getItem(KEY);
        if (saved) {
            window.location.replace('/books/import/?' + saved);
        }
    }
})();

// Remember category distribution open/closed state
document.addEventListener('DOMContentLoaded', function() {
    const details = document.getElementById('categoryDistribution');
    if (details) {
        // Restore state from localStorage (default: closed)
        const isOpen = localStorage.getItem('categoryDistributionOpen');
        if (isOpen === 'true') {
            details.setAttribute('open', '');
        } else {
            details.removeAttribute('open');
        }

        // Save state when toggled
        details.addEventListener('toggle', function() {
            localStorage.setItem('categoryDistributionOpen', details.open);
        });
    }
});

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

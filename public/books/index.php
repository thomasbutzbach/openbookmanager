<?php
/**
 * Books List
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$currentPage = $_GET['page'] ?? 1;

// Get pagination settings
$itemsPerPage = $config['pagination']['books'];

// Build WHERE clause for counting
$whereClause = 'WHERE 1=1';
$params = [];

// Search filter
if (!empty($search)) {
    $whereClause .= ' AND (b.title LIKE ? OR b.isbn LIKE ? OR b.publisher LIKE ? OR b.notes LIKE ? OR CONCAT(a.surname, " ", a.lastname) LIKE ? OR CONCAT(a.lastname, " ", a.surname) LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Category filter
if (!empty($categoryFilter)) {
    $whereClause .= ' AND b.code_category = ?';
    $params[] = $categoryFilter;
}

// Language filter
if (!empty($languageFilter)) {
    $whereClause .= ' AND b.language = ?';
    $params[] = $languageFilter;
}

try {
    // Count total books (for pagination)
    $countSql = "
        SELECT COUNT(DISTINCT b.id) as total
        FROM books b
        LEFT JOIN book_author ba ON b.id = ba.book_id
        LEFT JOIN authors a ON ba.author_id = a.id
        {$whereClause}
    ";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $totalBooks = $stmt->fetch()['total'];

    // Calculate pagination
    $pagination = getPaginationData($totalBooks, $currentPage, $itemsPerPage);

    // Build main query with pagination
    $sql = "
        SELECT b.*,
               GROUP_CONCAT(CONCAT(a.surname, ' ', a.lastname) SEPARATOR ', ') as authors,
               c.title as category_title,
               mc.code as maincat_code,
               mc.title as maincat_title
        FROM books b
        LEFT JOIN book_author ba ON b.id = ba.book_id
        LEFT JOIN authors a ON ba.author_id = a.id
        LEFT JOIN categories c ON b.code_category = c.code
        LEFT JOIN maincategories mc ON c.code_maincategory = mc.code
        {$whereClause}
        GROUP BY b.id
    ";

    // Sorting
    $allowedSorts = ['title', 'year', 'created_at', 'code_category'];
    $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY b.{$sortBy} {$sortOrder}";

    // Add pagination limit
    $sql .= " LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    // Get authors for each book (for linking)
    $bookIds = array_column($books, 'id');
    $authorsData = [];
    if (!empty($bookIds)) {
        $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
        $stmt = $db->prepare("
            SELECT ba.book_id, a.id, a.surname, a.lastname
            FROM book_author ba
            JOIN authors a ON ba.author_id = a.id
            WHERE ba.book_id IN ($placeholders)
            ORDER BY a.lastname, a.surname
        ");
        $stmt->execute($bookIds);
        foreach ($stmt->fetchAll() as $row) {
            $authorsData[$row['book_id']][] = $row;
        }
    }

    // Get all categories for filter
    $categoriesStmt = $db->query('
        SELECT c.*, mc.title as maincat_title
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        ORDER BY mc.title, c.title
    ');
    $categories = $categoriesStmt->fetchAll();

    // Get unique languages
    $languagesStmt = $db->query('SELECT DISTINCT language FROM books WHERE language IS NOT NULL ORDER BY language');
    $languages = $languagesStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Books</h1>
        <div>
            <a href="/books/add.php" class="btn btn-primary">Add New Book</a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="section" x-data="{ showFilters: <?= (!empty($categoryFilter) || !empty($languageFilter) || $sortBy !== 'created_at' || $sortOrder !== 'DESC') ? 'true' : 'false' ?> }">
        <form method="GET" action="/books/" id="filterForm">
            <div class="filter-bar">
                <div class="search-box">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search books, ISBN, publisher..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <button type="button" @click="showFilters = !showFilters" class="btn btn-secondary">
                    Filters
                </button>

                <button type="submit" class="btn btn-primary">Search</button>

                <?php if (!empty($search) || !empty($categoryFilter) || !empty($languageFilter)): ?>
                    <a href="/books/" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>

            <!-- Advanced Filters -->
            <div x-show="showFilters" x-transition class="filter-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select name="category" id="category" @change="document.getElementById('filterForm').submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['code']) ?>" <?= $categoryFilter === $cat['code'] ? 'selected' : '' ?>>
                                    <?= e($cat['maincat_title']) ?> > <?= e($cat['title']) ?> (<?= e($cat['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="language">Language</label>
                        <select name="language" id="language" @change="document.getElementById('filterForm').submit()">
                            <option value="">All Languages</option>
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?= e($lang) ?>" <?= $languageFilter === $lang ? 'selected' : '' ?>>
                                    <?= e($lang) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sort">Sort By</label>
                        <select name="sort" id="sort" @change="document.getElementById('filterForm').submit()">
                            <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Date Added</option>
                            <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="year" <?= $sortBy === 'year' ? 'selected' : '' ?>>Year</option>
                            <option value="code_category" <?= $sortBy === 'code_category' ? 'selected' : '' ?>>Category</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="order">Order</label>
                        <select name="order" id="order" @change="document.getElementById('filterForm').submit()">
                            <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                            <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Books List -->
    <div class="section">
        <?php if (empty($books)): ?>
            <p class="text-muted">
                <?php if (!empty($search) || !empty($categoryFilter) || !empty($languageFilter)): ?>
                    No books found matching your criteria.
                <?php else: ?>
                    No books yet. <a href="/books/add.php">Add your first book</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <p class="text-muted" style="margin: 0;">
                    Showing <?= $pagination['offset'] + 1 ?> to <?= min($pagination['offset'] + $itemsPerPage, $totalBooks) ?>
                    of <?= $totalBooks ?> book(s)
                    <?php if (!empty($search) || !empty($categoryFilter) || !empty($languageFilter)): ?>
                        (filtered)
                    <?php endif; ?>
                </p>

                <div class="export-buttons" x-data="{ showExport: false }">
                    <button type="button" @click="showExport = !showExport" class="btn btn-secondary btn-sm">
                        📥 Export
                    </button>
                    <div x-show="showExport" x-transition style="position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.5rem; margin-top: 0.25rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10;">
                        <?php
                        // Build export URL with current filters
                        $exportParams = [];
                        if (!empty($search)) $exportParams[] = 'search=' . urlencode($search);
                        if (!empty($categoryFilter)) $exportParams[] = 'category=' . urlencode($categoryFilter);
                        if (!empty($languageFilter)) $exportParams[] = 'language=' . urlencode($languageFilter);
                        if ($sortBy !== 'created_at') $exportParams[] = 'sort=' . urlencode($sortBy);
                        if ($sortOrder !== 'DESC') $exportParams[] = 'order=' . urlencode($sortOrder);
                        $exportQuery = !empty($exportParams) ? '&' . implode('&', $exportParams) : '';
                        ?>
                        <a href="/exports/books.php?format=csv<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📄 CSV</a>
                        <a href="/exports/books.php?format=json<?= $exportQuery ?>" class="btn btn-sm" style="display: block; margin-bottom: 0.25rem; white-space: nowrap;">📋 JSON</a>
                        <a href="/exports/books.php?format=pdf<?= $exportQuery ?>" class="btn btn-sm" style="display: block; white-space: nowrap;" target="_blank">🖨️ PDF/Print</a>
                    </div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Author(s)</th>
                        <th>Year</th>
                        <th>Category</th>
                        <th>Language</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td>
                                <code><?= generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']) ?></code>
                            </td>
                            <td>
                                <?php if ($book['cover_image']): ?>
                                    <img src="<?= e($book['cover_image']) ?>" alt="Cover" class="book-cover-thumb">
                                <?php else: ?>
                                    <div class="book-cover-placeholder">📚</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($book['title']) ?></strong>
                                <?php if ($book['isbn']): ?>
                                    <br><small>ISBN: <?= e($book['isbn']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($authorsData[$book['id']])): ?>
                                    <?php foreach ($authorsData[$book['id']] as $author): ?>
                                        <a href="/authors/edit.php?id=<?= $author['id'] ?>">
                                            <?= e($author['surname']) ?> <?= e($author['lastname']) ?>
                                        </a><?php if ($author !== end($authorsData[$book['id']])): ?>, <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatYear($book['year']) ?></td>
                            <td>
                                <span class="badge"><?= e($book['maincat_title']) ?></span>
                                <br><?= e($book['category_title']) ?>
                            </td>
                            <td><?= e($book['language'] ?: '-') ?></td>
                            <td class="actions">
                                <a href="/books/view.php?id=<?= $book['id'] ?>" class="btn btn-sm" title="View">👁️</a>
                                <a href="/books/edit.php?id=<?= $book['id'] ?>" class="btn btn-sm" title="Edit">✏️</a>
                                <a href="/books/delete.php?id=<?= $book['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Delete"
                                   onclick="return confirmDelete('Delete book \'<?= e($book['title']) ?>\'?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Build pagination URL preserving all filters
            $paginationUrl = '/books/?';
            $urlParams = [];
            if (!empty($search)) $urlParams[] = 'search=' . urlencode($search);
            if (!empty($categoryFilter)) $urlParams[] = 'category=' . urlencode($categoryFilter);
            if (!empty($languageFilter)) $urlParams[] = 'language=' . urlencode($languageFilter);
            if ($sortBy !== 'created_at') $urlParams[] = 'sort=' . urlencode($sortBy);
            if ($sortOrder !== 'DESC') $urlParams[] = 'order=' . urlencode($sortOrder);
            $paginationUrl .= implode('&', $urlParams);

            echo renderPagination($pagination, $paginationUrl);
            ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

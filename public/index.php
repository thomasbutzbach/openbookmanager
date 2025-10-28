<?php
/**
 * OpenBookManager Entry Point
 */

$app = require __DIR__ . '/../src/bootstrap.php';
extract($app);

// Require authentication for main application
requireAuth();

// Get statistics for dashboard
try {
    // Count books
    $stmt = $db->query('SELECT COUNT(*) as count FROM books');
    $bookCount = $stmt->fetch()['count'];

    // Count authors
    $stmt = $db->query('SELECT COUNT(*) as count FROM authors');
    $authorCount = $stmt->fetch()['count'];

    // Count categories
    $stmt = $db->query('SELECT COUNT(*) as count FROM categories');
    $categoryCount = $stmt->fetch()['count'];

    // Count wishlist items
    $stmt = $db->query('SELECT COUNT(*) as count FROM wishlist');
    $wishlistCount = $stmt->fetch()['count'];

    // Recent books
    $stmt = $db->query('
        SELECT b.*,
               GROUP_CONCAT(CONCAT(a.surname, " ", a.lastname) SEPARATOR ", ") as authors,
               c.title as category_title,
               mc.code as maincat_code
        FROM books b
        LEFT JOIN book_author ba ON b.id = ba.book_id
        LEFT JOIN authors a ON ba.author_id = a.id
        LEFT JOIN categories c ON b.code_category = c.code
        LEFT JOIN maincategories mc ON c.code_maincategory = mc.code
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 5
    ');
    $recentBooks = $stmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Include header
include __DIR__ . '/../src/Views/layout/header.php';
?>

<div class="container">
    <h1>Dashboard</h1>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-content">
                <div class="stat-number"><?= $bookCount ?></div>
                <div class="stat-label">Books</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-content">
                <div class="stat-number"><?= $authorCount ?></div>
                <div class="stat-label">Authors</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🗂️</div>
            <div class="stat-content">
                <div class="stat-number"><?= $categoryCount ?></div>
                <div class="stat-label">Categories</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💭</div>
            <div class="stat-content">
                <div class="stat-number"><?= $wishlistCount ?></div>
                <div class="stat-label">Wishlist</div>
            </div>
        </div>
    </div>

    <!-- Recent Books -->
    <div class="section">
        <h2>Recent Books</h2>

        <?php if (empty($recentBooks)): ?>
            <p class="text-muted">No books yet. <a href="/books/add.php">Add your first book</a></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Title</th>
                        <th>Author(s)</th>
                        <th>Year</th>
                        <th>Category</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBooks as $book): ?>
                        <tr>
                            <td><code><?= generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']) ?></code></td>
                            <td><?= e($book['title']) ?></td>
                            <td><?= e($book['authors'] ?: '-') ?></td>
                            <td><?= formatYear($book['year']) ?></td>
                            <td><?= e($book['category_title']) ?></td>
                            <td><?= formatDate($book['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 1rem;">
                <a href="/books/" class="btn btn-secondary">View All Books</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../src/Views/layout/footer.php'; ?>

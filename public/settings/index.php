<?php
/**
 * Settings & Backup Page
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get database statistics
try {
    $stats = [];

    // Count books
    $stmt = $db->query('SELECT COUNT(*) as count FROM books');
    $stats['books'] = $stmt->fetch()['count'];

    // Count authors
    $stmt = $db->query('SELECT COUNT(*) as count FROM authors');
    $stats['authors'] = $stmt->fetch()['count'];

    // Count categories
    $stmt = $db->query('SELECT COUNT(*) as count FROM categories');
    $stats['categories'] = $stmt->fetch()['count'];

    // Count main categories
    $stmt = $db->query('SELECT COUNT(*) as count FROM maincategories');
    $stats['maincategories'] = $stmt->fetch()['count'];

    // Count wishlist items
    $stmt = $db->query('SELECT COUNT(*) as count FROM wishlist');
    $stats['wishlist'] = $stmt->fetch()['count'];

    // Get version information
    $appVersion = getAppVersion();
    $dbVersion = getDbVersion($db);
    $updateAvailable = checkUpdateAvailable($db);

    // Get current date
    $currentDate = date('Y-m-d H:i:s');

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>⚙️ Settings</h1>
    </div>

    <!-- Update Available Banner -->
    <?php if ($updateAvailable): ?>
        <div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>🎉 Update Available!</strong>
                <p style="margin: 0.5rem 0 0 0;">
                    A new version is available: <strong><?= e($updateAvailable['available']) ?></strong>
                    (current: <?= e($updateAvailable['current']) ?>)
                </p>
            </div>
            <a href="/update/" class="btn btn-primary">Update Now</a>
        </div>
    <?php endif; ?>

    <!-- Application Info -->
    <div class="section">
        <h2>Application Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Application Version</label>
                <div><?= e($appVersion) ?></div>
            </div>
            <div class="info-item">
                <label>Database Version</label>
                <div><?= $dbVersion ? e($dbVersion) : '<span class="text-muted">Not initialized</span>' ?></div>
            </div>
            <div class="info-item">
                <label>Database</label>
                <div><?= $config['database']['database'] ?></div>
            </div>
            <div class="info-item">
                <label>Environment</label>
                <div><?= $config['app']['debug'] ? 'Development' : 'Production' ?></div>
            </div>
        </div>
    </div>

    <!-- Future Settings Sections -->
    <div class="section">
        <h2>Settings</h2>
        <p class="text-muted">Additional settings will be available here in future updates.</p>

        <div class="settings-placeholder">
            <div>🔒 Password Change</div>
            <div>🌐 Language Settings</div>
            <div>🎨 Theme Settings</div>
            <div>📊 Display Preferences</div>
        </div>
    </div>

    <!-- Backup & Export Section -->
    <div class="section">
        <h2>Backup & Export</h2>
        <p class="text-muted">Create complete backups of your book collection for migration or archival purposes.</p>

        <div class="settings-grid">
            <!-- Full Backup (SQL) -->
            <div class="settings-card">
                <div class="settings-card-icon">💾</div>
                <h3>Full Backup (SQL)</h3>
                <p>Complete database dump as SQL file. Perfect for restoring your entire collection or migrating to another server.</p>

                <div class="settings-stats">
                    <strong>What will be exported:</strong>
                    <ul>
                        <li><?= $stats['books'] ?> Books</li>
                        <li><?= $stats['authors'] ?> Authors</li>
                        <li><?= $stats['maincategories'] ?> Main Categories</li>
                        <li><?= $stats['categories'] ?> Subcategories</li>
                        <li><?= $stats['wishlist'] ?> Wishlist Items</li>
                        <li>All relationships and sequences</li>
                    </ul>
                </div>

                <a href="/exports/backup-sql.php" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    📥 Download SQL Backup
                </a>
            </div>

            <!-- Full Export (JSON) -->
            <div class="settings-card">
                <div class="settings-card-icon">📋</div>
                <h3>Full Export (JSON)</h3>
                <p>Human-readable JSON export with all data and relationships. Can be edited before re-import.</p>

                <div class="settings-stats">
                    <strong>What will be exported:</strong>
                    <ul>
                        <li><?= $stats['books'] ?> Books (with authors)</li>
                        <li><?= $stats['authors'] ?> Authors</li>
                        <li><?= $stats['maincategories'] ?> Main Categories</li>
                        <li><?= $stats['categories'] ?> Subcategories</li>
                        <li><?= $stats['wishlist'] ?> Wishlist Items</li>
                        <li>Hierarchical structure</li>
                    </ul>
                </div>

                <a href="/exports/backup-json.php" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    📥 Download JSON Export
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

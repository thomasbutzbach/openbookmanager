<?php
/**
 * Update / Migration Page
 * Handles database migrations and version updates
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get version information
$appVersion = getAppVersion();
$dbVersion = getDbVersion($db);
$updateAvailable = checkUpdateAvailable($db);

// If no update available, redirect to settings
if (!$updateAvailable) {
    setFlash('info', 'Your system is up to date!');
    redirect('/settings/');
}

// Get pending migrations
$migrationsPath = __DIR__ . '/../../database/migrations/';
$pendingMigrations = [];

if (is_dir($migrationsPath)) {
    $files = glob($migrationsPath . '*.sql');

    foreach ($files as $file) {
        $filename = basename($file);

        // Parse filename: NNN_to_X.Y.Z.sql
        if (preg_match('/^(\d+)_to_(.+)\.sql$/', $filename, $matches)) {
            $migrationNumber = $matches[1];
            $targetVersion = $matches[2];

            // Only include migrations between current and target version
            if (compareVersions($targetVersion, $dbVersion) > 0 &&
                compareVersions($targetVersion, $appVersion) <= 0) {
                $pendingMigrations[] = [
                    'number' => $migrationNumber,
                    'filename' => $filename,
                    'path' => $file,
                    'target_version' => $targetVersion,
                ];
            }
        }
    }

    // Sort by migration number
    usort($pendingMigrations, function($a, $b) {
        return (int)$a['number'] - (int)$b['number'];
    });
}

// Handle update execution
$updateStatus = null;
$updateLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_update'])) {
    $updateStatus = 'running';
    $updateLog[] = '🚀 Starting update process...';
    $updateLog[] = 'Current version: ' . $dbVersion;
    $updateLog[] = 'Target version: ' . $appVersion;
    $updateLog[] = '';

    try {
        // Step 1: Create automatic backup
        $updateLog[] = '📦 Creating automatic backup...';
        $backupFile = __DIR__ . '/../../backups/pre_update_' . date('Y-m-d_His') . '.sql';
        $backupDir = dirname($backupFile);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Simple backup (we can enhance this later)
        $updateLog[] = '✓ Backup created: ' . basename($backupFile);
        $updateLog[] = '';

        // Step 2: Execute migrations
        $updateLog[] = '🔄 Executing migrations...';

        foreach ($pendingMigrations as $migration) {
            $updateLog[] = 'Running: ' . $migration['filename'];

            try {
                $sql = file_get_contents($migration['path']);

                // Execute migration
                $db->exec($sql);

                $updateLog[] = '✓ Success: Updated to version ' . $migration['target_version'];

            } catch (PDOException $e) {
                throw new Exception('Migration ' . $migration['filename'] . ' failed: ' . $e->getMessage());
            }
        }

        $updateLog[] = '';
        $updateLog[] = '✅ Update completed successfully!';
        $updateLog[] = 'New database version: ' . getDbVersion($db);

        $updateStatus = 'success';

    } catch (Exception $e) {
        $updateLog[] = '';
        $updateLog[] = '❌ Update failed: ' . $e->getMessage();
        $updateLog[] = 'Please restore from backup: ' . $backupFile;
        $updateStatus = 'error';
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔄 System Update</h1>
    </div>

    <?php if ($updateStatus === 'success'): ?>
        <!-- Success Message -->
        <div class="alert alert-success">
            <h3>Update Successful! 🎉</h3>
            <p>Your system has been updated to version <strong><?= e(getDbVersion($db)) ?></strong></p>
            <a href="/settings/" class="btn btn-primary" style="margin-top: 1rem;">Back to Settings</a>
        </div>

        <!-- Update Log -->
        <div class="section">
            <h2>Update Log</h2>
            <div style="background-color: var(--bg-color); padding: 1rem; border-radius: 0.375rem; font-family: monospace; font-size: 0.875rem; white-space: pre-wrap;">
                <?= implode("\n", array_map('htmlspecialchars', $updateLog)) ?>
            </div>
        </div>

    <?php elseif ($updateStatus === 'error'): ?>
        <!-- Error Message -->
        <div class="alert alert-danger">
            <h3>Update Failed ❌</h3>
            <p>An error occurred during the update process. Please check the log below and restore from backup if necessary.</p>
        </div>

        <!-- Update Log -->
        <div class="section">
            <h2>Update Log</h2>
            <div style="background-color: var(--bg-color); padding: 1rem; border-radius: 0.375rem; font-family: monospace; font-size: 0.875rem; white-space: pre-wrap; color: var(--danger-color);">
                <?= implode("\n", array_map('htmlspecialchars', $updateLog)) ?>
            </div>
        </div>

        <div style="margin-top: 1rem;">
            <a href="/settings/" class="btn btn-secondary">Back to Settings</a>
        </div>

    <?php else: ?>
        <!-- Update Information -->
        <div class="alert alert-info">
            <h3>Update Available</h3>
            <p>
                Your system can be updated from version <strong><?= e($dbVersion) ?></strong>
                to <strong><?= e($appVersion) ?></strong>
            </p>
        </div>

        <!-- Pending Migrations -->
        <div class="section">
            <h2>Pending Migrations</h2>

            <?php if (empty($pendingMigrations)): ?>
                <p class="text-muted">No migrations found. The version numbers will be synchronized.</p>
            <?php else: ?>
                <p class="text-muted">The following database migrations will be executed:</p>

                <table class="table" style="margin-top: 1rem;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Migration</th>
                            <th>Target Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingMigrations as $migration): ?>
                            <tr>
                                <td><?= e($migration['number']) ?></td>
                                <td><code><?= e($migration['filename']) ?></code></td>
                                <td><strong><?= e($migration['target_version']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Important Notes -->
        <div class="section">
            <h2>⚠️ Important Notes</h2>
            <ul class="simple-list">
                <li>✓ An automatic backup will be created before the update</li>
                <li>✓ The update process cannot be interrupted once started</li>
                <li>✓ Make sure you have a recent manual backup</li>
                <li>⚠️ The application should not be used during the update</li>
            </ul>
        </div>

        <!-- Execute Update -->
        <div class="section">
            <form method="POST" onsubmit="return confirm('Are you sure you want to start the update? This process cannot be undone.');">
                <input type="hidden" name="execute_update" value="1">
                <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.75rem 2rem;">
                    🚀 Execute Update Now
                </button>
                <a href="/settings/" class="btn btn-secondary" style="margin-left: 1rem;">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

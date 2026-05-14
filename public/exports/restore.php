<?php
/**
 * Restore from Full Backup (ZIP)
 *
 * Two-step process:
 * Step 1 (GET): Upload form
 * Step 2 (POST with file): Preview backup contents, ask for confirmation
 * Step 3 (POST with action=execute): Execute restore
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

if (!class_exists('ZipArchive')) {
    setFlash('error', 'ZIP extension is not available on this server.');
    header('Location: /settings/');
    exit;
}

function insertRow(PDO $db, string $table, array $row): void
{
    $columns = array_keys($row);
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $table,
        implode(', ', array_map(fn($c) => "`$c`", $columns)),
        implode(', ', array_map(fn($c) => ':' . $c, $columns))
    );
    $db->prepare($sql)->execute($row);
}

// ── Step 3: Execute restore ───────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'execute') {
    $tempFile = $_SESSION['restore_temp_file'] ?? null;

    if (!$tempFile || !file_exists($tempFile)) {
        setFlash('error', 'Restore session expired. Please upload the backup again.');
        header('Location: /exports/restore.php');
        exit;
    }

    try {
        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            throw new Exception('Could not open backup file.');
        }

        $jsonContent = $zip->getFromName('backup.json');
        if ($jsonContent === false) {
            throw new Exception('Invalid backup: backup.json not found.');
        }

        $backup = json_decode($jsonContent, true);
        $data   = $backup['data'];

        // Restore database
        $db->beginTransaction();
        try {
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach (['book_author', 'scanned_books', 'wishlist', 'books', 'category_sequences', 'categories', 'maincategories', 'authors'] as $table) {
                $db->exec("DELETE FROM `$table`");
            }

            foreach ($data['maincategories']     ?? [] as $row) { insertRow($db, 'maincategories',     $row); }
            foreach ($data['categories']         ?? [] as $row) { insertRow($db, 'categories',         $row); }
            foreach ($data['category_sequences'] ?? [] as $row) { insertRow($db, 'category_sequences', $row); }
            foreach ($data['authors']            ?? [] as $row) { insertRow($db, 'authors',            $row); }
            foreach ($data['books']              ?? [] as $row) { unset($row['author_ids']); insertRow($db, 'books', $row); }
            foreach ($data['book_author']        ?? [] as $row) { insertRow($db, 'book_author',        $row); }
            foreach ($data['scanned_books']      ?? [] as $row) { insertRow($db, 'scanned_books',      $row); }
            foreach ($data['wishlist']           ?? [] as $row) { insertRow($db, 'wishlist',           $row); }

            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
            throw $e;
        }

        // Restore uploaded files
        $uploadsBase = __DIR__ . '/../uploads/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) {
                continue;
            }
            $targetPath = $uploadsBase . substr($name, strlen('uploads/'));
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($targetPath, $zip->getFromIndex($i));
        }

        $zip->close();
        unlink($tempFile);
        unset($_SESSION['restore_temp_file']);

        $stats = $backup['metadata']['statistics'];
        setFlash('success', sprintf(
            'Restore completed: %d books, %d authors, %d categories restored.',
            $stats['books'] ?? 0,
            $stats['authors'] ?? 0,
            ($stats['maincategories'] ?? 0) + ($stats['categories'] ?? 0)
        ));
        header('Location: /settings/');
        exit;

    } catch (Exception $e) {
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        unset($_SESSION['restore_temp_file']);
        setFlash('error', 'Restore failed: ' . $e->getMessage());
        header('Location: /exports/restore.php');
        exit;
    }
}

// ── Step 2: Validate upload and show preview ──────────────────────────────────

$step       = 1;
$backupMeta = null;
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = 'The uploaded file exceeds the maximum allowed size.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed (error code ' . $file['error'] . ').';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
        $errors[] = 'Please upload a .zip file.';
    } else {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            $errors[] = 'Could not open the file as a ZIP archive.';
        } else {
            $jsonContent = $zip->getFromName('backup.json');
            $zip->close();

            if ($jsonContent === false) {
                $errors[] = 'Invalid backup: backup.json not found in archive.';
            } else {
                $backup = json_decode($jsonContent, true);
                if (($backup['metadata']['application'] ?? '') !== 'OpenBookManager') {
                    $errors[] = 'This does not appear to be a valid OpenBookManager backup.';
                } else {
                    $tempFile = tempnam(sys_get_temp_dir(), 'obm_restore_');
                    move_uploaded_file($file['tmp_name'], $tempFile);
                    $_SESSION['restore_temp_file'] = $tempFile;
                    $backupMeta = $backup['metadata'];
                    $step = 2;
                }
            }
        }
    }
}

// ── Render ────────────────────────────────────────────────────────────────────

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>♻️ Restore from Backup</h1>
        <a href="/settings/" class="btn btn-secondary">← Back to Settings</a>
    </div>

    <?php if ($step === 1): ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Upload Backup File</h2>

            <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                <strong>⚠️ Warning:</strong> Restoring a backup will <strong>replace all existing data</strong>
                (books, authors, categories, wishlist, uploaded files). This cannot be undone.
                Your user account will not be affected.
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="backup_file">Select Full Backup ZIP file</label>
                    <input type="file" id="backup_file" name="backup_file" accept=".zip" required
                           style="display: block; margin-top: 0.5rem;">
                    <small class="text-muted">Only .zip files created with "Full Backup with Files" are supported.</small>
                </div>

                <button type="submit" class="btn btn-danger" style="margin-top: 1rem;">
                    Upload &amp; Preview
                </button>
            </form>
        </div>

    <?php elseif ($step === 2 && $backupMeta): ?>

        <?php
            $stats = $backupMeta['statistics'] ?? [];
            $files = $backupMeta['files'] ?? [];
        ?>

        <div class="section">
            <h2>Backup Preview</h2>
            <p class="text-muted">Please review the backup contents before restoring.</p>

            <div class="info-grid" style="margin-bottom: 1.5rem;">
                <div class="info-item">
                    <label>Backup Date</label>
                    <div><?= e($backupMeta['export_date'] ?? '–') ?></div>
                </div>
                <div class="info-item">
                    <label>App Version</label>
                    <div><?= e($backupMeta['version'] ?? '–') ?></div>
                </div>
                <div class="info-item">
                    <label>Type</label>
                    <div><?= e($backupMeta['export_type'] ?? '–') ?></div>
                </div>
            </div>

            <h3>What will be restored</h3>
            <div class="info-grid" style="margin-bottom: 1.5rem;">
                <div class="info-item"><label>Books</label><div><?= (int)($stats['books'] ?? 0) ?></div></div>
                <div class="info-item"><label>Authors</label><div><?= (int)($stats['authors'] ?? 0) ?></div></div>
                <div class="info-item"><label>Main Categories</label><div><?= (int)($stats['maincategories'] ?? 0) ?></div></div>
                <div class="info-item"><label>Subcategories</label><div><?= (int)($stats['categories'] ?? 0) ?></div></div>
                <div class="info-item"><label>Wishlist Items</label><div><?= (int)($stats['wishlist'] ?? 0) ?></div></div>
                <div class="info-item"><label>Scanned Books</label><div><?= (int)($stats['scanned_books'] ?? 0) ?></div></div>
                <div class="info-item"><label>Cover Images</label><div><?= (int)($files['covers'] ?? 0) ?></div></div>
                <div class="info-item"><label>Documents</label><div><?= (int)($files['documents'] ?? 0) ?></div></div>
            </div>

            <div class="alert alert-warning">
                <strong>⚠️ This will delete all current data and replace it with the backup contents.</strong>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <form method="POST">
                    <input type="hidden" name="action" value="execute">
                    <button type="submit" class="btn btn-danger">
                        ♻️ Restore Now
                    </button>
                </form>
                <a href="/exports/restore.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

<?php
/**
 * Manually Add Book to Scan Queue
 * For books without ISBN or with incorrect API data
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$formData = [
    'title' => '',
    'subtitle' => '',
    'authors_raw' => '',
    'published_year' => '',
    'publisher' => '',
    'pages' => '',
    'language' => '',
    'notes' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'subtitle' => trim($_POST['subtitle'] ?? ''),
        'authors_raw' => trim($_POST['authors_raw'] ?? ''),
        'published_year' => !empty($_POST['published_year']) ? (int)$_POST['published_year'] : null,
        'publisher' => trim($_POST['publisher'] ?? ''),
        'pages' => !empty($_POST['pages']) ? (int)$_POST['pages'] : null,
        'language' => trim($_POST['language'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    if (empty($formData['authors_raw'])) {
        $errors[] = 'At least one author is required.';
    }

    // If no errors, insert into scanned_books
    if (empty($errors)) {
        try {
            // Generate unique ISBN for manually added books (MANUAL-timestamp-random)
            $manualIsbn = 'MANUAL-' . time() . '-' . rand(1000, 9999);

            $stmt = $db->prepare('
                INSERT INTO scanned_books (
                    isbn,
                    title,
                    subtitle,
                    authors_raw,
                    published_year,
                    publisher,
                    pages,
                    language,
                    notes,
                    status,
                    scanned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');

            $stmt->execute([
                $manualIsbn, // Unique ISBN marker for manually added books
                $formData['title'],
                $formData['subtitle'] ?: null,
                $formData['authors_raw'],
                $formData['published_year'],
                $formData['publisher'] ?: null,
                $formData['pages'],
                $formData['language'] ?: null,
                $formData['notes'] ?: null,
                'pending'
            ]);

            setFlash('success', 'Book manually added to import queue!');
            redirect('/books/import/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>➕ Add Book Manually</h1>
        <a href="/books/import/" class="btn btn-secondary">← Back to Import Manager</a>
    </div>

    <div class="section">
        <p class="text-muted" style="margin-bottom: 1.5rem;">
            Use this form to manually add books that don't have an ISBN or where the API returned incorrect data.
            The book will be added to the import queue where you can categorize it normally.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="card">
                <div class="card-header">
                    <h2 style="margin: 0;">📚 Book Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" value="<?= e($formData['title']) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="subtitle">Subtitle</label>
                        <input type="text" id="subtitle" name="subtitle" value="<?= e($formData['subtitle']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="authors_raw">Author(s) *</label>
                        <input type="text" id="authors_raw" name="authors_raw" value="<?= e($formData['authors_raw']) ?>" required>
                        <small class="form-help">Multiple authors separated by comma (e.g., "John Doe, Jane Smith")</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="published_year">Year</label>
                            <input type="number" id="published_year" name="published_year" value="<?= e($formData['published_year']) ?>" min="1000" max="2100">
                        </div>

                        <div class="form-group">
                            <label for="pages">Pages</label>
                            <input type="number" id="pages" name="pages" value="<?= e($formData['pages']) ?>" min="1">
                        </div>

                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" id="language" name="language" value="<?= e($formData['language']) ?>" maxlength="10" placeholder="e.g., en, de">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" value="<?= e($formData['publisher']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3"><?= e($formData['notes']) ?></textarea>
                        <small class="form-help">Any additional information (optional)</small>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <a href="/books/import/" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">💾 Add to Import Queue</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../src/Views/layout/footer.php'; ?>

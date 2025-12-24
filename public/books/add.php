<?php
/**
 * Add New Book
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse combined category value (format: "maincategory|category")
    $categoryCombined = trim($_POST['category_combined'] ?? '');
    $categoryParts = explode('|', $categoryCombined);
    $codeMaincategory = $categoryParts[0] ?? '';
    $codeCategory = $categoryParts[1] ?? '';

    // Get form data
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
        'isbn' => trim($_POST['isbn'] ?? ''),
        'cover_image' => trim($_POST['cover_image'] ?? ''),
        'code_category' => $codeCategory,
        'code_maincategory' => $codeMaincategory,
        'publisher' => trim($_POST['publisher'] ?? ''),
        'language' => trim($_POST['language'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'author_ids' => $_POST['author_ids'] ?? [],
        'format_type' => trim($_POST['format_type'] ?? 'physical'),
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    if (empty($formData['code_category']) || empty($formData['code_maincategory'])) {
        $errors[] = 'Category is required.';
    }

    if (empty($formData['author_ids'])) {
        $errors[] = 'At least one author is required.';
    }

    // Validate format_type
    if (!in_array($formData['format_type'], ['physical', 'digital', 'both'])) {
        $errors[] = 'Invalid format type.';
    }

    // If no errors, insert book
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Verify the category exists (with composite key)
            $stmt = $db->prepare('SELECT code, code_maincategory FROM categories WHERE code = ? AND code_maincategory = ?');
            $stmt->execute([$formData['code_category'], $formData['code_maincategory']]);
            $categoryData = $stmt->fetch();

            if (!$categoryData) {
                throw new Exception('Invalid category selected');
            }

            // Get next number for this category
            $stmt = $db->prepare('SELECT next_number FROM category_sequences WHERE code_category = ? AND code_maincategory = ?');
            $stmt->execute([$formData['code_category'], $formData['code_maincategory']]);
            $sequence = $stmt->fetch();

            if ($sequence) {
                // Sequence exists, use current number and increment
                $nextNumber = $sequence['next_number'];
                $stmt = $db->prepare('UPDATE category_sequences SET next_number = next_number + 1 WHERE code_category = ? AND code_maincategory = ?');
                $stmt->execute([$formData['code_category'], $formData['code_maincategory']]);
            } else {
                // First book in this category, start at 1
                $nextNumber = 1;
                $stmt = $db->prepare('INSERT INTO category_sequences (code_category, code_maincategory, next_number) VALUES (?, ?, 2)');
                $stmt->execute([$formData['code_category'], $formData['code_maincategory']]);
            }

            // Insert book
            $stmt = $db->prepare('
                INSERT INTO books (title, year, isbn, cover_image, document_file, format_type, code_category, code_maincategory, number_in_category, publisher, language, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $formData['title'],
                $formData['year'],
                $formData['isbn'] ?: null,
                $formData['cover_image'] ?: null,
                null, // document_file - will be updated after upload
                $formData['format_type'],
                $formData['code_category'],
                $formData['code_maincategory'],
                $nextNumber,
                $formData['publisher'] ?: null,
                $formData['language'] ?: null,
                $formData['notes'] ?: null
            ]);

            $bookId = $db->lastInsertId();

            // Handle document upload
            $documentFile = null;
            if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = uploadBookDocument($_FILES['document'], $bookId, $config);
                if ($uploadResult['success']) {
                    $documentFile = $uploadResult['path'];
                    // Update book with document path
                    $stmt = $db->prepare('UPDATE books SET document_file = ? WHERE id = ?');
                    $stmt->execute([$documentFile, $bookId]);
                } else {
                    // Upload failed - rollback and show error
                    $db->rollBack();
                    $errors[] = $uploadResult['error'];
                }
            }

            // Only proceed if no upload errors
            if (empty($errors)) {
                // Insert author relationships
                $stmt = $db->prepare('INSERT INTO book_author (book_id, author_id) VALUES (?, ?)');
                foreach ($formData['author_ids'] as $authorId) {
                    $stmt->execute([$bookId, $authorId]);
                }

                $db->commit();

                setFlash('success', 'Book added successfully!');
                redirect('/books/view.php?id=' . $bookId);
            }

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get categories
try {
    $categoriesStmt = $db->query('
        SELECT c.*, mc.code as maincat_code, mc.title as maincat_title
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        ORDER BY mc.title, c.title
    ');
    $categories = $categoriesStmt->fetchAll();

    // Get all authors
    $authorsStmt = $db->query('SELECT * FROM authors ORDER BY lastname, surname');
    $authors = $authorsStmt->fetchAll();

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add New Book</h1>
        <a href="/books/" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="section">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/books/add.php" id="bookForm" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="title">Title *</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        value="<?= e($formData['title'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="author_ids">Author(s) *</label>
                    <select id="author_ids" name="author_ids[]" multiple size="6" required>
                        <?php foreach ($authors as $author): ?>
                            <option
                                value="<?= $author['id'] ?>"
                                <?= in_array($author['id'], $formData['author_ids'] ?? []) ? 'selected' : '' ?>
                            >
                                <?= e($author['lastname']) ?>, <?= e($author['surname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Hold Ctrl/Cmd to select multiple authors</small>
                    <div style="margin-top: 0.5rem;">
                        <a href="/authors/add.php?return=books-add" class="btn btn-sm btn-secondary">+ Add New Author</a>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category_combined">Category *</label>
                    <select id="category_combined" name="category_combined" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $combinedValue = $cat['code_maincategory'] . '|' . $cat['code'];
                            $currentCombined = ($formData['code_maincategory'] ?? '') . '|' . ($formData['code_category'] ?? '');
                            ?>
                            <option
                                value="<?= e($combinedValue) ?>"
                                <?= $currentCombined === $combinedValue ? 'selected' : '' ?>
                            >
                                <?= e($cat['maincat_title']) ?> > <?= e($cat['title']) ?> (<?= e($cat['maincat_code']) ?> <?= e($cat['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">The tag number will be assigned automatically</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="isbn">ISBN</label>
                    <input
                        type="text"
                        id="isbn"
                        name="isbn"
                        placeholder="e.g., 9783499626005"
                        value="<?= e($formData['isbn'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="year">Year</label>
                    <input
                        type="number"
                        id="year"
                        name="year"
                        min="1000"
                        max="<?= date('Y') + 1 ?>"
                        placeholder="e.g., <?= date('Y') ?>"
                        value="<?= e($formData['year'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="language">Language</label>
                    <input
                        type="text"
                        id="language"
                        name="language"
                        placeholder="e.g., EN, DE, FR"
                        maxlength="10"
                        value="<?= e($formData['language'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="publisher">Publisher</label>
                    <input
                        type="text"
                        id="publisher"
                        name="publisher"
                        placeholder="e.g., Penguin Books"
                        value="<?= e($formData['publisher'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="cover_image">Cover Image URL</label>
                    <input
                        type="text"
                        id="cover_image"
                        name="cover_image"
                        placeholder="https://example.com/cover.jpg or /uploads/covers/image.jpg"
                        value="<?= e($formData['cover_image'] ?? '') ?>"
                    >
                    <small class="form-help">Enter a URL or local path to a cover image</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="notes">Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        placeholder="Additional notes about this book..."
                    ><?= e($formData['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="format_type">Format</label>
                    <select id="format_type" name="format_type">
                        <option value="physical" <?= ($formData['format_type'] ?? 'physical') === 'physical' ? 'selected' : '' ?>>Physical Book</option>
                        <option value="digital" <?= ($formData['format_type'] ?? '') === 'digital' ? 'selected' : '' ?>>Digital Only</option>
                        <option value="both" <?= ($formData['format_type'] ?? '') === 'both' ? 'selected' : '' ?>>Physical & Digital</option>
                    </select>
                    <small class="form-help">Is this book physical, digital, or both?</small>
                </div>

                <div class="form-group">
                    <label for="document">Document (PDF/EPUB)</label>
                    <input
                        type="file"
                        id="document"
                        name="document"
                        accept=".pdf,.epub"
                    >
                    <small class="form-help">PDF or EPUB, max <?= $config['documents']['max_size_mb'] ?? 100 ?> MB</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Book</button>
                <a href="/books/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

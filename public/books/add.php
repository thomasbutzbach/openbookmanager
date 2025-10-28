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
    // Get form data
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
        'isbn' => trim($_POST['isbn'] ?? ''),
        'cover_image' => trim($_POST['cover_image'] ?? ''),
        'code_category' => trim($_POST['code_category'] ?? ''),
        'publisher' => trim($_POST['publisher'] ?? ''),
        'language' => trim($_POST['language'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'author_ids' => $_POST['author_ids'] ?? []
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    if (empty($formData['code_category'])) {
        $errors[] = 'Category is required.';
    }

    if (empty($formData['author_ids'])) {
        $errors[] = 'At least one author is required.';
    }

    // If no errors, insert book
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Get and increment sequence for this category (proper autoincrement behavior)
            // Insert or update sequence
            $stmt = $db->prepare('
                INSERT INTO category_sequences (code_category, last_number)
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE last_number = last_number + 1
            ');
            $stmt->execute([$formData['code_category']]);

            // Get the new number
            $stmt = $db->prepare('SELECT last_number FROM category_sequences WHERE code_category = ?');
            $stmt->execute([$formData['code_category']]);
            $nextNumber = $stmt->fetch()['last_number'];

            // Insert book
            $stmt = $db->prepare('
                INSERT INTO books (title, year, isbn, cover_image, code_category, number_in_category, publisher, language, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $formData['title'],
                $formData['year'],
                $formData['isbn'] ?: null,
                $formData['cover_image'] ?: null,
                $formData['code_category'],
                $nextNumber,
                $formData['publisher'] ?: null,
                $formData['language'] ?: null,
                $formData['notes'] ?: null
            ]);

            $bookId = $db->lastInsertId();

            // Insert author relationships
            $stmt = $db->prepare('INSERT INTO book_author (book_id, author_id) VALUES (?, ?)');
            foreach ($formData['author_ids'] as $authorId) {
                $stmt->execute([$bookId, $authorId]);
            }

            $db->commit();

            setFlash('success', 'Book added successfully!');
            redirect('/books/view.php?id=' . $bookId);

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

        <form method="POST" action="/books/add.php" id="bookForm">
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
                    <label for="code_category">Category *</label>
                    <select id="code_category" name="code_category" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option
                                value="<?= e($cat['code']) ?>"
                                <?= ($formData['code_category'] ?? '') === $cat['code'] ? 'selected' : '' ?>
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
                        type="url"
                        id="cover_image"
                        name="cover_image"
                        placeholder="https://example.com/cover.jpg"
                        value="<?= e($formData['cover_image'] ?? '') ?>"
                    >
                    <small class="form-help">Enter a URL to a cover image (file upload coming soon)</small>
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

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Book</button>
                <a href="/books/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

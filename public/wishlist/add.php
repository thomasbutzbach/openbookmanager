<?php
/**
 * Add Wishlist Item
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
        'author_name' => trim($_POST['author_name'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    // If no errors, insert item
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO wishlist (title, author_name, notes) VALUES (?, ?, ?)');
            $stmt->execute([
                $formData['title'],
                $formData['author_name'] ?: null,
                $formData['notes'] ?: null
            ]);

            setFlash('success', 'Item added to wishlist!');
            redirect('/wishlist/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add to Wishlist</h1>
        <a href="/wishlist/" class="btn btn-secondary">Back to Wishlist</a>
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

        <form method="POST" action="/wishlist/add.php">
            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="title">Title *</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        autofocus
                        value="<?= e($formData['title'] ?? '') ?>"
                        placeholder="Book title"
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="author_name">Author(s)</label>
                    <input
                        type="text"
                        id="author_name"
                        name="author_name"
                        value="<?= e($formData['author_name'] ?? '') ?>"
                        placeholder="e.g., J.K. Rowling"
                    >
                    <small class="form-help">Enter author name(s) as free text</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label for="notes">Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        placeholder="ISBN, publisher, price, or any other notes..."
                    ><?= e($formData['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add to Wishlist</button>
                <a href="/wishlist/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

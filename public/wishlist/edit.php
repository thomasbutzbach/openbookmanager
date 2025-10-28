<?php
/**
 * Edit Wishlist Item
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$itemId = $_GET['id'] ?? null;

if (!$itemId) {
    setFlash('error', 'Item ID is required.');
    redirect('/wishlist/');
}

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

    // If no errors, update item
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('UPDATE wishlist SET title = ?, author_name = ?, notes = ? WHERE id = ?');
            $stmt->execute([
                $formData['title'],
                $formData['author_name'] ?: null,
                $formData['notes'] ?: null,
                $itemId
            ]);

            setFlash('success', 'Wishlist item updated!');
            redirect('/wishlist/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load item
try {
    $stmt = $db->prepare('SELECT * FROM wishlist WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        setFlash('error', 'Item not found.');
        redirect('/wishlist/');
    }

    // Pre-fill form data if not submitted yet
    if (empty($formData)) {
        $formData = [
            'title' => $item['title'],
            'author_name' => $item['author_name'],
            'notes' => $item['notes']
        ];
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Wishlist Item</h1>
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

        <form method="POST" action="/wishlist/edit.php?id=<?= $itemId ?>">
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
                <button type="submit" class="btn btn-primary">Update Item</button>
                <a href="/wishlist/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

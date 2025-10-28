<?php
/**
 * Add New Author
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$formData = [];

// Check if return URL is provided (for redirecting back to book form)
$returnUrl = $_GET['return'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'surname' => trim($_POST['surname'] ?? ''),
        'lastname' => trim($_POST['lastname'] ?? '')
    ];

    // Validation
    if (empty($formData['surname'])) {
        $errors[] = 'First name is required.';
    }

    if (empty($formData['lastname'])) {
        $errors[] = 'Last name is required.';
    }

    // Check for duplicate
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('SELECT id FROM authors WHERE surname = ? AND lastname = ?');
            $stmt->execute([$formData['surname'], $formData['lastname']]);
            if ($stmt->fetch()) {
                $errors[] = 'An author with this name already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // If no errors, insert author
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO authors (surname, lastname) VALUES (?, ?)');
            $stmt->execute([$formData['surname'], $formData['lastname']]);

            setFlash('success', 'Author added successfully!');

            // Redirect back to book form if return parameter is set
            if ($returnUrl === 'books-add') {
                redirect('/books/add.php');
            } else {
                redirect('/authors/');
            }

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add New Author</h1>
        <a href="/authors/" class="btn btn-secondary">Back to List</a>
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

        <form method="POST" action="/authors/add.php<?= $returnUrl ? '?return=' . e($returnUrl) : '' ?>" id="authorForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="surname">First Name *</label>
                    <input
                        type="text"
                        id="surname"
                        name="surname"
                        required
                        autofocus
                        value="<?= e($formData['surname'] ?? '') ?>"
                        placeholder="e.g., Stephen"
                    >
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name *</label>
                    <input
                        type="text"
                        id="lastname"
                        name="lastname"
                        required
                        value="<?= e($formData['lastname'] ?? '') ?>"
                        placeholder="e.g., King"
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Author</button>
                <a href="<?= $returnUrl === 'books-add' ? '/books/add.php' : '/authors/' ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

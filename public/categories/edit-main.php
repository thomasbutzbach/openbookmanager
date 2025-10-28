<?php
/**
 * Edit Main Category
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$code = $_GET['code'] ?? null;

if (!$code) {
    setFlash('error', 'Category code is required.');
    redirect('/categories/');
}

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'title' => trim($_POST['title'] ?? '')
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    // If no errors, update
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('UPDATE maincategories SET title = ? WHERE code = ?');
            $stmt->execute([$formData['title'], $code]);

            setFlash('success', 'Main category updated successfully!');
            redirect('/categories/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load category
try {
    $stmt = $db->prepare('SELECT * FROM maincategories WHERE code = ?');
    $stmt->execute([$code]);
    $category = $stmt->fetch();

    if (!$category) {
        setFlash('error', 'Category not found.');
        redirect('/categories/');
    }

    // Get subcategories
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM categories WHERE code_maincategory = ?');
    $stmt->execute([$code]);
    $subcatCount = $stmt->fetch()['count'];

    // Get books
    $stmt = $db->prepare('
        SELECT COUNT(DISTINCT b.id) as count
        FROM books b
        JOIN categories c ON b.code_category = c.code
        WHERE c.code_maincategory = ?
    ');
    $stmt->execute([$code]);
    $bookCount = $stmt->fetch()['count'];

    // Pre-fill form
    if (empty($formData)) {
        $formData = ['title' => $category['title']];
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Main Category</h1>
        <a href="/categories/" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="section">
        <div class="info-box">
            <strong>Code:</strong> <code><?= e($code) ?></code> (cannot be changed)
            <br><strong>Subcategories:</strong> <?= $subcatCount ?>
            <br><strong>Books:</strong> <?= $bookCount ?>
        </div>

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

        <form method="POST" action="/categories/edit-main.php?code=<?= e($code) ?>">
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
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Main Category</button>
                <a href="/categories/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

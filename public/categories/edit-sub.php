<?php
/**
 * Edit Subcategory
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
        'title' => trim($_POST['title'] ?? ''),
        'code_maincategory' => trim($_POST['code_maincategory'] ?? '')
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    if (empty($formData['code_maincategory'])) {
        $errors[] = 'Main category is required.';
    }

    // If no errors, update
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('UPDATE categories SET title = ?, code_maincategory = ? WHERE code = ?');
            $stmt->execute([$formData['title'], $formData['code_maincategory'], $code]);

            setFlash('success', 'Subcategory updated successfully!');
            redirect('/categories/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load category
try {
    $stmt = $db->prepare('
        SELECT c.*, mc.title as maincat_title
        FROM categories c
        JOIN maincategories mc ON c.code_maincategory = mc.code
        WHERE c.code = ?
    ');
    $stmt->execute([$code]);
    $category = $stmt->fetch();

    if (!$category) {
        setFlash('error', 'Category not found.');
        redirect('/categories/');
    }

    // Get books
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM books WHERE code_category = ?');
    $stmt->execute([$code]);
    $bookCount = $stmt->fetch()['count'];

    // Get all main categories
    $stmt = $db->query('SELECT * FROM maincategories ORDER BY title');
    $mainCategories = $stmt->fetchAll();

    // Pre-fill form
    if (empty($formData)) {
        $formData = [
            'title' => $category['title'],
            'code_maincategory' => $category['code_maincategory']
        ];
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Subcategory</h1>
        <a href="/categories/" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="section">
        <div class="info-box">
            <strong>Code:</strong> <code><?= e($code) ?></code> (cannot be changed)
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

        <form method="POST" action="/categories/edit-sub.php?code=<?= e($code) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="code_maincategory">Main Category *</label>
                    <select id="code_maincategory" name="code_maincategory" required>
                        <?php foreach ($mainCategories as $main): ?>
                            <option
                                value="<?= e($main['code']) ?>"
                                <?= ($formData['code_maincategory'] ?? '') === $main['code'] ? 'selected' : '' ?>
                            >
                                <?= e($main['code']) ?> - <?= e($main['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
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
                <button type="submit" class="btn btn-primary">Update Subcategory</button>
                <a href="/categories/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

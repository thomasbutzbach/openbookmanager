<?php
/**
 * Add Subcategory
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$formData = [];
$isJsonRequest = false;

// Get preselected main category from URL
$preselectedMain = $_GET['main'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a JSON request
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJsonRequest = strpos($contentType, 'application/json') !== false;

    // Get form data (support both form and JSON)
    if ($isJsonRequest) {
        $input = json_decode(file_get_contents('php://input'), true);
        $formData = [
            'code' => strtoupper(trim($input['code'] ?? '')),
            'code_maincategory' => trim($input['main_category'] ?? ''),
            'title' => trim($input['title'] ?? '')
        ];
    } else {
        $formData = [
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'code_maincategory' => trim($_POST['code_maincategory'] ?? ''),
            'title' => trim($_POST['title'] ?? '')
        ];
    }

    // Validation
    if (empty($formData['code'])) {
        $errors[] = 'Code is required.';
    } elseif (!preg_match('/^[A-Z]{2}$/', $formData['code'])) {
        $errors[] = 'Code must be exactly 2 uppercase letters (e.g., PH, MA).';
    }

    if (empty($formData['code_maincategory'])) {
        $errors[] = 'Main category is required.';
    }

    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    // Check for duplicate within the same main category
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('SELECT code FROM categories WHERE code = ? AND code_maincategory = ?');
            $stmt->execute([$formData['code'], $formData['code_maincategory']]);
            if ($stmt->fetch()) {
                $errors[] = 'A subcategory with this code already exists in this main category.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // If no errors, insert subcategory
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO categories (code, code_maincategory, title) VALUES (?, ?, ?)');
            $stmt->execute([$formData['code'], $formData['code_maincategory'], $formData['title']]);

            // Initialize category sequence with composite key
            $stmt = $db->prepare('INSERT INTO category_sequences (code_category, code_maincategory, next_number) VALUES (?, ?, 1)');
            $stmt->execute([$formData['code'], $formData['code_maincategory']]);

            // JSON response for AJAX requests
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Category created successfully',
                    'code' => $formData['code'],
                    'title' => $formData['title'],
                    'code_maincategory' => $formData['code_maincategory']
                ]);
                exit;
            }

            // Regular form submission
            setFlash('success', 'Subcategory added successfully!');
            redirect('/categories/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // If there are errors and it's a JSON request, return them
    if (!empty($errors) && $isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        exit;
    }
}

// Get main categories
try {
    $stmt = $db->query('SELECT * FROM maincategories ORDER BY title');
    $mainCategories = $stmt->fetchAll();

    // If preselected main category is set and form not submitted, use it
    if ($preselectedMain && empty($formData['code_maincategory'])) {
        $formData['code_maincategory'] = $preselectedMain;
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add Subcategory</h1>
        <a href="/categories/" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="section">
        <?php if (empty($mainCategories)): ?>
            <div class="alert alert-warning">
                <strong>No main categories found!</strong>
                <p>You need to <a href="/categories/add-main.php">create a main category</a> first before adding subcategories.</p>
            </div>
        <?php else: ?>
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

            <form method="POST" action="/categories/add-sub.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="code_maincategory">Main Category *</label>
                        <select id="code_maincategory" name="code_maincategory" required>
                            <option value="">-- Select Main Category --</option>
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
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code">Code * (2 letters)</label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            required
                            maxlength="2"
                            pattern="[A-Za-z]{2}"
                            autofocus
                            value="<?= e($formData['code'] ?? '') ?>"
                            placeholder="e.g., PH"
                            style="text-transform: uppercase;"
                        >
                        <small class="form-help">Must be exactly 2 uppercase letters</small>
                    </div>

                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            required
                            value="<?= e($formData['title'] ?? '') ?>"
                            placeholder="e.g., Physics"
                        >
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Subcategory</button>
                    <a href="/categories/" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

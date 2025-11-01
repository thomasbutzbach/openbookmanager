<?php
/**
 * Add Main Category
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$formData = [];
$isJsonRequest = false;

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
            'title' => trim($input['title'] ?? '')
        ];
    } else {
        $formData = [
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'title' => trim($_POST['title'] ?? '')
        ];
    }

    // Validation
    if (empty($formData['code'])) {
        $errors[] = 'Code is required.';
    } elseif (!preg_match('/^[A-Z]{2}$/', $formData['code'])) {
        $errors[] = 'Code must be exactly 2 uppercase letters (e.g., WR, BL).';
    }

    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    // Check for duplicate
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('SELECT code FROM maincategories WHERE code = ?');
            $stmt->execute([$formData['code']]);
            if ($stmt->fetch()) {
                $errors[] = 'A main category with this code already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // If no errors, insert main category
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO maincategories (code, title) VALUES (?, ?)');
            $stmt->execute([$formData['code'], $formData['title']]);

            // JSON response for AJAX requests
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Main category created successfully',
                    'code' => $formData['code'],
                    'title' => $formData['title']
                ]);
                exit;
            }

            // Regular form submission
            setFlash('success', 'Main category added successfully!');
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

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Add Main Category</h1>
        <a href="/categories/" class="btn btn-secondary">Back to List</a>
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

        <form method="POST" action="/categories/add-main.php">
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
                        placeholder="e.g., WR"
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
                        placeholder="e.g., Scientific / Research"
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Main Category</button>
                <a href="/categories/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

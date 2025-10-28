<?php
/**
 * Edit Author
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$authorId = $_GET['id'] ?? null;

if (!$authorId) {
    setFlash('error', 'Author ID is required.');
    redirect('/authors/');
}

$errors = [];
$formData = [];

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

    // Check for duplicate (excluding current author)
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('SELECT id FROM authors WHERE surname = ? AND lastname = ? AND id != ?');
            $stmt->execute([$formData['surname'], $formData['lastname'], $authorId]);
            if ($stmt->fetch()) {
                $errors[] = 'An author with this name already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // If no errors, update author
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('UPDATE authors SET surname = ?, lastname = ? WHERE id = ?');
            $stmt->execute([$formData['surname'], $formData['lastname'], $authorId]);

            setFlash('success', 'Author updated successfully!');
            redirect('/authors/');

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load author data
try {
    $stmt = $db->prepare('SELECT * FROM authors WHERE id = ?');
    $stmt->execute([$authorId]);
    $author = $stmt->fetch();

    if (!$author) {
        setFlash('error', 'Author not found.');
        redirect('/authors/');
    }

    // Get books by this author
    $stmt = $db->prepare('
        SELECT b.*
        FROM books b
        JOIN book_author ba ON b.id = ba.book_id
        WHERE ba.author_id = ?
        ORDER BY b.title
    ');
    $stmt->execute([$authorId]);
    $books = $stmt->fetchAll();

    // Pre-fill form data if not submitted yet
    if (empty($formData)) {
        $formData = [
            'surname' => $author['surname'],
            'lastname' => $author['lastname']
        ];
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Author</h1>
        <a href="/authors/" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="section">
        <?php if (!empty($books)): ?>
            <div class="info-box">
                <strong>📚 This author has <?= count($books) ?> book(s)</strong>
            </div>
        <?php endif; ?>

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

        <form method="POST" action="/authors/edit.php?id=<?= $authorId ?>" id="authorForm">
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
                <button type="submit" class="btn btn-primary">Update Author</button>
                <a href="/authors/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <?php if (!empty($books)): ?>
            <div style="margin-top: 2rem;">
                <h3>Books by this Author</h3>
                <ul class="simple-list">
                    <?php foreach ($books as $book): ?>
                        <li>
                            <a href="/books/view.php?id=<?= $book['id'] ?>">
                                <?= e($book['title']) ?>
                                <?php if ($book['year']): ?>
                                    (<?= $book['year'] ?>)
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

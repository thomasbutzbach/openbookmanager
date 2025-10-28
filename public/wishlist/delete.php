<?php
/**
 * Delete Wishlist Item
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

$itemId = $_GET['id'] ?? null;

if (!$itemId) {
    setFlash('error', 'Item ID is required.');
    redirect('/wishlist/');
}

// Get item details
try {
    $stmt = $db->prepare('SELECT * FROM wishlist WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        setFlash('error', 'Item not found.');
        redirect('/wishlist/');
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm === 'yes') {
        try {
            $stmt = $db->prepare('DELETE FROM wishlist WHERE id = ?');
            $stmt->execute([$itemId]);

            setFlash('success', 'Item removed from wishlist!');
            redirect('/wishlist/');

        } catch (PDOException $e) {
            setFlash('error', 'Database error: ' . $e->getMessage());
            redirect('/wishlist/');
        }
    } else {
        redirect('/wishlist/');
    }
}

include __DIR__ . '/../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Remove from Wishlist</h1>
        <a href="/wishlist/" class="btn btn-secondary">Cancel</a>
    </div>

    <div class="section">
        <div class="alert alert-warning">
            <strong>⚠️ Warning:</strong> You are about to remove this item from your wishlist. This action cannot be undone!
        </div>

        <div class="delete-confirmation">
            <h2>Item Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Title</label>
                    <div><strong><?= e($item['title']) ?></strong></div>
                </div>

                <?php if ($item['author_name']): ?>
                    <div class="detail-item">
                        <label>Author(s)</label>
                        <div><?= e($item['author_name']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($item['notes']): ?>
                    <div class="detail-item" style="grid-column: span 2;">
                        <label>Notes</label>
                        <div><?= nl2br(e($item['notes'])) ?></div>
                    </div>
                <?php endif; ?>

                <div class="detail-item">
                    <label>Added</label>
                    <div><?= formatDate($item['created_at']) ?></div>
                </div>
            </div>

            <form method="POST" action="/wishlist/delete.php?id=<?= $itemId ?>" style="margin-top: 2rem;">
                <p><strong>Are you sure you want to remove this item from your wishlist?</strong></p>

                <div class="form-actions">
                    <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                        🗑️ Yes, Remove Item
                    </button>
                    <a href="/wishlist/" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../src/Views/layout/footer.php'; ?>

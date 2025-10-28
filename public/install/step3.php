<?php
/**
 * Installer - Step 3: Admin Account
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../../config/config.php')) {
    die('Installation already completed.');
}

// Check if step 2 completed
if (empty($_SESSION['install']['db_name'])) {
    header('Location: /install/step2.php');
    exit;
}

// Handle form submission
$errors = [];
$formData = [
    'admin_user' => $_POST['admin_user'] ?? '',
    'admin_pass' => $_POST['admin_pass'] ?? '',
    'admin_pass_confirm' => $_POST['admin_pass_confirm'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    if (empty($formData['admin_user'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($formData['admin_user']) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }

    if (empty($formData['admin_pass'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($formData['admin_pass']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if ($formData['admin_pass'] !== $formData['admin_pass_confirm']) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        // Save to session
        $_SESSION['install']['admin_user'] = $formData['admin_user'];
        $_SESSION['install']['admin_pass'] = $formData['admin_pass'];

        // Redirect to step 4
        header('Location: /install/step4.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account - OpenBookManager Installation</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>📚 OpenBookManager</h1>
            <p>Installation Wizard</p>
        </div>

        <div class="installer-content">
            <div class="installer-step">Step 3 of 4</div>
            <h2>Admin Account</h2>
            <p>Create your administrator account. You'll use this to log in to OpenBookManager.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        • <?= htmlspecialchars($error) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/install/step3.php">
                <div class="form-group">
                    <label for="admin_user">Username *</label>
                    <input
                        type="text"
                        id="admin_user"
                        name="admin_user"
                        value="<?= htmlspecialchars($formData['admin_user']) ?>"
                        required
                        minlength="3"
                        placeholder="admin"
                        autocomplete="off"
                    >
                    <small class="text-muted">At least 3 characters</small>
                </div>

                <div class="form-group">
                    <label for="admin_pass">Password *</label>
                    <input
                        type="password"
                        id="admin_pass"
                        name="admin_pass"
                        required
                        minlength="8"
                        placeholder="••••••••"
                        autocomplete="new-password"
                    >
                    <small class="text-muted">At least 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="admin_pass_confirm">Confirm Password *</label>
                    <input
                        type="password"
                        id="admin_pass_confirm"
                        name="admin_pass_confirm"
                        required
                        minlength="8"
                        placeholder="••••••••"
                        autocomplete="new-password"
                    >
                </div>

                <div class="installer-actions">
                    <a href="/install/step2.php" class="btn btn-secondary">Back</a>
                    <button type="submit" class="btn btn-primary">Continue to Installation</button>
                </div>
            </form>
        </div>

        <div class="installer-footer">
            OpenBookManager v1.0.0 | Installation Wizard
        </div>
    </div>
</body>
</html>

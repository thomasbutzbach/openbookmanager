<?php
/**
 * Login Page
 */

$app = require __DIR__ . '/../src/bootstrap.php';
extract($app);

// If already logged in, redirect to dashboard
if (isAuthenticated()) {
    redirect('/');
}

$error = null;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        try {
            $stmt = $db->prepare('SELECT id, username, password FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                setFlash('success', 'Successfully logged in!');
                redirect('/');
            } else {
                $error = 'Invalid credentials.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1><?= e($config['app']['name']) ?></h1>
            <p class="subtitle">Book Collection Management</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        value="<?= e($_POST['username'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <small>Version <?= e($config['app']['version']) ?></small>
            </div>
        </div>
    </div>
</body>
</html>

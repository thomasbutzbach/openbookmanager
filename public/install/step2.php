<?php
/**
 * Installer - Step 2: Database Configuration
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../../config/config.php')) {
    die('Installation already completed.');
}

// Handle form submission
$errors = [];
$formData = [
    'db_host' => $_POST['db_host'] ?? ($_SESSION['install']['db_host'] ?? 'localhost'),
    'db_port' => $_POST['db_port'] ?? ($_SESSION['install']['db_port'] ?? '3306'),
    'db_name' => $_POST['db_name'] ?? ($_SESSION['install']['db_name'] ?? ''),
    'db_user' => $_POST['db_user'] ?? ($_SESSION['install']['db_user'] ?? ''),
    'db_pass' => $_POST['db_pass'] ?? ($_SESSION['install']['db_pass'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    if (empty($formData['db_host'])) {
        $errors[] = 'Database host is required';
    }
    if (empty($formData['db_name'])) {
        $errors[] = 'Database name is required';
    }
    if (empty($formData['db_user'])) {
        $errors[] = 'Database user is required';
    }

    if (empty($errors)) {
        // Test connection
        try {
            $testDb = new PDO(
                "mysql:host={$formData['db_host']};port={$formData['db_port']};dbname={$formData['db_name']};charset=utf8mb4",
                $formData['db_user'],
                $formData['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Save to session
            $_SESSION['install'] = $formData;

            // Redirect to step 3
            header('Location: /install/step3.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - OpenBookManager Installation</title>
    <link rel="stylesheet" href="/css/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>📚 OpenBookManager</h1>
            <p>Installation Wizard</p>
        </div>

        <div class="installer-content">
            <div class="installer-step">Step 2 of 4</div>
            <h2>Database Configuration</h2>
            <p>Please enter your database connection details. Make sure the database already exists.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        • <?= htmlspecialchars($error) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/install/step2.php" x-data="{ testing: false, testResult: null }">
                <div class="form-group">
                    <label for="db_host">Database Host *</label>
                    <input
                        type="text"
                        id="db_host"
                        name="db_host"
                        value="<?= htmlspecialchars($formData['db_host']) ?>"
                        required
                        placeholder="localhost"
                    >
                    <small class="text-muted">Usually "localhost"</small>
                </div>

                <div class="form-group">
                    <label for="db_port">Database Port *</label>
                    <input
                        type="number"
                        id="db_port"
                        name="db_port"
                        value="<?= htmlspecialchars($formData['db_port']) ?>"
                        required
                        placeholder="3306"
                    >
                    <small class="text-muted">Default MySQL port is 3306</small>
                </div>

                <div class="form-group">
                    <label for="db_name">Database Name *</label>
                    <input
                        type="text"
                        id="db_name"
                        name="db_name"
                        value="<?= htmlspecialchars($formData['db_name']) ?>"
                        required
                        placeholder="openbookmanager"
                    >
                    <small class="text-muted">The database must already exist</small>
                </div>

                <div class="form-group">
                    <label for="db_user">Database User *</label>
                    <input
                        type="text"
                        id="db_user"
                        name="db_user"
                        value="<?= htmlspecialchars($formData['db_user']) ?>"
                        required
                        placeholder="username"
                    >
                </div>

                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input
                        type="password"
                        id="db_pass"
                        name="db_pass"
                        value="<?= htmlspecialchars($formData['db_pass']) ?>"
                        placeholder="password"
                    >
                    <small class="text-muted">Leave empty if no password</small>
                </div>

                <div class="installer-actions">
                    <a href="/install/" class="btn btn-secondary">Back</a>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="testing = true;
                                fetch('/install/test-connection.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: new URLSearchParams(new FormData(document.querySelector('form')))
                                })
                                .then(r => r.json())
                                .then(data => { testResult = data; testing = false; })
                                .catch(() => { testResult = {success: false, message: 'Connection test failed'}; testing = false; })"
                        :disabled="testing"
                    >
                        <span x-show="!testing">🔍 Test Connection</span>
                        <span x-show="testing">Testing...</span>
                    </button>

                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>

                <!-- Test Result -->
                <div x-show="testResult" x-transition class="alert" :class="testResult?.success ? 'alert-success' : 'alert-danger'" style="margin-top: 1rem;">
                    <span x-text="testResult?.message"></span>
                </div>
            </form>
        </div>

        <div class="installer-footer">
            OpenBookManager v1.0.0 | Installation Wizard
        </div>
    </div>
</body>
</html>

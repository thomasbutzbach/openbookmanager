<?php
/**
 * Installer - Step 1: Welcome & System Check
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../../config/config.php')) {
    die('Installation already completed. Delete config/config.php to reinstall.');
}

// Reset install session
$_SESSION['install'] = [];

// System Requirements Check
$checks = [
    'php_version' => [
        'label' => 'PHP Version >= 8.0',
        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'value' => PHP_VERSION,
    ],
    'pdo' => [
        'label' => 'PDO Extension',
        'status' => extension_loaded('pdo'),
        'value' => extension_loaded('pdo') ? 'Installed' : 'Not installed',
    ],
    'pdo_mysql' => [
        'label' => 'PDO MySQL Driver',
        'status' => extension_loaded('pdo_mysql'),
        'value' => extension_loaded('pdo_mysql') ? 'Installed' : 'Not installed',
    ],
    'json' => [
        'label' => 'JSON Extension',
        'status' => extension_loaded('json'),
        'value' => extension_loaded('json') ? 'Installed' : 'Not installed',
    ],
    'config_writable' => [
        'label' => 'config/ directory writable',
        'status' => is_writable(__DIR__ . '/../../config'),
        'value' => is_writable(__DIR__ . '/../../config') ? 'Writable' : 'Not writable',
    ],
    'uploads_writable' => [
        'label' => 'public/uploads/ directory writable',
        'status' => is_writable(__DIR__ . '/../uploads'),
        'value' => is_writable(__DIR__ . '/../uploads') ? 'Writable' : 'Not writable',
    ],
];

$allChecksPass = !in_array(false, array_column($checks, 'status'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - OpenBookManager</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>📚 OpenBookManager</h1>
            <p>Installation Wizard</p>
        </div>

        <div class="installer-content">
            <div class="installer-step">Step 1 of 4</div>
            <h2>Welcome</h2>
            <p>Welcome to the OpenBookManager installation wizard. This will guide you through the setup process.</p>

            <h3 style="margin-top: 2rem;">System Requirements</h3>
            <div class="requirements-list">
                <?php foreach ($checks as $check): ?>
                    <div class="requirement-item <?= $check['status'] ? 'success' : 'error' ?>">
                        <span class="requirement-icon"><?= $check['status'] ? '✓' : '✗' ?></span>
                        <span class="requirement-label"><?= $check['label'] ?></span>
                        <span class="requirement-value"><?= $check['value'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$allChecksPass): ?>
                <div class="alert alert-danger" style="margin-top: 2rem;">
                    <strong>Installation cannot proceed.</strong><br>
                    Please fix the issues above and refresh this page.
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-top: 2rem;">
                    <strong>All requirements met!</strong><br>
                    You can proceed with the installation.
                </div>

                <div class="installer-actions">
                    <a href="/install/step2.php" class="btn btn-primary btn-lg">Continue to Database Setup</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="installer-footer">
            OpenBookManager v1.0.0 | Installation Wizard
        </div>
    </div>
</body>
</html>

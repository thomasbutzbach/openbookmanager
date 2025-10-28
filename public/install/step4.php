<?php
/**
 * Installer - Step 4: Execute Installation
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../../config/config.php')) {
    die('Installation already completed.');
}

// Check if previous steps completed
if (empty($_SESSION['install']['db_name']) || empty($_SESSION['install']['admin_user'])) {
    header('Location: /install/');
    exit;
}

$installLog = [];
$installStatus = 'pending';
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_install'])) {
    $installStatus = 'running';

    try {
        $installLog[] = '🚀 Starting installation...';
        $installData = $_SESSION['install'];

        // Step 1: Create config.php
        $installLog[] = '1️⃣ Creating configuration file...';

        // Extract variables for proper interpolation
        $dbHost = $installData['db_host'];
        $dbPort = $installData['db_port'];
        $dbName = $installData['db_name'];
        $dbUser = $installData['db_user'];
        $dbPass = $installData['db_pass'];

        // Build config.php content
        $configContent = <<<PHP
<?php
/**
 * OpenBookManager Configuration
 */

return [
    // Database Configuration
    'database' => [
        'host' => '{$dbHost}',
        'port' => {$dbPort},
        'database' => '{$dbName}',
        'username' => '{$dbUser}',
        'password' => '{$dbPass}',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // Application Settings
    'app' => [
        'name' => 'OpenBookManager',
        'version' => '1.0.0',
        'url' => 'http://localhost:8000',
        'timezone' => 'Europe/Berlin',
        'debug' => true,
    ],

    // Pagination settings
    'pagination' => [
        'books' => 50,
        'authors' => 20,
        'categories' => 50,
        'wishlist' => 20,
    ],

    // Session Configuration
    'session' => [
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
    ],
];

PHP;

        if (!file_put_contents(__DIR__ . '/../../config/config.php', $configContent)) {
            throw new Exception('Could not write config.php');
        }

        $installLog[] = '✓ Configuration file created';

        // Step 2: Connect to database
        $installLog[] = '2️⃣ Connecting to database...';

        $db = new PDO(
            "mysql:host={$installData['db_host']};port={$installData['db_port']};dbname={$installData['db_name']};charset=utf8mb4",
            $installData['db_user'],
            $installData['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $installLog[] = '✓ Database connection established';

        // Step 3: Execute schema.sql
        $installLog[] = '3️⃣ Creating database structure...';

        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('schema.sql not found');
        }

        $sql = file_get_contents($schemaFile);

        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s)
        );

        foreach ($statements as $statement) {
            $db->exec($statement);
        }

        $installLog[] = '✓ Database structure created';

        // Step 4: Execute add_system_info.sql
        $installLog[] = '4️⃣ Initializing system information...';

        $systemInfoFile = __DIR__ . '/../../database/add_system_info.sql';
        if (file_exists($systemInfoFile)) {
            $sql = file_get_contents($systemInfoFile);
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s)
            );

            foreach ($statements as $statement) {
                $db->exec($statement);
            }

            $installLog[] = '✓ System information initialized';
        }

        // Step 5: Create admin user
        $installLog[] = '5️⃣ Creating admin account...';

        $hashedPassword = password_hash($installData['admin_pass'], PASSWORD_BCRYPT);

        $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $stmt->execute([$installData['admin_user'], $hashedPassword]);

        $installLog[] = '✓ Admin account created';

        // Step 6: Success!
        $installLog[] = '';
        $installLog[] = '✅ Installation completed successfully!';
        $installLog[] = '';
        $installLog[] = 'Username: ' . $installData['admin_user'];
        $installLog[] = 'Database: ' . $installData['db_name'];

        $installStatus = 'success';

        // Clear install session
        unset($_SESSION['install']);

    } catch (Exception $e) {
        $installLog[] = '';
        $installLog[] = '❌ Installation failed!';
        $installLog[] = 'Error: ' . $e->getMessage();
        $installStatus = 'error';
        $errorMessage = $e->getMessage();

        // Cleanup on error - remove config.php
        if (file_exists(__DIR__ . '/../../config/config.php')) {
            @unlink(__DIR__ . '/../../config/config.php');
        }
    }
}
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
            <div class="installer-step">Step 4 of 4</div>
            <h2>Installation</h2>

            <?php if ($installStatus === 'pending'): ?>
                <p>Ready to install OpenBookManager with the following settings:</p>

                <div style="margin: 2rem 0; padding: 1.5rem; background: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color); text-align: left;">
                    <div style="margin-bottom: 1rem; text-align: left;">
                        <strong>Database:</strong> <?= htmlspecialchars($_SESSION['install']['db_name']) ?>
                    </div>
                    <div style="margin-bottom: 1rem; text-align: left;">
                        <strong>Database Host:</strong> <?= htmlspecialchars($_SESSION['install']['db_host']) ?>:<?= htmlspecialchars($_SESSION['install']['db_port']) ?>
                    </div>
                    <div style="text-align: left;">
                        <strong>Admin Username:</strong> <?= htmlspecialchars($_SESSION['install']['admin_user']) ?>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <strong>⚠️ Important:</strong><br>
                    • This will create all necessary database tables<br>
                    • Make sure the database is empty or you have a backup<br>
                    • This process cannot be undone
                </div>

                <form method="POST">
                    <input type="hidden" name="start_install" value="1">
                    <div class="installer-actions">
                        <a href="/install/step3.php" class="btn btn-secondary">Back</a>
                        <button type="submit" class="btn btn-primary btn-lg">🚀 Start Installation</button>
                    </div>
                </form>

            <?php elseif ($installStatus === 'success'): ?>
                <div class="alert alert-success">
                    <h3>🎉 Installation Successful!</h3>
                    <p>OpenBookManager has been installed successfully.</p>
                </div>

                <div class="install-log">
                    <?php foreach ($installLog as $line): ?>
                        <div><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="installer-actions" style="margin-top: 2rem;">
                    <a href="/login.php" class="btn btn-primary btn-lg">Go to Login</a>
                </div>

            <?php elseif ($installStatus === 'error'): ?>
                <div class="alert alert-danger">
                    <h3>❌ Installation Failed</h3>
                    <p><?= htmlspecialchars($errorMessage) ?></p>
                </div>

                <div class="install-log">
                    <?php foreach ($installLog as $line): ?>
                        <div><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="installer-actions" style="margin-top: 2rem;">
                    <a href="/install/step3.php" class="btn btn-secondary">Back</a>
                    <button onclick="location.reload()" class="btn btn-primary">Try Again</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="installer-footer">
            OpenBookManager v1.0.0 | Installation Wizard
        </div>
    </div>
</body>
</html>

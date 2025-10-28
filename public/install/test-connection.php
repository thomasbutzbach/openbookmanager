<?php
/**
 * AJAX Endpoint: Test Database Connection
 */

session_start();

header('Content-Type: application/json');

// Check if already installed
if (file_exists(__DIR__ . '/../../config/config.php')) {
    echo json_encode(['success' => false, 'message' => 'Already installed']);
    exit;
}

$host = $_POST['db_host'] ?? '';
$port = $_POST['db_port'] ?? '3306';
$name = $_POST['db_name'] ?? '';
$user = $_POST['db_user'] ?? '';
$pass = $_POST['db_pass'] ?? '';

if (empty($host) || empty($name) || empty($user)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    // Test if we can query
    $db->query('SELECT 1');

    echo json_encode([
        'success' => true,
        'message' => '✓ Connection successful! Database is accessible.'
    ]);

} catch (PDOException $e) {
    $message = $e->getMessage();

    // Provide helpful error messages
    if (strpos($message, 'Unknown database') !== false) {
        $message = "Database '{$name}' does not exist. Please create it first.";
    } elseif (strpos($message, 'Access denied') !== false) {
        $message = 'Access denied. Please check your username and password.';
    } elseif (strpos($message, 'Connection refused') !== false) {
        $message = "Cannot connect to database server at '{$host}:{$port}'. Is MySQL running?";
    }

    echo json_encode([
        'success' => false,
        'message' => '✗ Connection failed: ' . $message
    ]);
}

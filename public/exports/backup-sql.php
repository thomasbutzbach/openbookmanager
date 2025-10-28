<?php
/**
 * Full SQL Backup
 * Creates a complete SQL dump of all tables for direct database restore
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Tables to export (in order to respect foreign keys)
$tables = [
    'users',
    'maincategories',
    'categories',
    'category_sequences',
    'authors',
    'books',
    'book_author',
    'wishlist',
    'changelog',
];

try {
    // Start output buffering
    ob_start();

    // SQL Header
    echo "-- OpenBookManager SQL Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: " . $config['database']['database'] . "\n";
    echo "-- \n\n";

    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET time_zone = \"+00:00\";\n\n";

    // Export each table
    foreach ($tables as $table) {
        // Check if table exists
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            echo "-- Table $table does not exist, skipping\n\n";
            continue;
        }

        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for table `$table`\n";
        echo "-- --------------------------------------------------------\n\n";

        // Get CREATE TABLE statement
        $stmt = $db->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch();
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $row['Create Table'] . ";\n\n";

        // Get table data
        $stmt = $db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            echo "-- Dumping data for table `$table`\n";
            echo "-- \n\n";

            // Get column names
            $columns = array_keys($rows[0]);
            $columnsList = '`' . implode('`, `', $columns) . '`';

            // Insert data in batches
            $batchSize = 100;
            $batches = array_chunk($rows, $batchSize);

            foreach ($batches as $batch) {
                echo "INSERT INTO `$table` ($columnsList) VALUES\n";

                $values = [];
                foreach ($batch as $row) {
                    $escapedValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escapedValues[] = 'NULL';
                        } else {
                            // Escape special characters
                            $escaped = addslashes($value);
                            $escapedValues[] = "'$escaped'";
                        }
                    }
                    $values[] = '(' . implode(', ', $escapedValues) . ')';
                }

                echo implode(",\n", $values) . ";\n\n";
            }
        } else {
            echo "-- No data in table `$table`\n\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "\n-- Backup completed successfully\n";

    // Get the output
    $sqlContent = ob_get_clean();

    // Send as download
    $filename = 'openbookmanager_backup_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sqlContent));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $sqlContent;
    exit;

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

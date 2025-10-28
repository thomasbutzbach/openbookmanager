<?php
/**
 * Wishlist Export
 * Supports CSV, JSON, and PDF (print-friendly HTML) formats
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get export format
$format = $_GET['format'] ?? 'csv';
$allowedFormats = ['csv', 'json', 'pdf'];

if (!in_array($format, $allowedFormats)) {
    die('Invalid export format');
}

// Get filter parameters
$search = $_GET['search'] ?? '';

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

if (!empty($search)) {
    $whereClause .= ' AND (title LIKE ? OR author_name LIKE ? OR notes LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Build query
$sql = "
    SELECT * FROM wishlist
    {$whereClause}
    ORDER BY created_at DESC
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        die('No wishlist items to export');
    }

    // Handle different export formats
    if ($format === 'csv') {
        // Prepare data for CSV
        $csvData = [];
        foreach ($items as $item) {
            $csvData[] = [
                'Title' => $item['title'],
                'Author(s)' => $item['author_name'] ?: '-',
                'Notes' => $item['notes'] ?: '-',
                'Added' => formatDateTime($item['created_at']),
            ];
        }

        $filename = 'wishlist_' . date('Y-m-d_His') . '.csv';
        exportCSV($csvData, $filename);

    } elseif ($format === 'json') {
        // Prepare data for JSON
        $jsonData = [];
        foreach ($items as $item) {
            $jsonData[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'author_name' => $item['author_name'] ?: null,
                'notes' => $item['notes'] ?: null,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }

        $filename = 'wishlist_' . date('Y-m-d_His') . '.json';
        exportJSON($jsonData, $filename);

    } elseif ($format === 'pdf') {
        // PDF = Print-friendly HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Wishlist Export - <?= date('Y-m-d H:i:s') ?></title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    font-size: 12px;
                }
                h1 {
                    font-size: 24px;
                    margin-bottom: 10px;
                }
                .meta {
                    color: #666;
                    margin-bottom: 20px;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .print-button {
                    margin-bottom: 20px;
                    padding: 10px 20px;
                    background-color: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .print-button:hover {
                    background-color: #1d4ed8;
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()" class="print-button">🖨️ Print / Save as PDF</button>
            </div>

            <h1>Wishlist Export</h1>
            <div class="meta">
                Export Date: <?= date('Y-m-d H:i:s') ?><br>
                Total Items: <?= count($items) ?>
                <?php if (!empty($search)): ?>
                    <br>Filtered Results
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author(s)</th>
                        <th>Notes</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= e($item['title']) ?></strong></td>
                            <td><?= e($item['author_name'] ?: '-') ?></td>
                            <td><?= e($item['notes'] ? truncate($item['notes'], 100) : '-') ?></td>
                            <td><?= formatDate($item['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

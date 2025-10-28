<?php
/**
 * Authors Export
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
    $whereClause .= ' AND (a.surname LIKE ? OR a.lastname LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Build query
$sql = "
    SELECT a.*,
           COUNT(ba.book_id) as book_count
    FROM authors a
    LEFT JOIN book_author ba ON a.id = ba.author_id
    {$whereClause}
    GROUP BY a.id
    ORDER BY a.lastname, a.surname
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $authors = $stmt->fetchAll();

    if (empty($authors)) {
        die('No authors to export');
    }

    // Handle different export formats
    if ($format === 'csv') {
        // Prepare data for CSV
        $csvData = [];
        foreach ($authors as $author) {
            $csvData[] = [
                'Last Name' => $author['lastname'],
                'First Name' => $author['surname'],
                'Book Count' => $author['book_count'],
                'Added' => formatDateTime($author['created_at']),
            ];
        }

        $filename = 'authors_' . date('Y-m-d_His') . '.csv';
        exportCSV($csvData, $filename);

    } elseif ($format === 'json') {
        // Prepare data for JSON
        $jsonData = [];
        foreach ($authors as $author) {
            $jsonData[] = [
                'id' => $author['id'],
                'surname' => $author['surname'],
                'lastname' => $author['lastname'],
                'book_count' => (int)$author['book_count'],
                'created_at' => $author['created_at'],
                'updated_at' => $author['updated_at'],
            ];
        }

        $filename = 'authors_' . date('Y-m-d_His') . '.json';
        exportJSON($jsonData, $filename);

    } elseif ($format === 'pdf') {
        // PDF = Print-friendly HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Authors Export - <?= date('Y-m-d H:i:s') ?></title>
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

            <h1>Authors Export</h1>
            <div class="meta">
                Export Date: <?= date('Y-m-d H:i:s') ?><br>
                Total Authors: <?= count($authors) ?>
                <?php if (!empty($search)): ?>
                    <br>Filtered Results
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Books</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authors as $author): ?>
                        <tr>
                            <td><strong><?= e($author['lastname']) ?></strong></td>
                            <td><?= e($author['surname']) ?></td>
                            <td><?= $author['book_count'] ?> book(s)</td>
                            <td><?= formatDate($author['created_at']) ?></td>
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

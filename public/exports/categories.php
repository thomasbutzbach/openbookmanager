<?php
/**
 * Categories Export
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

// Build query - get all categories with their main categories
$sql = "
    SELECT c.*,
           mc.title as maincat_title,
           COUNT(DISTINCT b.id) as book_count
    FROM categories c
    JOIN maincategories mc ON c.code_maincategory = mc.code
    LEFT JOIN books b ON b.code_category = c.code
    GROUP BY c.code
    ORDER BY mc.title, c.title
";

try {
    $stmt = $db->query($sql);
    $categories = $stmt->fetchAll();

    if (empty($categories)) {
        die('No categories to export');
    }

    // Handle different export formats
    if ($format === 'csv') {
        // Prepare data for CSV
        $csvData = [];
        foreach ($categories as $cat) {
            $csvData[] = [
                'Code' => $cat['code'],
                'Title' => $cat['title'],
                'Main Category' => $cat['maincat_title'],
                'Main Category Code' => $cat['code_maincategory'],
                'Book Count' => $cat['book_count'],
                'Created' => formatDateTime($cat['created_at']),
            ];
        }

        $filename = 'categories_' . date('Y-m-d_His') . '.csv';
        exportCSV($csvData, $filename);

    } elseif ($format === 'json') {
        // Prepare data for JSON - hierarchical structure
        $mainCategories = [];

        // First get all main categories
        $mainStmt = $db->query('SELECT * FROM maincategories ORDER BY title');
        $allMainCats = $mainStmt->fetchAll();

        foreach ($allMainCats as $main) {
            $mainCategories[] = [
                'code' => $main['code'],
                'title' => $main['title'],
                'subcategories' => []
            ];
        }

        // Add subcategories
        foreach ($categories as $cat) {
            foreach ($mainCategories as &$main) {
                if ($main['code'] === $cat['code_maincategory']) {
                    $main['subcategories'][] = [
                        'code' => $cat['code'],
                        'title' => $cat['title'],
                        'book_count' => (int)$cat['book_count'],
                        'created_at' => $cat['created_at'],
                        'updated_at' => $cat['updated_at'],
                    ];
                    break;
                }
            }
        }

        $filename = 'categories_' . date('Y-m-d_His') . '.json';
        exportJSON($mainCategories, $filename);

    } elseif ($format === 'pdf') {
        // Get main categories for grouping
        $mainStmt = $db->query('SELECT * FROM maincategories ORDER BY title');
        $mainCategories = $mainStmt->fetchAll();

        // Group categories by main category
        $grouped = [];
        foreach ($mainCategories as $main) {
            $grouped[$main['code']] = [
                'main' => $main,
                'subs' => []
            ];
        }

        foreach ($categories as $cat) {
            $grouped[$cat['code_maincategory']]['subs'][] = $cat;
        }

        // PDF = Print-friendly HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Categories Export - <?= date('Y-m-d H:i:s') ?></title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                    .category-group { page-break-inside: avoid; }
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
                .category-group {
                    margin-bottom: 2rem;
                    border: 1px solid #e2e8f0;
                    border-radius: 0.375rem;
                    padding: 1rem;
                }
                .category-header {
                    background-color: #f2f2f2;
                    padding: 0.75rem;
                    margin: -1rem -1rem 1rem -1rem;
                    font-weight: bold;
                    font-size: 14px;
                }
                .category-code {
                    font-family: monospace;
                    background-color: #e2e8f0;
                    padding: 0.25rem 0.5rem;
                    border-radius: 0.25rem;
                    margin-left: 0.5rem;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f9f9f9;
                    font-weight: bold;
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

            <h1>Categories Export</h1>
            <div class="meta">
                Export Date: <?= date('Y-m-d H:i:s') ?><br>
                Total Main Categories: <?= count($mainCategories) ?><br>
                Total Subcategories: <?= count($categories) ?>
            </div>

            <?php foreach ($grouped as $group): ?>
                <?php if (!empty($group['subs'])): ?>
                    <div class="category-group">
                        <div class="category-header">
                            <?= e($group['main']['title']) ?>
                            <span class="category-code"><?= e($group['main']['code']) ?></span>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Books</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['subs'] as $sub): ?>
                                    <tr>
                                        <td><code><?= e($sub['code']) ?></code></td>
                                        <td><?= e($sub['title']) ?></td>
                                        <td><?= $sub['book_count'] ?> book(s)</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        exit;
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

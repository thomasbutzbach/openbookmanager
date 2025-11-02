<?php
/**
 * Books Export
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

// Get filter parameters (same as in books/index.php)
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$languageFilter = $_GET['language'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

if (!empty($search)) {
    $whereClause .= ' AND (b.title LIKE ? OR b.isbn LIKE ? OR b.publisher LIKE ? OR b.notes LIKE ? OR CONCAT(a.surname, " ", a.lastname) LIKE ? OR CONCAT(a.lastname, " ", a.surname) LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($categoryFilter)) {
    $whereClause .= ' AND b.code_category = ?';
    $params[] = $categoryFilter;
}

if (!empty($languageFilter)) {
    $whereClause .= ' AND b.language = ?';
    $params[] = $languageFilter;
}

// Build query (no pagination for export - export all matching records)
$sql = "
    SELECT b.*,
           GROUP_CONCAT(CONCAT(a.surname, ' ', a.lastname) SEPARATOR ', ') as authors,
           c.title as category_title,
           mc.code as maincat_code,
           mc.title as maincat_title
    FROM books b
    LEFT JOIN book_author ba ON b.id = ba.book_id
    LEFT JOIN authors a ON ba.author_id = a.id
    LEFT JOIN categories c ON b.code_category = c.code AND b.code_maincategory = c.code_maincategory
    LEFT JOIN maincategories mc ON c.code_maincategory = mc.code
    {$whereClause}
    GROUP BY b.id
";

// Sorting
$allowedSorts = ['title', 'year', 'created_at', 'code_category'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
$sql .= " ORDER BY b.{$sortBy} {$sortOrder}";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    if (empty($books)) {
        die('No books to export');
    }

    // Handle different export formats
    if ($format === 'csv') {
        // Prepare data for CSV
        $csvData = [];
        foreach ($books as $book) {
            $tag = generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']);
            $csvData[] = [
                'Tag' => $tag,
                'Title' => $book['title'],
                'Authors' => $book['authors'] ?: '-',
                'ISBN' => $book['isbn'] ?: '-',
                'Year' => formatYear($book['year']),
                'Publisher' => $book['publisher'] ?: '-',
                'Language' => $book['language'] ?: '-',
                'Main Category' => $book['maincat_title'],
                'Category' => $book['category_title'],
                'Notes' => $book['notes'] ?: '-',
                'Added' => formatDateTime($book['created_at']),
            ];
        }

        $filename = 'books_' . date('Y-m-d_His') . '.csv';
        exportCSV($csvData, $filename);

    } elseif ($format === 'json') {
        // Prepare data for JSON
        $jsonData = [];
        foreach ($books as $book) {
            $tag = generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']);
            $jsonData[] = [
                'tag' => $tag,
                'title' => $book['title'],
                'authors' => $book['authors'] ?: null,
                'isbn' => $book['isbn'] ?: null,
                'year' => $book['year'],
                'publisher' => $book['publisher'] ?: null,
                'language' => $book['language'] ?: null,
                'main_category' => [
                    'code' => $book['maincat_code'],
                    'title' => $book['maincat_title'],
                ],
                'category' => [
                    'code' => $book['code_category'],
                    'title' => $book['category_title'],
                ],
                'notes' => $book['notes'] ?: null,
                'cover_image' => $book['cover_image'] ?: null,
                'created_at' => $book['created_at'],
                'updated_at' => $book['updated_at'],
            ];
        }

        $filename = 'books_' . date('Y-m-d_His') . '.json';
        exportJSON($jsonData, $filename);

    } elseif ($format === 'pdf') {
        // PDF = Print-friendly HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Books Export - <?= date('Y-m-d H:i:s') ?></title>
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
                .tag {
                    font-family: monospace;
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

            <h1>Books Export</h1>
            <div class="meta">
                Export Date: <?= date('Y-m-d H:i:s') ?><br>
                Total Books: <?= count($books) ?>
                <?php if (!empty($search) || !empty($categoryFilter) || !empty($languageFilter)): ?>
                    <br>Filtered Results
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Title</th>
                        <th>Authors</th>
                        <th>Year</th>
                        <th>Category</th>
                        <th>Language</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td class="tag"><?= generateBookTag($book['maincat_code'], $book['code_category'], $book['number_in_category']) ?></td>
                            <td>
                                <strong><?= e($book['title']) ?></strong>
                                <?php if ($book['isbn']): ?>
                                    <br><small>ISBN: <?= e($book['isbn']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($book['authors'] ?: '-') ?></td>
                            <td><?= formatYear($book['year']) ?></td>
                            <td>
                                <?= e($book['maincat_title']) ?><br>
                                <small><?= e($book['category_title']) ?></small>
                            </td>
                            <td><?= e($book['language'] ?: '-') ?></td>
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

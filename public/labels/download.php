<?php
/**
 * Label Download Endpoint
 * Generates ZPL files for label printing
 */

$app = require __DIR__ . '/../../src/bootstrap.php';
extract($app);

requireAuth();

// Get parameters
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

// Validate type
$validTypes = ['category', 'spine', 'full', 'dual'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    die('Invalid label type. Must be: category, spine, full, or dual');
}

// Check if labels are enabled
if (!$config['labels']['enabled']) {
    http_response_code(503);
    die('Label printing is disabled. Enable it in config.php');
}

try {
    $zpl = '';
    $filename = '';

    switch ($type) {
        case 'category':
            // Get category data
            if (empty($id)) {
                throw new Exception('Category ID is required');
            }

            // Parse composite key: "CODE_MAINCODE" (e.g., "PH_SF")
            if (strpos($id, '_') === false) {
                throw new Exception('Invalid category ID format');
            }

            [$code, $mainCode] = explode('_', $id, 2);

            $stmt = $db->prepare('
                SELECT c.*, mc.code as maincat_code, mc.title as maincat_title
                FROM categories c
                JOIN maincategories mc ON c.code_maincategory = mc.code
                WHERE c.code = ? AND c.code_maincategory = ?
            ');
            $stmt->execute([$code, $mainCode]);
            $category = $stmt->fetch();

            if (!$category) {
                throw new Exception('Category not found');
            }

            $zpl = generateCategoryLabel(
                $category['maincat_code'],
                $category['code'],
                $category['title'],
                $config['labels']
            );

            $filename = "category_{$category['maincat_code']}_{$category['code']}.zpl";
            break;

        case 'spine':
        case 'full':
        case 'dual':
            // Get book data
            if (empty($id)) {
                throw new Exception('Book ID is required');
            }

            $stmt = $db->prepare('
                SELECT b.*,
                       c.code as category_code,
                       mc.code as maincat_code,
                       GROUP_CONCAT(CONCAT(a.surname, " ", a.lastname) SEPARATOR ", ") as authors
                FROM books b
                JOIN categories c ON b.code_category = c.code AND b.code_maincategory = c.code_maincategory
                JOIN maincategories mc ON c.code_maincategory = mc.code
                LEFT JOIN book_author ba ON b.id = ba.book_id
                LEFT JOIN authors a ON ba.author_id = a.id
                WHERE b.id = ?
                GROUP BY b.id
            ');
            $stmt->execute([$id]);
            $book = $stmt->fetch();

            if (!$book) {
                throw new Exception('Book not found');
            }

            // Generate appropriate label(s)
            if ($type === 'spine') {
                $zpl = generateSpineLabel(
                    $book['maincat_code'],
                    $book['category_code'],
                    $book['number_in_category'],
                    $config['labels']
                );
            } elseif ($type === 'full') {
                $zpl = generateFullBookLabel(
                    $book['maincat_code'],
                    $book['category_code'],
                    $book['number_in_category'],
                    $book['authors'] ?? 'Unknown',
                    $book['title'],
                    $config['labels']
                );
            } else { // dual
                $zpl = generateDualBookLabel(
                    $book['maincat_code'],
                    $book['category_code'],
                    $book['number_in_category'],
                    $book['authors'] ?? 'Unknown',
                    $book['title'],
                    $config['labels']
                );
            }

            $bookTag = generateBookTag($book['maincat_code'], $book['category_code'], $book['number_in_category']);
            $bookTag = str_replace(' ', '_', $bookTag);
            $filename = "book_{$bookTag}_{$type}.zpl";
            break;
    }

    // Send ZPL file for download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($zpl));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $zpl;
    exit;

} catch (Exception $e) {
    http_response_code(400);
    die('Error generating label: ' . htmlspecialchars($e->getMessage()));
}

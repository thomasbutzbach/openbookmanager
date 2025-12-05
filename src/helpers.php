<?php
/**
 * Helper Functions
 */

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isAuthenticated()) {
        redirect('/login.php');
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Set flash message
 */
function setFlash($type, $message, $allowHtml = false) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'allow_html' => $allowHtml
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate book tag (e.g., "WR PH 0042")
 */
function generateBookTag($mainCategoryCode, $categoryCode, $numberInCategory) {
    return sprintf('%s %s %04d', $mainCategoryCode, $categoryCode, $numberInCategory);
}

/**
 * Format year for display (handle null)
 */
function formatYear($year) {
    return $year ? (string)$year : '-';
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Format date
 */
function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'd.m.Y H:i') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Debug helper (only in debug mode)
 */
function dd($var) {
    global $config;
    if ($config['app']['debug']) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    global $config;
    return rtrim($config['app']['url'], '/') . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset($path) {
    return '/' . ltrim($path, '/');
}

/**
 * Calculate pagination data
 *
 * @param int $totalItems Total number of items
 * @param int $currentPage Current page number (1-based)
 * @param int $itemsPerPage Number of items per page
 * @return array Pagination data (offset, limit, totalPages, currentPage, hasNext, hasPrev)
 */
function getPaginationData($totalItems, $currentPage, $itemsPerPage) {
    $currentPage = max(1, (int)$currentPage); // Ensure at least page 1
    $totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));

    // Ensure current page is within valid range
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $itemsPerPage;

    return [
        'offset' => $offset,
        'limit' => $itemsPerPage,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
        'hasNext' => $currentPage < $totalPages,
        'hasPrev' => $currentPage > 1,
        'totalItems' => $totalItems,
    ];
}

/**
 * Render pagination controls
 *
 * @param array $paginationData Data from getPaginationData()
 * @param string $baseUrl Base URL for pagination links (will append ?page=X or &page=X)
 * @return string HTML for pagination controls
 */
function renderPagination($paginationData, $baseUrl) {
    if ($paginationData['totalPages'] <= 1) {
        return ''; // No pagination needed for single page
    }

    $currentPage = $paginationData['currentPage'];
    $totalPages = $paginationData['totalPages'];

    // Determine if we need to append ? or &
    $separator = strpos($baseUrl, '?') === false ? '?' : '&';

    $html = '<div class="pagination">';

    // Previous button
    if ($paginationData['hasPrev']) {
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . ($currentPage - 1)) . '" class="pagination-item">&laquo; Previous</a>';
    } else {
        $html .= '<span class="pagination-item pagination-disabled">&laquo; Previous</span>';
    }

    // Page numbers with smart ellipsis
    $range = 2; // Show 2 pages on each side of current

    for ($i = 1; $i <= $totalPages; $i++) {
        // Always show first page, last page, and pages around current
        $showPage = ($i == 1) ||
                    ($i == $totalPages) ||
                    ($i >= $currentPage - $range && $i <= $currentPage + $range);

        if ($showPage) {
            if ($i == $currentPage) {
                $html .= '<span class="pagination-item pagination-active">' . $i . '</span>';
            } else {
                $html .= '<a href="' . e($baseUrl . $separator . 'page=' . $i) . '" class="pagination-item">' . $i . '</a>';
            }
            $lastShown = $i;
        } else if (isset($lastShown) && $lastShown == $i - 1) {
            // Show ellipsis for gaps
            $html .= '<span class="pagination-item pagination-ellipsis">...</span>';
        }
    }

    // Next button
    if ($paginationData['hasNext']) {
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . ($currentPage + 1)) . '" class="pagination-item">Next &raquo;</a>';
    } else {
        $html .= '<span class="pagination-item pagination-disabled">Next &raquo;</span>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Export data as CSV
 *
 * @param array $data Array of associative arrays (rows)
 * @param string $filename Filename for download
 * @param array $headers Optional custom headers (uses array keys if not provided)
 */
function exportCSV($data, $filename, $headers = null) {
    if (empty($data)) {
        return;
    }

    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write headers
    if ($headers === null) {
        $headers = array_keys($data[0]);
    }
    fputcsv($output, $headers);

    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

/**
 * Export data as JSON
 *
 * @param array $data Data to export
 * @param string $filename Filename for download
 */
function exportJSON($data, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get application version from VERSION file
 *
 * @return string Version string (e.g., "1.0.0")
 */
function getAppVersion() {
    $versionFile = __DIR__ . '/../VERSION';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return '0.0.0'; // Fallback if file doesn't exist
}

/**
 * Get database version from system_info table
 *
 * @param PDO $db Database connection
 * @return string|null Version string or null if not found
 */
function getDbVersion($db) {
    try {
        $stmt = $db->prepare("SELECT value FROM system_info WHERE `key` = 'version'");
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result && $result['value']) {
            $versionData = json_decode($result['value'], true);
            return $versionData['version'] ?? null;
        }
    } catch (PDOException $e) {
        // Table might not exist yet
        return null;
    }

    return null;
}

/**
 * Compare two semantic version strings
 *
 * @param string $version1 First version (e.g., "1.2.0")
 * @param string $version2 Second version (e.g., "1.1.0")
 * @return int Returns -1 if v1 < v2, 0 if equal, 1 if v1 > v2
 */
function compareVersions($version1, $version2) {
    return version_compare($version1, $version2);
}

/**
 * Check if update is available
 *
 * @param PDO $db Database connection
 * @return array|null Returns ['current' => '1.0.0', 'available' => '1.1.0'] or null if no update
 */
function checkUpdateAvailable($db) {
    $appVersion = getAppVersion();
    $dbVersion = getDbVersion($db);

    if ($dbVersion === null) {
        // Database not initialized
        return null;
    }

    if (compareVersions($appVersion, $dbVersion) > 0) {
        return [
            'current' => $dbVersion,
            'available' => $appVersion,
        ];
    }

    return null;
}

/**
 * Fetch book information from Google Books API by ISBN
 *
 * @param string $isbn ISBN-10 or ISBN-13
 * @return array|null Book data or null if not found
 */
function fetchBookByISBN($isbn) {
    // Clean ISBN (remove dashes, spaces)
    $isbn = preg_replace('/[^0-9X]/i', '', $isbn);

    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn);

    // Set timeout and error handling
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    if (!isset($data['totalItems']) || $data['totalItems'] == 0) {
        return null;
    }

    $volumeInfo = $data['items'][0]['volumeInfo'] ?? [];

    // Extract published year from date string (format: YYYY-MM-DD or YYYY)
    $publishedYear = null;
    if (isset($volumeInfo['publishedDate'])) {
        $publishedYear = (int)substr($volumeInfo['publishedDate'], 0, 4);
    }

    // Get the best available cover image
    $coverUrl = null;
    if (isset($volumeInfo['imageLinks'])) {
        // Prefer larger images
        if (isset($volumeInfo['imageLinks']['large'])) {
            $coverUrl = $volumeInfo['imageLinks']['large'];
        } elseif (isset($volumeInfo['imageLinks']['medium'])) {
            $coverUrl = $volumeInfo['imageLinks']['medium'];
        } elseif (isset($volumeInfo['imageLinks']['small'])) {
            $coverUrl = $volumeInfo['imageLinks']['small'];
        } elseif (isset($volumeInfo['imageLinks']['thumbnail'])) {
            $coverUrl = $volumeInfo['imageLinks']['thumbnail'];
        } elseif (isset($volumeInfo['imageLinks']['smallThumbnail'])) {
            $coverUrl = $volumeInfo['imageLinks']['smallThumbnail'];
        }

        // Force HTTPS
        if ($coverUrl) {
            $coverUrl = str_replace('http://', 'https://', $coverUrl);
        }
    }

    // Try Open Library for better cover images first
    $openLibraryCover = getOpenLibraryCover($isbn);
    $finalCoverUrl = $openLibraryCover ?: $coverUrl;

    return [
        'isbn' => $isbn,
        'title' => $volumeInfo['title'] ?? '',
        'subtitle' => $volumeInfo['subtitle'] ?? null,
        'authors' => $volumeInfo['authors'] ?? [],
        'published_year' => $publishedYear,
        'publisher' => $volumeInfo['publisher'] ?? null,
        'pages' => $volumeInfo['pageCount'] ?? null,
        'language' => $volumeInfo['language'] ?? null,
        'description' => $volumeInfo['description'] ?? null,
        'cover_url' => $finalCoverUrl,
        'cover_source' => $openLibraryCover ? 'openlibrary' : ($coverUrl ? 'google' : null),
    ];
}

/**
 * Check if Open Library has a cover for this ISBN
 *
 * @param string $isbn ISBN to check
 * @return string|null Cover URL if available, null otherwise
 */
function getOpenLibraryCover($isbn) {
    $isbn = preg_replace('/[^0-9X]/i', '', $isbn);
    $coverUrl = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";

    // Perform HEAD request to check if cover exists
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);

    $headers = @get_headers($coverUrl, 1, $context);

    if ($headers === false) {
        return null;
    }

    // Check if we got a 200 OK response
    if (isset($headers[0]) && strpos($headers[0], '200') !== false) {
        return $coverUrl;
    }

    return null;
}

/**
 * Download book cover from URL and save locally
 *
 * @param string $coverUrl URL of the cover image
 * @param string $isbn ISBN for filename
 * @return string|null Relative path to local file or null on failure
 */
function downloadBookCover($coverUrl, $isbn) {
    if (empty($coverUrl)) {
        return null;
    }

    $uploadsDir = __DIR__ . '/../public/uploads/covers/';

    // Create directory if it doesn't exist
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            return null;
        }
    }

    // Determine file extension
    $parsedUrl = parse_url($coverUrl);
    $pathInfo = pathinfo($parsedUrl['path'] ?? '');
    $extension = $pathInfo['extension'] ?? 'jpg';

    // Validate extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($extension), $allowedExtensions)) {
        $extension = 'jpg';
    }

    // Generate filename
    $filename = 'isbn_' . preg_replace('/[^0-9X]/i', '', $isbn) . '.' . $extension;
    $localPath = $uploadsDir . $filename;
    $relativePath = '/uploads/covers/' . $filename;

    // Download image with timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ]
    ]);

    $imageData = @file_get_contents($coverUrl, false, $context);

    if ($imageData === false || empty($imageData)) {
        return null;
    }

    // Save to file
    if (file_put_contents($localPath, $imageData) === false) {
        return null;
    }

    return $relativePath;
}

/**
 * Parse author name string into surname and lastname
 * Handles formats like "Firstname Lastname" or "Lastname, Firstname"
 *
 * @param string $authorName Full author name
 * @return array ['surname' => string, 'lastname' => string]
 */
function parseAuthorName($authorName) {
    $authorName = trim($authorName);

    // Check if format is "Lastname, Firstname"
    if (strpos($authorName, ',') !== false) {
        $parts = array_map('trim', explode(',', $authorName, 2));
        return [
            'surname' => $parts[1] ?? '',
            'lastname' => $parts[0] ?? ''
        ];
    }

    // Otherwise assume "Firstname Lastname" or "Firstname Middle Lastname"
    $parts = explode(' ', $authorName);

    if (count($parts) === 1) {
        // Only one name - treat as lastname
        return [
            'surname' => '',
            'lastname' => $parts[0]
        ];
    }

    // Last part is lastname, everything else is surname
    $lastname = array_pop($parts);
    $surname = implode(' ', $parts);

    return [
        'surname' => $surname,
        'lastname' => $lastname
    ];
}

/**
 * Find existing author by name or return null
 *
 * @param PDO $db Database connection
 * @param string $surname Surname/First name
 * @param string $lastname Last name
 * @return array|null Author record or null
 */
function findAuthorByName($db, $surname, $lastname) {
    $stmt = $db->prepare('
        SELECT * FROM authors
        WHERE LOWER(surname) = LOWER(?) AND LOWER(lastname) = LOWER(?)
        LIMIT 1
    ');
    $stmt->execute([$surname, $lastname]);
    return $stmt->fetch() ?: null;
}

/**
 * Parse authors string (comma-separated) and match against existing authors
 *
 * @param PDO $db Database connection
 * @param string $authorsRaw Comma-separated author names
 * @return array Array of parsed authors with matching info
 */
function parseAndMatchAuthors($db, $authorsRaw) {
    if (empty($authorsRaw)) {
        return [];
    }

    $authorNames = array_map('trim', explode(',', $authorsRaw));
    $result = [];

    foreach ($authorNames as $name) {
        if (empty($name)) continue;

        $parsed = parseAuthorName($name);
        $existing = findAuthorByName($db, $parsed['surname'], $parsed['lastname']);

        $result[] = [
            'original' => $name,
            'surname' => $parsed['surname'],
            'lastname' => $parsed['lastname'],
            'existing_id' => $existing ? $existing['id'] : null,
            'is_new' => $existing === null
        ];
    }

    return $result;
}

/**
 * ============================================================================
 * LABEL PRINTING HELPERS
 * ============================================================================
 */

/**
 * Sanitize text for ZPL output
 * Removes special characters that could break ZPL syntax
 */
function sanitizeZPL($text) {
    // Replace problematic characters
    $text = str_replace(['^', '~', '\\'], '', $text);
    // Limit length and trim
    return trim(substr($text, 0, 100));
}

/**
 * Convert millimeters to dots based on DPI
 */
function mmToDots($mm, $dpi = 203) {
    return round(($mm / 25.4) * $dpi);
}

/**
 * Generate ZPL for category label (shelf label)
 * Shows: Category Code + Full Title (no barcode)
 *
 * @param string $mainCategoryCode Main category code (e.g., "SF")
 * @param string $categoryCode Subcategory code (e.g., "PH")
 * @param string $categoryTitle Full category title
 * @param array $config Label configuration
 * @return string ZPL code
 */
function generateCategoryLabel($mainCategoryCode, $categoryCode, $categoryTitle, $config) {
    $mainCategoryCode = sanitizeZPL($mainCategoryCode);
    $categoryCode = sanitizeZPL($categoryCode);
    $categoryTitle = sanitizeZPL($categoryTitle);

    // Combined code display
    $displayCode = $mainCategoryCode . ' ' . $categoryCode;

    // Convert dimensions
    $width = mmToDots($config['width_mm'], $config['dpi']);
    $height = mmToDots($config['height_mm'], $config['dpi']);

    // Font sizes from config
    $codeSize = $config['category_label']['code_size'];
    $titleSize = $config['category_label']['title_size'];

    // Word wrap title for 38mm width (approx. 22-24 chars per line at size 25)
    $maxCharsPerLine = 23;
    $titleLines = [];

    if (strlen($categoryTitle) <= $maxCharsPerLine) {
        $titleLines[] = $categoryTitle;
    } else {
        // Split at word boundaries if possible
        $words = explode(' ', $categoryTitle);
        $currentLine = '';

        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $maxCharsPerLine) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $titleLines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $titleLines[] = $currentLine;
        }

        // Limit to 3 lines max
        $titleLines = array_slice($titleLines, 0, 3);
    }

    // Build ZPL
    $zpl = "^XA\n";
    $zpl .= "^PW{$width}\n";
    $zpl .= "^LL{$height}\n";

    // Print settings
    $zpl .= "^MD{$config['darkness']}\n";
    $zpl .= "^PR{$config['print_speed']}\n";

    // Use Unicode encoding for umlauts (UTF-8)
    $zpl .= "^CI28\n";  // UTF-8 encoding

    // Ensure exactly 3 title lines for consistent layout (fill with empty lines)
    while (count($titleLines) < 3) {
        $titleLines[] = '';
    }

    // Calculate vertical centering (always: code + 3 title lines)
    // Add top margin to prevent content from being cut off at the top edge
    $topMargin = 20;  // Minimum margin from top edge in dots (~2.5mm at 203 DPI)
    $lineSpacing = $titleSize + 2;
    $totalContentHeight = $codeSize + 6 + (3 * $lineSpacing);  // Code + gap + 3 title lines
    $verticalOffset = ($height - $totalContentHeight) / 2;

    $codeY = max($topMargin, round($verticalOffset));  // Ensure minimum top margin
    $titleStartY = $codeY + $codeSize + 6;  // 6 pixels gap between code and title

    // Category code (centered horizontally and vertically) - optimized for 38x19mm
    $blockWidth = $width - 10;  // Full width minus margins
    $zpl .= "^FO5,{$codeY}^A0N,{$codeSize},{$codeSize}^FB{$blockWidth},1,0,C,0^FD{$displayCode}^FS\n";

    // Category title (always 3 lines, centered) - optimized for 38x19mm
    foreach ($titleLines as $index => $line) {
        $yPos = $titleStartY + ($index * $lineSpacing);
        $zpl .= "^FO5,{$yPos}^A0N,{$titleSize},{$titleSize}^FB{$blockWidth},1,0,C,0^FD{$line}^FS\n";
    }

    $zpl .= "^XZ";

    return $zpl;
}

/**
 * Generate ZPL for spine label (minimal book label for spine)
 * Shows: Book Tag + Barcode
 *
 * @param string $mainCategoryCode Main category code
 * @param string $categoryCode Subcategory code
 * @param int $number Book number in category
 * @param array $config Label configuration
 * @return string ZPL code
 */
function generateSpineLabel($mainCategoryCode, $categoryCode, $number, $config) {
    $mainCategoryCode = sanitizeZPL($mainCategoryCode);
    $categoryCode = sanitizeZPL($categoryCode);

    // Display format: "MC SU 0001"
    $displayTag = generateBookTag($mainCategoryCode, $categoryCode, $number);

    // Barcode format: "MCSU0001" (no spaces/special chars)
    $barcodeData = $mainCategoryCode . $categoryCode . str_pad($number, 4, '0', STR_PAD_LEFT);

    // Convert dimensions
    $width = mmToDots($config['width_mm'], $config['dpi']);
    $height = mmToDots($config['height_mm'], $config['dpi']);

    // Font sizes from config
    $tagSize = $config['spine_label']['tag_size'];
    $barcodeHeight = $config['spine_label']['barcode_height'];

    // Build ZPL
    $zpl = "^XA\n";
    $zpl .= "^PW{$width}\n";
    $zpl .= "^LL{$height}\n";

    // Print settings
    $zpl .= "^MD{$config['darkness']}\n";
    $zpl .= "^PR{$config['print_speed']}\n";
    $zpl .= "^CI28\n";  // UTF-8 encoding for umlauts

    // Calculate vertical centering for spine label
    $totalContentHeight = $tagSize + 6 + $barcodeHeight;
    $verticalOffset = ($height - $totalContentHeight) / 2;

    $tagY = max(3, round($verticalOffset));
    $barcodeY = $tagY + $tagSize + 6;

    // Book tag (human readable, centered) - optimized for 38x19mm
    $blockWidth = $width - 10;
    $zpl .= "^FO5,{$tagY}^A0N,{$tagSize},{$tagSize}^FB{$blockWidth},1,0,C,0^FD{$displayTag}^FS\n";

    // Barcode (Code 128, centered) - optimized for 38x19mm
    // Calculate approximate barcode width and center it manually
    // Code 128: ~11 modules per character, module width = 2
    $barcodeLength = strlen($barcodeData);
    $moduleWidth = 2;
    $estimatedBarcodeWidth = ($barcodeLength * 11 + 35) * $moduleWidth;  // +35 for start/stop/checksum
    $barcodeX = round(($width - $estimatedBarcodeWidth) / 2);
    $barcodeX = max(5, $barcodeX);  // Ensure minimum margin

    $zpl .= "^BY{$moduleWidth},{$moduleWidth},{$barcodeHeight}\n";
    $zpl .= "^FO{$barcodeX},{$barcodeY}^BCN,{$barcodeHeight},N,N,N^FD{$barcodeData}^FS\n";

    $zpl .= "^XZ";

    return $zpl;
}

/**
 * Generate ZPL for full book label (back cover label)
 * Shows: Book Tag + Barcode + Author + Title
 *
 * @param string $mainCategoryCode Main category code
 * @param string $categoryCode Subcategory code
 * @param int $number Book number in category
 * @param string $author Author name(s)
 * @param string $title Book title
 * @param array $config Label configuration
 * @return string ZPL code
 */
function generateFullBookLabel($mainCategoryCode, $categoryCode, $number, $author, $title, $config) {
    $mainCategoryCode = sanitizeZPL($mainCategoryCode);
    $categoryCode = sanitizeZPL($categoryCode);
    $author = sanitizeZPL($author);
    $title = sanitizeZPL($title);

    // Display format: "MC SU 0001"
    $displayTag = generateBookTag($mainCategoryCode, $categoryCode, $number);

    // Barcode format: "MCSU0001"
    $barcodeData = $mainCategoryCode . $categoryCode . str_pad($number, 4, '0', STR_PAD_LEFT);

    // Truncate author and title to fit
    $authorShort = substr($author, 0, 30);
    $titleShort = substr($title, 0, 32);

    // Convert dimensions
    $width = mmToDots($config['width_mm'], $config['dpi']);
    $height = mmToDots($config['height_mm'], $config['dpi']);

    // Font sizes from config
    $tagSize = $config['full_label']['tag_size'];
    $barcodeHeight = $config['full_label']['barcode_height'];
    $authorSize = $config['full_label']['author_size'];
    $titleSize = $config['full_label']['title_size'];

    // Build ZPL
    $zpl = "^XA\n";
    $zpl .= "^PW{$width}\n";
    $zpl .= "^LL{$height}\n";

    // Print settings
    $zpl .= "^MD{$config['darkness']}\n";
    $zpl .= "^PR{$config['print_speed']}\n";
    $zpl .= "^CI28\n";  // UTF-8 encoding for umlauts

    // Calculate vertical centering for full label (with larger gaps)
    $totalContentHeight = $tagSize + 5 + $barcodeHeight + 5 + $authorSize + 4 + $titleSize;
    $verticalOffset = ($height - $totalContentHeight) / 2;

    $tagY = max(3, round($verticalOffset));
    $barcodeY = $tagY + $tagSize + 5;
    $authorY = $barcodeY + $barcodeHeight + 5;
    $titleY = $authorY + $authorSize + 4;

    $blockWidth = $width - 10;

    // Book tag (human readable, centered) - optimized for 38x19mm
    $zpl .= "^FO5,{$tagY}^A0N,{$tagSize},{$tagSize}^FB{$blockWidth},1,0,C,0^FD{$displayTag}^FS\n";

    // Barcode (Code 128, centered) - optimized for 38x19mm
    // Calculate approximate barcode width and center it manually
    $barcodeLength = strlen($barcodeData);
    $moduleWidth = 2;
    $estimatedBarcodeWidth = ($barcodeLength * 11 + 35) * $moduleWidth;  // +35 for start/stop/checksum
    $barcodeX = round(($width - $estimatedBarcodeWidth) / 2);
    $barcodeX = max(5, $barcodeX);  // Ensure minimum margin

    $zpl .= "^BY{$moduleWidth},{$moduleWidth},{$barcodeHeight}\n";
    $zpl .= "^FO{$barcodeX},{$barcodeY}^BCN,{$barcodeHeight},N,N,N^FD{$barcodeData}^FS\n";

    // Author (centered) - optimized for 38x19mm
    $zpl .= "^FO5,{$authorY}^A0N,{$authorSize},{$authorSize}^FB{$blockWidth},1,0,C,0^FD{$authorShort}^FS\n";

    // Title (centered) - optimized for 38x19mm
    $zpl .= "^FO5,{$titleY}^A0N,{$titleSize},{$titleSize}^FB{$blockWidth},1,0,C,0^FD{$titleShort}^FS\n";

    $zpl .= "^XZ";

    return $zpl;
}

/**
 * Generate dual label (spine + full) in one ZPL output
 * Useful for printing both labels in sequence
 */
function generateDualBookLabel($mainCategoryCode, $categoryCode, $number, $author, $title, $config) {
    $spineLabel = generateSpineLabel($mainCategoryCode, $categoryCode, $number, $config);
    $fullLabel = generateFullBookLabel($mainCategoryCode, $categoryCode, $number, $author, $title, $config);

    return $spineLabel . "\n" . $fullLabel;
}

// ============================================================================
// Document Upload Functions
// ============================================================================

/**
 * Get the documents upload directory path
 */
function getDocumentsDir(): string {
    return __DIR__ . '/../public/uploads/documents/';
}

/**
 * Get allowed document MIME types
 */
function getAllowedDocumentMimeTypes(): array {
    return [
        'pdf' => 'application/pdf',
        'epub' => 'application/epub+zip',
    ];
}

/**
 * Get maximum document size in bytes
 */
function getMaxDocumentSize($config): int {
    $maxMb = $config['documents']['max_size_mb'] ?? 100;
    return $maxMb * 1024 * 1024;
}

/**
 * Validate and upload a book document (PDF or EPUB)
 *
 * @param array $file The $_FILES['document'] array
 * @param int $bookId The book ID for naming the file
 * @param array $config Application config
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function uploadBookDocument(array $file, int $bookId, array $config): array {
    // Check if documents feature is enabled
    if (!($config['documents']['enabled'] ?? true)) {
        return ['success' => false, 'path' => null, 'error' => 'Document uploads are disabled.'];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the upload_max_filesize directive.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        ];
        $error = $errorMessages[$file['error']] ?? 'Unknown upload error.';
        return ['success' => false, 'path' => null, 'error' => $error];
    }

    // Check file size
    $maxSize = getMaxDocumentSize($config);
    if ($file['size'] > $maxSize) {
        $maxMb = $config['documents']['max_size_mb'] ?? 100;
        return ['success' => false, 'path' => null, 'error' => "File exceeds maximum size of {$maxMb} MB."];
    }

    // Get file extension
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Validate extension
    $allowedTypes = $config['documents']['allowed_types'] ?? ['pdf', 'epub'];
    if (!in_array($extension, $allowedTypes)) {
        $allowed = implode(', ', $allowedTypes);
        return ['success' => false, 'path' => null, 'error' => "Invalid file type. Allowed: {$allowed}."];
    }

    // Validate MIME type
    $allowedMimes = getAllowedDocumentMimeTypes();
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $expectedMime = $allowedMimes[$extension] ?? null;
    if ($mimeType !== $expectedMime) {
        return ['success' => false, 'path' => null, 'error' => 'File content does not match its extension.'];
    }

    // Ensure upload directory exists
    $uploadsDir = getDocumentsDir();
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to create upload directory.'];
        }
    }

    // Generate filename based on book ID
    $filename = $bookId . '.' . $extension;
    $fullPath = $uploadsDir . $filename;
    $relativePath = '/uploads/documents/' . $filename;

    // Delete existing document if present (different extension)
    deleteBookDocumentByBookId($bookId);

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'path' => $relativePath, 'error' => null];
}

/**
 * Delete a book document by its relative path
 *
 * @param string|null $relativePath The relative path stored in database
 * @return bool True if deleted or didn't exist, false on error
 */
function deleteBookDocument(?string $relativePath): bool {
    if (empty($relativePath)) {
        return true;
    }

    // Security: ensure path is within documents directory
    if (strpos($relativePath, '/uploads/documents/') !== 0) {
        return false;
    }

    $filename = basename($relativePath);
    $fullPath = getDocumentsDir() . $filename;

    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }

    return true;
}

/**
 * Delete any existing document for a book ID (checks both pdf and epub)
 *
 * @param int $bookId The book ID
 * @return bool True if deleted or didn't exist
 */
function deleteBookDocumentByBookId(int $bookId): bool {
    $uploadsDir = getDocumentsDir();
    $extensions = ['pdf', 'epub'];

    foreach ($extensions as $ext) {
        $path = $uploadsDir . $bookId . '.' . $ext;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    return true;
}

/**
 * Get the MIME type for a document based on its path
 *
 * @param string $relativePath The relative path to the document
 * @return string|null The MIME type or null if unknown
 */
function getDocumentMimeType(string $relativePath): ?string {
    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    $mimeTypes = getAllowedDocumentMimeTypes();

    return $mimeTypes[$extension] ?? null;
}

/**
 * Get a human-readable label for format type
 *
 * @param string $formatType The format type (physical, digital, both)
 * @return string Human-readable label
 */
function formatTypeLabel(string $formatType): string {
    $labels = [
        'physical' => 'Physical Book',
        'digital' => 'Digital Only',
        'both' => 'Physical & Digital',
    ];

    return $labels[$formatType] ?? 'Unknown';
}

/**
 * Get an icon for format type
 *
 * @param string $formatType The format type (physical, digital, both)
 * @return string Emoji icon
 */
function formatTypeIcon(string $formatType): string {
    $icons = [
        'physical' => '',
        'digital' => '💾',
        'both' => '💾',
    ];

    return $icons[$formatType] ?? '';
}

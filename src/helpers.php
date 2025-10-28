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
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
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

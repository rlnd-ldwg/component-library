<?php
// Central error handling: log errors, never show them to the browser.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Credentials: prefer the file outside the web root, fall back to a local config.php
$externalConfig = '/etc/components/config.php';
require_once is_readable($externalConfig) ? $externalConfig : __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/** Escape LIKE wildcards so that "_" and "%" are searched literally. */
function likeEscape(string $s): string {
    return addcslashes($s, '\\%_');
}

/**
 * Build the WHERE clause for the list and the export from GET parameters.
 * Returns [sqlFragment, params].
 */
function buildFilter(array $src): array {
    $where  = [];
    $params = [];

    $category = trim((string)($src['category'] ?? ''));
    $location = trim((string)($src['location'] ?? ''));
    $search   = trim((string)($src['search']   ?? ''));

    if ($category !== '') {
        $where[] = 'category = :category';
        $params[':category'] = $category;
    }
    if ($location !== '') {
        $where[] = 'location LIKE :location';
        $params[':location'] = '%' . likeEscape($location) . '%';
    }
    if ($search !== '') {
        $where[] = '(part_type LIKE :s1 OR description LIKE :s2 OR part_value LIKE :s3)';
        $like = '%' . likeEscape($search) . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
    }

    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

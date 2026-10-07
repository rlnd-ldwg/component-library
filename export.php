<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');   // <- auf '0' statt '1' für die Produktion

require_once __DIR__ . '/db.php';

$db = getDB();

// Optional: nur gefilterte Daten exportieren
$where = [];
$params = [];

if (!empty($_GET['category'])) {
    $where[] = 'category = :category';
    $params[':category'] = $_GET['category'];
}
if (!empty($_GET['location'])) {
    $where[] = 'location LIKE :location';
    $params[':location'] = '%' . $_GET['location'] . '%';
}
if (!empty($_GET['search'])) {
    $where[] = '(part_type LIKE :s1 OR description LIKE :s2 OR part_value LIKE :s3)';
    $params[':s1'] = '%' . $_GET['search'] . '%';
    $params[':s2'] = '%' . $_GET['search'] . '%';
    $params[':s3'] = '%' . $_GET['search'] . '%';
}

$sql = 'SELECT id, category, part_type, description, part_value, footprint,
               mounting, quantity, location, manufacturer, unit_price, notes,
               created_at, updated_at
        FROM components';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY category, part_type';

$stmt = $db->prepare($sql);
$stmt->execute($params);

// Dateiname mit Datum
$filename = 'bauteile_' . date('Y-m-d_His') . '.csv';

// Eventuell gepufferte Ausgabe verwerfen, bevor Header gesendet werden
if (ob_get_level()) {
    ob_end_clean();
}

// Header für Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Ausgabe
$out = fopen('php://output', 'w');

// BOM für Excel/LibreCalc, damit UTF-8 korrekt erkannt wird
fwrite($out, "\xEF\xBB\xBF");

// Kopfzeile
fputcsv($out, [
    'id', 'category', 'part_type', 'description', 'part_value', 'footprint',
    'mounting', 'quantity', 'location', 'manufacturer', 'unit_price', 'notes',
    'created_at', 'updated_at'
], ';', '"', '\\');

// Datenzeilen
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row, ';', '"', '\\');
}

fclose($out);
exit;

<?php
require_once __DIR__ . '/db.php';

$db = getDB();

// Optional: export only the filtered data
[$whereSql, $params] = buildFilter($_GET);

$sql = 'SELECT id, category, part_type, description, part_value, footprint,
               mounting, quantity, location, manufacturer, unit_price, notes,
               created_at, updated_at
        FROM components' . $whereSql . ' ORDER BY category, part_type';

$stmt = $db->prepare($sql);
$stmt->execute($params);

// File name with date
$filename = 'components_' . date('Y-m-d_His') . '.csv';

// Discard any buffered output before sending headers
while (ob_get_level()) {
    ob_end_clean();
}

// Download headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM so that Excel/LibreCalc detect UTF-8
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'id', 'category', 'part_type', 'description', 'part_value', 'footprint',
    'mounting', 'quantity', 'location', 'manufacturer', 'unit_price', 'notes',
    'created_at', 'updated_at'
], ';', '"', '\\');

// Data rows
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row, ';', '"', '\\');
}

fclose($out);
exit;

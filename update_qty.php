<?php
require_once __DIR__ . '/db.php';

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$id  = (int)($_POST['id'] ?? 0);
$qty = $_POST['quantity'] ?? null;
$delta = isset($_POST['delta']) ? (int)$_POST['delta'] : null;

if ($id <= 0) {
    http_response_code(400);
    exit('Ungültige ID');
}

$db = getDB();

// Aktuellen Wert holen
$stmt = $db->prepare('SELECT quantity FROM components WHERE id = :id');
$stmt->execute([':id' => $id]);
$current = $stmt->fetchColumn();

if ($current === false) {
    http_response_code(404);
    exit('Bauteil nicht gefunden');
}

// Neue Menge bestimmen
if ($delta !== null) {
    $new = max(0, (int)$current + $delta);
} elseif ($qty !== null) {
    $new = max(0, (int)$qty);
} else {
    http_response_code(400);
    exit('Keine Menge angegeben');
}

// Update
$stmt = $db->prepare('UPDATE components SET quantity = :q WHERE id = :id');
$stmt->execute([':q' => $new, ':id' => $id]);

// JSON-Antwort (für AJAX)
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'id' => $id, 'quantity' => $new]);

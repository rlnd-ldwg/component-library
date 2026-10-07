<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Ungültige ID');
}

// Datensatz laden (zum Anzeigen, was gelöscht würde)
$stmt = $db->prepare('SELECT * FROM components WHERE id = :id');
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();

if (!$c) {
    exit('Bauteil nicht gefunden');
}

// Löschen erst nach Bestätigung (POST)
$deleted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    $stmt = $db->prepare('DELETE FROM components WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $deleted = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bauteil löschen</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #f5f5f5; }
        h1 { color: #c53030; }
        .box { background: #fff; padding: 1.5rem; border-radius: 6px; max-width: 600px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .box p { margin: 0.4rem 0; }
        .actions { margin-top: 1.5rem; display: flex; gap: 1rem; }
        .btn-danger { background: #c53030; color: #fff; padding: 0.6rem 1.2rem; border: none; cursor: pointer; border-radius: 4px; font-size: 1rem; }
        .btn-cancel { background: #e2e8f0; color: #333; padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 4px; font-size: 1rem; }
        .msg-ok { background: #c6f6d5; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php if ($deleted): ?>
        <div class="msg-ok">✅ Bauteil wurde gelöscht.</div>
        <p><a href="index.php">← Zurück zur Liste</a></p>
    <?php else: ?>
        <h1>🗑️ Bauteil löschen</h1>
        <div class="box">
            <p><strong>ID:</strong> <?= (int)$c['id'] ?></p>
            <p><strong>Kategorie:</strong> <?= htmlspecialchars($c['category'] ?? '–') ?></p>
            <p><strong>Typ:</strong> <?= htmlspecialchars($c['part_type'] ?? '–') ?></p>
            <p><strong>Beschreibung:</strong> <?= htmlspecialchars($c['description'] ?? '–') ?></p>
            <p><strong>Wert:</strong> <?= htmlspecialchars($c['part_value'] ?? '–') ?></p>
            <p><strong>Menge:</strong> <?= (int)$c['quantity'] ?></p>
            <p><strong>Lagerort:</strong> <?= htmlspecialchars($c['location'] ?? '–') ?></p>

            <form method="post" class="actions">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn-danger">Ja, endgültig löschen</button>
                <a href="index.php" class="btn-cancel">Abbrechen</a>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>

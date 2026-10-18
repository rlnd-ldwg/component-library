<?php
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
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
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

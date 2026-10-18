<?php
require_once __DIR__ . '/db.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Ungültige ID');
}

$stmt = $db->prepare('SELECT * FROM components WHERE id = :id');
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();

if (!$c) {
    exit('Bauteil nicht gefunden');
}

// Hilfsfunktion für schöne Ausgabe
function show($v) {
    return $v === null || $v === '' ? '–' : htmlspecialchars((string)$v);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?= show($c['part_type'] ?: $c['description'] ?: $c['category']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
    <h1><?= show($c['part_type'] ?: $c['description'] ?: $c['category']) ?></h1>
    <div class="sub">
        <?= show($c['category']) ?>
        <?php if ($c['footprint']): ?> · <?= show($c['footprint']) ?><?php endif; ?>
        <?php if ($c['mounting']): ?> · <?= show($c['mounting']) ?><?php endif; ?>
    </div>

    <div class="card">
        <table class="fields">
            <tr><th>Kategorie</th><td><?= show($c['category']) ?></td></tr>
            <tr><th>Typ</th><td><?= show($c['part_type']) ?></td></tr>
            <tr><th>Beschreibung</th><td><?= show($c['description']) ?></td></tr>
            <tr><th>Wert</th><td><?= show($c['part_value']) ?></td></tr>
            <tr><th>Bauform</th><td><?= show($c['footprint']) ?></td></tr>
            <tr><th>Montage</th><td><?= show($c['mounting']) ?></td></tr>
            <tr>
                <th>Menge</th>
                <td class="<?= (int)$c['quantity'] < 5 ? 'qty-low' : '' ?>">
                    <?= (int)$c['quantity'] ?>
                </td>
            </tr>
            <tr><th>Lagerort</th><td><?= show($c['location']) ?></td></tr>
            <tr><th>Hersteller</th><td><?= show($c['manufacturer']) ?></td></tr>
            <tr>
                <th>Preis/Stück</th>
                <td><?= $c['unit_price'] !== null ? number_format((float)$c['unit_price'], 4, ',', '.') . ' €' : '–' ?></td>
            </tr>
            <?php if (!empty($c['notes'])): ?>
            <tr><th>Notizen</th><td><div class="notes"><?= show($c['notes']) ?></div></td></tr>
            <?php endif; ?>
        </table>

        <div class="actions">
            <a href="edit.php?id=<?= (int)$c['id'] ?>" class="btn btn-edit">✏️ Bearbeiten</a>
            <a href="delete.php?id=<?= (int)$c['id'] ?>" class="btn btn-del" onclick="return confirm('Wirklich löschen?');">🗑️ Löschen</a>
            <a href="index.php" class="btn btn-back">← Zur Liste</a>
        </div>

        <p class="meta">
            ID <?= (int)$c['id'] ?> ·
            Angelegt: <?= show($c['created_at']) ?> ·
            Geändert: <?= show($c['updated_at']) ?>
        </p>
    </div>
</body>
</html>

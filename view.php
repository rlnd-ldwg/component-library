<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
    return $v === null || $v === '' ? '–' : htmlspecialchars($v);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?= show($c['part_type'] ?: $c['description'] ?: $c['category']) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #f5f5f5; }
        h1 { color: #333; margin-bottom: 0.2rem; }
        .sub { color: #666; margin-bottom: 1.5rem; }
        .card { background: #fff; padding: 1.5rem 2rem; border-radius: 6px; max-width: 700px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table.fields { border-collapse: collapse; width: 100%; }
        table.fields th { text-align: left; padding: 0.5rem 1rem 0.5rem 0; color: #555; vertical-align: top; width: 180px; font-weight: 600; }
        table.fields td { padding: 0.5rem 0; }
        .qty-low { color: #c53030; font-weight: bold; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-size: 0.95rem; }
        .btn-edit { background: #4a5568; color: #fff; }
        .btn-back { background: #e2e8f0; color: #333; }
        .btn-del { background: #c53030; color: #fff; }
        .notes { white-space: pre-wrap; background: #f7fafc; padding: 0.6rem; border-radius: 4px; }
        .meta { color: #888; font-size: 0.85rem; margin-top: 1rem; }
    </style>
</head>
<body>
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

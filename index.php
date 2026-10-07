<?php
require_once __DIR__ . '/db.php';

$db = getDB();

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

$sql = 'SELECT * FROM components';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY category, part_type';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll();

$categories = $db->query('SELECT DISTINCT category FROM components WHERE category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bauteil-Inventar</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #f5f5f5; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4a5568; color: #fff; }
        tr:hover { background: #f0f0f0; }
        .filter { margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .filter input, .filter select, .filter button { padding: 0.4rem 0.6rem; font-size: 0.95rem; }
        .filter button { background: #4a5568; color: #fff; border: none; cursor: pointer; }
        .count { color: #666; margin-bottom: 1rem; }
        .low { color: #c53030; font-weight: bold; }
        
.btn-row {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.btn {
    padding: 0.5rem 1rem;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    display: inline-block;
    line-height: 1.2;
    font-size: 0.95rem;
}
.btn-add { background: #38a169; }
.btn-add:hover { background: #2f855a; }
.btn-export { background: #2b6cb0; }
.btn-export:hover { background: #2c5282; }
.btn-import { background: #c53030; }
.btn-import:hover { background: #9b2c2c; }
.qty-btn {
    width: 24px; height: 24px;
    border: 1px solid #ccc; background: #f7f7f7;
    cursor: pointer; border-radius: 3px;
    font-weight: bold;
}
.qty-btn:hover { background: #e2e8f0; }
.qty-value { display: inline-block; min-width: 2.5em; text-align: center; }

        .btn-add:hover { background: #2f855a; }
        .qty-btn {
            width: 24px; height: 24px;
            border: 1px solid #ccc; background: #f7f7f7;
            cursor: pointer; border-radius: 3px;
            font-weight: bold;
        }
        .qty-btn:hover { background: #e2e8f0; }
        .qty-value { display: inline-block; min-width: 2.5em; text-align: center; }
.btn-row { display: flex; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap; }
.btn { padding: 0.5rem 1rem; color: #fff; text-decoration: none; border-radius: 4px; display: inline-block; }
.btn-add { background: #38a169; }
.btn-add:hover { background: #2f855a; }
.btn-export { background: #2b6cb0; }
.btn-export:hover { background: #2c5282; }
    </style>
</head>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const delta = btn.dataset.delta;
            const span = document.getElementById('qty-' + id);
            console.log('Klick:', id, delta);   // <-- Debug

            btn.disabled = true;
            try {
                const res = await fetch('update_qty.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + encodeURIComponent(id) + '&delta=' + encodeURIComponent(delta)
                });
                console.log('Status:', res.status);  // <-- Debug
                const text = await res.text();
                console.log('Antwort:', text);       // <-- Debug
                const data = JSON.parse(text);
                if (data.ok) {
                    span.textContent = data.quantity;
                    span.parentElement.classList.toggle('low', data.quantity < 5);
                } else {
                    alert('Fehler beim Speichern');
                }
            } catch (e) {
                console.error(e);
                alert('Netzwerkfehler: ' + e.message);
            } finally {
                btn.disabled = false;
            }
        });
    });
});
</script>

<body>
    <h1>🔧 Bauteil-Inventar</h1>

    <form class="filter" method="get">
        <input type="text" name="search" placeholder="Suchen (Typ, Beschreibung, Wert)..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <select name="category">
            <option value="">Alle Kategorien</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= ($_GET['category'] ?? '') === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="location" placeholder="Lagerort..." value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
        <button type="submit">Filtern</button>
        <a href="index.php">Zurücksetzen</a>
    </form>

    <?php
    // Aktuelle Filter-Parameter übernehmen, damit der Export gefiltert wird
    $exportParams = http_build_query([
        'search'   => $_GET['search']   ?? '',
        'category' => $_GET['category'] ?? '',
        'location' => $_GET['location'] ?? '',
    ]);
    ?>
    
<div class="btn-row">
    <a href="add.php" class="btn btn-add">➕ Bauteil hinzufügen</a>
    <?php
    $exportParams = http_build_query([
        'search'   => $_GET['search']   ?? '',
        'category' => $_GET['category'] ?? '',
        'location' => $_GET['location'] ?? '',
    ]);
    ?>
    <a href="export.php?<?= htmlspecialchars($exportParams) ?>" class="btn btn-export">⬇️ CSV-Export</a>
    <a href="import.php" class="btn btn-import">⬆️ CSV-Import</a>
</div>

    <p class="count"><?= count($components) ?> Bauteil(e) gefunden.</p>

    <table>
        <thead>
            <tr>
                <th>Kategorie</th>
                <th>Typ</th>
                <th>Beschreibung</th>
                <th>Wert</th>
                <th>Bauform</th>
                <th>Menge</th>
                <th>Lagerort</th>
                <th>Notizen</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($components)): ?>
                <tr><td colspan="8">Keine Bauteile gefunden.</td></tr>
            <?php else: ?>
                <?php foreach ($components as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['category'] ?? '–') ?></td>
                        <td>
                            <?php $label = $c['part_type'] ?: ($c['description'] ?: ($c['part_value'] ?: $c['category'])); ?>
                            <a href="view.php?id=<?= (int)$c['id'] ?>" style="text-decoration:none;color:#2b6cb0;">
                                <?= htmlspecialchars($label ?? '–') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($c['description'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($c['part_value'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($c['footprint'] ?? '–') ?></td>
                        <td class="<?= (int)$c['quantity'] < 5 ? 'low' : '' ?>">
                            <button type="button" class="qty-btn" data-id="<?= (int)$c['id'] ?>" data-delta="-1">−</button>
                            <span class="qty-value" id="qty-<?= (int)$c['id'] ?>"><?= (int)$c['quantity'] ?></span>
                            <button type="button" class="qty-btn" data-id="<?= (int)$c['id'] ?>" data-delta="1">+</button>
                        </td>
                        <td><?= htmlspecialchars($c['location'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($c['notes'] ?? '–') ?></td>
			<td>
                            <a href="edit.php?id=<?= (int)$c['id'] ?>">✏️</a>
			    <a href="delete.php?id=<?= (int)$c['id'] ?>" onclick="return confirm('Wirklich löschen?');">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

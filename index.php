<?php
require_once __DIR__ . '/db.php';

$db = getDB();

[$whereSql, $params] = buildFilter($_GET);

$stmt = $db->prepare('SELECT * FROM components' . $whereSql . ' ORDER BY category, part_type');
$stmt->execute($params);
$components = $stmt->fetchAll();

$categories = $db->query('SELECT DISTINCT category FROM components WHERE category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

// Pass current filters on to the export so that only the filtered rows are exported
$exportParams = http_build_query([
    'search'   => (string)($_GET['search']   ?? ''),
    'category' => (string)($_GET['category'] ?? ''),
    'location' => (string)($_GET['location'] ?? ''),
]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bauteil-Inventar</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
    <!-- Standard Favicon (Browser-Tab) -->
    <link rel="icon" type="image/png" sizes="64x64" href="favicon.png">
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
    <h1>🔧 Bauteil-Inventar</h1>
    <div class="toolbar">
        <form class="filter" method="get">
            <input type="text" name="search" placeholder="Suchen (Typ, Beschreibung, Wert)..." value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>">
            <select name="category">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= (string)($_GET['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="location" placeholder="Lagerort..." value="<?= htmlspecialchars((string)($_GET['location'] ?? '')) ?>">
            <button type="submit">Filtern</button>
            <a href="index.php" class="reset-link">Zurücksetzen</a>
        </form>

        <div class="btn-row">
            <a href="add.php" class="btn btn-add">➕ Bauteil hinzufügen</a>
            <a href="export.php?<?= htmlspecialchars($exportParams) ?>" class="btn btn-export">⬇️ CSV-Export</a>
            <a href="import.php" class="btn btn-import">⬆️ CSV-Import</a>
            <a href="swap.php" class="btn btn-swap">🔄 Schubladen tauschen</a>
        </div>
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
                <tr><td colspan="9">Keine Bauteile gefunden.</td></tr>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const delta = btn.dataset.delta;
            const span = document.getElementById('qty-' + id);

            btn.disabled = true;
            try {
                const res = await fetch('update_qty.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + encodeURIComponent(id) + '&delta=' + encodeURIComponent(delta)
                });
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                const data = await res.json();
                if (data.ok) {
                    span.textContent = data.quantity;
                    span.parentElement.classList.toggle('low', data.quantity < 5);
                } else {
                    alert('Fehler beim Speichern');
                }
            } catch (e) {
                console.error(e);
                alert('Fehler: ' + e.message);
            } finally {
                btn.disabled = false;
            }
        });
    });
});
</script>
</body>
</html>

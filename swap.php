<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/db.php';

$db = getDB();
$errors = [];
$messages = [];
$preview = null;
$done = false;

function normalizeLocation(string $s): string {
    $s = trim($s);
    $s = preg_replace('#/+#', '/', $s) ?? '';
    $s = trim($s, '/');
    return $s;
}

function findInDrawer(PDO $db, string $drawer): array {
    $stmt = $db->prepare('
        SELECT id, category, part_type, description, part_value, footprint, location
        FROM components
        WHERE location = :d1 OR location LIKE CONCAT(:d2, "/%")
        ORDER BY location, id
    ');
    $stmt->execute([':d1' => $drawer, ':d2' => $drawer]);
    return $stmt->fetchAll();
}

function swapPrefix(string $location, string $from, string $to): string {
    if ($location === $from) {
        return $to;
    }
    if (strpos($location, $from . '/') === 0) {
        return $to . substr($location, strlen($from));
    }
    return $location;
}

$source    = normalizeLocation($_POST['source'] ?? $_GET['source'] ?? '');
$target    = normalizeLocation($_POST['target'] ?? $_GET['target'] ?? '');
$action    = $_POST['action'] ?? '';
$confirmed = ($action === 'swap');

if ($source !== '' && $target !== '') {
    if ($source === $target) {
        $errors[] = 'Quelle und Ziel dürfen nicht identisch sein.';
    }
    if (strpos($source, $target . '/') === 0 || strpos($target, $source . '/') === 0) {
        $errors[] = 'Quelle und Ziel dürfen nicht ineinander verschachtelt sein.';
    }
}

if (!$errors && $source !== '' && $target !== '') {
    $srcItems = findInDrawer($db, $source);
    $dstItems = findInDrawer($db, $target);

    if ($confirmed) {
        $dummy = '__SWAP_TMP__';
        try {
            $db->beginTransaction();

            // Schritt 1: Quelle -> Dummy
            $stmt = $db->prepare('
                UPDATE components
                SET location = CONCAT(:dummy1, SUBSTRING(location, CHAR_LENGTH(:src1) + 1))
                WHERE location = :src2 OR location LIKE CONCAT(:src3, "/%")
            ');
            $stmt->execute([
                ':dummy1' => $dummy,
                ':src1'   => $source,
                ':src2'   => $source,
                ':src3'   => $source,
            ]);

            // Schritt 2: Ziel -> Quelle
            $stmt = $db->prepare('
                UPDATE components
                SET location = CONCAT(:src4, SUBSTRING(location, CHAR_LENGTH(:dst1) + 1))
                WHERE location = :dst2 OR location LIKE CONCAT(:dst3, "/%")
            ');
            $stmt->execute([
                ':src4' => $source,
                ':dst1' => $target,
                ':dst2' => $target,
                ':dst3' => $target,
            ]);

            // Schritt 3: Dummy -> Ziel
            $stmt = $db->prepare('
                UPDATE components
                SET location = CONCAT(:dst4, SUBSTRING(location, CHAR_LENGTH(:dummy2) + 1))
                WHERE location = :dummy3 OR location LIKE CONCAT(:dummy4, "/%")
            ');
            $stmt->execute([
                ':dst4'   => $target,
                ':dummy2' => $dummy,
                ':dummy3' => $dummy,
                ':dummy4' => $dummy,
            ]);

            $db->commit();
            $done = true;
            $messages[] = sprintf(
                'Tausch erfolgreich: %d Datensatz/Datensätze in "%s", %d in "%s".',
                count($srcItems), $source, count($dstItems), $target
            );
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = 'Tausch fehlgeschlagen: ' . $e->getMessage();
        }
    } else {
        $preview = [
            'source'   => $source,
            'target'   => $target,
            'srcItems' => $srcItems,
            'dstItems' => $dstItems,
        ];
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function label($row) {
    return $row['part_type'] ?: ($row['description'] ?: ($row['part_value'] ?: $row['category']));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Schubladen tauschen</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
    <h1>🔄 Schubladen tauschen</h1>

    <?php foreach ($messages as $m): ?>
        <div class="msg-ok"><?= h($m) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
        <div class="msg-err"><?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if ($done): ?>
        <p><a href="swap.php">← Weiteren Tausch durchführen</a> · <a href="index.php">Zur Liste</a></p>
    <?php else: ?>

    <div class="box">
        <form method="post">
            <div class="row">
                <div>
                    <label>Schublade A (Quelle)</label>
                    <input type="text" name="source" required
                           value="<?= h($source) ?>"
                           placeholder="z.B. St/A/1/1">
                    <p class="hint">Nur den Schubladen-Pfad angeben, ohne Fach (z.B. <code>St/A/1/1</code>).</p>
                </div>
                <div>
                    <label>Schublade B (Ziel)</label>
                    <input type="text" name="target" required
                           value="<?= h($target) ?>"
                           placeholder="z.B. St/A/3/4">
                    <p class="hint">Beide Schubladen werden samt Inhalt getauscht.</p>
                </div>
            </div>
            <button type="submit">Vorschau anzeigen</button>
        </form>

        <?php if ($preview): ?>
            <?php
                $srcItems = $preview['srcItems'];
                $dstItems = $preview['dstItems'];
            ?>
            <fieldset>
                <legend>Vorschau</legend>

                <h2>"<?= h($preview['source']) ?>" → "<?= h($preview['target']) ?>"</h2>
                <p class="count"><?= count($srcItems) ?> Bauteil(e) betroffen</p>
                <?php if ($srcItems): ?>
                    <table>
                        <thead><tr><th>ID</th><th>Bezeichnung</th><th>Alter Lagerort</th><th></th><th>Neuer Lagerort</th></tr></thead>
                        <tbody>
                            <?php foreach ($srcItems as $r): ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= h(label($r)) ?></td>
                                    <td><?= h($r['location']) ?></td>
                                    <td class="arrow">→</td>
                                    <td class="new-loc"><?= h(swapPrefix($r['location'], $preview['source'], $preview['target'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h2>"<?= h($preview['target']) ?>" → "<?= h($preview['source']) ?>"</h2>
                <p class="count"><?= count($dstItems) ?> Bauteil(e) betroffen</p>
                <?php if ($dstItems): ?>
                    <table>
                        <thead><tr><th>ID</th><th>Bezeichnung</th><th>Alter Lagerort</th><th></th><th>Neuer Lagerort</th></tr></thead>
                        <tbody>
                            <?php foreach ($dstItems as $r): ?>
                                <tr>
                                    <td><?= (int)$r['id'] ?></td>
                                    <td><?= h(label($r)) ?></td>
                                    <td><?= h($r['location']) ?></td>
                                    <td class="arrow">→</td>
                                    <td class="new-loc"><?= h(swapPrefix($r['location'], $preview['target'], $preview['source'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </fieldset>

            <form method="post" style="margin-top:1rem;">
                <input type="hidden" name="action" value="swap">
                <input type="hidden" name="source" value="<?= h($preview['source']) ?>">
                <input type="hidden" name="target" value="<?= h($preview['target']) ?>">
                <button type="submit" class="danger"
                        onclick="return confirm('Wirklich tauschen? <?= count($srcItems) ?> + <?= count($dstItems) ?> Datensätze werden umgeschrieben.');">
                    🔄 Jetzt tauschen
                </button>
            </form>
        <?php endif; ?>
    </div>

    <p><a class="back" href="index.php">← Abbrechen</a></p>

    <?php endif; ?>
</body>
</html>

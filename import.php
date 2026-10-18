<?php
require_once __DIR__ . '/db.php';

$db = getDB();
$messages = [];
$errors = [];
$imported = null;

$allowedColumns = [
    'category', 'part_type', 'description', 'part_value', 'footprint',
    'mounting', 'quantity', 'location', 'manufacturer', 'unit_price', 'notes'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv'];

    $mode        = $_POST['mode'] ?? 'append';        // append | upsert
    $resetTable  = ($_POST['reset_table'] ?? '') === 'yes';
    $upsertMode  = $_POST['upsert_mode'] ?? 'add';    // add | replace

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload-Fehler: Code ' . $file['error'];
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Ungültiger Upload.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $errors[] = 'Datei konnte nicht geöffnet werden.';
        } else {
            // BOM entfernen
            $firstBytes = fread($handle, 3);
            if ($firstBytes !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle, 0, ';', '"', '\\');
            if (!$header) {
                $errors[] = 'Kopfzeile konnte nicht gelesen werden.';
            } else {
                $header = array_map(fn($h) => strtolower(trim($h)), $header);

                if (!in_array('category', $header, true)) {
                    $errors[] = 'Spalte "category" fehlt in der Kopfzeile.';
                }

                $rows = [];
                while (($data = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                    if (count($data) === 1 && trim($data[0]) === '') continue;
                    $row = [];
                    foreach ($header as $i => $col) {
                        $row[$col] = $data[$i] ?? null;
                    }
                    $rows[] = $row;
                }
                fclose($handle);

                if (!$errors && $rows) {
                    try {
                        $db->beginTransaction();

                        // Optional: empty table first (DELETE keeps the structure and stays inside the transaction)
                        if ($resetTable) {
                            $db->exec('DELETE FROM components');
                        }

                        $cols = array_intersect($allowedColumns, $header);
                        $placeholders = array_map(fn($c) => ':' . $c, $cols);
                        $insertSql = 'INSERT INTO components (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
                        $insertStmt = $db->prepare($insertSql);

                        // Lookup für Upsert
                        $lookupStmt = $db->prepare('
                            SELECT id, quantity FROM components
                            WHERE (category <=> :category)
                              AND (part_type <=> :part_type)
                              AND (part_value <=> :part_value)
                              AND (footprint <=> :footprint)
                              AND (location <=> :location)
                            LIMIT 1
                        ');
                        $updateQtyStmt = $db->prepare('UPDATE components SET quantity = :q WHERE id = :id');

                        $added = 0;
                        $updated = 0;

                        foreach ($rows as $row) {
                            // Werte normalisieren
                            $params = [];
                            foreach ($cols as $c) {
                                $v = $row[$c] ?? null;
                                if ($v !== null) {
                                    $v = trim($v);
                                    if ($v === '') $v = null;
                                }
                                if ($c === 'quantity' && $v !== null) $v = (int)$v;
                                if ($c === 'unit_price' && $v !== null) $v = (float)str_replace(',', '.', $v);
                                $params[':' . $c] = $v;
                            }

                            $doInsert = true;

                            if ($mode === 'upsert') {
                                // Prüfen, ob es einen passenden Datensatz gibt
                                $lookupStmt->execute([
                                    ':category'   => $params[':category']   ?? null,
                                    ':part_type'  => $params[':part_type']  ?? null,
                                    ':part_value' => $params[':part_value'] ?? null,
                                    ':footprint'  => $params[':footprint']  ?? null,
                                    ':location'   => $params[':location']   ?? null,
                                ]);
                                $existing = $lookupStmt->fetch();
                                if ($existing) {
                                    $newQty = $upsertMode === 'add'
                                        ? (int)$existing['quantity'] + (int)($params[':quantity'] ?? 0)
                                        : (int)($params[':quantity'] ?? 0);
                                    $updateQtyStmt->execute([':q' => $newQty, ':id' => $existing['id']]);
                                    $updated++;
                                    $doInsert = false;
                                }
                            }

                            if ($doInsert) {
                                $insertStmt->execute($params);
                                $added++;
                            }
                        }

                        $db->commit();
                        $imported = ['added' => $added, 'updated' => $updated];
                        $messages[] = "Import abgeschlossen: $added neu, $updated aktualisiert."
                                    . ($resetTable ? ' (Tabelle wurde vorher geleert.)' : '');
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $errors[] = 'Import fehlgeschlagen: ' . $e->getMessage();
                    }
                } elseif (!$rows) {
                    $errors[] = 'Keine Datenzeilen in der CSV gefunden.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>CSV-Import</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
    <h1>⬆️ CSV-Import</h1>

    <?php foreach ($messages as $m): ?>
        <div class="msg-ok"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
        <div class="msg-err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($imported !== null): ?>
        <p><a href="index.php">← Zur Liste</a></p>
    <?php else: ?>
        <div class="box">
            <form method="post" enctype="multipart/form-data">
                <label>CSV-Datei auswählen (Trennzeichen <code>;</code>, UTF-8):</label>
                <input type="file" name="csv" accept=".csv,text/csv" required>

                <fieldset>
                    <legend>Import-Modus</legend>
                    <div class="radio-row">
                        <label>
                            <input type="radio" name="mode" value="append" checked>
                            Nur hinzufügen (jede Zeile = neuer Datensatz)
                        </label>
                    </div>
                    <div class="radio-row">
                        <label>
                            <input type="radio" name="mode" value="upsert">
                            Zusammenführen (bei gleicher Kategorie + Typ + Wert + Bauform + Lagerort)
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Wenn ein Datensatz schon existiert (nur bei „Zusammenführen")</legend>
                    <div class="radio-row">
                        <label>
                            <input type="radio" name="upsert_mode" value="add" checked>
                            Menge addieren
                        </label>
                    </div>
                    <div class="radio-row">
                        <label>
                            <input type="radio" name="upsert_mode" value="replace">
                            Menge ersetzen
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>⚠️ Gefährlich</legend>
                    <label style="font-weight:normal;">
                        <input type="checkbox" name="reset_table" value="yes">
                        Tabelle vorher komplett leeren (alle aktuellen Daten gehen verloren!)
                    </label>
                </fieldset>

                <p style="margin-top:1rem; color:#666; font-size:0.9rem;">
                    Erwartete Spalten (mindestens <code>category</code>):<br>
                    <code>category;part_type;description;part_value;footprint;mounting;quantity;location;manufacturer;unit_price;notes</code>
                </p>

                <button type="submit">Import starten</button>
            </form>
        </div>
        <p><a class="back" href="index.php">← Abbrechen</a></p>
    <?php endif; ?>
</body>
</html>

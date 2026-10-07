<?php
require_once __DIR__ . '/db.php';

$db = getDB();
$errors = [];
$success = false;

// Kategorien für Dropdown laden
$categories = $db->query('SELECT DISTINCT category FROM components WHERE category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pflichtfeld prüfen
    if (empty(trim($_POST['category'] ?? ''))) {
        $errors[] = 'Kategorie ist ein Pflichtfeld.';
    }

    if (!$errors) {
        $stmt = $db->prepare('
            INSERT INTO components 
              (category, part_type, description, part_value, footprint, mounting,
               quantity, location, manufacturer, unit_price, notes)
            VALUES 
              (:category, :part_type, :description, :part_value, :footprint, :mounting,
               :quantity, :location, :manufacturer, :unit_price, :notes)
        ');

        $stmt->execute([
            ':category'     => trim($_POST['category']) ?: null,
            ':part_type'    => trim($_POST['part_type'] ?? '') ?: null,
            ':description'  => trim($_POST['description'] ?? '') ?: null,
            ':part_value'   => trim($_POST['part_value'] ?? '') ?: null,
            ':footprint'    => trim($_POST['footprint'] ?? '') ?: null,
            ':mounting'     => trim($_POST['mounting'] ?? '') ?: null,
            ':quantity'     => ($_POST['quantity'] ?? '') === '' ? null : (int)$_POST['quantity'],
            ':location'     => trim($_POST['location'] ?? '') ?: null,
            ':manufacturer' => trim($_POST['manufacturer'] ?? '') ?: null,
            ':unit_price'   => ($_POST['unit_price'] ?? '') === '' ? null : (float)str_replace(',', '.', $_POST['unit_price']),
            ':notes'        => trim($_POST['notes'] ?? '') ?: null,
        ]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bauteil hinzufügen</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; background: #f5f5f5; }
        h1 { color: #333; }
        form { background: #fff; padding: 1.5rem; border-radius: 6px; max-width: 700px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 0.8rem; font-weight: 600; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 0.4rem; font-size: 0.95rem; box-sizing: border-box; }
        textarea { min-height: 60px; }
        button { margin-top: 1.2rem; padding: 0.6rem 1.2rem; background: #4a5568; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        .row { display: flex; gap: 1rem; }
        .row > div { flex: 1; }
        .msg-ok { background: #c6f6d5; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; }
        .msg-err { background: #fed7d7; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; }
        a.back { display: inline-block; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>➕ Bauteil hinzufügen</h1>

    <?php if ($success): ?>
        <div class="msg-ok">✅ Bauteil wurde gespeichert. <a href="index.php">Zur Liste</a> oder <a href="add.php">noch eins anlegen</a>.</div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="msg-err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <label>Kategorie *</label>
        <input type="text" name="category" list="catlist" required value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
        <datalist id="catlist">
            <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>">
            <?php endforeach; ?>
        </datalist>

        <div class="row">
            <div>
                <label>Typ</label>
                <input type="text" name="part_type" value="<?= htmlspecialchars($_POST['part_type'] ?? '') ?>">
            </div>
            <div>
                <label>Wert</label>
                <input type="text" name="part_value" value="<?= htmlspecialchars($_POST['part_value'] ?? '') ?>">
            </div>
        </div>

        <label>Beschreibung</label>
        <input type="text" name="description" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">

        <div class="row">
            <div>
                <label>Bauform</label>
                <input type="text" name="footprint" value="<?= htmlspecialchars($_POST['footprint'] ?? '') ?>">
            </div>
            <div>
                <label>Montage</label>
                <select name="mounting">
                    <option value="">–</option>
                    <option value="smd" <?= ($_POST['mounting'] ?? '') === 'smd' ? 'selected' : '' ?>>smd</option>
                    <option value="tht" <?= ($_POST['mounting'] ?? '') === 'tht' ? 'selected' : '' ?>>tht</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Menge</label>
                <input type="number" name="quantity" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
            </div>
            <div>
                <label>Lagerort</label>
                <input type="text" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label>Hersteller</label>
                <input type="text" name="manufacturer" value="<?= htmlspecialchars($_POST['manufacturer'] ?? '') ?>">
            </div>
            <div>
                <label>Preis/Stück</label>
                <input type="text" name="unit_price" value="<?= htmlspecialchars($_POST['unit_price'] ?? '') ?>" placeholder="z.B. 0,23">
            </div>
        </div>

        <label>Notizen</label>
        <textarea name="notes"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <button type="submit">Speichern</button>
    </form>

    <a class="back" href="index.php">← Zurück zur Liste</a>
</body>
</html>

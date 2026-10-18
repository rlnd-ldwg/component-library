<?php
require_once __DIR__ . '/db.php';

$db = getDB();
$errors = [];
$success = false;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Ungültige ID');
}

// Aktuellen Datensatz laden
$stmt = $db->prepare('SELECT * FROM components WHERE id = :id');
$stmt->execute([':id' => $id]);
$c = $stmt->fetch();

if (!$c) {
    exit('Bauteil nicht gefunden');
}

// Kategorien für Dropdown
$categories = $db->query('SELECT DISTINCT category FROM components WHERE category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

// Speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['category'] ?? ''))) {
        $errors[] = 'Kategorie ist ein Pflichtfeld.';
    }

    if (!$errors) {
        $stmt = $db->prepare('
            UPDATE components SET
              category = :category,
              part_type = :part_type,
              description = :description,
              part_value = :part_value,
              footprint = :footprint,
              mounting = :mounting,
              quantity = :quantity,
              location = :location,
              manufacturer = :manufacturer,
              unit_price = :unit_price,
              notes = :notes
            WHERE id = :id
        ');

        $stmt->execute([
            ':category'     => trim($_POST['category']) ?: null,
            ':part_type'    => trim($_POST['part_type'] ?? '') ?: null,
            ':description'  => trim($_POST['description'] ?? '') ?: null,
            ':part_value'   => trim($_POST['part_value'] ?? '') ?: null,
            ':footprint'    => trim($_POST['footprint'] ?? '') ?: null,
            ':mounting'     => trim($_POST['mounting'] ?? '') ?: null,
            ':quantity'     => ($_POST['quantity'] ?? '') === '' ? 0 : max(0, (int)$_POST['quantity']),
            ':location'     => trim($_POST['location'] ?? '') ?: null,
            ':manufacturer' => trim($_POST['manufacturer'] ?? '') ?: null,
            ':unit_price'   => ($_POST['unit_price'] ?? '') === '' ? null : (float)str_replace(',', '.', $_POST['unit_price']),
            ':notes'        => trim($_POST['notes'] ?? '') ?: null,
            ':id'           => $id,
        ]);

        // Daten neu laden, damit das Formular den aktuellen Stand zeigt
        $stmt = $db->prepare('SELECT * FROM components WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $c = $stmt->fetch();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bauteil bearbeiten</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
</head>
<body class="<?= (defined('APP_ENV') && APP_ENV === 'test') ? 'env-test' : '' ?>">
    <h1>✏️ Bauteil bearbeiten #<?= (int)$c['id'] ?></h1>

    <?php if ($success): ?>
        <div class="msg-ok">✅ Änderungen gespeichert.</div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="msg-err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <label>Kategorie *</label>
        <input type="text" name="category" list="catlist" required value="<?= htmlspecialchars($c['category'] ?? '') ?>">
        <datalist id="catlist">
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>">
            <?php endforeach; ?>
        </datalist>

        <div class="row">
            <div>
                <label>Typ</label>
                <input type="text" name="part_type" value="<?= htmlspecialchars($c['part_type'] ?? '') ?>">
            </div>
            <div>
                <label>Wert</label>
                <input type="text" name="part_value" value="<?= htmlspecialchars($c['part_value'] ?? '') ?>">
            </div>
        </div>

        <label>Beschreibung</label>
        <input type="text" name="description" value="<?= htmlspecialchars($c['description'] ?? '') ?>">

        <div class="row">
            <div>
                <label>Bauform</label>
                <input type="text" name="footprint" value="<?= htmlspecialchars($c['footprint'] ?? '') ?>">
            </div>
            <div>
                <label>Montage</label>
                <select name="mounting">
                    <option value="">–</option>
                    <option value="smd" <?= ($c['mounting'] ?? '') === 'smd' ? 'selected' : '' ?>>smd</option>
                    <option value="tht" <?= ($c['mounting'] ?? '') === 'tht' ? 'selected' : '' ?>>tht</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div>
                <label>Menge</label>
                <input type="number" name="quantity" value="<?= htmlspecialchars($c['quantity'] ?? '') ?>">
            </div>
            <div>
                <label>Lagerort</label>
                <input type="text" name="location" value="<?= htmlspecialchars($c['location'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label>Hersteller</label>
                <input type="text" name="manufacturer" value="<?= htmlspecialchars($c['manufacturer'] ?? '') ?>">
            </div>
            <div>
                <label>Preis/Stück</label>
                <input type="text" name="unit_price" value="<?= htmlspecialchars($c['unit_price'] ?? '') ?>" placeholder="z.B. 0,23">
            </div>
        </div>

        <label>Notizen</label>
        <textarea name="notes"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>

        <button type="submit">Speichern</button>
    </form>

    <p class="meta">
        Angelegt: <?= htmlspecialchars($c['created_at'] ?? '–') ?> · 
        Geändert: <?= htmlspecialchars($c['updated_at'] ?? '–') ?>
    </p>

    <p><a href="index.php">← Zurück zur Liste</a></p>
</body>
</html>

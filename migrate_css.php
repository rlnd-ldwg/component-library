<?php
// migrate_css.php
// Automatisiertes Skript zum Auslagern des CSS aus den PHP-Dateien.

$files = ['index.php', 'add.php', 'edit.php', 'delete.php', 'view.php', 'import.php', 'swap.php'];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "Datei nicht gefunden: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original = $content;
    
    // 1. Alten <style>-Block entfernen
    $content = preg_replace('/\s*<style[^>]*>.*?<\/style>/is', '', $content);
    
    // 2. CSS-Links einfügen (nur wenn noch nicht vorhanden)
    if (strpos($content, 'css/style.css') === false) {
        $cssLinks = <<<CSS
    <link rel="stylesheet" href="css/style.css">
    <?php if (defined('APP_ENV') && APP_ENV === 'test'): ?>
        <link rel="stylesheet" href="css/theme-test.css">
    <?php endif; ?>
CSS;
        $content = preg_replace('/<\/head>/i', $cssLinks . "\n</head>", $content, 1);
    }
    
    // 3. Body-Tag anpassen (env-test Klasse hinzufügen)
    if (strpos($content, 'env-test') === false) {
        $content = preg_replace_callback('/<body([^>]*)>/i', function($matches) {
            $attrs = $matches[1];
            $newClass = '<?= (defined(\'APP_ENV\') && APP_ENV === \'test\') ? \'env-test\' : \'\' ?>';
            
            if (preg_match('/class\s*=\s*([\'"])(.*?)\1/i', $attrs, $classMatch)) {
                $existingClass = $classMatch[2];
                $replacement = 'class="' . $existingClass . ' ' . $newClass . '"';
                $attrs = preg_replace('/class\s*=\s*([\'"])(.*?)\1/i', $replacement, $attrs);
            } else {
                $attrs .= ' class="' . $newClass . '"';
            }
            return '<body' . $attrs . '>';
        }, $content);
    }
    
    // Speichern, wenn Änderungen gemacht wurden
    if ($content !== $original) {
        file_put_contents($file . '.bak', $original);
        file_put_contents($file, $content);
        echo "✅ Angepasst: $file (Backup: $file.bak)\n";
    } else {
        echo "⏭️ Keine Änderung nötig: $file\n";
    }
}
echo "Fertig!\n";

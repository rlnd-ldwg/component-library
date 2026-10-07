# Bauteil-Inventar – Dokumentation

Eine einfache Web-Anwendung zur Verwaltung elektronischer und mechanischer Bauteile für die Heimanwendung. Bestehend aus einer MariaDB-Datenbank und einer PHP-Weboberfläche, die über Apache ausgeliefert wird.

---

## 1. Überblick

| Komponente | Zweck |
|---|---|
| **MariaDB** | Speichert alle Bauteile in einer Tabelle `components` |
| **Apache** | Liefert die Weboberfläche unter `/components/` aus |
| **PHP** | Verarbeitet Formulare, Filter, Export und Import |
| **Basic-Auth** | Schützt den Zugang zur Weboberfläche |

**Zugriffs-URL:** `https://rlnd.spdns.eu/components/`

**Dateiablage:** `/var/www/components/`

---

## 2. Datenbank einrichten

### 2.1 Datenbank und Tabelle anlegen

Mit `root` oder einem Benutzer mit `CREATE`-Rechten anmelden:

```bash
sudo mysql
```

Dann ausführen:

```sql
CREATE DATABASE IF NOT EXISTS bauteile
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bauteile;

CREATE TABLE IF NOT EXISTS components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NULL COMMENT 'Kategorie (ic, mcu, led, resistor, ...)',
    part_type VARCHAR(100) NULL COMMENT 'Typ/Bezeichnung (z.B. 8255, ATmega328P)',
    description VARCHAR(255) NULL COMMENT 'Kurzbeschreibung',
    part_value VARCHAR(50) NULL COMMENT 'Wert (10k, 100n, 20MHz)',
    footprint VARCHAR(50) NULL COMMENT 'Bauform (1206, SOT-23, ...)',
    mounting VARCHAR(10) NULL COMMENT 'Montage (smd, tht)',
    quantity INT DEFAULT 0 COMMENT 'Menge',
    location VARCHAR(100) NULL COMMENT 'Lagerort',
    manufacturer VARCHAR(100) NULL COMMENT 'Hersteller',
    unit_price DECIMAL(10, 4) NULL COMMENT 'Preis pro Stück',
    notes TEXT NULL COMMENT 'Notizen',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_location (location),
    INDEX idx_part_type (part_type),
    INDEX idx_footprint (footprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Spaltenübersicht

| Spalte | Typ | Bedeutung | Beispielwert |
|---|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | eindeutige ID | `42` |
| `category` | VARCHAR(50) | Kategorie | `resistor`, `led`, `ic` |
| `part_type` | VARCHAR(100) | konkreter Typ | `ATmega328P`, `BC847` |
| `description` | VARCHAR(255) | kurze Beschreibung | `npn`, `red`, `ca yellow` |
| `part_value` | VARCHAR(50) | Wert | `10k`, `100n`, `20MHz` |
| `footprint` | VARCHAR(50) | Bauform | `1206`, `SOT-23`, `hc-49` |
| `mounting` | VARCHAR(10) | Montageart | `smd`, `tht` |
| `quantity` | INT | Menge | `25` |
| `location` | VARCHAR(100) | Lagerort | `A/1/1/B` |
| `manufacturer` | VARCHAR(100) | Hersteller | `TI`, `esun` |
| `unit_price` | DECIMAL(10,4) | Preis pro Stück | `0.0162` |
| `notes` | TEXT | Notizen | `1x Devboard` |
| `created_at` | TIMESTAMP | Anlagedatum | automatisch |
| `updated_at` | TIMESTAMP | Änderungsdatum | automatisch |

### 2.3 Benutzer anlegen

**Wichtig:** Nicht `root` für die Anwendung verwenden. Eigenen Benutzer anlegen:

```sql
CREATE USER 'bauteile_user'@'localhost' IDENTIFIED BY 'DEIN_PASSWORT_HIER';

GRANT SELECT, INSERT, UPDATE, DELETE ON bauteile.* TO 'bauteile_user'@'localhost';

FLUSH PRIVILEGES;
```

**Rechte im Detail:**

| Recht | Wofür |
|---|---|
| `SELECT` | Liste anzeigen, Suchen, Filtern, Export |
| `INSERT` | Neue Bauteile anlegen, CSV-Import |
| `UPDATE` | Bearbeiten, Mengen-Schnelländerung, CSV-Import |
| `DELETE` | Löschen |

Das `DROP TABLE`-Recht für den optionalen „Alles löschen"-Modus im Import ist **nicht** enthalten – der Import nutzt stattdessen ein `DELETE FROM` bzw. `TRUNCATE`. Falls du den destruktiven Import brauchst, zusätzlich:

```sql
GRANT DROP ON bauteile.* TO 'bauteile_user'@'localhost';
```

**Empfehlung:** Erstmal weglassen. Die meisten Import-Fälle funktionieren ohne.

---

## 3. Ordnerstruktur

```
/var/www/components/
├── .htaccess          # Basic-Auth + Zugriffsschutz
├── config.php         # DB-Zugangsdaten
├── db.php             # PDO-Verbindung
├── index.php          # Liste + Filter + Mengen-Schnelländerung
├── add.php            # Bauteil hinzufügen
├── edit.php           # Bauteil bearbeiten
├── delete.php         # Bauteil löschen (mit Bestätigung)
├── view.php           # Detailansicht
├── export.php         # CSV-Export
├── import.php         # CSV-Import
└── update_qty.php     # AJAX-Endpunkt für +/- Buttons
```

### Konfigurationsdatei `config.php`

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bauteile');
define('DB_USER', 'bauteile_user');
define('DB_PASS', 'DEIN_PASSWORT_HIER');
```

---

## 4. Apache-Konfiguration

### 4.1 Alias einrichten

In der Vhost-Config `/etc/apache2/sites-enabled/nextcloud.conf` innerhalb `<VirtualHost *:443>`:

```apache
Alias /components /var/www/components

<Directory /var/www/components>
    AllowOverride All
    Require all granted
    Options -Indexes
</Directory>
```

| Zeile | Zweck |
|---|---|
| `Alias /components /var/www/components` | URL `/components/` → Ordner |
| `AllowOverride All` | `.htaccess`-Dateien werden gelesen |
| `Require all granted` | Apache erlaubt Zugriff (Ordner außerhalb DocumentRoot) |
| `Options -Indexes` | Kein automatisches Directory-Listing |

### 4.2 Apache neu laden

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

`configtest` muss `Syntax OK` melden.

---

## 5. Zugangsschutz

### 5.1 Passwortdatei anlegen

```bash
sudo htpasswd -c /etc/apache2/.htpasswd bauteile
```

- `-c` nur beim **ersten** Aufruf
- Benutzername: `bauteile`
- Passwort wird abgefragt

Weitere Benutzer (ohne `-c`):

```bash
sudo htpasswd /etc/apache2/.htpasswd zweiteruser
```

Die Datei liegt **außerhalb** des Webroots und ist damit nicht über HTTP erreichbar.

### 5.2 `.htaccess` in `/var/www/components/`

```apache
AuthType Basic
AuthName "Bauteile"
AuthUserFile /etc/apache2/.htpasswd
Require valid-user

Options -Indexes
```

### 5.3 Testen

```
https://rlnd.spdns.eu/components/
```

→ Login-Dialog → Benutzername + Passwort → Liste erscheint.

---

## 6. Bedienung

### 6.1 Liste (`index.php`)

- Zeigt alle Bauteile
- **Filter:** Suchfeld (Typ/Beschreibung/Wert), Kategorie-Dropdown, Lagerort
- **Mengen-Schnelländerung:** `+` / `−` Buttons direkt in der Tabelle (AJAX, ohne Neuladen)
- **Rote Hervorhebung** bei Mengen < 5
- **Buttons:** „➕ Bauteil hinzufügen", „⬇️ CSV-Export", „⬆️ CSV-Import"
- **Pro Zeile:** Typ-Link (Detailansicht), ✏️ Bearbeiten, 🗑️ Löschen

### 6.2 Bauteil hinzufügen (`add.php`)

- Pflichtfeld: `category`
- Alle anderen Felder optional
- Kategorie mit Autovervollständigung (bestehende Kategorien als Vorschlag)
- Komma im Preis wird automatisch in Punkt konvertiert

### 6.3 Bearbeiten (`edit.php`)

- Alle Felder vorbelegt mit aktuellen Werten
- Zeitstempel werden angezeigt (angelegt / geändert)

### 6.4 Löschen (`delete.php`)

- Bestätigungsseite mit allen Details des Bauteils
- Erst nach Klick auf „Ja, endgültig löschen" wird gelöscht
- Zusätzlich `confirm()`-Dialog auf der Listenseite

### 6.5 Detailansicht (`view.php`)

- Alle Felder übersichtlich dargestellt
- Aktionen: Bearbeiten, Löschen, Zurück
- Zeigt Zeitstempel

### 6.6 CSV-Export (`export.php`)

- Exportiert **alle** oder nur die **aktuell gefilterten** Bauteile
- Format: Semikolon-getrennt, UTF-8 mit BOM (LibreCalc/Excel-kompatibel)
- Dateiname: `bauteile_YYYY-MM-DD_HHMMSS.csv`
- Spalten: `id;category;part_type;description;part_value;footprint;mounting;quantity;location;manufacturer;unit_price;notes;created_at;updated_at`

### 6.7 CSV-Import (`import.php`)

**Drei Modi:**

| Modus | Verhalten |
|---|---|
| **Nur hinzufügen** | Jede Zeile wird neuer Datensatz (auch bei Duplikaten) |
| **Zusammenführen + Menge addieren** | Bei gleicher Kombination (category+part_type+part_value+footprint+location) wird Menge addiert |
| **Zusammenführen + Menge ersetzen** | Bei gleicher Kombination wird Menge auf CSV-Wert gesetzt |

**Optional:** Häkchen „Tabelle vorher komplett leeren" (destruktiv, standardmäßig aus).

**Erwartete Spalten** (mindestens `category`, Reihenfolge egal):

```
category;part_type;description;part_value;footprint;mounting;quantity;location;manufacturer;unit_price;notes
```

**Format:** Semikolon-getrennt, UTF-8 (BOM wird automatisch entfernt), Kopfzeile in Zeile 1.

Alle Importe laufen in einer Transaktion – bei Fehler wird komplett zurückgerollt.

---

## 7. Datenmodell-Konventionen

Damit die spätere Suche funktioniert, sollten Werte einheitlich gepflegt werden.

### 7.1 Kategorien

Einheitliche Schreibweise (klein):

```
ic, mcu, led, 7-segment, resistor, capacitor, inductor, crystal,
oscillator, diode, transistor, mosfet, regler, leistungstreiber,
filament, connector, misc
```

### 7.2 Werte normieren

| Bauteilart | Format | Beispiele |
|---|---|---|
| Widerstände | Zahl + Einheit | `10k`, `4k7`, `0.33` |
| Kondensatoren | Zahl + Einheit | `100n`, `10u`, `22p` |
| Frequenzen | Zahl + MHz | `20MHz`, `16MHz` |
| Sonstiges | wie aufgedruckt | `red`, `5.0V` |

**Nicht** verwenden: `4,7k`, `0.1u`, `10000`, `20 Mhz`.

### 7.3 Bauformen

Einheitlich klein:

```
1206, 0805, 0603, 2406, sot-23, to-92, sop-8, ssop-28, tqfp-32,
sma, sod-123, dil14, dil4, hc-49, smd6035
```

### 7.4 Lagerort-Schema

Empfehlung: `Schrank/Ebene/Fach` oder feiner:

```
A/1/1/B    Schrank A, Ebene 1, Fach 1, Box B
A/1/4/     Schrank A, Ebene 1, Fach 4
WC-RU      Werkstatt, rechte untere Ecke (für Filament)
```

**Konsequent bleiben** – sonst funktioniert das Filtern nach Lagerort nicht.

---

## 8. Datensicherung

### 8.1 CSV-Export (regelmäßig)

Über die Weboberfläche: `⬇️ CSV-Export` → Datei lokal speichern.

Empfehlung: Vor jedem Import und nach jeder Inventur-Session.

### 8.2 Datenbank-Dump

Zusätzlich als vollständiges Backup:

```bash
mysqldump -u bauteile_user -p bauteile > bauteile_backup_$(date +%F).sql
```

Wiederherstellung:

```bash
mysql -u bauteile_user -p bauteile < bauteile_backup_2026-10-06.sql
```

---

## 9. Typische Arbeitsabläufe

### 9.1 Inventur

1. `https://rlnd.spdns.eu/components/` aufrufen
2. Vorher: `⬇️ CSV-Export` als Sicherung
3. Durch die Schubladen gehen, Mengen mit `+`/`−` korrigieren
4. Neue Bauteile: `➕ Bauteil hinzufügen` oder als CSV importieren
5. Nach der Inventur: nochmal `⬇️ CSV-Export` als Sicherung

### 9.2 Neue Lieferung einpflegen

1. `⬆️ CSV-Import` öffnen
2. Modus: **„Zusammenführen + Menge addieren"**
3. CSV hochladen mit den neuen Mengen
4. Ergebnis: Bestehende Mengen werden erhöht, neue Kombinationen angelegt

### 9.3 Komplett neuer Aufbau aus LibreCalc

1. In LibreCalc Liste pflegen (Spalten wie in 6.7)
2. Als CSV speichern (Semikolon, UTF-8)
3. `⬆️ CSV-Import` mit Häkchen „Tabelle vorher komplett leeren"
4. Bestätigen → alte Tabelle wird verworfen, CSV-Stand ist neu

---

## 10. Fehlerbehebung

### 500 Internal Server Error

1. PHP-Fehler anzeigen lassen: in der betroffenen Datei ganz oben `ini_set('display_errors', '1');`
2. Syntaxprüfung per Konsole: `php -l /var/www/components/DATEI.php`
3. Apache-Log: `sudo tail -n 30 /var/log/apache2/nextcloud_error.log`

### 403 Forbidden

- `<Directory>`-Block in der Vhost-Config fehlt oder `Require all granted` fehlt
- `.htaccess` hat einen Tippfehler

### Login-Dialog erscheint nicht

- `.htaccess` fehlt im Ordner
- `AllowOverride All` fehlt im `<Directory>`-Block
- `AuthUserFile`-Pfad falsch

### CSV-Import scheitert

- Kopfzeile fehlt oder enthält nicht `category`
- Trennzeichen ist Komma statt Semikolon
- Datei ist nicht UTF-8

### Umlaute im CSV-Export falsch

- Beim Import in LibreCalc **Semikolon** als Trennzeichen wählen
- UTF-8 im Import-Dialog einstellen

---

## 11. Referenz: SQL-Snippets

### Alle Bauteile einer Kategorie

```sql
SELECT * FROM components WHERE category = 'led' ORDER BY part_value;
```

### Knappe Bestände

```sql
SELECT category, part_type, part_value, quantity, location
FROM components
WHERE quantity < 5
ORDER BY quantity, category;
```

### Was liegt in einem bestimmten Fach?

```sql
SELECT * FROM components WHERE location = 'A/1/1/B';
```

### Volltextsuche

```sql
SELECT * FROM components
WHERE part_type LIKE '%855%'
   OR description LIKE '%855%'
   OR part_value LIKE '%855%';
```

### Gesamtwert des Lagers

```sql
SELECT ROUND(SUM(quantity * unit_price), 2) AS gesamt_wert
FROM components
WHERE unit_price IS NOT NULL;
```

### Kategorien mit Anzahl Bauteilarten

```sql
SELECT category, COUNT(*) AS arten, SUM(quantity) AS stueck_gesamt
FROM components
GROUP BY category
ORDER BY category;
```

---

## 12. Sicherheitshinweise

- **Basic-Auth ist Pflicht**, wenn der Server von außen erreichbar ist (was bei `spdns.eu` der Fall ist).
- **Kein `root`-DB-User** in `config.php` verwenden.
- **`config.php`** darf nicht in einem öffentlichen Repository landen.
- **HTTPS** ist über die bestehende Nextcloud-Vhost-Konfiguration aktiv (Let's Encrypt o.ä.) – sicherstellen, dass HTTP auf HTTPS umleitet.
- **`Options -Indexes`** verhindert, dass bei fehlender `index.php` das Verzeichnis gelistet wird.
- **`DROP TABLE`**-Recht dem DB-User nur geben, wenn der destruktive Import-Modus gebraucht wird.

---

## 13. Struktur-Übersicht (Schaubild)

```
rlnd.spdns.eu (HTTPS)
│
├── /                        → Nextcloud
└── /components/             → Bauteil-Verwaltung  [Basic-Auth]
    │
    ├── index.php            Liste, Filter, +/- Mengen
    ├── add.php              Hinzufügen
    ├── edit.php             Bearbeiten
    ├── delete.php           Löschen
    ├── view.php             Detailansicht
    ├── export.php           CSV-Export
    ├── import.php           CSV-Import
    ├── update_qty.php       AJAX-Endpoint
    ├── db.php               PDO-Verbindung
    ├── config.php           DB-Zugangsdaten
    └── .htaccess            Auth
        │
        ▼
    MariaDB: bauteile.components
```


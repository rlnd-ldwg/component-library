# Component Inventory – Documentation

A simple web application for managing electronic and mechanical components for home use. It consists of a MariaDB database and a PHP web interface served by Apache.

---

## 1. Overview

| Component | Purpose |
|---|---|
| **MariaDB** | Stores all components in a table `components` |
| **Apache** | Serves the web interface at `/components/` |
| **PHP** | Handles forms, filters, export and import |
| **Basic auth** | Protects access to the web interface |

**Access URL:** `https://rlnd.spdns.eu/components/`

**File location:** `/var/www/components/`

**Credentials file:** `/etc/components/config.php` (outside the web root)

---

## 2. Setting up the database

### 2.1 Create database and table

Log in as `root` or a user with `CREATE` privileges:

```bash
sudo mysql
```

Then run:

```sql
CREATE DATABASE IF NOT EXISTS components
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE components;

CREATE TABLE IF NOT EXISTS components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NULL COMMENT 'Category (ic, mcu, led, resistor, ...)',
    part_type VARCHAR(100) NULL COMMENT 'Type/designation (e.g. 8255, ATmega328P)',
    description VARCHAR(255) NULL COMMENT 'Short description',
    part_value VARCHAR(50) NULL COMMENT 'Value (10k, 100n, 20MHz)',
    footprint VARCHAR(50) NULL COMMENT 'Package (1206, SOT-23, ...)',
    mounting VARCHAR(10) NULL COMMENT 'Mounting (smd, tht)',
    quantity INT DEFAULT 0 COMMENT 'Quantity',
    location VARCHAR(100) NULL COMMENT 'Storage location',
    manufacturer VARCHAR(100) NULL COMMENT 'Manufacturer',
    unit_price DECIMAL(10, 4) NULL COMMENT 'Price per piece',
    notes TEXT NULL COMMENT 'Notes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_location (location),
    INDEX idx_part_type (part_type),
    INDEX idx_footprint (footprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Column overview

| Column | Type | Meaning | Example |
|---|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | unique ID | `42` |
| `category` | VARCHAR(50) | category | `resistor`, `led`, `ic` |
| `part_type` | VARCHAR(100) | specific type | `ATmega328P`, `BC847` |
| `description` | VARCHAR(255) | short description | `npn`, `red`, `ca yellow` |
| `part_value` | VARCHAR(50) | value | `10k`, `100n`, `20MHz` |
| `footprint` | VARCHAR(50) | package | `1206`, `SOT-23`, `hc-49` |
| `mounting` | VARCHAR(10) | mounting type | `smd`, `tht` |
| `quantity` | INT | quantity | `25` |
| `location` | VARCHAR(100) | storage location | `A/1/1/B` |
| `manufacturer` | VARCHAR(100) | manufacturer | `TI`, `esun` |
| `unit_price` | DECIMAL(10,4) | price per piece | `0.0162` |
| `notes` | TEXT | notes | `1x devboard` |
| `created_at` | TIMESTAMP | creation date | automatic |
| `updated_at` | TIMESTAMP | modification date | automatic |

### 2.3 Create a user

**Important:** Do not use `root` for the application. Create a dedicated user with a strong, unique password:

```sql
CREATE USER 'component'@'localhost' IDENTIFIED BY 'A_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE ON components.* TO 'component'@'localhost';

FLUSH PRIVILEGES;
```

Change the password later with:

```sql
ALTER USER 'component'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';
FLUSH PRIVILEGES;
```

**Privileges in detail:**

| Privilege | Used for |
|---|---|
| `SELECT` | Showing the list, searching, filtering, export |
| `INSERT` | Creating new components, CSV import |
| `UPDATE` | Editing, quick quantity changes, CSV import |
| `DELETE` | Deleting, "empty table first" option of the import |

No `DROP` or `CREATE` privilege is needed. The import's reset option uses `DELETE FROM components`, which keeps the table structure and runs inside the import transaction.

---

## 3. Folder structure

```
/var/www/components/
├── .htaccess            # Basic auth + access protection
├── config.example.php   # Template for the credentials file
├── db.php               # Error handling, config loading, PDO connection, shared filter
├── index.php            # List + filter + quick quantity change
├── add.php              # Add component
├── edit.php             # Edit component
├── delete.php           # Delete component (with confirmation)
├── view.php             # Detail view
├── export.php           # CSV export
├── import.php           # CSV import
└── update_qty.php       # AJAX endpoint for +/- buttons

/etc/components/
└── config.php           # DB credentials (NOT in the web root)
```

### 3.1 Credentials file

The password is stored **outside** the web root. `db.php` loads `/etc/components/config.php` first and only falls back to `/var/www/components/config.php` if the external file does not exist (useful for migration; do not keep a local copy with a real password).

Set it up:

```bash
sudo mkdir -p /etc/components
sudo cp /var/www/components/config.example.php /etc/components/config.php
sudo nano /etc/components/config.php        # enter the real password
sudo chown root:www-data /etc/components/config.php
sudo chmod 640 /etc/components/config.php
```

Content of `/etc/components/config.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'components');
define('DB_USER', 'component');
define('DB_PASS', 'A_STRONG_PASSWORD');
```

Check that PHP can read it (adjust the user if PHP does not run as `www-data`):

```bash
sudo -u www-data php -r "require '/etc/components/config.php'; echo DB_USER, PHP_EOL;"
```

**Alternatives** (not used here):

- `SetEnv` in the vhost: password lives in the Apache config and can show up in `phpinfo()`/debug output.
- MariaDB `unix_socket` authentication: no password at all, but every process running as `www-data` (including Nextcloud) may connect as that DB user.

---

## 4. Apache configuration

### 4.1 Set up the alias

In the vhost config `/etc/apache2/sites-enabled/nextcloud.conf`, inside `<VirtualHost *:443>`:

```apache
Alias /components /var/www/components

<Directory /var/www/components>
    AllowOverride All
    Require all granted
    Options -Indexes
</Directory>
```

| Line | Purpose |
|---|---|
| `Alias /components /var/www/components` | URL `/components/` → folder |
| `AllowOverride All` | `.htaccess` files are read |
| `Require all granted` | Apache allows access (folder is outside DocumentRoot) |
| `Options -Indexes` | No automatic directory listing |

### 4.2 Reload Apache

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

`configtest` must report `Syntax OK`.

---

## 5. Access protection

### 5.1 Create the password file

```bash
sudo htpasswd -c /etc/apache2/.htpasswd component
```

- `-c` only on the **first** call
- Username: `component`
- The password is prompted for

Additional users (without `-c`):

```bash
sudo htpasswd /etc/apache2/.htpasswd seconduser
```

The file is located **outside** the web root and is therefore not reachable via HTTP.

### 5.2 `.htaccess` in `/var/www/components/`

```apache
AuthType Basic
AuthName "Components"
AuthUserFile /etc/apache2/.htpasswd
Require valid-user

Options -Indexes

# These files are only included by PHP, never requested directly
<FilesMatch "^(config|config\.example|db)\.php$">
    Require all denied
</FilesMatch>
```

### 5.3 Testing

```
https://rlnd.spdns.eu/components/
```

→ Login dialog → username + password → list appears.

Also verify that `https://rlnd.spdns.eu/components/config.php` and `.../db.php` return **403**.

---

## 6. Usage

### 6.1 List (`index.php`)

- Shows all components
- **Filters:** search field (type/description/value), category dropdown, storage location (`%` and `_` are searched literally)
- **Quick quantity change:** `+` / `−` buttons directly in the table (AJAX, no reload; quantity never drops below 0)
- **Red highlighting** for quantities < 5
- **Buttons:** "➕ Add component", "⬇️ CSV export" (respects the current filter), "⬆️ CSV import"
- **Per row:** type link (detail view), ✏️ edit, 🗑️ delete

### 6.2 Add a component (`add.php`)

- Required field: `category`
- All other fields optional
- Empty quantity is stored as `0`
- Category with autocomplete (existing categories as suggestions)
- A comma in the price is automatically converted to a period

### 6.3 Edit (`edit.php`)

- All fields prefilled with current values
- Empty quantity is stored as `0`
- Timestamps are displayed (created / modified)

### 6.4 Delete (`delete.php`)

- Confirmation page showing all details of the component
- Deletion only happens after clicking "Yes, delete permanently" (POST)
- Additional `confirm()` dialog on the list page and the detail page

### 6.5 Detail view (`view.php`)

- All fields clearly displayed
- Actions: edit, delete, back
- Shows timestamps

### 6.6 CSV export (`export.php`)

- Exports **all** or only the **currently filtered** components
- Format: semicolon-separated, UTF-8 with BOM (LibreCalc/Excel compatible)
- File name: `components_YYYY-MM-DD_HHMMSS.csv`
- Columns: `id;category;part_type;description;part_value;footprint;mounting;quantity;location;manufacturer;unit_price;notes;created_at;updated_at`

### 6.7 CSV import (`import.php`)

**Import mode** (choose one):

| Mode | Behavior |
|---|---|
| **Add only** | Every row becomes a new record (even for duplicates) |
| **Merge** | If the combination category + part_type + part_value + footprint + location already exists, that record is updated instead of creating a new one |

**If a record already exists** (only used with "Merge"):

| Setting | Behavior |
|---|---|
| **Add quantity** | CSV quantity is added to the existing quantity |
| **Replace quantity** | Existing quantity is set to the CSV value |

**Optional:** Checkbox "Completely empty the table first" (destructive, off by default). Runs `DELETE FROM components` inside the import transaction, so a failed import restores the old data.

**Expected columns** (at least `category`, order does not matter):

```
category;part_type;description;part_value;footprint;mounting;quantity;location;manufacturer;unit_price;notes
```

Columns of an export that are not in this list (`id`, `created_at`, `updated_at`) are ignored, so an export can be re-imported.

**Format:** semicolon-separated, UTF-8 (BOM is removed automatically), header row in line 1.

All imports run in a single transaction – on error everything is rolled back.

---

## 7. Data model conventions

For searching to work later, values should be maintained consistently.

### 7.1 Categories

Consistent spelling (lowercase):

```
ic, mcu, led, 7-segment, resistor, capacitor, inductor, crystal,
oscillator, diode, transistor, mosfet, regler, leistungstreiber,
filament, connector, misc
```

### 7.2 Normalizing values

| Component type | Format | Examples |
|---|---|---|
| Resistors | number + unit | `10k`, `4k7`, `0.33` |
| Capacitors | number + unit | `100n`, `10u`, `22p` |
| Frequencies | number + MHz | `20MHz`, `16MHz` |
| Other | as printed | `red`, `5.0V` |

**Do not** use: `4,7k`, `0.1u`, `10000`, `20 Mhz`.

### 7.3 Packages

Consistently lowercase:

```
1206, 0805, 0603, 2406, sot-23, to-92, sop-8, ssop-28, tqfp-32,
sma, sod-123, dil14, dil4, hc-49, smd6035
```

### 7.4 Storage location scheme

Recommendation: `cabinet/level/compartment` or finer:

```
A/1/1/B    Cabinet A, level 1, compartment 1, box B
A/1/4/     Cabinet A, level 1, compartment 4
WC-RU      Workshop, lower right corner (for filament)
```

**Stay consistent** – otherwise filtering by storage location will not work.

---

## 8. Backups

### 8.1 CSV export (regularly)

Via the web interface: `⬇️ CSV export` → save file locally.

Recommendation: Before every import and after every inventory session.

### 8.2 Database dump

Additionally as a full backup (run as a user with admin rights via `sudo`; the application user `component` has no `LOCK TABLES` privilege):

```bash
sudo mysqldump components > components_backup_$(date +%F).sql
```

Restore:

```bash
sudo mysql components < components_backup_2026-10-06.sql
```

---

## 9. Typical workflows

### 9.1 Inventory

1. Open `https://rlnd.spdns.eu/components/`
2. First: `⬇️ CSV export` as a backup
3. Go through the drawers, correct quantities with `+`/`−`
4. New components: `➕ Add component` or import as CSV
5. After the inventory: `⬇️ CSV export` again as a backup

### 9.2 Entering a new delivery

1. Open `⬆️ CSV import`
2. Import mode: **"Merge"**, then **"Add quantity"**
3. Upload a CSV with the new quantities
4. Result: Existing quantities are increased, new combinations are created

### 9.3 Complete rebuild from LibreCalc

1. Maintain the list in LibreCalc (columns as in 6.7)
2. Save as CSV (semicolon, UTF-8)
3. `⬆️ CSV import` with the checkbox "Completely empty the table first"
4. Confirm → old data is removed, the CSV state is the new state (all inside one transaction)

---

## 10. Troubleshooting

### 500 Internal Server Error

PHP errors are **not** shown in the browser (`display_errors` is off in `db.php`); they go to the Apache log.

1. Apache log: `sudo tail -n 30 /var/log/apache2/nextcloud_error.log`
2. Syntax check from the console: `php -l /var/www/components/FILE.php`
3. Only for temporary debugging: change `display_errors` to `'1'` in `db.php`, and **revert it afterwards**

### 403 Forbidden

- The `<Directory>` block is missing in the vhost config, or `Require all granted` is missing
- `.htaccess` contains a typo

### Login dialog does not appear

- `.htaccess` is missing in the folder
- `AllowOverride All` is missing in the `<Directory>` block
- `AuthUserFile` path is wrong

### Database connection fails

- `/etc/components/config.php` missing, wrong password, or not readable by the web server user (check `ls -l` and the `chown`/`chmod` from section 3.1)
- DB user lacks privileges on `components.*`

### CSV import fails

- Header row is missing or does not contain `category`
- Separator is a comma instead of a semicolon
- File is not UTF-8

### Umlauts wrong in the CSV export

- When importing into LibreCalc, choose **semicolon** as the separator
- Set UTF-8 in the import dialog

---

## 11. Reference: SQL snippets

### All components of a category

```sql
SELECT * FROM components WHERE category = 'led' ORDER BY part_value;
```

### Low stock

```sql
SELECT category, part_type, part_value, quantity, location
FROM components
WHERE quantity < 5
ORDER BY quantity, category;
```

### What is in a specific compartment?

```sql
SELECT * FROM components WHERE location = 'A/1/1/B';
```

### Full-text search

```sql
SELECT * FROM components
WHERE part_type LIKE '%855%'
   OR description LIKE '%855%'
   OR part_value LIKE '%855%';
```

### Total value of the stock

```sql
SELECT ROUND(SUM(quantity * unit_price), 2) AS total_value
FROM components
WHERE unit_price IS NOT NULL;
```

### Categories with number of part types

```sql
SELECT category, COUNT(*) AS types, SUM(quantity) AS pieces_total
FROM components
GROUP BY category
ORDER BY category;
```

---

## 12. Security notes

- **Basic auth is mandatory** if the server is reachable from outside (which is the case with `spdns.eu`).
- **Never use the `root` DB user** in the application.
- **Keep the password out of the web root**: `/etc/components/config.php`, mode `640`, owner `root:www-data`.
- **Never commit credentials** to a repository; only `config.example.php` belongs there. If a password was ever shared or committed, change it.
- **`display_errors` is off** in production; errors are logged instead of shown.
- **HTTPS** is active via the existing Nextcloud vhost configuration (Let's Encrypt or similar) – make sure HTTP redirects to HTTPS.
- **`Options -Indexes`** prevents the directory from being listed when `index.php` is missing.
- The DB user needs **no** `DROP`/`CREATE` privileges.
- The state-changing endpoints (`update_qty.php`, `delete.php`) have no CSRF token and rely on Basic auth plus a private installation.

---

## 13. Structure overview (diagram)

```
rlnd.spdns.eu (HTTPS)
│
├── /                        → Nextcloud
└── /components/             → Component management  [Basic auth]
    │
    ├── index.php            List, filter, +/- quantities
    ├── add.php              Add
    ├── edit.php             Edit
    ├── delete.php           Delete
    ├── view.php             Detail view
    ├── export.php           CSV export
    ├── import.php           CSV import
    ├── update_qty.php       AJAX endpoint
    ├── db.php               Config loading + PDO connection + shared filter
    ├── config.example.php   Template (no real password)
    └── .htaccess            Auth + file blocking
        │
        ▼
    /etc/components/config.php   (credentials, outside web root)
        │
        ▼
    MariaDB: components.components
```

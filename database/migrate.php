<?php
declare(strict_types=1);

/**
 * Brings the database up to the current schema on the first request after a
 * deploy — no SQL import needed. Each step runs once; app_settings remembers
 * the version reached. Works on MySQL 8 and MariaDB. No table or column is
 * named after a MySQL 8 reserved word.
 */
const SCHEMA_VERSION = 1;

function runMigrations(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (k VARCHAR(64) PRIMARY KEY, v MEDIUMTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $v = (int) $db->query("SELECT v FROM app_settings WHERE k = 'schema_version'")->fetchColumn();
    if ($v >= SCHEMA_VERSION) {
        seedAdmin($db);
        return;
    }
    if ((int) $db->query("SELECT GET_LOCK('pallets_migrate', 10)")->fetchColumn() !== 1) return;
    try {
        if ($v < 1) { migrationV1($db); seedCatalogue($db); seedPosts($db); }
        $db->prepare("REPLACE INTO app_settings (k, v) VALUES ('schema_version', ?)")->execute([(string) SCHEMA_VERSION]);
    } finally {
        $db->query("SELECT RELEASE_LOCK('pallets_migrate')");
    }
    seedAdmin($db);
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $s = $db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$table, $column]);
    return (bool) $s->fetchColumn();
}

function addColumn(PDO $db, string $table, string $column, string $definition): void
{
    if (!columnExists($db, $table, $column)) {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function migrationV1(PDO $db): void
{
    $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        full_name VARCHAR(120) NULL,
        company_name VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        password_hash VARCHAR(255) NULL,
        role ENUM('user','admin') NOT NULL DEFAULT 'user',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        address VARCHAR(255) NULL,
        city VARCHAR(80) NULL,
        province VARCHAR(40) NULL,
        vat_number VARCHAR(40) NULL,
        signup_ip VARCHAR(45) NULL,
        popia_consent_at DATETIME NULL,
        last_login_at DATETIME NULL,
        login_count INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (role), INDEX (created_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL, success TINYINT(1) NOT NULL DEFAULT 0,
        method VARCHAR(20) NOT NULL DEFAULT 'password', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (email, created_at), INDEX (ip, created_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
        used_at DATETIME NULL, ip VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (token_hash), INDEX (user_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NULL, action VARCHAR(60) NOT NULL, target VARCHAR(200) NULL, detail TEXT NULL,
        ip VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (created_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED NULL,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        description TEXT NULL,
        icon VARCHAR(40) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX (parent_id),
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category_id INT UNSIGNED NULL,
        name VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE,
        sku VARCHAR(60) NULL,
        price DECIMAL(10,2) NULL,
        regular_price DECIMAL(10,2) NULL,
        short_description TEXT NULL,
        description MEDIUMTEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (category_id), INDEX (is_active, is_featured),
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ){$t}");

    // Extra categories a product also shows under (e.g. Custom Pallets).
    $db->exec("CREATE TABLE IF NOT EXISTS product_categories (
        product_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (product_id, category_id), INDEX (category_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        path VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX (product_id, sort_order),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS quotes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(20) NULL UNIQUE,
        user_id INT UNSIGNED NULL,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NULL,
        company_name VARCHAR(160) NULL,
        delivery_address VARCHAR(255) NULL,
        city VARCHAR(80) NULL,
        province VARCHAR(40) NULL,
        needed_by DATE NULL,
        message TEXT NULL,
        status ENUM('new','reviewing','quoted','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'new',
        delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        valid_until DATE NULL,
        customer_note TEXT NULL,
        admin_note TEXT NULL,
        source VARCHAR(30) NOT NULL DEFAULT 'website',
        quoted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id), INDEX (email), INDEX (status), INDEX (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS quote_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quote_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        name VARCHAR(200) NOT NULL,
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        list_price DECIMAL(10,2) NULL,
        unit_price DECIMAL(10,2) NULL,
        notes VARCHAR(500) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX (quote_id), INDEX (product_id),
        FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS quote_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quote_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        from_admin TINYINT(1) NOT NULL DEFAULT 0,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (quote_id, created_at),
        FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS enquiries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NULL,
        address VARCHAR(255) NULL, message TEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
        ip VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (is_read), INDEX (created_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(200) NOT NULL UNIQUE, title VARCHAR(220) NOT NULL, excerpt TEXT NULL, body MEDIUMTEXT NULL,
        image VARCHAR(255) NULL, is_published TINYINT(1) NOT NULL DEFAULT 1,
        published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (is_published, published_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS ai_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL, kind VARCHAR(40) NOT NULL, ok TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (created_at)
    ){$t}");

    $db->exec("CREATE TABLE IF NOT EXISTS sent_emails (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, kind VARCHAR(40) NULL,
        status ENUM('sent','failed') NOT NULL, error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (created_at), INDEX (status)
    ){$t}");
}

/** Categories and the 37 products copied from the old WooCommerce site (database/seed/products.json). */
function seedCatalogue(PDO $db): void
{
    if ((int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0) return;
    $cats = [
        // slug => [name, parent slug, icon, description]
        'wooden-pallets'     => ['Wooden Pallets', null, 'fa-tree', 'New, recycled and repaired timber pallets in standard and custom sizes — 2-way, 4-way and Euro.'],
        'plastic-pallets'    => ['Plastic Pallets', null, 'fa-cubes', 'Hygienic, weather-proof plastic pallets: nestable, solid top, mesh, Euro and spill pallets.'],
        'aluminium-pallets'  => ['Aluminium Pallets', null, 'fa-layer-group', 'Light, strong, rust-free aluminium pallets for export, food and pharmaceutical use.'],
        'galvanised-pallets' => ['Galvanised & Steel Pallets', null, 'fa-industry', 'Hot-dip galvanised and slatted steel pallets for heavy loads and outdoor storage.'],
        'custom-pallets'     => ['Custom Pallets', null, 'fa-ruler-combined', 'Pallets built to your size, load and export (ISPM 15) requirements.'],
        '2-way-entry-pallets'=> ['2 Way Entry Pallets', 'wooden-pallets', 'fa-arrows-left-right', 'Forklift entry from two sides — strong solid-bearer pallets for racking and bricks.'],
        '4-way-entry-pallets'=> ['4 Way Entry Pallets', 'wooden-pallets', 'fa-arrows-up-down-left-right', 'Forklift and pallet-jack entry from all four sides.'],
        'euro-pallets'       => ['Euro Pallets', 'wooden-pallets', 'fa-earth-europe', '800 × 1200 mm European-spec pallets, light and heavy duty.'],
    ];
    $ids = [];
    $i = 0;
    $ins = $db->prepare('INSERT INTO categories (parent_id, name, slug, description, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($cats as $slug => [$name, $parent, $icon, $desc]) {
        $ins->execute([$parent ? $ids[$parent] : null, $name, $slug, $desc, $icon, $i++]);
        $ids[$slug] = (int) $db->lastInsertId();
    }

    $file = __DIR__ . '/seed/products.json';
    if (!is_file($file)) return;
    $products = json_decode((string) file_get_contents($file), true) ?: [];
    $featured = ['aluminium-pallet-2-way-120-x-100-x-15-cm', 'standard-4-way-entry-pallet-1000-x-1200', 'large-plastic-pallet-1200mm-x-1200mm',
                 'galvanised-pallet-2-way-120-x-90-x-11-5-cm', 'light-euro-pallet-industrial-a-grade-0-8m-x-1-2m', '2-way-entry-pallet-square-sized-1m-x-1m',
                 'plastic-pallet-solid-top-1200mm-x-1000mm', 'custom-pallets-offer'];
    $p   = $db->prepare('INSERT INTO products (category_id, name, slug, sku, price, regular_price, short_description, description, is_featured, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $pc  = $db->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)');
    $img = $db->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, ?)');
    foreach ($products as $n => $row) {
        $catSlug = $row['subcategory'] ?: $row['category'];
        $p->execute([
            $ids[$catSlug] ?? null, $row['name'], $row['slug'], $row['sku'] ?: null,
            $row['price'], $row['regular_price'] > $row['price'] ? $row['regular_price'] : null,
            $row['short_description'] ?: null, $row['description'] ?: null,
            in_array($row['slug'], $featured, true) ? 1 : 0, $n,
        ]);
        $pid = (int) $db->lastInsertId();
        foreach (array_unique(array_filter(array_merge([$row['category'], $row['subcategory']], $row['extra_categories'] ?? []))) as $c) {
            if (isset($ids[$c])) $pc->execute([$pid, $ids[$c]]);
        }
        foreach ($row['images'] as $k => $f) $img->execute([$pid, 'assets/img/products/' . $f, $k]);
    }
}

function seedPosts(PDO $db): void
{
    if ((int) $db->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0) return;
    $file = __DIR__ . '/seed/content.json';
    if (!is_file($file)) return;
    $c = json_decode((string) file_get_contents($file), true) ?: [];
    $s = $db->prepare('INSERT INTO posts (slug, title, excerpt, body, published_at) VALUES (?, ?, ?, ?, ?)');
    foreach ($c['posts'] ?? [] as $p) {
        $title = mb_convert_case(mb_strtolower($p['title']), MB_CASE_TITLE);
        $s->execute([$p['slug'], $title, $p['excerpt'], $p['text'], str_replace('T', ' ', $p['date'])]);
    }
}

/** The first admin, from admin_email + admin_seed_password in secrets.php. */
function seedAdmin(PDO $db): void
{
    $pw = (string) secret('admin_seed_password', '');
    if ($pw === '' || $pw === 'CHANGE_ME') return;
    $s = $db->prepare('SELECT id FROM users WHERE email = ?');
    $s->execute([ADMIN_EMAIL]);
    if ($s->fetchColumn()) return;
    $db->prepare("INSERT INTO users (email, full_name, password_hash, role, is_active) VALUES (?, 'Administrator', ?, 'admin', 1)")
       ->execute([ADMIN_EMAIL, password_hash($pw, PASSWORD_DEFAULT)]);
}

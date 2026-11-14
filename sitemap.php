<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    [APP_URL . '/', null, '1.0'], [APP_URL . '/shop.php', null, '0.9'], [APP_URL . '/quote.php', null, '0.6'],
    [APP_URL . '/about.php', null, '0.6'], [APP_URL . '/contact.php', null, '0.7'], [APP_URL . '/blog.php', null, '0.5'],
    [APP_URL . '/returns.php', null, '0.3'], [APP_URL . '/privacy.php', null, '0.2'], [APP_URL . '/terms.php', null, '0.2'],
];
foreach (allCategories() as $c) $urls[] = [categoryUrl($c), null, '0.8'];
foreach (getDB()->query('SELECT slug, updated_at FROM products WHERE is_active = 1') as $p) $urls[] = [APP_URL . '/product.php?s=' . rawurlencode($p['slug']), $p['updated_at'], '0.7'];
foreach (getDB()->query('SELECT slug, published_at FROM posts WHERE is_published = 1') as $p) $urls[] = [APP_URL . '/post.php?s=' . rawurlencode($p['slug']), $p['published_at'], '0.5'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $pri]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . '<priority>' . $pri . "</priority></url>\n";
}
echo '</urlset>';

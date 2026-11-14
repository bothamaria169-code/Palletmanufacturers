<?php
declare(strict_types=1);

/** Products, categories and the visitor's quote basket. */

function allCategories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = getDB()->query(
            'SELECT c.*, (SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN product_categories pc ON pc.product_id = p.id
                          WHERE p.is_active = 1 AND (p.category_id = c.id OR pc.category_id = c.id
                                OR p.category_id IN (SELECT id FROM categories k WHERE k.parent_id = c.id))) AS product_count
             FROM categories c ORDER BY c.sort_order, c.name'
        )->fetchAll();
    }
    return $cats;
}

function topCategories(): array
{
    return array_values(array_filter(allCategories(), fn($c) => $c['parent_id'] === null));
}

function childCategories(int $parentId): array
{
    return array_values(array_filter(allCategories(), fn($c) => (int) $c['parent_id'] === $parentId));
}

function categoryBySlug(string $slug): ?array
{
    foreach (allCategories() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}

function categoryById(?int $id): ?array
{
    foreach (allCategories() as $c) if ((int) $c['id'] === $id) return $c;
    return null;
}

/** First image of each product, keyed by product id. */
function attachImages(array $products): array
{
    if (!$products) return [];
    $ids = implode(',', array_map(fn($p) => (int) $p['id'], $products));
    $imgs = [];
    foreach (getDB()->query("SELECT product_id, path FROM product_images WHERE product_id IN ({$ids}) ORDER BY sort_order, id") as $r) {
        $imgs[(int) $r['product_id']][] = $r['path'];
    }
    foreach ($products as &$p) $p['images'] = $imgs[(int) $p['id']] ?? [];
    return $products;
}

/**
 * @param array{cat?: ?array, q?: string, sort?: string, featured?: bool, limit?: int, offset?: int} $f
 * @return array{0: array, 1: int} products, total
 */
function findProducts(array $f = []): array
{
    $where = ['p.is_active = 1'];
    $args  = [];
    if (!empty($f['cat'])) {
        $ids = [(int) $f['cat']['id']];
        foreach (childCategories((int) $f['cat']['id']) as $c) $ids[] = (int) $c['id'];
        $in = implode(',', $ids);
        $where[] = "(p.category_id IN ({$in}) OR p.id IN (SELECT product_id FROM product_categories WHERE category_id IN ({$in})))";
    }
    if (!empty($f['q'])) {
        $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($args, $like, $like, $like);
    }
    if (!empty($f['featured'])) $where[] = 'p.is_featured = 1';
    $order = match ($f['sort'] ?? '') {
        'price_asc'  => 'p.price IS NULL, p.price ASC',
        'price_desc' => 'p.price DESC',
        'name'       => 'p.name',
        'new'        => 'p.created_at DESC, p.id DESC',
        default      => 'p.is_featured DESC, p.sort_order, p.id',
    };
    $sqlWhere = implode(' AND ', $where);
    $c = getDB()->prepare("SELECT COUNT(*) FROM products p WHERE {$sqlWhere}");
    $c->execute($args);
    $total = (int) $c->fetchColumn();
    $limit = (int) ($f['limit'] ?? 24);
    $offset = (int) ($f['offset'] ?? 0);
    $s = getDB()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id
                           WHERE {$sqlWhere} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}");
    $s->execute($args);
    return [attachImages($s->fetchAll()), $total];
}

function productBySlug(string $slug, bool $activeOnly = true): ?array
{
    $s = getDB()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ?' . ($activeOnly ? ' AND p.is_active = 1' : ''));
    $s->execute([$slug]);
    $p = $s->fetch();
    return $p ? attachImages([$p])[0] : null;
}

function productById(int $id): ?array
{
    $s = getDB()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
    $s->execute([$id]);
    $p = $s->fetch();
    return $p ? attachImages([$p])[0] : null;
}

function imgUrl(?string $path, bool $small = false): string
{
    if (!$path) return APP_URL . '/assets/img/placeholder.svg';
    if ($small && str_ends_with($path, '.webp')) {
        $sm = substr($path, 0, -5) . '-sm.webp';
        if (is_file(BASE_DIR . '/' . $sm)) $path = $sm;
    }
    return APP_URL . '/' . $path;
}

function productUrl(array $p): string
{
    return APP_URL . '/product.php?s=' . rawurlencode($p['slug']);
}

function categoryUrl(array $c): string
{
    return APP_URL . '/shop.php?cat=' . rawurlencode($c['slug']);
}

function onSale(array $p): bool
{
    return $p['regular_price'] !== null && (float) $p['regular_price'] > (float) $p['price'];
}

function priceHtml(array $p): string
{
    if ($p['price'] === null || (float) $p['price'] <= 0) return '<span class="price-ask">Price on request</span>';
    $out = '<span class="price-now">' . rand_($p['price']) . '</span>';
    if (onSale($p)) $out .= ' <del class="price-was">' . rand_($p['regular_price']) . '</del>';
    return $out . ' <small class="price-unit">each</small>';
}

function productCard(array $p): string
{
    $img  = $p['images'][0] ?? null;
    $url  = productUrl($p);
    $pct  = onSale($p) ? (int) round((1 - (float) $p['price'] / (float) $p['regular_price']) * 100) : 0;
    $name = e($p['name']);
    return '<article class="pcard">'
        . '<a class="pcard__img" href="' . $url . '"><img src="' . e(imgUrl($img, true)) . '" alt="' . $name . '" width="560" height="560" loading="lazy">'
        . ($pct ? '<span class="pcard__badge">-' . $pct . '%</span>' : '') . '</a>'
        . '<div class="pcard__body">'
        . ($p['category_name'] ? '<a class="pcard__cat" href="' . APP_URL . '/shop.php?cat=' . e($p['category_slug']) . '">' . e($p['category_name']) . '</a>' : '')
        . '<h3><a href="' . $url . '">' . $name . '</a></h3>'
        . '<div class="pcard__price">' . priceHtml($p) . '</div>'
        . '<div class="pcard__actions">'
        . '<button class="btn btn-primary btn-sm" data-add="' . (int) $p['id'] . '"><i class="fa-solid fa-file-circle-plus"></i>Add to quote</button>'
        . '<a class="btn btn-ghost btn-sm" href="' . $url . '" aria-label="View ' . $name . '"><i class="fa-solid fa-eye"></i></a>'
        . '</div></div></article>';
}

// ── Quote basket (session) ─────────────────────────────────────────
// ['<product id>' => ['qty' => 10, 'notes' => 'heat treated']]

function basket(): array
{
    startAppSession();
    return $_SESSION['basket'] ?? [];
}

function basketCount(): int
{
    return count(basket());
}

function basketSet(int $productId, int $qty, ?string $notes = null): void
{
    startAppSession();
    if ($qty <= 0) {
        unset($_SESSION['basket'][$productId]);
        return;
    }
    $_SESSION['basket'][$productId] = [
        'qty'   => min($qty, 100000),
        'notes' => $notes ?? ($_SESSION['basket'][$productId]['notes'] ?? ''),
    ];
}

function basketClear(): void
{
    startAppSession();
    $_SESSION['basket'] = [];
}

/** Basket lines with their products, dropping anything no longer for sale. */
function basketLines(): array
{
    $b = basket();
    if (!$b) return [];
    $ids = implode(',', array_map('intval', array_keys($b)));
    $rows = attachImages(getDB()->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.id IN ({$ids})")->fetchAll());
    $lines = [];
    foreach ($rows as $p) {
        $item = $b[(int) $p['id']];
        $lines[] = ['product' => $p, 'qty' => (int) $item['qty'], 'notes' => (string) $item['notes'],
                    'line' => $p['price'] !== null ? (float) $p['price'] * (int) $item['qty'] : null];
    }
    return $lines;
}

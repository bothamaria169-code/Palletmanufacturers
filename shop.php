<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$cat  = isset($_GET['cat']) ? categoryBySlug((string) $_GET['cat']) : null;
$q    = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$sort = (string) ($_GET['sort'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 24;
[$products, $total] = findProducts(['cat' => $cat, 'q' => $q, 'sort' => $sort, 'limit' => $per, 'offset' => ($page - 1) * $per]);
$parent = $cat && $cat['parent_id'] ? categoryById((int) $cat['parent_id']) : null;

$title = $cat ? $cat['name'] . ' for Sale' : ($q !== '' ? 'Search: ' . $q : 'All Pallets for Sale');
$desc  = $cat ? ($cat['description'] ?: $cat['name'] . ' at wholesale prices.') . ' Order online from ' . APP_NAME . ', Glen Anil, Durban.'
              : 'Shop wooden, plastic, aluminium, galvanised and custom pallets at wholesale prices. Delivery across South Africa.';
$canon = APP_URL . '/shop.php' . ($cat ? '?cat=' . rawurlencode($cat['slug']) : '');
siteOpen($title . ' | ' . APP_NAME, ['description' => $desc, 'canonical' => $canon, 'noindex' => $q !== ''], 'shop');
$query = array_filter(['cat' => $cat['slug'] ?? null, 'q' => $q ?: null, 'sort' => $sort ?: null]);
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= APP_URL ?>/">Home</a> / <a href="<?= APP_URL ?>/shop.php">Pallets</a><?php if ($parent): ?> / <a href="<?= e(categoryUrl($parent)) ?>"><?= e($parent['name']) ?></a><?php endif; ?><?php if ($cat): ?> / <span><?= e($cat['name']) ?></span><?php endif; ?></nav>
    <h1><?= e($cat ? $cat['name'] : ($q !== '' ? 'Results for "' . $q . '"' : 'All pallets')) ?></h1>
    <p><?= e($cat['description'] ?? 'Every pallet we stock, with today\'s price. Add what you need to your quote and we\'ll confirm stock and delivery.') ?></p>
  </div>
</section>

<div class="wrap shop">
  <aside class="shop-side" aria-label="Categories">
    <h2>Categories</h2>
    <a href="<?= APP_URL ?>/shop.php" class="<?= !$cat ? 'is-on' : '' ?>">All pallets <span><?= (int) getDB()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn() ?></span></a>
    <?php foreach (topCategories() as $c): ?>
      <a href="<?= e(categoryUrl($c)) ?>" class="<?= $cat && (int) $cat['id'] === (int) $c['id'] ? 'is-on' : '' ?>"><?= e($c['name']) ?> <span><?= (int) $c['product_count'] ?></span></a>
      <?php foreach (childCategories((int) $c['id']) as $k): ?>
        <a href="<?= e(categoryUrl($k)) ?>" class="sub <?= $cat && (int) $cat['id'] === (int) $k['id'] ? 'is-on' : '' ?>"><?= e($k['name']) ?> <span><?= (int) $k['product_count'] ?></span></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="help">
      <strong>Need a custom size?</strong>
      Tell us the dimensions, load and quantity and we'll build it.
      <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/quote.php?custom=1"><i class="fa-solid fa-ruler-combined"></i>Custom quote</a>
    </div>
  </aside>

  <div>
    <nav class="chips-row" aria-label="Categories">
      <a href="<?= APP_URL ?>/shop.php" class="<?= !$cat ? 'is-on' : '' ?>">All</a>
      <?php foreach (allCategories() as $c): ?><a href="<?= e(categoryUrl($c)) ?>" class="<?= $cat && (int) $cat['id'] === (int) $c['id'] ? 'is-on' : '' ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </nav>
    <div class="shop-bar">
      <span class="muted"><?= number_format($total) ?> product<?= $total === 1 ? '' : 's' ?></span>
      <form method="get">
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat['slug']) ?>"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <label class="sr-only" for="sort" hidden>Sort</label>
        <select id="sort" name="sort" onchange="this.form.submit()" aria-label="Sort products">
          <option value="">Sort: popular</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
        </select>
      </form>
    </div>
    <?php if ($products): ?>
      <div class="pgrid"><?php foreach ($products as $p) echo productCard($p); ?></div>
      <?= pager($total, $page, $per, $query) ?>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h2>No pallets found</h2><p>Try another search, or ask us — we probably have it or can build it.</p><a class="btn btn-primary" href="<?= APP_URL ?>/quote.php?custom=1">Request a custom quote</a></div>
    <?php endif; ?>
  </div>
</div>
<?php siteClose();

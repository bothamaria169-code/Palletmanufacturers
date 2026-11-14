<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$p = productBySlug((string) ($_GET['s'] ?? ''));
if (!$p) {
    http_response_code(404);
    siteOpen('Pallet not found | ' . APP_NAME, ['noindex' => true]);
    echo '<div class="wrap empty-state" style="padding-block:96px"><i class="fa-solid fa-pallet"></i><h1>That pallet isn\'t available</h1><p>It may have sold out or been renamed.</p><a class="btn btn-primary" href="' . APP_URL . '/shop.php">Browse all pallets</a></div>';
    siteClose();
    exit;
}
$cat    = categoryById($p['category_id'] ? (int) $p['category_id'] : null);
$parent = $cat && $cat['parent_id'] ? categoryById((int) $cat['parent_id']) : null;
[$related] = findProducts(['cat' => $cat, 'limit' => 5]);
$related = array_slice(array_values(array_filter($related, fn($r) => (int) $r['id'] !== (int) $p['id'])), 0, 4);
$inBasket = basket()[(int) $p['id']]['qty'] ?? 0;
$hasPrice = $p['price'] !== null && (float) $p['price'] > 0;
$url = productUrl($p);
$imgs = $p['images'] ?: [null];

$schema = [
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'sku' => $p['sku'] ?: (string) $p['id'],
    'image' => array_map(fn($i) => imgUrl($i), array_filter($p['images'])), 'description' => excerpt($p['short_description'] ?: $p['description'], 300),
    'brand' => ['@type' => 'Brand', 'name' => APP_NAME], 'category' => $cat['name'] ?? 'Pallets',
];
if ($hasPrice) {
    $schema['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'ZAR', 'price' => number_format((float) $p['price'], 2, '.', ''), 'availability' => 'https://schema.org/InStock',
                         'url' => $url, 'seller' => ['@type' => 'Organization', 'name' => APP_NAME]];
}
$crumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Pallets', 'item' => APP_URL . '/shop.php'],
    $cat ? ['@type' => 'ListItem', 'position' => 2, 'name' => $cat['name'], 'item' => categoryUrl($cat)] : null,
    ['@type' => 'ListItem', 'position' => $cat ? 3 : 2, 'name' => $p['name'], 'item' => $url],
]))];

siteOpen($p['name'] . ($hasPrice ? ' – ' . rand_($p['price']) : '') . ' | ' . APP_NAME, [
    'description' => excerpt($p['short_description'] ?: $p['description'], 155) ?: $p['name'] . ' for sale at ' . APP_NAME,
    'canonical' => $url, 'image' => $p['images'] ? imgUrl($p['images'][0]) : null, 'og_type' => 'product',
    'schema' => [$schema, $crumbs], 'preload' => imgUrl($imgs[0]),
], 'shop');
?>
<div class="wrap">
  <nav class="crumbs" style="margin-top:1.4rem" aria-label="Breadcrumb"><a href="<?= APP_URL ?>/">Home</a> / <a href="<?= APP_URL ?>/shop.php">Pallets</a><?php if ($parent): ?> / <a href="<?= e(categoryUrl($parent)) ?>"><?= e($parent['name']) ?></a><?php endif; ?><?php if ($cat): ?> / <a href="<?= e(categoryUrl($cat)) ?>"><?= e($cat['name']) ?></a><?php endif; ?></nav>
  <div class="pdp">
    <div class="gallery">
      <div class="gallery__main"><img src="<?= e(imgUrl($imgs[0])) ?>" alt="<?= e($p['name']) ?>" width="1200" height="1200"></div>
      <?php if (count($p['images']) > 1): ?>
        <div class="gallery__thumbs">
          <?php foreach ($p['images'] as $i => $img): ?>
            <button type="button" class="<?= $i === 0 ? 'is-on' : '' ?>" data-src="<?= e(imgUrl($img)) ?>" aria-label="Show photo <?= $i + 1 ?>"><img src="<?= e(imgUrl($img, true)) ?>" alt="" width="76" height="76" loading="lazy"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($cat): ?><a class="pcard__cat" href="<?= e(categoryUrl($cat)) ?>"><?= e($cat['name']) ?></a><?php endif; ?>
      <h1><?= e($p['name']) ?></h1>
      <div class="pdp__price"><?= priceHtml($p) ?><?php if (onSale($p)): ?> <span class="tag tag-off">Save <?= rand_((float) $p['regular_price'] - (float) $p['price']) ?></span><?php endif; ?></div>
      <?php if ($p['short_description']): ?><div class="pdp__short rich"><?= richText($p['short_description']) ?></div><?php endif; ?>

      <div class="buybox">
        <div class="buybox__row">
          <div class="qty"><button type="button" data-step="-10" aria-label="Less">−</button><input id="pdpQty" type="number" min="1" value="<?= $inBasket ?: 10 ?>" aria-label="Quantity"><button type="button" data-step="10" aria-label="More">+</button></div>
          <?php if ($hasPrice): ?><div class="buybox__est">Estimate: <strong id="pdpEst" data-price="<?= e((string) (float) $p['price']) ?>"></strong></div><?php endif; ?>
        </div>
        <div class="field"><label for="pdpNote">Notes for this item <span class="hint">optional — e.g. heat treated, custom height</span></label><input id="pdpNote" type="text" maxlength="300" placeholder="Anything we should know?"></div>
        <div class="buybox__row">
          <button class="btn btn-primary btn-lg" data-add="<?= (int) $p['id'] ?>" data-qty-from="#pdpQty" data-note-from="#pdpNote"><i class="fa-solid fa-file-circle-plus"></i>Add to quote</button>
          <a class="btn btn-wa btn-lg" href="<?= e(waLink('Hi, I\'m interested in: ' . $p['name'] . ' — ' . $url)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Ask</a>
        </div>
        <a href="<?= APP_URL ?>/quote.php" class="muted" style="font-size:.86rem">View my quote →</a>
      </div>

      <div class="pdp__facts">
        <div><i class="fa-solid fa-truck-fast"></i><span>Delivery across KZN, Gauteng and South Africa — cost confirmed on your quote.</span></div>
        <div><i class="fa-solid fa-rotate-left"></i><span>30-day returns on standard items. <a href="<?= APP_URL ?>/returns.php">Return policy</a></span></div>
        <div><i class="fa-solid fa-receipt"></i><span><?= e(vatNote()) ?> Bulk pricing on request.</span></div>
        <?php if ($p['sku']): ?><div><i class="fa-solid fa-barcode"></i><span>SKU: <?= e($p['sku']) ?></span></div><?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($p['description']): ?>
    <section class="tabbox rich" style="margin-bottom:3rem"><h2 style="font-size:1.4rem;margin-bottom:.6rem">Product details</h2><?= richText($p['description']) ?></section>
  <?php endif; ?>

  <?php if ($related): ?>
    <section style="margin-bottom:2rem">
      <div class="sec-head"><div><h2 style="font-size:1.6rem">You might also need</h2></div></div>
      <div class="pgrid"><?php foreach ($related as $r) echo productCard($r); ?></div>
    </section>
  <?php endif; ?>
</div>
<?php siteClose();

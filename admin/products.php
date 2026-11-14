<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_active') {
        $db->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
    } elseif ($action === 'toggle_featured') {
        $db->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?')->execute([$pid]);
    } elseif ($action === 'price') {
        $v = trim((string) ($_POST['price'] ?? ''));
        $db->prepare('UPDATE products SET price = ? WHERE id = ?')->execute([$v === '' ? null : round((float) $v, 2), $pid]);
        audit('product_price', '#' . $pid, $v);
        if (!empty($_POST['ajax'])) jsonResponse(['ok' => true]);
    }
    redirect('admin/products.php?' . http_build_query(array_filter(['cat' => $_POST['cat'] ?? null, 'q' => $_POST['q'] ?? null])));
}

$catId = (int) ($_GET['cat'] ?? 0);
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$where = ['1=1'];
$args = [];
if ($catId) { $where[] = '(p.category_id = ? OR p.id IN (SELECT product_id FROM product_categories WHERE category_id = ?))'; array_push($args, $catId, $catId); }
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; array_push($args, "%{$q}%", "%{$q}%"); }
$s = $db->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                          (SELECT COALESCE(SUM(qty),0) FROM quote_items i WHERE i.product_id = p.id) AS requested
                   FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort_order, p.id');
$s->execute($args);
$rows = attachImages($s->fetchAll());

adminOpen('products', 'Products', count($rows) . ' products. Change a price right in the list — it saves when you leave the box.',
    '<a class="btn btn-primary" href="product.php"><i class="fa-solid fa-plus"></i>Add product</a>');
?>
<div class="card">
  <form class="toolbar" method="get">
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU" aria-label="Search products">
    <select name="cat" onchange="this.form.submit()" aria-label="Category"><option value="">All categories</option><?php foreach (allCategories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ghost btn-sm">Filter</button>
  </form>
  <table class="tbl"><thead><tr><th>Product</th><th>Category</th><th class="num">Price (R)</th><th class="num">Requested</th><th>Shown</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $p): ?>
    <tr style="<?= $p['is_active'] ? '' : 'opacity:.55' ?>">
      <td class="cell-main"><div class="who"><img class="thumb" src="<?= e(imgUrl($p['images'][0] ?? null, true)) ?>" alt="" width="44" height="44" loading="lazy"><div><strong><a href="product.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a></strong><small><?= $p['sku'] ? 'SKU ' . e($p['sku']) . ' · ' : '' ?><?= count($p['images']) ?> photo<?= count($p['images']) === 1 ? '' : 's' ?><?= onSale($p) ? ' · <span style="color:var(--coral)">on sale (was ' . rand_($p['regular_price']) . ')</span>' : '' ?></small></div></div></td>
      <td data-label="Category"><span class="muted"><?= e($p['category_name'] ?: '—') ?></span></td>
      <td class="num" data-label="Price"><input class="input price-inline" data-id="<?= (int) $p['id'] ?>" inputmode="decimal" value="<?= $p['price'] !== null ? e(rtrim(rtrim(number_format((float) $p['price'], 2, '.', ''), '0'), '.')) : '' ?>" style="max-width:100px;text-align:right;padding:.4rem .6rem" aria-label="Price of <?= e($p['name']) ?>"></td>
      <td class="num" data-label="Requested"><?= (int) $p['requested'] ?></td>
      <td data-label="Shown">
        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle_active"><button class="tag <?= $p['is_active'] ? 'tag-on' : 'tag-off' ?>" style="border:0;cursor:pointer"><?= $p['is_active'] ? 'Live' : 'Hidden' ?></button></form>
        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="toggle_featured"><button class="tag <?= $p['is_featured'] ? 'tag-google' : '' ?>" style="border:0;cursor:pointer" title="Featured on the homepage"><i class="fa-<?= $p['is_featured'] ? 'solid' : 'regular' ?> fa-star"></i></button></form>
      </td>
      <td><div class="row-actions"><a class="btn-xs" href="product.php?id=<?= (int) $p['id'] ?>"><i class="fa-solid fa-pen"></i>Edit</a><a class="btn-xs" href="<?= e(productUrl($p)) ?>" target="_blank" aria-label="View on site"><i class="fa-solid fa-eye"></i></a></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php adminClose('<script>
document.querySelectorAll(".price-inline").forEach(function (i) {
  var old = i.value;
  i.addEventListener("change", function () {
    pmPost("admin/products.php", { action: "price", id: i.dataset.id, price: i.value, ajax: 1 }).then(function (r) {
      if (r.ok) { pmToast("Price saved"); old = i.value; } else { i.value = old; }
    });
  });
});
</script>');

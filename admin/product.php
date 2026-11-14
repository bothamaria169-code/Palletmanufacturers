<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$p  = $id ? productById($id) : null;
if ($id && !$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }

/** Saves an uploaded photo as WebP (1400px) + a 560px thumbnail; returns the stored path. */
function saveProductImage(array $file, string $slug): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > MAX_IMG_SIZE) return null;
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => imagecreatefromwebp($file['tmp_name']),
        default        => imagecreatefromgif($file['tmp_name']),
    };
    if (!$src) return null;
    if (!is_dir(PRODUCT_IMG_DIR)) mkdir(PRODUCT_IMG_DIR, 0755, true);
    $name = substr($slug, 0, 80) . '-' . bin2hex(random_bytes(3));
    foreach ([1400 => '', 560 => '-sm'] as $max => $suffix) {
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($dst, PRODUCT_IMG_DIR . $name . $suffix . '.webp', 82);
        imagedestroy($dst);
    }
    imagedestroy($src);
    return 'uploads/products/' . $name . '.webp';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete' && $p) {
        $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        audit('product_delete', $p['name']);
        flash('success', 'Product deleted. Old quotes keep the line name and price.');
        redirect('admin/products.php');
    }
    if ($action === 'img_delete' && $p) {
        $img = $db->prepare('SELECT path FROM product_images WHERE id = ? AND product_id = ?');
        $img->execute([(int) $_POST['img'], $id]);
        if ($path = $img->fetchColumn()) {
            $db->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int) $_POST['img']]);
            if (str_starts_with($path, 'uploads/')) { @unlink(BASE_DIR . '/' . $path); @unlink(BASE_DIR . '/' . substr($path, 0, -5) . '-sm.webp'); }
        }
        redirect('admin/product.php?id=' . $id . '#photos');
    }
    if ($action === 'img_first' && $p) {
        $db->prepare('UPDATE product_images SET sort_order = sort_order + 1 WHERE product_id = ?')->execute([$id]);
        $db->prepare('UPDATE product_images SET sort_order = 0 WHERE id = ? AND product_id = ?')->execute([(int) $_POST['img'], $id]);
        redirect('admin/product.php?id=' . $id . '#photos');
    }

    $name = postText('name', 200);
    if ($name === '') { flash('error', 'Give the product a name.'); redirect('admin/product.php' . ($id ? '?id=' . $id : '')); }
    $slug = slugify(postText('slug', 200) ?: $name);
    $clash = $db->prepare('SELECT id FROM products WHERE slug = ? AND id <> ?');
    $clash->execute([$slug, $id]);
    if ($clash->fetchColumn()) $slug .= '-' . bin2hex(random_bytes(2));
    $num = fn(string $k) => ($v = trim((string) ($_POST[$k] ?? ''))) === '' ? null : round((float) str_replace([' ', ','], ['', '.'], $v), 2);
    $vals = [(int) ($_POST['category_id'] ?? 0) ?: null, $name, $slug, postText('sku', 60) ?: null, $num('price'), $num('regular_price'),
             trim((string) ($_POST['short_description'] ?? '')) ?: null, trim((string) ($_POST['description'] ?? '')) ?: null,
             !empty($_POST['is_active']) ? 1 : 0, !empty($_POST['is_featured']) ? 1 : 0, (int) ($_POST['sort_order'] ?? 0)];
    if ($p) {
        $db->prepare('UPDATE products SET category_id=?, name=?, slug=?, sku=?, price=?, regular_price=?, short_description=?, description=?, is_active=?, is_featured=?, sort_order=? WHERE id=?')
           ->execute([...$vals, $id]);
        audit('product_save', $name);
    } else {
        $db->prepare('INSERT INTO products (category_id, name, slug, sku, price, regular_price, short_description, description, is_active, is_featured, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
           ->execute($vals);
        $id = (int) $db->lastInsertId();
        audit('product_create', $name);
    }
    $db->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$id]);
    $pc = $db->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)');
    foreach (array_unique(array_filter(array_map('intval', array_merge((array) ($_POST['extra_cats'] ?? []), [(int) ($_POST['category_id'] ?? 0)])))) as $cid) $pc->execute([$id, $cid]);

    $added = 0;
    $files = $_FILES['photos'] ?? null;
    if ($files && is_array($files['name'])) {
        $next = (int) $db->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ' . $id)->fetchColumn();
        foreach ($files['name'] as $i => $n) {
            $path = saveProductImage(['tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], $slug);
            if ($path) { $db->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)')->execute([$id, $path, $next++]); $added++; }
        }
    }
    flash('success', 'Product saved' . ($added ? " with {$added} new photo" . ($added > 1 ? 's' : '') : '') . '.');
    redirect('admin/product.php?id=' . $id);
}

$extra = $p ? $db->query('SELECT category_id FROM product_categories WHERE product_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN) : [];
$imgs = $p ? $db->query('SELECT * FROM product_images WHERE product_id = ' . $id . ' ORDER BY sort_order, id')->fetchAll() : [];
$v = fn(string $k, $d = '') => $p[$k] ?? $d;
$num = fn($n) => $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
require_once BASE_DIR . '/includes/ai.php';

adminOpen('products', $p ? 'Edit product' : 'Add product', $p ? e($p['name']) : 'A new pallet for the shop.',
    $p ? '<a class="btn btn-ghost" href="' . e(productUrl($p)) . '" target="_blank"><i class="fa-solid fa-eye"></i>View on site</a>' : '');
?>
<form method="post" enctype="multipart/form-data">
<?= csrfField() ?>
<div class="grid-2">
  <div>
    <div class="card">
      <div class="card__head"><h2>Details</h2></div>
      <div class="card__body">
        <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e($v('name')) ?>" placeholder="4 Way Entry Pallet | 1m x 1.2m | Industrial A Grade"></div>
        <div class="form-grid">
          <div class="field"><label for="price">Price (R) <span class="hint">blank = "Price on request"</span></label><input id="price" name="price" inputmode="decimal" value="<?= e($num($v('price', null))) ?>"></div>
          <div class="field"><label for="regular_price">Was price (R) <span class="hint">shows a sale badge</span></label><input id="regular_price" name="regular_price" inputmode="decimal" value="<?= e($num($v('regular_price', null))) ?>"></div>
          <div class="field"><label for="sku">SKU</label><input id="sku" name="sku" value="<?= e($v('sku')) ?>"></div>
          <div class="field"><label for="slug">Web address</label><input id="slug" name="slug" value="<?= e($v('slug')) ?>" placeholder="made from the name"></div>
        </div>
        <div class="field">
          <label for="short_description">Short description <span class="hint">top of the product page</span></label>
          <textarea id="short_description" name="short_description" style="min-height:80px"><?= e($v('short_description')) ?></textarea>
        </div>
        <div class="field">
          <label for="description">Full description <span class="hint">blank line = new paragraph · "- " = bullet</span></label>
          <textarea id="description" name="description" style="min-height:220px"><?= e($v('description')) ?></textarea>
        </div>
        <div class="form-foot">
          <button type="button" class="btn btn-ghost" id="aiBtn" <?= aiEnabled() ? '' : 'disabled title="Add a Groq key in Settings → AI"' ?>><i class="fa-solid fa-wand-magic-sparkles"></i>✨ Write with AI</button>
          <?php if (!aiEnabled()): ?><span class="hint">Add a Groq key in <a href="settings.php?tab=ai">Settings → AI</a> to use this.</span><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card" id="photos">
      <div class="card__head"><h2>Photos</h2><p>JPG, PNG or WebP up to 8 MB. Saved as WebP automatically.</p></div>
      <div class="card__body">
        <?php if ($imgs): ?>
          <div class="img-grid" style="margin-bottom:1rem">
            <?php foreach ($imgs as $i => $img): ?>
              <figure><img src="<?= e(imgUrl($img['path'], true)) ?>" alt="" loading="lazy"><figcaption>
                <?php if ($i): ?><button class="btn-xs" form="imgFirst<?= (int) $img['id'] ?>" title="Make main photo"><i class="fa-solid fa-star"></i></button><?php else: ?><span class="tag tag-google">Main</span><?php endif; ?>
                <button class="btn-xs danger" form="imgDel<?= (int) $img['id'] ?>" title="Delete photo"><i class="fa-solid fa-trash-can"></i></button>
              </figcaption></figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="Upload photos">
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card__head"><h2>Publish</h2></div>
      <div class="card__body">
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= !$p || $p['is_active'] ? 'checked' : '' ?>> <span>Live on the website</span></label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= $p && $p['is_featured'] ? 'checked' : '' ?>> <span>Featured on the homepage</span></label>
        <div class="field"><label for="sort_order">Sort order <span class="hint">lower shows first</span></label><input id="sort_order" type="number" name="sort_order" value="<?= (int) $v('sort_order', 0) ?>"></div>
        <div class="form-foot"><button class="btn btn-primary" name="action" value="save"><i class="fa-solid fa-floppy-disk"></i>Save product</button></div>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Category</h2></div>
      <div class="card__body">
        <div class="field"><label for="category_id">Main category</label><select id="category_id" name="category_id"><?php foreach (allCategories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id', 0) === (int) $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <label class="hint" style="display:block;margin-bottom:.4rem">Also show under</label>
        <div class="chips">
          <?php foreach (allCategories() as $c): ?><label class="chip-check"><input type="checkbox" name="extra_cats[]" value="<?= (int) $c['id'] ?>" <?= in_array($c['id'], $extra) ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php if ($p): ?>
      <div style="text-align:right"><button class="btn-xs danger" form="delForm"><i class="fa-solid fa-trash-can"></i>Delete product</button></div>
    <?php endif; ?>
  </div>
</div>
</form>
<?php foreach ($imgs as $img): ?>
  <form method="post" id="imgDel<?= (int) $img['id'] ?>" data-confirm="Delete this photo?"><?= csrfField() ?><input type="hidden" name="action" value="img_delete"><input type="hidden" name="img" value="<?= (int) $img['id'] ?>"></form>
  <form method="post" id="imgFirst<?= (int) $img['id'] ?>"><?= csrfField() ?><input type="hidden" name="action" value="img_first"><input type="hidden" name="img" value="<?= (int) $img['id'] ?>"></form>
<?php endforeach; ?>
<?php if ($p): ?><form method="post" id="delForm" data-confirm="Delete <?= e($p['name']) ?>? This can't be undone."><?= csrfField() ?><input type="hidden" name="action" value="delete"></form><?php endif; ?>
<?php adminClose('<script>
var ai = document.getElementById("aiBtn");
if (ai) ai.onclick = function () {
  var sel = document.getElementById("category_id");
  ai.disabled = true; ai.classList.add("is-busy");
  pmPost("ajax/admin_ai.php", { name: document.getElementById("name").value, category: sel.options[sel.selectedIndex].text,
    short_description: document.getElementById("short_description").value, description: document.getElementById("description").value })
  .then(function (r) {
    ai.disabled = false; ai.classList.remove("is-busy");
    if (!r.ok) { pmToast(r.error || "AI failed"); return; }
    if (r.short) document.getElementById("short_description").value = r.short;
    document.getElementById("description").value = r.description;
    pmToast("Written — check it, then Save product");
  }).catch(function () { ai.disabled = false; ai.classList.remove("is-busy"); pmToast("Network problem"); });
};
</script>');

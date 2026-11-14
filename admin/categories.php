<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$cid]);
        audit('category_delete', '#' . $cid);
        flash('success', 'Category deleted. Its products are now uncategorised — give them a new category.');
    } else {
        $name = postText('name', 100);
        if ($name === '') { flash('error', 'Name is required.'); redirect('admin/categories.php'); }
        $slug = slugify(postText('slug', 120) ?: $name);
        $parent = (int) ($_POST['parent_id'] ?? 0) ?: null;
        if ($parent === $cid) $parent = null;
        $vals = [$parent, $name, $slug, postText('description', 600) ?: null, postText('icon', 40) ?: 'fa-pallet', (int) ($_POST['sort_order'] ?? 0)];
        try {
            if ($cid) {
                $db->prepare('UPDATE categories SET parent_id=?, name=?, slug=?, description=?, icon=?, sort_order=? WHERE id=?')->execute([...$vals, $cid]);
            } else {
                $db->prepare('INSERT INTO categories (parent_id, name, slug, description, icon, sort_order) VALUES (?,?,?,?,?,?)')->execute($vals);
            }
            audit('category_save', $name);
            flash('success', 'Category saved.');
        } catch (PDOException $e) {
            flash('error', $e->getCode() === '23000' ? 'That web address is already used by another category.' : 'Could not save.');
        }
    }
    redirect('admin/categories.php');
}

$edit = isset($_GET['id']) ? categoryById((int) $_GET['id']) : null;
adminOpen('categories', 'Categories', 'The shop menu, filters and category pages come from this list.');
?>
<div class="grid-2">
  <div class="card">
    <table class="tbl"><thead><tr><th>Category</th><th>Web address</th><th class="num">Products</th><th></th></tr></thead><tbody>
      <?php foreach (allCategories() as $c): ?>
        <tr>
          <td class="cell-main"><div class="who"><div class="kpi__ico" style="width:36px;height:36px"><i class="fa-solid <?= e($c['icon'] ?: 'fa-pallet') ?>"></i></div><div><strong><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></strong><small><?= e(excerpt($c['description'], 70)) ?></small></div></div></td>
          <td data-label="Address"><span class="muted">?cat=<?= e($c['slug']) ?></span></td>
          <td class="num" data-label="Products"><?= (int) $c['product_count'] ?></td>
          <td><div class="row-actions"><a class="btn-xs" href="?id=<?= (int) $c['id'] ?>"><i class="fa-solid fa-pen"></i>Edit</a>
            <form method="post" data-confirm="Delete <?= e($c['name']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn-xs danger" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button></form></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <div class="card__head"><h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Add a category' ?></h2><?php if ($edit): ?><a href="categories.php">+ New</a><?php endif; ?></div>
    <form class="card__body" method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="form-grid">
        <div class="field"><label for="parent_id">Inside</label><select id="parent_id" name="parent_id"><option value="">— Top level —</option><?php foreach (topCategories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ($edit['parent_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="slug">Web address</label><input id="slug" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="made from the name"></div>
        <div class="field"><label for="icon">Icon <span class="hint"><a href="https://fontawesome.com/search?o=r&m=free&s=solid" target="_blank" rel="noopener">Font Awesome</a> name</span></label><input id="icon" name="icon" value="<?= e($edit['icon'] ?? 'fa-pallet') ?>"></div>
        <div class="field"><label for="sort_order">Sort order</label><input id="sort_order" type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
      </div>
      <div class="field"><label for="description">Description <span class="hint">top of the category page + Google</span></label><textarea id="description" name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="form-foot"><button class="btn btn-primary">Save category</button></div>
    </form>
  </div>
</div>
<?php adminClose();

<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$pid]);
        flash('success', 'Article deleted.');
        redirect('admin/posts.php');
    }
    $title = postText('title', 220);
    if ($title === '') { flash('error', 'Title is required.'); redirect('admin/posts.php?id=' . $pid); }
    $slug = slugify(postText('slug', 200) ?: $title);
    $c = $db->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ?');
    $c->execute([$slug, $pid]);
    if ($c->fetchColumn()) $slug .= '-' . bin2hex(random_bytes(2));
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['published_at'] ?? '')) ? $_POST['published_at'] . ' 09:00:00' : date('Y-m-d H:i:s');
    $vals = [$slug, $title, postText('excerpt', 600) ?: null, trim((string) ($_POST['body'] ?? '')), !empty($_POST['is_published']) ? 1 : 0, $date];
    if ($pid) $db->prepare('UPDATE posts SET slug=?, title=?, excerpt=?, body=?, is_published=?, published_at=? WHERE id=?')->execute([...$vals, $pid]);
    else { $db->prepare('INSERT INTO posts (slug, title, excerpt, body, is_published, published_at) VALUES (?,?,?,?,?,?)')->execute($vals); $pid = (int) $db->lastInsertId(); }
    audit('post_save', $title);
    flash('success', 'Article saved.');
    redirect('admin/posts.php?id=' . $pid);
}

$edit = isset($_GET['id']) ? $db->query('SELECT * FROM posts WHERE id = ' . (int) $_GET['id'])->fetch() : null;
$isNew = isset($_GET['new']);
$rows = $db->query('SELECT id, slug, title, is_published, published_at FROM posts ORDER BY published_at DESC')->fetchAll();

adminOpen('posts', 'Blog', 'Pallet guides help customers find you on Google.', '<a class="btn btn-primary" href="?new=1"><i class="fa-solid fa-plus"></i>New article</a>');
if ($edit || $isNew): ?>
  <div class="card">
    <div class="card__head"><h2><?= $edit ? 'Edit article' : 'New article' ?></h2><a href="posts.php">← All articles</a></div>
    <form class="card__body" method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="field"><label for="title">Title</label><input id="title" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="form-grid">
        <div class="field"><label for="slug">Web address</label><input id="slug" name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="made from the title"></div>
        <div class="field"><label for="published_at">Date</label><input id="published_at" type="date" name="published_at" value="<?= e(substr((string) ($edit['published_at'] ?? date('Y-m-d')), 0, 10)) ?>"></div>
      </div>
      <div class="field"><label for="excerpt">Summary <span class="hint">shown on the blog list and Google</span></label><textarea id="excerpt" name="excerpt" style="min-height:70px"><?= e($edit['excerpt'] ?? '') ?></textarea></div>
      <div class="field"><label for="body">Article <span class="hint">blank line = new paragraph · short line on its own = heading · "- " = bullet</span></label><textarea id="body" name="body" style="min-height:360px"><?= e($edit['body'] ?? '') ?></textarea></div>
      <label class="check"><input type="checkbox" name="is_published" value="1" <?= !$edit || $edit['is_published'] ? 'checked' : '' ?>> <span>Published</span></label>
      <div class="form-foot"><button class="btn btn-primary">Save article</button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= APP_URL ?>/post.php?s=<?= e($edit['slug']) ?>" target="_blank">View</a><?php endif; ?></div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <table class="tbl"><thead><tr><th>Title</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="cell-main"><strong><a href="?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></strong></td><td data-label="Date"><?= date('j M Y', strtotime($r['published_at'])) ?></td>
        <td data-label="Status"><span class="tag <?= $r['is_published'] ? 'tag-on' : 'tag-off' ?>"><?= $r['is_published'] ? 'Published' : 'Draft' ?></span></td>
        <td><div class="row-actions"><a class="btn-xs" href="?id=<?= (int) $r['id'] ?>">Edit</a><form method="post" data-confirm="Delete this article?"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-xs danger" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button></form></div></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif;
adminClose();

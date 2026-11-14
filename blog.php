<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
$posts = getDB()->query('SELECT * FROM posts WHERE is_published = 1 ORDER BY published_at DESC')->fetchAll();
siteOpen('Pallet Guide – Tips on Pallet Sizes, Repairs & Recycling | ' . APP_NAME, [
    'description' => 'Practical guides on choosing pallets, repair economics, recycling and eco-friendly timber packaging from South African pallet specialists.',
    'canonical' => APP_URL . '/blog.php',
], 'blog');
?>
<section class="page-head"><div class="wrap"><nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <span>Pallet guide</span></nav><h1>Pallet guide</h1><p>Straight answers on pallet sizes, repairs, recycling and getting the most value from your pallets.</p></div></section>
<div class="wrap section tight">
  <?php if ($posts): ?>
    <div class="posts">
      <?php foreach ($posts as $p): ?>
        <a class="post-card" href="<?= APP_URL ?>/post.php?s=<?= e($p['slug']) ?>"><time><?= date('j M Y', strtotime($p['published_at'])) ?></time><h3><?= e($p['title']) ?></h3><p><?= e(excerpt($p['excerpt'] ?: $p['body'], 160)) ?></p><span>Read more →</span></a>
      <?php endforeach; ?>
    </div>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-newspaper"></i><h2>Articles coming soon</h2></div><?php endif; ?>
</div>
<?php siteClose();

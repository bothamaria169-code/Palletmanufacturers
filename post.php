<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
$s = getDB()->prepare('SELECT * FROM posts WHERE slug = ? AND is_published = 1');
$s->execute([(string) ($_GET['s'] ?? '')]);
$post = $s->fetch();
if (!$post) {
    http_response_code(404);
    siteOpen('Article not found | ' . APP_NAME, ['noindex' => true]);
    echo '<div class="wrap empty-state" style="padding-block:96px"><i class="fa-solid fa-newspaper"></i><h1>Article not found</h1><a class="btn btn-primary" href="' . APP_URL . '/blog.php">All articles</a></div>';
    siteClose();
    exit;
}
$url = APP_URL . '/post.php?s=' . rawurlencode($post['slug']);
$ld = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post['title'], 'datePublished' => date('c', strtotime($post['published_at'])),
       'author' => ['@type' => 'Organization', 'name' => APP_NAME], 'publisher' => ['@type' => 'Organization', 'name' => APP_NAME], 'mainEntityOfPage' => $url];
siteOpen($post['title'] . ' | ' . APP_NAME, ['description' => excerpt($post['excerpt'] ?: $post['body'], 155), 'canonical' => $url, 'og_type' => 'article', 'schema' => [$ld]], 'blog');
?>
<article class="wrap article">
  <nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <a href="<?= APP_URL ?>/blog.php">Pallet guide</a></nav>
  <h1><?= e($post['title']) ?></h1>
  <p class="muted" style="margin-bottom:2rem"><?= date('j F Y', strtotime($post['published_at'])) ?> · <?= e(APP_NAME) ?></p>
  <div class="rich"><?= richText($post['body']) ?></div>
</article>
<?php siteClose();

<?php
declare(strict_types=1);

/**
 * Admin shell: sidebar, top bar and page header. Pages handle their POSTs
 * (which redirect), then call adminOpen() … adminClose().
 */
require_once dirname(__DIR__) . '/includes/boot.php';

requireAdmin();
$ADMIN = getCurrentUser();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireCsrf();

function adminOpen(string $active, string $title, string $sub = '', string $actions = ''): void
{
    global $ADMIN;
    $db = getDB();
    $newQuotes = (int) $db->query("SELECT COUNT(*) FROM quotes WHERE status IN ('new','reviewing')")->fetchColumn();
    $unread    = (int) $db->query('SELECT COUNT(*) FROM enquiries WHERE is_read = 0')->fetchColumn();
    $newUsers  = (int) $db->query('SELECT COUNT(*) FROM users WHERE created_at > (NOW() - INTERVAL 1 DAY)')->fetchColumn();
    $unpaid    = (int) $db->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
    $failed    = (int) $db->query("SELECT COUNT(*) FROM sent_emails WHERE status = 'failed' AND created_at > (NOW() - INTERVAL 1 DAY)")->fetchColumn();

    $nav = [
        'Sales' => [
            ['dashboard', 'index.php',     'fa-gauge-high',    'Dashboard', 0],
            ['quotes',    'quotes.php',    'fa-file-invoice',  'Quotes', $newQuotes],
            ['invoices',  'invoices.php',  'fa-file-invoice-dollar', 'Invoices', $unpaid],
            ['inbox',     'inbox.php',     'fa-envelope',      'Inbox (sales@)', 0],
            ['enquiries', 'enquiries.php', 'fa-comments',      'Enquiries', $unread],
            ['users',     'users.php',     'fa-users',         'Customers', $newUsers],
        ],
        'Catalogue' => [
            ['products',  'products.php',  'fa-pallet',        'Products', 0],
            ['categories','categories.php','fa-folder-tree',   'Categories', 0],
            ['posts',     'posts.php',     'fa-newspaper',     'Blog', 0],
        ],
        'Setup' => [
            ['templates', 'templates.php', 'fa-pen-to-square', 'Email templates', 0],
            ['emails',    'emails.php',    'fa-envelope-open-text', 'Sent log', $failed],
            ['settings',  'settings.php',  'fa-sliders',       'Settings', 0],
            ['audit',     'audit.php',     'fa-clock-rotate-left', 'Audit log', 0],
        ],
    ];
    $name = $ADMIN['full_name'] ?: $ADMIN['email'];
    pageHead($title . ' – Admin – ' . APP_NAME, ['css' => ['style.css', 'admin.css', 'site.css'], 'noindex' => true]);
    ?>
<body class="adm">
<div class="adm-scrim" id="admScrim"></div>
<aside class="adm-side" id="admSide">
  <?= brandMark('brand--light') ?>
  <?php foreach ($nav as $group => $links): ?>
    <div class="adm-side__label"><?= e($group) ?></div>
    <?php foreach ($links as [$slug, $href, $icon, $label, $count]): ?>
      <a href="<?= APP_URL ?>/admin/<?= $href ?>" class="<?= $active === $slug ? 'is-active' : '' ?>">
        <i class="fa-solid <?= $icon ?>"></i><span><?= e($label) ?></span>
        <?php if ($count): ?><b class="n"><?= (int) $count ?></b><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="adm-side__foot">
    <a href="<?= APP_URL ?>/account.php"><i class="fa-solid fa-user-gear"></i><span>My account</span></a>
    <a href="<?= APP_URL ?>/" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View website</span></a>
  </div>
</aside>

<div class="adm-main">
  <header class="adm-top">
    <button class="icon-btn adm-burger" id="admBurger" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <form class="adm-search" action="<?= APP_URL ?>/admin/quotes.php" method="get" role="search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" name="q" placeholder="Search quotes by ref, name, email or company…" aria-label="Search quotes" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <div class="adm-top__right">
      <button class="icon-btn" id="themeBtn" title="Switch light / dark" aria-label="Switch theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
      <div class="adm-me">
        <div class="avatar"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
        <div class="adm-me__txt"><strong><?= e($name) ?></strong><small>Administrator</small></div>
      </div>
      <form method="POST" action="<?= APP_URL ?>/logout.php"><?= csrfField() ?><button class="icon-btn" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
    </div>
  </header>

  <main class="adm-body">
    <div class="adm-head">
      <div><h1><?= e($title) ?></h1><?php if ($sub): ?><p><?= $sub ?></p><?php endif; ?></div>
      <?php if ($actions): ?><div class="adm-head__actions"><?= $actions ?></div><?php endif; ?>
    </div>
    <?= flashHtml() ?>
<?php
}

function adminClose(string $scripts = ''): void
{
    appClose($scripts);
}

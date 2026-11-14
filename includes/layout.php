<?php
declare(strict_types=1);

/**
 * Page shells: <head>, the public header/footer, the split-screen auth pages
 * and the customer panel (same sidebar layout as the admin).
 */
function pageHead(string $title, array $opt = []): void
{
    $css     = $opt['css'] ?? ['style.css', 'site.css'];
    $noindex = $opt['noindex'] ?? false;
    $desc    = $opt['description'] ?? APP_TAGLINE;
    $canon   = $opt['canonical'] ?? null;
    $image   = $opt['image'] ?? APP_URL . '/assets/img/og.jpg';
    $v       = '?v=' . (string) @filemtime(BASE_DIR . '/assets/css/site.css');
    $pixel   = get_setting('meta_pixel_id');
    $ga      = get_setting('ga4_id');
    ?>
<!DOCTYPE html>
<html lang="en-ZA">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <?php if ($canon): ?><link rel="canonical" href="<?= e($canon) ?>"><?php endif; ?>
  <?php if ($gsc = get_setting('gsc_verification')): ?><meta name="google-site-verification" content="<?= e($gsc) ?>"><?php endif; ?>
  <meta name="theme-color" content="#1c1917">
  <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:type" content="<?= e($opt['og_type'] ?? 'website') ?>">
  <meta property="og:url" content="<?= e($canon ?: APP_URL) ?>">
  <meta property="og:image" content="<?= e($image) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/mark.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <?php foreach ($css as $file): ?>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/<?= e($file) . $v ?>">
  <?php endforeach; ?>
  <?php if (!empty($opt['preload'])): ?><link rel="preload" as="image" href="<?= e($opt['preload']) ?>"><?php endif; ?>
  <script>try{var t=localStorage.getItem('pm-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
  <?php foreach ($opt['schema'] ?? [] as $ld): ?>
  <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <?php endforeach; ?>
  <?php if ($ga && !IS_LOCAL): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode($ga) ?>);</script>
  <?php endif; ?>
  <?php if ($pixel && !IS_LOCAL): ?>
  <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',<?= json_encode($pixel) ?>);fbq('track','PageView');</script>
  <?php endif; ?>
</head>
<?php
}

function brandMark(string $class = ''): string
{
    return '<a href="' . APP_URL . '/" class="brand ' . e($class) . '" aria-label="' . APP_NAME . ' home">'
         . '<img src="' . APP_URL . '/assets/img/mark.svg" alt="" class="brand__mark" width="36" height="36">'
         . '<span class="brand__word">Durban\'s<em>Pallets</em><small>' . e(biz('biz_trading')) . '</small></span></a>';
}

function flashHtml(): string
{
    $out = '';
    foreach (takeFlashes() as [$type, $msg]) {
        $icon = $type === 'error' ? 'fa-circle-exclamation' : ($type === 'success' ? 'fa-circle-check' : 'fa-circle-info');
        $out .= '<div class="alert alert-' . e($type) . '"><i class="fa-solid ' . $icon . '"></i><span>' . e($msg) . '</span></div>';
    }
    return $out;
}

/** Organization / LocalBusiness schema for every public page. */
function businessSchema(): array
{
    return [
        '@context' => 'https://schema.org', '@type' => 'LocalBusiness', '@id' => APP_URL . '/#business',
        'name' => APP_NAME, 'alternateName' => biz('biz_trading'), 'url' => APP_URL . '/', 'image' => APP_URL . '/assets/img/og.jpg',
        'logo' => APP_URL . '/assets/img/mark.svg',
        'telephone' => biz('biz_phone'), 'email' => biz('biz_email'), 'priceRange' => 'R50 – R5000',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => '68 Columbine Place, Glen Anil', 'addressLocality' => 'Durban',
                      'addressRegion' => 'KwaZulu-Natal', 'postalCode' => '4051', 'addressCountry' => 'ZA'],
        'areaServed' => ['Durban', 'Umhlanga', 'Pinetown', 'Pietermaritzburg', 'KwaZulu-Natal', 'Johannesburg', 'Gauteng', 'South Africa'],
        'openingHours' => ['Mo-Fr 08:00-20:00', 'Sa-Su 10:00-13:00'],
    ];
}

// ── Public site ────────────────────────────────────────────────────
function siteOpen(string $title, array $opt = [], string $active = ''): void
{
    $opt['schema'] = array_merge([businessSchema()], $opt['schema'] ?? []);
    pageHead($title, $opt);
    $user  = isLoggedIn() ? getCurrentUser() : null;
    $count = basketCount();
    $tops  = topCategories();
    ?>
<body class="site">
<a class="skip" href="#main">Skip to content</a>
<div class="topbar">
  <div class="wrap">
    <span><i class="fa-solid fa-truck-fast"></i> Delivery across South Africa · Custom sizes made to order</span>
    <span class="topbar__right">
      <a href="tel:<?= e(preg_replace('/[^\d+]/', '', biz('biz_phone'))) ?>"><i class="fa-solid fa-phone"></i> <?= e(biz('biz_phone')) ?></a>
      <a href="mailto:<?= e(biz('biz_email')) ?>" class="hide-sm"><i class="fa-solid fa-envelope"></i> <?= e(biz('biz_email')) ?></a>
    </span>
  </div>
</div>
<header class="site-nav">
  <div class="wrap">
    <?= brandMark() ?>
    <nav class="mainnav" id="mainNav" aria-label="Main">
      <a href="<?= APP_URL ?>/" class="<?= $active === 'home' ? 'is-on' : '' ?>">Home</a>
      <div class="dd">
        <a href="<?= APP_URL ?>/shop.php" class="<?= $active === 'shop' ? 'is-on' : '' ?>">Pallets <i class="fa-solid fa-chevron-down"></i></a>
        <div class="dd__menu">
          <?php foreach ($tops as $c): ?>
            <a href="<?= e(categoryUrl($c)) ?>"><i class="fa-solid <?= e($c['icon'] ?: 'fa-pallet') ?>"></i><span><strong><?= e($c['name']) ?></strong><small><?= (int) $c['product_count'] ?> products</small></span></a>
            <?php foreach (childCategories((int) $c['id']) as $k): ?>
              <a href="<?= e(categoryUrl($k)) ?>" class="dd__sub"><?= e($k['name']) ?></a>
            <?php endforeach; ?>
          <?php endforeach; ?>
          <a href="<?= APP_URL ?>/shop.php" class="dd__all">View all pallets <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>
      <a href="<?= APP_URL ?>/about.php" class="<?= $active === 'about' ? 'is-on' : '' ?>">About</a>
      <a href="<?= APP_URL ?>/blog.php" class="<?= $active === 'blog' ? 'is-on' : '' ?>">Pallet guide</a>
      <a href="<?= APP_URL ?>/contact.php" class="<?= $active === 'contact' ? 'is-on' : '' ?>">Contact</a>
      <a href="<?= APP_URL ?>/<?= $user ? ($user['role'] === 'admin' ? 'admin/' : 'dashboard.php') : 'login.php' ?>" class="mainnav__acct"><i class="fa-regular fa-user"></i> <?= $user ? 'My account' : 'Sign in' ?></a>
    </nav>
    <div class="site-nav__right">
      <form class="navsearch" action="<?= APP_URL ?>/shop.php" role="search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" placeholder="Search pallets…" aria-label="Search pallets" value="<?= e($_GET['q'] ?? '') ?>"></form>
      <button class="icon-btn" id="themeBtn" title="Switch light / dark" aria-label="Switch light or dark theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
      <a class="icon-btn hide-sm" href="<?= APP_URL ?>/<?= $user ? ($user['role'] === 'admin' ? 'admin/' : 'dashboard.php') : 'login.php' ?>" title="<?= $user ? 'My account' : 'Sign in' ?>" aria-label="<?= $user ? 'My account' : 'Sign in' ?>"><i class="fa-regular fa-user"></i></a>
      <a class="btn btn-primary basket-btn" href="<?= APP_URL ?>/quote.php"><i class="fa-solid fa-file-invoice"></i><span class="hide-sm">My quote</span><b class="basket-n" id="basketN" <?= $count ? '' : 'hidden' ?>><?= $count ?></b></a>
      <button class="icon-btn burger" id="burger" aria-label="Menu" aria-controls="mainNav" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    </div>
  </div>
</header>
<main id="main">
<?php
}

function siteClose(bool $ctaBand = true): void
{
    $tops = topCategories();
    ?>
</main>
<?php if ($ctaBand): ?>
<section class="section">
  <div class="wrap">
    <div class="cta-band">
      <div>
        <h2>Need pallets in bulk or a custom size?</h2>
        <p>Tell us the size, load and quantity. We'll send a priced quote — usually the same working day.</p>
      </div>
      <div class="cta-band__btns">
        <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/quote.php"><i class="fa-solid fa-file-invoice"></i>Request a quote</a>
        <a class="btn btn-wa btn-lg" href="<?= e(waLink('Hi, I would like a quote for pallets.')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp us</a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
<footer class="site-foot">
  <div class="wrap foot-grid">
    <div>
      <?= brandMark('brand--light') ?>
      <p class="foot-about">Stockists, manufacturers and recyclers of wooden, plastic, aluminium and galvanised pallets. Standard and custom sizes for warehousing, logistics, manufacturing and export.</p>
      <div class="socials">
        <?php if (biz('biz_facebook')): ?><a href="<?= e(biz('biz_facebook')) ?>" aria-label="Facebook" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
        <a href="<?= e(waLink()) ?>" aria-label="WhatsApp" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i></a>
        <a href="mailto:<?= e(biz('biz_email')) ?>" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
      </div>
    </div>
    <div>
      <h3>Pallets</h3>
      <?php foreach ($tops as $c): ?><a href="<?= e(categoryUrl($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h3>Customer service</h3>
      <a href="<?= APP_URL ?>/quote.php">Request a quote</a>
      <a href="<?= APP_URL ?>/dashboard.php">My account &amp; quotes</a>
      <a href="<?= APP_URL ?>/contact.php">Contact us</a>
      <a href="<?= APP_URL ?>/returns.php">Return policy</a>
      <a href="<?= APP_URL ?>/privacy.php">Privacy policy</a>
      <a href="<?= APP_URL ?>/terms.php">Terms &amp; conditions</a>
    </div>
    <div>
      <h3>Visit or call</h3>
      <p><i class="fa-solid fa-location-dot"></i> <a href="<?= e(biz('biz_maps')) ?>" target="_blank" rel="noopener"><?= e(biz('biz_address')) ?></a></p>
      <p><i class="fa-solid fa-phone"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', biz('biz_phone'))) ?>"><?= e(biz('biz_phone')) ?></a></p>
      <p><i class="fa-solid fa-envelope"></i> <a href="mailto:<?= e(biz('biz_email')) ?>"><?= e(biz('biz_email')) ?></a></p>
      <p><i class="fa-regular fa-clock"></i> <span style="white-space:pre-line"><?= e(biz('biz_hours')) ?></span></p>
    </div>
  </div>
  <div class="wrap foot-legal">
    <span>© <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(biz('biz_trading')) ?>. All rights reserved.</span>
    <span>Wholesale wooden, plastic &amp; aluminium pallets for sale in South Africa.</span>
  </div>
</footer>
<a class="wa-float" href="<?= e(waLink('Hi, I have a question about pallets.')) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.PM = { url: <?= json_encode(APP_URL) ?>, csrf: <?= json_encode(csrfToken()) ?> };</script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= (string) @filemtime(BASE_DIR . '/assets/js/app.js') ?>" defer></script>
</body>
</html>
<?php
}

// ── Sign in / sign up / reset ──────────────────────────────────────
function authOpen(string $title, string $heading, string $sub): void
{
    pageHead($title . ' – ' . APP_NAME, ['css' => ['style.css'], 'noindex' => true]);
    ?>
<body class="auth">
  <aside class="auth__brand">
    <?= brandMark('brand--light') ?>
    <div class="auth__pitch">
      <h2>All your pallet quotes,<br><span>in one place.</span></h2>
      <p>Request quotes for wooden, plastic, aluminium and galvanised pallets, see our prices the moment they're ready, and accept online.</p>
      <div class="auth__demo" aria-hidden="true">
        <div class="demo-file"><i class="fa-solid fa-pallet" style="color:var(--brand)"></i><div><strong>4 Way Entry Pallet · 1m × 1.2m</strong><small>Qty 200 · Industrial A grade</small></div></div>
        <div class="demo-bubble me">Request quote</div>
        <div class="demo-file"><i class="fa-solid fa-file-invoice" style="color:var(--mint)"></i><div><strong>Quote PM-26-0142 ready</strong><small>R17 000 incl. delivery · valid 14 days</small></div><i class="fa-solid fa-check-double"></i></div>
      </div>
      <ul class="auth__ticks">
        <li><i class="fa-solid fa-check"></i>Every quote saved with its prices and status</li>
        <li><i class="fa-solid fa-check"></i>Message our sales team about any quote</li>
        <li><i class="fa-solid fa-check"></i>Re-order your usual pallets in one click</li>
      </ul>
    </div>
  </aside>
  <main class="auth__main">
    <div class="auth__card">
      <div class="auth__mobile-brand"><?= brandMark() ?></div>
      <h1><?= e($heading) ?></h1>
      <p class="auth__sub"><?= $sub ?></p>
      <?= flashHtml() ?>
<?php
}

function authClose(): void
{
    ?>
    </div>
    <p class="auth__legal">© <?= date('Y') ?> <?= APP_NAME ?> · <a href="<?= APP_URL ?>/">Back to the shop</a> · <a href="<?= APP_URL ?>/privacy.php">Privacy</a> · <a href="<?= APP_URL ?>/terms.php">Terms</a></p>
  </main>
  <script>
    document.querySelectorAll('[data-toggle-pw]').forEach(function (b) {
      b.addEventListener('click', function () {
        var i = document.getElementById(b.dataset.togglePw);
        i.type = i.type === 'password' ? 'text' : 'password';
        b.innerHTML = i.type === 'password' ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
      });
    });
    document.querySelectorAll('form[data-busy]').forEach(function (f) {
      f.addEventListener('submit', function () { var b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.classList.add('is-busy'); } });
    });
  </script>
</body>
</html>
<?php
}

// ── Customer panel ─────────────────────────────────────────────────
function appOpen(string $active, string $title, string $sub = '', string $actions = ''): void
{
    $user = getCurrentUser();
    $counts = quoteCounts((int) $user['id']);
    $nav = [
        'My quotes' => [
            ['dashboard', 'dashboard.php', 'fa-gauge-high', 'Dashboard', 0],
            ['quotes',    'quotes.php',    'fa-file-invoice', 'All quotes', $counts['quoted']],
            ['new',       'quote.php',     'fa-file-circle-plus', 'New quote request', basketCount()],
        ],
        'Shop' => [
            ['shop',      'shop.php',      'fa-pallet', 'Browse pallets', 0],
            ['contact',   'contact.php',   'fa-headset', 'Contact sales', 0],
        ],
        'Me' => [
            ['account',   'account.php',   'fa-user-gear', 'Account & delivery', 0],
        ],
    ];
    $name = $user['full_name'] ?: $user['email'];
    pageHead($title . ' – ' . APP_NAME, ['css' => ['style.css', 'admin.css', 'site.css'], 'noindex' => true]);
    ?>
<body class="adm">
<div class="adm-scrim" id="admScrim"></div>
<aside class="adm-side" id="admSide">
  <?= brandMark('brand--light') ?>
  <?php foreach ($nav as $group => $links): ?>
    <div class="adm-side__label"><?= e($group) ?></div>
    <?php foreach ($links as [$slug, $href, $icon, $label, $count]): ?>
      <a href="<?= APP_URL . '/' . $href ?>" class="<?= $active === $slug ? 'is-active' : '' ?>">
        <i class="fa-solid <?= $icon ?>"></i><span><?= e($label) ?></span>
        <?php if ($count): ?><b class="n"><?= (int) $count ?></b><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="adm-side__foot">
    <?php if ($user['role'] === 'admin'): ?><a href="<?= APP_URL ?>/admin/"><i class="fa-solid fa-shield-halved"></i><span>Admin panel</span></a><?php endif; ?>
    <a href="<?= APP_URL ?>/"><i class="fa-solid fa-store"></i><span>Back to website</span></a>
  </div>
</aside>

<div class="adm-main">
  <header class="adm-top">
    <button class="icon-btn adm-burger" id="admBurger" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <form class="adm-search" action="<?= APP_URL ?>/shop.php" method="get" role="search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" name="q" placeholder="Search pallets…" aria-label="Search pallets">
    </form>
    <div class="adm-top__right">
      <button class="icon-btn" id="themeBtn" title="Switch light / dark" aria-label="Switch theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
      <div class="adm-me">
        <div class="avatar"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
        <div class="adm-me__txt"><strong><?= e($name) ?></strong><small><?= e($user['company_name'] ?: 'Customer') ?></small></div>
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

function appClose(string $scripts = ''): void
{
    ?>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.PM = { url: <?= json_encode(APP_URL) ?>, csrf: <?= json_encode(csrfToken()) ?> };</script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= (string) @filemtime(BASE_DIR . '/assets/js/app.js') ?>" defer></script>
<?= $scripts ?>
</body>
</html>
<?php
}

/** Quote line table (customer + admin view). */
function quoteTable(array $q): string
{
    $out = '<table class="tbl"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Line total</th></tr></thead><tbody>';
    foreach ($q['items'] as $it) {
        $line = $it['unit_price'] !== null ? (float) $it['unit_price'] * (int) $it['qty'] : null;
        $out .= '<tr><td class="cell-main"><div class="who">'
              . ($it['image'] ? '<img class="thumb" src="' . e(imgUrl($it['image'], true)) . '" alt="" width="44" height="44" loading="lazy">' : '<div class="thumb"></div>')
              . '<div><strong>' . ($it['product_slug'] ? '<a href="' . APP_URL . '/product.php?s=' . e($it['product_slug']) . '">' . e($it['name']) . '</a>' : e($it['name'])) . '</strong>'
              . ($it['notes'] ? '<small>' . e($it['notes']) . '</small>' : '') . '</div></div></td>'
              . '<td class="num" data-label="Qty">' . (int) $it['qty'] . '</td>'
              . '<td class="num" data-label="Unit price">' . ($it['unit_price'] !== null ? rand_($it['unit_price'], true) : '<span class="muted">TBC</span>') . '</td>'
              . '<td class="num" data-label="Line total"><strong>' . ($line !== null ? rand_($line, true) : '—') . '</strong></td></tr>';
    }
    if (!$q['items']) $out .= '<tr><td colspan="4" class="empty">Custom request — see the message below.</td></tr>';
    $out .= '</tbody></table><div class="totals">'
          . '<div><span>Subtotal</span><b>' . rand_($q['subtotal'], true) . '</b></div>'
          . ((float) $q['delivery_fee'] > 0 ? '<div><span>Delivery</span><b>' . rand_($q['delivery_fee'], true) . '</b></div>' : '')
          . ((float) $q['discount'] > 0 ? '<div><span>Discount</span><b>-' . rand_($q['discount'], true) . '</b></div>' : '')
          . '<div class="totals__grand"><span>Total</span><b>' . rand_($q['total'], true) . '</b></div>'
          . '<small>' . e(vatNote()) . '</small></div>';
    return $out;
}

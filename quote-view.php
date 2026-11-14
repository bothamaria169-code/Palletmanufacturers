<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$key  = (string) ($_GET['k'] ?? $_POST['k'] ?? '');
$user = isLoggedIn() ? getCurrentUser() : null;
$q    = $user ? quoteForUser($id, $user) : null;
$viaKey = false;
if (!$q && $key !== '' && hash_equals(quoteKey($id), $key)) {
    $q = quoteById($id);
    $viaKey = true;
}
if (!$q) {
    if (!$user) requireLogin();
    http_response_code(404);
    exit('Quote not found.');
}
$self = APP_URL . '/quote-view.php?id=' . $id . ($viaKey ? '&k=' . $key : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'message') {
        $body = postText('body', 3000);
        if ($body !== '') {
            addQuoteMessage($id, $user ? (int) $user['id'] : null, false, $body);
            flash('success', 'Message sent to our sales team.');
        }
    } elseif (in_array($action, ['accept', 'decline'], true) && $q['status'] === 'quoted') {
        $new = $action === 'accept' ? 'accepted' : 'declined';
        getDB()->prepare('UPDATE quotes SET status = ? WHERE id = ?')->execute([$new, $id]);
        $reason = postText('reason', 1000);
        if ($reason !== '') addQuoteMessage($id, $user ? (int) $user['id'] : null, false, ($new === 'accepted' ? 'Accepted: ' : 'Declined: ') . $reason);
        sendMail(get_setting('notify_email', ADMIN_EMAIL), 'Quote ' . $q['ref'] . ' ' . $new . ' by ' . $q['full_name'], emailLayout(
            'Quote ' . e($q['ref']) . ' was ' . $new,
            emailRows(['Customer' => $q['full_name'], 'Company' => $q['company_name'] ?: '—', 'Total' => rand_($q['total'], true), 'Phone' => $q['phone'] ?: '—']),
            'Open quote', APP_URL . '/admin/quote.php?id=' . $id), [], $q['email'], 'quote_' . $new);
        flash('success', $new === 'accepted' ? 'Thank you! Quote accepted — we\'ll send the invoice and confirm delivery.' : 'Quote declined. Thanks for letting us know.');
    }
    header('Location: ' . $self);
    exit;
}

$steps = ['new' => 'Requested', 'reviewing' => 'Being priced', 'quoted' => 'Quote ready', 'accepted' => 'Accepted', 'completed' => 'Delivered'];
$order = array_keys($steps);
$pos   = array_search($q['status'], $order, true);
$expired = $q['status'] === 'quoted' && $q['valid_until'] && strtotime($q['valid_until'] . ' 23:59:59') < time();
$showPrices = in_array($q['status'], ['quoted', 'accepted', 'completed', 'declined'], true);

$actions = '<button class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i>Print / PDF</button>';
if ($user && !$viaKey && $q['items']) $actions .= '<a class="btn btn-ghost" href="' . APP_URL . '/quote.php?reorder=' . $id . '"><i class="fa-solid fa-rotate-right"></i>Re-order</a>';

$body = function () use ($q, $steps, $pos, $expired, $showPrices, $id, $key, $viaKey, $user) { ?>
  <?php if (in_array($q['status'], ['declined', 'cancelled'], true)): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span>This quote was <?= e(strtolower(QUOTE_STATUSES[$q['status']])) ?>.</span></div>
  <?php else: ?>
    <div class="timeline" aria-label="Quote progress">
      <?php $i = 0; foreach ($steps as $k => $label): ?><div class="<?= $pos !== false && $i <= $pos ? 'done' : '' ?>"><?= e($label) ?></div><?php $i++; endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($q['status'] === 'quoted' && !$expired): ?>
    <div class="card" style="border-color:var(--brand);margin-bottom:1.2rem">
      <div class="card__body" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div><strong style="font-size:1.05rem">Your quote is ready: <?= rand_($q['total'], true) ?></strong><br><span class="muted">Valid until <?= $q['valid_until'] ? date('j F Y', strtotime($q['valid_until'])) : '—' ?>. Accept it and we'll invoice and book delivery.</span></div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="k" value="<?= e($key) ?>"><input type="hidden" name="action" value="accept"><button class="btn btn-primary"><i class="fa-solid fa-check"></i>Accept quote</button></form>
          <form method="post" data-confirm="Decline this quote?"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="k" value="<?= e($key) ?>"><input type="hidden" name="action" value="decline"><button class="btn btn-ghost">Decline</button></form>
        </div>
      </div>
    </div>
  <?php elseif ($expired): ?>
    <div class="alert alert-info"><i class="fa-solid fa-clock"></i><span>This quote expired on <?= date('j F Y', strtotime($q['valid_until'])) ?>. Send us a message below and we'll refresh the prices.</span></div>
  <?php elseif (!$showPrices): ?>
    <div class="alert alert-info"><i class="fa-solid fa-hourglass-half"></i><span>Our sales team is pricing this request. The amounts below are today's list prices — your final quote includes delivery and any bulk discount.</span></div>
  <?php endif; ?>

  <div class="grid-2">
    <div class="card">
      <div class="card__head"><h2>Items</h2><span class="muted" style="font-size:.82rem">Requested <?= date('j M Y, H:i', strtotime($q['created_at'])) ?></span></div>
      <?= quoteTable($q) ?>
      <?php if ($q['customer_note']): ?><div class="card__body" style="border-top:1px solid var(--line)"><strong>Note from our sales team</strong><p style="white-space:pre-line;color:var(--text-2);margin-top:.3rem"><?= e($q['customer_note']) ?></p></div><?php endif; ?>
    </div>
    <div>
      <div class="card">
        <div class="card__head"><h2>Delivery details</h2></div>
        <div class="card__body facts" style="display:grid;gap:.55rem;font-size:.9rem">
          <div><span class="muted">Name</span><br><strong><?= e($q['full_name']) ?></strong><?= $q['company_name'] ? ' · ' . e($q['company_name']) : '' ?></div>
          <div><span class="muted">Contact</span><br><?= e($q['email']) ?><?= $q['phone'] ? ' · ' . e($q['phone']) : '' ?></div>
          <div><span class="muted">Deliver to</span><br><?= e(trim(implode(', ', array_filter([$q['delivery_address'], $q['city'], $q['province']])))) ?: '—' ?></div>
          <?php if ($q['needed_by']): ?><div><span class="muted">Needed by</span><br><?= date('j F Y', strtotime($q['needed_by'])) ?></div><?php endif; ?>
          <?php if ($q['message']): ?><div><span class="muted">Your message</span><br><span style="white-space:pre-line"><?= e($q['message']) ?></span></div><?php endif; ?>
        </div>
      </div>
      <div class="card no-print">
        <div class="card__head"><h2>Messages</h2></div>
        <div class="msgs">
          <?php if (!$q['messages']): ?><p class="muted" style="font-size:.88rem">Questions about this quote? Message our sales team here — we reply by email too.</p><?php endif; ?>
          <?php foreach ($q['messages'] as $m): ?>
            <div class="msg <?= $m['from_admin'] ? '' : 'me' ?>"><?= e($m['body']) ?><small><?= $m['from_admin'] ? e(APP_NAME) . ' sales' : 'You' ?> · <?= e(timeAgo($m['created_at'])) ?></small></div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="msg-form"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="k" value="<?= e($key) ?>"><input type="hidden" name="action" value="message">
          <textarea name="body" required maxlength="3000" placeholder="Write a message…" aria-label="Message"></textarea><button class="btn btn-primary" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button></form>
      </div>
      <?php if ($viaKey && !$user): ?>
        <div class="card no-print"><div class="card__body"><strong>Keep all your quotes in one place</strong><p class="muted" style="font-size:.88rem;margin:.3rem 0 .8rem">Create a free account with <?= e($q['email']) ?> and this quote moves into your dashboard.</p>
          <a class="btn btn-dark btn-sm" href="<?= APP_URL ?>/register.php?email=<?= rawurlencode($q['email']) ?>"><i class="fa-solid fa-user-plus"></i>Create account</a></div></div>
      <?php endif; ?>
    </div>
  </div>
<?php };

$title = 'Quote ' . $q['ref'];
$sub = quoteStatusTag($q['status']) . ' &nbsp;·&nbsp; ' . e(count($q['items'])) . ' item' . (count($q['items']) === 1 ? '' : 's') . ' · ' . rand_($q['total'], true);
if ($user && !$viaKey) {
    appOpen('quotes', $title, $sub, $actions);
    $body();
    appClose();
} else {
    pageHead($title . ' – ' . APP_NAME, ['css' => ['style.css', 'admin.css', 'site.css'], 'noindex' => true]);
    ?>
<body class="site">
<header class="site-nav"><div class="wrap"><?= brandMark() ?><div class="site-nav__right"><a class="btn btn-ghost" href="<?= APP_URL ?>/shop.php">Shop</a><?= $user ? '<a class="btn btn-primary" href="' . APP_URL . '/dashboard.php">My dashboard</a>' : '<a class="btn btn-primary" href="' . APP_URL . '/login.php">Sign in</a>' ?></div></div></header>
<main class="wrap" style="padding-block:2rem 4rem">
  <div class="adm-head"><div><h1><?= e($title) ?></h1><p><?= $sub ?></p></div><div class="adm-head__actions"><?= $actions ?></div></div>
  <?= flashHtml() ?>
  <?php $body(); ?>
</main>
<div class="toast" id="toast"></div>
<script>window.PM = { url: <?= json_encode(APP_URL) ?>, csrf: <?= json_encode(csrfToken()) ?> };</script>
<script src="<?= APP_URL ?>/assets/js/app.js" defer></script>
</body></html>
<?php
}

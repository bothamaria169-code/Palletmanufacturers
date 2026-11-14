<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
requireLogin();
$user = getCurrentUser();
if ($user['role'] === 'admin' && !isset($_GET['as_customer'])) redirect('admin/');

$uid = (int) $user['id'];
$counts = quoteCounts($uid);
$db = getDB();
$recent = $db->prepare('SELECT q.*, (SELECT COUNT(*) FROM quote_items i WHERE i.quote_id = q.id) AS n_items,
                               (SELECT COUNT(*) FROM quote_messages m WHERE m.quote_id = q.id AND m.from_admin = 1) AS n_replies
                        FROM quotes q WHERE q.user_id = ? ORDER BY q.created_at DESC LIMIT 6');
$recent->execute([$uid]);
$recent = $recent->fetchAll();
$spent = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM quotes WHERE user_id = ? AND status IN ('accepted','completed')");
$spent->execute([$uid]);
$usual = $db->prepare('SELECT p.*, SUM(i.qty) AS total_qty FROM quote_items i JOIN quotes q ON q.id = i.quote_id JOIN products p ON p.id = i.product_id
                       WHERE q.user_id = ? AND p.is_active = 1 GROUP BY p.id ORDER BY total_qty DESC LIMIT 4');
$usual->execute([$uid]);
$usual = attachImages($usual->fetchAll());
$first = explode(' ', (string) ($user['full_name'] ?: 'there'))[0];

appOpen('dashboard', 'Hi ' . $first . ' 👋', 'Your pallet quotes, prices and orders in one place.',
    '<a class="btn btn-primary" href="' . APP_URL . '/shop.php"><i class="fa-solid fa-file-circle-plus"></i>New quote</a>');
?>
<div class="kpis">
  <div class="kpi"><div class="kpi__ico"><i class="fa-solid fa-file-invoice"></i></div><div><div class="kpi__label">Quotes requested</div><div class="kpi__value"><?= array_sum($counts) ?></div></div></div>
  <div class="kpi kpi--amber"><div class="kpi__ico"><i class="fa-solid fa-bell"></i></div><div><div class="kpi__label">Ready to accept</div><div class="kpi__value"><?= $counts['quoted'] ?></div><div class="kpi__delta">waiting for you</div></div></div>
  <div class="kpi kpi--violet"><div class="kpi__ico"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="kpi__label">Being priced</div><div class="kpi__value"><?= $counts['new'] + $counts['reviewing'] ?></div></div></div>
  <div class="kpi kpi--mint"><div class="kpi__ico"><i class="fa-solid fa-truck"></i></div><div><div class="kpi__label">Accepted orders</div><div class="kpi__value"><?= rand_($spent->fetchColumn()) ?></div><div class="kpi__delta"><?= $counts['accepted'] + $counts['completed'] ?> orders</div></div></div>
</div>

<?php if ($counts['quoted']): ?>
  <div class="alert alert-info"><i class="fa-solid fa-bell"></i><span>You have <?= $counts['quoted'] ?> quote<?= $counts['quoted'] > 1 ? 's' : '' ?> ready. Open <?= $counts['quoted'] > 1 ? 'them' : 'it' ?> to see the prices and accept. <a href="<?= APP_URL ?>/quotes.php?status=quoted">View now</a></span></div>
<?php endif; ?>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2>Recent quotes</h2><a href="<?= APP_URL ?>/quotes.php">See all</a></div>
    <?php if ($recent): ?>
      <table class="tbl"><thead><tr><th>Quote</th><th>Status</th><th class="num">Total</th><th></th></tr></thead><tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="cell-main"><strong><a href="<?= APP_URL ?>/quote-view.php?id=<?= (int) $r['id'] ?>"><?= e($r['ref']) ?></a></strong><br><small class="muted"><?= (int) $r['n_items'] ?> item<?= $r['n_items'] == 1 ? '' : 's' ?> · <?= e(timeAgo($r['created_at'])) ?><?= $r['n_replies'] ? ' · <i class="fa-solid fa-comment"></i> ' . (int) $r['n_replies'] : '' ?></small></td>
          <td data-label="Status"><?= quoteStatusTag($r['status']) ?></td>
          <td class="num" data-label="Total"><?= rand_($r['total'], true) ?></td>
          <td><div class="row-actions"><a class="btn-xs" href="<?= APP_URL ?>/quote-view.php?id=<?= (int) $r['id'] ?>">Open</a></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php else: ?>
      <div class="empty"><i class="fa-solid fa-file-invoice"></i>No quotes yet. <a href="<?= APP_URL ?>/shop.php">Browse pallets</a> and press "Add to quote".</div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <div class="card__head"><h2>Your usual pallets</h2><a href="<?= APP_URL ?>/shop.php">Shop</a></div>
      <?php if ($usual): ?>
        <?php foreach ($usual as $p): ?>
          <div class="list-row">
            <img class="thumb" src="<?= e(imgUrl($p['images'][0] ?? null, true)) ?>" alt="" width="44" height="44" style="width:44px;height:44px">
            <div class="list-row__main"><strong><a href="<?= e(productUrl($p)) ?>"><?= e($p['name']) ?></a></strong><small><?= rand_($p['price']) ?> each · ordered <?= (int) $p['total_qty'] ?></small></div>
            <button class="btn-xs" data-add="<?= (int) $p['id'] ?>"><i class="fa-solid fa-plus"></i>Add</button>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty"><i class="fa-solid fa-pallet"></i>Pallets you order often will show here for one-click re-ordering.</div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card__head"><h2>Need help?</h2></div>
      <div class="card__body" style="display:grid;gap:.6rem;font-size:.9rem">
        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', biz('biz_phone'))) ?>"><i class="fa-solid fa-phone"></i> <?= e(biz('biz_phone')) ?></a>
        <a href="<?= e(waLink()) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp our sales team</a>
        <a href="mailto:<?= e(biz('biz_email')) ?>"><i class="fa-solid fa-envelope"></i> <?= e(biz('biz_email')) ?></a>
      </div>
    </div>
  </div>
</div>
<?php appClose();

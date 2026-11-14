<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

$counts   = quoteCounts();
$monthWon = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status IN ('accepted','completed') AND updated_at >= '" . date('Y-m-01') . "'")->fetchColumn();
$pipeline = (float) $db->query("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status = 'quoted'")->fetchColumn();
$customers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$newCust  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at > (NOW() - INTERVAL 30 DAY)")->fetchColumn();
$sent     = $counts['quoted'] + $counts['accepted'] + $counts['completed'] + $counts['declined'];
$winRate  = $sent ? (int) round(($counts['accepted'] + $counts['completed']) / $sent * 100) : 0;

// Quote requests per day, last 30 days.
$days = [];
for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-{$i} days"))] = 0;
foreach ($db->query("SELECT DATE(created_at) d, COUNT(*) n FROM quotes WHERE created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(created_at)") as $r) $days[$r['d']] = (int) $r['n'];
$max = max(1, max($days));

$latest = $db->query("SELECT * FROM quotes ORDER BY FIELD(status,'new','reviewing') DESC, created_at DESC LIMIT 8")->fetchAll();
$top = $db->query('SELECT i.name, i.product_id, SUM(i.qty) qty, COUNT(DISTINCT i.quote_id) quotes FROM quote_items i GROUP BY i.product_id, i.name ORDER BY qty DESC LIMIT 6')->fetchAll();
$topMax = max(1, (int) ($top[0]['qty'] ?? 1));
$enq = $db->query('SELECT * FROM enquiries ORDER BY created_at DESC LIMIT 5')->fetchAll();

adminOpen('dashboard', 'Dashboard', 'Quotes, customers and enquiries at a glance.',
    '<a class="btn btn-ghost" href="' . APP_URL . '/admin/product.php"><i class="fa-solid fa-plus"></i>Product</a><a class="btn btn-primary" href="' . APP_URL . '/admin/quotes.php?status=new"><i class="fa-solid fa-file-invoice"></i>Price new quotes</a>');
?>
<div class="kpis">
  <div class="kpi"><div class="kpi__ico"><i class="fa-solid fa-inbox"></i></div><div><div class="kpi__label">To price</div><div class="kpi__value"><?= $counts['new'] + $counts['reviewing'] ?></div><div class="kpi__delta"><?= $counts['new'] ?> new · <?= $counts['reviewing'] ?> in progress</div></div></div>
  <div class="kpi kpi--amber"><div class="kpi__ico"><i class="fa-solid fa-paper-plane"></i></div><div><div class="kpi__label">Sent, awaiting reply</div><div class="kpi__value"><?= rand_($pipeline) ?></div><div class="kpi__delta"><?= $counts['quoted'] ?> quotes</div></div></div>
  <div class="kpi kpi--mint"><div class="kpi__ico"><i class="fa-solid fa-handshake"></i></div><div><div class="kpi__label">Won this month</div><div class="kpi__value"><?= rand_($monthWon) ?></div><div class="kpi__delta">win rate <b><?= $winRate ?>%</b></div></div></div>
  <div class="kpi kpi--violet"><div class="kpi__ico"><i class="fa-solid fa-users"></i></div><div><div class="kpi__label">Customers</div><div class="kpi__value"><?= $customers ?></div><div class="kpi__delta"><b>+<?= $newCust ?></b> in 30 days</div></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><div><h2>Quote requests — last 30 days</h2><p><?= array_sum($days) ?> requests</p></div></div>
    <div class="card__body">
      <div class="spark" role="img" aria-label="Quote requests per day for the last 30 days">
        <?php foreach ($days as $d => $n): ?><span style="height:<?= max(3, (int) round($n / $max * 100)) ?>%" title="<?= date('j M', strtotime($d)) ?>: <?= $n ?>" class="<?= $n ? '' : 'zero' ?>"></span><?php endforeach; ?>
      </div>
      <div class="spark__axis"><span><?= date('j M', strtotime(array_key_first($days))) ?></span><span>Today</span></div>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h2>Most requested pallets</h2></div>
    <div class="card__body">
      <?php if ($top): foreach ($top as $t): ?>
        <div class="bar-row"><span title="<?= e($t['name']) ?>" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['name']) ?></span><div class="track"><span style="width:<?= (int) round($t['qty'] / $topMax * 100) ?>%"></span></div><b><?= (int) $t['qty'] ?></b></div>
      <?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-chart-simple"></i>No quotes yet.</div><?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2>Latest quotes</h2><a href="quotes.php">All quotes</a></div>
    <?php if ($latest): ?>
    <table class="tbl"><thead><tr><th>Quote</th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($latest as $q): ?>
        <tr>
          <td class="cell-main"><strong><a href="quote.php?id=<?= (int) $q['id'] ?>"><?= e($q['ref']) ?></a></strong><br><small class="muted"><?= e(timeAgo($q['created_at'])) ?></small></td>
          <td data-label="Customer"><?= e($q['full_name']) ?><br><small class="muted"><?= e($q['company_name'] ?: $q['email']) ?></small></td>
          <td data-label="Status"><?= quoteStatusTag($q['status']) ?></td>
          <td class="num" data-label="Total"><?= rand_($q['total'], true) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="empty"><i class="fa-solid fa-file-invoice"></i>Quote requests from the website will appear here.</div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card__head"><h2>Latest enquiries</h2><a href="enquiries.php">All</a></div>
    <?php if ($enq): foreach ($enq as $m): ?>
      <a class="list-row" href="enquiries.php?id=<?= (int) $m['id'] ?>" style="color:inherit;text-decoration:none">
        <div class="avatar"><?= e(strtoupper(mb_substr($m['full_name'], 0, 1))) ?></div>
        <div class="list-row__main"><strong><?= $m['is_read'] ? '' : '<span class="tag tag-on">New</span> ' ?><?= e($m['full_name']) ?></strong><small><?= e(excerpt($m['message'], 70)) ?></small></div>
        <div class="list-row__side"><?= e(timeAgo($m['created_at'])) ?></div>
      </a>
    <?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-inbox"></i>Contact-form messages appear here.</div><?php endif; ?>
  </div>
</div>
<?php adminClose();

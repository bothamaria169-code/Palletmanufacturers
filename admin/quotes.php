<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

$status = (string) ($_GET['status'] ?? '');
$q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 30;
$where  = ['1=1'];
$args   = [];
if (isset(QUOTE_STATUSES[$status])) { $where[] = 'q.status = ?'; $args[] = $status; }
if ($q !== '') {
    $where[] = '(q.ref LIKE ? OR q.full_name LIKE ? OR q.email LIKE ? OR q.company_name LIKE ? OR q.phone LIKE ?)';
    array_push($args, ...array_fill(0, 5, '%' . $q . '%'));
}
$w = implode(' AND ', $where);
$c = getDB()->prepare("SELECT COUNT(*) FROM quotes q WHERE {$w}");
$c->execute($args);
$total = (int) $c->fetchColumn();
$s = getDB()->prepare("SELECT q.*, (SELECT COUNT(*) FROM quote_items i WHERE i.quote_id = q.id) n_items,
                              (SELECT COUNT(*) FROM quote_messages m WHERE m.quote_id = q.id AND m.from_admin = 0) n_msgs
                       FROM quotes q WHERE {$w} ORDER BY q.created_at DESC LIMIT {$per} OFFSET " . (($page - 1) * $per));
$s->execute($args);
$rows = $s->fetchAll();
$counts = quoteCounts();

adminOpen('quotes', 'Quotes', 'Every quote request from the website. Open one to price it and email it to the customer.',
    '<a class="btn btn-primary" href="quote.php?new=1"><i class="fa-solid fa-plus"></i>New quote</a>');
?>
<div class="tabs">
  <a href="?" class="<?= $status === '' ? 'is-on' : '' ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach (QUOTE_STATUSES as $k => $label): ?><a href="?status=<?= $k ?>" class="<?= $status === $k ? 'is-on' : '' ?>"><?= e($label) ?> (<?= $counts[$k] ?>)</a><?php endforeach; ?>
</div>
<div class="card">
  <form class="toolbar" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search ref, name, email, company or phone" aria-label="Search quotes">
    <button class="btn btn-ghost btn-sm">Search</button>
    <span class="count"><?= number_format($total) ?> quotes</span>
  </form>
  <?php if ($rows): ?>
  <table class="tbl"><thead><tr><th>Quote</th><th>Customer</th><th>Deliver to</th><th>Status</th><th class="num">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="cell-main"><strong><a href="quote.php?id=<?= (int) $r['id'] ?>"><?= e($r['ref']) ?></a></strong><br><small class="muted"><?= date('j M Y H:i', strtotime($r['created_at'])) ?> · <?= (int) $r['n_items'] ?> items<?= $r['n_msgs'] ? ' · <i class="fa-solid fa-comment"></i> ' . (int) $r['n_msgs'] : '' ?></small></td>
        <td data-label="Customer"><strong><?= e($r['full_name']) ?></strong><?= $r['user_id'] ? ' <i class="fa-solid fa-user-check" title="Has an account" style="color:var(--mint)"></i>' : '' ?><br><small class="muted"><?= e($r['company_name'] ?: $r['email']) ?></small></td>
        <td data-label="Deliver to"><span class="muted"><?= e(trim(($r['city'] ?? '') . ', ' . ($r['province'] ?? ''), ', ') ?: '—') ?></span></td>
        <td data-label="Status"><?= quoteStatusTag($r['status']) ?></td>
        <td class="num" data-label="Total"><strong><?= rand_($r['total'], true) ?></strong></td>
        <td><div class="row-actions"><a class="btn-xs" href="quote.php?id=<?= (int) $r['id'] ?>"><?= in_array($r['status'], ['new', 'reviewing'], true) ? '<i class="fa-solid fa-tag"></i>Price it' : 'Open' ?></a></div></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
  <?= pager($total, $page, $per, array_filter(['status' => $status ?: null, 'q' => $q ?: null])) ?>
  <?php else: ?><div class="empty"><i class="fa-solid fa-file-invoice"></i>No quotes match.</div><?php endif; ?>
</div>
<?php adminClose();

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
requireLogin();
$user = getCurrentUser();

$status = (string) ($_GET['status'] ?? '');
$where = 'q.user_id = ?';
$args = [(int) $user['id']];
if (isset(QUOTE_STATUSES[$status])) { $where .= ' AND q.status = ?'; $args[] = $status; }
$s = getDB()->prepare("SELECT q.*, (SELECT COUNT(*) FROM quote_items i WHERE i.quote_id = q.id) AS n_items,
                              (SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM quote_items i WHERE i.quote_id = q.id) AS item_names
                       FROM quotes q WHERE {$where} ORDER BY q.created_at DESC");
$s->execute($args);
$rows = $s->fetchAll();
$counts = quoteCounts((int) $user['id']);

appOpen('quotes', 'My quotes', 'Every quote you\'ve requested, with its prices and status.',
    '<a class="btn btn-primary" href="' . APP_URL . '/shop.php"><i class="fa-solid fa-file-circle-plus"></i>New quote</a>');
?>
<div class="tabs">
  <a href="?" class="<?= $status === '' ? 'is-on' : '' ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach (QUOTE_STATUSES as $k => $label): if (!$counts[$k]) continue; ?>
    <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'is-on' : '' ?>"><?= e($label) ?> (<?= $counts[$k] ?>)</a>
  <?php endforeach; ?>
</div>
<div class="card">
  <?php if ($rows): ?>
    <table class="tbl"><thead><tr><th>Quote</th><th>Items</th><th>Status</th><th>Valid until</th><th class="num">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="cell-main"><strong><a href="<?= APP_URL ?>/quote-view.php?id=<?= (int) $r['id'] ?>"><?= e($r['ref']) ?></a></strong><br><small class="muted"><?= date('j M Y', strtotime($r['created_at'])) ?></small></td>
        <td data-label="Items"><span class="muted"><?= e(excerpt($r['item_names'] ?: 'Custom request', 70)) ?></span></td>
        <td data-label="Status"><?= quoteStatusTag($r['status']) ?></td>
        <td data-label="Valid until"><?= $r['valid_until'] ? date('j M Y', strtotime($r['valid_until'])) : '—' ?></td>
        <td class="num" data-label="Total"><strong><?= rand_($r['total'], true) ?></strong></td>
        <td><div class="row-actions"><a class="btn-xs" href="<?= APP_URL ?>/quote-view.php?id=<?= (int) $r['id'] ?>">Open</a><?php if ($r['n_items']): ?><a class="btn-xs" href="<?= APP_URL ?>/quote.php?reorder=<?= (int) $r['id'] ?>"><i class="fa-solid fa-rotate-right"></i>Re-order</a><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php else: ?>
    <div class="empty"><i class="fa-solid fa-file-invoice"></i>No quotes here yet.</div>
  <?php endif; ?>
</div>
<?php appClose();

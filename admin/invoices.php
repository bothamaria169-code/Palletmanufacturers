<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

$status = (string) ($_GET['status'] ?? '');
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$where = ['1=1']; $args = [];
if (isset(INVOICE_STATUSES[$status])) { $where[] = 'i.status = ?'; $args[] = $status; }
if ($q !== '') { $where[] = '(i.invoice_no LIKE ? OR i.full_name LIKE ? OR i.email LIKE ? OR i.company_name LIKE ?)'; array_push($args, ...array_fill(0, 4, "%{$q}%")); }
$s = getDB()->prepare('SELECT i.*, q.ref AS quote_ref FROM invoices i LEFT JOIN quotes q ON q.id = i.quote_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC LIMIT 300');
$s->execute($args);
$rows = $s->fetchAll();
$sum = getDB()->query("SELECT status, COUNT(*) n, COALESCE(SUM(total),0) t FROM invoices GROUP BY status")->fetchAll(PDO::FETCH_UNIQUE);
$overdue = (float) getDB()->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status = 'unpaid' AND due_date < CURDATE()")->fetchColumn();

adminOpen('invoices', 'Invoices', 'Made from accepted quotes. Open a quote → <strong>Create invoice</strong>.');
?>
<div class="kpis">
  <div class="kpi kpi--amber"><div class="kpi__ico"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="kpi__label">Unpaid</div><div class="kpi__value"><?= rand_($sum['unpaid']['t'] ?? 0) ?></div><div class="kpi__delta"><?= (int) ($sum['unpaid']['n'] ?? 0) ?> invoices</div></div></div>
  <div class="kpi"><div class="kpi__ico" style="background:var(--coral-50);color:var(--coral)"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="kpi__label">Overdue</div><div class="kpi__value"><?= rand_($overdue) ?></div></div></div>
  <div class="kpi kpi--mint"><div class="kpi__ico"><i class="fa-solid fa-circle-check"></i></div><div><div class="kpi__label">Paid</div><div class="kpi__value"><?= rand_($sum['paid']['t'] ?? 0) ?></div><div class="kpi__delta"><?= (int) ($sum['paid']['n'] ?? 0) ?> invoices</div></div></div>
  <div class="kpi kpi--violet"><div class="kpi__ico"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><div class="kpi__label">All invoices</div><div class="kpi__value"><?= array_sum(array_map(fn($r) => (int) $r['n'], $sum)) ?></div></div></div>
</div>
<div class="tabs"><a href="?" class="<?= $status === '' ? 'is-on' : '' ?>">All</a><?php foreach (INVOICE_STATUSES as $k => $l): ?><a href="?status=<?= $k ?>" class="<?= $status === $k ? 'is-on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="card">
  <form class="toolbar" method="get"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search invoice no., name, email or company" aria-label="Search invoices"><button class="btn btn-ghost btn-sm">Search</button></form>
  <?php if ($rows): ?>
  <table class="tbl"><thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th>Status</th><th class="num">Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $late = $r['status'] === 'unpaid' && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
    <tr>
      <td class="cell-main"><strong><a href="invoice.php?id=<?= (int) $r['id'] ?>"><?= e($r['invoice_no']) ?></a></strong><br><small class="muted"><?= date('j M Y', strtotime($r['issue_date'])) ?><?= $r['quote_ref'] ? ' · ' . e($r['quote_ref']) : '' ?><?= $r['sent_at'] ? ' · emailed' : '' ?></small></td>
      <td data-label="Customer"><?= e($r['full_name']) ?><br><small class="muted"><?= e($r['company_name'] ?: $r['email']) ?></small></td>
      <td data-label="Due"><span style="<?= $late ? 'color:var(--coral);font-weight:700' : '' ?>"><?= $r['due_date'] ? date('j M Y', strtotime($r['due_date'])) : '—' ?><?= $late ? ' · overdue' : '' ?></span></td>
      <td data-label="Status"><?= invoiceStatusTag($r['status']) ?></td>
      <td class="num" data-label="Total"><strong><?= rand_($r['total'], true) ?></strong></td>
      <td><div class="row-actions"><a class="btn-xs" href="invoice.php?id=<?= (int) $r['id'] ?>">Open</a><a class="btn-xs" href="<?= e(invoiceLink((int) $r['id'], true)) ?>" target="_blank" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><div class="empty"><i class="fa-solid fa-file-invoice-dollar"></i>No invoices yet.</div><?php endif; ?>
</div>
<?php adminClose();

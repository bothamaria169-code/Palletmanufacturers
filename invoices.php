<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
requireLogin();
$user = getCurrentUser();

$s = getDB()->prepare("SELECT i.*, q.ref AS quote_ref FROM invoices i LEFT JOIN quotes q ON q.id = i.quote_id
                       WHERE (i.user_id = ? OR (i.user_id IS NULL AND i.email = ?)) AND i.status <> 'cancelled' ORDER BY i.id DESC");
$s->execute([(int) $user['id'], $user['email']]);
$rows = $s->fetchAll();

appOpen('invoices', 'My invoices', 'Every invoice for your orders. Use the invoice number as your payment reference.');
?>
<div class="card">
  <?php if ($rows): ?>
    <table class="tbl"><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Status</th><th class="num">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="cell-main"><strong><a href="<?= e(invoiceLink((int) $r['id'])) ?>" target="_blank"><?= e($r['invoice_no']) ?></a></strong><?= $r['quote_ref'] ? '<br><small class="muted">Quote ' . e($r['quote_ref']) . '</small>' : '' ?></td>
        <td data-label="Date"><?= date('j M Y', strtotime($r['issue_date'])) ?></td>
        <td data-label="Due"><?= $r['due_date'] ? date('j M Y', strtotime($r['due_date'])) : '—' ?></td>
        <td data-label="Status"><?= invoiceStatusTag($r['status']) ?></td>
        <td class="num" data-label="Total"><strong><?= rand_($r['total'], true) ?></strong></td>
        <td><div class="row-actions"><a class="btn-xs" href="<?= e(invoiceLink((int) $r['id'])) ?>" target="_blank">View</a><a class="btn-xs" href="<?= e(invoiceLink((int) $r['id'], true)) ?>&dl=1"><i class="fa-solid fa-file-pdf"></i>PDF</a></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php else: ?>
    <div class="empty"><i class="fa-solid fa-file-invoice-dollar"></i>No invoices yet. When you accept a quote, your invoice appears here.</div>
  <?php endif; ?>
</div>
<?php appClose();

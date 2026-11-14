<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$inv = invoiceById($id);
if (!$inv) { flash('error', 'Invoice not found.'); redirect('admin/invoices.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'send') {
        $err = sendInvoice($id);
        audit('invoice_send', $inv['invoice_no']);
        flash($err === '' ? 'success' : 'error', $err === '' ? 'Invoice emailed to ' . $inv['email'] . ' with the PDF attached.' : 'Email failed: ' . $err);
    } elseif ($action === 'paid') {
        $db->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$id]);
        if ($inv['quote_id']) $db->prepare("UPDATE quotes SET status = IF(status IN ('quoted','accepted'), 'accepted', status) WHERE id = ?")->execute([$inv['quote_id']]);
        audit('invoice_paid', $inv['invoice_no']);
        flash('success', $inv['invoice_no'] . ' marked as paid.');
    } elseif ($action === 'unpaid') {
        $db->prepare("UPDATE invoices SET status = 'unpaid', paid_at = NULL WHERE id = ?")->execute([$id]);
        flash('success', 'Marked as unpaid.');
    } elseif ($action === 'cancel') {
        $db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        audit('invoice_cancel', $inv['invoice_no']);
        flash('success', $inv['invoice_no'] . ' cancelled. Make a new one from the quote if the amounts changed.');
    } else {
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['due_date'] ?? '')) ? $_POST['due_date'] : null;
        $db->prepare('UPDATE invoices SET due_date = ?, notes = ?, vat_number = ?, billing_address = ? WHERE id = ?')
           ->execute([$due, postText('notes', 3000) ?: null, postText('vat_number', 40) ?: null, postText('billing_address', 400) ?: null, $id]);
        flash('success', 'Invoice saved.');
    }
    redirect('admin/invoice.php?id=' . $id);
}

adminOpen('invoices', 'Invoice ' . $inv['invoice_no'], invoiceStatusTag($inv['status']) . ' &nbsp;·&nbsp; ' . e($inv['full_name']) . ' · ' . rand_($inv['total'], true)
    . ($inv['sent_at'] ? ' · emailed ' . e(timeAgo($inv['sent_at'])) : ''),
    '<a class="btn btn-ghost" href="' . e(invoiceLink($id)) . '" target="_blank"><i class="fa-solid fa-print"></i>View / print</a>'
    . '<a class="btn btn-ghost" href="' . e(invoiceLink($id, true)) . '&dl=1"><i class="fa-solid fa-file-pdf"></i>PDF</a>');
?>
<div class="grid-2">
  <div>
    <div class="card">
      <div class="card__head"><h2>Lines</h2><?php if ($inv['quote_id']): ?><a href="quote.php?id=<?= (int) $inv['quote_id'] ?>">Quote <?= e($inv['quote_ref']) ?></a><?php endif; ?></div>
      <?= quoteTable(['items' => array_map(fn($i) => $i + ['image' => null, 'product_slug' => null], $inv['items']), 'subtotal' => $inv['subtotal'], 'delivery_fee' => $inv['delivery_fee'], 'discount' => $inv['discount'], 'total' => $inv['total']]) ?>
    </div>
    <div class="card">
      <div class="card__head"><h2>Details</h2></div>
      <form class="card__body" method="post"><?= csrfField() ?><input type="hidden" name="action" value="save">
        <div class="form-grid">
          <div class="field"><label for="due_date">Due date</label><input id="due_date" type="date" name="due_date" value="<?= e($inv['due_date']) ?>"></div>
          <div class="field"><label for="vat_number">Customer VAT no.</label><input id="vat_number" name="vat_number" value="<?= e($inv['vat_number']) ?>"></div>
          <div class="field full"><label for="billing_address">Billing address</label><input id="billing_address" name="billing_address" value="<?= e($inv['billing_address']) ?>"></div>
          <div class="field full"><label for="notes">Notes on the invoice</label><textarea id="notes" name="notes"><?= e($inv['notes']) ?></textarea></div>
        </div>
        <div class="form-foot"><button class="btn btn-ghost">Save</button></div>
      </form>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card__head"><h2>Send &amp; payment</h2></div>
      <div class="card__body" style="display:grid;gap:.6rem">
        <?php if ($inv['status'] !== 'cancelled'): ?>
          <form method="post" data-busy><?= csrfField() ?><input type="hidden" name="action" value="send"><button class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i><?= $inv['sent_at'] ? 'Email invoice again' : 'Email invoice (PDF)' ?></button></form>
          <?php if ($inv['status'] === 'unpaid'): ?>
            <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="paid"><button class="btn btn-ghost btn-block"><i class="fa-solid fa-circle-check" style="color:var(--mint)"></i>Mark as paid</button></form>
          <?php else: ?>
            <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="unpaid"><button class="btn btn-ghost btn-block">Mark as unpaid</button></form>
          <?php endif; ?>
          <form method="post" data-confirm="Cancel <?= e($inv['invoice_no']) ?>?"><?= csrfField() ?><input type="hidden" name="action" value="cancel"><button class="btn-xs danger">Cancel invoice</button></form>
        <?php else: ?>
          <p class="muted">This invoice is cancelled.</p>
        <?php endif; ?>
        <p class="hint">Sent from <?= e(SALES_EMAIL) ?> using the <a href="templates.php?t=invoice_sent">Invoice email template</a>. Bank details come from <a href="settings.php?tab=invoices">Settings → Invoices</a>.</p>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Customer</h2></div>
      <div class="card__body" style="display:grid;gap:.4rem;font-size:.9rem">
        <strong><?= e($inv['full_name']) ?><?= $inv['company_name'] ? ' · ' . e($inv['company_name']) : '' ?></strong>
        <a href="inbox.php?compose=1&to=<?= rawurlencode($inv['email']) ?>&subject=<?= rawurlencode('Invoice ' . $inv['invoice_no']) ?>"><i class="fa-solid fa-envelope"></i> <?= e($inv['email']) ?></a>
        <?php if ($inv['phone']): ?><span><i class="fa-solid fa-phone muted"></i> <?= e($inv['phone']) ?></span><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php adminClose();

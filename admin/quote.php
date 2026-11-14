<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

// ── New quote typed in by staff (phone / walk-in / WhatsApp) ─────────
if (isset($_GET['new'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ['full_name' => postText('full_name', 120), 'email' => strtolower(postText('email', 190)), 'phone' => postText('phone', 40), 'company_name' => postText('company_name', 160),
              'delivery_address' => postText('delivery_address'), 'city' => postText('city', 80), 'province' => in_array($_POST['province'] ?? '', PROVINCES, true) ? $_POST['province'] : '',
              'needed_by' => '', 'message' => postText('message', 3000)];
        if ($d['full_name'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'A name and a valid email are needed.');
            redirect('admin/quote.php?new=1');
        }
        $u = findUserByEmail($d['email']);
        $id = createQuote($d, [], $u ? (int) $u['id'] : null, postText('source', 30) ?: 'phone', false);
        audit('quote_create', quoteRef($id));
        flash('success', 'Quote created. Add the pallets below.');
        redirect('admin/quote.php?id=' . $id);
    }
    adminOpen('quotes', 'New quote', 'For a customer who phoned, emailed, WhatsApped or walked in.');
    ?>
    <div class="card" style="max-width:760px"><form class="card__body" method="post"><?= csrfField() ?>
      <div class="form-grid">
        <div class="field"><label for="full_name">Customer name</label><input id="full_name" name="full_name" required></div>
        <div class="field"><label for="company_name">Company</label><input id="company_name" name="company_name"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required></div>
        <div class="field"><label for="phone">Phone / WhatsApp</label><input id="phone" name="phone"></div>
        <div class="field full"><label for="delivery_address">Delivery address</label><input id="delivery_address" name="delivery_address"></div>
        <div class="field"><label for="city">Town / city</label><input id="city" name="city"></div>
        <div class="field"><label for="province">Province</label><select id="province" name="province"><?php foreach (PROVINCES as $p): ?><option <?= $p === 'KwaZulu-Natal' ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="source">Came in by</label><select id="source" name="source"><option value="phone">Phone</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="walk-in">Walk-in</option></select></div>
        <div class="field full"><label for="message">What they asked for</label><textarea id="message" name="message"></textarea></div>
      </div>
      <div class="form-foot"><button class="btn btn-primary">Create quote</button><a class="btn btn-ghost" href="quotes.php">Cancel</a></div>
    </form></div>
    <?php
    adminClose();
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$q  = quoteById($id);
if (!$q) { flash('error', 'Quote not found.'); redirect('admin/quotes.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $db->prepare('DELETE FROM quotes WHERE id = ?')->execute([$id]);
        audit('quote_delete', $q['ref']);
        flash('success', 'Quote ' . $q['ref'] . ' deleted.');
        redirect('admin/quotes.php');
    }
    if ($action === 'invoice') {
        try {
            $iid = createInvoiceFromQuote($id);
            audit('invoice_create', invoiceNo($iid), $q['ref']);
            flash('success', 'Invoice ' . invoiceNo($iid) . ' created. Check it, then press "Email invoice".');
            redirect('admin/invoice.php?id=' . $iid);
        } catch (Throwable $e) {
            flash('error', 'Could not create the invoice: ' . $e->getMessage());
            redirect('admin/quote.php?id=' . $id);
        }
    }
    if ($action === 'message') {
        $body = postText('body', 3000);
        if ($body !== '') {
            addQuoteMessage($id, (int) $ADMIN['id'], true, $body);
            flash('success', 'Message sent to ' . $q['email'] . '.');
        }
        redirect('admin/quote.php?id=' . $id . '#messages');
    }

    // Save lines + totals + status
    $up = $db->prepare('UPDATE quote_items SET name = ?, qty = ?, unit_price = ?, notes = ? WHERE id = ? AND quote_id = ?');
    $del = $db->prepare('DELETE FROM quote_items WHERE id = ? AND quote_id = ?');
    foreach ((array) ($_POST['items'] ?? []) as $itemId => $it) {
        if (!empty($it['remove']) || (int) ($it['qty'] ?? 0) <= 0) { $del->execute([(int) $itemId, $id]); continue; }
        $price = trim((string) ($it['unit_price'] ?? ''));
        $up->execute([mb_substr(trim((string) $it['name']), 0, 200) ?: 'Item', (int) $it['qty'], $price === '' ? null : round((float) str_replace([' ', ','], ['', '.'], $price), 2),
                      mb_substr(trim((string) ($it['notes'] ?? '')), 0, 500) ?: null, (int) $itemId, $id]);
    }
    $addId = (int) ($_POST['add_product'] ?? 0);
    $addName = postText('add_name', 200);
    if ($addId && ($p = productById($addId))) {
        $db->prepare('INSERT INTO quote_items (quote_id, product_id, name, qty, list_price, unit_price, sort_order) VALUES (?,?,?,?,?,?,99)')
           ->execute([$id, $addId, $p['name'], max(1, (int) ($_POST['add_qty'] ?? 1)), $p['price'], $p['price']]);
    } elseif ($addName !== '') {
        $pr = trim((string) ($_POST['add_price'] ?? ''));
        $db->prepare('INSERT INTO quote_items (quote_id, product_id, name, qty, unit_price, sort_order) VALUES (?,NULL,?,?,?,99)')
           ->execute([$id, $addName, max(1, (int) ($_POST['add_qty'] ?? 1)), $pr === '' ? null : (float) $pr]);
    }
    $status = isset(QUOTE_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : $q['status'];
    if ($status === 'new' && $action === 'save') $status = 'reviewing';
    $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['valid_until'] ?? '')) ? $_POST['valid_until'] : null;
    $db->prepare('UPDATE quotes SET delivery_fee = ?, discount = ?, valid_until = ?, customer_note = ?, admin_note = ?, status = ? WHERE id = ?')
       ->execute([max(0, (float) ($_POST['delivery_fee'] ?? 0)), max(0, (float) ($_POST['discount'] ?? 0)), $valid,
                  postText('customer_note', 3000) ?: null, postText('admin_note', 3000) ?: null, $status, $id]);
    recalcQuote($id);

    if ($action === 'send') {
        $missing = (int) $db->query('SELECT COUNT(*) FROM quote_items WHERE quote_id = ' . $id . ' AND unit_price IS NULL')->fetchColumn();
        if ($missing) {
            flash('error', "{$missing} line(s) have no price yet. Fill in every unit price before sending.");
        } elseif (($err = sendQuoteToCustomer($id)) !== '') {
            flash('error', 'Saved, but the email failed: ' . $err . ' — check Settings → Email.');
        } else {
            audit('quote_send', $q['ref'], 'Total ' . rand_(quoteById($id)['total'], true));
            flash('success', 'Quote emailed to ' . $q['email'] . '. It\'s marked "Quote sent".');
        }
    } else {
        audit('quote_save', $q['ref'], 'status ' . $status);
        flash('success', 'Quote saved.');
    }
    redirect('admin/quote.php?id=' . $id);
}

$invoices = invoicesForQuote($id);
$products = $db->query('SELECT id, name, price FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$customer = $q['user_id'] ? $db->query('SELECT * FROM users WHERE id = ' . (int) $q['user_id'])->fetch() : null;
$history = $db->prepare('SELECT id, ref, status, total, created_at FROM quotes WHERE email = ? AND id <> ? ORDER BY created_at DESC LIMIT 5');
$history->execute([$q['email'], $id]);
$history = $history->fetchAll();
$tel = preg_replace('/\D/', '', (string) $q['phone']);
if (str_starts_with($tel, '0')) $tel = '27' . substr($tel, 1);

adminOpen('quotes', 'Quote ' . $q['ref'], quoteStatusTag($q['status']) . ' &nbsp;·&nbsp; ' . e($q['full_name']) . ' · requested ' . e(timeAgo($q['created_at'])) . ' via ' . e($q['source']),
    '<a class="btn btn-ghost" href="' . e(quoteLink($id)) . '" target="_blank"><i class="fa-solid fa-eye"></i>Customer view</a>'
    . '<a class="btn btn-ghost" href="' . e(quoteDocLink($id, true)) . '&dl=1"><i class="fa-solid fa-file-pdf"></i>PDF</a>'
    . ($tel ? '<a class="btn btn-wa" href="https://wa.me/' . $tel . '?text=' . rawurlencode('Hi ' . explode(' ', $q['full_name'])[0] . ', your pallet quote ' . $q['ref'] . ' is ready: ' . quoteLink($id)) . '" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>' : ''));
?>
<form method="post" id="quoteForm">
<button type="submit" name="action" value="save" style="position:absolute;left:-9999px;width:1px;height:1px" aria-hidden="true" tabindex="-1"></button><!-- Enter key saves, never emails -->
<?= csrfField() ?>
<div class="grid-2">
  <div>
    <div class="card">
      <div class="card__head"><h2>Lines &amp; prices</h2><p>List price is today's website price. Change the unit price to quote.</p></div>
      <div style="overflow-x:auto"><table class="tbl line-edit"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">List</th><th class="num">Unit price (R)</th><th class="num">Line</th><th></th></tr></thead><tbody>
        <?php foreach ($q['items'] as $it): $iid = (int) $it['id']; ?>
          <tr>
            <td class="cell-main"><input name="items[<?= $iid ?>][name]" value="<?= e($it['name']) ?>" aria-label="Item name"><input name="items[<?= $iid ?>][notes]" value="<?= e($it['notes']) ?>" placeholder="Notes for the customer" style="margin-top:.3rem;font-size:.8rem" aria-label="Notes"></td>
            <td class="num" data-label="Qty"><input type="number" min="0" name="items[<?= $iid ?>][qty]" value="<?= (int) $it['qty'] ?>" style="max-width:80px" aria-label="Quantity"></td>
            <td class="num muted" data-label="List"><?= $it['list_price'] !== null ? rand_($it['list_price']) : '—' ?></td>
            <td class="num" data-label="Unit price"><input inputmode="decimal" name="items[<?= $iid ?>][unit_price]" value="<?= $it['unit_price'] !== null ? e(number_format((float) $it['unit_price'], 2, '.', '')) : '' ?>" placeholder="Price" aria-label="Unit price"></td>
            <td class="num" data-label="Line"><strong><?= $it['unit_price'] !== null ? rand_((float) $it['unit_price'] * (int) $it['qty'], true) : '—' ?></strong></td>
            <td><label class="btn-xs danger" title="Remove line"><input type="checkbox" name="items[<?= $iid ?>][remove]" value="1" style="accent-color:var(--coral)"> <i class="fa-solid fa-trash-can"></i></label></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$q['items']): ?><tr><td colspan="6" class="empty">No lines yet — add pallets below.</td></tr><?php endif; ?>
      </tbody></table></div>
      <div class="card__body" style="border-top:1px solid var(--line)">
        <strong style="font-size:.86rem">Add a line</strong>
        <div class="form-grid" style="margin-top:.6rem;grid-template-columns:2fr .6fr">
          <div class="field"><select name="add_product" aria-label="Add a product"><option value="">— Choose a product —</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= rand_($p['price']) ?>)</option><?php endforeach; ?></select></div>
          <div class="field"><input type="number" name="add_qty" min="1" value="1" aria-label="Quantity"></div>
          <div class="field"><input name="add_name" placeholder="…or a custom item, e.g. Custom pallet 1100 × 1300 heat-treated" aria-label="Custom item"></div>
          <div class="field"><input name="add_price" inputmode="decimal" placeholder="Unit R" aria-label="Custom item price"></div>
        </div>
      </div>
      <div class="card__body" style="border-top:1px solid var(--line)">
        <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
          <div class="field"><label for="delivery_fee">Delivery (R)</label><input id="delivery_fee" name="delivery_fee" inputmode="decimal" value="<?= e(number_format((float) $q['delivery_fee'], 2, '.', '')) ?>"></div>
          <div class="field"><label for="discount">Discount (R)</label><input id="discount" name="discount" inputmode="decimal" value="<?= e(number_format((float) $q['discount'], 2, '.', '')) ?>"></div>
          <div class="field"><label for="valid_until">Valid until</label><input id="valid_until" type="date" name="valid_until" value="<?= e($q['valid_until'] ?: date('Y-m-d', strtotime('+14 days'))) ?>"></div>
        </div>
        <div class="totals" style="border:0;padding:0">
          <div><span>Subtotal</span><b><?= rand_($q['subtotal'], true) ?></b></div>
          <div class="totals__grand"><span>Total</span><b><?= rand_($q['total'], true) ?></b></div>
          <small><?= e(vatNote()) ?> Totals update when you save.</small>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Notes</h2></div>
      <div class="card__body">
        <div class="field"><label for="customer_note">Note on the quote <span class="hint">the customer sees this</span></label><textarea id="customer_note" name="customer_note" placeholder="E.g. Prices for collection from our Glen Anil yard. Heat-treated adds R15 per pallet."><?= e($q['customer_note']) ?></textarea></div>
        <div class="field"><label for="admin_note">Internal note <span class="hint">staff only</span></label><textarea id="admin_note" name="admin_note"><?= e($q['admin_note']) ?></textarea></div>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card__head"><h2>Status &amp; send</h2></div>
      <div class="card__body">
        <div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach (QUOTE_STATUSES as $k => $label): ?><option value="<?= $k ?>" <?= $q['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="form-foot">
          <button class="btn btn-primary" name="action" value="send" data-busy><i class="fa-solid fa-paper-plane"></i>Save &amp; email quote</button>
          <button class="btn btn-ghost" name="action" value="save"><i class="fa-solid fa-floppy-disk"></i>Save</button>
        </div>
        <p class="hint" style="margin-top:.8rem">"Save &amp; email" sends the priced quote from <?= e(SALES_EMAIL) ?> to <strong><?= e($q['email']) ?></strong> with the PDF attached and a link to accept it online, and sets the status to "Quote sent".<?= $q['quoted_at'] ? ' Last sent ' . e(timeAgo($q['quoted_at'])) . '.' : '' ?></p>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Invoice</h2></div>
      <div class="card__body">
        <?php foreach ($invoices as $iv): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.5rem"><a href="invoice.php?id=<?= (int) $iv['id'] ?>"><strong><?= e($iv['invoice_no']) ?></strong></a> <?= invoiceStatusTag($iv['status']) ?> <span><?= rand_($iv['total'], true) ?></span></div>
        <?php endforeach; ?>
        <?php if (!array_filter($invoices, fn($iv) => $iv['status'] !== 'cancelled')): ?>
          <button class="btn btn-dark btn-block" form="invForm"<?= $q['items'] ? '' : ' disabled' ?>><i class="fa-solid fa-file-invoice-dollar"></i>Create invoice from this quote</button>
          <p class="hint" style="margin-top:.5rem">Uses the saved prices, delivery and discount. Save first if you changed anything.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Customer</h2><?= $customer ? '<a href="users.php?q=' . rawurlencode($customer['email']) . '">Account</a>' : '<span class="tag">Guest</span>' ?></div>
      <div class="card__body" style="display:grid;gap:.5rem;font-size:.9rem">
        <div><strong><?= e($q['full_name']) ?></strong><?= $q['company_name'] ? ' · ' . e($q['company_name']) : '' ?></div>
        <div><i class="fa-solid fa-envelope muted"></i> <a href="mailto:<?= e($q['email']) ?>?subject=<?= rawurlencode('Your quote ' . $q['ref']) ?>"><?= e($q['email']) ?></a></div>
        <?php if ($q['phone']): ?><div><i class="fa-solid fa-phone muted"></i> <a href="tel:<?= e($q['phone']) ?>"><?= e($q['phone']) ?></a></div><?php endif; ?>
        <div><i class="fa-solid fa-location-dot muted"></i> <?= e(implode(', ', array_filter([$q['delivery_address'], $q['city'], $q['province']])) ?: '—') ?></div>
        <?php if ($q['needed_by']): ?><div><i class="fa-solid fa-calendar muted"></i> Needed by <strong><?= date('j M Y', strtotime($q['needed_by'])) ?></strong></div><?php endif; ?>
        <?php if ($customer && $customer['vat_number']): ?><div><i class="fa-solid fa-receipt muted"></i> VAT <?= e($customer['vat_number']) ?></div><?php endif; ?>
        <?php if ($q['message']): ?><div style="background:var(--surface-2);border-radius:10px;padding:.7rem;white-space:pre-line"><?= e($q['message']) ?></div><?php endif; ?>
        <?php if ($history): ?><div style="margin-top:.4rem"><span class="muted" style="font-size:.8rem">Other quotes from this email</span>
          <?php foreach ($history as $h): ?><div><a href="quote.php?id=<?= (int) $h['id'] ?>"><?= e($h['ref']) ?></a> · <?= quoteStatusTag($h['status']) ?> · <?= rand_($h['total']) ?></div><?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card" id="messages">
      <div class="card__head"><h2>Messages</h2></div>
      <div class="msgs">
        <?php if (!$q['messages']): ?><p class="muted" style="font-size:.88rem">No messages yet. What you write here is emailed to the customer and shown on their quote.</p><?php endif; ?>
        <?php foreach ($q['messages'] as $m): ?>
          <div class="msg <?= $m['from_admin'] ? 'me' : '' ?>"><?= e($m['body']) ?><small><?= $m['from_admin'] ? 'You (' . e($m['full_name'] ?: 'admin') . ')' : e($q['full_name']) ?> · <?= e(timeAgo($m['created_at'])) ?></small></div>
        <?php endforeach; ?>
      </div>
      <div class="msg-form">
        <textarea name="body" form="msgForm" maxlength="3000" placeholder="Message the customer…" aria-label="Message"></textarea><button class="btn btn-primary" form="msgForm" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button></div>
    </div>

    <div style="text-align:right"><button class="btn-xs danger" form="delForm"><i class="fa-solid fa-trash-can"></i>Delete quote</button></div>
  </div>
</div>
</form>
<form method="post" id="msgForm"><?= csrfField() ?><input type="hidden" name="action" value="message"></form>
<form method="post" id="invForm"><?= csrfField() ?><input type="hidden" name="action" value="invoice"></form>
<form method="post" id="delForm" data-confirm="Delete quote <?= e($q['ref']) ?> permanently?"><?= csrfField() ?><input type="hidden" name="action" value="delete"></form>
<?php adminClose();

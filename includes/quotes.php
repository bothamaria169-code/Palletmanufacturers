<?php
declare(strict_types=1);

/** Quote requests: create, load, price, totals and the emails around them. */

function quoteRef(int $id): string
{
    return 'PM-' . date('y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/**
 * Saves a quote request from the basket (or a custom request with no lines).
 *
 * @param array $d full_name, email, phone, company_name, delivery_address, city, province, needed_by, message
 * @param array $lines from basketLines()
 */
function createQuote(array $d, array $lines, ?int $userId, string $source = 'website', bool $notify = true): int
{
    $db = getDB();
    $db->beginTransaction();
    $db->prepare('INSERT INTO quotes (user_id, full_name, email, phone, company_name, delivery_address, city, province, needed_by, message, source)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$userId, $d['full_name'], strtolower($d['email']), $d['phone'] ?: null, $d['company_name'] ?: null,
                  $d['delivery_address'] ?: null, $d['city'] ?: null, $d['province'] ?: null, $d['needed_by'] ?: null, $d['message'] ?: null, $source]);
    $id = (int) $db->lastInsertId();
    $db->prepare('UPDATE quotes SET ref = ? WHERE id = ?')->execute([quoteRef($id), $id]);
    $ins = $db->prepare('INSERT INTO quote_items (quote_id, product_id, name, qty, list_price, unit_price, notes, sort_order) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($lines as $i => $l) {
        $ins->execute([$id, $l['product']['id'], $l['product']['name'], $l['qty'], $l['product']['price'], $l['product']['price'], $l['notes'] ?: null, $i]);
    }
    recalcQuote($id);
    $db->commit();

    $q = quoteById($id);
    if ($notify) notifyNewQuote($q);
    return $id;
}

function quoteById(int $id): ?array
{
    $s = getDB()->prepare('SELECT * FROM quotes WHERE id = ?');
    $s->execute([$id]);
    $q = $s->fetch();
    if (!$q) return null;
    $i = getDB()->prepare('SELECT qi.*, (SELECT path FROM product_images pi WHERE pi.product_id = qi.product_id ORDER BY sort_order, id LIMIT 1) AS image,
                                  p.slug AS product_slug
                           FROM quote_items qi LEFT JOIN products p ON p.id = qi.product_id WHERE qi.quote_id = ? ORDER BY qi.sort_order, qi.id');
    $i->execute([$id]);
    $q['items'] = $i->fetchAll();
    $m = getDB()->prepare('SELECT m.*, u.full_name FROM quote_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.quote_id = ? ORDER BY m.created_at, m.id');
    $m->execute([$id]);
    $q['messages'] = $m->fetchAll();
    return $q;
}

/** The quote if this user may see it (owner, or admin). */
function quoteForUser(int $id, array $user): ?array
{
    $q = quoteById($id);
    if (!$q) return null;
    if ($user['role'] === 'admin' || (int) $q['user_id'] === (int) $user['id']) return $q;
    return null;
}

function recalcQuote(int $id): void
{
    $db = getDB();
    $s = $db->prepare('SELECT COALESCE(SUM(qty * COALESCE(unit_price, 0)), 0) FROM quote_items WHERE quote_id = ?');
    $s->execute([$id]);
    $sub = (float) $s->fetchColumn();
    $db->prepare('UPDATE quotes SET subtotal = ?, total = GREATEST(0, ? + delivery_fee - discount) WHERE id = ?')->execute([$sub, $sub, $id]);
}

function quoteStatusTag(string $status): string
{
    return '<span class="tag qs-' . e($status) . '">' . e(QUOTE_STATUSES[$status] ?? ucfirst($status)) . '</span>';
}

function vatNote(): string
{
    return get_setting('prices_include_vat', '1') === '1' ? 'Prices include 15% VAT.' : 'Prices exclude VAT (15% added on invoice).';
}

function addQuoteMessage(int $quoteId, ?int $userId, bool $fromAdmin, string $body): void
{
    getDB()->prepare('INSERT INTO quote_messages (quote_id, user_id, from_admin, body) VALUES (?, ?, ?, ?)')
        ->execute([$quoteId, $userId, $fromAdmin ? 1 : 0, $body]);
    $q = quoteById($quoteId);
    if ($fromAdmin) {
        sendMail($q['email'], 'Message about your quote ' . $q['ref'], emailLayout('New message about quote ' . e($q['ref']),
            '<p style="white-space:pre-line">' . e($body) . '</p>', 'View & reply', quoteLink($quoteId)), [], biz('biz_email'), 'quote_message');
    } else {
        sendMail(get_setting('notify_email', ADMIN_EMAIL), 'Customer message on ' . $q['ref'], emailLayout('Message from ' . e($q['full_name']),
            '<p style="white-space:pre-line">' . e($body) . '</p>', 'Open quote', APP_URL . '/admin/quote.php?id=' . $quoteId), [], $q['email'], 'quote_message_admin');
    }
}

function quoteItemsTableHtml(array $q, bool $forEmail = true): string
{
    $rows = '';
    foreach ($q['items'] as $it) {
        $line = $it['unit_price'] !== null ? (float) $it['unit_price'] * (int) $it['qty'] : null;
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eef1f6">' . e($it['name']) . ($it['notes'] ? '<br><small style="color:#7c8899">' . e($it['notes']) . '</small>' : '') . '</td>'
               . '<td style="padding:8px;border-bottom:1px solid #eef1f6;text-align:center">' . (int) $it['qty'] . '</td>'
               . '<td style="padding:8px 0;border-bottom:1px solid #eef1f6;text-align:right">' . ($it['unit_price'] !== null ? rand_($it['unit_price'], true) : 'TBC') . '</td>'
               . '<td style="padding:8px 0;border-bottom:1px solid #eef1f6;text-align:right">' . ($line !== null ? rand_($line, true) : 'TBC') . '</td></tr>';
    }
    $foot = '<tr><td colspan="3" style="padding:8px 0;text-align:right;color:#7c8899">Subtotal</td><td style="padding:8px 0;text-align:right">' . rand_($q['subtotal'], true) . '</td></tr>';
    if ((float) $q['delivery_fee'] > 0) $foot .= '<tr><td colspan="3" style="padding:4px 0;text-align:right;color:#7c8899">Delivery</td><td style="text-align:right">' . rand_($q['delivery_fee'], true) . '</td></tr>';
    if ((float) $q['discount'] > 0) $foot .= '<tr><td colspan="3" style="padding:4px 0;text-align:right;color:#7c8899">Discount</td><td style="text-align:right">-' . rand_($q['discount'], true) . '</td></tr>';
    $foot .= '<tr><td colspan="3" style="padding:10px 0;text-align:right;font-weight:800">Total</td><td style="padding:10px 0;text-align:right;font-weight:800">' . rand_($q['total'], true) . '</td></tr>';
    return '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr>'
        . '<th style="text-align:left;padding:6px 0;color:#7c8899;font-size:12px">Item</th><th style="color:#7c8899;font-size:12px">Qty</th>'
        . '<th style="text-align:right;color:#7c8899;font-size:12px">Unit</th><th style="text-align:right;color:#7c8899;font-size:12px">Total</th></tr>'
        . $rows . $foot . '</table>';
}

function notifyNewQuote(array $q): void
{
    $items = quoteItemsTableHtml($q);
    sendMail(get_setting('notify_email', ADMIN_EMAIL), 'New quote request ' . $q['ref'] . ' – ' . $q['full_name'], emailLayout('New quote request ' . e($q['ref']),
        emailRows(['Name' => $q['full_name'], 'Company' => $q['company_name'] ?: '—', 'Email' => $q['email'], 'Phone' => $q['phone'] ?: '—',
                   'Deliver to' => trim(($q['delivery_address'] ?? '') . ', ' . ($q['city'] ?? '') . ', ' . ($q['province'] ?? ''), ', ') ?: '—',
                   'Needed by' => $q['needed_by'] ?: '—'])
        . ($q['message'] ? '<p style="margin-top:16px;white-space:pre-line"><strong>Message:</strong><br>' . e($q['message']) . '</p>' : '')
        . '<div style="margin-top:18px">' . $items . '</div>',
        'Price this quote', APP_URL . '/admin/quote.php?id=' . $q['id']), [], $q['email'], 'quote_admin');

    sendMail($q['email'], 'We received your quote request ' . $q['ref'], emailLayout('Thanks, ' . e(explode(' ', $q['full_name'])[0]) . ' — we\'re on it',
        '<p>Your quote request <strong>' . e($q['ref']) . '</strong> is with our sales team. We\'ll confirm prices, stock and delivery to you, usually within one working day.</p>'
        . '<div style="margin:18px 0">' . $items . '</div><p style="color:#7c8899;font-size:13px">Prices shown are our current list prices. ' . e(vatNote()) . ' Delivery is confirmed on the final quote.</p>',
        'Track my quote', quoteLink((int) $q['id'])), [], biz('biz_email'), 'quote_customer');
}

/** Emails the priced quote to the customer and marks it sent. */
function sendQuoteToCustomer(int $id): string
{
    $q = quoteById($id);
    if (!$q) return 'Quote not found.';
    getDB()->prepare("UPDATE quotes SET status = 'quoted', quoted_at = NOW(), valid_until = COALESCE(valid_until, CURDATE() + INTERVAL 14 DAY) WHERE id = ?")->execute([$id]);
    $q = quoteById($id);
    $hasAccount = (bool) findUserByEmail($q['email']);
    $body = '<p>Here is your quote <strong>' . e($q['ref']) . '</strong>' . ($q['valid_until'] ? ', valid until <strong>' . date('j F Y', strtotime($q['valid_until'])) . '</strong>' : '') . '.</p>'
          . '<div style="margin:18px 0">' . quoteItemsTableHtml($q) . '</div>'
          . ($q['customer_note'] ? '<p style="white-space:pre-line;background:#fdf4e7;border-radius:10px;padding:12px">' . e($q['customer_note']) . '</p>' : '')
          . '<p style="color:#7c8899;font-size:13px">' . e(vatNote()) . '</p>'
          . ($hasAccount ? '<p>Accept it in your dashboard and we\'ll send the invoice and arrange delivery.</p>'
                         : '<p>Accept it online with the button below, or reply to this email. Create a free account with this email address to keep all your quotes in one place.</p>');
    return sendMail($q['email'], 'Your quote ' . $q['ref'] . ' from ' . APP_NAME, emailLayout('Your pallet quote', $body,
        'View & accept quote', quoteLink($id)),
        [], biz('biz_email'), 'quote_sent');
}

function quoteCounts(?int $userId = null): array
{
    $sql = 'SELECT status, COUNT(*) n FROM quotes' . ($userId ? ' WHERE user_id = ' . (int) $userId : '') . ' GROUP BY status';
    $out = array_fill_keys(array_keys(QUOTE_STATUSES), 0);
    foreach (getDB()->query($sql) as $r) $out[$r['status']] = (int) $r['n'];
    return $out;
}

/** A link to the quote that works without signing in (signed, view + accept only). */
function quoteLink(int $id): string
{
    return APP_URL . '/quote-view.php?id=' . $id . '&k=' . quoteKey($id);
}

function quoteKey(int $id): string
{
    return substr(hash_hmac('sha256', 'quote|' . $id, APP_SECRET), 0, 24);
}

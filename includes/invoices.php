<?php
declare(strict_types=1);

require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/templates.php';

const INVOICE_STATUSES = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

function invoiceNo(int $id): string
{
    return 'INV-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** Creates an invoice from a quote's lines and totals. Returns the invoice id. */
function createInvoiceFromQuote(int $quoteId): int
{
    $q = quoteById($quoteId);
    if (!$q) throw new RuntimeException('Quote not found.');
    $db = getDB();
    $vat = null;
    if ($q['user_id']) {
        $s = $db->prepare('SELECT vat_number FROM users WHERE id = ?');
        $s->execute([$q['user_id']]);
        $vat = $s->fetchColumn() ?: null;
    }
    $terms = max(0, (int) get_setting('invoice_terms_days', '7'));
    $db->beginTransaction();
    $db->prepare('INSERT INTO invoices (quote_id, user_id, full_name, email, phone, company_name, vat_number, billing_address, issue_date, due_date, subtotal, delivery_fee, discount, total, notes)
                  VALUES (?,?,?,?,?,?,?,?,CURDATE(), CURDATE() + INTERVAL ' . $terms . ' DAY,?,?,?,?,?)')
       ->execute([$quoteId, $q['user_id'], $q['full_name'], $q['email'], $q['phone'], $q['company_name'], $vat,
                  trim(implode(', ', array_filter([$q['delivery_address'], $q['city'], $q['province']]))) ?: null,
                  $q['subtotal'], $q['delivery_fee'], $q['discount'], $q['total'], $q['customer_note']]);
    $id = (int) $db->lastInsertId();
    $db->prepare('UPDATE invoices SET invoice_no = ? WHERE id = ?')->execute([invoiceNo($id), $id]);
    $ins = $db->prepare('INSERT INTO invoice_items (invoice_id, name, qty, unit_price, notes, sort_order) VALUES (?,?,?,?,?,?)');
    foreach ($q['items'] as $i => $it) $ins->execute([$id, $it['name'], $it['qty'], (float) ($it['unit_price'] ?? 0), $it['notes'], $i]);
    $db->commit();
    return $id;
}

function invoiceById(int $id): ?array
{
    $s = getDB()->prepare('SELECT i.*, q.ref AS quote_ref FROM invoices i LEFT JOIN quotes q ON q.id = i.quote_id WHERE i.id = ?');
    $s->execute([$id]);
    $inv = $s->fetch();
    if (!$inv) return null;
    $it = getDB()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id');
    $it->execute([$id]);
    $inv['items'] = $it->fetchAll();
    return $inv;
}

function invoicesForQuote(int $quoteId): array
{
    $s = getDB()->prepare('SELECT id, invoice_no, status, total FROM invoices WHERE quote_id = ? ORDER BY id DESC');
    $s->execute([$quoteId]);
    return $s->fetchAll();
}

function invoiceKey(int $id): string
{
    return substr(hash_hmac('sha256', 'invoice|' . $id, APP_SECRET), 0, 24);
}

/** Signed link to the invoice (view + PDF) that works without signing in. */
function invoiceLink(int $id, bool $pdf = false): string
{
    return APP_URL . '/document.php?type=invoice&id=' . $id . '&k=' . invoiceKey($id) . ($pdf ? '&pdf=1' : '');
}

function quoteDocLink(int $id, bool $pdf = false): string
{
    return APP_URL . '/document.php?type=quote&id=' . $id . '&k=' . quoteKey($id) . ($pdf ? '&pdf=1' : '');
}

function invoiceDocData(array $inv): array
{
    return [
        'number' => $inv['invoice_no'], 'status' => $inv['status'],
        'extra'  => ['Invoice no.' => $inv['invoice_no'], 'Date' => date('j M Y', strtotime($inv['issue_date'])),
                     'Due date' => $inv['due_date'] ? date('j M Y', strtotime($inv['due_date'])) : '', 'Quote' => $inv['quote_ref'] ?? '',
                     'Customer VAT no.' => $inv['vat_number'] ?? ''],
        'to'     => [$inv['company_name'], $inv['full_name'], $inv['email'], $inv['phone'], $inv['billing_address']],
        'items'  => $inv['items'], 'subtotal' => $inv['subtotal'], 'delivery_fee' => $inv['delivery_fee'], 'discount' => $inv['discount'], 'total' => $inv['total'],
        'notes'  => $inv['notes'],
    ];
}

function invoicePdf(array $inv): string
{
    return renderPdf(documentHtml('invoice', invoiceDocData($inv)));
}

/** Emails the invoice (PDF attached) from the sales mailbox. */
function sendInvoice(int $id): string
{
    $inv = invoiceById($id);
    if (!$inv) return 'Invoice not found.';
    $bank = bankLines();
    [$subject, $body] = renderTemplate('invoice_sent', [
        'name' => explode(' ', $inv['full_name'])[0], 'invoice_no' => $inv['invoice_no'], 'ref' => $inv['quote_ref'] ?? '',
        'total' => rand_($inv['total'], true), 'due_date' => $inv['due_date'] ? date('j F Y', strtotime($inv['due_date'])) : 'on receipt',
        'bank' => $bank ? "Banking details:\n" . implode("\n", $bank) : '',
    ]);
    $err = sendMail($inv['email'], $subject, emailLayout('', $body, templateButton('invoice_sent'), invoiceLink($id)), [], SALES_EMAIL, 'invoice',
        ['mailbox' => 'sales', 'files' => [[invoicePdf($inv), $inv['invoice_no'] . '.pdf']]]);
    if ($err === '') getDB()->prepare('UPDATE invoices SET sent_at = NOW() WHERE id = ?')->execute([$id]);
    return $err;
}

function invoiceStatusTag(string $s): string
{
    $cls = ['unpaid' => 'qs-quoted', 'paid' => 'qs-accepted', 'cancelled' => 'qs-cancelled'][$s] ?? '';
    return '<span class="tag ' . $cls . '">' . e(INVOICE_STATUSES[$s] ?? $s) . '</span>';
}

<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Quote and invoice documents: one HTML layout used for the printable page
 * and for the PDF (dompdf). Tables and inline CSS only — dompdf has no flexbox.
 */

/** Company details for the letterhead (Admin → Settings → Invoices). */
function companyLines(): array
{
    return array_values(array_filter([
        biz('biz_address'),
        biz('biz_phone') . ' · ' . SALES_EMAIL,
        preg_replace('#^https?://#', '', APP_URL),
        get_setting('company_reg') ? 'Reg. no. ' . get_setting('company_reg') : '',
        get_setting('company_vat') ? 'VAT no. ' . get_setting('company_vat') : '',
    ]));
}

function bankLines(): array
{
    return array_values(array_filter([
        get_setting('bank_name') ? 'Bank: ' . get_setting('bank_name') : '',
        get_setting('bank_account_name') ? 'Account name: ' . get_setting('bank_account_name') : '',
        get_setting('bank_account_no') ? 'Account no.: ' . get_setting('bank_account_no') : '',
        get_setting('bank_branch') ? 'Branch code: ' . get_setting('bank_branch') : '',
        get_setting('bank_account_type') ? 'Account type: ' . get_setting('bank_account_type') : '',
    ]));
}

/**
 * @param string $type 'quote' | 'invoice'
 * @param array  $d    number, date, extra (label => value), to (lines), items, subtotal, delivery_fee, discount, total, notes, status
 */
function documentHtml(string $type, array $d): string
{
    $isInv = $type === 'invoice';
    $brand = '#b45309';
    $rows = '';
    foreach ($d['items'] as $i => $it) {
        $price = $it['unit_price'] !== null ? (float) $it['unit_price'] : null;
        $rows .= '<tr style="background:' . ($i % 2 ? '#faf8f6' : '#fff') . '">'
            . '<td style="padding:8px 10px">' . e($it['name']) . ($it['notes'] ? '<div style="color:#78716c;font-size:10px;margin-top:2px">' . e($it['notes']) . '</div>' : '') . '</td>'
            . '<td style="padding:8px 10px;text-align:center">' . (int) $it['qty'] . '</td>'
            . '<td style="padding:8px 10px;text-align:right">' . ($price !== null ? rand_($price, true) : 'TBC') . '</td>'
            . '<td style="padding:8px 10px;text-align:right">' . ($price !== null ? rand_($price * (int) $it['qty'], true) : '—') . '</td></tr>';
    }
    if (!$d['items']) $rows = '<tr><td colspan="4" style="padding:12px 10px;color:#78716c">Custom request — see notes.</td></tr>';
    $tot = '<tr><td style="padding:4px 10px;color:#78716c">Subtotal</td><td style="padding:4px 10px;text-align:right">' . rand_($d['subtotal'], true) . '</td></tr>';
    if ((float) $d['delivery_fee'] > 0) $tot .= '<tr><td style="padding:4px 10px;color:#78716c">Delivery</td><td style="padding:4px 10px;text-align:right">' . rand_($d['delivery_fee'], true) . '</td></tr>';
    if ((float) $d['discount'] > 0) $tot .= '<tr><td style="padding:4px 10px;color:#78716c">Discount</td><td style="padding:4px 10px;text-align:right">-' . rand_($d['discount'], true) . '</td></tr>';
    $vatIncl = get_setting('prices_include_vat', '1') === '1';
    if ($vatIncl) $tot .= '<tr><td style="padding:4px 10px;color:#78716c">VAT (15%) included</td><td style="padding:4px 10px;text-align:right;color:#78716c">' . rand_((float) $d['total'] * 15 / 115, true) . '</td></tr>';
    $tot .= '<tr><td style="padding:10px;border-top:2px solid #10233F;font-weight:bold;font-size:14px">' . ($isInv ? 'Amount due' : 'Total') . '</td><td style="padding:10px;border-top:2px solid #10233F;text-align:right;font-weight:bold;font-size:14px">' . rand_($d['total'], true) . '</td></tr>';

    $meta = '';
    foreach ($d['extra'] as $k => $v) if ($v) $meta .= '<tr><td style="color:#78716c;padding:2px 12px 2px 0">' . e($k) . '</td><td style="text-align:right;font-weight:bold">' . e($v) . '</td></tr>';
    $stamp = '';
    if (!empty($d['status']) && in_array($d['status'], ['paid', 'cancelled'], true)) {
        $col = $d['status'] === 'paid' ? '#16a34a' : '#e5484d';
        $stamp = '<div style="border:3px solid ' . $col . ';color:' . $col . ';display:inline-block;padding:4px 14px;font-weight:bold;font-size:16px;letter-spacing:2px;margin-top:8px">' . strtoupper($d['status']) . '</div>';
    }
    $bank = $isInv && bankLines() ? '<div style="margin-top:22px;padding:12px 14px;background:#fdf4e7;border-radius:6px"><strong>Banking details</strong><br>' . implode('<br>', array_map('e', bankLines())) . '<br>Reference: <strong>' . e($d['number']) . '</strong></div>' : '';
    $terms = $isInv ? get_setting('invoice_footer', 'Thank you for your business. Goods remain the property of ' . APP_NAME . ' until paid in full.')
                    : 'This quote is valid until the date shown and subject to stock availability. ' . vatNote();

    return '<!doctype html><html><head><meta charset="utf-8"><title>' . e(($isInv ? 'Invoice ' : 'Quote ') . $d['number']) . '</title>
<style>@page{margin:28px 32px}body{font-family:DejaVu Sans,Helvetica,Arial,sans-serif;font-size:11px;color:#1c1917;margin:0}table{border-collapse:collapse;width:100%}</style></head><body>
<table><tr>
 <td style="vertical-align:top"><img src="' . BASE_DIR . '/assets/img/logo.png" style="height:58px">
   <div style="color:#78716c;line-height:1.5;margin-top:4px">' . implode('<br>', array_map('e', companyLines())) . '</div></td>
 <td style="vertical-align:top;text-align:right"><div style="font-size:26px;font-weight:bold;color:' . $brand . ';letter-spacing:1px">' . ($isInv ? 'TAX INVOICE' : 'QUOTATION') . '</div>
   <table style="width:auto;margin-left:auto;margin-top:6px">' . $meta . '</table>' . $stamp . '</td>
</tr></table>
<div style="height:3px;background:' . $brand . ';margin:16px 0"></div>
<table><tr><td style="vertical-align:top;width:55%"><div style="color:#78716c;font-size:10px;text-transform:uppercase;letter-spacing:1px">' . ($isInv ? 'Bill to' : 'Prepared for') . '</div>
 <div style="margin-top:4px;line-height:1.5">' . implode('<br>', array_map('e', array_filter($d['to']))) . '</div></td></tr></table>
<table style="margin-top:18px"><thead><tr style="background:#10233F;color:#fff">
 <th style="padding:8px 10px;text-align:left">Description</th><th style="padding:8px 10px;width:50px">Qty</th><th style="padding:8px 10px;text-align:right;width:90px">Unit price</th><th style="padding:8px 10px;text-align:right;width:100px">Amount</th></tr></thead>
 <tbody>' . $rows . '</tbody></table>
<table style="margin-top:10px"><tr><td style="width:55%;vertical-align:top;padding-right:20px">'
 . ($d['notes'] ? '<div style="color:#78716c;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-top:6px">Notes</div><div style="margin-top:4px;white-space:pre-line">' . e($d['notes']) . '</div>' : '')
 . '</td><td style="vertical-align:top"><table>' . $tot . '</table></td></tr></table>'
 . $bank . '
<div style="margin-top:26px;padding-top:10px;border-top:1px solid #e7e5e4;color:#78716c;font-size:9.5px">' . e($terms) . '</div>
</body></html>';
}

function renderPdf(string $html): string
{
    $o = new Options();
    $o->set('isRemoteEnabled', false);
    $o->set('defaultFont', 'DejaVu Sans');
    $o->setChroot(BASE_DIR);
    $pdf = new Dompdf($o);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4');
    $pdf->render();
    return (string) $pdf->output();
}

function quoteDocData(array $q): array
{
    return [
        'number' => $q['ref'], 'status' => '',
        'extra'  => ['Quote no.' => $q['ref'], 'Date' => date('j M Y', strtotime($q['quoted_at'] ?: $q['created_at'])),
                     'Valid until' => $q['valid_until'] ? date('j M Y', strtotime($q['valid_until'])) : ''],
        'to'     => [$q['company_name'], $q['full_name'], $q['email'], $q['phone'], trim(implode(', ', array_filter([$q['delivery_address'], $q['city'], $q['province']])))],
        'items'  => $q['items'], 'subtotal' => $q['subtotal'], 'delivery_fee' => $q['delivery_fee'], 'discount' => $q['discount'], 'total' => $q['total'],
        'notes'  => $q['customer_note'],
    ];
}

function quotePdf(array $q): string
{
    return renderPdf(documentHtml('quote', quoteDocData($q)));
}

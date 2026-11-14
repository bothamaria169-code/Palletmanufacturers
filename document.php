<?php
declare(strict_types=1);

/**
 * Printable quote / invoice, and its PDF (?pdf=1).
 * Open to: a signed link (from the emails), the customer it belongs to, or an admin.
 */
require_once __DIR__ . '/includes/boot.php';

$type = ($_GET['type'] ?? '') === 'invoice' ? 'invoice' : 'quote';
$id   = (int) ($_GET['id'] ?? 0);
$key  = (string) ($_GET['k'] ?? '');
$user = isLoggedIn() ? getCurrentUser() : null;

if ($type === 'invoice') {
    $doc = invoiceById($id);
    $ok = $doc && (($key !== '' && hash_equals(invoiceKey($id), $key)) || ($user && ($user['role'] === 'admin' || (int) $doc['user_id'] === (int) $user['id'])));
    if (!$ok || ($doc['status'] === 'cancelled' && (!$user || $user['role'] !== 'admin'))) { http_response_code(404); exit('Invoice not found.'); }
    $data = invoiceDocData($doc);
    $file = $doc['invoice_no'] . '.pdf';
} else {
    $doc = quoteById($id);
    $ok = $doc && (($key !== '' && hash_equals(quoteKey($id), $key)) || ($user && ($user['role'] === 'admin' || (int) $doc['user_id'] === (int) $user['id'])));
    if (!$ok) { http_response_code(404); exit('Quote not found.'); }
    $data = quoteDocData($doc);
    $file = 'Quote-' . $doc['ref'] . '.pdf';
}

$html = documentHtml($type, $data);
if (!empty($_GET['pdf'])) {
    $pdf = renderPdf($html);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $file . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Robots-Tag: noindex');
    echo $pdf;
    exit;
}

// Printable page: the same document with a small toolbar.
$self = APP_URL . '/document.php?type=' . $type . '&id=' . $id . ($key !== '' ? '&k=' . rawurlencode($key) : '');
$bar = '<div class="docbar" style="font-family:system-ui,sans-serif;background:#1c1917;color:#fff;padding:10px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between">'
     . '<strong>' . e(APP_NAME) . '</strong><span style="display:flex;gap:8px;flex-wrap:wrap">'
     . '<a href="' . e($self) . '&pdf=1&dl=1" style="background:#b45309;color:#fff;padding:9px 14px;border-radius:8px;text-decoration:none;font-weight:700">Download PDF</a>'
     . '<a href="#" onclick="window.print();return false" style="background:#fff;color:#1c1917;padding:9px 14px;border-radius:8px;text-decoration:none;font-weight:700">Print</a></span></div>'
     . '<style>@media print{.docbar{display:none!important}}body{background:#f5f5f4}.sheet{max-width:820px;margin:16px auto;background:#fff;padding:28px 32px;box-shadow:0 2px 12px rgba(0,0,0,.08)}@media(max-width:640px){.sheet{padding:16px;margin:0}}</style>';
$html = str_replace('<body>', '<body><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">' . $bar . '<div class="sheet">', $html);
echo str_replace('</body>', '</div></body>', $html);

<?php
declare(strict_types=1);

/**
 * Customer email templates, editable in Admin → Email templates.
 *
 * Plain text with {placeholders}. A blank line starts a new paragraph.
 * {items} drops in the table of pallets and totals. The branded letterhead,
 * the button and the footer are added around every template automatically.
 */
const EMAIL_TEMPLATES = [
    'quote_received' => [
        'label'   => 'Quote request received (to customer)',
        'when'    => 'Sent straight after a customer sends a quote request.',
        'vars'    => ['name', 'ref', 'items', 'business', 'phone'],
        'subject' => 'We received your quote request {ref}',
        'body'    => "Hi {name},\n\nThank you for your quote request {ref}. Our sales team will confirm prices, stock and delivery — usually within one working day.\n\n{items}\n\nPrices shown are today's list prices. Delivery and bulk discounts are added on your final quote.\n\nNeed it sooner? Call us on {phone}.",
        'button'  => 'Track my quote',
    ],
    'quote_sent' => [
        'label'   => 'Priced quote (to customer)',
        'when'    => 'Sent when you press "Save & email quote". The quote PDF is attached.',
        'vars'    => ['name', 'ref', 'total', 'valid_until', 'items', 'note', 'business', 'phone'],
        'subject' => 'Your quote {ref} from {business}',
        'body'    => "Hi {name},\n\nThank you for your enquiry. Please find your quote {ref} below and attached as a PDF. It is valid until {valid_until}.\n\n{items}\n\n{note}\n\nTo go ahead, press \"View & accept quote\" — we'll send your invoice and book delivery. Questions? Reply to this email or call {phone}.",
        'button'  => 'View & accept quote',
    ],
    'invoice_sent' => [
        'label'   => 'Invoice (to customer)',
        'when'    => 'Sent when you press "Email invoice". The invoice PDF is attached.',
        'vars'    => ['name', 'invoice_no', 'ref', 'total', 'due_date', 'bank', 'business', 'phone'],
        'subject' => 'Invoice {invoice_no} from {business}',
        'body'    => "Hi {name},\n\nThank you for your order. Your invoice {invoice_no} for {total} is attached, due by {due_date}.\n\n{bank}\n\nPlease use {invoice_no} as the payment reference and send proof of payment to this email address.",
        'button'  => 'View invoice',
    ],
    'enquiry_reply' => [
        'label'   => 'Contact form thank-you (to customer)',
        'when'    => 'Sent after a Contact-page message. If AI replies are on, the AI writes the middle part instead of this text.',
        'vars'    => ['name', 'business', 'phone'],
        'subject' => 'Thanks for contacting {business}',
        'body'    => "Hi {name},\n\nThank you for contacting {business}. We've received your message and one of our team will get back to you shortly — usually within one working day.\n\nIf you need pallets, the fastest way to a price is to request a quote on our website.",
        'button'  => 'Request a quote',
    ],
    'quote_message' => [
        'label'   => 'Message about a quote (to customer)',
        'when'    => 'Sent when you write a message on a quote in the admin.',
        'vars'    => ['name', 'ref', 'message', 'business'],
        'subject' => 'Message about your quote {ref}',
        'body'    => "Hi {name},\n\n{message}",
        'button'  => 'View & reply',
    ],
    'signature' => [
        'label'   => 'Signature (added to every customer email)',
        'when'    => 'Added at the bottom of all the templates above and of inbox replies.',
        'vars'    => ['business', 'phone', 'sales_email', 'website'],
        'subject' => '—',
        'body'    => "Kind regards,\nThe {business} sales team\n{phone} · {sales_email}\n{website}",
        'button'  => '',
    ],
];

/** The saved (or default) subject + body of a template. */
function templateText(string $key): array
{
    $def = EMAIL_TEMPLATES[$key];
    try {
        $s = getDB()->prepare('SELECT subject, body FROM email_templates WHERE tkey = ?');
        $s->execute([$key]);
        if ($row = $s->fetch()) return [$row['subject'], $row['body']];
    } catch (Throwable) {
    }
    return [$def['subject'], $def['body']];
}

/** Values every template can use. */
function templateBaseVars(): array
{
    return ['business' => APP_NAME, 'phone' => biz('biz_phone'), 'sales_email' => SALES_EMAIL, 'website' => preg_replace('#^https?://#', '', APP_URL)];
}

/** Plain text (already escaped) to paragraphs. */
function textToHtml(string $escaped): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($escaped)) ?: [] as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out .= str_starts_with($p, '<table') || str_starts_with($p, '<div') ? $p : '<p>' . nl2br($p, false) . '</p>';
    }
    return $out;
}

/**
 * Fills a template. Text values are escaped; keys in $htmlVars (e.g. items)
 * are inserted as HTML.
 *
 * @return array{0: string, 1: string} subject, body HTML (with signature)
 */
function renderTemplate(string $key, array $vars, array $htmlVars = []): array
{
    [$subject, $body] = templateText($key);
    $vars = array_merge(templateBaseVars(), $vars);
    $sub = strtr($subject, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', $vars)));
    $html = e($body);
    foreach ($vars as $k => $v) $html = str_replace('{' . $k . '}', e((string) $v), $html);
    foreach ($htmlVars as $k => $v) $html = str_replace('{' . $k . '}', "\n\n" . $v . "\n\n", $html);
    $html = textToHtml(preg_replace('/\{[a-z_]+\}/', '', $html));
    return [$sub, $html . signatureHtml()];
}

function signatureHtml(): string
{
    [, $sig] = templateText('signature');
    $vars = templateBaseVars();
    $h = e($sig);
    foreach ($vars as $k => $v) $h = str_replace('{' . $k . '}', e($v), $h);
    return '<p style="margin-top:22px;color:#4a5768">' . nl2br($h, false) . '</p>';
}

function templateButton(string $key): string
{
    return EMAIL_TEMPLATES[$key]['button'] ?? '';
}

<?php
declare(strict_types=1);

/**
 * Builds docs/Durbans-Pallets-Guide.pdf (the owner's guide) from the
 * screenshots in docs/guide/. Run: php docs/build-guide.php
 * With --logins it also builds _private/Durbans-Pallets-Logins.pdf from
 * _private/logins.json (never committed).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

const SITE  = 'durbanspallets.co.za';
const BRAND = '#b45309';
$img = fn(string $f, int $w = 100) => '<img src="' . str_replace('\\', '/', __DIR__) . '/guide/' . $f . '.jpg" style="width:' . $w . '%;border:1px solid #e7e5e4;border-radius:6px">';
$h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/** A row of journey steps joined by arrows. */
function journey(array $steps, string $color): string
{
    $out = '<table class="journey"><tr>';
    foreach ($steps as $i => [$icon, $title, $text]) {
        if ($i) $out .= '<td class="arrow">&#9654;</td>';
        $out .= '<td class="step" style="border-top:4px solid ' . $color . '"><div class="num" style="background:' . $color . '">' . ($i + 1) . '</div>'
              . '<div class="st">' . $title . '</div><div class="sx">' . $text . '</div></td>';
    }
    return $out . '</tr></table>';
}

function pdf(string $html, string $file): void
{
    $o = new Options();
    $o->set('isRemoteEnabled', false);
    $o->set('defaultFont', 'DejaVu Sans');
    $o->setChroot(dirname(__DIR__));
    $d = new Dompdf($o);
    $d->loadHtml($html, 'UTF-8');
    $d->setPaper('A4');
    $d->render();
    $c = $d->getCanvas();
    $c->page_text(520, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 7.5, [0.47, 0.44, 0.42]);
    file_put_contents($file, $d->output());
    echo 'wrote ' . $file . ' (' . round(filesize($file) / 1024) . " KB)\n";
}

$css = '<style>
@page { margin: 34px 38px 44px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1c1917; line-height: 1.5; }
h1 { font-size: 26px; margin: 0 0 6px; } h2 { font-size: 17px; margin: 0 0 8px; color: #1c1917; border-bottom: 3px solid ' . BRAND . '; padding-bottom: 5px; }
h3 { font-size: 12.5px; margin: 14px 0 5px; color: ' . BRAND . '; }
p { margin: 0 0 7px; } ol, ul { margin: 0 0 8px; padding-left: 18px; } li { margin-bottom: 3px; }
.page { page-break-after: always; } .page:last-child { page-break-after: auto; }
.cover { background: #1c1917; color: #fff; padding: 46px 40px; border-radius: 10px; }
.cover .brand { font-size: 30px; font-weight: bold; } .cover .brand span { color: #f59e0b; }
.pill { display: inline-block; background: #fdf4e7; color: ' . BRAND . '; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
.box { background: #faf8f6; border: 1px solid #e7e5e4; border-radius: 8px; padding: 10px 12px; margin: 8px 0; }
.tip { background: #fdf4e7; border-left: 4px solid ' . BRAND . '; padding: 8px 12px; margin: 8px 0; border-radius: 4px; }
table { border-collapse: collapse; width: 100%; }
.grid td { border: 1px solid #e7e5e4; padding: 6px 8px; vertical-align: top; }
.grid th { background: #1c1917; color: #fff; text-align: left; padding: 6px 8px; font-size: 9.5px; }
.journey { margin: 8px 0 4px; } .journey td { vertical-align: top; }
.step { background: #faf8f6; border: 1px solid #e7e5e4; border-radius: 6px; padding: 8px 7px; width: 22%; }
.num { color: #fff; width: 18px; height: 18px; line-height: 18px; text-align: center; border-radius: 9px; font-weight: bold; font-size: 9.5px; margin-bottom: 4px; }
.st { font-weight: bold; font-size: 10px; margin-bottom: 2px; } .sx { font-size: 8.8px; color: #57534e; }
.arrow { width: 3%; text-align: center; color: #a8a29e; font-size: 11px; padding-top: 22px; }
.caption { font-size: 8.5px; color: #78716c; margin: 3px 0 10px; }
.two td { vertical-align: top; } b.btn { background: ' . BRAND . '; color: #fff; padding: 0 5px; border-radius: 3px; font-weight: bold; }
</style>';

$customerJourney1 = journey([
    ['', 'Finds the site', 'Google, WhatsApp or the address durbanspallets.co.za'],
    ['', 'Picks pallets', 'Browses by type, opens a pallet, sets the quantity'],
    ['', 'Adds to quote', 'Presses <b>Add to quote</b> — no card or payment needed'],
    ['', 'Sends request', 'Fills in delivery details → <b>Send quote request</b>'],
], BRAND);
$customerJourney2 = journey([
    ['', 'Gets an email', 'A copy of the request with a <b>Track my quote</b> link'],
    ['', 'Gets your price', 'Your priced quote by email, PDF attached'],
    ['', 'Accepts online', 'Presses <b>Accept quote</b> — you get an email'],
    ['', 'Invoice & delivery', 'Gets the invoice PDF, pays by EFT, you deliver'],
], '#16a34a');
$adminJourney = journey([
    ['', 'New request', 'Email to sales@ + red number on <b>Quotes</b>'],
    ['', 'Price it', 'Type unit prices, delivery, discount, note'],
    ['', 'Send', '<b>Save &amp; email quote</b> (PDF attached)'],
    ['', 'Accepted', 'Email to you; quote shows <b>Accepted</b>'],
], '#1c1917');
$adminJourney2 = journey([
    ['', 'Invoice', '<b>Create invoice from this quote</b>'],
    ['', 'Email it', '<b>Email invoice (PDF)</b> with bank details'],
    ['', 'Get paid', 'Money in → <b>Mark as paid</b>'],
    ['', 'Deliver', 'Status → <b>Delivered</b> → <b>Save</b>'],
], '#7c5cff');

$html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>

<div class="page">
  <div class="cover">
    <div class="brand">Durban\'s <span>Pallets</span></div>
    <div style="color:#d6d3d1;margin-top:4px">' . SITE . '</div>
    <div style="font-size:24px;font-weight:bold;margin-top:60px">Website &amp; admin guide</div>
    <div style="color:#d6d3d1;font-size:12px;margin-top:6px">How customers order, and how you price quotes, send invoices and answer email.</div>
    <div style="margin-top:70px;color:#a8a29e;font-size:9px">Version ' . date('j F Y') . ' · Your passwords are on a separate login sheet.</div>
  </div>
  <h3 style="margin-top:22px">In this guide</h3>
  <table class="grid"><tr><th style="width:8%">#</th><th>Section</th><th style="width:15%">Page</th></tr>
   <tr><td>1</td><td>How the website works — the two journeys</td><td>2</td></tr>
   <tr><td>2</td><td>What your customers see, step by step</td><td>3–5</td></tr>
   <tr><td>3</td><td>Signing in to your admin panel</td><td>6</td></tr>
   <tr><td>4</td><td>Daily work: pricing and sending a quote</td><td>7</td></tr>
   <tr><td>5</td><td>Invoices and getting paid</td><td>8</td></tr>
   <tr><td>6</td><td>Email: the sales@ inbox, enquiries and templates</td><td>9–10</td></tr>
   <tr><td>7</td><td>Products, prices and photos</td><td>11</td></tr>
   <tr><td>8</td><td>Settings, emails customers receive, and help</td><td>12</td></tr></table>
  <div class="tip"><b>The idea in one line:</b> customers don\'t pay online. They build a <b>quote request</b>, you send the price, they accept it, and you invoice them.</div>
</div>

<div class="page">
  <h2>1. How the website works</h2>
  <p>There are two sides: the <b>website</b> your customers use, and your <b>admin panel</b> at <b>' . SITE . '/admin</b>. Everything a customer does lands in the admin panel and in the <b>sales@durbanspallets.co.za</b> mailbox.</p>
  <h3>The customer\'s journey</h3>
  ' . $customerJourney1 . '<div style="text-align:center;color:#a8a29e;font-size:11px">&#9660;</div>' . $customerJourney2 . '
  <h3>Your journey (admin)</h3>
  ' . $adminJourney . '<div style="text-align:center;color:#a8a29e;font-size:11px">&#9660;</div>' . $adminJourney2 . '
  <div class="box"><b>Quote statuses you will see:</b> <span class="pill">New request</span> just arrived · <span class="pill">Being priced</span> you saved it · <span class="pill">Quote sent</span> emailed to the customer · <span class="pill">Accepted</span> / <span class="pill">Declined</span> the customer\'s answer · <span class="pill">Delivered</span> done.</div>
  <div class="tip">Customers <b>don\'t need an account</b>. Every email has a secure link to their quote. If they make a free account with the same email, all their quotes and invoices appear in their own dashboard.</div>
</div>

<div class="page">
  <h2>2. What your customers see</h2>
  <h3>Step 1 — The home page</h3>
  ' . $img('01-home', 80) . '<p class="caption">The home page shows the pallet types, best sellers, how ordering works, reviews and questions. The orange <b>My quote</b> button (top right) is their basket. The green button opens WhatsApp to 078 727 5235.</p>
  <h3>Step 2 — Choosing pallets</h3>
  ' . $img('02-shop', 80) . '<p class="caption">All 37 pallets are grouped as Wooden (2-way, 4-way, Euro), Plastic, Aluminium, Galvanised &amp; Steel and Custom. Customers can sort by price.</p>
</div>

<div class="page">
  <h3>Step 3 — A pallet\'s page</h3>
  ' . $img('03-product', 80) . '<p class="caption">They set the quantity (the estimate updates), add a note such as "heat treated", and press <b>Add to quote</b>. <b>Ask</b> opens WhatsApp with the pallet\'s name filled in.</p>
  <h3>Step 4 — Sending the quote request</h3>
  ' . $img('05-quote-basket', 80) . '<p class="caption">On <b>My quote</b> they check quantities, fill in name, email, phone and delivery address, and press <b>Send quote request</b>. They get a copy by email straight away, and you get one at sales@.</p>
</div>

<div class="page">
  <h3>Step 5 — Their quote is ready</h3>
  ' . $img('06-quote-ready', 78) . '<p class="caption">When you send the price, the customer\'s email links here. They see the progress bar, your prices and note, and press <b>Accept quote</b> or <b>Decline</b>. They can message you, download the quote PDF, and later open their invoice.</p>
  <table class="two"><tr><td style="width:56%;padding-right:12px">
    <h3>Step 6 — The invoice (PDF)</h3>' . $img('07-invoice', 72) . '<p class="caption">The invoice shows your banking details and the invoice number to use as the payment reference.</p>
  </td><td style="width:44%">
    <h3>On a phone</h3>' . $img('04-home-phone', 55) . '<p class="caption">The whole site works on phones. The menu sits behind the ☰ button.</p>
  </td></tr></table>
</div>

<div class="page">
  <h3>Step 7 — The customer\'s own dashboard</h3>
  ' . $img('08-customer-dashboard') . '<p class="caption">Customers with an account see all their quotes and invoices. They can re-order the same pallets in one click and save their delivery details.</p>
  <h2 style="margin-top:16px">3. Signing in to your admin panel</h2>
  <ol>
    <li>Go to <b>' . SITE . '/admin</b> (or press the person icon at the top of the website).</li>
    <li>Type the admin <b>email</b> and <b>password</b> from your login sheet → <b>Sign in</b>.</li>
    <li>First time: left menu, bottom → <b>My account</b> → <b>Password</b> → choose your own password → <b>Change password</b>.</li>
  </ol>
  <div class="tip"><b>Forgot the password?</b> On the sign-in page press <b>Forgot password?</b> — a reset link is emailed to you. It works once and expires in 60 minutes.</div>
  <div class="box"><b>Left menu:</b> Dashboard · <b>Quotes</b> · <b>Invoices</b> · <b>Inbox (sales@)</b> · Enquiries · Customers · Products · Categories · Blog · <b>Email templates</b> · Sent log · <b>Settings</b> · Audit log. The green numbers show what needs your attention.</div>
</div>

<div class="page">
  <h2>4. Daily work: pricing and sending a quote</h2>
  ' . $img('12-admin-price-quote', 92) . '
  <ol>
    <li>Left menu → <b>Quotes</b>. New requests show <span class="pill">New request</span>. Press <b>Price it</b>.</li>
    <li>In <b>Lines &amp; prices</b>, type the <b>Unit price</b> for each line. "List" is the website price, for reference. Change the quantity if needed, or tick the bin to remove a line.</li>
    <li>Need something extra? Under <b>Add a line</b> pick a product, or type a custom item and its price.</li>
    <li>Fill in <b>Delivery (R)</b>, a <b>Discount</b> if any, and <b>Valid until</b>.</li>
    <li>Optional: <b>Note on the quote</b> (the customer sees it) and an <b>Internal note</b> (staff only).</li>
    <li>Press <b>Save &amp; email quote</b>. The customer gets the price with the PDF attached and an <b>Accept</b> button.</li>
  </ol>
  <div class="tip"><b>Customer on WhatsApp?</b> Press the green <b>WhatsApp</b> button on the quote. It opens a chat with a link to their quote. <b>Phoned or walked in?</b> Quotes → <b>New quote</b>.</div>
</div>

<div class="page">
  <h2>5. Invoices and getting paid</h2>
  ' . $img('13-admin-invoice', 92) . '
  <ol>
    <li>Open the accepted quote → <b>Create invoice from this quote</b>. It copies the prices, delivery and discount, and gets a number like INV-00012.</li>
    <li>Check it, then press <b>Email invoice (PDF)</b>. The customer gets the PDF with your banking details.</li>
    <li>When the money is in → <b>Mark as paid</b>. The PDF then shows PAID.</li>
    <li>Prices changed? <b>Cancel invoice</b>, fix the quote, and create a new invoice.</li>
  </ol>
  <div class="box"><b>Before your first invoice:</b> <b>Settings → Invoices</b> → bank, account name, account number, branch code, payment days, and your VAT / company registration numbers → <b>Save</b>. These print on every invoice.</div>
  <p><b>Invoices</b> in the left menu shows what is unpaid, overdue and paid.</p>
</div>

<div class="page">
  <h2>6. Email: the sales@ inbox</h2>
  ' . $img('18-admin-inbox', 92) . '
  <ol>
    <li>Left menu → <b>Inbox (sales@)</b> shows the sales@durbanspallets.co.za mailbox. Unread emails have an orange dot.</li>
    <li>Click an email to read it. If the sender has quotes or invoices, they show under <b>This customer</b>.</li>
    <li>Write under <b>Reply</b> → <b>Send</b>. You can attach a quote or invoice PDF for that customer. Your signature is added and a copy goes into Sent.</li>
    <li><b>New email</b> (top right) writes a fresh email. <b>Sent</b> shows what went out.</li>
  </ol>
  <div class="tip">The screen above appears until the sales@ password is saved in <b>Settings → Email</b>. After that it shows the real mailbox. You can still use webmail at <b>' . SITE . '/webmail</b>, sign in with the full email address.</div>
  <h3>Enquiries (Contact page)</h3>
  <p>Messages from the Contact page appear under <b>Enquiries</b> and are emailed to sales@. The customer gets an automatic thank-you. Press <b>Turn into a quote</b> to price it.</p>
</div>

<div class="page">
  <h3>Email templates — change the wording</h3>
  ' . $img('15-admin-templates', 92) . '
  <ol>
    <li>Left menu → <b>Email templates</b> → pick a tab (quote received, priced quote, invoice, thank-you, message, signature).</li>
    <li>Change the text. The words in {curly brackets} fill in by themselves — click one to insert it.</li>
    <li>The <b>Preview</b> on the right shows the email → <b>Save template</b>. <b>Reset to original</b> undoes your changes.</li>
  </ol>
  <h3>The emails customers receive</h3>
  <table class="grid"><tr><th>When</th><th>Email</th><th>Attachment</th></tr>
   <tr><td>They send a quote request</td><td>Quote request received + link to track it</td><td>—</td></tr>
   <tr><td>You press Save &amp; email quote</td><td>Your priced quote + Accept button</td><td>Quote PDF</td></tr>
   <tr><td>You email the invoice</td><td>Invoice with banking details</td><td>Invoice PDF</td></tr>
   <tr><td>You write a message on a quote</td><td>Message about the quote</td><td>—</td></tr>
   <tr><td>They use the Contact page</td><td>Thank-you (or a personal AI reply, if AI is on)</td><td>—</td></tr>
   <tr><td>They make an account / reset a password</td><td>Welcome / password link (from info@)</td><td>—</td></tr></table>
</div>

<div class="page">
  <h2>7. Products, prices and photos</h2>
  ' . $img('16-admin-products', 92) . '
  <ul>
    <li><b>Change a price:</b> Products → type the new price in the list → click elsewhere. It saves by itself.</li>
    <li><b>Hide or show a pallet:</b> press <b>Live</b> / <b>Hidden</b>. The ★ puts it on the home page.</li>
    <li><b>Add a pallet:</b> <b>Add product</b> → name, price, category, description → upload photos → <b>Save product</b>. Phone photos are fine; they are shrunk automatically.</li>
    <li><b>Sale price:</b> fill in <b>Was price</b> and the site shows a red "-%" badge.</li>
    <li><b>Write with AI</b> writes the description for you (needs the AI key in Settings → AI).</li>
  </ul>
  <div class="box">Leave the price empty and the website shows <b>Price on request</b> — handy for custom pallets.</div>
</div>

<div class="page">
  <h2>8. Settings and help</h2>
  ' . $img('17-admin-settings-email', 80) . '
  <table class="grid" style="margin-top:8px"><tr><th style="width:24%">Settings tab</th><th>What it\'s for</th></tr>
   <tr><td>Business</td><td>Name, phone, WhatsApp, email, address, hours — shown everywhere on the site.</td></tr>
   <tr><td>Quotes</td><td>Where new requests are emailed, and whether prices include VAT.</td></tr>
   <tr><td>Email</td><td>The info@ and sales@ mailbox passwords. Test with <b>Test info@</b>, <b>Test sales@</b> and <b>Test inbox</b>.</td></tr>
   <tr><td>Invoices</td><td>Bank details, VAT and registration numbers, payment days.</td></tr>
   <tr><td>AI (Groq)</td><td>Free AI for product descriptions and contact replies.</td></tr>
   <tr><td>Google &amp; Meta</td><td>Google Analytics, Search Console and Meta Pixel codes.</td></tr></table>
  <h3>Quick help</h3>
  <table class="grid"><tr><th style="width:38%">Problem</th><th>What to do</th></tr>
   <tr><td>Customer didn\'t get the quote email</td><td>Ask them to check spam. <b>Sent log</b> shows if it failed. You can also send the link by WhatsApp.</td></tr>
   <tr><td>Inbox shows an error</td><td><b>Settings → Email</b> → re-enter the sales@ password → <b>Test inbox</b>.</td></tr>
   <tr><td>Can\'t sign in to webmail or cPanel</td><td>Webmail takes the full email address. cPanel takes the hosting username, not an email address.</td></tr>
   <tr><td>A price on the invoice is wrong</td><td>Cancel the invoice, fix the quote, <b>Save</b>, create a new invoice.</td></tr></table>
</div>
</body></html>';

pdf($html, __DIR__ . '/Durbans-Pallets-Guide.pdf');

// Private login sheet (never committed)
if (in_array('--logins', $argv, true)) {
    $L = json_decode((string) file_get_contents(dirname(__DIR__) . '/_private/logins.json'), true);
    $rows = '';
    foreach ($L as $r) $rows .= '<tr><td><b>' . $h($r[0]) . '</b></td><td>' . $h($r[1]) . '</td><td>' . $h($r[2]) . '</td><td style="font-family:DejaVu Sans Mono">' . $h($r[3]) . '</td></tr>';
    $sheet = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>
    <div class="cover" style="padding:24px 30px"><div class="brand" style="font-size:22px">Durban\'s <span>Pallets</span></div><div style="color:#d6d3d1">Login sheet — keep this private</div></div>
    <table class="grid" style="margin-top:16px"><tr><th>What</th><th>Where</th><th>Username</th><th>Password</th></tr>' . $rows . '</table>
    <div class="tip" style="margin-top:14px"><b>Please change the admin password the first time you sign in:</b> admin panel → <b>My account</b> → <b>Password</b>. Don\'t send this sheet by email together with the website address.</div>
    <p style="margin-top:10px">Full instructions: <b>Durbans-Pallets-Guide.pdf</b>.</p></body></html>';
    pdf($sheet, dirname(__DIR__) . '/_private/Durbans-Pallets-Logins.pdf');
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once BASE_DIR . '/includes/ai.php';

$tabs = ['business' => 'Business', 'quotes' => 'Quotes', 'email' => 'Email', 'invoices' => 'Invoices', 'ai' => 'AI (Groq)', 'tracking' => 'Google & Meta'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'business';

$fields = [
    'business' => ['biz_name', 'biz_trading', 'biz_phone', 'biz_whatsapp', 'biz_email', 'biz_address', 'biz_hours', 'biz_maps', 'biz_facebook'],
    'quotes'   => ['notify_email', 'prices_include_vat'],
    'email'    => ['smtp_host', 'smtp_port', 'smtp_user', 'sales_smtp_user', 'imap_host', 'imap_port', 'mail_from_name'],
    'invoices' => ['company_reg', 'company_vat', 'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_account_type', 'invoice_terms_days', 'invoice_footer'],
    'ai'       => ['ai_enabled', 'ai_autoreply', 'ai_monthly_limit', 'groq_model'],
    'tracking' => ['ga4_id', 'gsc_verification', 'meta_pixel_id'],
];
$secrets = ['email' => ['smtp_pass', 'sales_smtp_pass'], 'ai' => ['groq_api_key']];
$checkboxes = ['prices_include_vat', 'ai_enabled', 'ai_autoreply'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if (in_array($action, ['test_email', 'test_sales'], true)) {
        $box = $action === 'test_sales' ? 'sales' : 'info';
        $to = $ADMIN['email'];
        $from = mailboxConfig($box)['from'];
        $err = sendMail($to, 'Test email from ' . $from, emailLayout('It works!', '<p>This came from <strong>' . e($from) . '</strong>. Your email settings are correct.</p>'), [], null, 'test', ['mailbox' => $box]);
        flash($err === '' ? 'success' : 'error', $err === '' ? "Test email sent from {$from} to {$to}. Check the inbox (and spam)." : 'Sending failed: ' . $err);
    } elseif ($action === 'test_inbox') {
        require_once BASE_DIR . '/includes/mailreader.php';
        try {
            $mr = MailReader::open();
            $n = $mr->select('INBOX');
            $sent = $mr->sentFolder();
            $mr->close();
            flash('success', "Inbox connected: {$n} emails in " . SALES_EMAIL . " (sent copies go to {$sent}).");
        } catch (Throwable $e) {
            flash('error', 'Inbox: ' . $e->getMessage());
        }
    } elseif ($action === 'test_ai') {
        $models = groqModels(true);
        if (!aiKey()) flash('error', 'Save a Groq API key first.');
        elseif (!$models) flash('error', 'Groq refused the key or could not be reached. Check the key at console.groq.com → API Keys.');
        else {
            $r = groqJson('Answer only with JSON {"ok":true,"msg":""}.', 'Say hello to a pallet company in 5 words.', 60);
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Groq works with ' . groqModel() . ': "' . ($r['data']['msg'] ?? 'ok') . '"' : 'Groq error: ' . $r['error']);
        }
    } else {
        foreach ($fields[$tab] as $k) {
            if (in_array($k, $checkboxes, true)) set_setting($k, !empty($_POST[$k]) ? '1' : '0');
            else set_setting($k, trim((string) ($_POST[$k] ?? '')));
        }
        foreach ($secrets[$tab] ?? [] as $k) save_secret_setting($k, (string) ($_POST[$k] ?? ''));
        audit('settings_save', $tab);
        flash('success', $tabs[$tab] . ' settings saved.');
    }
    redirect('admin/settings.php?tab=' . $tab);
}

$val = fn(string $k, string $d = '') => get_setting($k, $d);
$input = function (string $k, string $label, string $hint = '', string $default = '', string $type = 'text') use ($val) {
    return '<div class="field"><label for="' . $k . '">' . e($label) . ($hint ? ' <span class="hint">' . $hint . '</span>' : '') . '</label>'
         . '<input id="' . $k . '" type="' . $type . '" name="' . $k . '" value="' . e($val($k, $default)) . '"></div>';
};
$secretInput = fn(string $k, string $label, string $hint = '') => '<div class="field"><label for="' . $k . '">' . e($label) . ($hint ? ' <span class="hint">' . $hint . '</span>' : '') . '</label>'
    . '<input id="' . $k . '" type="password" name="' . $k . '" autocomplete="off" placeholder="' . e(masked(get_setting($k)) ?: 'not set') . '"></div>';

adminOpen('settings', 'Settings', 'Every key and detail the site uses. Changes apply straight away.');
?>
<div class="tabs"><?php foreach ($tabs as $k => $label): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'is-on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?></div>
<div class="card" style="max-width:860px">
<form class="card__body" method="post"><?= csrfField() ?>
<?php if ($tab === 'business'): ?>
  <p class="hint" style="margin-bottom:1rem">Shown in the header, footer, contact page, emails and Google's business schema. Keep name, address and phone identical to your Google Business Profile.</p>
  <div class="form-grid">
    <?= $input('biz_name', 'Business name', '', biz('biz_name')) ?>
    <?= $input('biz_trading', 'Trading as', '', biz('biz_trading')) ?>
    <?= $input('biz_phone', 'Phone', 'as shown on the site', biz('biz_phone')) ?>
    <?= $input('biz_whatsapp', 'WhatsApp number', 'digits only, e.g. 27787275235', biz('biz_whatsapp')) ?>
    <?= $input('biz_email', 'Sales email', '', biz('biz_email')) ?>
    <?= $input('biz_facebook', 'Facebook page link', 'optional', biz('biz_facebook')) ?>
    <div class="field full"><label for="biz_address">Address</label><input id="biz_address" name="biz_address" value="<?= e(biz('biz_address')) ?>"></div>
    <div class="field full"><label for="biz_maps">Google Maps link</label><input id="biz_maps" name="biz_maps" value="<?= e(biz('biz_maps')) ?>"></div>
    <div class="field full"><label for="biz_hours">Working hours <span class="hint">one line per row</span></label><textarea id="biz_hours" name="biz_hours" style="min-height:70px"><?= e(biz('biz_hours')) ?></textarea></div>
  </div>
<?php elseif ($tab === 'quotes'): ?>
  <?= $input('notify_email', 'Send new quote requests, enquiries and customer messages to', 'one email address', SALES_EMAIL, 'email') ?>
  <label class="check"><input type="checkbox" name="prices_include_vat" value="1" <?= $val('prices_include_vat', '1') === '1' ? 'checked' : '' ?>> <span>Website prices include 15% VAT <span class="hint">(untick if your prices are excl. VAT)</span></span></label>
<?php elseif ($tab === 'email'): ?>
  <p class="hint" style="margin-bottom:1rem">🟧 cPanel → <strong>Email Accounts</strong> → <strong>Connect Devices</strong> shows these values. Use the full email address as the username.</p>
  <div class="form-grid">
    <?= $input('smtp_host', 'Mail server', 'e.g. mail.durbanspallets.co.za', SMTP_HOST) ?>
    <?= $input('smtp_port', 'SMTP port', '465 (SSL) or 587', (string) SMTP_PORT) ?>
    <?= $input('mail_from_name', 'From name', '', APP_NAME) ?>
  </div>
  <h3 style="font-size:1rem;margin:1rem 0 .3rem">Info mailbox <span class="hint">account emails: welcome, password links</span></h3>
  <div class="form-grid">
    <?= $input('smtp_user', 'Email address', '', SMTP_USER ?: ADMIN_EMAIL) ?>
    <?= $secretInput('smtp_pass', 'Password', 'blank keeps the saved one') ?>
  </div>
  <h3 style="font-size:1rem;margin:1rem 0 .3rem">Sales mailbox <span class="hint">enquiries, quotes, invoices + the admin Inbox</span></h3>
  <div class="form-grid">
    <?= $input('sales_smtp_user', 'Email address', '', SALES_EMAIL) ?>
    <?= $secretInput('sales_smtp_pass', 'Password', 'blank keeps the saved one') ?>
    <?= $input('imap_host', 'Incoming (IMAP) server', 'blank = same mail server', '') ?>
    <?= $input('imap_port', 'IMAP port', '993', '993') ?>
  </div>
  <p class="hint">No sales password yet? Quotes go out from the info mailbox instead. Without any password the server's own mail is used (often lands in spam).</p>
<?php elseif ($tab === 'invoices'): ?>
  <p class="hint" style="margin-bottom:1rem">Printed on every invoice and quote PDF.</p>
  <div class="form-grid">
    <?= $input('company_reg', 'Company registration no.', 'optional') ?>
    <?= $input('company_vat', 'Your VAT no.', 'optional') ?>
    <?= $input('bank_name', 'Bank', 'e.g. FNB') ?>
    <?= $input('bank_account_name', 'Account name', '', APP_NAME) ?>
    <?= $input('bank_account_no', 'Account number') ?>
    <?= $input('bank_branch', 'Branch code') ?>
    <?= $input('bank_account_type', 'Account type', 'e.g. Business cheque') ?>
    <?= $input('invoice_terms_days', 'Payment due after (days)', '0 = on receipt', '7', 'number') ?>
    <div class="field full"><label for="invoice_footer">Footer text</label><input id="invoice_footer" name="invoice_footer" value="<?= e($val('invoice_footer', 'Thank you for your business. Goods remain the property of ' . APP_NAME . ' until paid in full.')) ?>"></div>
  </div>
<?php elseif ($tab === 'ai'): ?>
  <div class="status-line <?= aiEnabled() ? 'ok' : 'off' ?>"><span class="dot"></span><?= aiEnabled() ? 'AI is on · model ' . e(groqModel()) . ' · ' . aiUsedThisMonth() . ' calls this month' : 'AI is off — add a Groq key' ?></div>
  <ol class="steps-list"><li>🟦 Go to <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com → API Keys</a> → <strong>Create API Key</strong>.</li><li>Paste it below → <strong>Save</strong> → <strong>Test AI</strong>.</li></ol>
  <?= $secretInput('groq_api_key', 'Groq API key', 'starts with gsk_') ?>
  <div class="form-grid">
    <div class="field"><label for="groq_model">Model <span class="hint">blank = best available (recommended)</span></label><select id="groq_model" name="groq_model"><option value="">Automatic</option><?php foreach (groqModels() as $m): ?><option <?= $val('groq_model') === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <?= $input('ai_monthly_limit', 'Monthly limit (calls)', '', '500', 'number') ?>
  </div>
  <label class="check"><input type="checkbox" name="ai_enabled" value="1" <?= $val('ai_enabled', '1') === '1' ? 'checked' : '' ?>> <span>Use AI ("✨ Write with AI" on products)</span></label>
  <label class="check"><input type="checkbox" name="ai_autoreply" value="1" <?= $val('ai_autoreply', '1') === '1' ? 'checked' : '' ?>> <span>Personal AI reply to contact-form enquiries <span class="hint">(falls back to a standard thank-you)</span></span></label>
<?php else: ?>
  <div class="form-grid">
    <?= $input('ga4_id', 'Google Analytics 4 ID', 'G-XXXXXXXXXX') ?>
    <?= $input('meta_pixel_id', 'Meta Pixel ID', 'numbers only') ?>
    <div class="field full"><label for="gsc_verification">Google Search Console code <span class="hint">only the content="…" value of the HTML tag</span></label><input id="gsc_verification" name="gsc_verification" value="<?= e($val('gsc_verification')) ?>"></div>
  </div>
  <p class="hint">Sitemap for Search Console: <code><?= e(APP_URL) ?>/sitemap.php</code> <button type="button" class="btn-xs" data-copy="<?= e(APP_URL) ?>/sitemap.php" aria-label="Copy"><i class="fa-regular fa-copy"></i></button></p>
<?php endif; ?>
  <div class="form-foot" style="margin-top:1rem">
    <button class="btn btn-primary" name="action" value="save">Save</button>
    <?php if ($tab === 'email'): ?><button class="btn btn-ghost" name="action" value="test_email"><i class="fa-solid fa-paper-plane"></i>Test info@</button><button class="btn btn-ghost" name="action" value="test_sales"><i class="fa-solid fa-paper-plane"></i>Test sales@</button><button class="btn btn-ghost" name="action" value="test_inbox"><i class="fa-solid fa-inbox"></i>Test inbox</button><?php endif; ?>
    <?php if ($tab === 'ai'): ?><button class="btn btn-ghost" name="action" value="test_ai"><i class="fa-solid fa-vial"></i>Test AI</button><?php endif; ?>
  </div>
</form>
</div>
<?php adminClose();

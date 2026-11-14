<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
siteOpen('Privacy Policy | ' . APP_NAME, ['description' => 'How ' . APP_NAME . ' collects, uses and protects your personal information under POPIA.', 'canonical' => APP_URL . '/privacy.php']);
$email = e(biz('biz_email'));
?>
<section class="page-head"><div class="wrap"><h1>Privacy policy</h1><p>Last updated <?= date('j F Y', filemtime(__FILE__)) ?>. This policy explains how we handle your personal information under the Protection of Personal Information Act (POPIA).</p></div></section>
<div class="wrap prose rich" style="max-width:820px">
  <h3>Who we are</h3>
  <p><?= e(APP_NAME) ?> (trading as <?= e(biz('biz_trading')) ?>), <?= e(biz('biz_address')) ?>. Information officer contact: <a href="mailto:<?= $email ?>"><?= $email ?></a>.</p>
  <h3>What we collect</h3>
  <ul><li>Contact details you give us: name, company, email, phone/WhatsApp number, delivery address and VAT number.</li><li>The quotes you request, the messages you send us and your quote history.</li><li>Technical data: IP address and browser type (for security and spam prevention), and — if enabled — analytics and Meta Pixel cookies.</li></ul>
  <h3>Why we use it</h3>
  <ul><li>To prepare and send quotes, invoices and deliveries.</li><li>To answer your enquiries and messages.</li><li>To keep your account secure and stop abuse.</li><li>To improve our website and advertising (only aggregated or pseudonymous data).</li></ul>
  <h3>Who we share it with</h3>
  <p>Only with service providers who help us run the business — our web host, email provider, delivery partners and, for website analytics, Google and Meta. We never sell your information.</p>
  <h3>How long we keep it</h3>
  <p>Account data stays until you delete your account. Quotes and invoices are kept for five years for tax purposes.</p>
  <h3>Your rights</h3>
  <ul><li>See and download your data: Dashboard → Account &amp; delivery → <strong>Download my data</strong>.</li><li>Correct your details in the same place.</li><li>Delete your account: Account &amp; delivery → <strong>Delete my account</strong>.</li><li>Object or complain: email <a href="mailto:<?= $email ?>"><?= $email ?></a>, or contact the Information Regulator (inforegulator.org.za).</li></ul>
  <h3>Security</h3>
  <p>Passwords are stored hashed, all forms are protected against cross-site requests, and the site runs over HTTPS.</p>
</div>
<?php siteClose(false);

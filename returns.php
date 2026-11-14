<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
siteOpen('Return Policy | ' . APP_NAME, ['description' => '30-day returns on standard pallets. How to request a return, refunds and damaged items.', 'canonical' => APP_URL . '/returns.php']);
$email = e(biz('biz_email'));
?>
<section class="page-head"><div class="wrap"><nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <span>Return policy</span></nav><h1>Return policy</h1><p>We're committed to your satisfaction with every purchase. If you're not completely happy with your order, we'll help with a simple return.</p></div></section>
<div class="wrap prose rich" style="max-width:820px">
  <h3>Return period</h3>
  <ul><li>You may return items within <strong>30 days</strong> of receipt for a refund or exchange.</li><li>Items must be unused, in their original condition, with all original packaging, accessories and documentation.</li></ul>
  <h3>Non-returnable items</h3>
  <ul><li>Customised or special-order products.</li><li>Items marked as "Final Sale" at the time of purchase.</li><li>Products that have been used, damaged or altered after delivery.</li></ul>
  <h3>How to start a return</h3>
  <ul><li>Email our customer support team at <a href="mailto:<?= $email ?>"><?= $email ?></a> with your quote or order number and the reason for the return.</li><li>We'll give you a Return Authorisation (RA) number and return instructions.</li><li>Pack the item securely and include a copy of your invoice or order confirmation with the RA number.</li></ul>
  <h3>Return shipping</h3>
  <ul><li>You pay return shipping unless the return is due to our error (for example a wrong or defective item).</li><li>We recommend a trackable shipping method.</li></ul>
  <h3>Refunds</h3>
  <ul><li>Once we receive and inspect the item, we'll let you know whether the refund is approved.</li><li>Approved refunds are processed within <strong>5 business days</strong> to your original payment method.</li></ul>
  <h3>Damaged or defective items</h3>
  <p>If you receive a damaged or defective product, contact us within <strong>48 hours</strong> of delivery with photos of the damage and we'll arrange a replacement or refund.</p>
  <h3>Questions?</h3>
  <p>Email <a href="mailto:<?= $email ?>"><?= $email ?></a> or call <?= e(biz('biz_phone')) ?>.</p>
</div>
<?php siteClose(false);

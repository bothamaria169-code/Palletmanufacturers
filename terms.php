<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
siteOpen('Terms & Conditions | ' . APP_NAME, ['description' => 'Terms for quotes, orders, delivery and payment at ' . APP_NAME . '.', 'canonical' => APP_URL . '/terms.php']);
?>
<section class="page-head"><div class="wrap"><h1>Terms &amp; conditions</h1><p>These terms apply to quotes, orders and use of this website.</p></div></section>
<div class="wrap prose rich" style="max-width:820px">
  <h3>Quotes</h3>
  <ul><li>Prices on the website are list prices per pallet and may change without notice. <?= e(vatNote()) ?></li><li>A quote is valid for the period shown on it (normally 14 days) and subject to stock availability.</li><li>Delivery costs and bulk discounts are confirmed on each quote.</li></ul>
  <h3>Orders and payment</h3>
  <ul><li>An order is confirmed when you accept a quote and we issue an invoice.</li><li>Custom and special-order pallets may require a deposit before production.</li><li>Goods remain our property until paid in full.</li></ul>
  <h3>Delivery</h3>
  <ul><li>We deliver to the address on your quote. Someone must be available to receive the goods and a forklift or offloading help may be needed for large loads.</li><li>Delivery dates are estimates; we'll keep you informed of any delay.</li></ul>
  <h3>Recycled pallets</h3>
  <p>Recycled and repaired pallets are graded and fit for purpose but may show signs of previous use. Grades are described on each product.</p>
  <h3>Returns</h3>
  <p>See our <a href="<?= APP_URL ?>/returns.php">return policy</a>.</p>
  <h3>Liability</h3>
  <p>Load ratings are guidelines for evenly distributed static loads. You are responsible for choosing a pallet suited to your load, racking and handling equipment. Our liability is limited to the value of the goods supplied.</p>
  <h3>Your account</h3>
  <p>Keep your password private. We may suspend accounts used for abuse or fraud.</p>
  <h3>Law</h3>
  <p>These terms are governed by the laws of the Republic of South Africa.</p>
</div>
<?php siteClose(false);

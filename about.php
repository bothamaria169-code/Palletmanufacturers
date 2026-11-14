<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
$productCount = (int) getDB()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

siteOpen('About Us – Pallet Stockists & Recyclers in Durban | ' . APP_NAME, [
    'description' => 'We buy, sort, repair and resell pallets, and rebuild timber into standard and custom sizes. Affordable, high-quality pallets with a smaller carbon footprint.',
    'canonical' => APP_URL . '/about.php',
], 'about');
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <span>About us</span></nav>
    <h1>Who we are</h1>
    <p>We are stockists of a variety of pallets for every application — and we strive to be number one in producing pallet products.</p>
  </div>
</section>

<section class="section tight">
  <div class="wrap split">
    <div>
      <span class="kicker">Recycled. Repaired. Rebuilt.</span>
      <h2>Quality pallets without virgin timber</h2>
      <p>Our focus is on recycling pallets by buying them from a variety of commercial and industrial suppliers, categorising, repairing where necessary and reselling them back to the market.</p>
      <p>We also up-cycle material by stripping down pallets and crates into planks for reassembly into standard and custom sizes. This lets us offer pallets and related products at affordable prices while keeping a high quality standard.</p>
      <p>It also means we don't buy any virgin material — reducing our carbon footprint, and that of our customers.</p>
    </div>
    <div class="split__img"><img src="<?= APP_URL ?>/assets/img/cat-wooden.webp" alt="Stack of recycled timber pallets" width="480" height="700" loading="lazy" style="object-fit:contain;background:#fff"></div>
  </div>
</section>

<section class="section tight section--alt">
  <div class="wrap">
    <div class="stats">
      <div><b><?= $productCount ?>+</b><span>pallet types in stock</span></div>
      <div><b>5</b><span>materials: timber, plastic, aluminium, steel, custom</span></div>
      <div><b>0</b><span>virgin timber bought</span></div>
      <div><b>9</b><span>provinces delivered to</span></div>
    </div>
  </div>
</section>

<section class="section tight">
  <div class="wrap split split--rev">
    <div class="split__img"><img src="<?= APP_URL ?>/assets/img/hero.webp" alt="Wooden, plastic and metal pallets" width="1400" height="470" loading="lazy" style="object-fit:contain;background:var(--ink)"></div>
    <div>
      <span class="kicker">What we do</span>
      <h2>Pallets for every warehouse, truck and forklift</h2>
      <p>All businesses that use a warehousing system with forklifts and trucks need pallets. Whether your business is warehousing, logistics, manufacturing or import/export, we have suitable pallets for the job.</p>
      <p>All our products are manufactured from raw product and re-purposed material, hand-picked from the finest local suppliers.</p>
      <ul class="ticks">
        <li><i class="fa-solid fa-circle-check"></i>Easy-to-use online quotes, available 24/7</li>
        <li><i class="fa-solid fa-circle-check"></i>Professional service and on-time delivery</li>
        <li><i class="fa-solid fa-circle-check"></i>Skilled staff to help you choose the right pallet</li>
      </ul>
      <a class="btn btn-primary" href="<?= APP_URL ?>/contact.php">We look forward to hearing from you <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>
<?php siteClose();

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

[$featured] = findProducts(['featured' => true, 'limit' => 8]);
[$plastic]  = findProducts(['cat' => categoryBySlug('plastic-pallets'), 'limit' => 4]);
$posts = getDB()->query('SELECT * FROM posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3')->fetchAll();
$catImg = ['wooden-pallets' => 'cat-wooden', 'plastic-pallets' => 'cat-plastic', 'aluminium-pallets' => 'cat-aluminium', 'galvanised-pallets' => 'cat-galvanised', 'custom-pallets' => 'cat-custom'];
$productCount = (int) getDB()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

$faqs = [
    ['What pallet sizes do you stock?', 'Our standard sizes are 1000 × 1200 mm (the South African standard), 800 × 1200 mm Euro pallets, and square pallets of 1 × 1 m, 1.1 × 1.1 m and 1.2 × 1.2 m. We also build any custom size to order.'],
    ['Do you deliver?', 'Yes. We deliver across KwaZulu-Natal from our Glen Anil, Durban yard and arrange transport to Gauteng and the rest of South Africa. The delivery cost is shown on your quote.'],
    ['Is there a minimum order?', 'No minimum for collection. For delivery, bigger loads work out cheaper per pallet — ask for a quote and we\'ll give you the best price for your quantity.'],
    ['Can you make heat-treated export (ISPM 15) pallets?', 'Yes. We produce certified, heat-treated export pallets in standard and special sizes. Mention it in the notes on your quote request.'],
    ['Do you buy used pallets?', 'Yes. We buy, sort and repair used pallets from commercial and industrial suppliers. Contact us with the quantity and condition.'],
    ['How do I get a price?', 'Add the pallets you need to your quote and press "Send quote request". We reply with a priced quote, usually the same working day. You can accept it online in your account.'],
];
$faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)];

siteOpen('Wooden, Plastic & Aluminium Pallets for Sale in Durban | ' . APP_NAME, [
    'description' => 'Pallet manufacturer and supplier in Durban, KZN. Wooden, plastic, aluminium, galvanised and custom pallets at wholesale prices. Request a quote online.',
    'canonical'   => APP_URL . '/',
    'preload'     => APP_URL . '/assets/img/hero-banner.webp',
    'schema'      => [$faqSchema],
], 'home');
?>
<section class="hero-banner" aria-label="Durban's Pallets — quality pallets, nationwide delivery">
  <picture>
    <source media="(max-width: 760px)" srcset="<?= APP_URL ?>/assets/img/hero-banner-900.webp">
    <img src="<?= APP_URL ?>/assets/img/hero-banner.webp" width="1672" height="941" fetchpriority="high"
         alt="Durban's Pallets — Pallet Manufacturers. Quality pallets, nationwide delivery: wooden, plastic, aluminium, steel &amp; galvanised and custom pallets. 68 Columbine Place, Glen Anil, Durban, 4051 · info@durbanspallets.co.za">
  </picture>
</section>
<section class="hero-bar">
  <div class="wrap">
    <div>
      <h1>Pallet manufacturers in Durban — <span>wooden, plastic, aluminium &amp; custom pallets.</span></h1>
      <div class="phero__proof">
        <span><i class="fa-solid fa-check"></i><?= $productCount ?> pallet types in stock</span>
        <span><i class="fa-solid fa-check"></i>Competitive prices</span>
        <span><i class="fa-solid fa-check"></i>Delivered nationwide</span>
      </div>
    </div>
    <div class="hero-bar__cta">
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/shop.php"><i class="fa-solid fa-pallet"></i>Shop pallets</a>
      <a class="btn btn-ghost btn-lg" href="<?= APP_URL ?>/quote.php"><i class="fa-solid fa-file-invoice"></i>Get a quote</a>
    </div>
  </div>
</section>

<section class="trust" aria-label="Why buy from us">
  <div class="wrap">
    <div><i class="fa-solid fa-truck-fast"></i><span><strong>Fast delivery</strong><small>KZN, Gauteng &amp; nationwide</small></span></div>
    <div><i class="fa-solid fa-shield-halved"></i><span><strong>Money-back guarantee</strong><small>Refund or exchange if damaged</small></span></div>
    <div><i class="fa-solid fa-ruler-combined"></i><span><strong>Custom sizes</strong><small>Built to your load &amp; spec</small></span></div>
    <div><i class="fa-solid fa-headset"></i><span><strong>Expert support</strong><small>We guide you to the right pallet</small></span></div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker">Shop by type</span><h2>Pallets for every application</h2><p>From R55 recycled timber pallets to food-grade plastic and rust-free aluminium — pick a range to see every size and price.</p></div>
      <a class="btn btn-ghost" href="<?= APP_URL ?>/shop.php">All <?= $productCount ?> products <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="cats">
      <?php foreach (topCategories() as $c): ?>
        <a class="cat" href="<?= e(categoryUrl($c)) ?>">
          <div class="cat__img"><img src="<?= APP_URL ?>/assets/img/<?= e($catImg[$c['slug']] ?? 'cat-wooden') ?>.webp" alt="<?= e($c['name']) ?>" width="480" height="700" loading="lazy"></div>
          <div class="cat__body"><strong><?= e($c['name']) ?></strong><small><?= (int) $c['product_count'] ?> products · Shop now</small></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker">Best sellers</span><h2>Featured pallets</h2><p>We supply small and large enterprises with a "basket" of differentiated pallets, aggregated and delivered as you need them.</p></div>
      <a class="btn btn-ghost" href="<?= APP_URL ?>/shop.php">Shop all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="pgrid"><?php foreach ($featured as $p) echo productCard($p); ?></div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">How it works</span><h2>From quote to delivery in four steps</h2></div></div>
    <div class="how">
      <div><h3>Pick your pallets</h3><p>Browse by type and size. Add each pallet and the quantity you need to your quote.</p></div>
      <div><h3>Send the request</h3><p>Tell us where to deliver and any special needs — heat treatment, custom sizes, load weight.</p></div>
      <div><h3>Get a priced quote</h3><p>Our sales team confirms prices, stock and delivery — usually the same working day.</p></div>
      <div><h3>Accept &amp; receive</h3><p>Accept the quote in your account. We invoice, load and deliver on time.</p></div>
    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="wrap split">
    <div class="split__img"><img src="<?= APP_URL ?>/assets/img/banner.webp" alt="Stacks of recycled timber pallets" width="1000" height="363" loading="lazy" style="object-fit:contain;background:#fff"></div>
    <div>
      <span class="kicker">Who we are</span>
      <h2>Stockists of pallets for every application</h2>
      <p>Our focus is on recycling pallets: we buy them from commercial and industrial suppliers, sort them, repair where needed and resell them to the market. We also strip pallets and crates into planks and rebuild them into standard and custom sizes.</p>
      <ul class="ticks">
        <li><i class="fa-solid fa-circle-check"></i>Affordable prices with a high quality standard</li>
        <li><i class="fa-solid fa-circle-check"></i>No virgin timber — a smaller carbon footprint for you and us</li>
        <li><i class="fa-solid fa-circle-check"></i>Warehousing, logistics, manufacturing and import/export</li>
      </ul>
      <a class="btn btn-dark" href="<?= APP_URL ?>/about.php">About us <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<?php if ($plastic): ?>
<section class="section">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="kicker">Hygienic &amp; weather-proof</span><h2>Plastic pallets</h2><p>Nestable, solid-top, mesh and spill pallets for food, pharma and outdoor storage.</p></div>
      <a class="btn btn-ghost" href="<?= APP_URL ?>/shop.php?cat=plastic-pallets">All plastic pallets <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="pgrid"><?php foreach ($plastic as $p) echo productCard($p); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section section--alt">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">Reviews</span><h2>What our customers say</h2></div></div>
    <div class="quotes-grid">
      <div class="tcard"><div class="stars">★★★★★</div><p>"They surely know what they are doing. They produced our special sized, certified and high quality export pallets in record time."</p><div><strong>Christiaan Scholes</strong><small>Marketing</small></div></div>
      <div class="tcard"><div class="stars">★★★★★</div><p>"Fantastic service. Very customer-centric and on-time delivery. Experts in pallets &amp; crates."</p><div><strong>Raul Magadlela</strong><small>Wholesaler</small></div></div>
      <div class="tcard"><div class="stars">★★★★★</div><p>"Very good service &amp; delivery times! I definitely recommend them!"</p><div><strong>Donald Coundouris</strong><small>Customer</small></div></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head" style="justify-content:center;text-align:center"><div><span class="kicker">FAQ</span><h2>Questions we get every day</h2></div></div>
    <div class="faq">
      <?php foreach ($faqs as [$q, $a]): ?><details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($posts): ?>
<section class="section section--alt">
  <div class="wrap">
    <div class="sec-head"><div><span class="kicker">Pallet guide</span><h2>Latest from the blog</h2></div><a class="btn btn-ghost" href="<?= APP_URL ?>/blog.php">All articles <i class="fa-solid fa-arrow-right"></i></a></div>
    <div class="posts">
      <?php foreach ($posts as $p): ?>
        <a class="post-card" href="<?= APP_URL ?>/post.php?s=<?= e($p['slug']) ?>"><time><?= date('j M Y', strtotime($p['published_at'])) ?></time><h3><?= e($p['title']) ?></h3><p><?= e(excerpt($p['excerpt'] ?: $p['body'], 140)) ?></p><span>Read more →</span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php siteClose();

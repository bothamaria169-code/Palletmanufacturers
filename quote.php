<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$user  = isLoggedIn() ? getCurrentUser() : null;
$lines = basketLines();
$errors = [];
$d = [
    'full_name' => $user['full_name'] ?? '', 'email' => $user['email'] ?? '', 'phone' => $user['phone'] ?? '',
    'company_name' => $user['company_name'] ?? '', 'delivery_address' => $user['address'] ?? '', 'city' => $user['city'] ?? '',
    'province' => $user['province'] ?? 'KwaZulu-Natal', 'needed_by' => '', 'message' => '',
];

// Re-order: copy the lines of an earlier quote into the basket.
if (isset($_GET['reorder']) && $user) {
    if ($old = quoteForUser((int) $_GET['reorder'], $user)) {
        foreach ($old['items'] as $it) if ($it['product_id']) basketSet((int) $it['product_id'], (int) $it['qty'], (string) $it['notes']);
        flash('success', 'Your items from ' . $old['ref'] . ' are in a new quote. Check the quantities and send.');
    }
    redirect('quote.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($d) as $k) $d[$k] = postText($k, $k === 'message' ? 3000 : 255);
    if ($user) $d['email'] = $user['email'];
    if (mb_strlen($d['full_name']) < 2) $errors[] = 'Please enter your name.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen(preg_replace('/\D/', '', $d['phone'])) < 9) $errors[] = 'Please enter a phone or WhatsApp number we can reach you on.';
    if (!$lines && mb_strlen($d['message']) < 10) $errors[] = 'Add at least one pallet to your quote, or describe what you need in the message box.';
    if ($d['province'] !== '' && !in_array($d['province'], PROVINCES, true)) $d['province'] = '';
    if ($d['needed_by'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['needed_by'])) $d['needed_by'] = '';
    if (!empty($_POST['website'])) $errors[] = 'Something went wrong.'; // honeypot
    if (empty($_POST['popia'])) $errors[] = 'Please agree that we may use your details to prepare your quote.';

    $s = getDB()->prepare('SELECT COUNT(*) FROM quotes WHERE email = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $s->execute([strtolower($d['email'])]);
    if ((int) $s->fetchColumn() >= 6) $errors[] = 'You\'ve sent several requests in the last hour. Please call or WhatsApp us instead.';

    if (!$errors) {
        $id = createQuote($d, $lines, $user ? (int) $user['id'] : (($u = findUserByEmail($d['email'])) ? (int) $u['id'] : null));
        basketClear();
        flash('success', 'Quote request sent! We\'ve emailed you a copy and will reply with prices soon.');
        header('Location: ' . ($user ? APP_URL . '/quote-view.php?id=' . $id . '&new=1' : quoteLink($id) . '&new=1'));
        exit;
    }
}

$est = array_sum(array_map(fn($l) => $l['line'] ?? 0, $lines));
siteOpen('My quote request | ' . APP_NAME, ['noindex' => true, 'description' => 'Request a quote for pallets from ' . APP_NAME . '.'], 'quote');
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <span>Quote request</span></nav>
    <h1>Your quote request</h1>
    <p>Check the pallets and quantities, tell us where to deliver, and send. We'll reply with a priced quote — usually the same working day.</p>
  </div>
</section>

<div class="wrap qgrid">
  <div>
    <?= flashHtml() ?>
    <div class="card">
      <div class="card__head"><h2><i class="fa-solid fa-pallet" style="color:var(--brand)"></i> Pallets in this quote</h2><a href="<?= APP_URL ?>/shop.php">+ Add more</a></div>
      <?php if ($lines): ?>
        <?php foreach ($lines as $l): $p = $l['product']; ?>
          <div class="qline" data-id="<?= (int) $p['id'] ?>">
            <a href="<?= e(productUrl($p)) ?>"><img src="<?= e(imgUrl($p['images'][0] ?? null, true)) ?>" alt="" width="72" height="72" loading="lazy"></a>
            <div>
              <h3><a href="<?= e(productUrl($p)) ?>"><?= e($p['name']) ?></a></h3>
              <small><?= $p['price'] !== null ? rand_($p['price']) . ' each' : 'Price on request' ?></small>
              <input class="note" type="text" maxlength="300" value="<?= e($l['notes']) ?>" placeholder="Notes (optional)" aria-label="Notes for <?= e($p['name']) ?>">
            </div>
            <div class="qline__side">
              <div class="qty"><button type="button" data-step="-1" aria-label="Less">−</button><input type="number" min="1" value="<?= (int) $l['qty'] ?>" aria-label="Quantity"><button type="button" data-step="1" aria-label="More">+</button></div>
              <strong data-line><?= $l['line'] !== null ? rand_($l['line'], true) : 'TBC' ?></strong>
              <button type="button" class="rm"><i class="fa-solid fa-trash-can"></i> Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="totals">
          <div class="totals__grand"><span>Estimate</span><b id="basketEst"><?= rand_($est, true) ?></b></div>
          <small>At today's list prices. <?= e(vatNote()) ?> Delivery and bulk discounts are added on your final quote.</small>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-file-circle-plus"></i>
          <h2><?= isset($_GET['custom']) ? 'Custom pallet request' : 'No pallets added yet' ?></h2>
          <p><?= isset($_GET['custom']) ? 'Describe the size, load, timber or material and quantity in the form — we\'ll price it for you.' : 'Browse the shop and press "Add to quote", or just describe what you need in the form.' ?></p>
          <a class="btn btn-primary" href="<?= APP_URL ?>/shop.php"><i class="fa-solid fa-pallet"></i>Browse pallets</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" class="form-card" data-busy novalidate>
    <h2>Delivery &amp; contact details</h2>
    <p><?= $user ? 'Signed in as <strong>' . e($user['email']) . '</strong> — this quote will be saved in your dashboard.' : 'Already a customer? <a href="' . APP_URL . '/login.php?next=' . rawurlencode('/quote.php') . '">Sign in</a> to save it to your account.' ?></p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div><?php endforeach; ?>
    <?= csrfField() ?>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="form-grid2">
      <div class="field"><label for="full_name">Your name</label><input id="full_name" name="full_name" required autocomplete="name" value="<?= e($d['full_name']) ?>"></div>
      <div class="field"><label for="company_name">Company <span class="hint">optional</span></label><input id="company_name" name="company_name" autocomplete="organization" value="<?= e($d['company_name']) ?>"></div>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e($d['email']) ?>" <?= $user ? 'readonly' : '' ?>></div>
      <div class="field"><label for="phone">Phone / WhatsApp</label><input id="phone" type="tel" name="phone" required autocomplete="tel" value="<?= e($d['phone']) ?>"></div>
      <div class="field full"><label for="delivery_address">Delivery address <span class="hint">or "collect"</span></label><input id="delivery_address" name="delivery_address" autocomplete="street-address" value="<?= e($d['delivery_address']) ?>"></div>
      <div class="field"><label for="city">Town / city</label><input id="city" name="city" autocomplete="address-level2" value="<?= e($d['city']) ?>"></div>
      <div class="field"><label for="province">Province</label><select id="province" name="province"><?php foreach (PROVINCES as $pv): ?><option <?= $d['province'] === $pv ? 'selected' : '' ?>><?= e($pv) ?></option><?php endforeach; ?></select></div>
      <div class="field full"><label for="needed_by">Needed by <span class="hint">optional</span></label><input id="needed_by" type="date" name="needed_by" min="<?= date('Y-m-d') ?>" value="<?= e($d['needed_by']) ?>"></div>
      <div class="field full"><label for="message"><?= $lines ? 'Anything else?' : 'What do you need?' ?> <span class="hint"><?= $lines ? 'optional' : 'size, material, load, quantity' ?></span></label><textarea id="message" name="message" placeholder="<?= $lines ? 'E.g. heat-treated for export, delivery on a weekday morning…' : 'E.g. 300 × 4-way pallets, 1100 × 1300 mm, 1 ton load, heat-treated…' ?>"><?= e($d['message']) ?></textarea></div>
    </div>
    <label class="check"><input type="checkbox" name="popia" value="1" <?= !empty($_POST['popia']) || $user ? 'checked' : '' ?> required> <span>I agree that <?= e(APP_NAME) ?> may use these details to prepare my quote and contact me about it (<a href="<?= APP_URL ?>/privacy.php" target="_blank">privacy policy</a>).</span></label>
    <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fa-solid fa-paper-plane"></i>Send quote request</button>
    <p class="hint" style="text-align:center;margin-top:.8rem">Prefer to talk? Call <a href="tel:<?= e(preg_replace('/[^\d+]/', '', biz('biz_phone'))) ?>"><?= e(biz('biz_phone')) ?></a> or <a href="<?= e(waLink('Hi, I would like a quote for pallets.')) ?>" target="_blank" rel="noopener">WhatsApp us</a>.</p>
  </form>
</div>
<?php siteClose(false);

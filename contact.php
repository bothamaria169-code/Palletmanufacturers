<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$user = isLoggedIn() ? getCurrentUser() : null;
$d = ['full_name' => $user['full_name'] ?? '', 'email' => $user['email'] ?? '', 'phone' => $user['phone'] ?? '', 'address' => $user['address'] ?? '', 'message' => ''];
$errors = [];
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($d) as $k) $d[$k] = postText($k, $k === 'message' ? 4000 : 255);
    if (mb_strlen($d['full_name']) < 2) $errors[] = 'Please enter your name.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (mb_strlen($d['message']) < 5) $errors[] = 'Please write a message.';
    if (!empty($_POST['website'])) $errors[] = 'Something went wrong.';
    $s = getDB()->prepare('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $s->execute([clientIp()]);
    if ((int) $s->fetchColumn() >= 5) $errors[] = 'Too many messages from your connection. Please call or WhatsApp us.';
    if (!$errors) {
        getDB()->prepare('INSERT INTO enquiries (full_name, email, phone, address, message, ip) VALUES (?,?,?,?,?,?)')
            ->execute([$d['full_name'], strtolower($d['email']), $d['phone'] ?: null, $d['address'] ?: null, $d['message'], clientIp()]);
        $id = (int) getDB()->lastInsertId();
        sendMail(get_setting('notify_email', ADMIN_EMAIL), 'Website enquiry from ' . $d['full_name'], emailLayout('New website enquiry',
            emailRows(['Name' => $d['full_name'], 'Email' => $d['email'], 'Phone / WhatsApp' => $d['phone'] ?: '—', 'Delivery address' => $d['address'] ?: '—'])
            . '<p style="margin-top:16px;white-space:pre-line">' . e($d['message']) . '</p>', 'Open enquiries', APP_URL . '/admin/enquiries.php?id=' . $id), [], $d['email'], 'enquiry_admin');
        require_once __DIR__ . '/includes/ai.php';
        sendEnquiryReply($d);
        $sent = true;
    }
}

siteOpen('Contact Us – Pallet Supplier Durban, KZN | ' . APP_NAME, [
    'description' => 'Call ' . biz('biz_phone') . ', WhatsApp or email ' . biz('biz_email') . '. Visit us at 68 Columbine Place, Glen Anil, Durban.',
    'canonical' => APP_URL . '/contact.php',
], 'contact');
?>
<section class="page-head">
  <div class="wrap">
    <nav class="crumbs"><a href="<?= APP_URL ?>/">Home</a> / <span>Contact</span></nav>
    <h1>Contact us</h1>
    <p>We're always open to questions, cooperation and suggestions. Reach us any way below — or send a message and we'll get back to you.</p>
  </div>
</section>
<div class="wrap contact-grid">
  <div class="contact-cards">
    <a class="ccard" href="tel:<?= e(preg_replace('/[^\d+]/', '', biz('biz_phone'))) ?>"><i class="fa-solid fa-phone"></i><div><small>Phone</small><span><?= e(biz('biz_phone')) ?></span></div></a>
    <a class="ccard" href="<?= e(waLink('Hi, I have a question about pallets.')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i><div><small>WhatsApp</small><span>Chat with sales</span></div></a>
    <a class="ccard" href="mailto:<?= e(biz('biz_email')) ?>"><i class="fa-solid fa-envelope"></i><div><small>Email</small><span><?= e(biz('biz_email')) ?></span></div></a>
    <a class="ccard" href="<?= e(biz('biz_maps')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot"></i><div><small>Yard</small><span><?= e(biz('biz_address')) ?></span></div></a>
    <div class="ccard"><i class="fa-regular fa-clock"></i><div><small>Working hours</small><span><?= e(biz('biz_hours')) ?></span></div></div>
    <div class="map"><iframe title="Map to our yard" loading="lazy" src="https://www.google.com/maps?q=<?= rawurlencode(biz('biz_address')) ?>&output=embed"></iframe></div>
  </div>
  <div class="form-card">
    <?php if ($sent): ?>
      <div class="empty-state"><i class="fa-solid fa-envelope-circle-check" style="color:var(--mint)"></i><h2>Thank you — message received</h2><p>We've emailed you a confirmation and will get back to you shortly.</p><a class="btn btn-primary" href="<?= APP_URL ?>/shop.php">Browse pallets</a></div>
    <?php else: ?>
      <h2>Send us a message</h2>
      <p>Need a price? <a href="<?= APP_URL ?>/quote.php">Request a quote</a> instead — it's faster and saved to your account.</p>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div><?php endforeach; ?>
      <form method="post" data-busy novalidate>
        <?= csrfField() ?>
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-grid2">
          <div class="field"><label for="full_name">Your name</label><input id="full_name" name="full_name" required autocomplete="name" value="<?= e($d['full_name']) ?>"></div>
          <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e($d['email']) ?>"></div>
          <div class="field"><label for="phone">WhatsApp number</label><input id="phone" type="tel" name="phone" autocomplete="tel" value="<?= e($d['phone']) ?>"></div>
          <div class="field"><label for="address">Delivery address</label><input id="address" name="address" autocomplete="street-address" value="<?= e($d['address']) ?>"></div>
          <div class="field full"><label for="message">Your message</label><textarea id="message" name="message" required><?= e($d['message']) ?></textarea></div>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fa-solid fa-paper-plane"></i>Send message</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php siteClose(false);

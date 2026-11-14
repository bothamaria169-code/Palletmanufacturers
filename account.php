<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
requireLogin();
$user = getCurrentUser();
$uid = (int) $user['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $prov = postText('province', 40);
        $db->prepare('UPDATE users SET full_name = ?, company_name = ?, phone = ?, vat_number = ?, address = ?, city = ?, province = ? WHERE id = ?')
           ->execute([postText('full_name', 120) ?: null, postText('company_name', 160) ?: null, postText('phone', 40) ?: null, postText('vat_number', 40) ?: null,
                      postText('address') ?: null, postText('city', 80) ?: null, in_array($prov, PROVINCES, true) ? $prov : null, $uid]);
        flash('success', 'Details saved. They\'ll fill in automatically on your next quote.');
    } elseif ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        if ($user['password_hash'] && !password_verify($cur, $user['password_hash'])) {
            flash('error', 'Your current password is wrong.');
        } elseif ($p = passwordProblem((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''))) {
            flash('error', $p);
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $uid]);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'export') {
        $q = $db->prepare('SELECT * FROM quotes WHERE user_id = ?');
        $q->execute([$uid]);
        $quotes = $q->fetchAll();
        foreach ($quotes as &$row) {
            $i = $db->prepare('SELECT name, qty, unit_price, notes FROM quote_items WHERE quote_id = ?');
            $i->execute([$row['id']]);
            $row['items'] = $i->fetchAll();
        }
        $me = $user;
        unset($me['password_hash']);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="my-data-' . date('Y-m-d') . '.json"');
        echo json_encode(['account' => $me, 'quotes' => $quotes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($action === 'delete') {
        if ($user['role'] === 'admin') {
            flash('error', 'Admin accounts can\'t be deleted here.');
        } elseif (!password_verify((string) ($_POST['current'] ?? ''), (string) $user['password_hash'])) {
            flash('error', 'Enter your password to delete your account.');
        } else {
            // Quotes stay with the business (tax records) but lose the link to the account.
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            logoutUser();
            header('Location: ' . APP_URL . '/?deleted=1');
            exit;
        }
    }
    redirect('account.php');
}

appOpen('account', 'Account & delivery', 'Your details fill in automatically when you request a quote.');
?>
<div class="grid-2 even">
  <div class="card">
    <div class="card__head"><h2>Company &amp; delivery details</h2></div>
    <form class="card__body" method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="profile">
      <div class="form-grid">
        <div class="field"><label for="full_name">Name</label><input id="full_name" name="full_name" value="<?= e($user['full_name']) ?>"></div>
        <div class="field"><label for="company_name">Company</label><input id="company_name" name="company_name" value="<?= e($user['company_name']) ?>"></div>
        <div class="field"><label>Email</label><input value="<?= e($user['email']) ?>" readonly></div>
        <div class="field"><label for="phone">Phone / WhatsApp</label><input id="phone" name="phone" value="<?= e($user['phone']) ?>"></div>
        <div class="field full"><label for="address">Delivery address</label><input id="address" name="address" value="<?= e($user['address']) ?>"></div>
        <div class="field"><label for="city">Town / city</label><input id="city" name="city" value="<?= e($user['city']) ?>"></div>
        <div class="field"><label for="province">Province</label><select id="province" name="province"><option value="">—</option><?php foreach (PROVINCES as $p): ?><option <?= $user['province'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="vat_number">VAT number <span class="hint">optional, for invoices</span></label><input id="vat_number" name="vat_number" value="<?= e($user['vat_number']) ?>"></div>
      </div>
      <div class="form-foot"><button class="btn btn-primary">Save details</button></div>
    </form>
  </div>
  <div>
    <div class="card">
      <div class="card__head"><h2>Password</h2></div>
      <form class="card__body" method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="password">
        <?php if ($user['password_hash']): ?><div class="field"><label for="current">Current password</label><input id="current" type="password" name="current" autocomplete="current-password" required></div><?php endif; ?>
        <div class="form-grid">
          <div class="field"><label for="pw">New password</label><input id="pw" type="password" name="password" autocomplete="new-password" required></div>
          <div class="field"><label for="pw2">Confirm</label><input id="pw2" type="password" name="confirm" autocomplete="new-password" required></div>
        </div>
        <div class="form-foot"><button class="btn btn-dark">Change password</button></div>
      </form>
    </div>
    <div class="card">
      <div class="card__head"><h2>Your data (POPIA)</h2></div>
      <div class="card__body">
        <p class="muted" style="font-size:.88rem;margin-bottom:1rem">Download everything we hold about you, or delete your account. Quotes we've already issued stay on record for tax purposes but are no longer linked to you.</p>
        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="export"><button class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i>Download my data</button></form>
        <?php if ($user['role'] !== 'admin'): ?>
        <details style="margin-top:1rem"><summary class="muted" style="cursor:pointer;font-size:.88rem">Delete my account</summary>
          <form method="post" data-confirm="Delete your account permanently?" style="margin-top:.8rem;display:flex;gap:.5rem;flex-wrap:wrap"><?= csrfField() ?><input type="hidden" name="action" value="delete">
            <input class="input" type="password" name="current" placeholder="Your password" required style="flex:1;min-width:180px"><button class="btn btn-sm" style="background:var(--coral);color:#fff">Delete account</button></form>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php appClose();

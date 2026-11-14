<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_POST['id'] ?? 0);
    $u = $db->query('SELECT * FROM users WHERE id = ' . $uid)->fetch();
    $action = $_POST['action'] ?? '';
    if (!$u) redirect('admin/users.php');
    if ($uid === (int) $ADMIN['id'] && in_array($action, ['toggle', 'role'], true)) {
        flash('error', 'You can\'t disable or demote your own account.');
    } elseif ($action === 'toggle') {
        $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
        audit($u['is_active'] ? 'user_disable' : 'user_enable', $u['email']);
        flash('success', $u['email'] . ($u['is_active'] ? ' disabled.' : ' enabled.'));
    } elseif ($action === 'role') {
        $new = $u['role'] === 'admin' ? 'user' : 'admin';
        $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$new, $uid]);
        audit('user_role', $u['email'], $new);
        flash('success', $u['email'] . ' is now ' . ($new === 'admin' ? 'an admin' : 'a customer') . '.');
    } elseif ($action === 'reset') {
        $err = sendResetLink($u);
        audit('user_reset_link', $u['email']);
        flash($err === '' ? 'success' : 'error', $err === '' ? 'Password link emailed to ' . $u['email'] . '.' : 'Email failed: ' . $err);
    } elseif ($action === 'add') {
        $email = strtolower(postText('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a valid email.'); redirect('admin/users.php'); }
        try {
            $db->prepare('INSERT INTO users (email, full_name, company_name, phone, role) VALUES (?,?,?,?,?)')
               ->execute([$email, postText('full_name', 120) ?: null, postText('company_name', 160) ?: null, postText('phone', 40) ?: null, ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user']);
            $new = findUserByEmail($email);
            claimGuestQuotes((int) $new['id'], $email);
            sendResetLink($new);
            audit('user_add', $email);
            flash('success', 'Account created for ' . $email . ' — they\'ve been emailed a link to set a password.');
        } catch (PDOException) {
            flash('error', 'That email already has an account.');
        }
    }
    redirect('admin/users.php?' . http_build_query(['q' => $_POST['q'] ?? '']));
}

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$role = (string) ($_GET['role'] ?? '');
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(u.email LIKE ? OR u.full_name LIKE ? OR u.company_name LIKE ? OR u.phone LIKE ?)'; array_push($args, ...array_fill(0, 4, "%{$q}%")); }
if (in_array($role, ['user', 'admin'], true)) { $where[] = 'u.role = ?'; $args[] = $role; }
$s = $db->prepare('SELECT u.*, (SELECT COUNT(*) FROM quotes q WHERE q.user_id = u.id) n_quotes,
                          (SELECT COALESCE(SUM(total),0) FROM quotes q WHERE q.user_id = u.id AND q.status IN (\'accepted\',\'completed\')) won
                   FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 300');
$s->execute($args);
$rows = $s->fetchAll();

adminOpen('users', 'Customers', 'Everyone with an account. Guests who only sent a quote appear under Quotes.');
?>
<div class="card">
  <form class="toolbar" method="get">
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search email, name, company or phone" aria-label="Search customers">
    <select name="role" onchange="this.form.submit()" aria-label="Role"><option value="">Everyone</option><option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Customers</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option></select>
    <button class="btn btn-ghost btn-sm">Search</button><span class="count"><?= count($rows) ?> accounts</span>
  </form>
  <table class="tbl"><thead><tr><th>Customer</th><th>Phone</th><th class="num">Quotes</th><th class="num">Won</th><th>Joined</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $u): ?>
    <tr style="<?= $u['is_active'] ? '' : 'opacity:.55' ?>">
      <td class="cell-main"><div class="who"><div class="avatar"><?= e(strtoupper(mb_substr($u['full_name'] ?: $u['email'], 0, 1))) ?></div><div><strong><?= e($u['full_name'] ?: '—') ?> <?= $u['role'] === 'admin' ? '<span class="tag tag-admin">Admin</span>' : '' ?><?= $u['is_active'] ? '' : ' <span class="tag tag-off">Disabled</span>' ?></strong><small><?= e($u['email']) ?><?= $u['company_name'] ? ' · ' . e($u['company_name']) : '' ?></small></div></div></td>
      <td data-label="Phone"><?= e($u['phone'] ?: '—') ?></td>
      <td class="num" data-label="Quotes"><a href="quotes.php?q=<?= rawurlencode($u['email']) ?>"><?= (int) $u['n_quotes'] ?></a></td>
      <td class="num" data-label="Won"><?= rand_($u['won']) ?></td>
      <td data-label="Joined"><span class="muted"><?= date('j M Y', strtotime($u['created_at'])) ?></span></td>
      <td><div class="row-actions">
        <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="action" value="reset"><button class="btn-xs" title="Email a set-password link"><i class="fa-solid fa-key"></i></button></form>
        <form method="post" data-confirm="<?= $u['role'] === 'admin' ? 'Make this person a normal customer?' : 'Give this person full admin access?' ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="action" value="role"><button class="btn-xs" title="Toggle admin"><i class="fa-solid fa-shield-halved"></i></button></form>
        <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="action" value="toggle"><button class="btn-xs danger"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<div class="card" style="max-width:760px">
  <div class="card__head"><h2>Add an account</h2><p>They get an email to set their own password. Their earlier guest quotes move into the account.</p></div>
  <form class="card__body" method="post"><?= csrfField() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="0">
    <div class="form-grid">
      <div class="field"><label for="a_email">Email</label><input id="a_email" type="email" name="email" required></div>
      <div class="field"><label for="a_name">Name</label><input id="a_name" name="full_name"></div>
      <div class="field"><label for="a_co">Company</label><input id="a_co" name="company_name"></div>
      <div class="field"><label for="a_ph">Phone</label><input id="a_ph" name="phone"></div>
      <div class="field"><label for="a_role">Role</label><select id="a_role" name="role"><option value="user">Customer</option><option value="admin">Admin (staff)</option></select></div>
    </div>
    <div class="form-foot"><button class="btn btn-primary">Create &amp; email link</button></div>
  </form>
</div>
<?php adminClose();

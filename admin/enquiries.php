<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $db->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$eid]);
        flash('success', 'Enquiry deleted.');
        redirect('admin/enquiries.php');
    }
    if ($action === 'to_quote') {
        $m = $db->query('SELECT * FROM enquiries WHERE id = ' . $eid)->fetch();
        if ($m) {
            $u = findUserByEmail($m['email']);
            $qid = createQuote(['full_name' => $m['full_name'], 'email' => $m['email'], 'phone' => $m['phone'], 'company_name' => '', 'delivery_address' => $m['address'],
                                'city' => '', 'province' => '', 'needed_by' => '', 'message' => $m['message']], [], $u ? (int) $u['id'] : null, 'enquiry', false);
            $db->prepare('UPDATE enquiries SET is_read = 1 WHERE id = ?')->execute([$eid]);
            flash('success', 'Quote created from the enquiry. Add the pallets and prices.');
            redirect('admin/quote.php?id=' . $qid);
        }
    }
    if ($action === 'unread') $db->prepare('UPDATE enquiries SET is_read = 0 WHERE id = ?')->execute([$eid]);
    redirect('admin/enquiries.php');
}

$open = isset($_GET['id']) ? $db->query('SELECT * FROM enquiries WHERE id = ' . (int) $_GET['id'])->fetch() : null;
if ($open && !$open['is_read']) $db->prepare('UPDATE enquiries SET is_read = 1 WHERE id = ?')->execute([$open['id']]);
$rows = $db->query('SELECT * FROM enquiries ORDER BY created_at DESC LIMIT 300')->fetchAll();

adminOpen('enquiries', 'Enquiries', 'Messages from the Contact page. Customers get an automatic thank-you email.');
?>
<div class="grid-2">
  <div class="card">
    <?php if ($rows): foreach ($rows as $m): ?>
      <a class="list-row" href="?id=<?= (int) $m['id'] ?>" style="color:inherit;text-decoration:none;<?= $open && $open['id'] == $m['id'] ? 'background:var(--brand-50)' : '' ?>">
        <div class="avatar"><?= e(strtoupper(mb_substr($m['full_name'], 0, 1))) ?></div>
        <div class="list-row__main"><strong><?= $m['is_read'] || ($open && $open['id'] == $m['id']) ? '' : '<span class="tag tag-on">New</span> ' ?><?= e($m['full_name']) ?></strong><small><?= e(excerpt($m['message'], 90)) ?></small></div>
        <div class="list-row__side"><?= e(timeAgo($m['created_at'])) ?></div>
      </a>
    <?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-inbox"></i>No enquiries yet.</div><?php endif; ?>
  </div>
  <div class="card">
    <?php if ($open): ?>
      <div class="card__head"><h2><?= e($open['full_name']) ?></h2><span class="muted" style="font-size:.82rem"><?= date('j M Y, H:i', strtotime($open['created_at'])) ?></span></div>
      <div class="card__body" style="display:grid;gap:.5rem;font-size:.9rem">
        <div><i class="fa-solid fa-envelope muted"></i> <a href="mailto:<?= e($open['email']) ?>?subject=<?= rawurlencode('Re: your enquiry to ' . APP_NAME) ?>"><?= e($open['email']) ?></a></div>
        <?php if ($open['phone']): ?><div><i class="fa-brands fa-whatsapp muted"></i> <a href="https://wa.me/<?= e(preg_replace('/^0/', '27', preg_replace('/\D/', '', $open['phone']))) ?>" target="_blank" rel="noopener"><?= e($open['phone']) ?></a></div><?php endif; ?>
        <?php if ($open['address']): ?><div><i class="fa-solid fa-location-dot muted"></i> <?= e($open['address']) ?></div><?php endif; ?>
        <div style="background:var(--surface-2);border-radius:10px;padding:.9rem;white-space:pre-line;margin-top:.4rem"><?= e($open['message']) ?></div>
        <div class="form-foot">
          <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><input type="hidden" name="action" value="to_quote"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-file-invoice"></i>Turn into a quote</button></form>
          <form method="post"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><input type="hidden" name="action" value="unread"><button class="btn btn-ghost btn-sm">Mark unread</button></form>
          <form method="post" data-confirm="Delete this enquiry?"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $open['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-xs danger"><i class="fa-solid fa-trash-can"></i>Delete</button></form>
        </div>
      </div>
    <?php else: ?><div class="empty"><i class="fa-regular fa-envelope-open"></i>Pick an enquiry to read it.</div><?php endif; ?>
  </div>
</div>
<?php adminClose();

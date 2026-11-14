<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';
require_once BASE_DIR . '/includes/mailreader.php';

$folderKey = ($_GET['f'] ?? '') === 'sent' ? 'sent' : 'inbox';
$uid  = (int) ($_GET['uid'] ?? 0);
$q    = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$page = max(1, (int) ($_GET['page'] ?? 1));
$error = '';
$list = [];
$msg = null;
$folder = 'INBOX';

if (!MailReader::configured()) {
    adminOpen('inbox', 'Inbox', 'Read and answer the ' . e(SALES_EMAIL) . ' mailbox here.');
    echo '<div class="card" style="max-width:720px"><div class="card__body"><div class="empty-state" style="padding:1.5rem 0"><i class="fa-solid fa-inbox"></i><h2>Connect the sales mailbox</h2>'
       . '<p>🟧 cPanel → <strong>Email Accounts</strong> → create <strong>' . e(SALES_EMAIL) . '</strong>. Then 🟩 <strong>Settings → Email</strong> → fill in the <em>Sales mailbox</em> password → <strong>Save</strong> → <strong>Test inbox</strong>.</p>'
       . '<a class="btn btn-primary" href="settings.php?tab=email">Open email settings</a></div></div></div>';
    adminClose();
    exit;
}

try {
    $mr = MailReader::open();
    if ($folderKey === 'sent') $folder = $mr->sentFolder();

    // Reply / new email
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $to = strtolower(postText('to', 190));
        $subject = postText('subject', 250);
        $body = trim((string) ($_POST['body'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
            flash('error', 'Fill in To, Subject and the message.');
        } else {
            $html = textToHtml(e($body)) . signatureHtml();
            $quoted = trim((string) ($_POST['quoted'] ?? ''));
            if ($quoted !== '') $html .= '<blockquote style="margin:18px 0 0;padding-left:12px;border-left:3px solid #e7e5e4;color:#78716c">' . nl2br(e(mb_substr($quoted, 0, 6000)), false) . '</blockquote>';
            $files = [];
            if (($qid = (int) ($_POST['attach_quote'] ?? 0)) && ($qq = quoteById($qid))) $files[] = [quotePdf($qq), 'Quote-' . $qq['ref'] . '.pdf'];
            if (($iid = (int) ($_POST['attach_invoice'] ?? 0)) && ($ii = invoiceById($iid))) $files[] = [invoicePdf($ii), $ii['invoice_no'] . '.pdf'];
            $err = sendMail($to, $subject, emailLayout('', $html), [], null, 'inbox_reply', ['mailbox' => 'sales', 'in_reply_to' => postText('in_reply_to', 400) ?: null, 'files' => $files]);
            if ($err !== '') {
                flash('error', 'Not sent: ' . $err);
            } else {
                $saved = !empty($GLOBALS['PM_LAST_MIME']) && $mr->append($mr->sentFolder(), $GLOBALS['PM_LAST_MIME']);
                audit('inbox_send', $to, $subject);
                flash('success', 'Sent to ' . $to . ($saved ? ' — a copy is in Sent.' : '.'));
            }
        }
        $mr->close();
        redirect('admin/inbox.php' . ($uid ? '?uid=' . $uid : ''));
    }

    $total = $mr->select($folder);
    if ($uid) $msg = $mr->message($uid);
    $list = $mr->latest(40, $page, $q);
    $mr->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// Link the sender to what we know about them.
$related = ['quotes' => [], 'invoices' => [], 'user' => null];
if ($msg) {
    $em = $msg['reply_to'];
    $s = getDB()->prepare('SELECT id, ref, status, total FROM quotes WHERE email = ? ORDER BY created_at DESC LIMIT 6');
    $s->execute([$em]);
    $related['quotes'] = $s->fetchAll();
    $s = getDB()->prepare("SELECT id, invoice_no, status, total FROM invoices WHERE email = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 6");
    $s->execute([$em]);
    $related['invoices'] = $s->fetchAll();
    $related['user'] = findUserByEmail($em);
}
$compose = isset($_GET['compose']);
$base = '?' . ($folderKey === 'sent' ? 'f=sent&' : '');

adminOpen('inbox', 'Inbox', e(SALES_EMAIL) . ($error === '' ? ' · ' . ($folderKey === 'sent' ? 'Sent' : 'Inbox') : ''),
    '<a class="btn btn-primary" href="?compose=1"><i class="fa-solid fa-pen"></i>New email</a>');
?>
<?php if ($error): ?>
  <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?> — check <a href="settings.php?tab=email">Settings → Email</a>.</span></div>
<?php endif; ?>
<div class="tabs"><a href="?" class="<?= $folderKey === 'inbox' ? 'is-on' : '' ?>"><i class="fa-solid fa-inbox"></i> Inbox</a><a href="?f=sent" class="<?= $folderKey === 'sent' ? 'is-on' : '' ?>"><i class="fa-solid fa-paper-plane"></i> Sent</a></div>
<div class="grid-2 inbox-grid">
  <div class="card">
    <form class="toolbar" method="get"><?php if ($folderKey === 'sent'): ?><input type="hidden" name="f" value="sent"><?php endif; ?>
      <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search sender, subject…" aria-label="Search email"><button class="btn btn-ghost btn-sm">Search</button></form>
    <?php if ($list): foreach ($list as $m): ?>
      <a class="list-row mail-row <?= $m['seen'] ? '' : 'unread' ?> <?= $uid === $m['uid'] ? 'is-open' : '' ?>" href="<?= $base ?>uid=<?= $m['uid'] ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>">
        <div class="avatar"><?= e(strtoupper(mb_substr($folderKey === 'sent' ? $m['to'] : ($m['from'] ?: '?'), 0, 1))) ?></div>
        <div class="list-row__main"><strong><?= e($folderKey === 'sent' ? 'To: ' . $m['to'] : $m['from']) ?></strong><small><?= e($m['subject']) ?></small></div>
        <div class="list-row__side"><?= e($m['date'] ? timeAgo($m['date']) : '') ?></div>
      </a>
    <?php endforeach; ?>
      <div class="pager"><?php if ($page > 1): ?><a href="<?= $base ?>page=<?= $page - 1 ?>">‹ Newer</a><?php endif; ?><?php if (count($list) === 40): ?><a href="<?= $base ?>page=<?= $page + 1 ?>">Older ›</a><?php endif; ?></div>
    <?php elseif (!$error): ?><div class="empty"><i class="fa-regular fa-envelope-open"></i>No emails here.</div><?php endif; ?>
  </div>

  <div>
    <?php if ($msg): ?>
      <div class="card">
        <div class="card__head" style="align-items:flex-start"><div><h2><?= e($msg['subject']) ?></h2><p><strong><?= e($msg['from']) ?></strong> &lt;<?= e($msg['from_email']) ?>&gt; · <?= e($msg['date'] ? date('j M Y H:i', strtotime($msg['date'])) : '') ?><br>To: <?= e($msg['to']) ?><?= $msg['cc'] ? ' · Cc: ' . e($msg['cc']) : '' ?></p></div></div>
        <div class="card__body">
          <?php if ($msg['html'] !== ''): ?>
            <iframe class="mail-frame" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= e('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: https:; style-src \'unsafe-inline\'"><base target="_blank"><style>body{font-family:system-ui,sans-serif;font-size:14px;color:#1c1917;margin:0;word-break:break-word}img{max-width:100%;height:auto}</style>' . $msg['html']) ?>" title="Email content"></iframe>
          <?php else: ?>
            <div class="mail-text"><?= nl2br(e($msg['text'])) ?></div>
          <?php endif; ?>
          <?php if ($msg['attachments']): ?><p class="hint" style="margin-top:.8rem"><i class="fa-solid fa-paperclip"></i> <?= e(implode(', ', $msg['attachments'])) ?> <span class="muted">(open in webmail to download)</span></p><?php endif; ?>
        </div>
      </div>
      <?php if ($related['quotes'] || $related['invoices'] || $related['user']): ?>
        <div class="card"><div class="card__body" style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;font-size:.86rem">
          <strong style="margin-right:.3rem">This customer:</strong>
          <?php if ($related['user']): ?><a class="btn-xs" href="users.php?q=<?= rawurlencode($related['user']['email']) ?>"><i class="fa-solid fa-user-check"></i>Account</a><?php endif; ?>
          <?php foreach ($related['quotes'] as $rq): ?><a class="btn-xs" href="quote.php?id=<?= (int) $rq['id'] ?>"><?= e($rq['ref']) ?> · <?= e(QUOTE_STATUSES[$rq['status']]) ?></a><?php endforeach; ?>
          <?php foreach ($related['invoices'] as $ri): ?><a class="btn-xs" href="invoice.php?id=<?= (int) $ri['id'] ?>"><?= e($ri['invoice_no']) ?> · <?= e(INVOICE_STATUSES[$ri['status']]) ?></a><?php endforeach; ?>
        </div></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($msg || $compose): ?>
      <div class="card" id="reply">
        <div class="card__head"><h2><?= $msg ? 'Reply' : 'New email' ?></h2><span class="muted" style="font-size:.8rem">from <?= e(SALES_EMAIL) ?></span></div>
        <form class="card__body" method="post" data-busy><?= csrfField() ?>
          <input type="hidden" name="in_reply_to" value="<?= e($msg['message_id'] ?? '') ?>">
          <input type="hidden" name="quoted" value="<?= e($msg ? 'On ' . ($msg['date'] ? date('j M Y H:i', strtotime($msg['date'])) : '') . ', ' . $msg['from'] . " wrote:\n" . mb_substr($msg['text'], 0, 4000) : '') ?>">
          <div class="form-grid">
            <div class="field"><label for="to">To</label><input id="to" type="email" name="to" required value="<?= e($msg['reply_to'] ?? ($_GET['to'] ?? '')) ?>"></div>
            <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" required value="<?= e($msg ? (preg_match('/^re:/i', $msg['subject']) ? $msg['subject'] : 'Re: ' . $msg['subject']) : ($_GET['subject'] ?? '')) ?>"></div>
          </div>
          <div class="field"><label for="body">Message <span class="hint">your signature is added automatically</span></label><textarea id="body" name="body" required style="min-height:180px"><?= e($msg ? 'Hi ' . (explode(' ', $msg['from'])[0] ?: 'there') . ",\n\n" : '') ?></textarea></div>
          <?php if ($related['quotes'] || $related['invoices']): ?>
            <div class="form-grid">
              <?php if ($related['quotes']): ?><div class="field"><label for="aq">Attach quote PDF</label><select id="aq" name="attach_quote"><option value="">—</option><?php foreach ($related['quotes'] as $rq): ?><option value="<?= (int) $rq['id'] ?>"><?= e($rq['ref']) ?> (<?= rand_($rq['total']) ?>)</option><?php endforeach; ?></select></div><?php endif; ?>
              <?php if ($related['invoices']): ?><div class="field"><label for="ai">Attach invoice PDF</label><select id="ai" name="attach_invoice"><option value="">—</option><?php foreach ($related['invoices'] as $ri): ?><option value="<?= (int) $ri['id'] ?>"><?= e($ri['invoice_no']) ?> (<?= rand_($ri['total']) ?>)</option><?php endforeach; ?></select></div><?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="form-foot"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i>Send</button></div>
        </form>
      </div>
    <?php elseif (!$error): ?>
      <div class="card"><div class="empty"><i class="fa-regular fa-envelope"></i>Pick an email to read and reply.</div></div>
    <?php endif; ?>
  </div>
</div>
<?php adminClose();

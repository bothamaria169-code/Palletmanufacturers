<?php
declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

$key = isset(EMAIL_TEMPLATES[$_GET['t'] ?? '']) ? $_GET['t'] : array_key_first(EMAIL_TEMPLATES);
$def = EMAIL_TEMPLATES[$key];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'reset') {
        getDB()->prepare('DELETE FROM email_templates WHERE tkey = ?')->execute([$key]);
        flash('success', 'Back to the original wording.');
    } else {
        $subject = postText('subject', 255);
        $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
        if ($body === '') { flash('error', 'The message can\'t be empty.'); redirect('admin/templates.php?t=' . $key); }
        getDB()->prepare('REPLACE INTO email_templates (tkey, subject, body) VALUES (?, ?, ?)')->execute([$key, $subject ?: $def['subject'], $body]);
        audit('template_save', $key);
        flash('success', 'Template saved.');
    }
    redirect('admin/templates.php?t=' . $key);
}

[$subject, $body] = templateText($key);
$sample = ['name' => 'Thandi', 'ref' => 'PM-26-0042', 'total' => 'R6 450.00', 'valid_until' => date('j F Y', strtotime('+14 days')), 'note' => 'Heat treatment included.',
           'invoice_no' => 'INV-00042', 'due_date' => date('j F Y', strtotime('+7 days')), 'bank' => "Banking details:\n" . (implode("\n", bankLines()) ?: 'Bank: (set in Settings → Invoices)'),
           'message' => 'Your pallets are loaded and will arrive tomorrow morning.'];
$items = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr><td style="padding:8px 0;border-bottom:1px solid #eef1f6">Standard 4 Way Entry Pallet | 1000 x 1200</td><td style="text-align:center">40</td><td style="text-align:right">R150.00</td><td style="text-align:right">R6 000.00</td></tr><tr><td colspan="3" style="padding:10px 0;text-align:right;font-weight:800">Total</td><td style="text-align:right;font-weight:800">R6 450.00</td></tr></table>';
if ($key === 'signature') { $pSub = ''; $pHtml = signatureHtml(); }
else { [$pSub, $pHtml] = renderTemplate($key, $sample, ['items' => $items]); }
$preview = emailLayout('', $pHtml, $def['button'] ?: null, $def['button'] ? '#' : null);
$custom = (bool) getDB()->query('SELECT 1 FROM email_templates WHERE tkey = ' . getDB()->quote($key))->fetchColumn();

adminOpen('templates', 'Email templates', 'The wording of every email customers get. Letterhead, button and footer are added automatically.');
?>
<div class="tabs"><?php foreach (EMAIL_TEMPLATES as $k => $t): ?><a href="?t=<?= $k ?>" class="<?= $k === $key ? 'is-on' : '' ?>"><?= e(preg_replace('/ \(.*\)$/', '', $t['label'])) ?></a><?php endforeach; ?></div>
<div class="grid-2 even">
  <div class="card">
    <div class="card__head"><div><h2><?= e($def['label']) ?></h2><p><?= e($def['when']) ?></p></div><?= $custom ? '<span class="tag tag-google">Edited</span>' : '' ?></div>
    <form class="card__body" method="post"><?= csrfField() ?>
      <?php if ($key !== 'signature'): ?><div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="<?= e($subject) ?>"></div><?php endif; ?>
      <div class="field"><label for="body">Message <span class="hint">blank line = new paragraph</span></label><textarea id="body" name="body" style="min-height:280px"><?= e($body) ?></textarea></div>
      <p class="hint" style="margin-bottom:.8rem">You can use: <?php foreach ($def['vars'] as $v): ?><button type="button" class="btn-xs" data-ins="{<?= e($v) ?>}">{<?= e($v) ?>}</button> <?php endforeach; ?></p>
      <div class="form-foot"><button class="btn btn-primary" name="action" value="save">Save template</button><?php if ($custom): ?><button class="btn btn-ghost" name="action" value="reset" formnovalidate onclick="return confirm('Go back to the original wording?')">Reset to original</button><?php endif; ?></div>
    </form>
  </div>
  <div class="card">
    <div class="card__head"><h2>Preview</h2><?php if ($pSub): ?><p>Subject: <strong><?= e($pSub) ?></strong></p><?php endif; ?></div>
    <iframe class="mail-frame" sandbox="" srcdoc="<?= e($preview) ?>" title="Email preview" style="height:620px"></iframe>
  </div>
</div>
<?php adminClose('<script>
document.querySelectorAll("[data-ins]").forEach(function (b) {
  b.onclick = function () { var t = document.getElementById("body"), s = t.selectionStart; t.value = t.value.slice(0, s) + b.dataset.ins + t.value.slice(t.selectionEnd); t.focus(); t.selectionStart = t.selectionEnd = s + b.dataset.ins.length; };
});
</script>');

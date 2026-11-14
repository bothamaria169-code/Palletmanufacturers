<?php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once BASE_DIR . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends one email. SMTP comes from Admin → Settings → Email, falling back to
 * secrets.php; with no SMTP password it uses the server's own mail().
 * Every send is logged in sent_emails (Admin → Emails).
 *
 * @param string|string[] $to
 * @return string '' on success, otherwise the reason it failed
 */
function sendMail(string|array $to, string $subject, string $html, array $attachments = [], ?string $replyTo = null, string $kind = ''): string
{
    $host = get_setting('smtp_host', SMTP_HOST);
    $port = (int) get_setting('smtp_port', (string) SMTP_PORT);
    $user = get_setting('smtp_user', SMTP_USER);
    $pass = get_setting('smtp_pass', SMTP_PASS);
    $from = $user !== '' ? $user : biz('biz_email');
    $error = '';

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        if ($host !== '' && $pass !== '') {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = $port;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout    = 20;
        } else {
            $mail->isMail();
        }
        $mail->setFrom($from, get_setting('mail_from_name', APP_NAME));
        foreach ((array) $to as $addr) $mail->addAddress($addr);
        if ($replyTo) $mail->addReplyTo($replyTo);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>'], "\n", $html))));
        foreach ($attachments as $file) if (is_file($file)) $mail->addAttachment($file);
        $mail->send();
    } catch (\Throwable $e) {
        $error = $e->getMessage() ?: 'Unknown mail error';
        error_log('Pallets mail to ' . implode(',', (array) $to) . ' failed: ' . $error);
    }
    try {
        getDB()->prepare('INSERT INTO sent_emails (to_email, subject, kind, status, error) VALUES (?, ?, ?, ?, ?)')
            ->execute([mb_substr(implode(', ', (array) $to), 0, 255), mb_substr($subject, 0, 255), $kind ?: null, $error === '' ? 'sent' : 'failed', $error === '' ? null : mb_substr($error, 0, 500)]);
    } catch (\Throwable) {
    }
    return $error;
}

/** The branded shell every email is wrapped in. */
function emailLayout(string $heading, string $bodyHtml, ?string $buttonText = null, ?string $buttonUrl = null, string $footerHtml = ''): string
{
    $btn = $buttonText && $buttonUrl
        ? '<p style="margin:28px 0 8px"><a href="' . e($buttonUrl) . '" style="background:#b45309;color:#ffffff;text-decoration:none;padding:13px 26px;border-radius:10px;font-weight:700;display:inline-block">' . e($buttonText) . '</a></p>'
        : '';
    $h1    = $heading !== '' ? '<h1 style="font-size:21px;margin:0 0 14px">' . $heading . '</h1>' : '';
    $year  = date('Y');
    $url   = APP_URL;
    $phone = e(biz('biz_phone'));
    $mail  = e(biz('biz_email'));
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f3f5fa;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#0e1726">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5fa;padding:28px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e3e8f2">
<tr><td style="background:#1c1917;padding:20px 28px;font-size:20px;font-weight:800;color:#ffffff">Durban's<span style="color:#f59e0b">Pallets</span></td></tr>
<tr><td style="padding:30px 28px 26px;font-size:15px;line-height:1.6">
{$h1}
{$bodyHtml}
{$btn}
{$footerHtml}
</td></tr>
<tr><td style="padding:16px 28px;background:#f7f8fc;color:#7c8899;font-size:12px">© {$year} Durban's Pallets · {$phone} · {$mail} · <a href="{$url}" style="color:#7c8899">{$url}</a></td></tr>
</table></td></tr></table></body></html>
HTML;
}

function emailRows(array $rows): string
{
    $out = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">';
    foreach ($rows as $k => $v) {
        $out .= '<tr><td style="padding:7px 0;color:#7c8899;width:38%;border-bottom:1px solid #eef1f6">' . e($k) . '</td>'
              . '<td style="padding:7px 0;border-bottom:1px solid #eef1f6"><strong>' . e((string) $v) . '</strong></td></tr>';
    }
    return $out . '</table>';
}

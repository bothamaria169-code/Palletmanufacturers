<?php
declare(strict_types=1);

/**
 * A small IMAP client for the sales@ inbox (Admin → Inbox). Talks IMAP over
 * TLS directly, so it works even where PHP's imap extension is missing.
 * Settings: Admin → Settings → Email (IMAP server + the sales mailbox login).
 */
final class MailReader
{
    /** @var resource|null */
    private $s = null;
    private int $tag = 0;

    public static function configured(): bool
    {
        return get_setting('sales_smtp_pass', (string) secret('sales_smtp_pass', '')) !== '';
    }

    public static function open(): self
    {
        if (!self::configured()) throw new RuntimeException('Add the sales@ mailbox password in Settings → Email first.');
        $c = mailboxConfig('sales');
        $host = get_setting('imap_host', $c['host']);
        $port = (int) get_setting('imap_port', '993');
        $r = new self();
        $err = '';
        // Try the configured server (with and without the certificate name check), then localhost:
        // on shared hosting the web server often can't reach its own public mail address.
        foreach ([[$host, true], [$host, false], ['localhost', false]] as [$try, $verify]) {
            $ctx = stream_context_create(['ssl' => $verify
                ? ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $try]
                : ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            $r->s = @stream_socket_client(($port === 993 ? 'ssl://' : 'tcp://') . $try . ':' . $port, $errno, $err, 10, STREAM_CLIENT_CONNECT, $ctx);
            if ($r->s) break;
        }
        if (!$r->s) throw new RuntimeException("Can't reach the mail server {$host}:{$port} or localhost ({$err}).");
        stream_set_timeout($r->s, 25);
        fgets($r->s);
        [$ok, , $line] = $r->cmd('LOGIN ' . self::q($c['user']) . ' ' . self::q($c['pass']));
        if (!$ok) throw new RuntimeException('The mail server refused the sales@ login: ' . trim($line));
        return $r;
    }

    private static function q(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    /** @return array{0: bool, 1: string, 2: string} ok, untagged data (literals inlined), status line */
    private function cmd(string $command): array
    {
        $tag = 'A' . (++$this->tag);
        fwrite($this->s, "{$tag} {$command}\r\n");
        $data = '';
        while (($line = fgets($this->s)) !== false) {
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $data .= $line;
                $need = (int) $m[1];
                while ($need > 0 && !feof($this->s)) {
                    $chunk = fread($this->s, min(8192, $need));
                    if ($chunk === false || $chunk === '') break;
                    $data .= $chunk;
                    $need -= strlen($chunk);
                }
                continue;
            }
            if (str_starts_with($line, $tag . ' ')) {
                return [(bool) preg_match('/^' . $tag . ' OK/i', $line), $data, $line];
            }
            $data .= $line;
        }
        throw new RuntimeException('The mail server stopped answering.');
    }

    public function select(string $folder): int
    {
        [$ok, $data, $line] = $this->cmd('SELECT ' . self::q($folder));
        if (!$ok) throw new RuntimeException("Folder {$folder} not found: " . trim($line));
        return preg_match('/\* (\d+) EXISTS/i', $data, $m) ? (int) $m[1] : 0;
    }

    /** The Sent folder's name (cPanel/Dovecot uses INBOX.Sent). */
    public function sentFolder(): string
    {
        [, $data] = $this->cmd('LIST "" "*"');
        foreach (['INBOX.Sent', 'Sent', 'INBOX/Sent', 'Sent Items', 'Sent Messages'] as $f) {
            if (stripos($data, '"' . $f . '"') !== false || preg_match('/ ' . preg_quote($f, '/') . '\r?$/mi', $data)) return $f;
        }
        return 'INBOX.Sent';
    }

    /**
     * Newest messages first.
     *
     * @return array<int, array{uid: int, seen: bool, from: string, from_email: string, to: string, subject: string, date: string, size: int}>
     */
    public function latest(int $limit = 40, int $page = 1, string $search = ''): array
    {
        $crit = $search !== '' ? 'OR OR FROM ' . self::q($search) . ' SUBJECT ' . self::q($search) . ' TO ' . self::q($search) : 'ALL';
        [, $data] = $this->cmd('UID SEARCH ' . $crit);
        $uids = preg_match('/\* SEARCH([\d ]*)/i', $data, $m) ? array_map('intval', array_filter(explode(' ', trim($m[1])), 'strlen')) : [];
        rsort($uids);
        $uids = array_slice($uids, ($page - 1) * $limit, $limit);
        if (!$uids) return [];
        [, $data] = $this->cmd('UID FETCH ' . implode(',', $uids) . ' (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])');
        $out = [];
        foreach (preg_split('/^\* \d+ FETCH /m', $data) as $chunk) {
            if (!preg_match('/UID (\d+)/', $chunk, $u)) continue;
            $h = self::headers(preg_replace('/^.*?\{\d+\}\r\n/s', '', $chunk));
            [$name, $email] = self::addr($h['from'] ?? '');
            $out[(int) $u[1]] = [
                'uid' => (int) $u[1], 'seen' => stripos($chunk, '\\Seen') !== false,
                'from' => $name ?: $email, 'from_email' => $email, 'to' => self::decode($h['to'] ?? ''),
                'subject' => self::decode($h['subject'] ?? '') ?: '(no subject)',
                'date' => ($t = strtotime((string) ($h['date'] ?? ''))) ? date('Y-m-d H:i:s', $t) : '',
                'size' => preg_match('/RFC822\.SIZE (\d+)/', $chunk, $sz) ? (int) $sz[1] : 0,
            ];
        }
        return array_values(array_filter(array_map(fn($u) => $out[$u] ?? null, $uids)));
    }

    /** One full message, marked as read. */
    public function message(int $uid): ?array
    {
        [, $data] = $this->cmd('UID FETCH ' . $uid . ' (UID FLAGS BODY.PEEK[])');
        if (!preg_match('/\{(\d+)\}\r\n/', $data, $m, PREG_OFFSET_CAPTURE)) return null;
        $raw = substr($data, $m[0][1] + strlen($m[0][0]), (int) $m[1][0]);
        $this->cmd('UID STORE ' . $uid . ' +FLAGS (\\Seen)');
        $msg = self::parse($raw);
        $h = $msg['headers'];
        [$name, $email] = self::addr($h['from'] ?? '');
        [, $replyTo] = self::addr($h['reply-to'] ?? '');
        return $msg + [
            'uid' => $uid, 'from' => $name ?: $email, 'from_email' => $email, 'reply_to' => $replyTo ?: $email,
            'to' => self::decode($h['to'] ?? ''), 'cc' => self::decode($h['cc'] ?? ''),
            'subject' => self::decode($h['subject'] ?? '') ?: '(no subject)',
            'date' => ($t = strtotime((string) ($h['date'] ?? ''))) ? date('Y-m-d H:i:s', $t) : '',
            'message_id' => trim((string) ($h['message-id'] ?? '')),
        ];
    }

    public function append(string $folder, string $raw): bool
    {
        $raw = str_replace(["\r\n", "\n"], ["\n", "\r\n"], $raw);
        $tag = 'A' . (++$this->tag);
        fwrite($this->s, "{$tag} APPEND " . self::q($folder) . ' (\\Seen) {' . strlen($raw) . "}\r\n");
        $line = fgets($this->s);
        if ($line === false || $line[0] !== '+') return false;
        fwrite($this->s, $raw . "\r\n");
        while (($line = fgets($this->s)) !== false) {
            if (str_starts_with($line, $tag . ' ')) return (bool) preg_match('/^' . $tag . ' OK/i', $line);
        }
        return false;
    }

    public function close(): void
    {
        if ($this->s) {
            @fwrite($this->s, 'A' . (++$this->tag) . " LOGOUT\r\n");
            @fclose($this->s);
            $this->s = null;
        }
    }

    // ── MIME ───────────────────────────────────────────────────────

    public static function decode(string $v): string
    {
        $v = trim(preg_replace('/\r?\n[ \t]+/', ' ', $v));
        if ($v === '') return '';
        $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $d !== false ? $d : mb_decode_mimeheader($v);
    }

    /** @return array{0: string, 1: string} name, email */
    public static function addr(string $v): array
    {
        $v = self::decode($v);
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $v, $m)) return [trim($m[1]), strtolower(trim($m[2]))];
        return ['', strtolower(trim($v, " <>\t"))];
    }

    public static function headers(string $block): array
    {
        $h = [];
        $block = preg_replace('/\r?\n[ \t]+/', ' ', $block);
        foreach (preg_split('/\r?\n/', $block) as $line) {
            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m)) {
                $k = strtolower($m[1]);
                if (!isset($h[$k])) $h[$k] = $m[2];
            }
        }
        return $h;
    }

    /** @return array{headers: array, text: string, html: string, attachments: string[]} */
    public static function parse(string $raw): array
    {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        $headers = self::headers($parts[0]);
        $out = ['headers' => $headers, 'text' => '', 'html' => '', 'attachments' => []];
        self::walk($headers, $parts[1] ?? '', $out);
        if ($out['text'] === '' && $out['html'] !== '') {
            $out['text'] = trim(html_entity_decode(strip_tags(preg_replace(['#<(br|/p|/div|/tr)[^>]*>#i', '#<(style|script)[^>]*>.*?</\1>#is'], ["\n", ''], $out['html'])), ENT_QUOTES, 'UTF-8'));
        }
        return $out;
    }

    private static function walk(array $h, string $body, array &$out): void
    {
        $type = strtolower((string) ($h['content-type'] ?? 'text/plain'));
        $disp = strtolower((string) ($h['content-disposition'] ?? ''));
        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $h['content-type'], $b)) {
            foreach (explode('--' . $b[1], $body) as $i => $part) {
                if ($i === 0 || str_starts_with($part, '--')) continue;
                $p = preg_split('/\r?\n\r?\n/', ltrim($part, "\r\n"), 2);
                self::walk(self::headers($p[0]), $p[1] ?? '', $out);
            }
            return;
        }
        $enc = strtolower(trim((string) ($h['content-transfer-encoding'] ?? '')));
        $data = $enc === 'base64' ? (string) base64_decode(preg_replace('/\s+/', '', $body)) : ($enc === 'quoted-printable' ? quoted_printable_decode($body) : $body);
        $hasName = (bool) preg_match('/name\*?="?([^";\r\n]+)/i', ($h['content-disposition'] ?? '') . ';' . ($h['content-type'] ?? ''), $n);
        if (str_contains($disp, 'attachment') || ($hasName && !str_starts_with($type, 'text/'))) {
            $out['attachments'][] = $hasName ? self::decode(rawurldecode(preg_replace("/^utf-8''/i", '', $n[1]))) : 'attachment';
            return;
        }
        $charset = preg_match('/charset="?([^";\s]+)/i', $h['content-type'] ?? '', $c) ? strtoupper($c[1]) : 'UTF-8';
        if ($charset !== 'UTF-8' && $charset !== 'US-ASCII') $data = (string) @mb_convert_encoding($data, 'UTF-8', $charset);
        if (str_starts_with($type, 'text/html') && $out['html'] === '') $out['html'] = $data;
        elseif (str_starts_with($type, 'text/plain') && $out['text'] === '') $out['text'] = $data;
    }
}

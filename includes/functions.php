<?php
declare(strict_types=1);

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function e(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postText(string $key, int $len = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $len);
}

const PROVINCES = ['Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape', 'Free State', 'Limpopo', 'Mpumalanga', 'North West', 'Northern Cape'];

const QUOTE_STATUSES = [
    'new'       => 'New request',
    'reviewing' => 'Being priced',
    'quoted'    => 'Quote sent',
    'accepted'  => 'Accepted',
    'declined'  => 'Declined',
    'completed' => 'Delivered',
    'cancelled' => 'Cancelled',
];

/** Business details shown on every page; editable in Admin → Settings → Business. */
function biz(string $key): string
{
    static $defaults = [
        'biz_name'     => "Durban's Pallets",
        'biz_trading'  => 'Pallet manufacturers',
        'biz_phone'    => '078 727 5235',
        'biz_whatsapp' => '27787275235',
        'biz_email'    => 'info@durbanspallets.co.za',
        'biz_address'  => '68 Columbine Place, Glen Anil, Durban, 4051',
        'biz_city'     => 'Durban',
        'biz_hours'    => "Mon – Fri: 08:00 – 20:00\nSat – Sun: 10:00 – 13:00",
        'biz_facebook' => '',
        'biz_maps'     => 'https://www.google.com/maps/search/?api=1&query=68+Columbine+Place+Glen+Anil+Durban+4051',
    ];
    return get_setting($key, $defaults[$key] ?? '');
}

function rand_(float|int|string|null $n, bool $cents = false): string
{
    if ($n === null || $n === '') return '—';
    $n = (float) $n;
    return 'R' . number_format($n, $cents || floor($n) != $n ? 2 : 0, '.', ' ');
}

function timeAgo(?string $dt): string
{
    if (!$dt) return '—';
    $s = time() - strtotime($dt);
    return match (true) {
        $s < 60      => 'just now',
        $s < 3600    => floor($s / 60) . ' min ago',
        $s < 86400   => floor($s / 3600) . ' h ago',
        $s < 172800  => 'yesterday',
        $s < 2592000 => floor($s / 86400) . ' days ago',
        default      => date('j M Y', strtotime($dt)),
    };
}

function excerpt(?string $text, int $len = 180): string
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text))));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len)) . '…' : $t;
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? mb_substr($s, 0, 180) : 'item-' . bin2hex(random_bytes(3));
}

/**
 * Plain text from the admin (or the old site) as safe HTML: blank lines make
 * paragraphs, lines starting with "- " make bullet lists, **bold** works.
 */
function richText(?string $text): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim((string) $text)) ?: [] as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
        if (!$lines) continue;
        $isList = count(array_filter($lines, fn($l) => str_starts_with($l, '- '))) === count($lines);
        $fmt = fn(string $l) => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($l));
        if ($isList) {
            $out .= '<ul>' . implode('', array_map(fn($l) => '<li>' . $fmt(substr($l, 2)) . '</li>', $lines)) . '</ul>';
        } elseif (count($lines) === 1 && mb_strlen($lines[0]) < 70 && !preg_match('/[.:,]$/', $lines[0])) {
            $out .= '<h3>' . $fmt($lines[0]) . '</h3>';
        } else {
            $out .= '<p>' . implode('<br>', array_map($fmt, $lines)) . '</p>';
        }
    }
    return $out;
}

function audit(string $action, string $target = '', string $detail = ''): void
{
    getDB()->prepare('INSERT INTO audit_log (admin_id, action, target, detail, ip) VALUES (?, ?, ?, ?, ?)')
        ->execute([$_SESSION['user_id'] ?? null, $action, mb_substr($target, 0, 200), $detail, clientIp()]);
}

function pager(int $total, int $page, int $per, array $query): string
{
    $pages = (int) ceil($total / $per);
    if ($pages <= 1) return '';
    $link = function (int $p, string $label, bool $on = false) use ($query) {
        $q = http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="' . ($on ? 'is-on' : '') . '" href="?' . e($q) . '">' . $label . '</a>';
    };
    $out = '<nav class="pager">';
    if ($page > 1) $out .= $link($page - 1, '<i class="fa-solid fa-chevron-left"></i>');
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) $out .= $link($p, (string) $p, $p === $page);
    if ($page < $pages) $out .= $link($page + 1, '<i class="fa-solid fa-chevron-right"></i>');
    return $out . '<span>' . number_format($total) . ' results</span></nav>';
}

function waLink(string $text = ''): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', biz('biz_whatsapp')) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

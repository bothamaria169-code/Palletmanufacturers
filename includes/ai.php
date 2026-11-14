<?php
declare(strict_types=1);

/**
 * Groq AI (free, OpenAI-compatible) — writes product descriptions in the admin
 * and personal replies to contact-form enquiries.
 *
 * The model is never hard-coded: the key's live model list is read from Groq,
 * the best one in GROQ_PREFERRED is used, and a retired model is swapped for
 * the next best automatically.
 *
 * Key: console.groq.com → API Keys, saved in Admin → Settings → AI.
 */
const GROQ_PREFERRED = [
    'openai/gpt-oss-120b', 'qwen/qwen3.6-27b', 'moonshotai/kimi-k2-instruct-0905', 'moonshotai/kimi-k2-instruct',
    'openai/gpt-oss-20b', 'meta-llama/llama-4-maverick-17b-128e-instruct', 'meta-llama/llama-4-scout-17b-16e-instruct',
    'qwen/qwen3-32b', 'llama-3.3-70b-versatile',
];

/** Chat models this key can use, from Groq (cached for a day). Empty on failure. */
function groqModels(bool $refresh = false): array
{
    $cached = json_decode(get_setting('groq_models_cache'), true) ?: [];
    if (!$refresh && !empty($cached['at']) && $cached['at'] > time() - 86400 && !empty($cached['ids'])) return $cached['ids'];
    if (aiKey() === '') return [];
    $ch = curl_init('https://api.groq.com/openai/v1/models');
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . aiKey()], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    $res = json_decode((string) curl_exec($ch), true) ?: [];
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return $cached['ids'] ?? [];
    $ids = [];
    foreach ($res['data'] ?? [] as $m) {
        $id = (string) ($m['id'] ?? '');
        if ($id === '' || (isset($m['active']) && !$m['active'])) continue;
        // Speech, moderation and agent systems can't write letters.
        if (preg_match('/whisper|tts|playai|orpheus|guard|safeguard|prompt-guard|compound|distil/i', $id)) continue;
        $ids[] = $id;
    }
    sort($ids);
    set_setting('groq_models_cache', json_encode(['at' => time(), 'ids' => $ids]));
    return $ids;
}

/** The best available model: the admin's choice if Groq still offers it, else the first preferred one. */
function groqModel(bool $refresh = false): string
{
    $chosen = get_setting('groq_model');
    $ids = groqModels($refresh);
    if (!$ids) return $chosen ?: GROQ_PREFERRED[0];
    if ($chosen !== '' && in_array($chosen, $ids, true)) return $chosen;
    foreach (GROQ_PREFERRED as $m) if (in_array($m, $ids, true)) return $m;
    return $ids[0];
}

/** Pulls the JSON object out of a reply that may wrap it in text or ``` fences. */
function jsonFromText(string $text): ?array
{
    $d = json_decode($text, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $d = json_decode($m[0], true);
        if (is_array($d)) return $d;
    }
    return null;
}
function aiKey(): string
{
    return trim(get_setting('groq_api_key', (string) secret('groq_api_key', '')));
}

function aiEnabled(): bool
{
    return aiKey() !== '' && get_setting('ai_enabled', '1') === '1';
}

function logAi(?int $userId, string $kind, bool $ok): void
{
    getDB()->prepare('INSERT INTO ai_requests (user_id, kind, ok) VALUES (?, ?, ?)')->execute([$userId, $kind, $ok ? 1 : 0]);
}

/**
 * One chat completion that must answer with JSON.
 *
 * @return array{ok: bool, data: array, error: string}
 */
function groqJson(string $system, string $user, int $maxTokens = 900): array
{
    $model = groqModel();
    $r = groqCall($model, $system, $user, $maxTokens, true);
    // Model retired or not on this key: re-read the list, switch, try once more.
    if (!$r['ok'] && preg_match('/does not exist|do not have access|decommission|deprecat|model_not_found|not supported/i', $r['error'])) {
        $next = groqModel(true);
        if ($next === $model) {
            $ids = groqModels();
            foreach (GROQ_PREFERRED as $m) if ($m !== $model && (!$ids || in_array($m, $ids, true))) { $next = $m; break; }
        }
        if ($next !== $model) {
            set_setting('groq_model', $next);
            error_log("Pallets: Groq model {$model} unavailable, switched to {$next}");
            $r = groqCall($next, $system, $user, $maxTokens, true);
        }
    }
    // Some models reject JSON mode; ask again for plain text and read the JSON out of it.
    if (!$r['ok'] && preg_match('/json|response_format/i', $r['error'])) {
        $r = groqCall(groqModel(), $system, $user, $maxTokens, false);
    }
    return $r;
}

/** @return array{ok: bool, data: array, error: string, model: string} */
function groqCall(string $model, string $system, string $user, int $maxTokens, bool $jsonMode): array
{
    $reasoning = (bool) preg_match('#^openai/gpt-oss|qwen3#', $model);
    $body = [
        'model'       => $model,
        'temperature' => 0.5,
        // Reasoning models spend tokens thinking before they answer.
        'max_completion_tokens' => $reasoning ? $maxTokens + 2500 : $maxTokens,
        'messages'    => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
    ];
    if ($jsonMode) $body['response_format'] = ['type' => 'json_object'];
    if (str_starts_with($model, 'openai/gpt-oss')) $body['reasoning_effort'] = 'low';
    if (str_starts_with($model, 'qwen/qwen3')) $body['reasoning_format'] = 'hidden';

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . aiKey(), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 50,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw  = (string) curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') return ['ok' => false, 'data' => [], 'error' => $err, 'model' => $model];
    $res = json_decode($raw, true) ?: [];
    if ($code !== 200) return ['ok' => false, 'data' => [], 'error' => (string) ($res['error']['message'] ?? 'HTTP ' . $code), 'model' => $model];
    $data = jsonFromText((string) ($res['choices'][0]['message']['content'] ?? ''));
    return $data !== null ? ['ok' => true, 'data' => $data, 'error' => '', 'model' => $model] : ['ok' => false, 'data' => [], 'error' => 'The AI answer was not readable.', 'model' => $model];
}


/** AI calls made this month (all kinds). */
function aiUsedThisMonth(): int
{
    $s = getDB()->prepare('SELECT COUNT(*) FROM ai_requests WHERE ok = 1 AND created_at >= ?');
    $s->execute([date('Y-m-01 00:00:00')]);
    return (int) $s->fetchColumn();
}

function aiUnderLimit(): bool
{
    return aiUsedThisMonth() < (int) get_setting('ai_monthly_limit', '500');
}

/**
 * Short + long description for a product, from its name, category, price and
 * whatever the admin already wrote.
 *
 * @return array{ok: bool, short?: string, description?: string, error?: string}
 */
function aiProductDescription(array $p, ?int $adminId): array
{
    if (!aiEnabled()) return ['ok' => false, 'error' => 'Add a Groq API key in Settings → AI first.'];
    if (!aiUnderLimit()) return ['ok' => false, 'error' => 'This month\'s AI limit is used up (Settings → AI).'];
    $system = <<<SYS
You write product copy for a South African pallet manufacturer and supplier (wooden, plastic, aluminium, galvanised and custom pallets, based in Durban, KZN).
Answer ONLY with JSON: {"short":"","description":""}.
- South African English. Plain text only. No markdown headings, no emojis, no prices (prices come from the database).
- short: 1–2 sentences, max 220 characters, the main use and benefit.
- description: 90–170 words. First a short paragraph, then a blank line, then 4–6 bullet lines that each start with "- " (size, entry type, material/grade, typical load or use, industries). Then a blank line and one line inviting the reader to request a quote for bulk or custom sizes.
- Only state dimensions, weights and specs that appear in the input. Never invent load ratings or certifications.
SYS;
    $prompt = "Product: {$p['name']}\nCategory: " . ($p['category'] ?? '') . "\nExisting notes:\n" . mb_substr(trim(($p['short_description'] ?? '') . "\n" . ($p['description'] ?? '')), 0, 2500);
    $r = groqJson($system, $prompt, 700);
    $desc = trim((string) ($r['data']['description'] ?? ''));
    $ok = $r['ok'] && mb_strlen($desc) > 60;
    logAi($adminId, 'product', $ok);
    if (!$ok) return ['ok' => false, 'error' => 'The AI could not write it just now' . ($r['error'] ? ' (' . $r['error'] . ')' : '') . '.'];
    return ['ok' => true, 'short' => mb_substr(trim((string) ($r['data']['short'] ?? '')), 0, 400), 'description' => $desc];
}

/**
 * Thank-you email for a contact-form enquiry. The AI writes the middle (a
 * helpful answer using our real product list); greeting, letterhead and
 * signature are fixed. Falls back to the standard thank-you on any failure.
 */
function sendEnquiryReply(array $d): void
{
    $first = e(explode(' ', trim($d['full_name']))[0] ?: 'there');
    $body = '<p>Thank you for contacting ' . e(APP_NAME) . '. We\'ve received your message and one of our team will get back to you shortly — usually within one working day.</p>';
    if (aiEnabled() && aiUnderLimit() && get_setting('ai_autoreply', '1') === '1') {
        $list = [];
        foreach (getDB()->query('SELECT name, price FROM products WHERE is_active = 1 ORDER BY sort_order LIMIT 60') as $p) {
            $list[] = '- ' . $p['name'] . ($p['price'] !== null ? ' — R' . (float) $p['price'] : '');
        }
        $system = 'You are the friendly sales assistant of ' . APP_NAME . ', a pallet manufacturer in Durban, South Africa. '
            . 'Write ONLY the middle part of an email reply to a website enquiry (no greeting, no sign-off). Answer with JSON {"body":""}. '
            . '2 short paragraphs, plain text, South African English. Thank them, respond to what they asked using ONLY the product list and facts given, '
            . 'say a team member will confirm prices, stock and delivery, and suggest requesting a quote on the website for bulk or custom sizes. '
            . 'Never invent prices, discounts, delivery dates or promises not in the facts.';
        $facts = "Phone: " . biz('biz_phone') . "\nHours: " . str_replace("\n", '; ', biz('biz_hours')) . "\nAddress: " . biz('biz_address')
               . "\nPrices are per pallet. Delivery cost is confirmed on each quote. Custom sizes and heat-treated export pallets are made to order.\nPRODUCTS:\n" . implode("\n", $list);
        $r = groqJson($system, "FACTS\n{$facts}\n\nENQUIRY\n" . mb_substr($d['message'], 0, 2000), 500);
        $ai = trim((string) ($r['data']['body'] ?? ''));
        $ok = $r['ok'] && mb_strlen($ai) > 40;
        logAi(null, 'autoreply', $ok);
        if ($ok) $body = implode('', array_map(fn($para) => '<p>' . nl2br(e(trim($para))) . '</p>', preg_split('/\n\s*\n/', $ai)));
    }
    $sig = '<p style="margin-top:22px">Kind regards,<br><strong>The ' . e(APP_NAME) . ' sales team</strong><br>' . e(biz('biz_phone')) . ' · ' . e(biz('biz_email')) . '</p>';
    sendMail($d['email'], 'Thanks for contacting ' . APP_NAME, emailLayout('Hi ' . $first . ',', $body . $sig, 'Request a quote', APP_URL . '/quote.php'), [], biz('biz_email'), 'enquiry_reply');
}

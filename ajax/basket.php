<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/boot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValid()) jsonResponse(['ok' => false, 'error' => 'Session expired — refresh the page.'], 419);

$id  = (int) ($_POST['product_id'] ?? 0);
$qty = max(0, (int) ($_POST['qty'] ?? 1));
$notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 300);
$p = $id ? productById($id) : null;
if (!$p || !$p['is_active']) jsonResponse(['ok' => false, 'error' => 'That product is no longer available.']);

$action = $_POST['action'] ?? 'add';
if ($action === 'add') {
    $current = basket()[$id]['qty'] ?? 0;
    basketSet($id, $current + max(1, $qty), $notes !== '' ? $notes : null);
} else {
    basketSet($id, $qty, $notes);
}

$lines = basketLines();
$est = array_sum(array_map(fn($l) => $l['line'] ?? 0, $lines));
$mine = basket()[$id]['qty'] ?? 0;
jsonResponse([
    'ok'       => true,
    'count'    => count($lines),
    'line'     => $p['price'] !== null ? rand_((float) $p['price'] * $mine, true) : 'TBC',
    'estimate' => rand_($est, true),
    'message'  => $action === 'add' ? "Added to your quote ({$mine} × " . mb_strimwidth($p['name'], 0, 40, '…') . ')' : 'Quote updated',
]);

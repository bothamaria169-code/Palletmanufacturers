<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/boot.php';
require_once dirname(__DIR__) . '/includes/ai.php';

if (!isAdmin() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValid()) jsonResponse(['ok' => false, 'error' => 'Not allowed.'], 403);
$name = postText('name', 200);
if ($name === '') jsonResponse(['ok' => false, 'error' => 'Type the product name first.']);
$r = aiProductDescription([
    'name' => $name, 'category' => postText('category', 120),
    'short_description' => postText('short_description', 1000), 'description' => postText('description', 4000),
], (int) $_SESSION['user_id']);
jsonResponse($r);

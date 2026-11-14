<?php
declare(strict_types=1);

/** Every page starts here: config, database, auth, layout, catalogue and quotes. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/catalogue.php';
require_once __DIR__ . '/quotes.php';
require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/invoices.php';

startAppSession();

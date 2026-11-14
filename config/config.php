<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('This site needs PHP 8.1 or newer. In cPanel open "Select PHP Version" (or "MultiPHP Manager") and choose PHP 8.2.');
}

// ── Secrets ──────────────────────────────────────────────────────
// Passwords and keys live in config/secrets.php (not in git). Copy
// config/secrets.example.php to start one.
$secretsFile = __DIR__ . '/secrets.php';
$GLOBALS['PM_SECRETS'] = is_file($secretsFile) ? (require $secretsFile) : [];

function secret(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['PM_SECRETS'][$key] ?? $default;
}

// ── App ──────────────────────────────────────────────────────────
define('APP_NAME', "Durban's Pallets");
define('APP_TAGLINE', 'Wooden, plastic, aluminium and galvanised pallets — manufactured, recycled and delivered across South Africa');
define('BASE_DIR', dirname(__DIR__));

$host = $_SERVER['HTTP_HOST'] ?? '';
define('IS_LOCAL', $host === ''
    ? PHP_OS_FAMILY === 'Windows'
    : (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$|\.(localhost|test)(:\d+)?$/', $host));

// APP_URL comes from the current host and install folder, so the same code
// runs at http://localhost/Palletmanufacturers.co.za and on the live domain.
$appUrl = (string) getenv('APP_URL');
if ($appUrl === '' && $host !== '') {
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $basePath = '/';
    if ($documentRoot && realpath(BASE_DIR) !== false && str_starts_with(realpath(BASE_DIR), $documentRoot)) {
        $relativePath = str_replace('\\', '/', substr(realpath(BASE_DIR), strlen($documentRoot)));
        $basePath = '/' . trim($relativePath, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
    $appUrl = $scheme . '://' . $host . ($basePath === '/' ? '' : $basePath);
}
if ($appUrl === '') {
    $appUrl = (string) secret('app_url', IS_LOCAL ? 'http://localhost/Palletmanufacturers.co.za' : 'https://durbanspallets.co.za');
}
define('APP_URL', rtrim($appUrl, '/'));

define('ADMIN_EMAIL', strtolower((string) secret('admin_email', 'info@durbanspallets.co.za')));
define('APP_SECRET', (string) secret('app_secret', 'change-me'));

// ── Database ─────────────────────────────────────────────────────
define('DB_HOST', (string) secret('db_host', 'localhost'));
define('DB_NAME', (string) secret(IS_LOCAL ? 'local_db_name' : 'db_name', 'durbanspallets'));
define('DB_USER', (string) secret(IS_LOCAL ? 'local_db_user' : 'db_user', 'root'));
define('DB_PASS', (string) secret(IS_LOCAL ? 'local_db_pass' : 'db_pass', ''));

// ── Uploads ──────────────────────────────────────────────────────
define('PRODUCT_IMG_DIR', BASE_DIR . '/uploads/products/');
define('MAX_IMG_SIZE', 8 * 1024 * 1024);

// ── Email / SMTP (PHPMailer) ──────────────────────────────────────
define('SMTP_HOST', (string) secret('smtp_host', ''));
define('SMTP_PORT', (int) secret('smtp_port', 465));
define('SMTP_USER', (string) secret('smtp_user', ''));
define('SMTP_PASS', (string) secret('smtp_pass', ''));

date_default_timezone_set('Africa/Johannesburg');
if (!IS_LOCAL) {
    ini_set('display_errors', '0');
}

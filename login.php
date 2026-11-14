<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

/** Only same-site paths are allowed after sign-in. */
function safeNext(string $n): string
{
    return preg_match('#^/[A-Za-z0-9._\-/?=&%]*$#', $n) && !str_starts_with($n, '//') ? $n : '';
}
$next = safeNext((string) ($_GET['next'] ?? $_POST['next'] ?? ''));
if (isLoggedIn() && getCurrentUser()) redirect(isAdmin() ? 'admin/' : 'dashboard.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!csrfValid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $result = loginUser($email, $password);
        $error = match ($result) {
            true         => '',
            'disabled'   => 'This account has been disabled. Please contact ' . biz('biz_email') . '.',
            'locked'     => 'Too many attempts. Please wait 15 minutes, or reset your password.',
            'nopassword' => 'Your account has no password yet. Use "Forgot password?" to get a link and set one.',
            default      => 'Wrong email or password.',
        };
        if ($result === true) {
            if (isAdmin()) redirect('admin/');
            // $next is a path under the web root; strip the install folder so redirect() doesn't double it.
            $base = (string) parse_url(APP_URL, PHP_URL_PATH);
            if ($next !== '' && $base !== '' && str_starts_with($next, $base)) $next = substr($next, strlen($base));
            redirect($next !== '' ? $next : 'dashboard.php');
        }
    }
}

authOpen('Sign in', 'Welcome back', 'Sign in to see your quotes, prices and orders.');
?>
      <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?></span></div><?php endif; ?>
      <form method="POST" novalidate data-busy>
        <?= csrfField() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="email">Email</label>
          <div class="input-wrap"><i class="fa-regular fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="you@company.co.za" value="<?= e($email) ?>" autocomplete="email" required <?= $email ? '' : 'autofocus' ?>>
          </div>
        </div>
        <div class="field">
          <label for="password">Password <a href="forgot-password.php<?= $email ? '?email=' . rawurlencode($email) : '' ?>">Forgot password?</a></label>
          <div class="input-wrap"><i class="fa-solid fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required <?= $email ? 'autofocus' : '' ?>>
            <button type="button" class="pw-toggle" data-toggle-pw="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
      </form>
      <p class="auth__alt">New customer? <a href="register.php">Create a free account</a></p>
<?php authClose();

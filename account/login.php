<?php
/**
 * Login with email address (or username) and password.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (dat_is_logged_in()) {
    header('Location: ' . dat_url('dashboard/index.php'));
    exit;
}

/** Only allow redirects inside this application. */
$safeNext = static function ($candidate) {
    $candidate = (string) $candidate;
    if ($candidate === '' || $candidate[0] !== '/' || strncmp($candidate, '//', 2) === 0) {
        return null;
    }
    return $candidate;
};

$errors = [];
$identifier = '';
$next = $safeNext($_POST['next'] ?? $_GET['next'] ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $result = dat_authenticate($identifier, (string) ($_POST['password'] ?? ''));

        if ($result['success']) {
            dat_login_session($result['user']);
            // Pick up anything that was created as a guest in this session.
            if (dat_claim_guest_assets($result['user']['id']) > 0) {
                dat_flash_set('success', t('login.claimed', 'The tag from your guest session is now in your account.'));
            }
            $target = $next !== null ? $next : dat_url('dashboard/index.php');
            header('Location: ' . $target);
            exit;
        }

        $errors[] = $result['message'] ?? t('error.login_failed', 'Email address or password is not correct.');
    }
}

dat_page_start([
    'title' => t('login.title', 'Log in') . ' | ' . PORTAL_NAME,
    'description' => t('login.meta', 'Log in to manage your digital asset tags.'),
    'robots' => 'noindex, follow',
    'unread' => 0,
]);
?>
<main id="main" class="auth-shell">
    <aside class="auth-aside">
        <h2><?= e(t('login.aside_title', 'Welcome back')) ?></h2>
        <p><?= e(t('login.aside_body', 'Your dashboard shows every asset, its tags and the messages finders sent you.')) ?></p>
        <ul>
            <li><i class="fa-solid fa-qrcode" aria-hidden="true"></i><span><?= e(t('login.point1', 'Download QR codes as PNG or SVG, ready to print.')) ?></span></li>
            <li><i class="fa-solid fa-wifi" aria-hidden="true"></i><span><?= e(t('login.point2', 'Copy the short NFC text and write it with any NFC writer app.')) ?></span></li>
            <li><i class="fa-solid fa-inbox" aria-hidden="true"></i><span><?= e(t('login.point3', 'Answer finders without revealing your contact details.')) ?></span></li>
        </ul>
    </aside>

    <div class="auth-main">
        <div class="auth-form">
            <h1><?= e(t('login.heading', 'Log in')) ?></h1>
            <p><?= e(t('login.subheading', 'Use the email address you registered with.')) ?></p>

            <?= dat_flash_render() ?>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(dat_url('account/login.php')) ?>" novalidate>
                <?= dat_csrf_field() ?>
                <?php if ($next !== null): ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label for="identifier"><?= e(t('form.email', 'Email address')) ?></label>
                    <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autocomplete="username">
                </div>

                <div class="form-row">
                    <label for="password"><?= e(t('form.password', 'Password')) ?></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg"><?= e(t('login.submit', 'Log in')) ?></button>
            </form>

            <p class="auth-switch">
                <?= e(t('login.no_account', 'No account yet?')) ?>
                <a href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
            </p>
        </div>
    </div>
</main>
<?php
dat_page_end(['footer' => false]);

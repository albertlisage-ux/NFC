<?php
/**
 * Registration. Collects the minimum: email, optional display name, password.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (dat_is_logged_in()) {
    header('Location: ' . dat_url('dashboard/index.php'));
    exit;
}

$errors = [];
$email = '';
$displayName = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!empty($_POST['website'])) {
            // Honeypot filled: treat as spam without telling the bot.
            dat_flash_set('error', t('error.register_failed', 'The account could not be created. Please try again.'));
            header('Location: ' . dat_url('account/register.php'));
            exit;
        }

        $result = dat_register_user($email, $password, $confirm, $displayName);
        if ($result['success']) {
            dat_login_session($result['user']);
            // A tag created in a guest session moves into the new account.
            $claimed = dat_claim_guest_assets($result['user']['id']);
            if ($claimed > 0) {
                dat_flash_set('success', t('register.claimed', 'Welcome. The tag from your guest session is now in your account.'));
                header('Location: ' . dat_url('dashboard/index.php'));
                exit;
            }
            dat_flash_set('success', t('register.welcome', 'Welcome. Your account is ready, add your first asset.'));
            header('Location: ' . dat_url('dashboard/assets-new.php'));
            exit;
        }

        $errors = $result['errors'] ?? [t('error.register_failed', 'The account could not be created. Please try again.')];
    }
}

dat_page_start([
    'title' => t('register.title', 'Create account') . ' | ' . PORTAL_NAME,
    'description' => t('register.meta', 'Create a Digital Asset Tag account with an email address and a password.'),
    'robots' => 'noindex, follow',
    'nav' => true,
    'unread' => 0,
]);
?>
<main id="main" class="auth-shell">
    <aside class="auth-aside">
        <h2><?= e(t('register.aside_title', 'Start with one tag')) ?></h2>
        <p><?= e(t('register.aside_body', 'An account holds your assets, their public pages and the messages finders send you.')) ?></p>
        <ul>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><?= e(t('register.point1', 'Email and password only, no phone number or address.')) ?></span></li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><?= e(t('register.point2', 'Unlimited assets, each with its own stable public link.')) ?></span></li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><?= e(t('register.point3', 'Anonymous message inbox, replies inside the portal.')) ?></span></li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><?= e(t('register.point4', 'Delete an asset at any time; the tag then reports that it is no longer active.')) ?></span></li>
        </ul>
    </aside>

    <div class="auth-main">
        <div class="auth-form">
            <h1><?= e(t('register.heading', 'Create your account')) ?></h1>
            <p><?= e(t('register.subheading', 'It takes a minute. You can add the first asset straight away.')) ?></p>

            <?= dat_flash_render() ?>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(dat_url('account/register.php')) ?>" novalidate>
                <?= dat_csrf_field() ?>
                <div class="honeypot" aria-hidden="true">
                    <label for="website"><?= e(t('form.website', 'Website')) ?></label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row">
                    <label for="email"><?= e(t('form.email', 'Email address')) ?></label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
                    <span class="form-hint"><?= e(t('register.email_hint', 'Used to sign in. It is never shown on a public tag page.')) ?></span>
                </div>

                <div class="form-row">
                    <label for="display_name"><?= e(t('form.display_name', 'Name for your dashboard (optional)')) ?></label>
                    <input type="text" id="display_name" name="display_name" value="<?= e($displayName) ?>" autocomplete="name" maxlength="80">
                </div>

                <div class="form-row">
                    <label for="password"><?= e(t('form.password', 'Password')) ?></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password" minlength="10">
                    <span class="form-hint"><?= e(t('register.password_hint', 'At least 10 characters, including a letter and a number.')) ?></span>
                </div>

                <div class="form-row">
                    <label for="password_confirm"><?= e(t('form.password_confirm', 'Repeat password')) ?></label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" minlength="10">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg"><?= e(t('register.submit', 'Create account')) ?></button>
            </form>

            <p class="auth-switch">
                <?= e(t('register.have_account', 'Already have an account?')) ?>
                <a href="<?= e(dat_url('account/login.php')) ?>"><?= e(t('nav.login', 'Log in')) ?></a>
            </p>
            <p class="auth-switch">
                <?= e(t('register.guest_prompt', 'Want to try it first?')) ?>
                <a href="<?= e(dat_url('start')) ?>"><?= e(t('register.guest_link', 'Create one guest tag without an account')) ?></a>
            </p>
        </div>
    </div>
</main>
<?php
dat_page_end(['footer' => false]);

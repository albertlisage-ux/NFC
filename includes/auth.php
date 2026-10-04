<?php
/**
 * Accounts: registration, login, sessions and CSRF.
 *
 * Only an email address, a display name and a password hash are stored. No
 * phone number, address or birthday is collected, matching the data
 * minimisation rules in the architecture document.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';

const DAT_USER_STATUS_ACTIVE   = 1;
const DAT_USER_STATUS_DISABLED = 2;

// A role, not a flag: the administrator area reads every table, and this is
// the one place that decides who may open it.
const DAT_USER_ROLE_USER  = 'user';
const DAT_USER_ROLE_ADMIN = 'admin';

const DAT_MESSAGE_DIRECTION_FINDER = 1;
const DAT_MESSAGE_DIRECTION_OWNER  = 2;

if (!function_exists('dat_normalize_email')) {
    function dat_normalize_email($email)
    {
        return mb_strtolower(trim((string) $email));
    }
}

if (!function_exists('dat_username_from_email')) {
    function dat_username_from_email($email)
    {
        $local = explode('@', $email)[0] ?? 'user';
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $local) ?: 'user';
        $base = mb_substr($base, 0, 40);

        $candidate = $base;
        $suffix = 1;
        while (dat_user_by_username($candidate) !== null) {
            $candidate = mb_substr($base, 0, 36) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}

if (!function_exists('dat_user_by_username')) {
    function dat_user_by_username($username)
    {
        return dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE username = ? LIMIT 1', [$username]);
    }
}

if (!function_exists('dat_user_by_email')) {
    function dat_user_by_email($email)
    {
        return dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE email = ? LIMIT 1', [dat_normalize_email($email)]);
    }
}

if (!function_exists('dat_user_by_id')) {
    function dat_user_by_id($id)
    {
        if (!is_string($id) || $id === '') {
            return null;
        }
        return dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE id = ? LIMIT 1', [$id]);
    }
}

/**
 * Register a new owner account.
 *
 * @return array{success: bool, user?: array, errors?: string[]}
 */
if (!function_exists('dat_register_user')) {
    function dat_register_user($email, $password, $passwordConfirm, $displayName = '')
    {
        $email = dat_normalize_email($email);
        $displayName = trim((string) $displayName);
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('error.email_invalid', 'Please enter a valid email address.');
        }
        if (strlen((string) $password) < 10) {
            $errors[] = t('error.password_short', 'Use at least 10 characters for your password.');
        }
        if (!preg_match('/[A-Za-z]/', (string) $password) || !preg_match('/[0-9]/', (string) $password)) {
            $errors[] = t('error.password_weak', 'The password needs at least one letter and one number.');
        }
        if ($password !== $passwordConfirm) {
            $errors[] = t('error.password_mismatch', 'The two passwords do not match.');
        }
        if (mb_strlen($displayName) > 80) {
            $errors[] = t('error.name_long', 'Please shorten the display name.');
        }
        if (dat_db() === null) {
            $errors[] = t('error.db_unavailable', 'The service is temporarily unavailable. Please try again later.');
        }
        if (!$errors && dat_user_by_email($email) !== null) {
            $errors[] = t('error.email_taken', 'An account with this email address already exists.');
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $userId = dat_uuid();
        $now = dat_now();
        $created = dat_exec(
            'INSERT INTO ' . dat_table('users') . '
                (id, email, username, display_name, password_hash, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $email,
                dat_username_from_email($email),
                $displayName !== '' ? $displayName : null,
                password_hash((string) $password, PASSWORD_DEFAULT),
                DAT_USER_STATUS_ACTIVE,
                $now,
                $now,
            ]
        );

        if ($created < 0) {
            return ['success' => false, 'errors' => [t('error.register_failed', 'The account could not be created. Please try again.')]];
        }

        $user = dat_user_by_id($userId);
        if ($user !== null) {
            dat_log_login_attempt($email, $userId, true, 'register');
        }

        return ['success' => true, 'user' => $user];
    }
}

/**
 * Log a login attempt and reject repeated failures from the same source.
 */
if (!function_exists('dat_login_is_throttled')) {
    function dat_login_is_throttled($identifier)
    {
        $row = dat_one(
            'SELECT COUNT(*) AS failures FROM ' . dat_table('login_logs') . '
              WHERE identifier = ? AND success = 0 AND created_at > ?',
            [mb_substr((string) $identifier, 0, 190), date('Y-m-d H:i:s', time() - 900)]
        );

        return (int) ($row['failures'] ?? 0) >= 10;
    }
}

if (!function_exists('dat_log_login_attempt')) {
    function dat_log_login_attempt($identifier, $userId, $success, $reason = null)
    {
        dat_query(
            'INSERT INTO ' . dat_table('login_logs') . '
                (user_id, identifier, ip_hash, user_agent, success, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                mb_substr((string) $identifier, 0, 190),
                dat_ip_hash(),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $success ? 1 : 0,
                $reason,
                dat_now(),
            ]
        );
    }
}

/**
 * Verify credentials and return the user row on success.
 *
 * @return array{success: bool, user?: array, message?: string}
 */
if (!function_exists('dat_authenticate')) {
    function dat_authenticate($identifier, $password)
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => t('error.credentials_required', 'Please enter your email address and password.')];
        }
        if (dat_db() === null) {
            return ['success' => false, 'message' => t('error.db_unavailable', 'The service is temporarily unavailable. Please try again later.')];
        }
        if (dat_login_is_throttled($identifier)) {
            return ['success' => false, 'message' => t('error.too_many_attempts', 'Too many failed attempts. Please wait 15 minutes.')];
        }

        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? dat_user_by_email($identifier)
            : dat_user_by_username($identifier);

        if ($user === null) {
            // Spend comparable time so a missing account is not obvious from timing.
            password_verify((string) $password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');
            dat_log_login_attempt($identifier, null, false, 'unknown_user');
            return ['success' => false, 'message' => t('error.login_failed', 'Email address or password is not correct.')];
        }

        if ((int) $user['status'] !== DAT_USER_STATUS_ACTIVE) {
            dat_log_login_attempt($identifier, $user['id'], false, 'disabled');
            return ['success' => false, 'message' => t('error.account_disabled', 'This account is disabled.')];
        }

        if (!password_verify((string) $password, $user['password_hash'])) {
            dat_log_login_attempt($identifier, $user['id'], false, 'bad_password');
            return ['success' => false, 'message' => t('error.login_failed', 'Email address or password is not correct.')];
        }

        dat_exec(
            'UPDATE ' . dat_table('users') . ' SET last_login_at = ? WHERE id = ?',
            [dat_now(), $user['id']]
        );
        dat_log_login_attempt($identifier, $user['id'], true, null);

        return ['success' => true, 'user' => $user];
    }
}

/* -------------------------------------------------------------------------
 * Session
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_login_session')) {
    function dat_login_session(array $user)
    {
        dat_session_start();
        session_regenerate_id(true);
        $_SESSION['dat_user_id'] = $user['id'];
        $_SESSION['dat_login_at'] = time();
    }
}

if (!function_exists('dat_logout')) {
    function dat_logout()
    {
        dat_session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
        }
        session_destroy();
    }
}

if (!function_exists('dat_current_user')) {
    function dat_current_user()
    {
        static $user = null;
        static $loaded = false;

        if ($loaded) {
            return $user;
        }
        $loaded = true;

        dat_session_start();
        $id = $_SESSION['dat_user_id'] ?? null;
        if (!is_string($id) || $id === '') {
            $user = null;
            return null;
        }

        $row = dat_user_by_id($id);
        if ($row === null || (int) $row['status'] !== DAT_USER_STATUS_ACTIVE) {
            $user = null;
            return null;
        }

        $user = $row;
        return $user;
    }
}

if (!function_exists('dat_is_logged_in')) {
    function dat_is_logged_in()
    {
        return dat_current_user() !== null;
    }
}

if (!function_exists('dat_require_login')) {
    function dat_require_login($returnTo = null)
    {
        if (dat_is_logged_in()) {
            return dat_current_user();
        }

        $target = $returnTo ?? ($_SERVER['REQUEST_URI'] ?? '/dashboard/');
        header('Location: ' . dat_url('account/login.php') . '?next=' . rawurlencode($target));
        exit;
    }
}

/** Whether an account carries the administrator role. */
if (!function_exists('dat_user_is_admin')) {
    function dat_user_is_admin($user)
    {
        if (!is_array($user)) {
            return false;
        }

        return (string) ($user['role'] ?? DAT_USER_ROLE_USER) === DAT_USER_ROLE_ADMIN;
    }
}

/**
 * Gate for the administrator area.
 *
 * A visitor who is not signed in is sent to the login form and comes back.
 * A signed-in visitor without the role gets a plain 404, because answering
 * "you are not allowed" would confirm that the area exists.
 */
if (!function_exists('dat_require_admin')) {
    function dat_require_admin()
    {
        $user = dat_require_login();
        if (dat_user_is_admin($user)) {
            return $user;
        }

        http_response_code(404);
        dat_page_start([
            'title' => t('tag.not_found_title', 'Tag not found') . ' | ' . PORTAL_NAME,
            'robots' => 'noindex, nofollow',
            'unread' => 0,
        ]);
        ?>
        <main id="main" class="shell">
            <section class="tag-page">
                <div class="empty-state">
                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                    <h1><?= e(t('tag.not_found_title', 'Tag not found')) ?></h1>
                    <p><?= e(t('tag.not_found_body', 'This link does not match any registered asset. Check the address, or scan the tag again.')) ?></p>
                    <p><a class="btn btn-ghost" href="<?= e(dat_url('dashboard/index.php')) ?>"><?= e(t('nav.dashboard', 'Overview')) ?></a></p>
                </div>
            </section>
        </main>
        <?php
        dat_page_end();
        exit;
    }
}

if (!function_exists('dat_owner_name')) {
    function dat_owner_name(array $user)
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        return $name !== '' ? $name : $user['username'];
    }
}

/* -------------------------------------------------------------------------
 * CSRF
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_csrf_token')) {
    function dat_csrf_token()
    {
        dat_session_start();
        if (empty($_SESSION['dat_csrf'])) {
            $_SESSION['dat_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['dat_csrf'];
    }
}

if (!function_exists('dat_csrf_field')) {
    function dat_csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . e(dat_csrf_token()) . '">';
    }
}

if (!function_exists('dat_csrf_verify')) {
    function dat_csrf_verify($token = null)
    {
        dat_session_start();
        $token = $token ?? ($_POST['_token'] ?? '');
        $expected = $_SESSION['dat_csrf'] ?? '';

        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }
}

if (!function_exists('dat_ip_hash')) {
    function dat_ip_hash()
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return null;
        }
        // Salted hash: enough to rate limit, not enough to reconstruct the IP.
        return hash('sha256', $ip . '|' . dat_env('PORTAL_IP_SALT', 'digital-asset-tag'));
    }
}

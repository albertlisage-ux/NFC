<?php
/**
 * Create or promote an administrator account.
 *
 *   php scripts/create-admin.php --email=you@example.com
 *   php scripts/create-admin.php --email=you@example.com --password='...'
 *   php scripts/create-admin.php --email=you@example.com --reset
 *   php scripts/create-admin.php --list
 *
 * The account signs in through the ordinary login form; the role is what opens
 * /admin/. Without --password the script generates one and prints it once.
 * Nothing anywhere can read a password back, so write it down or change it.
 *
 * The account is created only if the email is free, and an existing account is
 * promoted rather than duplicated, so this is safe to run twice.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/** Read --name=value and bare --name arguments. */
function admin_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (strpos($argument, '--') !== 0) {
            continue;
        }
        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }

    return $options;
}

/** A password that can be read aloud without ambiguity. */
function admin_generate_password(int $groups = 5, int $size = 4): string
{
    // No 0/O, 1/l/I: this gets typed by hand on a phone at least once.
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $last = strlen($alphabet) - 1;
    $parts = [];
    for ($group = 0; $group < $groups; $group++) {
        $part = '';
        for ($index = 0; $index < $size; $index++) {
            $part .= $alphabet[random_int(0, $last)];
        }
        $parts[] = $part;
    }

    return implode('-', $parts);
}

$options = admin_options($argv);

if (dat_db() === null) {
    fwrite(STDERR, 'Cannot reach the database. Check the .env in this directory.' . PHP_EOL);
    exit(1);
}

if (isset($options['list'])) {
    echo 'Accounts in ' . DB_NAME . ':' . PHP_EOL;
    foreach (dat_all('SELECT email, username, role, status, last_login_at FROM ' . dat_table('users') . ' ORDER BY created_at') as $row) {
        printf(
            '  %-32s %-16s %-6s status=%d last_login=%s%s',
            $row['email'],
            $row['username'],
            (string) $row['role'],
            (int) $row['status'],
            (string) ($row['last_login_at'] ?? 'never'),
            PHP_EOL
        );
    }
    exit(0);
}

$email = trim((string) ($options['email'] ?? ''));
if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, 'Give a real email address: --email=you@example.com' . PHP_EOL);
    exit(1);
}

$email = dat_normalize_email($email);
$existing = dat_user_by_email($email);
$given = isset($options['password']) && (string) $options['password'] !== '';
$replace = $given || isset($options['reset']);

// A password is only invented when one is actually going to be stored, so the
// line below is never printed for a password the account does not have.
$password = $given ? (string) $options['password'] : '';
$generated = false;
if (!$existing || $replace) {
    if ($password === '') {
        $password = admin_generate_password();
        $generated = true;
    }
    if (strlen($password) < 12) {
        fwrite(STDERR, 'Use a password of at least 12 characters.' . PHP_EOL);
        exit(1);
    }
}

$now = dat_now();

if ($existing !== null) {
    if ($replace) {
        dat_exec(
            'UPDATE ' . dat_table('users') . ' SET role = ?, password_hash = ?, updated_at = ? WHERE id = ?',
            [DAT_USER_ROLE_ADMIN, password_hash($password, PASSWORD_DEFAULT), $now, $existing['id']]
        );
        echo 'Promoted ' . $email . ' to administrator and set a new password.' . PHP_EOL;
    } else {
        dat_exec(
            'UPDATE ' . dat_table('users') . ' SET role = ?, updated_at = ? WHERE id = ?',
            [DAT_USER_ROLE_ADMIN, $now, $existing['id']]
        );
        echo 'Promoted ' . $email . ' to administrator.' . PHP_EOL;
        echo 'The existing password was kept. Add --reset to set a new one.' . PHP_EOL;
    }
} else {
    $registered = dat_register_user($email, $password, $password, 'Administrator');
    if (empty($registered['success'])) {
        fwrite(STDERR, 'Could not create the account: ' . implode('; ', (array) ($registered['errors'] ?? ['unknown error'])) . PHP_EOL);
        exit(1);
    }

    dat_exec(
        'UPDATE ' . dat_table('users') . ' SET role = ? WHERE id = ?',
        [DAT_USER_ROLE_ADMIN, $registered['user']['id']]
    );
    echo 'Created administrator ' . $email . ' (username ' . $registered['user']['username'] . ').' . PHP_EOL;
}

if ($generated) {
    echo PHP_EOL . '    password: ' . $password . PHP_EOL . PHP_EOL;
    echo 'This is the only time it is shown. Put it in a password manager.' . PHP_EOL;
}

echo 'Sign in at ' . dat_url('account/login.php') . ' and open ' . dat_url('admin/index.php') . PHP_EOL;

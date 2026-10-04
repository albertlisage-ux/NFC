<?php
/**
 * Guest sessions: create one tag before registering.
 *
 * A guest gets a real account row under the hood (email in the reserved
 * @guest.invalid domain), so assets, tags, QR codes and messages reuse the
 * normal code paths and the normal owner checks. When the visitor registers or
 * logs in, the assets are transferred to their account and the guest row is
 * removed. Nothing is claimed means the row expires and is deleted.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assets.php';

const DAT_GUEST_EMAIL_DOMAIN = 'guest.invalid';
const DAT_GUEST_TTL_DAYS = 7;
const DAT_GUEST_ASSET_LIMIT = 1;
const DAT_GUEST_IP_LIMIT = 3;

if (!function_exists('dat_is_guest_email')) {
    function dat_is_guest_email($email)
    {
        return is_string($email) && substr($email, -strlen('@' . DAT_GUEST_EMAIL_DOMAIN)) === '@' . DAT_GUEST_EMAIL_DOMAIN;
    }
}

if (!function_exists('dat_is_guest_user')) {
    function dat_is_guest_user($user)
    {
        return is_array($user) && dat_is_guest_email($user['email'] ?? '');
    }
}

/** Create the guest account row and session record for this visitor. */
if (!function_exists('dat_create_guest_session')) {
    function dat_create_guest_session()
    {
        if (dat_db() === null) {
            return ['success' => false, 'errors' => [t('error.db_unavailable', 'The service is temporarily unavailable. Please try again later.')]];
        }
        if (dat_guest_ip_limited()) {
            return ['success' => false, 'errors' => [t('error.guest_limited', 'Several guest tags were already created from this connection. Please create an account to continue.')]];
        }

        $userId = dat_uuid();
        $now = dat_now();
        $email = 'guest-' . bin2hex(random_bytes(8)) . '@' . DAT_GUEST_EMAIL_DOMAIN;

        $created = dat_exec(
            'INSERT INTO ' . dat_table('users') . '
                (id, email, username, display_name, password_hash, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $email,
                'guest-' . substr(bin2hex(random_bytes(5)), 0, 8),
                t('guest.display_name', 'Guest'),
                // Guest rows are never able to sign in.
                password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                DAT_USER_STATUS_ACTIVE,
                $now,
                $now,
            ]
        );

        if ($created < 0) {
            return ['success' => false, 'errors' => [t('error.guest_failed', 'The guest session could not be created. Please try again.')]];
        }

        dat_exec(
            'INSERT INTO ' . dat_table('guest_sessions') . ' (id, user_id, ip_hash, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?)',
            [
                dat_uuid(),
                $userId,
                dat_ip_hash(),
                $now,
                date('Y-m-d H:i:s', time() + DAT_GUEST_TTL_DAYS * 86400),
            ]
        );

        dat_session_start();
        $_SESSION['dat_guest_user_id'] = $userId;

        return ['success' => true, 'user' => dat_user_by_id($userId)];
    }
}

/** Throttle guest creation per IP address. */
if (!function_exists('dat_guest_ip_limited')) {
    function dat_guest_ip_limited()
    {
        if (PORTAL_DEMO_MODE) {
            return false;
        }

        $ipHash = dat_ip_hash();
        if ($ipHash === null) {
            return false;
        }

        $row = dat_one(
            'SELECT COUNT(*) AS hits FROM ' . dat_table('guest_sessions') . '
              WHERE ip_hash = ? AND created_at > ?',
            [$ipHash, date('Y-m-d H:i:s', time() - 3600)]
        );

        return (int) ($row['hits'] ?? 0) >= DAT_GUEST_IP_LIMIT;
    }
}

/**
 * The guest account of the current visitor, if any.
 */
if (!function_exists('dat_guest_user')) {
    function dat_guest_user()
    {
        dat_session_start();
        $id = $_SESSION['dat_guest_user_id'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }

        $user = dat_user_by_id($id);
        if ($user === null || !dat_is_guest_user($user)) {
            unset($_SESSION['dat_guest_user_id']);
            return null;
        }

        $session = dat_one(
            'SELECT * FROM ' . dat_table('guest_sessions') . ' WHERE user_id = ? LIMIT 1',
            [$id]
        );
        if ($session === null) {
            return $user;
        }
        if (strtotime($session['expires_at']) < time()) {
            unset($_SESSION['dat_guest_user_id']);
            return null;
        }

        return $user;
    }
}

/** Assets created in the current guest session. */
if (!function_exists('dat_guest_assets')) {
    function dat_guest_assets()
    {
        $guest = dat_guest_user();
        return $guest === null ? [] : dat_assets_for_owner($guest['id']);
    }
}

if (!function_exists('dat_guest_can_create')) {
    function dat_guest_can_create()
    {
        $guest = dat_guest_user();
        if ($guest === null) {
            return true;
        }

        return count(dat_guest_assets()) < DAT_GUEST_ASSET_LIMIT;
    }
}

/**
 * Hand the guest assets over to a real account. Called after registration and
 * after login, so a tag created as a guest ends up in the right dashboard.
 *
 * @return int number of transferred assets
 */
if (!function_exists('dat_claim_guest_assets')) {
    function dat_claim_guest_assets($userId)
    {
        $guest = dat_guest_user();
        if ($guest === null || $guest['id'] === $userId) {
            return 0;
        }

        $transferred = dat_exec(
            'UPDATE ' . dat_table('assets') . ' SET owner_id = ?, updated_at = ? WHERE owner_id = ?',
            [$userId, dat_now(), $guest['id']]
        );

        if ($transferred < 0) {
            dat_log_error('Guest asset transfer failed', ['guest' => $guest['id']]);
            return 0;
        }

        // Removing the guest account also removes its guest_sessions row.
        dat_exec('DELETE FROM ' . dat_table('users') . ' WHERE id = ?', [$guest['id']]);

        dat_session_start();
        unset($_SESSION['dat_guest_user_id']);

        return $transferred;
    }
}

/**
 * Delete guest accounts that were never claimed. Their assets cascade away
 * with them, which is the documented retention for the guest path.
 */
if (!function_exists('dat_purge_guest_sessions')) {
    function dat_purge_guest_sessions()
    {
        $expired = dat_all(
            'SELECT user_id FROM ' . dat_table('guest_sessions') . '
              WHERE expires_at < ?',
            [dat_now()]
        );

        $removed = 0;
        foreach ($expired as $row) {
            $user = dat_user_by_id($row['user_id']);
            if ($user !== null && dat_is_guest_user($user)) {
                dat_exec('DELETE FROM ' . dat_table('users') . ' WHERE id = ?', [$row['user_id']]);
                $removed++;
            }
        }

        return $removed;
    }
}

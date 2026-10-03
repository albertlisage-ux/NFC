<?php
/**
 * Anonymous finder messages and owner replies.
 *
 * A finder never creates an account. They receive a random sender token which
 * is the only key to their thread, so the owner can answer without either side
 * learning the other's contact details.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assets.php';

const DAT_MESSAGE_STATUS_NEW      = 1;
const DAT_MESSAGE_STATUS_READ     = 2;
const DAT_MESSAGE_STATUS_REPLIED  = 3;
const DAT_MESSAGE_STATUS_ARCHIVED = 4;
const DAT_MESSAGE_STATUS_EXPIRED  = 5;

const DAT_MESSAGE_MAX_LENGTH = 1000;

/** Token that identifies a finder thread, stored in the finder's session. */
if (!function_exists('dat_sender_token')) {
    function dat_sender_token($create = true)
    {
        dat_session_start();

        if (!empty($_SESSION['dat_sender_token'])) {
            return $_SESSION['dat_sender_token'];
        }
        if (!$create) {
            return null;
        }

        $_SESSION['dat_sender_token'] = bin2hex(random_bytes(16));
        return $_SESSION['dat_sender_token'];
    }
}

/** Anonymous rate limiting: N messages per IP hash per asset per hour. */
if (!function_exists('dat_message_rate_limited')) {
    function dat_message_rate_limited($assetId)
    {
        if (PORTAL_DEMO_MODE) {
            return false;
        }

        $ipHash = dat_ip_hash();
        if ($ipHash === null) {
            return false;
        }

        $row = dat_one(
            'SELECT COUNT(*) AS hits FROM ' . dat_table('message_rate') . '
              WHERE ip_hash = ? AND asset_id = ? AND created_at > ?',
            [$ipHash, $assetId, date('Y-m-d H:i:s', time() - 3600)]
        );

        return (int) ($row['hits'] ?? 0) >= PORTAL_MESSAGE_RATE_LIMIT;
    }
}

if (!function_exists('dat_message_rate_register')) {
    function dat_message_rate_register($assetId)
    {
        dat_query(
            'INSERT INTO ' . dat_table('message_rate') . ' (ip_hash, asset_id, created_at) VALUES (?, ?, ?)',
            [dat_ip_hash(), $assetId, dat_now()]
        );
    }
}

/**
 * Store a finder message.
 *
 * @return array{success: bool, token?: string, errors?: string[]}
 */
if (!function_exists('dat_create_finder_message')) {
    function dat_create_finder_message(array $asset, $content)
    {
        $content = trim((string) $content);
        $errors = [];

        if ($content === '') {
            $errors[] = t('error.message_empty', 'Please write a short message.');
        }
        if (mb_strlen($content) > DAT_MESSAGE_MAX_LENGTH) {
            $errors[] = t('error.message_long', 'Please keep the message under 1000 characters.');
        }
        if (dat_db() === null) {
            $errors[] = t('error.db_unavailable', 'The service is temporarily unavailable. Please try again later.');
        }
        if (!$errors && dat_message_rate_limited($asset['id'])) {
            $errors[] = t('error.rate_limited', 'You have sent several messages already. Please try again later.');
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $token = bin2hex(random_bytes(16));
        $messageId = dat_uuid();
        $expiresAt = date('Y-m-d H:i:s', time() + (PORTAL_MESSAGE_RETENTION_DAYS * 86400));

        $inserted = dat_exec(
            'INSERT INTO ' . dat_table('messages') . '
                (id, asset_id, sender_token, direction, content, status, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $messageId,
                $asset['id'],
                $token,
                DAT_MESSAGE_DIRECTION_FINDER,
                $content,
                DAT_MESSAGE_STATUS_NEW,
                dat_now(),
                $expiresAt,
            ]
        );

        if ($inserted < 0) {
            return ['success' => false, 'errors' => [t('error.message_failed', 'The message could not be delivered. Please try again.')]];
        }

        dat_message_rate_register($asset['id']);
        dat_session_start();
        $_SESSION['dat_sender_token'] = $token;

        return ['success' => true, 'token' => $token];
    }
}

if (!function_exists('dat_message_thread')) {
    function dat_message_thread($assetId, $token, $publicId = null)
    {
        if (!is_string($token) || $token === '') {
            return [];
        }
        if ($publicId === null) {
            $asset = dat_one('SELECT * FROM ' . dat_table('assets') . ' WHERE id = ? LIMIT 1', [$assetId]);
            $publicId = $asset['public_id'] ?? null;
        }

        $asset = dat_one('SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1', [$publicId]);
        if ($asset === null || $asset['id'] !== $assetId) {
            return [];
        }

        return dat_all(
            'SELECT * FROM ' . dat_table('messages') . '
              WHERE asset_id = ? AND (sender_token = ? OR reply_to_id IN (
                    SELECT id FROM ' . dat_table('messages') . ' WHERE asset_id = ? AND sender_token = ?
              ))
              ORDER BY created_at ASC',
            [$assetId, $token, $assetId, $token]
        );
    }
}

/** Owner inbox: one row per finder thread, newest activity first. */
if (!function_exists('dat_message_threads_for_owner')) {
    function dat_message_threads_for_owner($ownerId)
    {
        $threads = dat_all(
            'SELECT
                m.asset_id,
                m.sender_token,
                a.public_id,
                a.name AS asset_name,
                a.type AS asset_type,
                a.status AS asset_status,
                MAX(m.created_at) AS last_at,
                COUNT(*) AS message_count,
                SUM(CASE WHEN m.direction = ? AND m.status = ? THEN 1 ELSE 0 END) AS unread_count
             FROM ' . dat_table('messages') . ' m
               JOIN ' . dat_table('assets') . ' a ON a.id = m.asset_id
              WHERE a.owner_id = ? AND a.status <> ? AND m.status <> ?
              GROUP BY m.asset_id, m.sender_token, a.public_id, a.name, a.type, a.status
              ORDER BY last_at DESC',
            [
                DAT_MESSAGE_DIRECTION_FINDER,
                DAT_MESSAGE_STATUS_NEW,
                $ownerId,
                DAT_ASSET_STATUS_DELETED,
                DAT_MESSAGE_STATUS_ARCHIVED,
            ]
        );

        // Latest message per thread, used as the inbox preview line.
        foreach ($threads as $index => $thread) {
            $last = dat_one(
                'SELECT content FROM ' . dat_table('messages') . '
                  WHERE asset_id = ? AND sender_token = ?
                  ORDER BY created_at DESC LIMIT 1',
                [$thread['asset_id'], $thread['sender_token']]
            );
            $threads[$index]['preview'] = (string) ($last['content'] ?? '');
        }

        return $threads;
    }
}

if (!function_exists('dat_message_thread_messages')) {
    function dat_message_thread_messages($assetId, $token, $ownerId)
    {
        $asset = dat_asset_for_owner($assetId, $ownerId);
        if ($asset === null) {
            return [];
        }

        $messages = dat_all(
            'SELECT * FROM ' . dat_table('messages') . '
              WHERE asset_id = ? AND (sender_token = ? OR reply_to_id IN (
                    SELECT id FROM ' . dat_table('messages') . ' WHERE asset_id = ? AND sender_token = ?
              ))
              ORDER BY created_at ASC',
            [$assetId, $token, $assetId, $token]
        );

        // Opening the thread marks the finder messages as read.
        dat_exec(
            'UPDATE ' . dat_table('messages') . '
                SET status = ?
              WHERE asset_id = ? AND sender_token = ? AND direction = ? AND status = ?',
            [DAT_MESSAGE_STATUS_READ, $assetId, $token, DAT_MESSAGE_DIRECTION_FINDER, DAT_MESSAGE_STATUS_NEW]
        );

        return $messages;
    }
}

/**
 * Owner replies inside a finder thread.
 */
if (!function_exists('dat_owner_reply')) {
    function dat_owner_reply($assetId, $token, $ownerId, $content)
    {
        $content = trim((string) $content);
        if ($content === '' || mb_strlen($content) > DAT_MESSAGE_MAX_LENGTH) {
            return ['success' => false, 'errors' => [t('error.message_empty', 'Please write a short message.')]];
        }

        $asset = dat_asset_for_owner($assetId, $ownerId);
        if ($asset === null) {
            return ['success' => false, 'errors' => [t('error.forbidden', 'This action is not allowed.')]];
        }

        $parent = dat_one(
            'SELECT * FROM ' . dat_table('messages') . '
              WHERE asset_id = ? AND sender_token = ? AND direction = ?
              ORDER BY created_at DESC LIMIT 1',
            [$assetId, $token, DAT_MESSAGE_DIRECTION_FINDER]
        );

        if ($parent === null) {
            return ['success' => false, 'errors' => [t('error.message_missing', 'That conversation no longer exists.')]];
        }

        $inserted = dat_exec(
            'INSERT INTO ' . dat_table('messages') . '
                (id, asset_id, sender_token, direction, reply_to_id, content, status, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                dat_uuid(),
                $assetId,
                $token,
                DAT_MESSAGE_DIRECTION_OWNER,
                $parent['id'],
                $content,
                DAT_MESSAGE_STATUS_NEW,
                dat_now(),
                $parent['expires_at'],
            ]
        );

        if ($inserted < 0) {
            return ['success' => false, 'errors' => [t('error.message_failed', 'The message could not be delivered. Please try again.')]];
        }

        dat_exec(
            'UPDATE ' . dat_table('messages') . '
                SET status = ?
              WHERE asset_id = ? AND sender_token = ? AND direction = ?',
            [DAT_MESSAGE_STATUS_REPLIED, $assetId, $token, DAT_MESSAGE_DIRECTION_FINDER]
        );

        return ['success' => true];
    }
}

if (!function_exists('dat_archive_thread')) {
    function dat_archive_thread($assetId, $token, $ownerId)
    {
        $asset = dat_asset_for_owner($assetId, $ownerId);
        if ($asset === null) {
            return false;
        }

        return dat_exec(
            'UPDATE ' . dat_table('messages') . '
                SET status = ?
              WHERE asset_id = ? AND sender_token = ?',
            [DAT_MESSAGE_STATUS_ARCHIVED, $assetId, $token]
        ) >= 0;
    }
}

if (!function_exists('dat_unread_message_count')) {
    function dat_unread_message_count($ownerId)
    {
        $row = dat_one(
            'SELECT COUNT(*) AS unread
               FROM ' . dat_table('messages') . ' m
               JOIN ' . dat_table('assets') . ' a ON a.id = m.asset_id
              WHERE a.owner_id = ? AND m.direction = ? AND m.status = ?',
            [$ownerId, DAT_MESSAGE_DIRECTION_FINDER, DAT_MESSAGE_STATUS_NEW]
        );

        return (int) ($row['unread'] ?? 0);
    }
}

/** Retention: messages past their expiry are removed with their replies. */
if (!function_exists('dat_purge_expired_messages')) {
    function dat_purge_expired_messages()
    {
        dat_exec(
            'DELETE FROM ' . dat_table('messages') . ' WHERE expires_at IS NOT NULL AND expires_at < ?',
            [dat_now()]
        );
    }
}

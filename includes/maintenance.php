<?php
/**
 * Housekeeping.
 *
 * The privacy page promises that anonymous messages expire, that guest tags are
 * removed and that the security log does not grow forever. This is the code that
 * actually does it, as cheap indexed deletes on a small share of requests. With
 * a scheduler available, call dat_run_maintenance() from cron and the random
 * gate below never has to fire.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/guest.php';

const DAT_LOGIN_LOG_RETENTION_DAYS = 90;
const DAT_RATE_ROW_RETENTION_HOURS = 24;

/** Drop rate-limit rows that are older than the window they protect. */
if (!function_exists('dat_prune_rate_rows')) {
    function dat_prune_rate_rows()
    {
        return dat_exec(
            'DELETE FROM ' . dat_table('message_rate') . ' WHERE created_at < ?',
            [date('Y-m-d H:i:s', time() - DAT_RATE_ROW_RETENTION_HOURS * 3600)]
        );
    }
}

/** Keep the login audit log bounded. */
if (!function_exists('dat_prune_login_logs')) {
    function dat_prune_login_logs()
    {
        return dat_exec(
            'DELETE FROM ' . dat_table('login_logs') . ' WHERE created_at < ?',
            [date('Y-m-d H:i:s', time() - DAT_LOGIN_LOG_RETENTION_DAYS * 86400)]
        );
    }
}

/**
 * One housekeeping pass. Returns what was removed, which the tests check.
 *
 * @return array{messages: int, guests: int, rate_rows: int, login_logs: int}
 */
if (!function_exists('dat_run_maintenance')) {
    function dat_run_maintenance()
    {
        if (!dat_db_available()) {
            return ['messages' => 0, 'guests' => 0, 'rate_rows' => 0, 'login_logs' => 0];
        }

        return [
            'messages' => max(0, (int) dat_purge_expired_messages()),
            'guests' => max(0, (int) dat_purge_guest_sessions()),
            'rate_rows' => max(0, (int) dat_prune_rate_rows()),
            'login_logs' => max(0, (int) dat_prune_login_logs()),
        ];
    }
}

/**
 * Run the pass on roughly one request in twenty: no cron needed, and no single
 * visitor pays for the work.
 */
if (!function_exists('dat_maintenance_opportunistic')) {
    function dat_maintenance_opportunistic()
    {
        if (PHP_SAPI === 'cli' || random_int(1, 20) !== 1) {
            return;
        }

        dat_run_maintenance();
    }
}

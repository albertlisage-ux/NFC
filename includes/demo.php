<?php
/**
 * Data for the trade fair walkthrough on /demo.
 *
 * Everything here is read-only: the page shows the real demo asset, the real
 * QR code and the real messages that arrived for it, so nothing on screen is a
 * mockup.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/catalog.php';

/** The product the walkthrough follows from scan to reply. */
if (!function_exists('dat_demo_showcase_key')) {
    function dat_demo_showcase_key()
    {
        // The lost-and-found chain only makes sense on the one product people
        // actually lose, so the walkthrough follows the pet tag.
        return 'pet';
    }
}

if (!function_exists('dat_demo_showcase_entry')) {
    function dat_demo_showcase_entry()
    {
        return dat_demo_entry(dat_demo_showcase_key());
    }
}

/** The live asset row, or null when the demo data has not been seeded. */
if (!function_exists('dat_demo_showcase_asset')) {
    function dat_demo_showcase_asset()
    {
        $entry = dat_demo_showcase_entry();
        return $entry === null ? null : dat_asset_by_public_id($entry['public_id']);
    }
}

/**
 * The product the "add or edit information" step follows.
 *
 * The opposite story to a lost pet: nothing has gone missing, the owner just
 * changes what the page says, and the printed code keeps working.
 */
if (!function_exists('dat_demo_information_key')) {
    function dat_demo_information_key()
    {
        return 'menu_board';
    }
}

if (!function_exists('dat_demo_information_entry')) {
    function dat_demo_information_entry()
    {
        return dat_demo_entry(dat_demo_information_key());
    }
}

/**
 * Recent messages that arrived for one public ID, newest first.
 *
 * Read-only and stripped down: the sender token stays in the database, only
 * the text, the direction and the time are returned.
 *
 * @return array<int, array{direction: int, content: string, created_at: string}>
 */
if (!function_exists('dat_demo_recent_messages')) {
    function dat_demo_recent_messages($publicId, $limit = 4)
    {
        $limit = max(1, min(20, (int) $limit));

        $rows = dat_all(
            'SELECT m.direction, m.content, m.created_at
               FROM ' . dat_table('messages') . ' m
               JOIN ' . dat_table('assets') . ' a ON a.id = m.asset_id
              WHERE a.public_id = ?
              ORDER BY m.created_at DESC
              LIMIT ' . $limit,
            [strtoupper(trim((string) $publicId))]
        );

        return $rows;
    }
}

/** How many finder messages the demo asset has received so far. */
if (!function_exists('dat_demo_message_count')) {
    function dat_demo_message_count($publicId)
    {
        $row = dat_one(
            'SELECT COUNT(*) AS total
               FROM ' . dat_table('messages') . ' m
               JOIN ' . dat_table('assets') . ' a ON a.id = m.asset_id
              WHERE a.public_id = ?',
            [strtoupper(trim((string) $publicId))]
        );

        return (int) ($row['total'] ?? 0);
    }
}

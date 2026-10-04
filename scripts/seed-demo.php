<?php
/**
 * Creates the demo owner and one worked example per asset type, using the
 * fixed public IDs from includes/catalog.php. The example blocks on the
 * use-cases page link to these live tag pages, so the illustrations and the
 * running portal always show the same data.
 *
 *   php scripts/seed-demo.php
 *   php scripts/seed-demo.php --email=you@example.com --move
 *
 * Safe to run repeatedly: existing rows are reused. `--email` seeds into an
 * account that already exists instead of the demo one, and `--move` hands the
 * existing examples over to it, which is how the catalogue examples end up in
 * the dashboard of the account that actually runs the shop.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assets.php';
require_once __DIR__ . '/../includes/nfc.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/demo.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

const DEMO_EMAIL = 'demo@example.com';
const DEMO_PASSWORD = 'DemoTag2026!';

$options = [];
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (strpos($argument, '--') === 0) {
        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

if (dat_db() === null) {
    fwrite(STDERR, 'Cannot connect to ' . DB_NAME . ' at ' . DB_HOST . PHP_EOL);
    exit(1);
}

$targetEmail = trim((string) ($options['email'] ?? DEMO_EMAIL));
$move = isset($options['move']);
$user = dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE email = ? LIMIT 1', [$targetEmail]);

if ($user === null && $targetEmail !== DEMO_EMAIL) {
    fwrite(STDERR, 'No account with the email ' . $targetEmail . '. Create it first.' . PHP_EOL);
    exit(1);
}

if ($user === null) {
    $userId = dat_uuid();
    dat_exec(
        'INSERT INTO ' . dat_table('users') . '
            (id, email, username, display_name, password_hash, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
        [$userId, DEMO_EMAIL, 'demo', 'Demo owner', password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), dat_now(), dat_now()]
    );
    $user = dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE email = ? LIMIT 1', [DEMO_EMAIL]);
    echo 'Created demo user ' . DEMO_EMAIL . PHP_EOL;
}

$created = 0;
$reused = 0;
$moved = 0;

/*
 * --move hands the existing examples to the chosen account. The tag pages,
 * the QR codes and the public demo all read an asset by its public ID, so
 * moving one changes who manages it and nothing else.
 */
if ($move) {
    foreach (array_column(dat_demo_catalog(), 'public_id') as $publicId) {
        $row = dat_one(
            'SELECT id, owner_id FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1',
            [$publicId]
        );
        if ($row === null || $row['owner_id'] === $user['id']) {
            continue;
        }
        dat_exec(
            'UPDATE ' . dat_table('assets') . ' SET owner_id = ?, updated_at = ? WHERE id = ?',
            [$user['id'], dat_now(), $row['id']]
        );
        $moved++;
    }
    if ($moved > 0) {
        echo 'Moved ' . $moved . ' example(s) to ' . $targetEmail . PHP_EOL;
    }
}

// Drop demo assets whose catalogue entry no longer exists (for example the
// retired bicycle, vehicle, item and industrial entries), so the demo account
// always reflects the current catalogue.
$validIds = array_column(dat_demo_catalog(), 'public_id');
foreach (dat_all('SELECT id, public_id, name FROM ' . dat_table('assets') . ' WHERE owner_id = ?', [$user['id']]) as $row) {
    if (!in_array($row['public_id'], $validIds, true)) {
        dat_exec('DELETE FROM ' . dat_table('assets') . ' WHERE id = ?', [$row['id']]);
        echo 'Removed retired demo asset ' . $row['public_id'] . ' (' . $row['name'] . ')' . PHP_EOL;
    }
}

foreach (dat_demo_catalog() as $entry) {
    $existing = dat_one(
        'SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1',
        [$entry['public_id']]
    );

    if ($existing === null) {
        $assetId = dat_uuid();
        $now = dat_now();
        dat_exec(
            'INSERT INTO ' . dat_table('assets') . '
                (id, public_id, owner_id, type, name, description, status, metadata_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $assetId,
                $entry['public_id'],
                $user['id'],
                $entry['type'],
                $entry['name'],
                $entry['description'],
                DAT_ASSET_STATUS_ACTIVE,
                json_encode(
                    dat_sanitize_metadata($entry['type'], $entry['metadata']),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                $now,
                $now,
            ]
        );
        dat_create_tag($assetId, DAT_TAG_TYPE_QR, dat_asset_type_label($entry['type']) . ' QR');
        dat_create_tag($assetId, DAT_TAG_TYPE_NFC, dat_asset_type_label($entry['type']) . ' NFC');
        $created++;
        echo 'Created demo asset ' . $entry['public_id'] . ' (' . $entry['name'] . ')' . PHP_EOL;
        continue;
    }

    // Keep the illustration in step with the catalog without touching the ID.
    $reused++;
    dat_exec(
        'UPDATE ' . dat_table('assets') . '
            SET name = ?, description = ?, type = ?, metadata_json = ?, updated_at = ?
          WHERE id = ?',
        [
            $entry['name'],
            $entry['description'],
            $entry['type'],
            json_encode(
                dat_sanitize_metadata($entry['type'], $entry['metadata']),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            dat_now(),
            $existing['id'],
        ]
    );
    dat_sync_default_tags($existing['id'], $user['id']);
}

echo PHP_EOL . 'Created: ' . $created . ', refreshed: ' . $reused . PHP_EOL;

/*
 * One realistic conversation on the showcase product, so the trade fair
 * walkthrough has something to show before the first visitor types. Only
 * added when the thread does not exist yet.
 */
$showcase = dat_demo_entry('keychain');
if ($showcase !== null && dat_db_available()) {
    $asset = dat_one(
        'SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1',
        [$showcase['public_id']]
    );
    if ($asset !== null) {
        $expiresAt = date('Y-m-d H:i:s', time() + (PORTAL_MESSAGE_RETENTION_DAYS * 86400));
        $finderMessage = dat_one(
            'SELECT id, sender_token FROM ' . dat_table('messages') . '
              WHERE asset_id = ? AND direction = ? ORDER BY created_at ASC LIMIT 1',
            [$asset['id'], DAT_MESSAGE_DIRECTION_FINDER]
        );

        if ($finderMessage === null) {
            $finderMessage = ['id' => dat_uuid(), 'sender_token' => bin2hex(random_bytes(16))];
            dat_exec(
                'INSERT INTO ' . dat_table('messages') . '
                    (id, asset_id, sender_token, direction, content, status, created_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $finderMessage['id'],
                    $asset['id'],
                    $finderMessage['sender_token'],
                    DAT_MESSAGE_DIRECTION_FINDER,
                    'I found a set of keys with this keychain in the doorway of house 4. The caretaker took them in, he is there until 6 pm.',
                    DAT_MESSAGE_STATUS_READ,
                    date('Y-m-d H:i:s', time() - 5400),
                    $expiresAt,
                ]
            );
            echo 'Seeded a finder message on ' . $showcase['public_id'] . PHP_EOL;
        }

        $ownerMessage = dat_one(
            'SELECT id FROM ' . dat_table('messages') . '
              WHERE asset_id = ? AND direction = ? LIMIT 1',
            [$asset['id'], DAT_MESSAGE_DIRECTION_OWNER]
        );

        if ($ownerMessage === null) {
            dat_exec(
                'INSERT INTO ' . dat_table('messages') . '
                    (id, asset_id, sender_token, direction, reply_to_id, content, status, created_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    dat_uuid(),
                    $asset['id'],
                    $finderMessage['sender_token'],
                    DAT_MESSAGE_DIRECTION_OWNER,
                    $finderMessage['id'],
                    'Thank you, I will collect them after work. Next coffee at the corner café is on me.',
                    DAT_MESSAGE_STATUS_READ,
                    date('Y-m-d H:i:s', time() - 4800),
                    $expiresAt,
                ]
            );
            dat_exec(
                'UPDATE ' . dat_table('messages') . ' SET status = ? WHERE asset_id = ? AND direction = ?',
                [DAT_MESSAGE_STATUS_REPLIED, $asset['id'], DAT_MESSAGE_DIRECTION_FINDER]
            );
            echo 'Seeded the owner reply on ' . $showcase['public_id'] . PHP_EOL;
        }
    }
}

echo 'Login: ' . DEMO_EMAIL . ' / ' . DEMO_PASSWORD . PHP_EOL;
echo 'Tag pages:' . PHP_EOL;
foreach (dat_demo_catalog() as $entry) {
    echo '  ' . dat_asset_type_label($entry['type']) . ': ' . dat_tag_url($entry['public_id']) . PHP_EOL;
}

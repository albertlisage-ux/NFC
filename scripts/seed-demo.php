<?php
/**
 * Creates the demo owner and one worked example per asset type, using the
 * fixed public IDs from includes/catalog.php. The example blocks on the
 * use-cases page link to these live tag pages, so the illustrations and the
 * running portal always show the same data.
 *
 *   php scripts/seed-demo.php
 *
 * Safe to run repeatedly: existing rows are reused.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assets.php';
require_once __DIR__ . '/../includes/nfc.php';
require_once __DIR__ . '/../includes/catalog.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

const DEMO_EMAIL = 'demo@example.com';
const DEMO_PASSWORD = 'DemoTag2026!';

if (dat_db() === null) {
    fwrite(STDERR, 'Cannot connect to ' . DB_NAME . ' at ' . DB_HOST . PHP_EOL);
    exit(1);
}

$user = dat_one('SELECT * FROM ' . dat_table('users') . ' WHERE email = ? LIMIT 1', [DEMO_EMAIL]);
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
echo 'Login: ' . DEMO_EMAIL . ' / ' . DEMO_PASSWORD . PHP_EOL;
echo 'Tag pages:' . PHP_EOL;
foreach (dat_demo_catalog() as $entry) {
    echo '  ' . dat_asset_type_label($entry['type']) . ': ' . dat_tag_url($entry['public_id']) . PHP_EOL;
}

<?php
/**
 * Creates a demo owner and one demo asset with the fixed public ID DEMTAG24,
 * so the QR code on the home page really opens a working tag page.
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

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

const DEMO_EMAIL = 'demo@example.com';
const DEMO_PASSWORD = 'DemoTag2026!';
const DEMO_PUBLIC_ID = 'DEMTAG24';

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

$asset = dat_one('SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1', [DEMO_PUBLIC_ID]);
if ($asset === null) {
    $assetId = dat_uuid();
    $now = dat_now();
    dat_exec(
        'INSERT INTO ' . dat_table('assets') . '
            (id, public_id, owner_id, type, name, description, status, metadata_json, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [
            $assetId,
            DEMO_PUBLIC_ID,
            $user['id'],
            DAT_ASSET_TYPE_PET,
            'Lucky',
            'Friendly dog, chipped, speaks German and English. If you found him, a message is enough.',
            json_encode([
                'animal' => 'Dog',
                'breed' => 'Golden Retriever',
                'color' => 'Golden',
                'gender' => 'Male',
            ], JSON_UNESCAPED_UNICODE),
            $now,
            $now,
        ]
    );
    dat_create_tag($assetId, DAT_TAG_TYPE_QR, 'Collar QR');
    dat_create_tag($assetId, DAT_TAG_TYPE_NFC, 'Collar NFC');
    $asset = dat_one('SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1', [DEMO_PUBLIC_ID]);
    echo 'Created demo asset ' . DEMO_PUBLIC_ID . PHP_EOL;
}

echo 'Login: ' . DEMO_EMAIL . ' / ' . DEMO_PASSWORD . PHP_EOL;
echo 'Tag page: ' . dat_tag_url($asset['public_id']) . PHP_EOL;

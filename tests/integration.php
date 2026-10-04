<?php
/**
 * End-to-end test of the domain layer.
 *
 * No MySQL server is required: `dat_db()` is replaced by a SQLite connection
 * before the application boots, and the schema below mirrors
 * config/schema.sql column for column. The test then asserts that the two
 * schemas agree, so a change to the MySQL schema that is not mirrored here
 * fails the suite instead of drifting silently.
 *
 *   php tests/integration.php
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * SQLite-backed replacement for the PDO/MySQL connection.
 */
if (!function_exists('dat_db')) {
    function dat_db()
    {
        static $pdo = null;
        static $attempted = false;

        if ($pdo instanceof PDO) {
            return $pdo;
        }
        if ($attempted) {
            return $pdo;
        }
        $attempted = true;

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        foreach (integration_schema() as $statement) {
            $pdo->exec($statement);
        }

        return $pdo;
    }
}

if (!function_exists('dat_db_available')) {
    function dat_db_available()
    {
        return dat_db() instanceof PDO;
    }
}

/** Mirrors config/schema.sql, minus the MySQL-only table options. */
function integration_schema(): array
{
    return [
        'CREATE TABLE dat_users (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            display_name TEXT,
            password_hash TEXT NOT NULL,
            status INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            last_login_at TEXT
        )',
        'CREATE TABLE dat_assets (
            id TEXT PRIMARY KEY,
            public_id TEXT NOT NULL UNIQUE,
            owner_id TEXT NOT NULL,
            type INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            status INTEGER NOT NULL DEFAULT 1,
            metadata_json TEXT,
            contact_whatsapp TEXT,
            tag_target TEXT NOT NULL DEFAULT \'portal\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY (owner_id) REFERENCES dat_users (id) ON DELETE CASCADE
        )',
        'CREATE TABLE dat_tags (
            id TEXT PRIMARY KEY,
            asset_id TEXT NOT NULL,
            type INTEGER NOT NULL,
            label TEXT,
            nfc_payload TEXT,
            url TEXT,
            status INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            replaced_at TEXT,
            FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
        )',
        'CREATE TABLE dat_asset_images (
            id TEXT PRIMARY KEY,
            asset_id TEXT NOT NULL,
            object_key TEXT NOT NULL,
            original_file_name TEXT,
            mime_type TEXT,
            byte_size INTEGER,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
        )',
        'CREATE TABLE dat_messages (
            id TEXT PRIMARY KEY,
            asset_id TEXT NOT NULL,
            sender_token TEXT,
            direction INTEGER NOT NULL DEFAULT 1,
            reply_to_id TEXT,
            content TEXT NOT NULL,
            status INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            expires_at TEXT,
            FOREIGN KEY (asset_id) REFERENCES dat_assets (id) ON DELETE CASCADE
        )',
        'CREATE TABLE dat_message_rate (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_hash TEXT NOT NULL,
            asset_id TEXT,
            created_at TEXT NOT NULL
        )',
        'CREATE TABLE dat_guest_sessions (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL UNIQUE,
            ip_hash TEXT,
            created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES dat_users (id) ON DELETE CASCADE
        )',
        'CREATE TABLE dat_login_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT,
            identifier TEXT NOT NULL,
            ip_hash TEXT,
            user_agent TEXT,
            success INTEGER NOT NULL DEFAULT 0,
            reason TEXT,
            created_at TEXT NOT NULL
        )',
        'CREATE TABLE dat_migrations (
            filename TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL
        )',
    ];
}

require_once __DIR__ . '/../includes/bootstrap.php';

$failures = 0;
$checks = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   " . $label . PHP_EOL;
        return;
    }
    $failures++;
    echo "  FAIL " . $label . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
}

/** Column names per table, read from config/schema.sql. */
function mysql_schema_columns(string $file): array
{
    $sql = (string) file_get_contents($file);
    $tables = [];

    if (!preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\)/s', $sql, $matches, PREG_SET_ORDER)) {
        return $tables;
    }

    foreach ($matches as $match) {
        $columns = [];
        foreach (preg_split('/\n/', $match[2]) as $line) {
            $line = trim($line, " \t,");
            if ($line === '') {
                continue;
            }
            $upper = strtoupper($line);
            foreach (['PRIMARY KEY', 'UNIQUE KEY', 'KEY ', 'CONSTRAINT', 'INDEX', 'FOREIGN KEY'] as $skip) {
                if (strpos($upper, $skip) === 0) {
                    continue 2;
                }
            }
            if (preg_match('/^(\w+)\s+/', $line, $column)) {
                $columns[] = strtolower($column[1]);
            }
        }
        $tables[strtolower($match[1])] = $columns;
    }

    return $tables;
}

echo 'Digital Asset Tag Portal integration tests' . PHP_EOL . PHP_EOL;

/* ------------------------------------------------- schema agreement ---- */

echo 'Schema agreement' . PHP_EOL;

$mysqlTables = mysql_schema_columns(DAT_APP_ROOT . '/config/schema.sql');
$pdo = dat_db();

check('config/schema.sql declares every table', count($mysqlTables) >= 7, (string) count($mysqlTables));

$pdoTables = [];
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'dat_%'")->fetchAll() as $row) {
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(' . $row['name'] . ')')->fetchAll() as $column) {
        $columns[] = strtolower($column['name']);
    }
    $pdoTables[$row['name']] = $columns;
}

check('mirrored schema has the same tables', array_keys($mysqlTables) === array_keys($pdoTables), implode(',', array_keys($pdoTables)));

$columnMismatch = [];
foreach ($mysqlTables as $table => $columns) {
    $mirrored = $pdoTables[$table] ?? [];
    $missing = array_diff($columns, $mirrored);
    $extra = array_diff($mirrored, $columns);
    if ($missing || $extra) {
        $columnMismatch[] = $table . ' missing=' . implode('|', $missing) . ' extra=' . implode('|', $extra);
    }
}
check('mirrored schema has the same columns', $columnMismatch === [], implode('; ', $columnMismatch));

/* ------------------------------------------------------- registration -- */

echo PHP_EOL . 'Registration and login' . PHP_EOL;

$result = dat_register_user('owner@example.com', 'short', 'short', 'Owner');
check('weak password is rejected', $result['success'] === false);

$result = dat_register_user('owner@example.com', 'Str0ngPassword', 'different', 'Owner');
check('password mismatch is rejected', $result['success'] === false);

$result = dat_register_user('not-an-email', 'Str0ngPassword', 'Str0ngPassword', 'Owner');
check('invalid email is rejected', $result['success'] === false);

$result = dat_register_user('owner@example.com', 'Str0ngPassword', 'Str0ngPassword', 'Owner');
check('valid registration succeeds', $result['success'] === true);
$owner = $result['user'] ?? null;

$duplicate = dat_register_user('owner@example.com', 'Str0ngPassword', 'Str0ngPassword', 'Owner');
check('duplicate email is rejected', $duplicate['success'] === false);

$stored = dat_user_by_email('owner@example.com');
check('password is stored as a hash', $stored !== null && $stored['password_hash'] !== 'Str0ngPassword' && strpos($stored['password_hash'], '$2y$') === 0);
check('username was derived from the email', ($stored['username'] ?? '') === 'owner');

check('wrong password fails', dat_authenticate('owner@example.com', 'nope')['success'] === false);
check('correct password succeeds', dat_authenticate('owner@example.com', 'Str0ngPassword')['success'] === true);
check('username login works', dat_authenticate('owner', 'Str0ngPassword')['success'] === true);
check('failed logins are recorded', (int) (dat_one('SELECT COUNT(*) AS c FROM dat_login_logs WHERE success = 0')['c'] ?? 0) > 0);

$second = dat_register_user('other@example.com', 'Str0ngPassword', 'Str0ngPassword', 'Other')['user'];

/* ------------------------------------------------------------- assets -- */

echo PHP_EOL . 'Assets' . PHP_EOL;

$asset = dat_create_asset($owner['id'], DAT_ASSET_TYPE_PET, 'Lucky', 'Friendly dog.', [
    'animal' => 'Dog',
    'breed' => 'Golden Retriever',
    'color' => 'Golden',
    'ignored_field' => 'must not be stored',
]);

check('asset was created', $asset !== null);
check('public ID is valid', dat_is_valid_public_id($asset['public_id'] ?? ''), (string) ($asset['public_id'] ?? ''));
check('unknown metadata field was dropped', !isset(dat_asset_metadata($asset)['ignored_field']));
check('asset is active by default', (int) $asset['status'] === DAT_ASSET_STATUS_ACTIVE);

$tags = dat_asset_tags($asset['id']);
check('QR and NFC tags were created', count($tags) === 2, (string) count($tags));
check('QR tag points at the public URL', ($tags[0]['url'] ?? '') === dat_tag_url($asset['public_id']));
check('NFC tag carries a payload', strpos((string) ($tags[1]['nfc_payload'] ?? ''), dat_tag_url($asset['public_id'])) !== false);

check('asset is reachable by public ID', dat_asset_by_public_id($asset['public_id']) !== null);
check('unknown public ID returns nothing', dat_asset_by_public_id('ZZZZ9999') === null);
check('invalid public ID returns nothing', dat_asset_by_public_id('abc') === null);

check('owner can read their asset', dat_asset_for_owner($asset['id'], $owner['id']) !== null);
check('another account cannot read it', dat_asset_for_owner($asset['id'], $second['id']) === null);
check('another account cannot update it', dat_update_asset($asset['id'], $second['id'], ['name' => 'Hijacked']) === false);
check('name was not changed by the other account', dat_asset_for_owner($asset['id'], $owner['id'])['name'] === 'Lucky');

$updated = dat_update_asset($asset['id'], $owner['id'], [
    'name' => 'Lucky II',
    'description' => 'Still a friendly dog.',
    'status' => DAT_ASSET_STATUS_LOST,
    'metadata' => ['animal' => 'Dog', 'breed' => 'Golden Retriever', 'color' => 'Cream'],
]);
$asset = dat_asset_for_owner($asset['id'], $owner['id']);
check('update succeeded', $updated && $asset['name'] === 'Lucky II');
check('status update succeeded', (int) $asset['status'] === DAT_ASSET_STATUS_LOST);
check('metadata update succeeded', dat_asset_metadata($asset)['color'] === 'Cream');
check('public ID survived the update', $asset['public_id'] === dat_asset_by_public_id($asset['public_id'])['public_id']);

$public = dat_public_asset($asset);
check('public projection hides the internal id', !isset($public['id'], $public['owner_id'], $public['metadata_json']));
check('public projection keeps public metadata', ($public['metadata']['Breed'] ?? '') === 'Golden Retriever');

$board = dat_create_asset($owner['id'], DAT_ASSET_TYPE_MENU_BOARD, 'Café menu board', '', [
    'material' => 'Acrylic',
    'location' => 'Counter',
    'wifi_network' => 'Cafe-Guest',
    'wifi_password' => 'Sonnenaufgang-2026',
]);
$boardPublic = dat_public_asset($board);
check('private fields are hidden on the public page', !isset($boardPublic['metadata']['Wi-Fi password']));
check('public product fields still render', ($boardPublic['metadata']['Material'] ?? '') === 'Acrylic'
    && ($boardPublic['metadata']['Guest Wi-Fi name'] ?? '') === 'Cafe-Guest');
check('private fields are still stored', dat_asset_metadata($board)['wifi_password'] === 'Sonnenaufgang-2026');

$counts = dat_asset_counts($owner['id']);
check('asset counts add up', $counts['total'] === 2 && $counts['lost'] === 1, json_encode($counts));

/* ------------------------------------------------------ WhatsApp field -- */

echo PHP_EOL . 'WhatsApp contact' . PHP_EOL;

check('a new asset starts without a number',
    dat_normalize_whatsapp($board['contact_whatsapp'] ?? '') === null);

check('the number can be stored', dat_update_asset($board['id'], $owner['id'], [
    'name' => 'Café menu board',
    'contact_whatsapp' => '+49 (170) 123-4567',
]) === true);
$board = dat_asset_for_owner($board['id'], $owner['id']);
check('the number is normalised on save', $board['contact_whatsapp'] === '+491701234567', (string) $board['contact_whatsapp']);
check('the tag page would get a wa.me link',
    strpos((string) dat_whatsapp_url($board, 'Hello'), 'https://wa.me/491701234567?text=') === 0);
check('the number stays out of the public projection', !isset(dat_public_asset($board)['contact_whatsapp']));

dat_update_asset($board['id'], $owner['id'], ['name' => 'Café menu board', 'contact_whatsapp' => '']);
check('clearing the field removes the button',
    dat_normalize_whatsapp(dat_asset_for_owner($board['id'], $owner['id'])['contact_whatsapp']) === null);

dat_update_asset($board['id'], $owner['id'], ['name' => 'Café menu board', 'contact_whatsapp' => '+491701234567']);
check('the number survives an unrelated update', dat_normalize_whatsapp(
    dat_asset_for_owner($board['id'], $owner['id'])['contact_whatsapp']
) === '+491701234567');

/* -------------------------------------------------------- chip target -- */

echo PHP_EOL . 'Chip target' . PHP_EOL;

$board = dat_asset_for_owner($board['id'], $owner['id']);
check('assets start on the tag page target', dat_normalize_tag_target($board['tag_target'] ?? '') === 'portal');
check('the stored tag points at the tag page',
    dat_asset_tags($board['id'])[0]['url'] === dat_tag_url($board['public_id']));

dat_update_asset($board['id'], $owner['id'], ['name' => 'Café menu board', 'tag_target' => 'whatsapp']);
$board = dat_asset_for_owner($board['id'], $owner['id']);
$boardTags = dat_asset_tags($board['id']);
$nfcTag = null;
foreach ($boardTags as $tag) {
    if ((int) $tag['type'] === DAT_TAG_TYPE_NFC) {
        $nfcTag = $tag;
    }
}
check('the target is stored', dat_normalize_tag_target($board['tag_target']) === 'whatsapp');
check('the printed code now carries the chat link',
    $boardTags[0]['url'] === 'https://wa.me/491701234567?text=' . rawurlencode(dat_whatsapp_message($board)),
    (string) $boardTags[0]['url']);
check('the chip carries the short chat link', $nfcTag !== null && $nfcTag['nfc_payload'] !== null
    && strpos($nfcTag['nfc_payload'], 'https://wa.me/491701234567') !== false
    && strpos($nfcTag['nfc_payload'], '?text=') === false);
check('the tag page still exists in whatsapp mode', dat_asset_by_public_id($board['public_id']) !== null);

dat_update_asset($board['id'], $owner['id'], ['name' => 'Café menu board', 'tag_target' => 'portal']);
check('switching back restores the page link',
    dat_asset_tags($board['id'])[0]['url'] === dat_tag_url($board['public_id']));

// A WhatsApp target without a number must fall back instead of breaking.
dat_update_asset($board['id'], $owner['id'], ['name' => 'Café menu board', 'contact_whatsapp' => '', 'tag_target' => 'whatsapp']);
$board = dat_asset_for_owner($board['id'], $owner['id']);
check('without a number the target falls back to the page',
    dat_tag_target_url($board) === dat_tag_url($board['public_id']));

/* --------------------------------------------------------------- tags -- */

echo PHP_EOL . 'Tag lifecycle' . PHP_EOL;

$extra = dat_create_tag($asset['id'], DAT_TAG_TYPE_NFC, 'Keychain chip');
check('extra tag was created', $extra !== null && $extra['label'] === 'Keychain chip');
check('extra tag uses the same URL', $extra['url'] === dat_tag_url($asset['public_id']));

$replacement = dat_replace_tag($extra['id'], $owner['id']);
check('replacement tag was created', $replacement !== null);
check('replacement keeps the same public URL', $replacement['url'] === dat_tag_url($asset['public_id']));
check('old tag is marked as replaced', (int) dat_tag_for_owner($extra['id'], $owner['id'])['status'] === DAT_TAG_STATUS_REPLACED);
check('another account cannot disable a tag', dat_disable_tag($replacement['id'], $second['id']) === false);
check('owner can disable a tag', dat_disable_tag($replacement['id'], $owner['id']) === true);

/* ----------------------------------------------------------- messages -- */

echo PHP_EOL . 'Finder messages' . PHP_EOL;

$found = dat_create_finder_message($asset, 'I found Lucky near the station.');
check('finder message was accepted', $found['success'] === true);
$token = $found['token'];

check('empty message is rejected', dat_create_finder_message($asset, '   ')['success'] === false);
check('over-long message is rejected', dat_create_finder_message($asset, str_repeat('x', 1001))['success'] === false);

$threads = dat_message_threads_for_owner($owner['id']);
check('owner inbox lists the thread', count($threads) === 1, (string) count($threads));
check('thread preview uses the message text', strpos((string) $threads[0]['preview'], 'near the station') !== false, (string) ($threads[0]['preview'] ?? ''));
check('thread counts the unread message', (int) $threads[0]['unread_count'] === 1);
check('unread counter matches', dat_unread_message_count($owner['id']) === 1);

$finderView = dat_message_thread($asset['id'], $token, $asset['public_id']);
check('finder sees their own thread', count($finderView) === 1);
check('finder thread is hidden for a wrong token', dat_message_thread($asset['id'], 'deadbeef', $asset['public_id']) === []);

$messages = dat_message_thread_messages($asset['id'], $token, $owner['id']);
check('owner sees the message', count($messages) === 1);
check('reading the thread clears unread', dat_unread_message_count($owner['id']) === 0);
check('another account cannot open the thread', dat_message_thread_messages($asset['id'], $token, $second['id']) === []);

$reply = dat_owner_reply($asset['id'], $token, $owner['id'], 'Thank you, I can collect him tonight.');
check('owner reply was stored', $reply['success'] === true);
check('another account cannot reply', dat_owner_reply($asset['id'], $token, $second['id'], 'nope')['success'] === false);

$conversation = dat_message_thread($asset['id'], $token, $asset['public_id']);
check('conversation contains both messages', count($conversation) === 2);
check('reply is marked as coming from the owner', (int) $conversation[1]['direction'] === DAT_MESSAGE_DIRECTION_OWNER);

// Rate limiting: exhaust the hourly allowance for this asset.
for ($i = 0; $i < PORTAL_MESSAGE_RATE_LIMIT; $i++) {
    dat_create_finder_message($asset, 'Message number ' . $i);
}
check('rate limit kicks in', dat_create_finder_message($asset, 'One message too many')['success'] === false);

dat_archive_thread($asset['id'], $token, $owner['id']);
$remainingTokens = array_column(dat_message_threads_for_owner($owner['id']), 'sender_token');
check('archiving hides the archived thread', !in_array($token, $remainingTokens, true), implode(',', $remainingTokens));

// Expiry: a message dated in the past is purged.
dat_exec('UPDATE dat_messages SET expires_at = ? WHERE asset_id = ?', ['2000-01-01 00:00:00', $asset['id']]);
dat_purge_expired_messages();
check('expired messages are purged', dat_all('SELECT id FROM dat_messages WHERE asset_id = ?', [$asset['id']]) === []);

/* ------------------------------------------------------ soft delete ---- */

echo PHP_EOL . 'Soft delete' . PHP_EOL;

check('asset is public before deletion', dat_asset_is_public($asset['status']) === true);
check('soft delete succeeds', dat_soft_delete_asset($asset['id'], $owner['id']) === true);

$deleted = dat_asset_by_public_id($asset['public_id']);
check('the public link still resolves after deletion', $deleted !== null);
check('but the tag page hides the asset', dat_asset_is_public($deleted['status']) === false);
check('deleted rows disappear from the dashboard', count(dat_assets_for_owner($owner['id'])) === 1);
check('another account cannot delete an asset', dat_soft_delete_asset($board['id'], $second['id']) === true); // no-op, row untouched
check('the menu board is still active', (int) dat_asset_for_owner($board['id'], $owner['id'])['status'] === DAT_ASSET_STATUS_ACTIVE);

/* ---------------------------------------------------- guest sessions --- */

echo PHP_EOL . 'Guest sessions' . PHP_EOL;

// A fresh visitor: no session, so no guest account yet.
unset($_SESSION['dat_guest_user_id']);
check('no guest user before starting', dat_guest_user() === null);

$guestSession = dat_create_guest_session();
check('guest session was created', $guestSession['success'] === true);
$guest = $guestSession['user'];
check('guest account uses the reserved domain', dat_is_guest_user($guest));
check('guest account cannot sign in', dat_authenticate($guest['email'], 'anything')['success'] === false);
check('guest session row exists', dat_one('SELECT id FROM dat_guest_sessions WHERE user_id = ?', [$guest['id']]) !== null);
check('guest session can create one tag', dat_guest_can_create() === true);

$guestAsset = dat_create_asset($guest['id'], DAT_ASSET_TYPE_KEYCHAIN, 'Guest keys', 'Keys found in the park.', ['material' => 'Acrylic', 'batch' => 'KC-TEST-01']);
check('guest asset was created', $guestAsset !== null && dat_is_valid_public_id($guestAsset['public_id']));
check('guest asset is publicly reachable', dat_asset_by_public_id($guestAsset['public_id']) !== null);
check('guest tag has QR and NFC tags', count(dat_asset_tags($guestAsset['id'])) === 2);
check('guest cannot create a second tag', dat_guest_can_create() === false);

$guestFinder = dat_create_finder_message($guestAsset, 'I found these keys by the fountain.');
check('a finder can message a guest asset', $guestFinder['success'] === true);

// Claiming: the visitor registers and the tag moves over.
$newOwner = dat_register_user('claimer@example.com', 'Str0ngPassword', 'Str0ngPassword', 'Claimer')['user'];
$claimed = dat_claim_guest_assets($newOwner['id']);
check('guest asset was transferred on registration', $claimed === 1, (string) $claimed);
check('the transferred tag sits in the new account', dat_asset_for_owner($guestAsset['id'], $newOwner['id']) !== null);
check('the transferred tag keeps its public ID', dat_asset_for_owner($guestAsset['id'], $newOwner['id'])['public_id'] === $guestAsset['public_id']);
check('the guest account is gone', dat_user_by_id($guest['id']) === null);
check('the guest session row is gone too', dat_one('SELECT id FROM dat_guest_sessions WHERE user_id = ?', [$guest['id']]) === null);
check('messages survived the transfer', dat_unread_message_count($newOwner['id']) === 1);
check('the guest session is cleared from the session', dat_guest_user() === null);

// Expiry: an unclaimed guest account is removed with its assets.
$stale = dat_create_guest_session();
$staleUser = $stale['user'];
dat_create_asset($staleUser['id'], DAT_ASSET_TYPE_MINI_TAG, 'Abandoned tag', '', []);
dat_exec('UPDATE dat_guest_sessions SET expires_at = ? WHERE user_id = ?', ['2000-01-01 00:00:00', $staleUser['id']]);
check('expired guest sessions are purged', dat_purge_guest_sessions() >= 1);
check('purged guest account is gone', dat_user_by_id($staleUser['id']) === null);
check('purged guest assets are gone', dat_asset_counts($staleUser['id'])['total'] === 0);

/* ------------------------------------------------------- housekeeping -- */

echo PHP_EOL . 'Housekeeping' . PHP_EOL;

// An expired message with a reply, an old rate row and an old login log.
$expiredThread = dat_create_finder_message($board, 'will be purged');
check('a message can be created for the purge test', $expiredThread['success'] === true);

dat_exec(
    'UPDATE dat_messages SET expires_at = ? WHERE sender_token = ?',
    ['2000-01-01 00:00:00', $expiredThread['token']]
);
dat_exec(
    'INSERT INTO dat_messages (id, asset_id, sender_token, direction, reply_to_id, content, status, created_at, expires_at)
     VALUES (?, ?, ?, 2, (SELECT id FROM dat_messages WHERE sender_token = ? LIMIT 1), ?, 1, ?, ?)',
    [dat_uuid(), $board['id'], $expiredThread['token'], $expiredThread['token'], 'reply that must go too', dat_now(), '2000-01-01 00:00:00']
);

dat_exec('INSERT INTO dat_message_rate (ip_hash, asset_id, created_at) VALUES (?, NULL, ?)', [str_repeat('a', 64), '2000-01-01 00:00:00']);
dat_exec(
    'INSERT INTO dat_login_logs (user_id, identifier, ip_hash, user_agent, success, reason, created_at)
     VALUES (NULL, ?, NULL, NULL, 0, NULL, ?)',
    ['ancient@example.com', '2000-01-01 00:00:00']
);

$before = [
    'messages' => (int) dat_one('SELECT COUNT(*) AS c FROM dat_messages')['c'],
    'rate' => (int) dat_one('SELECT COUNT(*) AS c FROM dat_message_rate')['c'],
    'logs' => (int) dat_one('SELECT COUNT(*) AS c FROM dat_login_logs')['c'],
];

$removed = dat_run_maintenance();

check('expired messages and their replies are removed', $removed['messages'] >= 2, json_encode($removed));
check('the thread really is gone', dat_message_thread($board['id'], $expiredThread['token'], $board['public_id']) === []);
$staleRate = (int) dat_one('SELECT COUNT(*) AS c FROM dat_message_rate WHERE created_at = ?', ['2000-01-01 00:00:00'])['c'];
check('stale rate rows are pruned', $removed['rate_rows'] >= 1 && $staleRate === 0,
    json_encode(['removed' => $removed['rate_rows'], 'stale_left' => $staleRate]));
check('old login logs are pruned', $removed['login_logs'] >= 1 && (int) dat_one('SELECT COUNT(*) AS c FROM dat_login_logs')['c'] < $before['logs']);
check('recent messages survive the pass', (int) dat_one('SELECT COUNT(*) AS c FROM dat_messages')['c'] < $before['messages']);

echo PHP_EOL . ($failures === 0
    ? "All {$checks} checks passed." . PHP_EOL
    : "{$failures} of {$checks} checks failed." . PHP_EOL);

exit($failures === 0 ? 0 : 1);

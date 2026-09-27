<?php
/**
 * Creates the portal tables. Run from the project root:
 *
 *   php scripts/migrate.php
 *
 * The script only issues CREATE TABLE IF NOT EXISTS statements, so it is safe
 * to run against a database that already holds other application tables.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../config/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$db = dat_db();
if ($db === null) {
    fwrite(STDERR, "Cannot connect to " . DB_NAME . " at " . DB_HOST . ":" . DB_PORT . PHP_EOL);
    exit(1);
}

echo 'Connected to ' . DB_NAME . ' on ' . DB_HOST . PHP_EOL;

$schema = file_get_contents(DAT_APP_ROOT . '/config/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Cannot read config/schema.sql" . PHP_EOL);
    exit(1);
}

// Drop comment lines, then split on statement terminators.
$lines = array_filter(
    preg_split('/\r\n|\n/', $schema),
    static fn ($line) => strpos(trim($line), '--') !== 0
);
$schema = implode("\n", $lines);

$statements = array_filter(
    array_map('trim', preg_split('/;\s*[\r\n]+/', $schema)),
    static fn ($statement) => $statement !== ''
);

$created = 0;
foreach ($statements as $statement) {
    try {
        $db->exec($statement);
        $created++;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Failed: ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, 'Statement: ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 120) . PHP_EOL);
        exit(1);
    }
}

echo 'Schema applied (' . $created . ' statements).' . PHP_EOL;

$tables = dat_all(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name LIKE ?',
    ['dat\_%']
);
echo 'Portal tables: ' . implode(', ', array_map(static fn ($row) => reset($row), $tables)) . PHP_EOL;

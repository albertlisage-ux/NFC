<?php
/**
 * Brings the database up to date. Run from the project root:
 *
 *   php scripts/migrate.php
 *
 * Two steps:
 *   1. config/schema.sql is applied, which only ever creates missing tables.
 *   2. every file in config/migrations/ that has not run yet is applied once,
 *      in filename order, and recorded in dat_migrations.
 *
 * A database that has no portal tables yet counts as a fresh install: the
 * baseline already contains the effect of every migration, so they are recorded
 * as applied instead of executed.
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
    fwrite(STDERR, 'Cannot connect to ' . DB_NAME . ' at ' . DB_HOST . ':' . DB_PORT . PHP_EOL);
    exit(1);
}

echo 'Connected to ' . DB_NAME . ' on ' . DB_HOST . PHP_EOL;

/** Split a .sql file into single statements. */
function migrate_statements(string $sql): array
{
    $lines = array_filter(
        preg_split('/\r\n|\n/', $sql),
        static fn ($line) => strpos(trim($line), '--') !== 0
    );

    return array_values(array_filter(
        array_map('trim', preg_split('/;\s*[\r\n]+/', implode("\n", $lines))),
        static fn ($statement) => $statement !== ''
    ));
}

/** Run one statement, reporting precisely what failed. */
function migrate_exec(PDO $db, string $statement, string $source): bool
{
    try {
        $db->exec($statement);
        return true;
    } catch (Throwable $e) {
        fwrite(STDERR, 'FAILED (' . $source . '): ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, 'Statement: ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 160) . PHP_EOL);
        return false;
    }
}

// Fresh database, or an installation that already carries data?
$existing = dat_one(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = ?',
    ['dat_assets']
);
$isFreshInstall = $existing === null;

// 1. Baseline.
$schema = file_get_contents(DAT_APP_ROOT . '/config/schema.sql');
if ($schema === false) {
    fwrite(STDERR, 'Cannot read config/schema.sql' . PHP_EOL);
    exit(1);
}

$created = 0;
foreach (migrate_statements($schema) as $statement) {
    if (!migrate_exec($db, $statement, 'schema.sql')) {
        exit(1);
    }
    $created++;
}
echo 'Baseline applied (' . $created . ' statements).' . PHP_EOL;

// 2. Migrations.
$migrationFiles = glob(DAT_APP_ROOT . '/config/migrations/*.sql') ?: [];
sort($migrationFiles);

$applied = [];
foreach (dat_all('SELECT filename FROM ' . dat_table('migrations')) as $row) {
    $applied[$row['filename']] = true;
}

$ran = 0;
$marked = 0;

foreach ($migrationFiles as $file) {
    $filename = basename($file);
    if (isset($applied[$filename])) {
        continue;
    }

    if ($isFreshInstall) {
        dat_query(
            'INSERT INTO ' . dat_table('migrations') . ' (filename, applied_at) VALUES (?, ?)',
            [$filename, dat_now()]
        );
        $marked++;
        continue;
    }

    echo 'Applying ' . $filename . PHP_EOL;
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, 'Cannot read ' . $filename . PHP_EOL);
        exit(1);
    }

    foreach (migrate_statements($sql) as $statement) {
        if (!migrate_exec($db, $statement, $filename)) {
            exit(1);
        }
    }

    dat_query(
        'INSERT INTO ' . dat_table('migrations') . ' (filename, applied_at) VALUES (?, ?)',
        [$filename, dat_now()]
    );
    $ran++;
}

if ($isFreshInstall) {
    echo 'Fresh install: ' . $marked . ' migrations marked as covered by the baseline.' . PHP_EOL;
} else {
    echo 'Migrations applied: ' . $ran . '.' . PHP_EOL;
}

$tables = dat_all(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name LIKE ?',
    ['dat\_%']
);
echo 'Portal tables: ' . implode(', ', array_map(static fn ($row) => reset($row), $tables)) . PHP_EOL;

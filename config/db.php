<?php
/**
 * Digital Asset Tag Portal - database layer (MySQL / MariaDB via PDO)
 *
 * Follows the connection and error-handling conventions of the existing
 * LinkTec site (config/db.php) so both applications can run on the same
 * LAMP server and share the same database user.
 *
 * All portal tables are prefixed with "dat_" so they can live in the same
 * schema as the existing site without collisions.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/app.php';

if (!defined('DB_HOST')) define('DB_HOST', dat_env('DAT_DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', (int) dat_env('DAT_DB_PORT', 3306));
if (!defined('DB_NAME')) define('DB_NAME', dat_env('DAT_DB_NAME', 'visionary_db'));
if (!defined('DB_USER')) define('DB_USER', dat_env('DAT_DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', (string) dat_env('DAT_DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_ERROR_LOG')) define('DB_ERROR_LOG', DAT_APP_ROOT . '/logs/db_errors.log');

/** Table name helper, keeps the dat_ prefix consistent. */
if (!function_exists('dat_table')) {
    function dat_table($name)
    {
        return 'dat_' . $name;
    }
}

if (!function_exists('dat_log_error')) {
    function dat_log_error($message, array $context = [])
    {
        $dir = dirname(DB_ERROR_LOG);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);
        if ($context) {
            $safe = $context;
            foreach (['password', 'password_hash', 'content', 'token'] as $secret) {
                if (isset($safe[$secret])) {
                    $safe[$secret] = '[redacted]';
                }
            }
            $line .= ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents(DB_ERROR_LOG, $line . PHP_EOL, FILE_APPEND);
    }
}

/**
 * Shared PDO connection (lazy, single instance per request).
 *
 * @return PDO|null null when the database is unreachable.
 */
if (!function_exists('dat_db')) {
    function dat_db()
    {
        static $connection = null;
        static $attempted = false;

        if ($connection instanceof PDO) {
            return $connection;
        }
        if ($attempted && $connection === null) {
            return null;
        }
        $attempted = true;

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];

        try {
            $initCommand = "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci";
            if (PHP_VERSION_ID >= 80500 && defined('Pdo\\Mysql::ATTR_INIT_COMMAND')) {
                $options[constant('Pdo\\Mysql::ATTR_INIT_COMMAND')] = $initCommand;
            } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $initCommand;
            }

            $connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            $connection->query('SELECT 1');
        } catch (PDOException $e) {
            dat_log_error('Database connection failed: ' . $e->getMessage(), [
                'host' => DB_HOST,
                'database' => DB_NAME,
            ]);
            $connection = null;
        }

        return $connection;
    }
}

/** True when the portal database is reachable. */
if (!function_exists('dat_db_available')) {
    function dat_db_available()
    {
        return dat_db() instanceof PDO;
    }
}

/**
 * Run a prepared statement.
 *
 * @return PDOStatement|null
 */
if (!function_exists('dat_query')) {
    function dat_query($sql, array $params = [])
    {
        $db = dat_db();
        if ($db === null) {
            return null;
        }

        try {
            $statement = $db->prepare($sql);
            if ($statement === false) {
                throw new RuntimeException('prepare() failed');
            }
            $statement->execute($params);
            return $statement;
        } catch (Throwable $e) {
            dat_log_error('Query failed: ' . $e->getMessage(), [
                'sql' => preg_replace('/\s+/', ' ', (string) $sql),
            ]);
            return null;
        }
    }
}

/** Fetch a single row. */
if (!function_exists('dat_one')) {
    function dat_one($sql, array $params = [])
    {
        $statement = dat_query($sql, $params);
        if ($statement === null) {
            return null;
        }
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }
}

/** Fetch all rows. */
if (!function_exists('dat_all')) {
    function dat_all($sql, array $params = [])
    {
        $statement = dat_query($sql, $params);
        return $statement === null ? [] : $statement->fetchAll();
    }
}

/** Execute a write statement and return the affected row count (or -1 on error). */
if (!function_exists('dat_exec')) {
    function dat_exec($sql, array $params = [])
    {
        $statement = dat_query($sql, $params);
        return $statement === null ? -1 : $statement->rowCount();
    }
}

/** Insert a row and return the generated auto-increment id. */
if (!function_exists('dat_insert')) {
    function dat_insert($sql, array $params = [])
    {
        if (dat_exec($sql, $params) < 0) {
            return null;
        }
        $db = dat_db();
        return $db === null ? null : (int) $db->lastInsertId();
    }
}

/** RFC 4122 version 4 UUID. */
if (!function_exists('dat_uuid')) {
    function dat_uuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

/** Current timestamp in the application timezone. */
if (!function_exists('dat_now')) {
    function dat_now()
    {
        return date('Y-m-d H:i:s');
    }
}

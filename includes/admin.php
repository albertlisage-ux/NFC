<?php
/**
 * Read-only helpers for the administrator area.
 *
 * Everything here reads. There is no insert, update or delete anywhere in this
 * file on purpose: the panel exists to look at the data, and a viewer that
 * cannot write is a viewer that cannot corrupt the portal while someone is
 * poking at it at a trade fair.
 *
 * Table and column names are never taken from the request directly. They are
 * checked against what the server reports, and only then interpolated, because
 * MySQL will not accept placeholders for identifiers.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/auth.php';

const DAT_ADMIN_PAGE_SIZE = 50;
const DAT_ADMIN_VALUE_LIMIT = 4000;

/** Names of the portal's own tables, read from the server, never guessed. */
if (!function_exists('dat_admin_tables')) {
    function dat_admin_tables()
    {
        static $tables = null;
        if ($tables !== null) {
            return $tables;
        }

        // SHOW TABLES does not always accept a placeholder for LIKE, so the
        // prefix filter happens here instead.
        $tables = [];
        foreach (dat_all('SHOW TABLES') as $row) {
            $name = (string) reset($row);
            if (strpos($name, 'dat_') === 0) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }
}

/** True when the name is one of this installation's tables. */
if (!function_exists('dat_admin_table')) {
    function dat_admin_table($name)
    {
        $name = trim((string) $name);

        return in_array($name, dat_admin_tables(), true) ? $name : null;
    }
}

/** Column names of a table, checked against the server. */
if (!function_exists('dat_admin_columns')) {
    function dat_admin_columns($table)
    {
        $table = dat_admin_table($table);
        if ($table === null) {
            return [];
        }

        $columns = [];
        foreach (dat_all('SHOW COLUMNS FROM `' . $table . '`') as $row) {
            $columns[] = [
                'name' => (string) $row['Field'],
                'type' => (string) ($row['Type'] ?? ''),
                'null' => (string) ($row['Null'] ?? ''),
                'key' => (string) ($row['Key'] ?? ''),
                'default' => $row['Default'] ?? null,
            ];
        }

        return $columns;
    }
}

if (!function_exists('dat_admin_row_count')) {
    function dat_admin_row_count($table)
    {
        $table = dat_admin_table($table);
        if ($table === null) {
            return 0;
        }

        $row = dat_one('SELECT COUNT(*) AS total FROM `' . $table . '`');

        return (int) ($row['total'] ?? 0);
    }
}

/** The column to order by: the newest row first whenever there is a time. */
if (!function_exists('dat_admin_order_column')) {
    function dat_admin_order_column($table)
    {
        $names = array_column(dat_admin_columns($table), 'name');
        foreach (['created_at', 'updated_at', 'id'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }

        return $names[0] ?? null;
    }
}

/**
 * One page of rows, optionally filtered.
 *
 * The search is a plain LIKE across the text columns. It is deliberately
 * simple: this is a viewer, not a query tool.
 *
 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int}
 */
if (!function_exists('dat_admin_rows')) {
    function dat_admin_rows($table, $page = 1, $search = '')
    {
        $table = dat_admin_table($table);
        if ($table === null) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $columns = dat_admin_columns($table);
        $where = '';
        $params = [];
        $search = trim((string) $search);

        if ($search !== '') {
            $textColumns = [];
            foreach ($columns as $column) {
                // Only the types that LIKE can sensibly match.
                if (preg_match('/char|text|date|time|int|decimal|enum/i', $column['type'])) {
                    $textColumns[] = '`' . $column['name'] . '` LIKE ?';
                    $params[] = '%' . $search . '%';
                }
            }
            if ($textColumns) {
                $where = ' WHERE (' . implode(' OR ', $textColumns) . ')';
            }
        }

        $countRow = dat_one('SELECT COUNT(*) AS total FROM `' . $table . '`' . $where, $params);
        $total = (int) ($countRow['total'] ?? 0);
        $pageSize = DAT_ADMIN_PAGE_SIZE;
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = max(1, min($pages, (int) $page));

        $order = dat_admin_order_column($table);
        $direction = $order === 'created_at' ? ' DESC' : '';
        $orderBy = $order !== null ? ' ORDER BY `' . $order . '`' . $direction : '';

        $rows = dat_all(
            'SELECT * FROM `' . $table . '`' . $where . $orderBy . ' LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize),
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}

/** A cell value, escaped and safe to drop into the table. */
if (!function_exists('dat_admin_cell')) {
    function dat_admin_cell($value)
    {
        if ($value === null) {
            return '<span class="admin-null">NULL</span>';
        }

        $text = (string) $value;
        if ($text === '') {
            return '<span class="admin-null">(empty)</span>';
        }

        $truncated = false;
        if (mb_strlen($text) > DAT_ADMIN_VALUE_LIMIT) {
            $text = mb_substr($text, 0, DAT_ADMIN_VALUE_LIMIT);
            $truncated = true;
        }

        $html = nl2br(e($text));
        if ($truncated) {
            $html .= ' <span class="admin-null">(cut off, ' . DAT_ADMIN_VALUE_LIMIT . ' characters)</span>';
        }

        return $html;
    }
}

/** The table list, shared by both pages. */
if (!function_exists('dat_admin_nav')) {
    function dat_admin_nav($current = null)
    {
        $html = '<nav class="admin-nav" aria-label="' . e(t('admin.tables', 'Tables')) . '"><ul>';
        foreach (dat_admin_tables() as $table) {
            $count = dat_admin_row_count($table);
            $active = $table === $current ? ' class="is-current"' : '';
            $html .= '<li' . $active . '><a href="' . e(dat_url('admin/table.php?name=' . rawurlencode($table))) . '">'
                . '<span class="admin-nav-name">' . e(substr($table, 4)) . '</span>'
                . '<span class="admin-nav-count">' . number_format($count) . '</span></a></li>';
        }
        $html .= '</ul></nav>';

        return $html;
    }
}

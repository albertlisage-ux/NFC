<?php
/**
 * Administrator overview: every table in the portal, with its row count.
 *
 * Read-only, and gated on the admin role rather than on knowing the URL.
 * scripts/create-admin.php promotes an account.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

$user = dat_require_admin();

$tables = dat_admin_tables();
$counts = [];
$totalRows = 0;
foreach ($tables as $table) {
    $count = dat_admin_row_count($table);
    $counts[$table] = $count;
    $totalRows += $count;
}

$server = dat_one('SELECT VERSION() AS version');
$database = dat_one('SELECT DATABASE() AS name');

dat_page_start([
    'title' => t('admin.title', 'Administration') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => dat_unread_message_count($user['id']),
]);
?>
<main id="main">
    <section class="section-band">
        <div class="section-content">
            <div class="section-head">
                <p class="eyebrow"><?= e(t('admin.eyebrow', 'Administration')) ?></p>
                <h1><?= e(t('admin.title', 'Administration')) ?></h1>
                <p><?= e(t('admin.lead', 'Every table in this installation, exactly as it is stored. This area only reads: there is no way to change a row from here.')) ?></p>
            </div>

            <div class="notice notice-warn">
                <strong><?= e(t('admin.warning_title', 'This view shows raw rows.')) ?></strong>
                <p><?= e(t('admin.warning_body', 'That includes password hashes and the anonymous tokens that identify a finder thread. Keep this account to yourself, use a password you do not use anywhere else, and treat everything on these pages as confidential.')) ?></p>
            </div>

            <div class="grid" style="margin-top:26px">
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong><?= number_format(count($tables)) ?></strong><?= e(t('admin.stat_tables', 'tables')) ?></div></div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong><?= number_format($totalRows) ?></strong><?= e(t('admin.stat_rows', 'rows in total')) ?></div></div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong><?= e((string) ($server['version'] ?? '?')) ?></strong><?= e(t('admin.stat_server', 'database server')) ?></div></div>
                </div>
            </div>

            <div class="admin-layout">
                <?= dat_admin_nav() ?>

                <div class="admin-panel">
                    <h2><?= e(t('admin.tables', 'Tables')) ?></h2>
                    <p class="form-hint"><?= e(sprintf(t('admin.database', 'Database: %s'), (string) ($database['name'] ?? DB_NAME))) ?></p>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?= e(t('admin.column_table', 'Table')) ?></th>
                                    <th><?= e(t('admin.column_rows', 'Rows')) ?></th>
                                    <th><?= e(t('admin.column_columns', 'Columns')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tables as $table): ?>
                                    <?php $columns = dat_admin_columns($table); ?>
                                    <tr>
                                        <td><code><?= e($table) ?></code></td>
                                        <td><?= number_format($counts[$table]) ?></td>
                                        <td><?= number_format(count($columns)) ?></td>
                                        <td>
                                            <a class="text-link" href="<?= e(dat_url('admin/table.php?name=' . rawurlencode($table))) ?>">
                                                <i class="fa-solid fa-table-list" aria-hidden="true"></i><b><?= e(t('admin.open', 'Open')) ?></b>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

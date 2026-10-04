<?php
/**
 * One table of the portal, read-only, with paging and a simple filter.
 *
 * The table name is checked against what the server reports before it is
 * interpolated, and every value is escaped on the way out.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

$user = dat_require_admin();

$table = dat_admin_table($_GET['name'] ?? '');
if ($table === null) {
    http_response_code(404);
    dat_page_start([
        'title' => t('admin.unknown_table', 'Unknown table') . ' | ' . PORTAL_NAME,
        'robots' => 'noindex, nofollow',
        'unread' => 0,
    ]);
    ?>
    <main id="main" class="shell">
        <section class="tag-page">
            <div class="empty-state">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                <h1><?= e(t('admin.unknown_table', 'Unknown table')) ?></h1>
                <p><?= e(t('admin.unknown_table_body', 'That table does not belong to this installation.')) ?></p>
                <p><a class="btn btn-ghost" href="<?= e(dat_url('admin/index.php')) ?>"><?= e(t('admin.back', 'Back to administration')) ?></a></p>
            </div>
        </section>
    </main>
    <?php
    dat_page_end();
    exit;
}

$search = (string) ($_GET['q'] ?? '');
$page = (int) ($_GET['page'] ?? 1);
$result = dat_admin_rows($table, $page, $search);
$columns = dat_admin_columns($table);
$base = dat_url('admin/table.php');

dat_page_start([
    'title' => $table . ' | ' . t('admin.title', 'Administration') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => dat_unread_message_count($user['id']),
]);
?>
<main id="main">
    <section class="section-band">
        <div class="section-content">
            <div class="section-head">
                <p class="eyebrow">
                    <a href="<?= e(dat_url('admin/index.php')) ?>"><?= e(t('admin.title', 'Administration')) ?></a>
                    <span aria-hidden="true">/</span>
                    <code><?= e($table) ?></code>
                </p>
                <h1><?= e(substr($table, 4)) ?></h1>
                <p><?= e(sprintf(t('admin.row_summary', '%s rows, %s columns, newest first.'), number_format($result['total']), number_format(count($columns)))) ?></p>
            </div>

            <form class="admin-search" method="get" action="<?= e($base) ?>">
                <input type="hidden" name="name" value="<?= e($table) ?>">
                <label for="q"><?= e(t('admin.search_label', 'Filter')) ?></label>
                <input type="search" id="q" name="q" value="<?= e($search) ?>"
                       placeholder="<?= e(t('admin.search_placeholder', 'Text to look for in any column')) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><?= e(t('admin.search', 'Search')) ?></button>
                <?php if ($search !== ''): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($base . '?name=' . rawurlencode($table)) ?>"><?= e(t('admin.clear', 'Clear')) ?></a>
                <?php endif; ?>
            </form>

            <div class="admin-layout">
                <?= dat_admin_nav($table) ?>

                <div class="admin-panel">
                    <?php if (!$result['rows']): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                            <h2><?= e(t('admin.no_rows', 'No rows')) ?></h2>
                            <p><?= e($search !== ''
                                ? t('admin.no_rows_search', 'Nothing in this table matches that text.')
                                : t('admin.no_rows_empty', 'This table is empty.')) ?></p>
                        </div>
                    <?php else: ?>
                        <?php /*
                         * Cloudflare rewrites anything that looks like an email
                         * address into a link that its own script has to decode.
                         * The site's Content-Security-Policy blocks that script,
                         * so the address would arrive broken. These markers are
                         * Cloudflare's documented opt-out.
                         */ ?>
                        <!--email_off-->
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-rows">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th>
                                                <?= e($column['name']) ?>
                                                <span class="admin-type"><?= e($column['type']) ?></span>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <td><?= dat_admin_cell($row[$column['name']] ?? null) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!--/email_off-->

                        <?php if ($result['pages'] > 1): ?>
                            <?php
                            $link = static function ($target) use ($base, $table, $search) {
                                $query = ['name' => $table, 'page' => $target];
                                if ($search !== '') {
                                    $query['q'] = $search;
                                }
                                return $base . '?' . http_build_query($query);
                            };
                            ?>
                            <nav class="admin-pager" aria-label="<?= e(t('admin.pages', 'Pages')) ?>">
                                <?php if ($result['page'] > 1): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e($link($result['page'] - 1)) ?>"><?= e(t('admin.previous', 'Previous')) ?></a>
                                <?php endif; ?>
                                <span><?= e(sprintf(t('admin.page_of', 'Page %d of %d'), $result['page'], $result['pages'])) ?></span>
                                <?php if ($result['page'] < $result['pages']): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e($link($result['page'] + 1)) ?>"><?= e(t('admin.next', 'Next')) ?></a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

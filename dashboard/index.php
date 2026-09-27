<?php
/**
 * Owner dashboard: every asset with its status, tag link and quick actions.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$user = dat_require_login();
$assets = dat_assets_for_owner($user['id']);
$counts = dat_asset_counts($user['id']);
$unread = dat_unread_message_count($user['id']);

dat_page_start([
    'title' => t('dashboard.title', 'Dashboard') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => $unread,
]);
?>
<main id="main" class="shell">
    <?= dat_flash_render() ?>

    <div class="page-head">
        <div>
            <h1><?= e(sprintf(t('dashboard.welcome', 'Welcome back, %s'), dat_owner_name($user))) ?></h1>
            <p><?= e(t('dashboard.subtitle', 'Manage your assets, their tags and the messages finders send you.')) ?></p>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/messages.php')) ?>">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                <?= e(t('nav.messages', 'Messages')) ?>
                <?php if ($unread > 0): ?><span class="nav-badge"><?= (int) $unread ?></span><?php endif; ?>
            </a>
            <a class="btn btn-primary" href="<?= e(dat_url('dashboard/assets-new.php')) ?>">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <?= e(t('dashboard.add_asset', 'Add asset')) ?>
            </a>
        </div>
    </div>

    <div class="stat-row">
        <div class="stat"><span><?= e(t('dashboard.stat_total', 'Assets')) ?></span><strong><?= (int) $counts['total'] ?></strong></div>
        <div class="stat"><span><?= e(t('status.active', 'Active')) ?></span><strong><?= (int) $counts['active'] ?></strong></div>
        <div class="stat"><span><?= e(t('status.lost', 'Lost')) ?></span><strong><?= (int) $counts['lost'] ?></strong></div>
        <div class="stat"><span><?= e(t('status.found', 'Found')) ?></span><strong><?= (int) $counts['found'] ?></strong></div>
    </div>

    <?php if (!$assets): ?>
        <div class="empty-state">
            <i class="fa-solid fa-tag" aria-hidden="true"></i>
            <h2><?= e(t('dashboard.empty_title', 'No assets yet')) ?></h2>
            <p><?= e(t('dashboard.empty_body', 'Create your first asset and the portal generates its public link, QR code and NFC text straight away.')) ?></p>
            <a class="btn btn-primary" href="<?= e(dat_url('dashboard/assets-new.php')) ?>"><?= e(t('dashboard.add_asset', 'Add asset')) ?></a>
        </div>
    <?php else: ?>
        <div class="asset-grid">
            <?php foreach ($assets as $asset): ?>
                <?php $typeMeta = dat_asset_type_meta($asset['type']); ?>
                <article class="asset-card">
                    <div class="asset-card-head">
                        <span class="tag-type-mark" aria-hidden="true"><?= e($typeMeta['emoji']) ?></span>
                        <div>
                            <h3><?= e($asset['name']) ?></h3>
                            <p><?= e(dat_asset_summary($asset)) ?></p>
                        </div>
                    </div>

                    <div class="asset-card-meta">
                        <?= dat_status_pill($asset['status']) ?>
                        <?= dat_type_pill($asset['type']) ?>
                    </div>

                    <p class="asset-card-url"><i class="fa-solid fa-link" aria-hidden="true"></i> <?= e(dat_tag_url($asset['public_id'])) ?></p>

                    <div class="btn-group">
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.view', 'View')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/asset-edit.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.edit', 'Edit')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.tags', 'Tags')) ?></a>
                        <a class="btn btn-quiet btn-sm" href="<?= e(dat_tag_url($asset['public_id'])) ?>" target="_blank" rel="noopener"><?= e(t('dashboard.open_public', 'Public page')) ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php
dat_page_end();

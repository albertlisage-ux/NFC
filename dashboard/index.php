<?php
/**
 * Owner dashboard: every asset with its status, tag link and quick actions.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

$user = dat_require_login();
$assets = dat_assets_for_owner($user['id']);

/*
 * The dashboard should lead with what needs attention: something reported
 * lost, then something found, then everything that is simply fine.
 */
$urgency = [
    DAT_ASSET_STATUS_LOST => 0,
    DAT_ASSET_STATUS_FOUND => 1,
    DAT_ASSET_STATUS_ACTIVE => 2,
];
usort($assets, static function ($left, $right) use ($urgency) {
    $leftRank = $urgency[(int) $left['status']] ?? 9;
    $rightRank = $urgency[(int) $right['status']] ?? 9;
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    // Newest first, with the name settling ties so the order a visitor sees
    // never depends on the database deciding to return rows in another order.
    $byTime = strcmp((string) $right['updated_at'], (string) $left['updated_at']);

    return $byTime !== 0 ? $byTime : strcmp((string) $left['name'], (string) $right['name']);
});

// The catalogue examples are ordinary assets; matching them by public ID lets
// their card show the product photo from the catalogue. Anything a visitor
// created shows its own uploaded photo, and falls back to the type icon.
$catalogueByPublicId = [];
foreach (dat_demo_catalog() as $entry) {
    $catalogueByPublicId[$entry['public_id']] = $entry;
}

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
            <?php if (dat_user_is_admin($user)): ?>
                <a class="btn btn-ghost" href="<?= e(dat_url('admin/index.php')) ?>">
                    <i class="fa-solid fa-database" aria-hidden="true"></i>
                    <?= e(t('admin.link', 'Administration')) ?>
                </a>
            <?php endif; ?>
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
        <div class="stat">
            <span><?= e(t('dashboard.stat_unread', 'Unread messages')) ?></span>
            <strong><?= (int) $unread ?></strong>
        </div>
    </div>

    <?php if (!$assets): ?>
        <div class="empty-state">
            <i class="fa-solid fa-tag" aria-hidden="true"></i>
            <h2><?= e(t('dashboard.empty_title', 'No assets yet')) ?></h2>
            <p><?= e(t('dashboard.empty_body', 'Create your first asset and the portal generates its public link, QR code and NFC text straight away.')) ?></p>
            <div class="btn-group" style="justify-content:center">
                <a class="btn btn-primary" href="<?= e(dat_url('dashboard/assets-new.php')) ?>"><?= e(t('dashboard.add_asset', 'Add asset')) ?></a>
                <a class="btn btn-ghost" href="<?= e(dat_url('use-cases')) ?>"><?= e(t('dashboard.empty_products', 'Look at the products first')) ?></a>
            </div>
            <p class="form-hint"><?= e(t('dashboard.empty_hint', 'A tag is one product plus the page behind it. The products page shows all nine with their real codes.')) ?></p>
        </div>
    <?php else: ?>
        <div class="asset-grid">
            <?php foreach ($assets as $asset): ?>
                <?php
                $catalogueEntry = $catalogueByPublicId[$asset['public_id']] ?? null;
                $uploaded = dat_image_keys($asset['id']);
                $thumbnail = $catalogueEntry !== null
                    ? dat_entry_image($catalogueEntry)
                    : ($uploaded ? dat_upload_url($uploaded[0]) : null);
                ?>
                <article class="asset-card asset-card-status-<?= (int) $asset['status'] ?>">
                    <div class="asset-card-head">
                        <?= dat_type_mark($asset['type'], $thumbnail, 'tag-type-mark') ?>
                        <div>
                            <h3><?= e($asset['name']) ?></h3>
                            <p><?= e(dat_asset_summary($asset)) ?></p>
                        </div>
                    </div>

                    <div class="asset-card-meta">
                        <?= dat_status_pill($asset['status']) ?>
                        <?= dat_type_pill($asset['type']) ?>
                    </div>

                    <p class="asset-card-url">
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                        <?= e(dat_tag_url($asset['public_id'])) ?>
                        <button type="button" class="btn btn-quiet btn-sm"
                                data-copy="<?= e(dat_tag_url($asset['public_id'])) ?>"
                                data-copy-label="<?= e(t('tag.copied', 'Copied')) ?>">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i><span><?= e(t('dashboard.copy_link', 'Copy')) ?></span>
                        </button>
                    </p>

                    <div class="btn-group">
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.view', 'View')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/asset-edit.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.edit', 'Edit')) ?></a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>">
                            <i class="fa-solid fa-qrcode" aria-hidden="true"></i><?= e(t('dashboard.tags', 'Tags')) ?>
                        </a>
                        <a class="btn btn-quiet btn-sm" href="<?= e(dat_tag_url($asset['public_id'])) ?>" target="_blank" rel="noopener"><?= e(t('dashboard.open_public', 'Public page')) ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php
        // Only worth saying when the account holds nothing but the examples,
        // which is exactly the case for the account that runs the shop.
        $exampleCount = 0;
        foreach ($assets as $asset) {
            if (isset($catalogueByPublicId[$asset['public_id']])) {
                $exampleCount++;
            }
        }
        ?>
        <?php if ($exampleCount === count($assets)): ?>
            <div class="notice notice-info" style="margin-top:26px">
                <strong><?= e(t('dashboard.examples_title', 'These are the nine catalogue examples.')) ?></strong>
                <p><?= e(t('dashboard.examples_body', 'Each one is a real asset with its own permanent link, the same one the products page and the printed code point at. Edit one and the page behind the code changes.')) ?></p>
                <p><a class="text-link" href="<?= e(dat_url('use-cases')) ?>"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('dashboard.examples_link', 'See them explained product by product')) ?></b></a></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php
dat_page_end();

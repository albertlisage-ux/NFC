<?php
/**
 * Public tag page: GET /t/{publicId}
 *
 * No login required. The page renders asset information only; owner details
 * are never part of the query result or the view.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$asset = $publicId !== '' ? dat_asset_by_public_id($publicId) : null;

if ($asset === null) {
    http_response_code(404);
    dat_page_start([
        'title' => t('tag.not_found_title', 'Tag not found') . ' | ' . PORTAL_NAME,
        'robots' => 'noindex, nofollow',
        'unread' => 0,
    ]);
    ?>
    <main id="main" class="shell">
        <section class="tag-page">
            <div class="empty-state">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                <h1><?= e(t('tag.not_found_title', 'Tag not found')) ?></h1>
                <p><?= e(t('tag.not_found_body', 'This link does not match any registered asset. Check the address, or scan the tag again.')) ?></p>
                <p><a class="btn btn-ghost" href="<?= e(dat_url('index.php')) ?>"><?= e(t('tag.back_home', 'Go to the start page')) ?></a></p>
            </div>
        </section>
    </main>
    <?php
    dat_page_end();
    exit;
}

$status = (int) $asset['status'];
$typeMeta = dat_asset_type_meta($asset['type']);
$isVisible = dat_asset_is_public($status);
$images = $isVisible ? dat_image_keys($asset['id']) : [];

// A finder who already wrote to the owner can reopen their own thread.
$senderToken = dat_sender_token(false);
$threadUrl = null;
if ($isVisible && $senderToken !== null) {
    $existing = dat_one(
        'SELECT id FROM ' . dat_table('messages') . '
          WHERE asset_id = ? AND sender_token = ? LIMIT 1',
        [$asset['id'], $senderToken]
    );
    if ($existing !== null) {
        $threadUrl = dat_url('t/thread.php') . '?id=' . rawurlencode($asset['public_id']) . '&token=' . rawurlencode($senderToken);
    }
}

dat_page_start([
    'title' => ($isVisible ? $asset['name'] : t('tag.inactive_title', 'Tag no longer active')) . ' | ' . PORTAL_NAME,
    'description' => $isVisible
        ? mb_substr(trim(strip_tags((string) $asset['description'])), 0, 150)
        : t('tag.inactive_body', 'This tag is no longer active.'),
    'robots' => 'noindex, follow',
    'unread' => 0,
]);
?>
<main id="main" class="shell">
    <section class="tag-page">
        <?php if ($isVisible): ?>
            <p class="tag-page-nav">
                <a href="<?= e(dat_url('index.php')) ?>">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <?= e(t('tag.back_home', 'Go to the start page')) ?>
                </a>
            </p>

            <?php dat_tag_card(dat_public_asset($asset), $images, [
                'found_url' => dat_url('t/found.php') . '?id=' . rawurlencode($asset['public_id']),
            ]); ?>

            <?php if ($threadUrl !== null): ?>
                <p class="tag-page-nav">
                    <a href="<?= e($threadUrl) ?>">
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        <?= e(t('tag.view_thread', 'See your conversation with the owner')) ?>
                    </a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-minus" aria-hidden="true"></i>
                <h1><?= e(t('tag.inactive_title', 'Tag no longer active')) ?></h1>
                <p><?= e(t('tag.inactive_body', 'This tag is no longer active. It may have been replaced or deleted by its owner.')) ?></p>
                <p><a class="btn btn-ghost" href="<?= e(dat_url('index.php')) ?>"><?= e(t('tag.back_home', 'Go to the start page')) ?></a></p>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
dat_page_end();

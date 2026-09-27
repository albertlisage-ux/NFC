<?php
/**
 * Tag management: the public URL, the QR code, the NFC payload and the list of
 * physical tags pointing at this asset.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$user = dat_require_login();
$assetId = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$asset = dat_asset_for_owner($assetId, $user['id']);

if ($asset === null) {
    http_response_code(404);
    dat_flash_set('error', t('error.asset_missing', 'That asset does not exist or belongs to another account.'));
    header('Location: ' . dat_url('dashboard/index.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!dat_csrf_verify()) {
        dat_flash_set('error', t('error.csrf', 'Your session expired. Please try again.'));
    } elseif ($action === 'add_tag') {
        $type = (int) ($_POST['tag_type'] ?? DAT_TAG_TYPE_QR);
        if (dat_create_tag($asset['id'], $type, (string) ($_POST['label'] ?? '') ?: null) !== null) {
            dat_flash_set('success', t('tag.added', 'Tag added. It points at the same public link.'));
        }
    } elseif ($action === 'disable_tag') {
        if (dat_disable_tag((string) ($_POST['tag_id'] ?? ''), $user['id'])) {
            dat_flash_set('info', t('tag.disabled', 'Tag disabled. The asset itself is unchanged.'));
        }
    } elseif ($action === 'replace_tag') {
        if (dat_replace_tag((string) ($_POST['tag_id'] ?? ''), $user['id']) !== null) {
            dat_flash_set('success', t('tag.replaced', 'Replacement created. Write the new tag and attach it.'));
        }
    }

    header('Location: ' . dat_url('dashboard/tags.php') . '?id=' . urlencode($asset['id']));
    exit;
}

$publicUrl = dat_tag_url($asset['public_id']);
$qrSvg = dat_qr_svg($publicUrl, 8, 4);
$tags = dat_asset_tags($asset['id']);
$nfcPayload = dat_nfc_payload($asset, $publicUrl);
$nfcBytes = dat_nfc_payload_bytes($nfcPayload);
$chipHint = dat_nfc_capacity_hint($nfcBytes);
$pngUrl = dat_url('qr/image.php') . '?id=' . rawurlencode($asset['public_id']) . '&format=png&download=1&scale=12';
$svgUrl = dat_url('qr/image.php') . '?id=' . rawurlencode($asset['public_id']) . '&format=svg&download=1&scale=12';
$nfcUrl = dat_url('qr/nfc.php') . '?id=' . rawurlencode($asset['public_id']) . '&download=1';
$justCreated = isset($_GET['created']);

dat_page_start([
    'title' => t('tag.page_title', 'Tags') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => dat_unread_message_count($user['id']),
]);
?>
<main id="main" class="shell">
    <?= dat_flash_render() ?>

    <div class="page-head">
        <div>
            <h1><?= e(t('tag.page_title', 'Tags')) ?></h1>
            <p><?= e($asset['name']) ?> &middot; <?= e(dat_asset_type_label($asset['type'])) ?></p>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.view', 'View')) ?></a>
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/index.php')) ?>"><?= e(t('nav.dashboard', 'Dashboard')) ?></a>
        </div>
    </div>

    <?php if ($justCreated): ?>
        <div class="notice notice-ok">
            <strong><?= e(t('tag.created_title', 'Your tag is ready')) ?></strong>
            <p><?= e(t('tag.created_body', 'Download the QR code, or copy the NFC text to your writing app. The link stays valid even if you rename the asset later.')) ?></p>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2><?= e(t('tag.public_url', 'Public link')) ?></h2>
        <p><?= e(t('tag.public_url_hint', 'This single address goes on every tag. It never changes, even if you replace a physical tag.')) ?></p>
        <p><code><?= e($publicUrl) ?></code></p>
        <div class="btn-group">
            <button type="button" class="btn btn-ghost" data-copy="<?= e($publicUrl) ?>" data-copy-label="<?= e(t('tag.copied', 'Copied')) ?>">
                <i class="fa-solid fa-copy" aria-hidden="true"></i><span><?= e(t('tag.copy_link', 'Copy link')) ?></span>
            </button>
            <a class="btn btn-quiet" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e(t('dashboard.open_public', 'Public page')) ?></a>
        </div>
    </div>

    <div class="tag-piece">
        <div class="tag-piece-head">
            <h3><i class="fa-solid fa-qrcode" aria-hidden="true"></i><?= e(t('tag.qr_title', 'QR code')) ?></h3>
            <div class="btn-group">
                <a class="btn btn-ghost btn-sm" href="<?= e($pngUrl) ?>"><?= e(t('tag.download_png', 'Download PNG')) ?></a>
                <a class="btn btn-ghost btn-sm" href="<?= e($svgUrl) ?>"><?= e(t('tag.download_svg', 'Download SVG')) ?></a>
            </div>
        </div>
        <p><?= e(t('tag.qr_hint', 'Use the SVG version for printing: it stays sharp at any size. Keep the white border, scanners need it.')) ?></p>
        <div class="qr-preview"><?= $qrSvg ?></div>
    </div>

    <div class="tag-piece">
        <div class="tag-piece-head">
            <h3><i class="fa-solid fa-wifi" aria-hidden="true"></i><?= e(t('tag.nfc_title', 'NFC payload')) ?></h3>
            <div class="btn-group">
                <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($nfcPayload) ?>" data-copy-label="<?= e(t('tag.copied', 'Copied')) ?>">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i><span><?= e(t('tag.copy_nfc', 'Copy text')) ?></span>
                </button>
                <a class="btn btn-ghost btn-sm" href="<?= e($nfcUrl) ?>"><?= e(t('tag.download_txt', 'Download .txt')) ?></a>
            </div>
        </div>
        <p><?= e(t('tag.nfc_hint', 'Write this as a text or URL record with any NFC writer app. It contains only what the chip can safely show to anyone.')) ?></p>
        <pre class="nfc-payload"><?= e($nfcPayload) ?></pre>
        <p class="form-hint">
            <?= e(sprintf(t('tag.nfc_size', '%d bytes written.'), $nfcBytes)) ?>
            <?php if ($chipHint !== null): ?>
                <?= e(sprintf(t('tag.nfc_fits', 'Fits on %s and larger chips.'), $chipHint)) ?>
            <?php endif; ?>
        </p>
        <ol class="form-hint">
            <li><?= e(t('tag.nfc_step1', 'Open an NFC writing app on your phone.')) ?></li>
            <li><?= e(t('tag.nfc_step2', 'Create a new text or URL record and paste the payload above.')) ?></li>
            <li><?= e(t('tag.nfc_step3', 'Hold the phone to the chip, then test it once before attaching the tag.')) ?></li>
        </ol>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2><?= e(t('tag.list_title', 'Physical tags')) ?></h2>
            <form method="post" action="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>" class="btn-group">
                <?= dat_csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
                <input type="hidden" name="action" value="add_tag">
                <select name="tag_type" aria-label="<?= e(t('tag.add_label', 'Tag type')) ?>">
                    <?php foreach (dat_tag_types() as $typeId => $type): ?>
                        <?php if ((int) $typeId === DAT_TAG_TYPE_RFID) continue; ?>
                        <option value="<?= (int) $typeId ?>"><?= e($type['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-ghost"><?= e(t('tag.add', 'Add tag')) ?></button>
            </form>
        </div>

        <?php if (!$tags): ?>
            <p class="form-hint"><?= e(t('tag.none', 'No physical tag recorded yet. Add one for each chip or printed code you attach.')) ?></p>
        <?php else: ?>
            <?php foreach ($tags as $tag): ?>
                <?php $tagMeta = dat_tag_type_meta($tag['type']); $active = (int) $tag['status'] === DAT_TAG_STATUS_ACTIVE; ?>
                <div class="tag-row">
                    <div>
                        <strong><i class="<?= e($tagMeta['icon']) ?>" aria-hidden="true"></i> <?= e($tag['label'] ?: $tagMeta['label']) ?></strong>
                        <p class="form-hint" style="margin-top:2px">
                            <code><?= e($tag['url']) ?></code> &middot; <?= e(date('d.m.Y', strtotime($tag['created_at']))) ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <?= $active
                            ? '<span class="pill pill-ok">' . e(dat_tag_status_label($tag['status'])) . '</span>'
                            : '<span class="pill pill-muted">' . e(dat_tag_status_label($tag['status'])) . '</span>' ?>
                        <?php if ($active): ?>
                            <form method="post" action="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"
                                  data-confirm="<?= e(t('tag.replace_confirm', 'Create a replacement tag? The old tag stops working.')) ?>">
                                <?= dat_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
                                <input type="hidden" name="action" value="replace_tag">
                                <input type="hidden" name="tag_id" value="<?= e($tag['id']) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('tag.replace', 'Replace')) ?></button>
                            </form>
                            <form method="post" action="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"
                                  data-confirm="<?= e(t('tag.disable_confirm', 'Disable this tag? The public link stays the same.')) ?>">
                                <?= dat_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
                                <input type="hidden" name="action" value="disable_tag">
                                <input type="hidden" name="tag_id" value="<?= e($tag['id']) ?>">
                                <button type="submit" class="btn btn-quiet btn-sm"><?= e(t('tag.disable', 'Disable')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
<?php
dat_page_end();

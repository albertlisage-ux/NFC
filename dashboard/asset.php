<?php
/**
 * Asset detail: status controls, details, photos and the danger zone.
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

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } elseif ($action === 'status') {
        $status = (int) ($_POST['status'] ?? 0);
        if (dat_set_asset_status($asset['id'], $user['id'], $status)) {
            dat_flash_set('success', t('asset.status_saved', 'Status updated.'));
        } elseif (dat_db_available()) {
            $errors[] = t('error.status_failed', 'The status could not be changed.');
        }
    } elseif ($action === 'upload') {
        $result = dat_store_upload($_FILES['photo'] ?? []);
        if ($result['success']) {
            dat_attach_image($asset['id'], $result['object_key'], $_FILES['photo']['name'] ?? null);
            dat_flash_set('success', t('asset.photo_added', 'Photo added. Location data was removed from the file.'));
        } elseif (!empty($result['errors'])) {
            $errors = $result['errors'];
        }
    } elseif ($action === 'delete_image') {
        if (dat_delete_image((string) ($_POST['image_id'] ?? ''), $asset['id'], $user['id'])) {
            dat_flash_set('success', t('asset.photo_deleted', 'Photo removed.'));
        }
    } elseif ($action === 'delete') {
        if (dat_soft_delete_asset($asset['id'], $user['id'])) {
            dat_flash_set('info', t('asset.deleted', 'Asset deactivated. Its tag link now reports that the tag is no longer active.'));
            header('Location: ' . dat_url('dashboard/index.php'));
            exit;
        }
    }

    $asset = dat_asset_for_owner($assetId, $user['id']) ?? $asset;
}

$images = dat_asset_images($asset['id']);
$tags = dat_asset_tags($asset['id']);
$metadata = dat_asset_metadata($asset);
$typeMeta = dat_asset_type_meta($asset['type']);
$unread = dat_unread_message_count($user['id']);

dat_page_start([
    'title' => $asset['name'] . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => $unread,
]);
?>
<main id="main" class="shell">
    <?= dat_flash_render() ?>

    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">
            <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="page-head">
        <div>
            <h1><?= e($typeMeta['emoji']) ?> <?= e($asset['name']) ?></h1>
            <p><?= e(dat_asset_type_label($asset['type'])) ?> <?= dat_status_pill($asset['status']) ?></p>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/asset-edit.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.edit', 'Edit')) ?></a>
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.tags', 'Tags')) ?></a>
            <a class="btn btn-primary" href="<?= e(dat_tag_url($asset['public_id'])) ?>" target="_blank" rel="noopener"><?= e(t('dashboard.open_public', 'Public page')) ?></a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2><?= e(t('asset.status_title', 'Status')) ?></h2>
            <span class="form-hint"><?= e(dat_asset_status_meta($asset['status'])['hint']) ?></span>
        </div>
        <form method="post" action="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>" class="btn-group">
            <?= dat_csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
            <input type="hidden" name="action" value="status">
            <?php foreach (dat_owner_selectable_statuses() as $statusId): ?>
                <?php $meta = dat_asset_status_meta($statusId); ?>
                <button type="submit" name="status" value="<?= (int) $statusId ?>"
                        class="btn <?= (int) $asset['status'] === $statusId ? 'btn-primary' : 'btn-ghost' ?>">
                    <i class="<?= e($meta['icon']) ?>" aria-hidden="true"></i>
                    <?= e($meta['label']) ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="panel">
        <h2><?= e(t('asset.details_title', 'Details')) ?></h2>
        <dl class="detail-list">
            <div><dt><?= e(t('asset.public_link', 'Public link')) ?></dt><dd><code><?= e(dat_tag_url($asset['public_id'])) ?></code></dd></div>
            <div><dt><?= e(t('asset.public_id', 'Public ID')) ?></dt><dd><code><?= e($asset['public_id']) ?></code></dd></div>
            <?php if (!empty($asset['description'])): ?>
                <div><dt><?= e(t('asset.description', 'Description')) ?></dt><dd><?= nl2br(e($asset['description'])) ?></dd></div>
            <?php endif; ?>
            <?php foreach (dat_metadata_fields($asset['type']) as $field): ?>
                <?php if (!isset($metadata[$field['key']]) || $metadata[$field['key']] === '') continue; ?>
                <div>
                    <dt>
                        <?= e($field['label']) ?>
                        <?php if (empty($field['public'])): ?><span class="pill pill-muted"><?= e(t('asset.private', 'private')) ?></span><?php endif; ?>
                    </dt>
                    <dd><?= e($metadata[$field['key']]) ?></dd>
                </div>
            <?php endforeach; ?>
            <div><dt><?= e(t('asset.tags_count', 'Tags')) ?></dt><dd><?= count($tags) ?> <?= e(t('asset.tags_unit', 'attached')) ?></dd></div>
            <div><dt><?= e(t('asset.created', 'Created')) ?></dt><dd><?= e(date('d.m.Y H:i', strtotime($asset['created_at']))) ?></dd></div>
        </dl>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2><?= e(t('asset.photos_title', 'Photos')) ?></h2>
            <span class="form-hint"><?= e(t('asset.photos_hint', 'JPEG, PNG or WebP up to 10 MB. Metadata and location data are stripped on upload.')) ?></span>
        </div>

        <?php if ($images): ?>
            <div class="tag-photos">
                <?php foreach ($images as $index => $image): ?>
                    <img src="<?= e(dat_upload_url($image['object_key'])) ?>" alt="<?= e($asset['name']) ?>" <?= $index === 0 ? 'class="tag-photo-lead"' : '' ?> loading="lazy">
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>" class="btn-group">
                <?= dat_csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
                <input type="hidden" name="action" value="delete_image">
                <?php foreach ($images as $image): ?>
                    <button type="submit" name="image_id" value="<?= e($image['id']) ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                        <?= e(date('d.m.Y', strtotime($image['created_at']))) ?>
                    </button>
                <?php endforeach; ?>
            </form>
        <?php else: ?>
            <p class="form-hint"><?= e(t('asset.no_photos', 'No photo yet. A photo helps a finder recognise the asset immediately.')) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>" enctype="multipart/form-data" class="btn-group">
            <?= dat_csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="btn btn-ghost"><?= e(t('asset.upload', 'Upload photo')) ?></button>
        </form>
    </div>

    <div class="panel">
        <h2><?= e(t('asset.danger_title', 'Deactivate this asset')) ?></h2>
        <p><?= e(t('asset.danger_body', 'The public link keeps working but reports that the tag is no longer active. Nothing is deleted from the database.')) ?></p>
        <form method="post" action="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"
              data-confirm="<?= e(t('asset.delete_confirm', 'Deactivate this asset? Its tag link will report that it is no longer active.')) ?>">
            <?= dat_csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($asset['id']) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger"><?= e(t('asset.delete_submit', 'Deactivate asset')) ?></button>
        </form>
    </div>
</main>
<?php
dat_page_end();

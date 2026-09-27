<?php
/**
 * Edit an asset. Ownership is enforced in the query, not by trusting the id.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/asset-form.php';

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
    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } else {
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'status' => (int) ($_POST['status'] ?? $asset['status']),
            'metadata' => is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : [],
            'type' => (int) ($_POST['type'] ?? $asset['type']),
        ];

        if ($payload['name'] === '') {
            $errors[] = t('error.asset_name', 'Please give the asset a name.');
        }

        // A type change replaces the detail set, so migrate what still applies.
        if ((int) $payload['type'] !== (int) $asset['type'] && in_array((int) $payload['type'], dat_asset_type_ids(), true)) {
            dat_exec(
                'UPDATE ' . dat_table('assets') . ' SET type = ? WHERE id = ? AND owner_id = ?',
                [(int) $payload['type'], $asset['id'], $user['id']]
            );
            $asset = dat_asset_for_owner($asset['id'], $user['id']);
        }

        if (!$errors) {
            if (dat_update_asset($asset['id'], $user['id'], $payload)) {
                dat_flash_set('success', t('asset.updated', 'Asset updated.'));
                header('Location: ' . dat_url('dashboard/asset.php') . '?id=' . urlencode($asset['id']));
                exit;
            }
            $errors[] = t('error.asset_update', 'The changes could not be saved. Please try again.');
        }
    }
}

dat_page_start([
    'title' => t('asset.edit_title', 'Edit asset') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => dat_unread_message_count($user['id']),
]);
?>
<main id="main" class="shell">
    <div class="page-head">
        <div>
            <h1><?= e(t('asset.edit_title', 'Edit asset')) ?></h1>
            <p><?= e($asset['name']) ?> &middot; <code><?= e($asset['public_id']) ?></code></p>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.view', 'View')) ?></a>
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/tags.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e(t('dashboard.tags', 'Tags')) ?></a>
        </div>
    </div>

    <?php dat_asset_form([
        'action' => dat_url('dashboard/asset-edit.php') . '?id=' . urlencode($asset['id']),
        'submit_label' => t('asset.save', 'Save changes'),
        'asset' => $asset,
        'errors' => $errors,
        'values' => [],
        'show_status' => true,
        'cancel_url' => dat_url('dashboard/asset.php') . '?id=' . urlencode($asset['id']),
    ]); ?>
</main>
<?php
dat_page_end();

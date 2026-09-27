<?php
/**
 * Create an asset. The portal generates the public ID, the QR tag and the NFC
 * payload in one step; the owner only supplies the content.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/asset-form.php';

$user = dat_require_login();
$errors = [];
$values = ['type' => DAT_ASSET_TYPE_PET, 'name' => '', 'description' => '', 'metadata' => []];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } else {
        $values = [
            'type' => (int) ($_POST['type'] ?? DAT_ASSET_TYPE_PET),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'metadata' => is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : [],
        ];

        if ($values['name'] === '') {
            $errors[] = t('error.asset_name', 'Please give the asset a name.');
        }
        if (!in_array((int) $values['type'], dat_asset_type_ids(), true)) {
            $errors[] = t('error.asset_type', 'Please choose an asset type.');
        }

        if (!$errors) {
            $asset = dat_create_asset(
                $user['id'],
                (int) $values['type'],
                $values['name'],
                $values['description'],
                $values['metadata']
            );

            if ($asset === null) {
                $errors[] = t('error.asset_create', 'The asset could not be created. Please try again.');
            } else {
                dat_flash_set('success', t('asset.created', 'Asset created. Download the QR code and write the NFC tag whenever you like.'));
                header('Location: ' . dat_url('dashboard/tags.php') . '?id=' . urlencode($asset['id']) . '&created=1');
                exit;
            }
        }
    }
}

dat_page_start([
    'title' => t('asset.create_title', 'Add asset') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => dat_unread_message_count($user['id']),
]);
?>
<main id="main" class="shell">
    <div class="page-head">
        <div>
            <h1><?= e(t('asset.create_title', 'Add asset')) ?></h1>
            <p><?= e(t('asset.create_subtitle', 'The public link is generated from a random ID and stays the same for the life of the asset.')) ?></p>
        </div>
    </div>

    <?php dat_asset_form([
        'action' => dat_url('dashboard/assets-new.php'),
        'submit_label' => t('asset.create_submit', 'Create asset'),
        'asset' => null,
        'errors' => $errors,
        'values' => $values,
        'cancel_url' => dat_url('dashboard/index.php'),
    ]); ?>
</main>
<?php
dat_page_end();

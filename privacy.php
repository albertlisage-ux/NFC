<?php
/**
 * Privacy page. Describes what this application actually stores, what each
 * asset type publishes, and how long messages are kept. It is not legal advice.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;

// One row per asset type, derived from the same field definitions used when
// rendering a tag page.
$typeFields = [];
foreach (dat_asset_types() as $type) {
    $public = [];
    $private = [];
    foreach ($type['fields'] as $field) {
        if (!empty($field['public'])) {
            $public[] = $field['label'];
        } else {
            $private[] = $field['label'];
        }
    }
    $typeFields[] = ['label' => $type['label'], 'public' => $public, 'private' => $private];
}

dat_page_start([
    'title' => t('privacy.title', 'Privacy') . ' | ' . PORTAL_NAME,
    'description' => t('privacy.meta', 'What the Digital Asset Tag Portal stores, what a public tag page shows, and how long messages are kept.'),
    'canonical' => dat_url('privacy'),
    'unread' => $unread,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('privacy.title', 'Privacy')) ?></p>
            <h1><?= e(t('privacy.h1', 'Data minimisation is the design rule here')) ?></h1>
            <p class="section-lead"><?= e(t('privacy.subtitle', 'Data minimisation is the design rule here, not an afterthought.')) ?></p>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('privacy.stored_title', 'What is stored')) ?></h2>
            <dl class="detail-list">
                <div><dt><?= e(t('privacy.stored_account', 'Account')) ?></dt><dd><?= e(t('privacy.stored_account_value', 'Email address, display name if you provide one, password hash, timestamps.')) ?></dd></div>
                <div><dt><?= e(t('privacy.stored_asset', 'Asset')) ?></dt><dd><?= e(t('privacy.stored_asset_value', 'Type, name, description, the details you enter, status, public ID.')) ?></dd></div>
                <div><dt><?= e(t('privacy.stored_tag', 'Tags')) ?></dt><dd><?= e(t('privacy.stored_tag_value', 'Tag type, label, status and the public URL written to the chip.')) ?></dd></div>
                <div><dt><?= e(t('privacy.stored_photo', 'Photos')) ?></dt><dd><?= e(t('privacy.stored_photo_value', 'Re-encoded on upload; EXIF metadata and GPS coordinates are removed.')) ?></dd></div>
                <div><dt><?= e(t('privacy.stored_message', 'Messages')) ?></dt><dd><?= e(t('privacy.stored_message_value', 'Message text, an anonymous sender token and an expiry date. No finder contact data.')) ?></dd></div>
                <div><dt><?= e(t('privacy.stored_logs', 'Technical logs')) ?></dt><dd><?= e(t('privacy.stored_logs_value', 'Login attempts with a salted IP hash, so rate limiting works without storing raw addresses in the audit table.')) ?></dd></div>
            </dl>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('privacy.fields_title', 'What each asset type publishes')) ?></h2>
            <p class="section-lead"><?= e(t('privacy.fields_body', 'The split below is the one the portal enforces when it renders a tag page. Private fields are stored for you and filtered out of every public view.')) ?></p>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('privacy.table_type', 'Asset type')) ?></th>
                            <th scope="col"><?= e(t('privacy.table_public', 'Published on the tag page')) ?></th>
                            <th scope="col"><?= e(t('privacy.table_private', 'Stored, never published')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($typeFields as $row): ?>
                            <tr>
                                <th scope="row"><?= e($row['label']) ?></th>
                                <td>
                                    <?= e(t('common.name_type_description', 'Name, type, description, photos, status')) ?>
                                    <?php if ($row['public']): ?>, <?= e(implode(', ', $row['public'])) ?><?php endif; ?>
                                </td>
                                <td>
                                    <?= $row['private']
                                        ? e(implode(', ', $row['private']))
                                        : e(t('privacy.table_private_none', 'No type specific fields')) ?>
                                    <span class="table-note"><?= e(t('privacy.table_private_note', 'Owner account data is never published for any type.')) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="form-hint">
                <?= e(t('privacy.fields_example', 'Want to see it in practice? Every example on the use cases page shows both lists next to a live tag page.')) ?>
                <a href="<?= e(dat_url('use-cases')) ?>"><?= e(t('nav.usecases', 'Use cases')) ?></a>
            </p>
        </div>
    </section>

    <section class="section">
        <div class="shell split-grid">
            <div>
                <h2><?= e(t('privacy.public_title', 'What a public tag page shows')) ?></h2>
                <p><?= e(t('privacy.public_body', 'Asset name, type, description, the details you marked as public, your photos and the status. Owner name, email address, phone number and postal address are never rendered on a tag page. Tag pages additionally ask search engines not to index them.')) ?></p>
            </div>
            <div>
                <h2><?= e(t('privacy.retention_title', 'Retention')) ?></h2>
                <p><?= e(sprintf(t('privacy.retention_body', 'Anonymous messages expire automatically after %d days. Deactivating an asset keeps its public link answering with "no longer active" instead of exposing a 404, and nothing is hard-deleted by the owner interface.'), PORTAL_MESSAGE_RETENTION_DAYS)) ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('privacy.rights_title', 'Your rights and contact')) ?></h2>
            <p><?= e(t('privacy.rights_body', 'You can ask for a copy or deletion of your account data at any time. Before this portal is used commercially, the operator must complete the imprint and add a named contact for data protection requests.')) ?></p>
            <div class="btn-group">
                <a class="btn btn-ghost" href="<?= e(dat_url('docs/imprint.php')) ?>"><?= e(t('footer.imprint', 'Imprint')) ?></a>
                <a class="btn btn-ghost" href="<?= e(dat_url('how-it-works')) ?>"><?= e(t('nav.how', 'How it works')) ?></a>
            </div>
            <p class="form-hint"><?= e(t('privacy.legal_note', 'This page describes the technical behaviour of the application. It is not legal advice.')) ?></p>
        </div>
    </section>
</main>
<?php
dat_page_end();

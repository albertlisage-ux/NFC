<?php
/**
 * Privacy summary page. The wording describes what this application actually
 * stores; it is not a substitute for legal advice before commercial launch.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

dat_page_start([
    'title' => t('privacy.title', 'Privacy') . ' | ' . PORTAL_NAME,
    'description' => t('privacy.meta', 'What the Digital Asset Tag Portal stores, what a public tag page shows, and how long messages are kept.'),
    'unread' => 0,
]);
?>
<main id="main" class="shell">
    <div class="page-head">
        <div>
            <h1><?= e(t('privacy.title', 'Privacy')) ?></h1>
            <p><?= e(t('privacy.subtitle', 'Data minimisation is the design rule here, not an afterthought.')) ?></p>
        </div>
    </div>

    <div class="panel">
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

    <div class="panel">
        <h2><?= e(t('privacy.public_title', 'What a public tag page shows')) ?></h2>
        <p><?= e(t('privacy.public_body', 'Asset name, type, description, the details you marked as public, your photos and the status. Owner name, email address, phone number and postal address are never rendered on a tag page. Tag pages additionally ask search engines not to index them.')) ?></p>
    </div>

    <div class="panel">
        <h2><?= e(t('privacy.retention_title', 'Retention')) ?></h2>
        <p><?= e(sprintf(t('privacy.retention_body', 'Anonymous messages expire automatically after %d days. Deactivating an asset keeps its public link answering with "no longer active" instead of exposing a 404, and nothing is hard-deleted by the owner interface.'), PORTAL_MESSAGE_RETENTION_DAYS)) ?></p>
    </div>

    <div class="panel">
        <h2><?= e(t('privacy.rights_title', 'Your rights and contact')) ?></h2>
        <p><?= e(t('privacy.rights_body', 'You can ask for a copy or deletion of your account data at any time. Before this portal is used commercially, the operator must complete the imprint and add a named contact for data protection requests.')) ?></p>
        <p class="form-hint"><?= e(t('privacy.legal_note', 'This page describes the technical behaviour of the application. It is not legal advice.')) ?></p>
    </div>
</main>
<?php
dat_page_end();

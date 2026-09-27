<?php
/**
 * Home page: what the portal is, how a tag works, and where to start.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;

/**
 * A real tag page preview: the same component that renders /t/{publicId}, fed
 * with sample data. Nothing here is a mockup image.
 */
$sampleAsset = dat_public_asset([
    'public_id' => 'DEMTAG24',
    'type' => DAT_ASSET_TYPE_PET,
    'name' => t('home.sample.name', 'Lucky'),
    'description' => t('home.sample.description', 'Friendly dog, chipped, speaks German and English.'),
    'status' => DAT_ASSET_STATUS_ACTIVE,
    'metadata_json' => json_encode([
        'animal' => t('field.animal.value_dog', 'Dog'),
        'breed' => 'Golden Retriever',
        'color' => t('home.sample.colour', 'Golden'),
    ], JSON_UNESCAPED_UNICODE),
]);

$demoTagUrl = dat_tag_url('DEMTAG24');
$demoQr = dat_qr_svg($demoTagUrl, 6, 3);
$assetTypes = dat_asset_types();

dat_page_start([
    'title' => PORTAL_NAME . ' | ' . t('home.title_suffix', 'One tag for pets, bikes, vehicles and more'),
    'description' => t('home.meta', 'Give pets, bicycles, vehicles, clothing, everyday items and industrial equipment a stable digital identity that any phone can read.'),
    'unread' => $unread,
]);
?>
<main id="main">
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><?= e(t('home.eyebrow', 'Digital asset tags')) ?></p>
                <h1><?= e(t('home.h1_line1', 'One tag. One identity.')) ?><br><span class="hero-accent"><?= e(t('home.h1_line2', 'One portal.')) ?></span></h1>
                <p class="hero-lead"><?= e(t('home.lead', 'A single stable link for the things you care about. Tap it with a phone and the finder sees what the asset is, and can reach you without ever seeing your contact details.')) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(dat_url('account/register.php')) ?>">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <?= e(t('home.cta_create', 'Create an asset')) ?>
                    </a>
                    <a class="btn btn-ghost btn-lg" href="<?= e($user !== null ? dat_url('dashboard/index.php') : dat_url('account/login.php')) ?>">
                        <?= e($user !== null ? t('nav.dashboard', 'Dashboard') : t('nav.login', 'Log in')) ?>
                    </a>
                </div>
                <ul class="hero-facts">
                    <li><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><?= e(t('home.fact_no_app', 'No app required')) ?></li>
                    <li><i class="fa-solid fa-link" aria-hidden="true"></i><?= e(t('home.fact_stable', 'Link never changes')) ?></li>
                    <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= e(t('home.fact_eu', 'Hosted in Germany')) ?></li>
                </ul>
            </div>

            <div class="hero-visual">
                <div class="phone-frame">
                    <div class="phone-bar" aria-hidden="true"><span></span></div>
                    <div class="phone-screen">
                        <p class="phone-scanline"><i class="fa-solid fa-nfc-symbol" aria-hidden="true"></i><?= e(t('home.preview_label', 'Tag scanned just now')) ?></p>
                        <div class="phone-card">
                            <?php dat_tag_card($sampleAsset, [], ['compact' => true, 'heading_level' => 2]); ?>
                        </div>
                    </div>
                </div>

                <figure class="qr-figure">
                    <div class="qr-frame"><?= $demoQr ?></div>
                    <figcaption>
                        <?= e(t('home.qr_caption', 'A real tag link, encoded by this portal.')) ?>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="section section-steps" id="how-it-works">
        <div class="shell">
            <h2><?= e(t('home.steps_title', 'How a tag works')) ?></h2>
            <p class="section-lead"><?= e(t('home.steps_lead', 'Three steps, no accounts for the finder, no app installs for anyone.')) ?></p>
            <ol class="steps">
                <li class="step">
                    <span class="step-index" aria-hidden="true">1</span>
                    <h3><?= e(t('home.step1_title', 'Create the asset')) ?></h3>
                    <p><?= e(t('home.step1_body', 'Choose a type, add a name and the details that help a finder recognise it. Your account stays minimal: email and password.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">2</span>
                    <h3><?= e(t('home.step2_title', 'Write the tag')) ?></h3>
                    <p><?= e(t('home.step2_body', 'Download the QR code as PNG or SVG, or copy the short NFC text and write it to an NTAG chip with any NFC writer app.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">3</span>
                    <h3><?= e(t('home.step3_title', 'Scan, and get a message')) ?></h3>
                    <p><?= e(t('home.step3_body', 'Whoever finds the asset opens the same stable link. If they write to you, the message lands in your inbox. They never learn who you are.')) ?></p>
                </li>
            </ol>
        </div>
    </section>

    <section class="section section-types" id="use-cases">
        <div class="shell">
            <h2><?= e(t('home.types_title', 'One platform, six asset types')) ?></h2>
            <p class="section-lead"><?= e(t('home.types_lead', 'Every type keeps its own fields, private by default, while sharing one public link format.')) ?></p>
            <div class="type-grid">
                <?php foreach ($assetTypes as $typeId => $type): ?>
                    <article class="type-tile">
                        <span class="type-tile-mark" aria-hidden="true"><?= e($type['emoji']) ?></span>
                        <h3><?= e($type['label']) ?></h3>
                        <p><?= e(t('type.' . $type['key'] . '.example', $type['label'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-privacy" id="privacy">
        <div class="shell privacy-grid">
            <div class="privacy-copy">
                <h2><?= e(t('home.privacy_title', 'What a finder sees, and what stays private')) ?></h2>
                <p><?= e(t('home.privacy_body', 'A lost-item page is public by nature. That is exactly why the portal is built around data minimisation: the page describes the asset, never the owner.')) ?></p>
                <p class="privacy-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <?= e(t('home.privacy_note', 'NFC chips can be read by anyone standing next to them, so no personal contact data is ever written to a tag.')) ?>
                </p>
                <a class="btn btn-ghost" href="<?= e(dat_url('docs/privacy.php')) ?>">
                    <?= e(t('home.privacy_link', 'Read the privacy summary')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="privacy-columns">
                <div class="privacy-column privacy-public">
                    <h3><i class="fa-solid fa-eye" aria-hidden="true"></i><?= e(t('home.privacy_public', 'Public on the tag page')) ?></h3>
                    <ul>
                        <li><?= e(t('home.privacy_public_1', 'Asset name and type')) ?></li>
                        <li><?= e(t('home.privacy_public_2', 'Description you choose to publish')) ?></li>
                        <li><?= e(t('home.privacy_public_3', 'Selected details, such as breed, brand or colour')) ?></li>
                        <li><?= e(t('home.privacy_public_4', 'Photos you upload')) ?></li>
                        <li><?= e(t('home.privacy_public_5', 'Lost, found or active status')) ?></li>
                    </ul>
                </div>
                <div class="privacy-column privacy-private">
                    <h3><i class="fa-solid fa-lock" aria-hidden="true"></i><?= e(t('home.privacy_private', 'Never public')) ?></h3>
                    <ul>
                        <li><?= e(t('home.privacy_private_1', 'Your name and email address')) ?></li>
                        <li><?= e(t('home.privacy_private_2', 'Phone number and postal address')) ?></li>
                        <li><?= e(t('home.privacy_private_3', 'Serial numbers, VIN and licence plates')) ?></li>
                        <li><?= e(t('home.privacy_private_4', 'Internal database identifiers')) ?></li>
                        <li><?= e(t('home.privacy_private_5', 'Message contents outside your inbox')) ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-owner">
        <div class="shell owner-grid">
            <div class="owner-copy">
                <h2><?= e(t('home.owner_title', 'Your dashboard keeps the controls')) ?></h2>
                <ul class="owner-list">
                    <li>
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('home.owner_lost', 'Mark an asset as lost')) ?></strong>
                            <p><?= e(t('home.owner_lost_body', 'The public page switches to a clear request for help. No need to edit anything by hand.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('home.owner_replace', 'Replace a tag, keep the link')) ?></strong>
                            <p><?= e(t('home.owner_replace_body', 'Lost the collar tag? Disable it and write a new one. The public link and the asset history stay exactly the same.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('home.owner_inbox', 'Answer anonymously')) ?></strong>
                            <p><?= e(t('home.owner_inbox_body', 'Messages are threads. Reply inside the portal and the finder sees your answer without either side sharing contact details.')) ?></p>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="owner-panel">
                <div class="owner-panel-head">
                    <span><?= e(t('home.owner_panel', 'Asset overview')) ?></span>
                    <span class="pill pill-neutral"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><?= e(t('home.owner_panel_scope', 'Your account only')) ?></span>
                </div>
                <table class="owner-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?= e(t('home.owner_col_link', 'Public link')) ?></th>
                            <td><code><?= e(dat_tag_url('DEMTAG24')) ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= e(t('home.owner_col_tags', 'Tags')) ?></th>
                            <td><?= e(t('home.owner_col_tags_value', '1 QR code, 1 NFC chip')) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= e(t('home.owner_col_status', 'Status')) ?></th>
                            <td><?= dat_status_pill(DAT_ASSET_STATUS_ACTIVE) ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?= e(t('home.owner_col_storage', 'Photos')) ?></th>
                            <td><?= e(t('home.owner_col_storage_value', 'EXIF data removed on upload')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="shell cta-band-inner">
            <div>
                <h2><?= e(t('home.cta_title', 'Start with one tag')) ?></h2>
                <p><?= e(t('home.cta_body', 'Create an account, add an asset and download the QR code in a couple of minutes.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e($user !== null ? dat_url('dashboard/assets-new.php') : dat_url('account/login.php')) ?>">
                    <?= e($user !== null ? t('home.cta_add', 'Add an asset') : t('nav.login', 'Log in')) ?>
                </a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

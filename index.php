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

$explore = [
    [
        'url' => dat_url('how-it-works'),
        'icon' => 'fa-solid fa-list-check',
        'eyebrow' => t('home.explore1_eyebrow', 'Step by step'),
        'title' => t('nav.how', 'How it works'),
        'body' => t('home.explore1_body', 'Create the asset, write the QR code or NFC chip, and see what a finder gets on screen. Includes the status flow and the tag replacement rules.'),
        'cta' => t('home.explore_read', 'Read the guide'),
    ],
    [
        'url' => dat_url('use-cases'),
        'icon' => 'fa-solid fa-layer-group',
        'eyebrow' => t('home.explore2_eyebrow', 'Six asset types'),
        'title' => t('nav.usecases', 'Use cases'),
        'body' => t('home.explore2_body', 'One platform for pets, bicycles, vehicles, clothing, everyday items and industrial equipment, with a worked example and a live tag page for each.'),
        'cta' => t('home.explore_see', 'See the examples'),
    ],
    [
        'url' => dat_url('privacy'),
        'icon' => 'fa-solid fa-user-shield',
        'eyebrow' => t('home.explore3_eyebrow', 'Private by default'),
        'title' => t('nav.privacy', 'Privacy'),
        'body' => t('home.explore3_body', 'What is stored, what each asset type publishes, and what never leaves your dashboard. Written as a description of what the software actually does.'),
        'cta' => t('home.explore_read', 'Read the guide'),
    ],
];

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

    <section class="section section-explore">
        <div class="shell">
            <h2><?= e(t('home.explore_title', 'Where to go next')) ?></h2>
            <p class="section-lead"><?= e(t('home.explore_lead', 'The details live on their own pages: the walkthrough, the six asset types with worked examples, and the privacy rules.')) ?></p>
            <div class="explore-grid">
                <?php foreach ($explore as $card): ?>
                    <article class="explore-card">
                        <span class="explore-icon" aria-hidden="true"><i class="<?= e($card['icon']) ?>"></i></span>
                        <p class="eyebrow"><?= e($card['eyebrow']) ?></p>
                        <h3><?= e($card['title']) ?></h3>
                        <p><?= e($card['body']) ?></p>
                        <a class="explore-link" href="<?= e($card['url']) ?>">
                            <?= e($card['cta']) ?>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                <?php endforeach; ?>
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

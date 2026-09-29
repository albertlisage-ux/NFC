<?php
/**
 * Home page.
 *
 * The order answers the questions a visitor asks in sequence:
 *   1. What is this?            hero, with a real tag page on screen
 *   2. Show me it works.        live example tags you can open right now
 *   3. What can I buy?          the four product families
 *   4. How does it work?        three steps, link to the full guide
 *   5. Is it safe?              one privacy line, link to the details
 *   6. Get started.             the call to action
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

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

// Four live examples, one per product family.
$examples = [];
foreach (['menu_board', 'wristband', 'keychain', 'pet'] as $key) {
    $entry = dat_demo_entry($key);
    if ($entry !== null) {
        $examples[] = $entry;
    }
}

$categories = dat_asset_type_categories();

dat_page_start([
    'title' => t('home.title_suffix', 'NFC tags, QR codes and one permanent link'),
    'description' => t('home.meta', 'Menu boards, posters, wristbands, necklaces, lanyards, keychains and mini tags that open one permanent page, plus digital tags for pets and clothing. Create the tag and write the chip yourself.'),
    'canonical' => dat_url(''),
    'unread' => $unread,
]);

// Structured data: the catalogue as a list of products with their examples.
$catalogueList = [];
$position = 0;
foreach (dat_demo_catalog() as $entry) {
    $catalogueList[] = [
        '@type' => 'ListItem',
        'position' => ++$position,
        'name' => $entry['name'],
        'url' => dat_tag_url($entry['public_id']),
    ];
}
dat_json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => PORTAL_NAME,
    'url' => dat_url(''),
    'inLanguage' => dat_lang(),
    'hasPart' => [
        '@type' => 'ItemList',
        'name' => t('home.families_title', 'The range, in four families'),
        'itemListElement' => $catalogueList,
    ],
]);
?>
<main id="main">
    <section class="hero">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><?= e(t('home.eyebrow', 'Digital asset tags')) ?></p>
                <h1><?= e(t('home.h1_line1', 'One tag. One identity.')) ?><br><span class="hero-accent"><?= e(t('home.h1_line2', 'One portal.')) ?></span></h1>
                <p class="hero-lead"><?= e(t('home.lead', 'A menu board, a poster, a wristband, a keychain. Every product carries the same permanent link, and a tap with any phone opens the page behind it.')) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(dat_url('start')) ?>">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <?= e(t('start.title', 'Start with one tag')) ?>
                    </a>
                    <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('use-cases')) ?>">
                        <?= e(t('home.cta_products', 'See the products')) ?>
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

    <section class="section section-examples">
        <div class="shell">
            <h2><?= e(t('home.examples_title', 'Open a real tag')) ?></h2>
            <p class="section-lead"><?= e(t('home.examples_lead', 'Four of the demo tags, exactly as a customer sees them after tapping the chip or scanning the code. Nothing to install.')) ?></p>

            <ul class="example-strip">
                <?php foreach ($examples as $entry): ?>
                    <?php
                    $typeMeta = dat_asset_type_meta($entry['type']);
                    $chipImage = dat_product_image_url($entry['image']);
                    ?>
                    <li>
                        <a class="example-chip" href="<?= e(dat_tag_url($entry['public_id'])) ?>">
                            <span class="example-chip-mark" aria-hidden="true">
                                <?php if ($chipImage !== null): ?>
                                    <img src="<?= e($chipImage) ?>" alt="" loading="lazy" width="48" height="48">
                                <?php else: ?>
                                    <?= e($typeMeta['emoji']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="example-chip-body">
                                <strong><?= e($entry['name']) ?></strong>
                                <small><?= e($typeMeta['label']) ?> &middot; <?= e($entry['public_id']) ?></small>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="section section-families">
        <div class="shell">
            <h2><?= e(t('home.families_title', 'The range, in four families')) ?></h2>
            <p class="section-lead"><?= e(t('home.families_lead', 'Every product answers at the same kind of link, so a menu board, a wristband and a pet pendant behave exactly the same way when they are tapped.')) ?></p>

            <div class="family-grid">
                <?php foreach ($categories as $category): ?>
                    <?php $firstType = dat_asset_types()[$category['types'][0] ?? 0] ?? null; ?>
                    <article class="family-card">
                        <h3><?= e($category['label']) ?></h3>
                        <p><?= e($category['hint']) ?></p>
                        <ul class="family-list">
                            <?php foreach ($category['types'] as $typeId): ?>
                                <?php $type = dat_asset_types()[$typeId] ?? null; if ($type === null) continue; ?>
                                <li>
                                    <a href="<?= e(dat_url('use-cases')) ?>#type-<?= e($type['key']) ?>">
                                        <span aria-hidden="true"><?= e($type['emoji']) ?></span>
                                        <?= e($type['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="explore-link" href="<?= e(dat_url('use-cases') . ($firstType !== null ? '#type-' . $firstType['key'] : '')) ?>">
                            <?= e(t('home.family_link', 'See the examples')) ?>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('home.steps_title', 'How a tag works')) ?></h2>
            <p class="section-lead"><?= e(t('home.steps_lead', 'Three steps, no accounts for the finder, no app installs for anyone.')) ?></p>
            <ol class="steps">
                <li class="step">
                    <span class="step-index" aria-hidden="true">1</span>
                    <h3><?= e(t('home.step1_title', 'Create the tag')) ?></h3>
                    <p><?= e(t('home.step1_body', 'Pick a product, give it a name and add the details a stranger needs. Your account stays minimal: email and password.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">2</span>
                    <h3><?= e(t('home.step2_title', 'Write the chip')) ?></h3>
                    <p><?= e(t('home.step2_body', 'Download the QR code as PNG or SVG, or copy the short NFC text and write it with any NFC app such as NFC Tools.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">3</span>
                    <h3><?= e(t('home.step3_title', 'Scan, and get a message')) ?></h3>
                    <p><?= e(t('home.step3_body', 'Whoever taps the tag opens the same stable link. If they write to you, the message lands in your inbox and they never learn who you are.')) ?></p>
                </li>
            </ol>
            <div class="btn-group">
                <a class="btn btn-ghost" href="<?= e(dat_url('how-it-works')) ?>">
                    <?= e(t('home.steps_link', 'Read the full walkthrough')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a class="btn btn-ghost" href="<?= e(dat_url('write-a-tag')) ?>">
                    <?= e(t('home.write_link', 'Writing the chip with NFC Tools')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <div class="privacy-band">
                <div>
                    <h2><?= e(t('home.privacy_title', 'What a finder sees, and what stays private')) ?></h2>
                    <p><?= e(t('home.privacy_body', 'The page describes the product, never the owner. Wi-Fi passwords, campaign IDs and production batches are stored in your dashboard and filtered out of every public page.')) ?></p>
                </div>
                <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('privacy')) ?>">
                    <?= e(t('home.privacy_link', 'Read the privacy summary')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="shell cta-band-inner">
            <div>
                <h2><?= e(t('home.cta_title', 'Start with one tag')) ?></h2>
                <p><?= e(t('home.cta_body', 'Create it as a guest in a minute, download the QR code or copy the NFC text, and keep it by registering.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('start')) ?>"><?= e(t('start.title', 'Start with one tag')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(dat_url('use-cases')) ?>"><?= e(t('home.cta_products', 'See the products')) ?></a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

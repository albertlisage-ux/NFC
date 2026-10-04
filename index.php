<?php
/**
 * Home page, laid out like developer.apple.com/design/resources: a short page
 * header, then alternating bands, each one a heading, a subtitle and a grid of
 * tiles. A tile is a preview image, a short title, one line of grey text and a
 * list of blue text links.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;

$catalog = dat_demo_catalog();
$categories = dat_asset_type_categories();

$byType = [];
foreach ($catalog as $entry) {
    $byType[$entry['type']] = $entry;
}

$showcase = $catalog['keychain'] ?? reset($catalog);
$showcaseUrl = dat_tag_url($showcase['public_id']);
$showcaseQr = dat_qr_svg($showcaseUrl, 6, 3);
$startQr = dat_qr_svg(dat_url('start'), 5, 3);
$showcaseAsset = dat_public_asset(dat_demo_asset_row($showcase));

/*
 * The guest Wi-Fi tile shows the real, scannable code, so it is drawn here
 * rather than being a cropped picture of one.
 */
$extrasBoard = dat_demo_entry('menu_board');
$wifiPayload = dat_wifi_qr_payload(
    $extrasBoard['metadata']['wifi_network'] ?? '',
    $extrasBoard['metadata']['wifi_password'] ?? ''
);
$wifiQr = $wifiPayload !== null ? dat_qr_svg($wifiPayload, 6, 4) : '';

dat_page_start([
    'title' => t('home.title_suffix', 'NFC tags, QR codes and one permanent link'),
    'description' => t('home.meta', 'Menu boards, posters, wristbands, necklaces, lanyards, keychains and mini tags that open one permanent page, plus digital tags for pets and clothing. Create the tag and write the chip yourself.'),
    'canonical' => dat_url(''),
    'unread' => $unread,
]);

// Structured data: the catalogue as a list of products.
$catalogueList = [];
$position = 0;
foreach ($catalog as $entry) {
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
        'name' => t('home.families_title', 'Products'),
        'itemListElement' => $catalogueList,
    ],
]);
?>
<main id="main">
    <section class="section-band">
        <div class="section-content">
            <div class="grid">
                <div class="grid-item large-span-6 medium-span-12 small-span-12">
                    <h1><?= e(t('home.h1_line1', 'One tag. One identity.')) ?> <?= e(t('home.h1_line2', 'One portal.')) ?></h1>
                    <p class="tile-body" style="font-size:21px;line-height:1.35;max-width:34ch">
                        <?= e(t('home.lead', 'A menu board, a poster, a wristband, a keychain. Every product carries the same permanent link, and a tap with any phone opens the page behind it.')) ?>
                    </p>
                    <div class="demo-actions" style="margin-top:22px">
                        <a class="btn btn-primary btn-lg" href="<?= e(dat_url('start')) ?>"><?= e(t('start.title', 'Start with one tag')) ?></a>
                        <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('use-cases')) ?>"><?= e(t('home.cta_products', 'See the products')) ?></a>
                    </div>
                </div>

                <div class="grid-item large-span-6 medium-span-12 small-span-12">
                    <?php /*
                     * Two compact code rows instead of two half-width tiles: a
                     * full tag URL needs the width, and in a 6-column tile it
                     * used to spill over the neighbouring one.
                     */ ?>
                    <div class="qr-rows">
                        <div class="qr-row">
                            <div class="qr-row-code"><?= $showcaseQr ?></div>
                            <div class="qr-row-text">
                                <h5><?= e(t('home.qr_title', 'The tag page')) ?></h5>
                                <p class="tile-body"><?= e($showcaseUrl) ?></p>
                            </div>
                        </div>
                        <div class="qr-row">
                            <div class="qr-row-code"><?= $startQr ?></div>
                            <div class="qr-row-text">
                                <h5><?= e(t('home.qr_start_title', 'Your own tag')) ?></h5>
                                <p class="tile-body"><?= e(t('demo.step5_qr_note', 'Scan to create your own tag')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid" style="margin-top:44px">
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong>9</strong><?= e(t('home.stat_products', 'products')) ?></div></div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong>1</strong><?= e(t('home.stat_links', 'link format for all of them')) ?></div></div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile-stats"><div><strong>0</strong><?= e(t('home.stat_apps', 'apps to install')) ?></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-band band-alt" id="products">
        <div class="section-content">
            <div class="section-head">
                <h2><?= e(t('home.families_title', 'Products')) ?></h2>
                <p><?= e(t('home.families_lead', 'Every product answers at the same kind of link, so a menu board, a wristband and a pet pendant behave exactly the same way when they are tapped.')) ?></p>
            </div>

            <?php foreach ($categories as $category): ?>
                <h4 style="margin-bottom:16px"><?= e($category['label']) ?></h4>
                <div class="grid" style="margin-bottom:40px">
                    <?php foreach ($category['types'] as $typeId): ?>
                        <?php
                        $type = dat_asset_types()[$typeId] ?? null;
                        $entry = $byType[$typeId] ?? null;
                        if ($type === null || $entry === null) {
                            continue;
                        }
                        $image = dat_entry_image($entry);
                        ?>
                        <div class="grid-item large-span-4 medium-span-6 small-span-12">
                            <div class="tile">
                                <div class="tile-media tile-media-tall">
                                    <?php if ($image !== null): ?>
                                        <img src="<?= e($image) ?>" alt="<?= e($entry['name']) ?>" loading="lazy" width="1200" height="900">
                                    <?php else: ?>
                                        <i class="<?= e($type['icon']) ?> tile-glyph" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </div>
                                <h5><?= e($type['label']) ?></h5>
                                <p class="tile-body"><?= e($entry['name']) ?> &middot; <?= e($entry['description']) ?></p>
                                <div class="tile-links">
                                    <a class="text-link" href="<?= e(dat_url('use-cases') . '#type-' . $type['key']) ?>">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('home.link_example', 'Worked example')) ?></b>
                                    </a>
                                    <a class="text-link" href="<?= e(dat_tag_url($entry['public_id'])) ?>">
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><b><?= e(t('home.link_tag_page', 'Live tag page')) ?></b>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-band" id="how-it-works">
        <div class="section-content">
            <div class="section-head">
                <h2><?= e(t('home.steps_title', 'How a tag works')) ?></h2>
                <p><?= e(t('home.steps_lead', 'Three steps, no accounts for the finder, no app installs for anyone.')) ?></p>
            </div>
            <div class="grid">
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/10">
                            <?php $sceneCreate = dat_scene_picture('create', t('home.step1_alt', 'A phone in one hand, setting up a tag')); ?>
                            <?php if ($sceneCreate !== ''): ?>
                                <?= $sceneCreate ?>
                            <?php else: ?>
                                <i class="fa-solid fa-plus tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.step1_title', 'Create the tag')) ?></h5>
                        <p class="tile-body"><?= e(t('home.step1_body', 'Pick a product, give it a name and add the details a stranger needs. Your account stays minimal: email and password.')) ?></p>
                    </div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/10">
                            <?php $sceneWrite = dat_scene_picture('write', t('home.step2_alt', 'An NFC chip inside a printed label')); ?>
                            <?php if ($sceneWrite !== ''): ?>
                                <?= $sceneWrite ?>
                            <?php else: ?>
                                <i class="fa-solid fa-wifi tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.step2_title', 'Write the chip')) ?></h5>
                        <p class="tile-body"><?= e(t('home.step2_body', 'Download the QR code as PNG or SVG, or copy the short NFC text and write it with any NFC app such as NFC Tools.')) ?></p>
                    </div>
                </div>
                <div class="grid-item large-span-4 medium-span-4 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/10">
                            <?php $sceneScan = dat_scene_picture('scan', t('home.step3_alt', 'A code being scanned with a phone')); ?>
                            <?php if ($sceneScan !== ''): ?>
                                <?= $sceneScan ?>
                            <?php else: ?>
                                <i class="fa-solid fa-comments tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.step3_title', 'Scan, and get a message')) ?></h5>
                        <p class="tile-body"><?= e(t('home.step3_body', 'Whoever taps the tag opens the same stable link. If they write to you, the message lands in your inbox and they never learn who you are.')) ?></p>
                    </div>
                </div>
            </div>
            <div class="tile-links" style="margin-top:30px">
                <a class="text-link" href="<?= e(dat_url('how-it-works')) ?>">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('home.steps_link', 'Read the full walkthrough')) ?></b>
                </a>
                <a class="text-link" href="<?= e(dat_url('write-a-tag')) ?>">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('home.write_link', 'Writing the chip with NFC Tools')) ?></b>
                </a>
                <a class="text-link" href="<?= e(dat_url('demo')) ?>">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('home.demo_link', 'Live demo of the whole chain')) ?></b>
                </a>
            </div>
        </div>
    </section>

    <section class="section-band band-alt" id="also-built-in">
        <div class="section-content">
            <div class="section-head">
                <h2><?= e(t('demo.extras_title', 'Also built in')) ?></h2>
                <p><?= e(t('home.extras_lead', 'Details that decide whether a tag survives daily use.')) ?></p>
            </div>
            <div class="grid">
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media tile-media-code" style="aspect-ratio:16/9">
                            <?php if ($wifiQr !== ''): ?>
                                <span class="code-card" role="img" aria-label="<?= e(t('home.extra_wifi_alt', 'The guest Wi-Fi code, drawn as a scannable QR code')) ?>"><?= $wifiQr ?></span>
                            <?php else: ?>
                                <i class="fa-solid fa-wifi tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.extra_wifi_title', 'Guest Wi-Fi code')) ?></h5>
                        <p class="tile-body"><?= e(t('demo.extra_wifi', 'A guest Wi-Fi code for menu boards and posters, printable next to the menu code. Guests scan it with the camera and the phone joins the network.')) ?></p>
                    </div>
                </div>
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/9">
                            <?php $messagingImage = dat_extra_image('messaging'); ?>
                            <?php if ($messagingImage !== null): ?>
                                <img src="<?= e($messagingImage) ?>" alt="<?= e(t('home.extra_whatsapp_alt', 'A phone in someone\'s hand, ready to answer a message')) ?>" loading="lazy" width="1200" height="675">
                            <?php else: ?>
                                <i class="fa-brands fa-whatsapp tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.extra_whatsapp_title', 'WhatsApp, if the owner wants it')) ?></h5>
                        <p class="tile-body"><?= e(t('demo.extra_whatsapp', 'An optional WhatsApp button on the tag page, or a chip that opens WhatsApp directly for shops that only want chat.')) ?></p>
                    </div>
                </div>
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/9">
                            <?php $tagsImage = dat_extra_image('tags'); ?>
                            <?php if ($tagsImage !== null): ?>
                                <img src="<?= e($tagsImage) ?>" alt="<?= e(t('home.extra_replace_alt', 'An RFID tag of the kind a damaged chip is swapped for')) ?>" loading="lazy" width="1200" height="675">
                            <?php else: ?>
                                <i class="fa-solid fa-arrows-rotate tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.extra_replace_title', 'Replace a tag, keep the link')) ?></h5>
                        <p class="tile-body"><?= e(t('home.extra_replace_body', 'A damaged chip is swapped in the dashboard. The public address never changes, so everything already printed keeps working.')) ?></p>
                    </div>
                </div>
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/9">
                            <?php $privacyImage = dat_extra_image('privacy'); ?>
                            <?php if ($privacyImage !== null): ?>
                                <img src="<?= e($privacyImage) ?>" alt="<?= e(t('home.extra_privacy_alt', 'A padlock on a weathered door')) ?>" loading="lazy" width="1200" height="675">
                            <?php else: ?>
                                <i class="fa-solid fa-user-shield tile-glyph" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?= e(t('home.privacy_title', 'What a finder sees, and what stays private')) ?></h5>
                        <p class="tile-body"><?= e(t('home.privacy_body', 'The page describes the product, never the owner. Wi-Fi passwords, campaign IDs and production batches are stored in your dashboard and filtered out of every public page.')) ?></p>
                        <div class="tile-links">
                            <a class="text-link" href="<?= e(dat_url('privacy')) ?>">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i><b><?= e(t('home.privacy_link', 'Read the privacy summary')) ?></b>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-band" id="try-it">
        <div class="section-content">
            <div class="grid">
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <h2><?= e(t('home.try_title', 'See it on a phone')) ?></h2>
                    <p class="tile-body" style="font-size:21px;line-height:1.35">
                        <?= e(t('home.try_body', 'This is the page behind the keychain above, exactly as it opens on a phone: no app, no account, one anonymous message.')) ?>
                    </p>
                    <div class="tile-links" style="margin-top:18px">
                        <a class="text-link" href="<?= e(dat_url('demo')) ?>">
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i><b><?= e(t('home.try_demo', 'Open the live demo')) ?></b>
                        </a>
                        <a class="text-link" href="<?= e($showcaseUrl) ?>">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><b><?= e(t('home.link_tag_page', 'Live tag page')) ?></b>
                        </a>
                    </div>
                </div>
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <?php dat_tag_card($showcaseAsset, [], ['heading_level' => 2]); ?>
                </div>
            </div>

            <div class="grid" style="margin-top:44px">
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/10"><img src="<?= e(dat_preview_image('dashboard')) ?>" alt="<?= e(t('home.owner_view_title', 'The owner dashboard')) ?>" loading="lazy"></div>
                        <h5><?= e(t('home.owner_view_title', 'The owner dashboard')) ?></h5>
                        <p class="tile-body"><?= e(t('home.owner_view_body', 'Every asset, its status and the messages that arrived. Replies stay inside the portal, so neither side has to share a phone number.')) ?></p>
                        <div class="tile-links">
                            <a class="text-link" href="<?= e(dat_url('demo')) ?>">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i><b><?= e(t('home.try_demo', 'Open the live demo')) ?></b>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="grid-item large-span-6 medium-span-6 small-span-12">
                    <div class="tile">
                        <div class="tile-media" style="aspect-ratio:16/10"><img src="<?= e(dat_preview_image('demo')) ?>" alt="<?= e(t('home.demo_link', 'Live demo of the whole chain')) ?>" loading="lazy"></div>
                        <h5><?= e(t('home.demo_link', 'Live demo of the whole chain')) ?></h5>
                        <p class="tile-body"><?= e(t('home.demo_body', 'Five steps on one page: the tag, the page it opens, the message a finder sends, the answer, and how to start your own.')) ?></p>
                        <div class="tile-links">
                            <a class="text-link" href="<?= e(dat_url('demo')) ?>">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i><b><?= e(t('home.demo_open', 'Run through it')) ?></b>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="section-content cta-band-inner">
            <div>
                <h2><?= e(t('home.cta_title', 'Start with one tag')) ?></h2>
                <p class="tile-body" style="font-size:17px"><?= e(t('home.cta_body', 'Create it as a guest in a minute, download the QR code or copy the NFC text, and keep it by registering.')) ?></p>
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

<?php
/**
 * The catalogue: every tag product the portal supports, grouped into families,
 * with one worked example per item.
 *
 * Picking a product swaps the panel in place instead of stacking every example
 * into one long scroll. Switching is driven by the URL fragment, so
 * /use-cases#type-keychain keeps working, deep links and the browser back
 * button behave, and no JavaScript is required.
 *
 * Everything here is generated from includes/catalog.php, so the examples, the
 * live tag pages and the seeded demo data always agree.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;

/**
 * Worked scenario per product. Kept next to the catalog keys so a translator
 * sees the situation and the reason it helps together.
 */
$stories = [
    'menu_board' => [
        'situation' => t('case.menu_board.situation', 'The season changes, three prices change with it, and the boards at the counter still show last month.'),
        'helps' => t('case.menu_board.helps', 'The code on the board opens the live menu, so a price change never means reprinting. Guests get the Wi-Fi name from the board, while the actual Wi-Fi password stays in the owner dashboard: it is fine for people standing in the room, not for a page the whole internet can open.'),
    ],
    'poster' => [
        'situation' => t('case.poster.situation', 'A poster hangs in the window for eight weeks and the phone number printed on it belongs to someone who has left.'),
        'helps' => t('case.poster.helps', 'The chip behind the paper points at the current campaign, so the offer behind the poster can change without touching the print. The internal campaign ID stays in the dashboard so the window poster cannot be traced back to the shop’s planning.'),
    ],
    'wristband' => [
        'situation' => t('case.wristband.situation', 'At the gate, eight hundred wristbands are handed out and the crew has to match a guest to a booking in seconds.'),
        'helps' => t('case.wristband.helps', 'Each band carries its own link, so a tap at any gate shows the booking it belongs to. The production batch is stored for the organiser, which is how a faulty batch can be traced later without printing it on the band.'),
    ],
    'necklace' => [
        'situation' => t('case.necklace.situation', 'The cat will not wear a collar with a dangling tag, but the family still wants a way back if she wanders off.'),
        'helps' => t('case.necklace.helps', 'A small pendant sits flat against the fur and answers with her name, breed and colour. Whoever finds her can send a message from the page, and the pendant never carries a phone number that a stranger could read off the collar.'),
    ],
    'lanyard' => [
        'situation' => t('case.lanyard.situation', 'Visitor passes are handed out at reception and collected again at the end of the day, and the paper list never matches the lanyards.'),
        'helps' => t('case.lanyard.helps', 'A tap on the lanyard shows which pass it is and who is holding it, so the desk can see what is still out at the end of the day. The safety release is described on the page, which matters when someone needs to hand a card back quickly.'),
    ],
    'keychain' => [
        'situation' => t('case.keychain.situation', 'A set of keys is found in a doorway and the only clue is a keychain with a faded logo.'),
        'helps' => t('case.keychain.helps', 'The keychain answers with what it belongs to and one button to send a message. The owner decides whether to reply, and the production batch stays internal so the finder cannot work out where the keys live from the page alone.'),
    ],
    'mini_tag' => [
        'situation' => t('case.mini_tag.situation', 'Fifty identical tool boxes sit in a workshop and every search for the right one costs ten minutes.'),
        'helps' => t('case.mini_tag.helps', 'A label the size of a fingernail is enough to answer with the tool it belongs to and where it lives. Because it is small, it goes on the box, the cable drum or the zip of a bag, and the batch number stays in the dashboard instead of on the workshop floor.'),
    ],
    'pet' => [
        'situation' => t('case.pet.situation', 'The dog slips through the garden gate and a neighbour finds him two streets away.'),
        'helps' => t('case.pet.helps', 'The collar chip is tapped with a phone: name, breed and colour are on screen, and one button sends a message. The owner answers and collects the dog, without publishing a phone number on the collar.'),
    ],
    'clothing' => [
        'situation' => t('case.clothing.situation', 'A club jacket is left in the changing room and ends up in the lost-property box.'),
        'helps' => t('case.clothing.helps', 'The tag in the inside pocket tells staff what the jacket is and how to reach the owner. The owner replies where and when to collect it, and can leave their name out of it entirely.'),
    ],
];

$categories = dat_asset_type_categories();
$catalog = dat_demo_catalog();

// Examples indexed by asset type, so a category block can find its tab.
$entriesByType = [];
foreach ($catalog as $entry) {
    $entriesByType[$entry['type']] = $entry;
}

$firstTypeId = null;
foreach ($categories as $category) {
    if ($firstTypeId === null && $category['types']) {
        $firstTypeId = $category['types'][0];
    }
}

dat_page_start([
    'title' => t('cases.title', 'Products') . ' | ' . PORTAL_NAME,
    'description' => t('cases.meta', 'Menu boards, NFC posters, wristbands, necklaces, lanyards, keychains and mini tags, plus the everyday assets you can tag directly. One worked example per product, each with a live tag page.'),
    'canonical' => dat_url('use-cases'),
    'unread' => $unread,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('cases.eyebrow', 'Products')) ?></p>
            <h1><?= e(t('cases.h1', 'Nine tag products, four families')) ?></h1>
            <p class="section-lead"><?= e(t('cases.lead', 'Every product carries the same permanent link and can be written with any NFC app. Pick one to see its worked example, what a finder is allowed to see, and the real tag page behind it.')) ?></p>
        </div>
    </section>

    <section class="section section-tabs">
        <div class="shell">
            <p class="section-lead"><?= e(t('cases.pick_hint', 'The example below changes in place. Every product ends up at the same kind of link, so only the fields differ.')) ?></p>

            <?php /*
             * Sticky catalogue navigation: the product list stays in view while
             * the example on the right is read, so a visitor never has to
             * scroll back up to switch to another product. On narrow screens
             * the same list becomes a pinned horizontal strip.
             */ ?>
            <div class="catalog" data-tabs>
                <aside class="catalog-nav" aria-label="<?= e(t('cases.tablist_label', 'Products')) ?>">
                    <?php foreach ($categories as $category): ?>
                        <section class="catalog-group" id="group-<?= e($category['key']) ?>">
                            <h2 class="catalog-group-title"><?= e($category['label']) ?></h2>
                            <ul class="catalog-list">
                                <?php foreach ($category['types'] as $typeId): ?>
                                    <?php
                                    $type = dat_asset_types()[$typeId] ?? null;
                                    if ($type === null) {
                                        continue;
                                    }
                                    $entry = $entriesByType[$typeId] ?? null;
                                    $image = $entry !== null ? dat_product_image_url($entry['image']) : null;
                                    $isFirst = $typeId === $firstTypeId;
                                    ?>
                                    <li>
                                        <a class="catalog-item"
                                           href="#type-<?= e($type['key']) ?>"
                                           id="tab-type-<?= e($type['key']) ?>"
                                           role="tab"
                                           aria-controls="type-<?= e($type['key']) ?>"
                                           aria-selected="<?= $isFirst ? 'true' : 'false' ?>"
                                           tabindex="<?= $isFirst ? '0' : '-1' ?>">
                                            <span class="catalog-item-mark" aria-hidden="true">
                                                <?php if ($image !== null): ?>
                                                    <img src="<?= e($image) ?>" alt="" loading="lazy" width="40" height="40">
                                                <?php else: ?>
                                                    <i class="<?= e($type['icon']) ?>" aria-hidden="true"></i>
                                                <?php endif; ?>
                                            </span>
                                            <span class="catalog-item-body">
                                                <strong><?= e($type['label']) ?></strong>
                                                <small><?= e($entry['name'] ?? t('type.' . $type['key'] . '.example', $type['label'])) ?></small>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </aside>

                <div class="type-panels">
                    <?php foreach ($catalog as $entry): ?>
                        <?php
                        $type = $entry['type'];
                        $typeMeta = dat_asset_type_meta($type);
                        $category = dat_asset_type_category($type);
                        $story = $stories[$entry['key']] ?? ['situation' => '', 'helps' => ''];
                        $split = dat_demo_field_split($entry);
                        $publicAsset = dat_public_asset(dat_demo_asset_row($entry));
                        $tagUrl = dat_tag_url($entry['public_id']);
                        $payload = dat_nfc_payload(dat_demo_asset_row($entry), $tagUrl);
                        $image = dat_product_image_url($entry['image']);
                        ?>
                        <article class="type-panel"
                                 id="type-<?= e($entry['key']) ?>"
                                 role="tabpanel"
                                 aria-labelledby="tab-type-<?= e($entry['key']) ?>"
                                 tabindex="0">
                            <header class="example-head">
                                <?= dat_type_mark($type, $image, 'tag-type-mark') ?>
                                <div>
                                    <p class="eyebrow"><?= e($category['label']) ?> &middot; <?= e($typeMeta['label']) ?></p>
                                    <h3><?= e($entry['name']) ?></h3>
                                </div>
                                <a class="btn btn-ghost btn-sm" href="<?= e($tagUrl) ?>" target="_blank" rel="noopener">
                                    <?= e(t('cases.open_example', 'Open the example')) ?>
                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            </header>

                            <div class="example-grid">
                                <div class="example-copy">
                                    <p class="example-situation">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                        <?= e($story['situation']) ?>
                                    </p>
                                    <p><?= e($story['helps']) ?></p>

                                    <div class="example-lists">
                                        <div class="example-list example-list-public">
                                            <h4><i class="fa-solid fa-eye" aria-hidden="true"></i><?= e(t('cases.public_list', 'A finder sees')) ?></h4>
                                            <ul>
                                                <li><?= e($entry['name']) ?></li>
                                                <li><?= e($typeMeta['label']) ?></li>
                                                <?php foreach ($split['public'] as $label => $value): ?>
                                                    <li><strong><?= e($label) ?>:</strong> <?= e($value) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="example-list example-list-private">
                                            <h4><i class="fa-solid fa-lock" aria-hidden="true"></i><?= e(t('cases.private_list', 'Stored, never published')) ?></h4>
                                            <?php if ($split['private']): ?>
                                                <ul>
                                                    <?php foreach ($split['private'] as $label => $value): ?>
                                                        <li><strong><?= e($label) ?>:</strong> <?= e($value) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <ul>
                                                    <li><?= e(t('cases.no_private', 'This type has no private fields, only your account data.')) ?></li>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <details class="faq-item">
                                        <summary><?= e(t('cases.payload_title', 'Text written to the NFC chip')) ?></summary>
                                        <pre class="nfc-sample"><?= e($payload) ?></pre>
                                        <p class="form-hint"><?= e(sprintf(t('cases.payload_hint', '%d bytes, the link is what makes the tag work.'), dat_nfc_payload_bytes($payload))) ?></p>
                                    </details>

                                    <p class="example-url"><code><?= e($tagUrl) ?></code></p>
                                </div>

                                <div class="example-preview">
                                    <?php if ($image !== null): ?>
                                        <figure class="product-shot">
                                            <img src="<?= e($image) ?>" alt="<?= e($entry['name']) ?>" loading="lazy">
                                        </figure>
                                    <?php else: ?>
                                        <p class="product-shot-placeholder">
                                            <?= dat_type_mark($type, null, 'type-mark-lg') ?>
                                            <span><?= e($typeMeta['label']) ?></span>
                                        </p>
                                    <?php endif; ?>
                                    <div class="tag-card-frame">
                                        <?php dat_tag_card($publicAsset, [], ['compact' => true, 'heading_level' => 4]); ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="tabs-fallback"><?= e(t('cases.fallback', 'Every example is on this page, one panel at a time. Pick another product above to switch.')) ?></p>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('cases.shared_title', 'What all of them have in common')) ?></h2>
            <div class="split-grid">
                <ul class="owner-list">
                    <li>
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared1', 'One link format')) ?></strong>
                            <p><?= e(t('cases.shared1_body', 'Every product answers at /t/{publicId}. The type only changes the fields, never the address.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared2', 'Private by default')) ?></strong>
                            <p><?= e(t('cases.shared2_body', 'Wi-Fi passwords, campaign IDs and production batches are stored for you and filtered out of every public page.')) ?></p>
                        </div>
                    </li>
                </ul>
                <ul class="owner-list">
                    <li>
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared3', 'Tags are interchangeable')) ?></strong>
                            <p><?= e(t('cases.shared3_body', 'Replace a lost chip or add a second code without touching the asset or its link.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared4', 'The same inbox')) ?></strong>
                            <p><?= e(t('cases.shared4_body', 'Messages land in one place, whatever product was scanned.')) ?></p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="shell cta-band-inner">
            <div>
                <h2><?= e(t('cases.cta_title', 'Which product would you start with?')) ?></h2>
                <p><?= e(t('cases.cta_body', 'Create the tag, download the QR code or the NFC text, and write the chip when it arrives.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('start')) ?>"><?= e(t('start.title', 'Start with one tag')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(dat_url('write-a-tag')) ?>"><?= e(t('nav.write', 'Write a tag')) ?></a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

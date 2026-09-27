<?php
/**
 * Use cases: "One platform, six asset types" with a worked example per type.
 *
 * Every block is generated from includes/catalog.php, links to the real tag
 * page seeded for that entry, and derives the public/private lists from the
 * same field definitions the portal uses when rendering. Nothing here is a
 * mockup.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;

/**
 * Extra copy per example. Kept next to the catalog keys so a translator sees
 * the scenario, the "why it helps" line and the type name together.
 */
$stories = [
    'pet' => [
        'situation' => t('case.pet.situation', 'The dog slips through the garden gate and a neighbour finds him two streets away.'),
        'helps' => t('case.pet.helps', 'The collar chip is tapped with a phone: name, breed and colour are on screen, and one button sends a message. The owner answers and collects the dog, without publishing a phone number on the collar.'),
    ],
    'bicycle' => [
        'situation' => t('case.bicycle.situation', 'The e-bike is moved by station staff during building work and ends up in another rack.'),
        'helps' => t('case.bicycle.helps', 'The QR sticker on the frame names the brand, model and frame size, which is enough to describe the bike exactly. The frame number stays private, so a found notice cannot be turned into a forged ownership claim.'),
    ],
    'vehicle' => [
        'situation' => t('case.vehicle.situation', 'Someone scrapes the rear bumper in a car park and a paper note would blow away.'),
        'helps' => t('case.vehicle.helps', 'The windscreen tag is scanned: model, year and colour identify the car, and the witness writes a message with the time and place. The licence plate and chassis number never appear on the page.'),
    ],
    'clothing' => [
        'situation' => t('case.clothing.situation', 'A club jacket is left in the changing room and ends up in the lost-property box.'),
        'helps' => t('case.clothing.helps', 'The tag in the inside pocket tells staff what the jacket is and how to reach the owner. The owner replies where and when to collect it, and can leave their name out of it entirely.'),
    ],
    'item' => [
        'situation' => t('case.item.situation', 'The camera bag is left under a café table and handed in at the counter.'),
        'helps' => t('case.item.helps', 'Staff scan the tag, see what the bag is and write one message. The purchase date and serial number are stored for the owner but never published, so the description cannot be reused for a fake classified ad.'),
    ],
    'industrial' => [
        'situation' => t('case.industrial.situation', 'A mobile power unit is at a customer site and nobody can reach the fitter who knows the maintenance interval.'),
        'helps' => t('case.industrial.helps', 'The plate is scanned: manufacturer, model and category are visible, and the service desk receives the message with the site details. Machine ID, serial number, location and service contact stay private, so the plate can be left readable on the shop floor.'),
    ],
];

$types = dat_asset_types();

dat_page_start([
    'title' => t('cases.title', 'Use cases') . ' | ' . PORTAL_NAME,
    'description' => t('cases.meta', 'One platform, six asset types: worked examples for pets, bicycles, vehicles, clothing, everyday items and industrial equipment, each with a live tag page.'),
    'canonical' => dat_url('use-cases'),
    'unread' => $unread,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('cases.eyebrow', 'Use cases')) ?></p>
            <h1><?= e(t('cases.h1', 'One platform, six asset types')) ?></h1>
            <p class="section-lead"><?= e(t('cases.lead', 'Each type keeps its own fields and its own fields stay private by default, while every asset shares the same link format. Below is one worked example per type, with the real tag page behind it.')) ?></p>
        </div>
    </section>

    <section class="section section-types-summary">
        <div class="shell">
            <div class="type-grid type-grid-compact">
                <?php foreach ($types as $type): ?>
                    <a class="type-tile type-tile-link" href="#type-<?= e($type['key']) ?>">
                        <span class="type-tile-mark" aria-hidden="true"><?= e($type['emoji']) ?></span>
                        <h2><?= e($type['label']) ?></h2>
                        <p><?= e(t('type.' . $type['key'] . '.example', $type['label'])) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php foreach (dat_demo_catalog() as $entry): ?>
        <?php
        $type = $entry['type'];
        $typeMeta = dat_asset_type_meta($type);
        $story = $stories[$entry['key']] ?? ['situation' => '', 'helps' => ''];
        $split = dat_demo_field_split($entry);
        $publicAsset = dat_public_asset(dat_demo_asset_row($entry));
        $tagUrl = dat_tag_url($entry['public_id']);
        $payload = dat_nfc_payload(dat_demo_asset_row($entry), $tagUrl);
        ?>
        <section class="section example-section" id="type-<?= e($entry['key']) ?>">
            <div class="shell">
                <header class="example-head">
                    <span class="tag-type-mark" aria-hidden="true"><?= e($typeMeta['emoji']) ?></span>
                    <div>
                        <p class="eyebrow"><?= e($typeMeta['label']) ?></p>
                        <h2><?= e($entry['name']) ?></h2>
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
                                <h3><i class="fa-solid fa-eye" aria-hidden="true"></i><?= e(t('cases.public_list', 'A finder sees')) ?></h3>
                                <ul>
                                    <li><?= e($entry['name']) ?></li>
                                    <li><?= e($typeMeta['label']) ?></li>
                                    <?php foreach ($split['public'] as $label => $value): ?>
                                        <li><strong><?= e($label) ?>:</strong> <?= e($value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="example-list example-list-private">
                                <h3><i class="fa-solid fa-lock" aria-hidden="true"></i><?= e(t('cases.private_list', 'Stored, never published')) ?></h3>
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
                        <div class="tag-card-frame">
                            <?php dat_tag_card($publicAsset, [], ['compact' => true, 'heading_level' => 3]); ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('cases.shared_title', 'What all six have in common')) ?></h2>
            <div class="split-grid">
                <ul class="owner-list">
                    <li>
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared1', 'One link format')) ?></strong>
                            <p><?= e(t('cases.shared1_body', 'Every asset answers at /t/{publicId}. The type only changes the fields, never the address.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared2', 'Private by default')) ?></strong>
                            <p><?= e(t('cases.shared2_body', 'Identification numbers are stored for you and filtered out of every public page.')) ?></p>
                        </div>
                    </li>
                </ul>
                <ul class="owner-list">
                    <li>
                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared3', 'Tags are interchangeable')) ?></strong>
                            <p><?= e(t('cases.shared3_body', 'Replace a lost chip or add a second printed code without touching the asset or its link.')) ?></p>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        <div>
                            <strong><?= e(t('cases.shared4', 'The same inbox')) ?></strong>
                            <p><?= e(t('cases.shared4_body', 'Finder messages land in one place, whatever type of asset was scanned.')) ?></p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="shell cta-band-inner">
            <div>
                <h2><?= e(t('cases.cta_title', 'Which of your things would you tag first?')) ?></h2>
                <p><?= e(t('cases.cta_body', 'Create an account and add the one that worries you most.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(dat_url('how-it-works')) ?>"><?= e(t('nav.how', 'How it works')) ?></a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

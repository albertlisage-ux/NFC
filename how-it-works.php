<?php
/**
 * How it works: the full explanation that used to be a section on the home
 * page. Owner path, finder path, tag lifecycle and the status flow.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$unread = $user !== null ? dat_unread_message_count($user['id']) : 0;
$demo = dat_demo_entry('pet');
$demoUrl = dat_tag_url($demo['public_id']);
$nfcSample = dat_nfc_payload(dat_demo_asset_row($demo), $demoUrl);

dat_page_start([
    'title' => t('how.title', 'How it works') . ' | ' . PORTAL_NAME,
    'description' => t('how.meta', 'From an empty account to a tag that answers: creating the asset, writing the QR code or NFC chip, and what happens when a finder scans it.'),
    'canonical' => dat_url('how-it-works'),
    'unread' => $unread,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('how.eyebrow', 'How it works')) ?></p>
            <h1><?= e(t('how.h1', 'From an asset to an answer in three steps')) ?></h1>
            <p class="section-lead"><?= e(t('how.lead', 'No app for the finder, no account for the finder, and a link that keeps working even when you replace the physical tag.')) ?></p>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <ol class="steps steps-detailed">
                <li class="step">
                    <span class="step-index" aria-hidden="true">1</span>
                    <h2><?= e(t('how.step1_title', 'Create the asset')) ?></h2>
                    <p><?= e(t('how.step1_body', 'Pick one of the six types, give it a name, and add the details that help a stranger recognise it. Photos are optional and are re-encoded on upload, so location data never reaches the page.')) ?></p>
                    <ul class="tick-list">
                        <li><?= e(t('how.step1_point1', 'Your account holds an email address and a password, nothing else.')) ?></li>
                        <li><?= e(t('how.step1_point2', 'Every asset gets a random public ID such as DEMTAG24.')) ?></li>
                        <li><?= e(t('how.step1_point3', 'Fields like VIN, serial number or licence plate are stored but never published.')) ?></li>
                    </ul>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">2</span>
                    <h2><?= e(t('how.step2_title', 'Write the tag')) ?></h2>
                    <p><?= e(t('how.step2_body', 'Download the QR code as PNG or SVG, or copy the short NFC text and write it to an NTAG chip with any NFC writer app. Both carry the same permanent link.')) ?></p>
                    <ul class="tick-list">
                        <li><?= e(t('how.step2_point1', 'SVG is the best choice for printing, PNG works everywhere.')) ?></li>
                        <li><?= e(t('how.step2_point2', 'Test the tag once before you attach it.')) ?></li>
                        <li><?= e(t('how.step2_point3', 'The portal shows how many bytes the NFC text uses, so you know which chip fits.')) ?></li>
                    </ul>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">3</span>
                    <h2><?= e(t('how.step3_title', 'Scan, and get a message')) ?></h2>
                    <p><?= e(t('how.step3_body', 'The finder opens the same stable link and sees what the asset is. If they write to you, the message waits in your inbox. You answer inside the portal, so neither side learns how to reach the other.')) ?></p>
                    <ul class="tick-list">
                        <li><?= e(t('how.step3_point1', 'Finder messages arrive without a name, email address or phone number.')) ?></li>
                        <li><?= e(t('how.step3_point2', 'Replies stay in the thread the finder keeps as a private link.')) ?></li>
                        <li><?= e(t('how.step3_point3', 'Messages expire automatically after the retention period.')) ?></li>
                    </ul>
                </li>
            </ol>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell split-grid">
            <div>
                <h2><?= e(t('how.split_title', 'Three different kinds of information')) ?></h2>
                <p><?= e(t('how.split_body', 'A tag is readable by anyone standing next to it, so the portal splits the data into three layers instead of putting everything on the chip.')) ?></p>
                <a class="btn btn-ghost" href="<?= e(dat_url('privacy')) ?>">
                    <?= e(t('how.split_link', 'Read the privacy summary')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="layer-columns">
                <article class="layer-card">
                    <h3><i class="fa-solid fa-wifi" aria-hidden="true"></i><?= e(t('how.layer_tag', 'On the tag')) ?></h3>
                    <p><?= e(t('how.layer_tag_body', 'A few readable lines and the link. Enough to identify the asset without a phone, nothing that identifies you.')) ?></p>
                    <pre class="nfc-sample"><?= e($nfcSample) ?></pre>
                </article>
                <article class="layer-card">
                    <h3><i class="fa-solid fa-eye" aria-hidden="true"></i><?= e(t('how.layer_public', 'On the public page')) ?></h3>
                    <p><?= e(t('how.layer_public_body', 'Name, type, description, the details you marked as public, your photos and the status.')) ?></p>
                    <ul class="tick-list">
                        <?php foreach (dat_demo_field_split($demo)['public'] as $label => $value): ?>
                            <li><strong><?= e($label) ?>:</strong> <?= e($value) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <article class="layer-card">
                    <h3><i class="fa-solid fa-lock" aria-hidden="true"></i><?= e(t('how.layer_private', 'In the portal only')) ?></h3>
                    <p><?= e(t('how.layer_private_body', 'Owner contact details, private fields, message threads and internal identifiers.')) ?></p>
                    <ul class="tick-list">
                        <li><?= e(t('how.layer_private_point1', 'Name, email address, phone number, postal address')) ?></li>
                        <li><?= e(t('how.layer_private_point2', 'Serial numbers and licence plates')) ?></li>
                        <li><?= e(t('how.layer_private_point3', 'Message contents outside your own inbox')) ?></li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('how.multi_title', 'One asset, several tags')) ?></h2>
            <p class="section-lead"><?= e(t('how.multi_body', 'A dog can carry a collar chip and a spare QR code on the lead. Both point at the same public ID, so both answer with the same page and the same inbox.')) ?></p>

            <div class="split-grid">
                <article class="panel">
                    <h3><?= e(t('how.replace_title', 'Replace a tag, keep the link')) ?></h3>
                    <p><?= e(t('how.replace_body', 'If a tag is lost or damaged, disable it and add a replacement. The public ID never changes, so anything you already printed or wrote stays valid.')) ?></p>
                    <div class="status-flow">
                        <span class="pill pill-muted"><?= e(t('tag.status.disabled', 'Disabled')) ?></span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <span class="pill pill-ok"><?= e(t('tag.status.active', 'Active')) ?></span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <code><?= e($demo['public_id']) ?></code>
                    </div>
                </article>

                <article class="panel">
                    <h3><?= e(t('how.status_title', 'When something goes missing')) ?></h3>
                    <p><?= e(t('how.status_body', 'You set the status, the portal does the rest of the wording.')) ?></p>
                    <div class="status-flow">
                        <?= dat_status_pill(DAT_ASSET_STATUS_ACTIVE) ?>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <?= dat_status_pill(DAT_ASSET_STATUS_LOST) ?>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <?= dat_status_pill(DAT_ASSET_STATUS_FOUND) ?>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <?= dat_status_pill(DAT_ASSET_STATUS_ACTIVE) ?>
                    </div>
                    <p class="form-hint"><?= e(t('how.status_hint', 'A finder message moves a lost asset to "found" on its own. Set it back to active once it is home again.')) ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell split-grid">
            <div>
                <h2><?= e(t('how.see_title', 'See a real tag page')) ?></h2>
                <p><?= e(t('how.see_body', 'This is a live page from the demo account, with the same layout a finder sees after scanning a chip or a printed code.')) ?></p>
                <a class="btn btn-primary" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener">
                    <?= e(t('how.see_link', 'Open the example tag page')) ?>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <p class="form-hint"><?= e($demoUrl) ?></p>
            </div>
            <div class="tag-card-frame">
                <?php dat_tag_card(dat_public_asset(dat_demo_asset_row($demo)), [], ['compact' => true, 'heading_level' => 3]); ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('how.faq_title', 'Common questions')) ?></h2>
            <div class="faq">
                <?php
                $faq = [
                    ['q' => t('how.faq1_q', 'Does the finder need an account?'), 'a' => t('how.faq1_a', 'No. The finder opens the page, writes one message and can follow the answer through a private link. Nothing is installed and nothing is registered.')],
                    ['q' => t('how.faq2_q', 'Which chips does the NFC text fit on?'), 'a' => t('how.faq2_a', 'The payload stays short, so it fits on an NTAG213 and anything larger. The tag page reports the exact byte count after every change to the asset.')],
                    ['q' => t('how.faq3_q', 'What happens if I lose the physical tag?'), 'a' => t('how.faq3_a', 'Add a replacement in the dashboard. The old tag is marked as replaced and the public ID stays the same, so the asset keeps its history and its link.')],
                    ['q' => t('how.faq4_q', 'Can I hide an asset from finders?'), 'a' => t('how.faq4_a', 'Set it to inactive. The link keeps answering, but the page only says that the tag is not active any more, and no asset details are shown.')],
                    ['q' => t('how.faq5_q', 'Do I have to publish photos?'), 'a' => t('how.faq5_a', 'No. A photo helps a finder recognise the asset, but the page works without one. Uploads are re-encoded, which removes EXIF data and GPS coordinates.')],
                    ['q' => t('how.faq6_q', 'Can someone use my page to fake ownership?'), 'a' => t('how.faq6_a', 'The page never shows serial numbers, VIN, licence plates or purchase dates, so it cannot be used as proof of ownership. Identification numbers stay in your dashboard.')],
                ];
                foreach ($faq as $item):
                ?>
                    <details class="faq-item">
                        <summary><?= e($item['q']) ?></summary>
                        <p><?= e($item['a']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="shell cta-band-inner">
            <div>
                <h2><?= e(t('how.cta_title', 'Try it with one asset')) ?></h2>
                <p><?= e(t('how.cta_body', 'Create an account, add the first asset and download its QR code in a couple of minutes.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(dat_url('use-cases')) ?>"><?= e(t('nav.usecases', 'Use cases')) ?></a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

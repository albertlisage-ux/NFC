<?php
/**
 * Write a tag: how to program an NFC chip with a third-party app such as
 * NFC Tools, and how that chip ends up opening this website.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$demo = dat_demo_entry('pet');
$demoUrl = dat_tag_url($demo['public_id']);
$demoPayload = dat_nfc_payload(dat_demo_asset_row($demo), $demoUrl);

$apps = [
    [
        'name' => 'NFC Tools',
        'platform' => t('guide.app_nfc_tools_platform', 'Android, iOS'),
        'use' => t('guide.app_nfc_tools_use', 'Write tab for programming, Read tab for checking a chip afterwards. The usual choice on both phones.'),
    ],
    [
        'name' => 'NXP TagWriter',
        'platform' => t('guide.app_tagwriter_platform', 'Android, iOS'),
        'use' => t('guide.app_tagwriter_use', 'Same job with a different interface, handy when a chip refuses to be written by the first app.'),
    ],
    [
        'name' => 'NXP TagInfo',
        'platform' => t('guide.app_taginfo_platform', 'Android, iOS'),
        'use' => t('guide.app_taginfo_use', 'Diagnostics: shows the chip model, its memory size and the records already on it.'),
    ],
];

dat_page_start([
    'title' => t('guide.title', 'Write a tag') . ' | ' . PORTAL_NAME,
    'description' => t('guide.meta', 'How to write the portal link to an NFC chip with a third-party app such as NFC Tools, which chip to buy, how the tap opens this website, and what to check before attaching the tag.'),
    'canonical' => dat_url('write-a-tag'),
    'unread' => $user !== null ? dat_unread_message_count($user['id']) : 0,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('guide.eyebrow', 'Tag preparation')) ?></p>
            <h1><?= e(t('guide.h1', 'Write a tag with a third-party app')) ?></h1>
            <p class="section-lead"><?= e(t('guide.lead', 'The portal does not need its own app. Any writer that can store a standard URI record works, because the chip only carries a link to your tag page.')) ?></p>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('guide.need_title', 'What you need')) ?></h2>
            <div class="explore-grid">
                <article class="explore-card">
                    <span class="explore-icon" aria-hidden="true"><i class="fa-solid fa-microchip"></i></span>
                    <h3><?= e(t('guide.need1_title', 'An NFC chip')) ?></h3>
                    <p><?= e(t('guide.need1_body', 'NTAG213 stickers are the cheapest option and hold this payload comfortably. NTAG215 or NTAG216 give you room for longer text.')) ?></p>
                </article>
                <article class="explore-card">
                    <span class="explore-icon" aria-hidden="true"><i class="fa-solid fa-mobile-screen"></i></span>
                    <h3><?= e(t('guide.need2_title', 'A phone with NFC')) ?></h3>
                    <p><?= e(t('guide.need2_body', 'Almost every Android phone since 2013 and every iPhone from the iPhone 7 onwards reads NFC tags. No special hardware is needed.')) ?></p>
                </article>
                <article class="explore-card">
                    <span class="explore-icon" aria-hidden="true"><i class="fa-solid fa-download"></i></span>
                    <h3><?= e(t('guide.need3_title', 'A writing app')) ?></h3>
                    <p><?= e(t('guide.need3_body', 'A free app from the store. The portal works with all of them because the record format is standardised.')) ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('guide.apps_title', 'Which app to use')) ?></h2>
            <p class="section-lead"><?= e(t('guide.apps_body', 'Any app that can write a URI or Text record does the job. These three are the ones most owners end up using.')) ?></p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('guide.table_app', 'App')) ?></th>
                            <th scope="col"><?= e(t('guide.table_platform', 'Platform')) ?></th>
                            <th scope="col"><?= e(t('guide.table_use', 'What it is good for')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apps as $app): ?>
                            <tr>
                                <th scope="row"><?= e($app['name']) ?></th>
                                <td><?= e($app['platform']) ?></td>
                                <td><?= e($app['use']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="form-hint"><?= e(t('guide.apps_note', 'If you prefer an open-source writer, check only that it can write a URI record. Nothing else about the app matters, and no account is ever needed in it.')) ?></p>
        </div>
    </section>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('guide.link_title', 'Where the link comes from')) ?></h2>
            <p class="section-lead"><?= e(t('guide.link_body', 'Every asset has one permanent address. You copy it from the portal, and from that moment the printed code and the chip share it.')) ?></p>
            <div class="split-grid">
                <div>
                    <ol class="step-list">
                        <li>
                            <span class="step-index" aria-hidden="true">1</span>
                            <div>
                                <strong><?= e(t('guide.link_step1', 'Open the asset and switch to Tags')) ?></strong>
                                <p><?= e(t('guide.link_step1_body', 'The dashboard shows the public link, the QR code and the NFC text for that asset.')) ?></p>
                            </div>
                        </li>
                        <li>
                            <span class="step-index" aria-hidden="true">2</span>
                            <div>
                                <strong><?= e(t('guide.link_step2', 'Copy the link, not the page title')) ?></strong>
                                <p><?= e(t('guide.link_step2_body', 'What makes a tag work is the address of the tag page, for example ' . $demoUrl . '. The readable lines are only there for people who read the chip without tapping it.')) ?></p>
                            </div>
                        </li>
                        <li>
                            <span class="step-index" aria-hidden="true">3</span>
                            <div>
                                <strong><?= e(t('guide.link_step3', 'No account needed for the example')) ?></strong>
                                <p><?= e(t('guide.link_step3_body', 'You can try the whole flow with a demo tag before you buy chips.')) ?></p>
                            </div>
                        </li>
                    </ol>
                    <div class="btn-group">
                        <a class="btn btn-primary" href="<?= e(dat_url('start')) ?>"><?= e(t('start.title', 'Start with one tag')) ?></a>
                        <a class="btn btn-ghost" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener"><?= e(t('guide.link_demo', 'Open the demo tag page')) ?></a>
                    </div>
                </div>
                <div>
                    <h3><?= e(t('guide.payload_title', 'Example NFC text')) ?></h3>
                    <pre class="nfc-sample"><?= e($demoPayload) ?></pre>
                    <p class="form-hint"><?= e(sprintf(t('guide.payload_hint', '%d bytes for this example. The last line is the only part a phone needs in order to open the page.'), dat_nfc_payload_bytes($demoPayload))) ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('guide.write_title', 'Writing the chip in NFC Tools')) ?></h2>
            <p class="section-lead"><?= e(t('guide.write_body', 'The steps are the same in other apps, only the labels differ.')) ?></p>
            <ol class="step-list">
                <li>
                    <span class="step-index" aria-hidden="true">1</span>
                    <div>
                        <strong><?= e(t('guide.write_step1', 'Install the app and switch NFC on')) ?></strong>
                        <p><?= e(t('guide.write_step1_body', 'On Android, NFC lives in the connection settings. On iPhone it is always listening, the app only needs permission.')) ?></p>
                    </div>
                </li>
                <li>
                    <span class="step-index" aria-hidden="true">2</span>
                    <div>
                        <strong><?= e(t('guide.write_step2', 'Open the Write tab and add a record')) ?></strong>
                        <p><?= e(t('guide.write_step2_body', 'Choose the record type "URL / URI" and paste the public link of your asset. This is the record that opens the browser.')) ?></p>
                    </div>
                </li>
                <li>
                    <span class="step-index" aria-hidden="true">3</span>
                    <div>
                        <strong><?= e(t('guide.write_step3', 'Optional: add the readable text as a second record')) ?></strong>
                        <p><?= e(t('guide.write_step3_body', 'Add a "Text" record with the short payload so the chip can also be read as plain text. Keep the URL record first, otherwise some phones show the text instead of opening the page.')) ?></p>
                    </div>
                </li>
                <li>
                    <span class="step-index" aria-hidden="true">4</span>
                    <div>
                        <strong><?= e(t('guide.write_step4', 'Tap Write and hold the phone over the chip')) ?></strong>
                        <p><?= e(t('guide.write_step4_body', 'Hold it still until the app confirms the write. Chips with little memory may refuse the optional text record, in which case write the link on its own.')) ?></p>
                    </div>
                </li>
                <li>
                    <span class="step-index" aria-hidden="true">5</span>
                    <div>
                        <strong><?= e(t('guide.write_step5', 'Read it back, then test the tap')) ?></strong>
                        <p><?= e(t('guide.write_step5_body', 'Switch to the Read tab to verify the record, then lock the phone screen, hold it over the chip and check that the tag page opens.')) ?></p>
                    </div>
                </li>
            </ol>

            <div class="notice notice-warn">
                <strong><?= e(t('guide.lock_title', 'About locking a tag')) ?></strong>
                <p><?= e(t('guide.lock_body', 'Most apps offer to make a chip read-only. That step cannot be undone, so lock a tag only after you have tested it, and only if you are sure the link will never need to change. The portal never requires locking.')) ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="shell split-grid">
            <div>
                <h2><?= e(t('guide.how_title', 'How the tap reaches this website')) ?></h2>
                <p><?= e(t('guide.how_body', 'The chip holds nothing but the address. Everything else, the asset details, the photos, the status and the message inbox, lives on this server, which is why the same link keeps working when you change a name or replace a tag.')) ?></p>
                <div class="status-flow">
                    <span class="pill pill-neutral"><i class="fa-solid fa-wifi" aria-hidden="true"></i><?= e(t('guide.flow_tag', 'Chip tapped')) ?></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <span class="pill pill-neutral"><i class="fa-solid fa-globe" aria-hidden="true"></i><?= e(t('guide.flow_browser', 'Browser opens the link')) ?></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <span class="pill pill-info"><i class="fa-solid fa-tag" aria-hidden="true"></i><?= e(t('guide.flow_page', 'Tag page loads')) ?></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    <span class="pill pill-warn"><i class="fa-solid fa-comments" aria-hidden="true"></i><?= e(t('guide.flow_message', 'Finder writes a message')) ?></span>
                </div>
                <p class="form-hint"><?= e(t('guide.how_offline', 'The link needs a network connection, like any website. The readable lines on the chip work without one.')) ?></p>
            </div>
            <div>
                <h2><?= e(t('guide.device_title', 'Reading on different phones')) ?></h2>
                <dl class="detail-list">
                    <div><dt><?= e(t('guide.device_android', 'Android')) ?></dt><dd><?= e(t('guide.device_android_body', 'A URI record opens the browser straight away. Keep NFC enabled, and hold the phone near the chip, usually at the top of the back.')) ?></dd></div>
                    <div><dt><?= e(t('guide.device_iphone', 'iPhone')) ?></dt><dd><?= e(t('guide.device_iphone_body', 'iPhone 7 and later read the chip in the background once the screen is on. A notification with the link appears, tapping it opens the page. Text-only records need the app or the NFC reader in the control centre.')) ?></dd></div>
                    <div><dt><?= e(t('guide.device_case', 'Thick cases and metal')) ?></dt><dd><?= e(t('guide.device_case_body', 'Metal surfaces and thick cases weaken the field. Test the chip in the place where it will actually live, especially on tools and machines.')) ?></dd></div>
                </dl>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('guide.trouble_title', 'When something does not work')) ?></h2>
            <div class="faq">
                <?php
                $problems = [
                    ['q' => t('guide.t1_q', 'The app refuses to write, saying the tag is locked'), 'a' => t('guide.t1_a', 'The chip was made read-only by an earlier write. Use a fresh chip: the portal does not need locked tags, and a locked chip cannot be corrected later.')],
                    ['q' => t('guide.t2_q', 'The write succeeds but tapping does nothing'), 'a' => t('guide.t2_a', 'Write the link as a URI record. A Text record alone is not opened by every phone. Read the chip back to confirm which record sits on it, and check that NFC is switched on.')],
                    ['q' => t('guide.t3_q', 'The tag opened the page but the photo is missing'), 'a' => t('guide.t3_a', 'The chip only carries the link, so the page is always as current as your last edit. Reload it; if the photo is still missing, check that it was uploaded to the right asset.')],
                    ['q' => t('guide.t4_q', 'The chip has too little memory'), 'a' => t('guide.t4_a', 'NTAG213 stickers hold around 137 bytes, which is enough for the link plus a few lines. Drop the optional text record, or use an NTAG215 for more room.')],
                    ['q' => t('guide.t5_q', 'I wrote the wrong link to a chip'), 'a' => t('guide.t5_a', 'As long as the chip is not locked, simply write the correct link again. If the chip is already attached, hold the phone to it and rewrite; the old value is replaced.')],
                    ['q' => t('guide.t6_q', 'Can someone else read the chip and take over my page?'), 'a' => t('guide.t6_a', 'Reading a chip only reveals the public link and the public text. Editing the page requires the account password, and the page never shows owner contact details.')],
                ];
                foreach ($problems as $item):
                ?>
                    <details class="faq-item">
                        <summary><?= e($item['q']) ?></summary>
                        <p><?= e($item['a']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-split">
        <div class="shell">
            <h2><?= e(t('guide.wa_title', 'WhatsApp and other reading apps')) ?></h2>
            <p class="section-lead"><?= e(t('guide.wa_lead', 'A QR code can be scanned by almost any app, including WhatsApp. A chip tap always hands the link to the phone, which then decides what opens.')) ?></p>

            <div class="faq">
                <?php
                $whatsappFaq = [
                    ['q' => t('guide.wa1_q', 'Can WhatsApp scan the QR code?'), 'a' => t('guide.wa1_a', 'Yes. WhatsApp has its own scanner, next to the search field, and the camera in a chat also reads codes. A code that points at your tag page opens inside the WhatsApp browser, where the finder can read the page and send the anonymous message. Joining a Wi-Fi network is the one thing WhatsApp cannot do from a scan, that needs the phone camera.')],
                    ['q' => t('guide.wa2_q', 'Does tapping the chip open WhatsApp?'), 'a' => t('guide.wa2_a', 'No. A tap gives the link to the operating system, which opens the browser. WhatsApp does not register itself as a handler for NFC links, so no chip can force a chat to open. What you can do is make the link itself a WhatsApp link, and that is exactly what the optional button on the tag page does.')],
                    ['q' => t('guide.wa3_q', 'How do I get a WhatsApp button on my tag page?'), 'a' => t('guide.wa3_a', 'Open the asset in the dashboard, enter a WhatsApp number and save. The public page then shows "Message on WhatsApp" next to "I found this", with a ready-made first sentence that names the asset. Leave the field empty and the button disappears again.')],
                    ['q' => t('guide.wa4_q', 'Should the chip point straight at WhatsApp instead?'), 'a' => t('guide.wa4_a', 'It can, but you lose the page: no asset details, no photos, no message history, and the link cannot be changed later. Pointing the chip at the portal and offering WhatsApp from there keeps both, and the anonymous message stays available for finders who do not want to give away their own number.')],
                ];
                foreach ($whatsappFaq as $item):
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
                <h2><?= e(t('guide.cta_title', 'Write your first tag')) ?></h2>
                <p><?= e(t('guide.cta_body', 'Create a tag and its link, copy the NFC text and write the chip. You can practise with a guest tag before buying any hardware.')) ?></p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(dat_url('start')) ?>"><?= e(t('start.title', 'Start with one tag')) ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener"><?= e(t('guide.link_demo', 'Open the demo tag page')) ?></a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

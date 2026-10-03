<?php
/**
 * Start with one tag.
 *
 * Two ways in: create a single tag straight away as a guest (no account, kept
 * for seven days unless you register), or follow the numbered registration
 * steps. Both paths end in the same place: a public link, a QR code and an NFC
 * payload you can write with a third-party app.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalog.php';

$user = dat_current_user();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } elseif (!empty($_POST['website'])) {
        // Honeypot: report nothing and quietly ignore.
        header('Location: ' . dat_url('start'));
        exit;
    } elseif ($user !== null) {
        // Signed in: point the owner at the real dashboard instead.
        header('Location: ' . dat_url('dashboard/assets-new.php'));
        exit;
    } else {
        if (!dat_guest_can_create()) {
            $errors[] = t('error.guest_full', 'This guest session already created its tag. Create an account to add more.');
        } else {
            $guest = dat_guest_user();
            if ($guest === null) {
                $session = dat_create_guest_session();
                if (!$session['success']) {
                    $errors = $session['errors'];
                } else {
                    $guest = $session['user'];
                }
            }

            if (!$errors && $guest !== null) {
                $type = (int) ($_POST['type'] ?? DAT_ASSET_TYPE_PET);
                $name = trim((string) ($_POST['name'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $metadata = is_array($_POST['metadata'] ?? null) ? $_POST['metadata'] : [];

                if ($name === '') {
                    $errors[] = t('error.asset_name', 'Please give the asset a name.');
                }
                if (!in_array($type, dat_asset_type_ids(), true)) {
                    $errors[] = t('error.asset_type', 'Please choose an asset type.');
                }

                if (!$errors) {
                    $asset = dat_create_asset($guest['id'], $type, $name, $description, $metadata);
                    if ($asset === null) {
                        $errors[] = t('error.asset_create', 'The asset could not be created. Please try again.');
                    } else {
                        header('Location: ' . dat_url('start') . '?created=1');
                        exit;
                    }
                }
            }
        }
    }
}

// Opportunistic cleanup of expired guest sessions, an occasional cheap query.
if (random_int(1, 20) === 1) {
    dat_purge_guest_sessions();
}

$guestAssets = $user === null ? dat_guest_assets() : [];
$guestAsset = $guestAssets[0] ?? null;
$justCreated = isset($_GET['created']) && $guestAsset !== null;
$publicUrl = $guestAsset !== null ? dat_tag_url($guestAsset['public_id']) : null;
$qrSvg = $publicUrl !== null ? dat_qr_svg($publicUrl, 7, 3) : '';
$nfcPayload = $guestAsset !== null ? dat_nfc_payload($guestAsset, $publicUrl) : '';

$steps = [
    ['title' => t('start.reg1', 'Choose an email address and a password'), 'body' => t('start.reg1_body', 'That is the whole form. No name, address, phone number or birthday is asked for.')],
    ['title' => t('start.reg2', 'Add your first asset'), 'body' => t('start.reg2_body', 'Pick a type, give it a name and add whatever helps a finder recognise it.')],
    ['title' => t('start.reg3', 'Download the QR code or copy the NFC text'), 'body' => t('start.reg3_body', 'Both carry the same permanent link, so you can print a label, write a chip or do both.')],
    ['title' => t('start.reg4', 'Write the tag and test it once'), 'body' => t('start.reg4_body', 'A third-party app such as NFC Tools writes the link. Hold your phone over the chip to check it opens the page.')],
];

dat_page_start([
    'title' => t('start.title', 'Start with one tag') . ' | ' . PORTAL_NAME,
    'description' => t('start.meta', 'Create your first digital asset tag as a guest without an account, or follow the four registration steps. Then write the tag with a third-party NFC app.'),
    'canonical' => dat_url('start'),
    'unread' => $user !== null ? dat_unread_message_count($user['id']) : 0,
]);
?>
<main id="main">
    <section class="page-hero">
        <div class="shell">
            <p class="eyebrow"><?= e(t('start.eyebrow', 'Start here')) ?></p>
            <h1><?= e(t('start.h1', 'Start with one tag')) ?></h1>
            <p class="section-lead"><?= e(t('start.lead', 'Create a single tag right now as a guest, or set up an account first. Both take a minute, and neither asks for personal data.')) ?></p>
        </div>
    </section>

    <?php if ($user !== null): ?>
        <section class="section">
            <div class="shell">
                <div class="notice notice-ok">
                    <strong><?= e(t('start.signed_in_title', 'You are signed in')) ?></strong>
                    <p><?= e(t('start.signed_in_body', 'Add as many assets as you like from your dashboard, and open the tag page to download the QR code or copy the NFC text.')) ?></p>
                </div>
                <div class="btn-group">
                    <a class="btn btn-primary btn-lg" href="<?= e(dat_url('dashboard/assets-new.php')) ?>"><?= e(t('dashboard.add_asset', 'Add asset')) ?></a>
                    <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('dashboard/index.php')) ?>"><?= e(t('nav.dashboard', 'Dashboard')) ?></a>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="section">
            <div class="shell split-grid">
                <div>
                    <h2><?= e(t('start.guest_title', 'Try it as a guest, without an account')) ?></h2>
                    <p><?= e(t('start.guest_body', 'Fill in three fields and the portal creates a real tag: a public link, a QR code and the NFC text. It stays reachable for seven days, and moves into your account the moment you register or log in.')) ?></p>
                    <ul class="tick-list">
                        <li><?= e(t('start.guest_point1', 'No email address needed for the guest tag.')) ?></li>
                        <li><?= e(t('start.guest_point2', 'The public link works immediately, so you can test it on your own phone.')) ?></li>
                        <li><?= e(t('start.guest_point3', 'One guest tag per session, kept for seven days unless it is claimed.')) ?></li>
                    </ul>
                </div>

                <div class="form-card">
                    <?php if ($errors): ?>
                        <div class="flash flash-error" role="alert">
                            <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($guestAsset !== null): ?>
                        <h3><?= e(t('start.guest_exists_title', 'Your guest tag is ready')) ?></h3>
                        <p><?= e(t('start.guest_exists_body', 'Use the panel below to download the QR code or copy the NFC text.')) ?></p>
                        <div class="btn-group">
                            <a class="btn btn-primary" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('start.keep_tag', 'Keep it, create an account')) ?></a>
                            <a class="btn btn-ghost" href="<?= e(dat_url('account/login.php')) . '?next=' . urlencode('/start') ?>"><?= e(t('start.have_account', 'I already have an account')) ?></a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= e(dat_url('start')) ?>" novalidate>
                            <?= dat_csrf_field() ?>
                            <div class="honeypot" aria-hidden="true">
                                <label for="website"><?= e(t('form.website', 'Website')) ?></label>
                                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="form-row">
                                <label><?= e(t('asset.type', 'Type')) ?></label>
                                <?php foreach (dat_asset_type_categories() as $category): ?>
                                    <fieldset class="type-group-select">
                                        <legend><?= e($category['label']) ?></legend>
                                        <div class="type-select">
                                            <?php foreach ($category['types'] as $typeId): ?>
                                                <?php $type = dat_asset_types()[$typeId] ?? null; if ($type === null) continue; ?>
                                                <label class="type-option">
                                                    <input type="radio" name="type" value="<?= (int) $typeId ?>"
                                                           data-type-key="<?= e($type['key']) ?>"
                                                           <?= (int) $typeId === DAT_ASSET_TYPE_MENU_BOARD ? 'checked' : '' ?>>
                                                    <span>
                                                        <em aria-hidden="true"><i class="<?= e($type['icon']) ?>"></i></em>
                                                        <?= e($type['label']) ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-row">
                                <label for="name"><?= e(t('asset.name', 'Name')) ?></label>
                                <input type="text" id="name" name="name" required maxlength="120"
                                       value="<?= e((string) ($_POST['name'] ?? '')) ?>"
                                       placeholder="<?= e(t('asset.name_placeholder', 'Lucky')) ?>">
                            </div>

                            <div class="form-row">
                                <label for="description"><?= e(t('asset.description', 'Description')) ?></label>
                                <textarea id="description" name="description" maxlength="2000"
                                          placeholder="<?= e(t('start.description_placeholder', 'Optional. Anything a finder should know.')) ?>"><?= e((string) ($_POST['description'] ?? '')) ?></textarea>
                            </div>

                            <?php foreach (dat_asset_types() as $type): ?>
                                <div class="meta-group" data-meta-group="<?= e($type['key']) ?>">
                                    <div class="meta-grid">
                                        <?php foreach ($type['fields'] as $field): ?>
                                            <?php if (empty($field['public'])) continue; ?>
                                            <?php $fieldId = 'guest_' . $type['key'] . '_' . $field['key']; ?>
                                            <div>
                                                <label for="<?= e($fieldId) ?>"><?= e($field['label']) ?></label>
                                                <input type="text" id="<?= e($fieldId) ?>"
                                                       name="metadata[<?= e($field['key']) ?>]" maxlength="120">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <?= e(t('start.guest_submit', 'Create my guest tag')) ?>
                            </button>
                            <p class="form-hint"><?= e(t('start.guest_hint', 'Private fields such as serial numbers are not part of the guest form. Any guest tag is removed after seven days if it is not claimed.')) ?></p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($guestAsset !== null): ?>
            <section class="section section-split" id="your-tag">
                <div class="shell">
                    <?php if ($justCreated): ?>
                        <div class="notice notice-ok">
                            <strong><?= e(t('start.created_title', 'Your tag is live')) ?></strong>
                            <p><?= e(t('start.created_body', 'Open the link on your phone, then download the QR code or copy the NFC text and write your chip.')) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="split-grid">
                        <div>
                            <h2><?= e(t('start.tag_panel_title', 'Your tag')) ?></h2>
                            <dl class="detail-list">
                                <div><dt><?= e(t('asset.public_link', 'Public link')) ?></dt><dd><code><?= e($publicUrl) ?></code></dd></div>
                                <div><dt><?= e(t('asset.public_id', 'Public ID')) ?></dt><dd><code><?= e($guestAsset['public_id']) ?></code></dd></div>
                                <div><dt><?= e(t('asset.type', 'Type')) ?></dt><dd><?= e(dat_asset_type_label($guestAsset['type'])) ?></dd></div>
                                <div><dt><?= e(t('asset.name', 'Name')) ?></dt><dd><?= e($guestAsset['name']) ?></dd></div>
                            </dl>

                            <h3><?= e(t('start.nfc_title', 'NFC text to write')) ?></h3>
                            <pre class="nfc-sample"><?= e($nfcPayload) ?></pre>
                            <p class="form-hint"><?= e(sprintf(t('tag.nfc_size', '%d bytes written.'), dat_nfc_payload_bytes($nfcPayload))) ?></p>

                            <div class="btn-group">
                                <button type="button" class="btn btn-ghost" data-copy="<?= e($nfcPayload) ?>" data-copy-label="<?= e(t('tag.copied', 'Copied')) ?>">
                                    <i class="fa-solid fa-copy" aria-hidden="true"></i><span><?= e(t('tag.copy_nfc', 'Copy text')) ?></span>
                                </button>
                                <a class="btn btn-ghost" href="<?= e(dat_url('qr/nfc.php')) ?>?id=<?= e(rawurlencode($guestAsset['public_id'])) ?>&download=1"><?= e(t('tag.download_txt', 'Download .txt')) ?></a>
                                <a class="btn btn-ghost" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e(t('dashboard.open_public', 'Public page')) ?></a>
                            </div>

                            <div class="btn-group" style="margin-top:12px">
                                <a class="btn btn-ghost" href="<?= e(dat_url('qr/image.php')) ?>?id=<?= e(rawurlencode($guestAsset['public_id'])) ?>&format=png&download=1&scale=12"><?= e(t('tag.download_png', 'Download PNG')) ?></a>
                                <a class="btn btn-ghost" href="<?= e(dat_url('qr/image.php')) ?>?id=<?= e(rawurlencode($guestAsset['public_id'])) ?>&format=svg&download=1&scale=12"><?= e(t('tag.download_svg', 'Download SVG')) ?></a>
                            </div>
                        </div>

                        <div class="example-preview">
                            <div class="qr-preview qr-preview-lg"><?= $qrSvg ?></div>
                            <p class="form-hint"><?= e(t('start.qr_hint', 'Print this on the label next to the chip, or stick it on the asset as it is.')) ?></p>
                            <div class="tag-card-frame">
                                <?php dat_tag_card(dat_public_asset($guestAsset), [], ['compact' => true, 'heading_level' => 3]); ?>
                            </div>
                            <div class="notice notice-info">
                                <strong><?= e(t('start.claim_title', 'Want to keep it?')) ?></strong>
                                <p><?= e(t('start.claim_body', 'Register or log in and this tag moves into your account with its link, QR code and messages. Guest tags are removed after seven days if nobody claims them.')) ?></p>
                                <div class="btn-group">
                                    <a class="btn btn-primary" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                                    <a class="btn btn-ghost" href="<?= e(dat_url('account/login.php')) . '?next=' . urlencode('/start') ?>"><?= e(t('nav.login', 'Log in')) ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="section section-split">
            <div class="shell split-grid">
                <div>
                    <h2><?= e(t('start.reg_title', 'Or create an account, in four steps')) ?></h2>
                    <p><?= e(t('start.reg_body', 'An account holds your assets and the messages finders send you. It is deliberately minimal: an email address and a password, nothing more.')) ?></p>
                    <ol class="step-list">
                        <?php foreach ($steps as $index => $step): ?>
                            <li>
                                <span class="step-index" aria-hidden="true"><?= $index + 1 ?></span>
                                <div>
                                    <strong><?= e($step['title']) ?></strong>
                                    <p><?= e($step['body']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="btn-group">
                        <a class="btn btn-primary btn-lg" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                        <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('write-a-tag')) ?>"><?= e(t('guide.title', 'Write a tag')) ?></a>
                    </div>
                </div>
                <div>
                    <h3><?= e(t('start.not_asked_title', 'What the registration does not ask for')) ?></h3>
                    <ul class="tick-list">
                        <li><?= e(t('start.not_asked1', 'Your name or postal address')) ?></li>
                        <li><?= e(t('start.not_asked2', 'A phone number')) ?></li>
                        <li><?= e(t('start.not_asked3', 'A date of birth')) ?></li>
                        <li><?= e(t('start.not_asked4', 'Payment details, there is nothing to pay here')) ?></li>
                    </ul>
                    <p class="form-hint"><?= e(t('start.not_asked_hint', 'The public tag page never shows owner contact details, so none of it needs to be collected in the first place.')) ?></p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <div class="shell">
            <h2><?= e(t('start.write_title', 'Then write the tag')) ?></h2>
            <p class="section-lead"><?= e(t('start.write_body', 'Any app that can write an NDEF record works. The portal does not depend on a specific one, because the chip only carries a standard link.')) ?></p>
            <ol class="steps">
                <li class="step">
                    <span class="step-index" aria-hidden="true">1</span>
                    <h3><?= e(t('start.write1_title', 'Copy the link or the text')) ?></h3>
                    <p><?= e(t('start.write1_body', 'Every asset shows its public link and the short NFC text in the portal, ready to copy or download.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">2</span>
                    <h3><?= e(t('start.write2_title', 'Write it with an NFC app')) ?></h3>
                    <p><?= e(t('start.write2_body', 'NFC Tools or a comparable writer stores the link as a URI record. Hold the phone over the chip until the app confirms.')) ?></p>
                </li>
                <li class="step">
                    <span class="step-index" aria-hidden="true">3</span>
                    <h3><?= e(t('start.write3_title', 'Test it, then attach it')) ?></h3>
                    <p><?= e(t('start.write3_body', 'Tap the chip once with the screen unlocked and check that the tag page opens before you put it on the asset.')) ?></p>
                </li>
            </ol>
            <div class="btn-group">
                <a class="btn btn-ghost btn-lg" href="<?= e(dat_url('write-a-tag')) ?>">
                    <?= e(t('start.write_guide', 'Open the step-by-step writing guide')) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
</main>
<?php
dat_page_end();

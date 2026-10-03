<?php
/**
 * Anonymous "I found this" form for a public tag page.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? $_POST['id'] ?? '')));
$asset = $publicId !== '' ? dat_asset_by_public_id($publicId) : null;

if ($asset === null || !dat_asset_is_public($asset['status'])) {
    http_response_code(404);
    dat_page_start([
        'title' => t('tag.not_found_title', 'Tag not found') . ' | ' . PORTAL_NAME,
        'robots' => 'noindex, nofollow',
        'unread' => 0,
    ]);
    ?>
    <main id="main" class="shell">
        <section class="tag-page">
            <div class="empty-state">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                <h1><?= e(t('tag.not_found_title', 'Tag not found')) ?></h1>
                <p><?= e(t('tag.not_found_body', 'This link does not match any registered asset. Check the address, or scan the tag again.')) ?></p>
            </div>
        </section>
    </main>
    <?php
    dat_page_end();
    exit;
}

$errors = [];
$content = '';
$sent = false;
$token = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $content = (string) ($_POST['message'] ?? '');

    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } elseif (!empty($_POST['reference'])) {
        // Honeypot: report success to bots without storing anything.
        $sent = true;
    } else {
        $result = dat_create_finder_message($asset, $content);
        if ($result['success']) {
            $sent = true;
            $token = $result['token'];

            // A lost report that reaches the owner flips the asset to "found".
            if ((int) $asset['status'] === DAT_ASSET_STATUS_LOST) {
                dat_set_asset_status($asset['id'], $asset['owner_id'], DAT_ASSET_STATUS_FOUND);
            }
        } else {
            $errors = $result['errors'] ?? [];
        }
    }
}

$threadUrl = $token !== null
    ? dat_url('t/thread.php') . '?id=' . rawurlencode($asset['public_id']) . '&token=' . rawurlencode($token)
    : null;

$typeMeta = dat_asset_type_meta($asset['type']);

dat_page_start([
    'title' => t('found.title', 'Send a message') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => 0,
]);
?>
<main id="main" class="shell">
    <section class="tag-page">
        <?php if ($sent): ?>
            <div class="form-card">
                <h1><?= e(t('found.sent_title', 'Thank you')) ?></h1>
                <p><?= e(t('found.sent_body', 'Your message has been delivered to the owner. They can answer you here; you do not need an account.')) ?></p>
                <?php if ($threadUrl !== null): ?>
                    <div class="notice notice-info">
                        <strong><?= e(t('found.link_title', 'Keep this link')) ?></strong>
                        <p><?= e(t('found.link_body', 'It is the only way back to your conversation. Anyone with the link can read and continue the thread, so keep it private.')) ?></p>
                        <p><code><?= e($threadUrl) ?></code></p>
                    </div>
                <?php endif; ?>
                <div class="btn-group">
                    <?php if ($threadUrl !== null): ?>
                        <a class="btn btn-primary" href="<?= e($threadUrl) ?>"><?= e(t('found.open_thread', 'Open the conversation')) ?></a>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="<?= e(dat_tag_url($asset['public_id'])) ?>"><?= e(t('found.back_to_tag', 'Back to the tag page')) ?></a>
                </div>
            </div>
        <?php else: ?>
            <p class="tag-page-nav">
                <a href="<?= e(dat_tag_url($asset['public_id'])) ?>">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <?= e(t('found.back_to_tag', 'Back to the tag page')) ?>
                </a>
            </p>

            <div class="form-card">
                <h1>
                    <?= dat_type_mark($asset['type'], null, 'tag-type-mark') ?>
                    <?= e(t('found.heading', 'I found this asset')) ?>
                </h1>
                <p><?= e(t('found.intro', 'Write a short message. The owner sees it in their dashboard and can answer you here. No name, email or phone number is required.')) ?></p>
                <p class="form-hint"><?= e(sprintf(t('found.about', 'About: %s'), $asset['name'])) ?></p>

                <?php if ($errors): ?>
                    <div class="flash flash-error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= e($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(dat_url('t/found.php')) ?>">
                    <?= dat_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($asset['public_id']) ?>">
                    <div class="honeypot" aria-hidden="true">
                        <label for="reference"><?= e(t('form.reference', 'Reference')) ?></label>
                        <input type="text" id="reference" name="reference" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row">
                        <label for="message"><?= e(t('found.message_label', 'Your message')) ?></label>
                        <textarea id="message" name="message" maxlength="1000" required
                                  data-counter="#message-counter"
                                  placeholder="<?= e(t('found.placeholder', 'I found this near the station this morning. It looks well cared for.')) ?>"><?= e($content) ?></textarea>
                        <span class="form-hint"><span id="message-counter">0 / 1000</span></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        <?= e(t('found.submit', 'Send message')) ?>
                    </button>
                </form>

                <p class="tag-privacy">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    <?= e(t('found.privacy', 'Nothing about you is stored: no name, no email address, no phone number. Only your message is kept, and it expires automatically.')) ?>
                </p>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
dat_page_end();

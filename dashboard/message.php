<?php
/**
 * One finder thread, with the owner reply box.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$user = dat_require_login();
$assetId = (string) ($_GET['asset'] ?? $_POST['asset'] ?? '');
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$asset = dat_asset_for_owner($assetId, $user['id']);

if ($asset === null || $token === '') {
    http_response_code(404);
    dat_flash_set('error', t('error.message_missing', 'That conversation no longer exists.'));
    header('Location: ' . dat_url('dashboard/messages.php'));
    exit;
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!dat_csrf_verify()) {
        $errors[] = t('error.csrf', 'Your session expired. Please try again.');
    } elseif ($action === 'reply') {
        $result = dat_owner_reply($asset['id'], $token, $user['id'], (string) ($_POST['reply'] ?? ''));
        if ($result['success']) {
            dat_flash_set('success', t('messages.reply_sent', 'Reply sent. The finder sees it on their private conversation link.'));
            header('Location: ' . dat_url('dashboard/message.php') . '?asset=' . urlencode($asset['id']) . '&token=' . urlencode($token));
            exit;
        }
        $errors = $result['errors'] ?? [];
    } elseif ($action === 'archive') {
        dat_archive_thread($asset['id'], $token, $user['id']);
        dat_flash_set('info', t('messages.archived', 'Conversation archived.'));
        header('Location: ' . dat_url('dashboard/messages.php'));
        exit;
    }
}

$messages = dat_message_thread_messages($asset['id'], $token, $user['id']);
$unread = dat_unread_message_count($user['id']);

dat_page_start([
    'title' => t('messages.thread_title', 'Conversation') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => $unread,
]);
?>
<main id="main" class="shell">
    <?= dat_flash_render() ?>

    <div class="page-head">
        <div>
            <h1><?= e(t('messages.thread_title', 'Conversation')) ?></h1>
            <p>
                <a href="<?= e(dat_url('dashboard/asset.php')) ?>?id=<?= e(urlencode($asset['id'])) ?>"><?= e($asset['name']) ?></a>
                &middot; <?= e(dat_asset_type_label($asset['type'])) ?>
                &middot; <?= dat_status_pill($asset['status']) ?>
            </p>
        </div>
        <div class="btn-group">
            <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/messages.php')) ?>"><?= e(t('messages.back', 'All messages')) ?></a>
            <form method="post" action="<?= e(dat_url('dashboard/message.php')) ?>" data-confirm="<?= e(t('messages.archive_confirm', 'Archive this conversation?')) ?>">
                <?= dat_csrf_field() ?>
                <input type="hidden" name="asset" value="<?= e($asset['id']) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="archive">
                <button type="submit" class="btn btn-quiet"><?= e(t('messages.archive', 'Archive')) ?></button>
            </form>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">
            <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <?php if (!$messages): ?>
            <p class="form-hint"><?= e(t('messages.gone', 'No messages left in this conversation.')) ?></p>
        <?php else: ?>
            <div class="conversation">
                <?php foreach ($messages as $message): ?>
                    <?php $fromOwner = (int) $message['direction'] === DAT_MESSAGE_DIRECTION_OWNER; ?>
                    <div class="bubble <?= $fromOwner ? 'bubble-owner' : 'bubble-finder' ?>">
                        <p><?= nl2br(e($message['content'])) ?></p>
                        <time datetime="<?= e($message['created_at']) ?>">
                            <?= e($fromOwner ? t('messages.you', 'You') : t('messages.finder', 'Finder')) ?>
                            &middot; <?= e(date('d.m.Y H:i', strtotime($message['created_at']))) ?>
                        </time>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2><?= e(t('messages.reply_title', 'Reply')) ?></h2>
        <p class="form-hint"><?= e(t('messages.reply_hint', 'The finder reads your answer on the private link they were given. Your name and email address are not shown.')) ?></p>
        <form method="post" action="<?= e(dat_url('dashboard/message.php')) ?>">
            <?= dat_csrf_field() ?>
            <input type="hidden" name="asset" value="<?= e($asset['id']) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="reply">
            <div class="form-row">
                <label for="reply"><?= e(t('messages.reply_label', 'Your answer')) ?></label>
                <textarea id="reply" name="reply" maxlength="1000" required
                          data-counter="#reply-counter"
                          placeholder="<?= e(t('messages.reply_placeholder', 'Thank you for getting in touch. I can collect it this evening.')) ?>"></textarea>
                <span class="form-hint"><span id="reply-counter">0 / 1000</span></span>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(t('messages.reply_submit', 'Send reply')) ?></button>
        </form>
    </div>
</main>
<?php
dat_page_end();

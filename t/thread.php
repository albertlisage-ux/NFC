<?php
/**
 * Finder side of a conversation. Access requires the private thread token
 * that was shown once, right after the message was sent.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$token = trim((string) ($_GET['token'] ?? ''));
$asset = $publicId !== '' ? dat_asset_by_public_id($publicId) : null;
$messages = ($asset !== null && $token !== '') ? dat_message_thread($asset['id'], $token, $publicId) : [];

dat_page_start([
    'title' => t('thread.title', 'Your conversation') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => 0,
]);
?>
<main id="main" class="shell">
    <section class="tag-page">
        <p class="tag-page-nav">
            <a href="<?= e($asset !== null ? dat_tag_url($asset['public_id']) : dat_url('index.php')) ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <?= e($asset !== null ? t('found.back_to_tag', 'Back to the tag page') : t('tag.back_home', 'Go to the start page')) ?>
            </a>
        </p>

        <div class="panel">
            <div class="panel-head">
                <h1><?= e(t('thread.heading', 'Conversation about this asset')) ?></h1>
                <?php if ($asset !== null): ?><?= dat_status_pill($asset['status']) ?><?php endif; ?>
            </div>

            <?php if (!$messages): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                    <p><?= e(t('thread.invalid', 'This conversation link is not valid any more, or the messages have expired.')) ?></p>
                </div>
            <?php else: ?>
                <div class="conversation">
                    <?php foreach ($messages as $message): ?>
                        <?php $fromOwner = (int) $message['direction'] === DAT_MESSAGE_DIRECTION_OWNER; ?>
                        <div class="bubble <?= $fromOwner ? 'bubble-owner' : 'bubble-finder' ?>">
                            <p><?= nl2br(e($message['content'])) ?></p>
                            <time datetime="<?= e($message['created_at']) ?>">
                                <?= e($fromOwner ? t('thread.from_owner', 'Owner') : t('thread.from_you', 'You')) ?>
                                &middot; <?= e(date('d.m.Y H:i', strtotime($message['created_at']))) ?>
                            </time>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="form-hint"><?= e(t('thread.note', 'Keep this link private: anyone who has it can read the conversation.')) ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
dat_page_end();

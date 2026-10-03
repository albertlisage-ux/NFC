<?php
/**
 * Owner inbox: one row per finder thread.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$user = dat_require_login();
$threads = dat_message_threads_for_owner($user['id']);
$unread = dat_unread_message_count($user['id']);

dat_page_start([
    'title' => t('messages.title', 'Messages') . ' | ' . PORTAL_NAME,
    'robots' => 'noindex, nofollow',
    'unread' => $unread,
]);
?>
<main id="main" class="shell">
    <?= dat_flash_render() ?>

    <div class="page-head">
        <div>
            <h1><?= e(t('messages.title', 'Messages')) ?></h1>
            <p><?= e(t('messages.subtitle', 'Anonymous messages from finders. Answer inside the portal, no contact details are exchanged.')) ?></p>
        </div>
        <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/index.php')) ?>"><?= e(t('nav.dashboard', 'Dashboard')) ?></a>
    </div>

    <?php if (!$threads): ?>
        <div class="empty-state">
            <i class="fa-solid fa-inbox" aria-hidden="true"></i>
            <h2><?= e(t('messages.empty_title', 'No messages yet')) ?></h2>
            <p><?= e(t('messages.empty_body', 'When someone scans one of your tags and writes to you, the conversation appears here.')) ?></p>
        </div>
    <?php else: ?>
        <div class="panel">
            <ul class="thread-list">
                <?php foreach ($threads as $thread): ?>
                    <?php
                    $link = dat_url('dashboard/message.php')
                        . '?asset=' . urlencode($thread['asset_id'])
                        . '&token=' . urlencode($thread['sender_token']);
                    ?>
                    <li>
                        <a class="thread-link" href="<?= e($link) ?>">
                            <?= dat_type_mark((int) $thread['asset_type'], null, 'tag-type-mark') ?>
                            <span class="thread-body">
                                <strong><?= e($thread['asset_name']) ?></strong>
                                <span class="thread-preview"><?= e(mb_substr(trim((string) $thread['preview']), 0, 120)) ?></span>
                            </span>
                            <span class="thread-meta">
                                <?= e(date('d.m.Y H:i', strtotime($thread['last_at']))) ?>
                                <br>
                                <?= (int) $thread['message_count'] ?> <?= e(t('messages.count', 'messages')) ?>
                                <?php if ((int) $thread['unread_count'] > 0): ?>
                                    <br><span class="pill pill-warn"><?= (int) $thread['unread_count'] ?> <?= e(t('messages.new', 'new')) ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</main>
<?php
dat_page_end();

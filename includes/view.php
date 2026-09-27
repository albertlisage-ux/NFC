<?php
/**
 * View helpers: page shell, navigation, cards and small UI building blocks.
 *
 * The public tag card below is rendered both on /t/{publicId} and, with sample
 * data, as a preview on the home page. One component, two consumers, so the
 * preview can never drift away from the real page.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/assets.php';

if (!function_exists('dat_asset_url')) {
    function dat_asset_url($path = '')
    {
        return dat_url('assets/' . ltrim((string) $path, '/'));
    }
}

if (!function_exists('dat_upload_url')) {
    function dat_upload_url($objectKey)
    {
        return dat_url('storage/uploads/' . ltrim((string) $objectKey, '/'));
    }
}

if (!function_exists('dat_current_path')) {
    function dat_current_path()
    {
        return strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    }
}

if (!function_exists('dat_lang_switch_url')) {
    function dat_lang_switch_url($lang)
    {
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $query = $_GET;
        $query['lang'] = $lang;
        return $path . '?' . http_build_query($query);
    }
}

/* -------------------------------------------------------------------------
 * Flash messages
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_flash_set')) {
    function dat_flash_set($type, $message)
    {
        dat_session_start();
        $_SESSION['dat_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('dat_flash_take')) {
    function dat_flash_take()
    {
        dat_session_start();
        $messages = $_SESSION['dat_flash'] ?? [];
        unset($_SESSION['dat_flash']);
        return $messages;
    }
}

if (!function_exists('dat_flash_render')) {
    function dat_flash_render()
    {
        $messages = dat_flash_take();
        if (!$messages) {
            return '';
        }

        $tones = [
            'success' => 'flash flash-success',
            'error' => 'flash flash-error',
            'info' => 'flash flash-info',
        ];

        $html = '';
        foreach ($messages as $message) {
            $class = $tones[$message['type']] ?? $tones['info'];
            $html .= '<div class="' . $class . '" role="status">' . e($message['message']) . '</div>';
        }

        return $html;
    }
}

/* -------------------------------------------------------------------------
 * Badges
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_status_pill')) {
    function dat_status_pill($status, $extra = '')
    {
        $meta = dat_asset_status_meta($status);
        return '<span class="pill pill-' . e($meta['tone']) . ' ' . e($extra) . '">'
            . '<i class="' . e($meta['icon']) . '" aria-hidden="true"></i>'
            . e($meta['label'])
            . '</span>';
    }
}

if (!function_exists('dat_type_pill')) {
    function dat_type_pill($type, $extra = '')
    {
        $meta = dat_asset_type_meta($type);
        return '<span class="pill pill-neutral ' . e($extra) . '">'
            . '<i class="' . e($meta['icon']) . '" aria-hidden="true"></i>'
            . e($meta['label'])
            . '</span>';
    }
}

/* -------------------------------------------------------------------------
 * Public tag card (shared component)
 * ---------------------------------------------------------------------- */

/**
 * @param array $asset  Result of dat_public_asset().
 * @param array $images List of object keys, newest first.
 * @param array $options ['compact' => bool, 'found_url' => string|null,
 *                        'heading_level' => 1|2|3]
 */
if (!function_exists('dat_tag_card')) {
    function dat_tag_card(array $asset, array $images = [], array $options = [])
    {
        $statusMeta = dat_asset_status_meta($asset['status']);
        $typeMeta = dat_asset_type_meta($asset['type']);
        $isLost = (int) $asset['status'] === DAT_ASSET_STATUS_LOST;
        $isFound = (int) $asset['status'] === DAT_ASSET_STATUS_FOUND;
        $compact = !empty($options['compact']);
        $foundUrl = $options['found_url'] ?? null;
        // The tag page owns the h1; previews embedded in another page use h2.
        $headingLevel = (int) ($options['heading_level'] ?? 1);
        $headingTag = in_array($headingLevel, [1, 2, 3], true) ? 'h' . $headingLevel : 'h1';
        ?>
        <article class="tag-card<?= $compact ? ' tag-card-compact' : '' ?>">
            <header class="tag-card-head">
                <span class="tag-type-mark" aria-hidden="true"><?= e($typeMeta['emoji']) ?></span>
                <div class="tag-card-title">
                    <<?= $headingTag ?> class="tag-title"><?= e($asset['name']) ?></<?= $headingTag ?>>
                    <p><?= e($asset['type_label']) ?><?php
                        $firstMeta = array_slice($asset['metadata'], 0, 1, true);
                        foreach ($firstMeta as $label => $value) {
                            echo ' <span aria-hidden="true">/</span> ' . e($value);
                        }
                    ?></p>
                </div>
                <?= dat_status_pill($asset['status']) ?>
            </header>

            <?php if ($isLost): ?>
                <div class="notice notice-warn">
                    <strong><?= e(t('public.lost_title', 'This asset is reported lost.')) ?></strong>
                    <p><?= e(t('public.lost_body', 'If you have found it, please send a message. The owner will be notified.')) ?></p>
                </div>
            <?php elseif ($isFound): ?>
                <div class="notice notice-info">
                    <strong><?= e(t('public.found_title', 'A finder already reported this asset.')) ?></strong>
                    <p><?= e(t('public.found_body', 'You can still send a message with more details.')) ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-ok">
                    <strong><?= e(t('public.active_title', 'This asset is registered.')) ?></strong>
                    <p><?= e(t('public.active_body', 'Scan a tag, open the page, and get in touch anonymously if something is wrong.')) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($images): ?>
                <div class="tag-photos">
                    <?php foreach (array_slice($images, 0, 3) as $index => $objectKey): ?>
                        <img src="<?= e(dat_upload_url($objectKey)) ?>"
                             alt="<?= e($asset['name']) ?>"
                             <?= $index === 0 ? 'class="tag-photo-lead"' : '' ?>
                             loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($asset['description'])): ?>
                <p class="tag-description"><?= nl2br(e($asset['description'])) ?></p>
            <?php endif; ?>

            <?php if ($asset['metadata']): ?>
                <dl class="tag-facts">
                    <?php foreach ($asset['metadata'] as $label => $value): ?>
                        <div>
                            <dt><?= e($label) ?></dt>
                            <dd><?= e($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if ($foundUrl): ?>
                <a class="btn btn-primary btn-block" href="<?= e($foundUrl) ?>">
                    <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                    <?= e(t('public.found_cta', 'I found this')) ?>
                </a>
            <?php endif; ?>

            <p class="tag-privacy">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                <?= e(t('public.privacy_note', 'This page never shows the owner name, address, phone number or email address.')) ?>
            </p>
        </article>
        <?php
    }
}

/* -------------------------------------------------------------------------
 * Page shell
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_page_start')) {
    function dat_page_start(array $options = [])
    {
        $title = $options['title'] ?? PORTAL_NAME;
        $description = $options['description'] ?? t('meta.description', 'One tag, one identity, one portal. Digital asset tags for pets, bicycles, vehicles, clothing, items and industrial equipment.');
        $robots = $options['robots'] ?? 'index, follow';
        $bodyClass = $options['body_class'] ?? '';
        $user = dat_current_user();
        $canonical = $options['canonical'] ?? (dat_base_url() . dat_current_path());
        $showNav = $options['nav'] ?? true;
        $unread = $options['unread'] ?? 0;
        ?>
<!DOCTYPE html>
<html lang="<?= e(dat_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#0f766e">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(PORTAL_NAME) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <link rel="icon" href="<?= e(dat_asset_url('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= e(dat_asset_url('css/portal.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main"><?= e(t('nav.skip', 'Skip to content')) ?></a>
<?php if ($showNav): ?>
    <header class="site-nav">
        <div class="shell nav-inner">
            <a class="brand" href="<?= e(dat_url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-tag"></i></span>
                <span class="brand-text">
                    <strong><?= e(t('brand.name', 'Digital Asset Tag')) ?></strong>
                    <small><?= e(t('brand.claim', 'One tag. One identity.')) ?></small>
                </span>
            </a>

            <nav class="nav-links" aria-label="<?= e(t('nav.primary', 'Main navigation')) ?>">
                <a href="<?= e(dat_url('how-it-works')) ?>"><?= e(t('nav.how', 'How it works')) ?></a>
                <a href="<?= e(dat_url('use-cases')) ?>"><?= e(t('nav.usecases', 'Use cases')) ?></a>
                <a href="<?= e(dat_url('privacy')) ?>"><?= e(t('nav.privacy', 'Privacy')) ?></a>
            </nav>

            <div class="nav-actions">
                <div class="lang-switch" role="group" aria-label="<?= e(t('nav.language', 'Language')) ?>">
                    <a href="<?= e(dat_lang_switch_url('en')) ?>" class="<?= dat_lang() === 'en' ? 'is-active' : '' ?>" hreflang="en">EN</a>
                    <a href="<?= e(dat_lang_switch_url('de')) ?>" class="<?= dat_lang() === 'de' ? 'is-active' : '' ?>" hreflang="de">DE</a>
                </div>
                <?php if ($user !== null): ?>
                    <a class="btn btn-ghost" href="<?= e(dat_url('dashboard/index.php')) ?>">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                        <?= e(t('nav.dashboard', 'Dashboard')) ?>
                        <?php if ($unread > 0): ?><span class="nav-badge"><?= (int) $unread ?></span><?php endif; ?>
                    </a>
                    <form method="post" action="<?= e(dat_url('account/logout.php')) ?>" class="inline-form">
                        <?= dat_csrf_field() ?>
                        <button type="submit" class="btn btn-quiet"><?= e(t('nav.logout', 'Log out')) ?></button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost" href="<?= e(dat_url('account/login.php')) ?>"><?= e(t('nav.login', 'Log in')) ?></a>
                    <a class="btn btn-primary" href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
                <?php endif; ?>
            </div>

            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-mobile">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
                <span class="sr-only"><?= e(t('nav.menu', 'Menu')) ?></span>
            </button>
        </div>
        <div class="nav-mobile" id="nav-mobile" hidden>
            <a href="<?= e(dat_url('how-it-works')) ?>"><?= e(t('nav.how', 'How it works')) ?></a>
            <a href="<?= e(dat_url('use-cases')) ?>"><?= e(t('nav.usecases', 'Use cases')) ?></a>
            <a href="<?= e(dat_url('privacy')) ?>"><?= e(t('nav.privacy', 'Privacy')) ?></a>
            <?php if ($user !== null): ?>
                <a href="<?= e(dat_url('dashboard/index.php')) ?>"><?= e(t('nav.dashboard', 'Dashboard')) ?></a>
                <a href="<?= e(dat_url('dashboard/messages.php')) ?>"><?= e(t('nav.messages', 'Messages')) ?></a>
            <?php else: ?>
                <a href="<?= e(dat_url('account/login.php')) ?>"><?= e(t('nav.login', 'Log in')) ?></a>
                <a href="<?= e(dat_url('account/register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
            <?php endif; ?>
        </div>
    </header>
<?php endif; ?>
<?php dat_db_banner(); ?>
        <?php
    }
}

if (!function_exists('dat_page_end')) {
    function dat_page_end(array $options = [])
    {
        $showFooter = $options['footer'] ?? true;
        ?>
<?php if ($showFooter): ?>
    <footer class="site-footer">
        <div class="shell footer-inner">
            <div class="footer-brand">
                <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-tag"></i></span>
                <div>
                    <strong><?= e(PORTAL_NAME) ?></strong>
                    <p><?= e(t('footer.tagline', 'Stable digital identities for the things that matter, readable by any phone.')) ?></p>
                </div>
            </div>
            <nav aria-label="<?= e(t('footer.legal', 'Legal')) ?>">
                <a href="<?= e(dat_url('privacy')) ?>"><?= e(t('footer.privacy', 'Privacy')) ?></a>
                <a href="<?= e(dat_url('docs/imprint.php')) ?>"><?= e(t('footer.imprint', 'Imprint')) ?></a>
                <a href="<?= e(dat_url('how-it-works')) ?>"><?= e(t('nav.how', 'How it works')) ?></a>
            </nav>
        </div>
        <div class="shell footer-note">
            <p><?= e(t('footer.note', 'Data minimisation by design: the public page shows asset information, never owner contact details.')) ?></p>
        </div>
    </footer>
<?php endif; ?>
    <script src="<?= e(dat_asset_url('js/portal.js')) ?>" defer></script>
</body>
</html>
        <?php
    }
}

/** Data-unavailable banner, shown instead of a fatal error when MySQL is down. */
if (!function_exists('dat_db_banner')) {
    function dat_db_banner()
    {
        if (dat_db_available()) {
            return;
        }
        ?>
        <div class="shell">
            <div class="notice notice-warn">
                <strong><?= e(t('db.offline_title', 'Database not reachable')) ?></strong>
                <p><?= e(t('db.offline_body', 'Pages render without data until the MySQL connection is restored. Check the values in .env and run scripts/migrate.php.')) ?></p>
            </div>
        </div>
        <?php
    }
}

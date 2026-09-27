<?php
/**
 * The privacy page moved to /privacy. Keep this path working for older links
 * and for anything already printed or bookmarked.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . dat_url('privacy'), true, 301);
exit;

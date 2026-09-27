<?php
/**
 * Logout: POST with a CSRF token, so a stray link cannot end the session.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && dat_csrf_verify()) {
    dat_logout();
    dat_session_start();
    dat_flash_set('info', t('logout.done', 'You are logged out.'));
}

header('Location: ' . dat_url('index.php'));
exit;

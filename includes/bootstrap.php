<?php
/**
 * Single entry point for the shared runtime: configuration, database, domain
 * helpers and the view layer. Every page starts by requiring this file.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/nfc.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/qrcode.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/guest.php';
require_once __DIR__ . '/demo.php';
require_once __DIR__ . '/view.php';

dat_session_start();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self'; font-src 'self' https://cdnjs.cloudflare.com; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
}

<?php
/**
 * Renders the QR images that are used as static assets on the home page.
 *
 *   php scripts/make-qr-assets.php
 *
 * The Wi-Fi code is owner-only through qr/image.php, so the example shown on
 * the marketing page is generated here once and shipped as a file.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/catalog.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$target = DAT_APP_ROOT . '/assets/img/previews';
if (!is_dir($target)) {
    mkdir($target, 0775, true);
}

$board = dat_demo_entry('menu_board');
$wifiPayload = dat_wifi_qr_payload(
    $board['metadata']['wifi_network'] ?? '',
    $board['metadata']['wifi_password'] ?? ''
);

if ($wifiPayload === null) {
    fwrite(STDERR, 'No guest Wi-Fi details in the catalogue entry.' . PHP_EOL);
    exit(1);
}

$qr = dat_qr($wifiPayload, 'M');
if ($qr === null) {
    fwrite(STDERR, 'QR generation failed.' . PHP_EOL);
    exit(1);
}

$png = $qr->toPng(10, 4);
if ($png === null) {
    fwrite(STDERR, 'PNG output unavailable (GD missing?).' . PHP_EOL);
    exit(1);
}

file_put_contents($target . '/wifi-code.png', $png);
echo 'Wrote ' . $target . '/wifi-code.png (' . strlen($png) . ' bytes)' . PHP_EOL;
echo 'Payload: ' . $wifiPayload . PHP_EOL;

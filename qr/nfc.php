<?php
/**
 * NFC payload download: /qr/nfc.php?id=XXXXXXXX[&download=1]
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$asset = dat_asset_by_public_id($publicId);

if ($asset === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unknown tag');
}

$payload = dat_nfc_payload($asset, dat_tag_url($asset['public_id']));

header('Content-Type: text/plain; charset=utf-8');
if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="nfc-' . preg_replace('/[^A-Z0-9]/', '', $publicId) . '.txt"');
}
echo $payload;

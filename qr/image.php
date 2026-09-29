<?php
/**
 * QR image endpoint:
 *   /qr/image.php?id=XXXXXXXX&format=png|svg[&download=1]
 *   /qr/image.php?id=XXXXXXXX&type=wifi&format=png[&download=1]
 *
 * A direct file endpoint rather than a third-party image service, so tag URLs
 * stay inside this installation.
 *
 * The default type encodes what the physical tag carries, which depends on the
 * asset's chip target. The Wi-Fi type is owner-only: it contains the network
 * password and must never be reachable without being signed in.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$format = strtolower((string) ($_GET['format'] ?? 'png'));
$type = strtolower((string) ($_GET['type'] ?? 'tag'));
$download = isset($_GET['download']);

if ($format !== 'svg') {
    $format = 'png';
}

// Any valid public ID may be encoded: the URL is public by design.
if (!dat_is_valid_public_id($publicId)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unknown tag');
}

$asset = dat_asset_by_public_id($publicId);
$payload = null;
$filename = 'tag-' . $publicId;

if ($type === 'wifi') {
    $user = dat_current_user();
    if ($asset === null || $user === null || dat_asset_for_owner($asset['id'], $user['id']) === null) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('The Wi-Fi code is only available to the owner');
    }

    $payload = dat_asset_wifi_payload($asset);
    $filename = 'wifi-' . $publicId;

    if ($payload === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('No guest Wi-Fi details stored for this asset');
    }
} else {
    // Falls back to the plain tag link when the database is unreachable.
    $payload = $asset !== null ? dat_tag_target_url($asset, false) : dat_tag_url($publicId);
}

$qr = dat_qr($payload, 'M');
if ($qr === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('QR generation failed');
}

$scale = min(20, max(2, (int) ($_GET['scale'] ?? 10)));

if ($format === 'svg') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '.svg"');
    echo $qr->toSvg($scale, 4);
    exit;
}

$png = $qr->toPng($scale, 4);
if ($png === null) {
    // GD missing: fall back to SVG rather than failing.
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '.svg"');
    echo $qr->toSvg($scale, 4);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '.png"');
header('Cache-Control: public, max-age=86400');
echo $png;

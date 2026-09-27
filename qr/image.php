<?php
/**
 * QR image endpoint: /qr/image.php?id=XXXXXXXX&format=png|svg[&download=1]
 *
 * A direct file endpoint rather than a third-party image service, so tag URLs
 * stay inside this installation.
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$publicId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$format = strtolower((string) ($_GET['format'] ?? 'png'));
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

$url = dat_tag_url($publicId);
$qr = dat_qr($url, 'M');
if ($qr === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('QR generation failed');
}

$scale = min(20, max(2, (int) ($_GET['scale'] ?? 10)));
$filename = 'tag-' . $publicId;

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

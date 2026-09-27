<?php
/**
 * Smoke tests that do not need a web server or a database.
 *
 *   php tests/smoke.php
 *   php tests/smoke.php --http=http://localhost:8080   (optional HTTP checks)
 */

if (!defined('LINKTEC_SECURE')) {
    define('LINKTEC_SECURE', true);
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..';

require_once __DIR__ . '/../includes/bootstrap.php';

$failures = 0;
$checks = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   " . $label . PHP_EOL;
        return;
    }
    $failures++;
    echo "  FAIL " . $label . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
}

echo 'Digital Asset Tag Portal smoke tests' . PHP_EOL . PHP_EOL;

/* ---------------------------------------------------------------- QR ---- */

echo 'QR encoder' . PHP_EOL;

check('byte capacity v1-M is 14', DatQrCode::byteCapacity(1, 'M') === 14, (string) DatQrCode::byteCapacity(1, 'M'));
check('byte capacity v2-M is 26', DatQrCode::byteCapacity(2, 'M') === 26, (string) DatQrCode::byteCapacity(2, 'M'));
check('byte capacity v4-M is 62', DatQrCode::byteCapacity(4, 'M') === 62, (string) DatQrCode::byteCapacity(4, 'M'));
check('byte capacity v8-M is 152', DatQrCode::byteCapacity(8, 'M') === 152, (string) DatQrCode::byteCapacity(8, 'M'));
check('byte capacity v10-M is 213', DatQrCode::byteCapacity(10, 'M') === 213, (string) DatQrCode::byteCapacity(10, 'M'));
check('byte capacity v1-L is 17', DatQrCode::byteCapacity(1, 'L') === 17, (string) DatQrCode::byteCapacity(1, 'L'));

$qrText = 'https://example.com/t/DEMTAG24';
$qr = DatQrCode::encodeText($qrText, 'M');
$expectedSize = $qr->version() * 4 + 17;
check('module grid size matches version', $qr->size() === $expectedSize, $qr->size() . ' vs ' . $expectedSize);

// Finder patterns: 7x7 rings at three corners.
$finderOk = true;
foreach ([[0, 0], [$qr->size() - 7, 0], [0, $qr->size() - 7]] as [$fx, $fy]) {
    for ($y = 0; $y < 7; $y++) {
        for ($x = 0; $x < 7; $x++) {
            $distance = max(abs($x - 3), abs($y - 3));
            $expected = $distance !== 2;
            if ($qr->isDark($fx + $x, $fy + $y) !== $expected) {
                $finderOk = false;
            }
        }
    }
}
check('finder patterns are correct', $finderOk);

$timingOk = true;
for ($i = 8; $i < $qr->size() - 8; $i++) {
    if ($qr->isDark($i, 6) !== ($i % 2 === 0) || $qr->isDark(6, $i) !== ($i % 2 === 0)) {
        $timingOk = false;
    }
}
check('timing patterns alternate', $timingOk);
check('dark module is set', $qr->isDark(8, $qr->size() - 8));

// Reed-Solomon: every block must be a valid codeword of the generator.
$rsOk = true;
$blockCount = 0;
foreach (DatQrCode::blocks($qrText, 'M') as $block) {
    $blockCount++;
    foreach (DatQrCode::syndromes($block['codewords'], $block['ec']) as $syndrome) {
        if ($syndrome !== 0) {
            $rsOk = false;
        }
    }
}
check('Reed-Solomon syndromes are zero', $rsOk);
check('at least one block was produced', $blockCount > 0);

// Version selection grows with payload length.
$short = DatQrCode::versionFor('short', 'M');
$long = DatQrCode::versionFor(str_repeat('a', 200), 'M');
check('longer payload uses a higher version', $long > $short, $short . ' -> ' . $long);

$svg = dat_qr_svg($qrText, 4, 4);
check('SVG output contains paths', strpos($svg, '<path') !== false && strlen($svg) > 500);

if (function_exists('imagecreatetruecolor')) {
    $png = $qr->toPng(4, 4);
    check('PNG output is a PNG image', is_string($png) && strncmp($png, "\x89PNG", 4) === 0);
} else {
    echo "  skip PNG output (GD extension missing)" . PHP_EOL;
}

/* ------------------------------------------------------- public ID ----- */

echo PHP_EOL . 'Public IDs' . PHP_EOL;

$alphabet = dat_public_id_alphabet();
check('alphabet excludes ambiguous characters', strpos($alphabet, 'O') === false && strpos($alphabet, 'I') === false);
check('alphabet is 32 characters', strlen($alphabet) === 32, (string) strlen($alphabet));

$ids = [];
for ($i = 0; $i < 500; $i++) {
    $ids[] = dat_generate_public_id();
}
check('generated IDs are unique', count(array_unique($ids)) === 500);
check('generated IDs are valid', count(array_filter($ids, 'dat_is_valid_public_id')) === 500);
check('lowercase IDs are rejected', !dat_is_valid_public_id('abc123'));
check('IDs with ambiguous characters are rejected', !dat_is_valid_public_id('ABCIO123'));

/* ------------------------------------------------------ NFC payload ---- */

echo PHP_EOL . 'NFC payload' . PHP_EOL;

$assetRow = [
    'public_id' => 'DEMTAG24',
    'type' => DAT_ASSET_TYPE_PET,
    'name' => 'Lucky',
    'description' => '',
    'status' => DAT_ASSET_STATUS_ACTIVE,
    'metadata_json' => json_encode(['animal' => 'Dog', 'breed' => 'Golden Retriever', 'color' => 'Golden']),
];

$payload = dat_nfc_payload($assetRow, 'https://example.com/t/DEMTAG24');
check('payload starts with the asset name', strpos($payload, 'Lucky') === 0);
check('payload contains the type and species', strpos($payload, 'Pet / Dog') !== false);
$expectedUrl = 'https://example.com/t/DEMTAG24';
check('payload ends with the stable URL', substr_compare(rtrim($payload), $expectedUrl, -strlen($expectedUrl)) === 0, $payload);
check('payload stays short', dat_nfc_payload_bytes($payload) < 137, (string) dat_nfc_payload_bytes($payload));
check('payload never contains contact placeholders', stripos($payload, '@') === false);

/* ------------------------------------------------ public projection ---- */

echo PHP_EOL . 'Public projection' . PHP_EOL;

$public = dat_public_asset($assetRow);
check('public projection drops the internal id', !array_key_exists('id', $public));
check('public projection drops the owner id', !array_key_exists('owner_id', $public));
check('public metadata excludes nothing here', count($public['metadata']) === 3, (string) count($public['metadata']));

$vehicleRow = [
    'public_id' => 'CARTAG24',
    'type' => DAT_ASSET_TYPE_VEHICLE,
    'name' => 'Estate car',
    'description' => '',
    'status' => DAT_ASSET_STATUS_ACTIVE,
    'metadata_json' => json_encode(['brand' => 'BMW', 'vin' => 'WBADT43452G296760', 'license_plate' => 'B-AB 1234']),
];
$vehiclePublic = dat_public_asset($vehicleRow);
check('private fields stay out of the public page', !isset($vehiclePublic['metadata']['VIN']) && !isset($vehiclePublic['metadata']['Licence plate']));
check('public fields still render', isset($vehiclePublic['metadata']['Brand']));

/* ------------------------------------------------------------- URLs ---- */

echo PHP_EOL . 'URLs' . PHP_EOL;

check('tag URL uses the stable pattern', dat_tag_url('DEMTAG24') === dat_base_url() . '/t/DEMTAG24', dat_tag_url('DEMTAG24'));
check('assets resolve to the asset directory', strpos(dat_asset_url('css/portal.css'), '/assets/css/portal.css') !== false);

/* ------------------------------------------------------ static files --- */

echo PHP_EOL . 'Static files' . PHP_EOL;

foreach (['assets/css/portal.css', 'assets/js/portal.js', 'assets/img/favicon.svg', 'config/schema.sql'] as $file) {
    check('exists: ' . $file, is_file(DAT_APP_ROOT . '/' . $file));
}

/* ------------------------------------------------------ optional HTTP -- */

$httpBase = null;
foreach ($argv ?? [] as $argument) {
    if (strpos($argument, '--http=') === 0) {
        $httpBase = rtrim(substr($argument, 7), '/');
    }
}

if ($httpBase !== null) {
    echo PHP_EOL . 'HTTP checks against ' . $httpBase . PHP_EOL;

    $fetch = static function (string $url): array {
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return ['status' => $status, 'body' => (string) $body];
    };

    foreach ([
        '/' => 200,
        '/account/login.php' => 200,
        '/account/register.php' => 200,
        '/docs/privacy.php' => 200,
        '/docs/imprint.php' => 200,
        '/t/DEMTAG24' => null,          // 200 with the demo asset, 404 without it
        '/t/ZZZZ9999' => 404,
        '/qr/image.php?id=DEMTAG24&format=svg' => 200,
    ] as $path => $expected) {
        $response = $fetch($httpBase . $path);
        if ($expected === null) {
            check('reachable: ' . $path, in_array($response['status'], [200, 404], true), 'status ' . $response['status']);
        } else {
            check('status ' . $expected . ': ' . $path, $response['status'] === $expected, 'got ' . $response['status']);
        }
    }
}

echo PHP_EOL . ($failures === 0
    ? "All {$checks} checks passed." . PHP_EOL
    : "{$failures} of {$checks} checks failed." . PHP_EOL);

exit($failures === 0 ? 0 : 1);

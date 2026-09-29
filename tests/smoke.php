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
require_once __DIR__ . '/../includes/catalog.php';

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

$boardRow = [
    'public_id' => 'MENUTAG2',
    'type' => DAT_ASSET_TYPE_MENU_BOARD,
    'name' => 'Café menu board',
    'description' => '',
    'status' => DAT_ASSET_STATUS_ACTIVE,
    'metadata_json' => json_encode([
        'material' => 'Acrylic',
        'wifi_network' => 'Cafe-Guest',
        'wifi_password' => 'Sonnenaufgang-2026',
    ]),
];
$boardPublic = dat_public_asset($boardRow);
check('private fields stay out of the public page', !isset($boardPublic['metadata']['Wi-Fi password']));
check('public fields still render', isset($boardPublic['metadata']['Material'], $boardPublic['metadata']['Guest Wi-Fi name']));

/* ------------------------------------------------------------- URLs ---- */

echo PHP_EOL . 'URLs' . PHP_EOL;

check('tag URL uses the stable pattern', dat_tag_url('DEMTAG24') === dat_base_url() . '/t/DEMTAG24', dat_tag_url('DEMTAG24'));
check('assets resolve to the asset directory', strpos(dat_asset_url('css/portal.css'), '/assets/css/portal.css') !== false);

/* ---------------------------------------------------- worked examples -- */

echo PHP_EOL . 'Worked examples' . PHP_EOL;

$catalog = dat_demo_catalog();
$configuredTypes = dat_asset_type_ids();
$catalogTypes = array_column($catalog, 'type');
check('catalog covers every configured type', array_diff($configuredTypes, $catalogTypes) === [],
    implode(',', array_diff($configuredTypes, $catalogTypes)));
check('catalog adds no unknown types', array_diff($catalogTypes, $configuredTypes) === []);
check('every type belongs to exactly one category', (static function () {
    $seen = [];
    foreach (dat_asset_type_categories() as $category) {
        foreach ($category['types'] as $typeId) {
            $seen[] = $typeId;
        }
    }
    $types = dat_asset_type_ids();
    sort($seen);
    sort($types);
    return $seen === $types && count($seen) === count(array_unique($seen)) && $seen !== [];
})(), 'categories do not cover the type list exactly');
check('catalog entries name an image file', array_filter(array_column($catalog, 'image'), static fn ($value) => trim((string) $value) === '') === []);
check('catalog image names are safe', array_filter(array_column($catalog, 'image'), static fn ($value) => preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $value) !== 1) === []);

/* -------------------------------------------------------- WhatsApp ------ */

echo PHP_EOL . 'WhatsApp contact' . PHP_EOL;

check('a plain number is accepted', dat_normalize_whatsapp('+49 170 1234567') === '+491701234567', (string) dat_normalize_whatsapp('+49 170 1234567'));
check('dashes and brackets are stripped', dat_normalize_whatsapp('(0170) 123-45 67') === '01701234567');
check('a number without plus keeps working', dat_normalize_whatsapp('0049 170 1234567') === '00491701234567');
check('empty stays empty', dat_normalize_whatsapp('   ') === null);
check('too short is rejected', dat_normalize_whatsapp('12345') === null);
check('too long is rejected', dat_normalize_whatsapp('1234567890123456789') === null);
check('letters alone are rejected', dat_normalize_whatsapp('call me') === null);

$waAsset = [
    'public_id' => 'MENUTAG2',
    'name' => 'Café menu board',
    'contact_whatsapp' => '+49 170 1234567',
];
$waUrl = dat_whatsapp_url($waAsset, 'Hello');
check('wa.me link is built', strpos($waUrl, 'https://wa.me/491701234567?text=') === 0, (string) $waUrl);
check('wa.me link carries the message', strpos((string) $waUrl, rawurlencode('Hello')) !== false);
check('no number means no link', dat_whatsapp_url(['contact_whatsapp' => '']) === null);
check('the ready-made message names the asset', strpos(dat_whatsapp_message($waAsset), 'Café menu board') !== false);

$waPublic = dat_public_asset([
    'public_id' => 'MENUTAG2',
    'type' => DAT_ASSET_TYPE_MENU_BOARD,
    'name' => 'Café menu board',
    'description' => '',
    'status' => DAT_ASSET_STATUS_ACTIVE,
    'metadata_json' => '{}',
    'contact_whatsapp' => '+491701234567',
]);
check('the public projection never carries the number',
    !in_array('+491701234567', array_map('strval', $waPublic), true)
    && !isset($waPublic['contact_whatsapp']));

/* ------------------------------------------------------- Wi-Fi codes ---- */

echo PHP_EOL . 'Guest Wi-Fi code' . PHP_EOL;

check('a WPA code is built',
    dat_wifi_qr_payload('Cafe-Guest', 'Sonnenaufgang-2026') === 'WIFI:T:WPA;S:Cafe-Guest;P:Sonnenaufgang-2026;H:false;;',
    (string) dat_wifi_qr_payload('Cafe-Guest', 'Sonnenaufgang-2026'));
check('special characters are escaped',
    dat_wifi_qr_payload('My;Net,work', 'a:b"c') === 'WIFI:T:WPA;S:My\;Net\,work;P:a\:b\"c;H:false;;',
    (string) dat_wifi_qr_payload('My;Net,work', 'a:b"c'));
check('an open network needs no password',
    dat_wifi_qr_payload('Cafe-Guest', '', 'NOPASS') === 'WIFI:T:nopass;S:Cafe-Guest;H:false;;');
check('a network without a name is rejected', dat_wifi_qr_payload('  ', 'secret') === null);
check('a protected network without a password is rejected', dat_wifi_qr_payload('Cafe-Guest', '') === null);

$demoIds = array_column($catalog, 'public_id');
check('demo public IDs are unique', count(array_unique($demoIds)) === count($demoIds));
check('demo public IDs are valid', count(array_filter($demoIds, 'dat_is_valid_public_id')) === count($demoIds), implode(',', $demoIds));

$leaks = [];
foreach ($catalog as $entry) {
    $public = dat_public_asset(dat_demo_asset_row($entry));
    $split = dat_demo_field_split($entry);
    check('example publishes something: ' . $entry['key'], $split['public'] !== []);
    foreach ($split['private'] as $value) {
        if (in_array($value, $public['metadata'], true)) {
            $leaks[] = $entry['key'] . ':' . $value;
        }
    }
}
check('no private example value reaches the public projection', $leaks === [], implode(', ', $leaks));

/* ------------------------------------------------------ static files --- */

echo PHP_EOL . 'Static files' . PHP_EOL;

foreach ([
    'assets/css/portal.css',
    'assets/js/portal.js',
    'assets/img/favicon.svg',
    'config/schema.sql',
    'index.php',
    'how-it-works.php',
    'use-cases.php',
    'privacy.php',
    'start.php',
    'write-a-tag.php',
] as $file) {
    check('exists: ' . $file, is_file(DAT_APP_ROOT . '/' . $file));
}

/* -------------------------------------------------- translation pairs -- */

echo PHP_EOL . 'Translations' . PHP_EOL;

$en = (array) require DAT_APP_ROOT . '/includes/lang/en.php';
$de = (array) require DAT_APP_ROOT . '/includes/lang/de.php';
$missingInDe = array_diff(array_keys($en), array_keys($de));
$missingInEn = array_diff(array_keys($de), array_keys($en));

check('German covers every English key', $missingInDe === [], implode(', ', array_slice($missingInDe, 0, 8)));
check('German adds no unknown keys', $missingInEn === [], implode(', ', array_slice($missingInEn, 0, 8)));
check('no empty translations', array_filter($de, static fn ($value) => trim((string) $value) === '') === []);

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

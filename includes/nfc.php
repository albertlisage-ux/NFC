<?php
/**
 * NFC payload generation.
 *
 * The payload stays deliberately short: a few lines a person can read without
 * a phone, plus the stable public URL. No owner contact data ever goes on the
 * tag, because anyone can read an NFC chip.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/assets.php';

/**
 * Build the NFC text for an asset row.
 *
 *   Lucky
 *   Pet / Dog
 *   Golden Retriever, Golden
 *
 *   https://example.com/t/XXXXXXXX
 */
if (!function_exists('dat_nfc_payload')) {
    function dat_nfc_payload(array $asset, $publicUrl = null)
    {
        $url = $publicUrl ?: dat_tag_url($asset['public_id'] ?? '');

        $lines = [];
        $name = trim((string) ($asset['name'] ?? ''));
        if ($name !== '') {
            $lines[] = $name;
        }

        $typeLabel = dat_asset_type_label($asset['type'] ?? 0);
        $species = dat_asset_metadata($asset)['animal'] ?? '';
        $lines[] = $species !== '' ? $typeLabel . ' / ' . $species : $typeLabel;

        $traits = [];
        foreach (dat_public_metadata($asset) as $label => $value) {
            // The animal is already part of the second line.
            if (mb_strtolower($label) === mb_strtolower(t('field.animal', 'Animal'))) {
                continue;
            }
            $traits[] = $value;
        }
        if ($traits) {
            $lines[] = implode(', ', array_slice($traits, 0, 3));
        }

        return trim(implode("\n", $lines)) . "\n\n" . $url;
    }
}

/** Byte length of a payload, used to warn owners about small NFC chips. */
if (!function_exists('dat_nfc_payload_bytes')) {
    function dat_nfc_payload_bytes($payload)
    {
        return strlen((string) $payload);
    }
}

/** Simple guidance for common NTAG chips. */
if (!function_exists('dat_nfc_capacity_hint')) {
    function dat_nfc_capacity_hint($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes <= 137) {
            return 'NTAG213';
        }
        if ($bytes <= 480) {
            return 'NTAG215';
        }
        if ($bytes <= 872) {
            return 'NTAG216';
        }
        return null;
    }
}

/**
 * Guest Wi-Fi code in the format phones understand when the camera scans it:
 *
 *   WIFI:T:WPA;S:Guest-Network;P:secret;;
 *
 * Used on menu boards and posters, where guests scan to join the network. The
 * password never appears on the public tag page, only on the printable sheet
 * the owner downloads from the dashboard.
 */
if (!function_exists('dat_wifi_qr_payload')) {
    function dat_wifi_qr_payload($ssid, $password = null, $encryption = 'WPA', $hidden = false)
    {
        $esc = static function ($value) {
            // The spec requires escaping \ ; , : and "
            return str_replace(
                ['\\', ';', ',', ':', '"'],
                ['\\\\', '\\;', '\\,', '\\:', '\\"'],
                (string) $value
            );
        };

        $ssid = trim((string) $ssid);
        if ($ssid === '') {
            return null;
        }

        $encryption = strtoupper((string) $encryption);
        if (!in_array($encryption, ['WPA', 'WEP', 'NOPASS'], true)) {
            $encryption = 'WPA';
        }
        if ($encryption === 'NOPASS') {
            return 'WIFI:T:nopass;S:' . $esc($ssid) . ';H:' . ($hidden ? 'true' : 'false') . ';;';
        }

        $password = (string) $password;
        if ($password === '') {
            return null;
        }

        return 'WIFI:T:' . $encryption
            . ';S:' . $esc($ssid)
            . ';P:' . $esc($password)
            . ';H:' . ($hidden ? 'true' : 'false')
            . ';;';
    }
}

/** True when an asset has enough detail to build a guest Wi-Fi code. */
if (!function_exists('dat_asset_wifi_payload')) {
    function dat_asset_wifi_payload(array $asset)
    {
        $metadata = dat_asset_metadata($asset);
        return dat_wifi_qr_payload($metadata['wifi_network'] ?? '', $metadata['wifi_password'] ?? '');
    }
}

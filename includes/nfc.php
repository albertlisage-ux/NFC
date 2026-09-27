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

<?php
/**
 * Asset domain layer.
 *
 * An Asset is the single core abstraction of the portal: one digital identity
 * that can carry several physical tags (QR, NFC, later RFID). Everything else
 * in the portal is an extension of this idea.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';

/* -------------------------------------------------------------------------
 * Enumerations (kept in sync with config/schema.sql)
 * ---------------------------------------------------------------------- */

/*
 * Type ids are permanent: an asset keeps the id it was created with, so a
 * printed tag never changes meaning. Ids 2, 3, 5 and 6 belonged to the first
 * catalogue (bicycle, vehicle, item, industrial) and are retired.
 */
const DAT_ASSET_TYPE_PET        = 1;
const DAT_ASSET_TYPE_CLOTHING   = 4;
const DAT_ASSET_TYPE_MENU_BOARD = 7;
const DAT_ASSET_TYPE_POSTER     = 8;
const DAT_ASSET_TYPE_MINI_TAG   = 9;
const DAT_ASSET_TYPE_LANYARD    = 10;
const DAT_ASSET_TYPE_WRISTBAND  = 11;
const DAT_ASSET_TYPE_KEYCHAIN   = 12;
const DAT_ASSET_TYPE_NECKLACE   = 13;

const DAT_ASSET_STATUS_ACTIVE   = 1;
const DAT_ASSET_STATUS_LOST     = 2;
const DAT_ASSET_STATUS_FOUND    = 3;
const DAT_ASSET_STATUS_INACTIVE = 4;
const DAT_ASSET_STATUS_DELETED  = 5;

const DAT_TAG_TYPE_QR   = 1;
const DAT_TAG_TYPE_NFC  = 2;
const DAT_TAG_TYPE_RFID = 3;

const DAT_TAG_STATUS_ACTIVE   = 1;
const DAT_TAG_STATUS_DISABLED = 2;
const DAT_TAG_STATUS_REPLACED = 3;

/**
 * Asset types with the public metadata fields that belong to each one.
 *
 * "public" => false fields are stored for the owner but never rendered on the
 * public tag page (see the privacy rules in the architecture document).
 */
if (!function_exists('dat_asset_types')) {
    function dat_asset_types()
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        // Field visibility drives both the owner forms and what a tag page may
        // render, so "public" => false values never reach a public view.
        $types = [
            DAT_ASSET_TYPE_MENU_BOARD => [
                'key' => 'menu_board',
                'category' => 'signage',
                'icon' => 'fa-solid fa-rectangle-list',
                'emoji' => "\u{1F4CB}",
                'label' => t('type.menu_board', 'Menu board'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'size', 'label' => t('field.size', 'Size'), 'public' => true],
                    ['key' => 'location', 'label' => t('field.location', 'Placement'), 'public' => true],
                    ['key' => 'menu_url', 'label' => t('field.menu_url', 'Digital menu'), 'public' => true],
                    ['key' => 'wifi_network', 'label' => t('field.wifi_network', 'Guest Wi-Fi name'), 'public' => true],
                    ['key' => 'wifi_password', 'label' => t('field.wifi_password', 'Wi-Fi password'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_POSTER => [
                'key' => 'poster',
                'category' => 'signage',
                'icon' => 'fa-solid fa-image',
                'emoji' => "\u{1F5BC}",
                'label' => t('type.poster', 'NFC poster'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'size', 'label' => t('field.size', 'Size'), 'public' => true],
                    ['key' => 'location', 'label' => t('field.location', 'Placement'), 'public' => true],
                    ['key' => 'campaign_id', 'label' => t('field.campaign_id', 'Campaign ID'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_WRISTBAND => [
                'key' => 'wristband',
                'category' => 'wearables',
                'icon' => 'fa-solid fa-clock',
                'emoji' => "\u{1F39F}",
                'label' => t('type.wristband', 'Wristband'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'closure', 'label' => t('field.closure', 'Closure'), 'public' => true],
                    ['key' => 'batch', 'label' => t('field.batch', 'Production batch'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_NECKLACE => [
                'key' => 'necklace',
                'category' => 'wearables',
                'icon' => 'fa-solid fa-gem',
                'emoji' => "\u{1F4FF}",
                'label' => t('type.necklace', 'Necklace'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'pendant', 'label' => t('field.pendant', 'Pendant shape'), 'public' => true],
                    ['key' => 'chain_length', 'label' => t('field.chain_length', 'Chain length'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'batch', 'label' => t('field.batch', 'Production batch'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_LANYARD => [
                'key' => 'lanyard',
                'category' => 'wearables',
                'icon' => 'fa-solid fa-id-badge',
                'emoji' => "\u{1F3F7}",
                'label' => t('type.lanyard', 'Lanyard'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'width', 'label' => t('field.width', 'Width'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'fitting', 'label' => t('field.fitting', 'Fitting'), 'public' => true],
                    ['key' => 'batch', 'label' => t('field.batch', 'Production batch'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_KEYCHAIN => [
                'key' => 'keychain',
                'category' => 'carry',
                'icon' => 'fa-solid fa-key',
                'emoji' => "\u{1F511}",
                'label' => t('type.keychain', 'Keychain'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'shape', 'label' => t('field.shape', 'Shape'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'batch', 'label' => t('field.batch', 'Production batch'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_MINI_TAG => [
                'key' => 'mini_tag',
                'category' => 'carry',
                'icon' => 'fa-solid fa-certificate',
                'emoji' => "\u{1F3F7}",
                'label' => t('type.mini_tag', 'Mini tag'),
                'fields' => [
                    ['key' => 'material', 'label' => t('field.material', 'Material'), 'public' => true],
                    ['key' => 'size', 'label' => t('field.size', 'Size'), 'public' => true],
                    ['key' => 'attachment', 'label' => t('field.attachment', 'Attachment'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'batch', 'label' => t('field.batch', 'Production batch'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_PET => [
                'key' => 'pet',
                'category' => 'assets',
                'icon' => 'fa-solid fa-dog',
                'emoji' => "\u{1F415}",
                'label' => t('type.pet', 'Pet'),
                'fields' => [
                    ['key' => 'animal', 'label' => t('field.animal', 'Animal'), 'public' => true],
                    ['key' => 'breed', 'label' => t('field.breed', 'Breed'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                    ['key' => 'gender', 'label' => t('field.gender', 'Gender'), 'public' => true],
                    ['key' => 'microchip', 'label' => t('field.microchip', 'Microchip number'), 'public' => false],
                ],
            ],
            DAT_ASSET_TYPE_CLOTHING => [
                'key' => 'clothing',
                'category' => 'assets',
                'icon' => 'fa-solid fa-shirt',
                'emoji' => "\u{1F455}",
                'label' => t('type.clothing', 'Clothing'),
                'fields' => [
                    ['key' => 'brand', 'label' => t('field.brand', 'Brand'), 'public' => true],
                    ['key' => 'model', 'label' => t('field.model', 'Model'), 'public' => true],
                    ['key' => 'size', 'label' => t('field.size', 'Size'), 'public' => true],
                    ['key' => 'color', 'label' => t('field.color', 'Colour'), 'public' => true],
                ],
            ],
        ];

        return $types;
    }
}

if (!function_exists('dat_asset_type_ids')) {
    function dat_asset_type_ids()
    {
        return array_keys(dat_asset_types());
    }
}

if (!function_exists('dat_asset_type_meta')) {
    function dat_asset_type_meta($type)
    {
        $types = dat_asset_types();
        $type = (int) $type;
        return $types[$type] ?? [
            'key' => 'other',
            'category' => 'assets',
            'icon' => 'fa-solid fa-box',
            'emoji' => "\u{1F4E6}",
            'label' => t('type.other', 'Other'),
            'fields' => [],
        ];
    }
}

/**
 * The catalogue is grouped so the interface can present families of products
 * instead of one long list: signage, wearables, carry items, and the assets
 * people tag directly.
 *
 * @return array<string, array{key: string, label: string, hint: string, types: int[]}>
 */
if (!function_exists('dat_asset_type_categories')) {
    function dat_asset_type_categories()
    {
        return [
            'signage' => [
                'key' => 'signage',
                'label' => t('category.signage', 'Signage and display'),
                'hint' => t('category.signage.hint', 'Boards and posters that carry the code where people stand in front of them.'),
                'types' => [DAT_ASSET_TYPE_MENU_BOARD, DAT_ASSET_TYPE_POSTER],
            ],
            'wearables' => [
                'key' => 'wearables',
                'label' => t('category.wearables', 'Worn on the body'),
                'hint' => t('category.wearables.hint', 'Chips on the wrist, around the neck or on a lanyard.'),
                'types' => [DAT_ASSET_TYPE_WRISTBAND, DAT_ASSET_TYPE_NECKLACE, DAT_ASSET_TYPE_LANYARD],
            ],
            'carry' => [
                'key' => 'carry',
                'label' => t('category.carry', 'Carried and attached'),
                'hint' => t('category.carry.hint', 'Keychains and small tags that live on keys, bags and equipment.'),
                'types' => [DAT_ASSET_TYPE_KEYCHAIN, DAT_ASSET_TYPE_MINI_TAG],
            ],
            'assets' => [
                'key' => 'assets',
                'label' => t('category.assets', 'Assets you tag directly'),
                'hint' => t('category.assets.hint', 'Living things and clothing, tagged with the details that identify them.'),
                'types' => [DAT_ASSET_TYPE_PET, DAT_ASSET_TYPE_CLOTHING],
            ],
        ];
    }
}

/** Asset types in catalogue order (category by category). */
if (!function_exists('dat_asset_types_ordered')) {
    function dat_asset_types_ordered()
    {
        $types = dat_asset_types();
        $ordered = [];

        foreach (dat_asset_type_categories() as $category) {
            foreach ($category['types'] as $typeId) {
                if (isset($types[$typeId])) {
                    $ordered[$typeId] = $types[$typeId];
                }
            }
        }

        return $ordered ?: $types;
    }
}

if (!function_exists('dat_asset_type_category')) {
    function dat_asset_type_category($type)
    {
        $meta = dat_asset_type_meta($type);
        $categories = dat_asset_type_categories();
        $key = $meta['category'] ?? 'assets';
        return $categories[$key] ?? $categories['assets'];
    }
}

if (!function_exists('dat_asset_type_label')) {
    function dat_asset_type_label($type)
    {
        return dat_asset_type_meta($type)['label'];
    }
}

if (!function_exists('dat_metadata_fields')) {
    function dat_metadata_fields($type)
    {
        return dat_asset_type_meta($type)['fields'];
    }
}

if (!function_exists('dat_asset_statuses')) {
    function dat_asset_statuses()
    {
        return [
            DAT_ASSET_STATUS_ACTIVE => [
                'key' => 'active',
                'label' => t('status.active', 'Active'),
                'hint' => t('status.active.hint', 'Everything is fine. The tag shows your asset details.'),
                'tone' => 'ok',
                'icon' => 'fa-solid fa-circle-check',
            ],
            DAT_ASSET_STATUS_LOST => [
                'key' => 'lost',
                'label' => t('status.lost', 'Lost'),
                'hint' => t('status.lost.hint', 'The public page asks the finder to contact you.'),
                'tone' => 'warn',
                'icon' => 'fa-solid fa-triangle-exclamation',
            ],
            DAT_ASSET_STATUS_FOUND => [
                'key' => 'found',
                'label' => t('status.found', 'Found'),
                'hint' => t('status.found.hint', 'Someone reported this asset as found.'),
                'tone' => 'info',
                'icon' => 'fa-solid fa-hand-holding-heart',
            ],
            DAT_ASSET_STATUS_INACTIVE => [
                'key' => 'inactive',
                'label' => t('status.inactive', 'Inactive'),
                'hint' => t('status.inactive.hint', 'The public page is hidden from finders.'),
                'tone' => 'muted',
                'icon' => 'fa-solid fa-circle-pause',
            ],
            DAT_ASSET_STATUS_DELETED => [
                'key' => 'deleted',
                'label' => t('status.deleted', 'Deleted'),
                'hint' => t('status.deleted.hint', 'The public page only states that the tag is no longer active.'),
                'tone' => 'muted',
                'icon' => 'fa-solid fa-trash-can',
            ],
        ];
    }
}

if (!function_exists('dat_asset_status_meta')) {
    function dat_asset_status_meta($status)
    {
        $statuses = dat_asset_statuses();
        $status = (int) $status;
        return $statuses[$status] ?? $statuses[DAT_ASSET_STATUS_ACTIVE];
    }
}

/** Statuses an owner may pick in the dashboard. */
if (!function_exists('dat_owner_selectable_statuses')) {
    function dat_owner_selectable_statuses()
    {
        return [
            DAT_ASSET_STATUS_ACTIVE,
            DAT_ASSET_STATUS_LOST,
            DAT_ASSET_STATUS_FOUND,
            DAT_ASSET_STATUS_INACTIVE,
        ];
    }
}

if (!function_exists('dat_tag_types')) {
    function dat_tag_types()
    {
        return [
            DAT_TAG_TYPE_QR => ['key' => 'qr', 'label' => t('tag.qr', 'QR code'), 'icon' => 'fa-solid fa-qrcode'],
            DAT_TAG_TYPE_NFC => ['key' => 'nfc', 'label' => t('tag.nfc', 'NFC tag'), 'icon' => 'fa-solid fa-wifi'],
            DAT_TAG_TYPE_RFID => ['key' => 'rfid', 'label' => t('tag.rfid', 'RFID tag'), 'icon' => 'fa-solid fa-tower-broadcast'],
        ];
    }
}

if (!function_exists('dat_tag_type_meta')) {
    function dat_tag_type_meta($type)
    {
        $types = dat_tag_types();
        $type = (int) $type;
        return $types[$type] ?? $types[DAT_TAG_TYPE_QR];
    }
}

if (!function_exists('dat_tag_status_label')) {
    function dat_tag_status_label($status)
    {
        switch ((int) $status) {
            case DAT_TAG_STATUS_DISABLED:
                return t('tag.status.disabled', 'Disabled');
            case DAT_TAG_STATUS_REPLACED:
                return t('tag.status.replaced', 'Replaced');
            default:
                return t('tag.status.active', 'Active');
        }
    }
}

/* -------------------------------------------------------------------------
 * Public ID
 * ---------------------------------------------------------------------- */

/**
 * Normalise an optional WhatsApp number.
 *
 * Returns null when the value cannot be a phone number, so a typo silently
 * disables the WhatsApp button instead of producing a broken link.
 */
if (!function_exists('dat_normalize_whatsapp')) {
    function dat_normalize_whatsapp($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $hasPlus = strpos($raw, '+') === 0;
        $digits = preg_replace('/\D+/', '', $raw);
        if (!is_string($digits) || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return ($hasPlus ? '+' : '') . $digits;
    }
}

/**
 * Deep link that opens a WhatsApp chat with a prefilled message.
 *
 * The number is never printed on the tag page. Whoever taps the button does
 * see it inside WhatsApp, so the button is opt-in per asset.
 */
if (!function_exists('dat_whatsapp_url')) {
    function dat_whatsapp_url(array $asset, $message = '')
    {
        $number = dat_normalize_whatsapp($asset['contact_whatsapp'] ?? '');
        if ($number === null) {
            return null;
        }

        $url = 'https://wa.me/' . ltrim($number, '+');
        $message = trim((string) $message);
        if ($message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }
}

/** Ready-made message a finder can send without typing anything. */
if (!function_exists('dat_whatsapp_message')) {
    function dat_whatsapp_message(array $asset)
    {
        return sprintf(
            t('whatsapp.message', 'Hello, I scanned the tag of %1$s (%2$s). Here is what I found:'),
            $asset['name'] ?? '',
            $asset['public_id'] ?? ''
        );
    }
}

/**
 * What a chip or printed code can carry.
 *
 * "portal" is the default and the recommended one: the tag answers with the
 * asset page, where the finder also gets the anonymous message and the photos.
 * "whatsapp" points the chip straight at a chat, for owners who only want to
 * be reachable on WhatsApp and do not need the page.
 */
if (!function_exists('dat_tag_targets')) {
    function dat_tag_targets()
    {
        return [
            'portal' => [
                'key' => 'portal',
                'label' => t('target.portal', 'The tag page (recommended)'),
                'hint' => t('target.portal.hint', 'The chip opens the page with the details, the photos and the anonymous message.'),
                'icon' => 'fa-solid fa-tag',
            ],
            'whatsapp' => [
                'key' => 'whatsapp',
                'label' => t('target.whatsapp', 'A WhatsApp chat'),
                'hint' => t('target.whatsapp.hint', 'The chip opens WhatsApp with your number, ready to write. Needs a WhatsApp number and loses the page.'),
                'icon' => 'fa-brands fa-whatsapp',
            ],
        ];
    }
}

if (!function_exists('dat_normalize_tag_target')) {
    function dat_normalize_tag_target($raw)
    {
        $raw = strtolower(trim((string) $raw));
        return array_key_exists($raw, dat_tag_targets()) ? $raw : 'portal';
    }
}

/**
 * Address the physical tag carries. Falls back to the tag page whenever the
 * selected mode cannot produce a usable link.
 */
if (!function_exists('dat_tag_target_url')) {
    function dat_tag_target_url(array $asset, $forNfc = false)
    {
        $mode = dat_normalize_tag_target($asset['tag_target'] ?? 'portal');

        if ($mode === 'whatsapp') {
            // NFC payloads stay short, so the chip carries the plain chat link
            // and the phone opens WhatsApp without a prefilled paragraph.
            $message = $forNfc ? '' : dat_whatsapp_message($asset);
            $url = dat_whatsapp_url($asset, $message);
            if ($url !== null) {
                return $url;
            }
        }

        return dat_tag_url($asset['public_id'] ?? '');
    }
}

/**
 * Unambiguous alphabet: no 0/O and no 1/I, so a printed ID can be typed back
 * in without guessing. 32^8 combinations keep IDs unguessable.
 */
if (!function_exists('dat_public_id_alphabet')) {
    function dat_public_id_alphabet()
    {
        return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    }
}

if (!function_exists('dat_generate_public_id')) {
    function dat_generate_public_id($length = 8)
    {
        $alphabet = dat_public_id_alphabet();
        $max = strlen($alphabet) - 1;
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, $max)];
        }
        return $id;
    }
}

/** Generate a public ID that is not used yet. */
if (!function_exists('dat_unique_public_id')) {
    function dat_unique_public_id($attempts = 12)
    {
        for ($i = 0; $i < $attempts; $i++) {
            $candidate = dat_generate_public_id();
            $exists = dat_one(
                'SELECT id FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1',
                [$candidate]
            );
            if ($exists === null) {
                return $candidate;
            }
        }

        // Fallback: longer ID, collision probability is negligible.
        return dat_generate_public_id(10);
    }
}

if (!function_exists('dat_is_valid_public_id')) {
    function dat_is_valid_public_id($publicId)
    {
        return is_string($publicId)
            && preg_match('/^[' . dat_public_id_alphabet() . ']{4,12}$/', $publicId) === 1;
    }
}

/* -------------------------------------------------------------------------
 * Metadata helpers
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_asset_metadata')) {
    function dat_asset_metadata(array $asset)
    {
        $raw = $asset['metadata_json'] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/** Keep only the fields that belong to the asset type, trimmed and length-capped. */
if (!function_exists('dat_sanitize_metadata')) {
    function dat_sanitize_metadata($type, $input, $onlyPublic = false)
    {
        $input = is_array($input) ? $input : [];
        $clean = [];

        foreach (dat_metadata_fields($type) as $field) {
            if ($onlyPublic && empty($field['public'])) {
                continue;
            }
            $value = $input[$field['key']] ?? '';
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value === '') {
                continue;
            }
            $clean[$field['key']] = mb_substr($value, 0, 120);
        }

        return $clean;
    }
}

/**
 * Metadata safe for the public tag page: only fields flagged public.
 */
if (!function_exists('dat_public_metadata')) {
    function dat_public_metadata(array $asset)
    {
        $metadata = dat_asset_metadata($asset);
        $public = [];
        foreach (dat_metadata_fields($asset['type'] ?? 0) as $field) {
            if (!empty($field['public']) && isset($metadata[$field['key']]) && $metadata[$field['key']] !== '') {
                $public[$field['label']] = $metadata[$field['key']];
            }
        }
        return $public;
    }
}

/** Short one-line subtitle for cards, e.g. "Dog / Golden Retriever". */
if (!function_exists('dat_asset_summary')) {
    function dat_asset_summary(array $asset)
    {
        $values = array_values(dat_public_metadata($asset));
        if (!$values) {
            return dat_asset_type_label($asset['type'] ?? 0);
        }
        return implode(' / ', array_slice($values, 0, 2));
    }
}

/* -------------------------------------------------------------------------
 * Reads
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_asset_by_public_id')) {
    function dat_asset_by_public_id($publicId)
    {
        if (!dat_is_valid_public_id($publicId)) {
            return null;
        }

        return dat_one(
            'SELECT * FROM ' . dat_table('assets') . ' WHERE public_id = ? LIMIT 1',
            [$publicId]
        );
    }
}

if (!function_exists('dat_asset_for_owner')) {
    function dat_asset_for_owner($assetId, $ownerId)
    {
        if (!is_string($assetId) || $assetId === '') {
            return null;
        }

        return dat_one(
            'SELECT * FROM ' . dat_table('assets') . ' WHERE id = ? AND owner_id = ? LIMIT 1',
            [$assetId, $ownerId]
        );
    }
}

if (!function_exists('dat_assets_for_owner')) {
    function dat_assets_for_owner($ownerId)
    {
        return dat_all(
            'SELECT * FROM ' . dat_table('assets') . '
              WHERE owner_id = ? AND status <> ?
              ORDER BY updated_at DESC, created_at DESC',
            [$ownerId, DAT_ASSET_STATUS_DELETED]
        );
    }
}

if (!function_exists('dat_asset_counts')) {
    function dat_asset_counts($ownerId)
    {
        $row = dat_one(
            'SELECT
                COUNT(*) AS total,
                SUM(status = ?) AS active,
                SUM(status = ?) AS lost,
                SUM(status = ?) AS found
             FROM ' . dat_table('assets') . '
             WHERE owner_id = ? AND status <> ?',
            [DAT_ASSET_STATUS_ACTIVE, DAT_ASSET_STATUS_LOST, DAT_ASSET_STATUS_FOUND, $ownerId, DAT_ASSET_STATUS_DELETED]
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'lost' => (int) ($row['lost'] ?? 0),
            'found' => (int) ($row['found'] ?? 0),
        ];
    }
}

if (!function_exists('dat_asset_tags')) {
    function dat_asset_tags($assetId)
    {
        return dat_all(
            'SELECT * FROM ' . dat_table('tags') . ' WHERE asset_id = ? ORDER BY type ASC, created_at ASC',
            [$assetId]
        );
    }
}

if (!function_exists('dat_asset_images')) {
    function dat_asset_images($assetId)
    {
        return dat_all(
            'SELECT * FROM ' . dat_table('asset_images') . ' WHERE asset_id = ? ORDER BY position ASC, created_at ASC',
            [$assetId]
        );
    }
}

/* -------------------------------------------------------------------------
 * Writes
 * ---------------------------------------------------------------------- */

/**
 * Create an asset with its default QR and NFC tags.
 *
 * Returns the created row, or null when the database is unavailable.
 */
if (!function_exists('dat_create_asset')) {
    function dat_create_asset($ownerId, $type, $name, $description = '', array $metadata = [], $status = DAT_ASSET_STATUS_ACTIVE, $contactWhatsapp = null)
    {
        $db = dat_db();
        if ($db === null) {
            return null;
        }

        $type = in_array((int) $type, dat_asset_type_ids(), true) ? (int) $type : DAT_ASSET_TYPE_ITEM;
        $name = mb_substr(trim((string) $name), 0, 120);
        if ($name === '') {
            return null;
        }

        $metadata = dat_sanitize_metadata($type, $metadata);
        $contactWhatsapp = dat_normalize_whatsapp($contactWhatsapp);
        $assetId = dat_uuid();
        $publicId = dat_unique_public_id();
        $now = dat_now();

        try {
            $db->beginTransaction();

            dat_query(
                'INSERT INTO ' . dat_table('assets') . '
                    (id, public_id, owner_id, type, name, description, status, metadata_json, contact_whatsapp, tag_target, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $assetId,
                    $publicId,
                    $ownerId,
                    $type,
                    $name,
                    mb_substr(trim((string) $description), 0, 2000),
                    (int) $status,
                    $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    $contactWhatsapp,
                    'portal',
                    $now,
                    $now,
                ]
            );

            $asset = dat_asset_for_owner($assetId, $ownerId);
            if ($asset === null) {
                throw new RuntimeException('Asset insert could not be read back');
            }

            dat_create_tag($assetId, DAT_TAG_TYPE_QR, 'Default QR tag');
            dat_create_tag($assetId, DAT_TAG_TYPE_NFC, 'Default NFC tag');

            $db->commit();
            return $asset;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            dat_log_error('Asset creation failed: ' . $e->getMessage(), ['type' => $type]);
            return null;
        }
    }
}

if (!function_exists('dat_update_asset')) {
    function dat_update_asset($assetId, $ownerId, array $data)
    {
        $asset = dat_asset_for_owner($assetId, $ownerId);
        if ($asset === null) {
            return false;
        }

        $name = mb_substr(trim((string) ($data['name'] ?? $asset['name'])), 0, 120);
        if ($name === '') {
            return false;
        }

        $status = (int) ($data['status'] ?? $asset['status']);
        if (!in_array($status, dat_owner_selectable_statuses(), true)) {
            $status = (int) $asset['status'];
        }

        $metadata = isset($data['metadata'])
            ? dat_sanitize_metadata($asset['type'], $data['metadata'])
            : dat_asset_metadata($asset);

        // Preserve private values that were not part of the submitted form.
        if (isset($data['metadata'])) {
            $existing = dat_asset_metadata($asset);
            foreach (dat_metadata_fields($asset['type']) as $field) {
                $key = $field['key'];
                if (!array_key_exists($key, $data['metadata']) && isset($existing[$key]) && empty($field['public'])) {
                    $metadata[$key] = $existing[$key];
                }
            }
        }

        // An empty submitted value clears the number, an absent key keeps it.
        $contactWhatsapp = array_key_exists('contact_whatsapp', $data)
            ? dat_normalize_whatsapp($data['contact_whatsapp'])
            : ($asset['contact_whatsapp'] ?? null);

        $tagTarget = array_key_exists('tag_target', $data)
            ? dat_normalize_tag_target($data['tag_target'])
            : dat_normalize_tag_target($asset['tag_target'] ?? 'portal');

        $affected = dat_exec(
            'UPDATE ' . dat_table('assets') . '
                SET name = ?, description = ?, status = ?, metadata_json = ?, contact_whatsapp = ?, tag_target = ?, updated_at = ?
              WHERE id = ? AND owner_id = ?',
            [
                $name,
                mb_substr(trim((string) ($data['description'] ?? $asset['description'])), 0, 2000),
                $status,
                $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $contactWhatsapp,
                $tagTarget,
                dat_now(),
                $assetId,
                $ownerId,
            ]
        );

        if ($affected >= 0) {
            dat_sync_default_tags($assetId, $ownerId);
        }

        return $affected >= 0;
    }
}

if (!function_exists('dat_set_asset_status')) {
    function dat_set_asset_status($assetId, $ownerId, $status)
    {
        if (!in_array((int) $status, dat_owner_selectable_statuses(), true)) {
            return false;
        }

        return dat_exec(
            'UPDATE ' . dat_table('assets') . ' SET status = ?, updated_at = ? WHERE id = ? AND owner_id = ?',
            [(int) $status, dat_now(), $assetId, $ownerId]
        ) >= 0;
    }
}

/** Soft delete: the tag URL keeps answering instead of turning into a 404. */
if (!function_exists('dat_soft_delete_asset')) {
    function dat_soft_delete_asset($assetId, $ownerId)
    {
        return dat_exec(
            'UPDATE ' . dat_table('assets') . '
                SET status = ?, deleted_at = ?, updated_at = ?
              WHERE id = ? AND owner_id = ?',
            [DAT_ASSET_STATUS_DELETED, dat_now(), dat_now(), $assetId, $ownerId]
        ) >= 0;
    }
}

/* -------------------------------------------------------------------------
 * Tags
 * ---------------------------------------------------------------------- */

if (!function_exists('dat_create_tag')) {
    function dat_create_tag($assetId, $type, $label = null, $status = DAT_TAG_STATUS_ACTIVE)
    {
        $asset = dat_one('SELECT * FROM ' . dat_table('assets') . ' WHERE id = ? LIMIT 1', [$assetId]);
        if ($asset === null) {
            return null;
        }

        $type = (int) $type;
        if (!isset(dat_tag_types()[$type])) {
            $type = DAT_TAG_TYPE_QR;
        }

        // QR codes and chips can carry different targets: a chip in WhatsApp
        // mode holds the short chat link, the printed code keeps the page.
        $url = dat_tag_target_url($asset, false);
        $tagId = dat_uuid();

        $payload = null;
        if ($type === DAT_TAG_TYPE_NFC) {
            require_once __DIR__ . '/nfc.php';
            $payload = dat_nfc_payload($asset, dat_tag_target_url($asset, true));
        }

        dat_query(
            'INSERT INTO ' . dat_table('tags') . '
                (id, asset_id, type, label, nfc_payload, url, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$tagId, $assetId, $type, $label, $payload, $url, (int) $status, dat_now()]
        );

        return dat_one('SELECT * FROM ' . dat_table('tags') . ' WHERE id = ? LIMIT 1', [$tagId]);
    }
}

/** Refresh stored URLs and NFC payloads after the asset changed. */
if (!function_exists('dat_sync_default_tags')) {
    function dat_sync_default_tags($assetId, $ownerId)
    {
        $asset = dat_asset_for_owner($assetId, $ownerId);
        if ($asset === null) {
            return;
        }

        require_once __DIR__ . '/nfc.php';
        $url = dat_tag_target_url($asset, false);
        $payload = dat_nfc_payload($asset, dat_tag_target_url($asset, true));

        dat_exec(
            'UPDATE ' . dat_table('tags') . ' SET url = ? WHERE asset_id = ? AND status = ?',
            [$url, $assetId, DAT_TAG_STATUS_ACTIVE]
        );
        dat_exec(
            'UPDATE ' . dat_table('tags') . ' SET nfc_payload = ? WHERE asset_id = ? AND type = ? AND status = ?',
            [$payload, $assetId, DAT_TAG_TYPE_NFC, DAT_TAG_STATUS_ACTIVE]
        );
    }
}

if (!function_exists('dat_tag_for_owner')) {
    function dat_tag_for_owner($tagId, $ownerId)
    {
        return dat_one(
            'SELECT t.* FROM ' . dat_table('tags') . ' t
               JOIN ' . dat_table('assets') . ' a ON a.id = t.asset_id
              WHERE t.id = ? AND a.owner_id = ? LIMIT 1',
            [$tagId, $ownerId]
        );
    }
}

if (!function_exists('dat_disable_tag')) {
    function dat_disable_tag($tagId, $ownerId)
    {
        $tag = dat_tag_for_owner($tagId, $ownerId);
        if ($tag === null) {
            return false;
        }

        return dat_exec(
            'UPDATE ' . dat_table('tags') . ' SET status = ?, replaced_at = ? WHERE id = ?',
            [DAT_TAG_STATUS_DISABLED, dat_now(), $tagId]
        ) >= 0;
    }
}

/**
 * Replace a lost physical tag without changing the public identity: the old
 * tag is disabled and a new one points at the same Asset and PublicId.
 */
if (!function_exists('dat_replace_tag')) {
    function dat_replace_tag($tagId, $ownerId)
    {
        $tag = dat_tag_for_owner($tagId, $ownerId);
        if ($tag === null) {
            return null;
        }

        dat_exec(
            'UPDATE ' . dat_table('tags') . ' SET status = ?, replaced_at = ? WHERE id = ?',
            [DAT_TAG_STATUS_REPLACED, dat_now(), $tagId]
        );

        return dat_create_tag(
            $tag['asset_id'],
            (int) $tag['type'],
            ($tag['label'] ?: dat_tag_type_meta($tag['type'])['label']) . ' (replacement)'
        );
    }
}

/* -------------------------------------------------------------------------
 * Public projection
 * ---------------------------------------------------------------------- */

/**
 * Everything the public page is allowed to see.
 *
 * Building an explicit array (instead of handing the row to the view) keeps
 * owner id, internal id and private metadata out of the public output.
 */
if (!function_exists('dat_public_asset')) {
    function dat_public_asset(array $asset)
    {
        return [
            'public_id' => $asset['public_id'],
            'type' => (int) $asset['type'],
            'type_label' => dat_asset_type_label($asset['type']),
            'type_icon' => dat_asset_type_meta($asset['type'])['icon'],
            'type_key' => dat_asset_type_meta($asset['type'])['key'],
            'name' => $asset['name'],
            'description' => $asset['description'] ?? '',
            'status' => (int) $asset['status'],
            'status_key' => dat_asset_status_meta($asset['status'])['key'],
            'metadata' => dat_public_metadata($asset),
        ];
    }
}

/** True when the public page may render the asset itself. */
if (!function_exists('dat_asset_is_public')) {
    function dat_asset_is_public($status)
    {
        return in_array(
            (int) $status,
            [DAT_ASSET_STATUS_ACTIVE, DAT_ASSET_STATUS_LOST, DAT_ASSET_STATUS_FOUND],
            true
        );
    }
}

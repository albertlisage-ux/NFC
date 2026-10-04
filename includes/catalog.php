<?php
/**
 * Worked examples, one per catalogue item.
 *
 * The same catalog feeds four consumers, so the illustrations can never drift
 * away from reality:
 *   1. scripts/seed-demo.php creates these assets on the server, using the
 *      fixed public IDs below.
 *   2. use-cases.php renders each entry as a live preview, links to its real
 *      tag page, and derives the public/private lists from the same metadata
 *      the portal stores.
 *   3. tests/smoke.php checks that every demo ID is valid, that the catalog
 *      covers every configured asset type, and that no private value leaks.
 *   4. The `image` key names the optional product photo in
 *      assets/img/products/. When the file is missing the emoji mark is used,
 *      so real photos can be dropped in at any time.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/assets.php';

/**
 * @return array<string, array{
 *   key: string, public_id: string, type: int, name: string,
 *   description: string, metadata: array<string,string>, image: string
 * }>
 */
if (!function_exists('dat_demo_catalog')) {
    function dat_demo_catalog()
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = [
            'menu_board' => [
                'key' => 'menu_board',
                'public_id' => 'MENUTAG2',
                'type' => DAT_ASSET_TYPE_MENU_BOARD,
                'name' => t('case.menu_board.name', 'Café menu board'),
                'description' => t('case.menu_board.description', 'Acrylic board at the counter. The code opens the current menu, and the Wi-Fi card next to it is meant for guests in the room.'),
                'image' => 'menu-board.jpg',
                'metadata' => [
                    'material' => t('case.menu_board.material', 'Acrylic, 4 mm'),
                    'size' => 'A4, 210 × 297 mm',
                    'location' => t('case.menu_board.location', 'Counter, next to the till'),
                    'menu_url' => 'https://menu.example.com/cafe',
                    'wifi_network' => 'Cafe-Guest',
                    'wifi_password' => 'Sonnenaufgang-2026',
                ],
            ],
            'poster' => [
                'key' => 'poster',
                'public_id' => 'PSTTAG24',
                'type' => DAT_ASSET_TYPE_POSTER,
                'name' => t('case.poster.name', 'Shop window poster'),
                'description' => t('case.poster.description', 'Poster in the window, with the chip behind the paper so a tap opens the current offers.'),
                'image' => 'poster.jpg',
                'metadata' => [
                    'material' => t('case.poster.material', 'Paper on a PVC core'),
                    'size' => 'DIN A2, 420 × 594 mm',
                    'location' => t('case.poster.location', 'Shop window, left pane'),
                    'campaign_id' => 'SUMMER-26',
                ],
            ],
            'wristband' => [
                'key' => 'wristband',
                'public_id' => 'WRSTAG24',
                'type' => DAT_ASSET_TYPE_WRISTBAND,
                'name' => t('case.wristband.name', 'Festival wristband'),
                'description' => t('case.wristband.description', 'Silicone wristband for day guests. A tap at the gate shows the booking it belongs to.'),
                'image' => 'wristband.jpg',
                'metadata' => [
                    'material' => t('case.wristband.material', 'Silicone'),
                    'color' => t('case.wristband.color', 'Teal'),
                    'closure' => t('case.wristband.closure', 'Snap closure'),
                    'batch' => 'WB-2026-04',
                ],
            ],
            'necklace' => [
                'key' => 'necklace',
                'public_id' => 'NCKTAG24',
                'type' => DAT_ASSET_TYPE_NECKLACE,
                'name' => t('case.necklace.name', 'Pet pendant'),
                'description' => t('case.necklace.description', 'Small steel pendant for a collar or a chain, light enough for every day.'),
                'image' => 'necklace.jpg',
                'metadata' => [
                    'material' => t('case.necklace.material', 'Stainless steel'),
                    'pendant' => t('case.necklace.pendant', 'Round, 25 mm'),
                    'chain_length' => '50 cm',
                    'color' => t('case.necklace.color', 'Brushed steel'),
                    'batch' => 'NK-2026-01',
                ],
            ],
            'lanyard' => [
                'key' => 'lanyard',
                'public_id' => 'LNYTAG24',
                'type' => DAT_ASSET_TYPE_LANYARD,
                'name' => t('case.lanyard.name', 'Staff lanyard'),
                'description' => t('case.lanyard.description', 'Lanyard with a safety release, used for staff cards and visitor passes.'),
                'image' => 'lanyard.jpg',
                'metadata' => [
                    'material' => t('case.lanyard.material', 'Recycled polyester'),
                    'width' => '20 mm',
                    'color' => t('case.lanyard.color', 'Navy'),
                    'fitting' => t('case.lanyard.fitting', 'Breakaway clip'),
                    'batch' => 'LY-2026-02',
                ],
            ],
            'keychain' => [
                'key' => 'keychain',
                'public_id' => 'KEYTAG24',
                'type' => DAT_ASSET_TYPE_KEYCHAIN,
                'name' => t('case.keychain.name', 'Acrylic keychain'),
                'description' => t('case.keychain.description', 'Keychain with the code printed on the back and a ring that survives a keyring.'),
                'image' => 'keychain.jpg',
                'metadata' => [
                    'material' => t('case.keychain.material', 'Acrylic'),
                    'shape' => t('case.keychain.shape', 'Rounded rectangle, 45 × 70 mm'),
                    'color' => t('case.keychain.color', 'Clear with white print'),
                    'batch' => 'KC-2026-03',
                ],
            ],
            'mini_tag' => [
                'key' => 'mini_tag',
                'public_id' => 'TNYTAG24',
                'type' => DAT_ASSET_TYPE_MINI_TAG,
                'name' => t('case.mini_tag.name', 'Mini tag'),
                'description' => t('case.mini_tag.description', 'Fingernail-sized label for small things: a tool box, a cable drum, a bag zip.'),
                'image' => 'mini-tag.jpg',
                'metadata' => [
                    'material' => t('case.mini_tag.material', 'Laminated PET'),
                    'size' => '15 × 25 mm',
                    'attachment' => t('case.mini_tag.attachment', 'Adhesive or loop'),
                    'color' => t('case.mini_tag.color', 'Black print on white'),
                    'batch' => 'MT-2026-05',
                ],
            ],
            'pet' => [
                'key' => 'pet',
                'public_id' => 'DEMTAG24',
                'type' => DAT_ASSET_TYPE_PET,
                'name' => 'Lucky',
                'description' => t('case.pet.description', 'Golden Retriever, male, chipped. Friendly with children, shy in traffic.'),
                'image' => 'pet.jpg',
                'metadata' => [
                    'animal' => t('field.animal.value_dog', 'Dog'),
                    'breed' => 'Golden Retriever',
                    'color' => t('case.pet.colour', 'Golden'),
                    'gender' => t('case.pet.gender', 'Male'),
                    'microchip' => '276098104512377',
                ],
            ],
            'clothing' => [
                'key' => 'clothing',
                'public_id' => 'JAKTAG24',
                'type' => DAT_ASSET_TYPE_CLOTHING,
                'name' => t('case.clothing.name', 'Patagonia Down Jacket'),
                'description' => t('case.clothing.description', 'Club jacket with the badge on the chest. Used at training and on trips.'),
                'image' => 'clothing.jpg',
                'metadata' => [
                    'brand' => 'Patagonia',
                    'model' => 'Down Sweater',
                    'size' => 'M',
                    'color' => t('case.clothing.colour', 'Dark blue'),
                ],
            ],
        ];

        return $catalog;
    }
}

if (!function_exists('dat_demo_entry')) {
    function dat_demo_entry($key)
    {
        $catalog = dat_demo_catalog();
        return $catalog[$key] ?? null;
    }
}

/** The catalog entry as an asset row, ready for the public projection. */
if (!function_exists('dat_demo_asset_row')) {
    function dat_demo_asset_row(array $entry, $status = DAT_ASSET_STATUS_ACTIVE)
    {
        return [
            'public_id' => $entry['public_id'],
            'type' => $entry['type'],
            'name' => $entry['name'],
            'description' => $entry['description'],
            'status' => $status,
            'metadata_json' => json_encode($entry['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}

/**
 * Split an entry's metadata into the part a finder sees and the part that stays
 * in the portal, using the field definitions that actually drive rendering.
 *
 * @return array{public: array<string,string>, private: array<string,string>}
 */
if (!function_exists('dat_demo_field_split')) {
    function dat_demo_field_split(array $entry)
    {
        $public = [];
        $private = [];

        foreach (dat_metadata_fields($entry['type']) as $field) {
            $value = $entry['metadata'][$field['key']] ?? '';
            if ($value === '') {
                continue;
            }
            if (!empty($field['public'])) {
                $public[$field['label']] = $value;
            } else {
                $private[$field['label']] = $value;
            }
        }

        return ['public' => $public, 'private' => $private];
    }
}

/**
 * URL of an optional product photo, or null when the file is not there yet.
 * Drop photos into assets/img/products/ using the names from the catalog.
 */
if (!function_exists('dat_product_image_url')) {
    function dat_product_image_url($file, $size = null)
    {
        $file = trim((string) $file);
        if ($file === '' || strpos($file, '/') !== false || strpos($file, '..') !== false) {
            return null;
        }

        $path = DAT_APP_ROOT . '/assets/img/products/' . $file;
        if (!is_file($path) || !function_exists('dat_url')) {
            return null;
        }

        return dat_url('assets/img/products/' . $file) . '?v=' . filemtime($path);
    }
}

/**
 * URL of a generated page preview, or null when it has not been rendered yet.
 *
 * These are screenshots of this application's own pages, produced by
 * scripts/make-previews.mjs, which is what the tiles on the home page show.
 */
if (!function_exists('dat_preview_image')) {
    function dat_preview_image($name)
    {
        foreach (['jpg', 'png'] as $extension) {
            $file = trim((string) $name) . '.' . $extension;
            $path = DAT_APP_ROOT . '/assets/img/previews/' . $file;
            if (is_file($path) && function_exists('dat_url')) {
                return dat_url('assets/img/previews/' . $file) . '?v=' . filemtime($path);
            }
        }

        return null;
    }
}

/** The image a product tile should show: real photo, then page preview. */
if (!function_exists('dat_entry_image')) {
    function dat_entry_image(array $entry)
    {
        $photo = dat_product_image_url($entry['image'] ?? null);
        return $photo !== null ? $photo : dat_preview_image($entry['key']);
    }
}

/**
 * URL of a real photograph in assets/img/scenes/, or null when it is missing.
 *
 * These are the pictures next to the three steps on the home page: a phone,
 * a chip in a label and a code being scanned. Expect 1600 x 1000, 16:10.
 * Leaving the file out falls back to the icon, so the layout never breaks.
 */
if (!function_exists('dat_scene_image')) {
    function dat_scene_image($name)
    {
        foreach (['jpg', 'png'] as $extension) {
            $file = trim((string) $name) . '.' . $extension;
            $path = DAT_APP_ROOT . '/assets/img/scenes/' . $file;
            if (is_file($path) && function_exists('dat_url')) {
                return dat_url('assets/img/scenes/' . $file) . '?v=' . filemtime($path);
            }
        }

        return null;
    }
}

/**
 * URL of the animation that belongs to a scene photo, or null when it has not
 * been built. Produced by scripts/make-scene-animations.py.
 */
if (!function_exists('dat_scene_animation')) {
    function dat_scene_animation($name)
    {
        $file = trim((string) $name) . '.webp';
        $path = DAT_APP_ROOT . '/assets/img/scenes/' . $file;
        if (!is_file($path) || !function_exists('dat_url')) {
            return null;
        }

        return dat_url('assets/img/scenes/' . $file) . '?v=' . filemtime($path);
    }
}

/**
 * The whole picture for a scene: the animation when it exists, the still as
 * the fallback, and the still again for anyone who asked for less motion.
 * Animated WebP cannot be paused from CSS, so the choice is made in markup.
 *
 * Returns an empty string when there is no still, so the caller can fall back
 * to a plain icon.
 */
if (!function_exists('dat_scene_picture')) {
    function dat_scene_picture($name, $alt, $width = 1600, $height = 1000)
    {
        $still = dat_scene_image($name);
        if ($still === null) {
            return '';
        }

        $animation = dat_scene_animation($name);
        $html = '<picture>';
        $html .= '<source srcset="' . e($still) . '" type="image/jpeg" media="(prefers-reduced-motion: reduce)">';
        if ($animation !== null) {
            $html .= '<source srcset="' . e($animation) . '" type="image/webp">';
        }
        $html .= '<img src="' . e($still) . '" alt="' . e($alt) . '" loading="lazy"'
            . ' width="' . (int) $width . '" height="' . (int) $height . '">';
        $html .= '</picture>';

        return $html;
    }
}

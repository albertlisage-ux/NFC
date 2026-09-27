<?php
/**
 * Worked examples, one per asset type.
 *
 * The same catalog feeds three consumers, so the illustrations can never drift
 * away from reality:
 *   1. scripts/seed-demo.php creates these assets on the server, using the
 *      fixed public IDs below.
 *   2. use-cases.php renders each entry as a live preview, links to its real
 *      tag page, and derives the public/private lists from the same metadata
 *      the portal stores.
 *   3. tests/smoke.php checks that every demo ID is a valid public ID and that
 *      all six asset types are covered.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/assets.php';

/**
 * @return array<string, array{
 *   key: string, public_id: string, type: int, name: string,
 *   description: string, metadata: array<string,string>
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
            'pet' => [
                'key' => 'pet',
                'public_id' => 'DEMTAG24',
                'type' => DAT_ASSET_TYPE_PET,
                'name' => 'Lucky',
                'description' => t('case.pet.description', 'Golden Retriever, male, chipped. Friendly with children, shy in traffic.'),
                'metadata' => [
                    'animal' => t('field.animal.value_dog', 'Dog'),
                    'breed' => 'Golden Retriever',
                    'color' => t('case.pet.colour', 'Golden'),
                    'gender' => t('case.pet.gender', 'Male'),
                    'microchip' => '276098104512377',
                ],
            ],
            'bicycle' => [
                'key' => 'bicycle',
                'public_id' => 'RADTAG24',
                'type' => DAT_ASSET_TYPE_BICYCLE,
                'name' => 'Cube Reaction Hybrid',
                'description' => t('case.bicycle.description', 'E-bike for the daily commute. The battery lock needs the key, so it is never parked overnight outside.'),
                'metadata' => [
                    'brand' => 'Cube',
                    'model' => 'Reaction Hybrid',
                    'color' => t('case.bicycle.colour', 'Black'),
                    'frame_size' => 'L',
                    'serial' => 'CUB-2024-884213',
                ],
            ],
            'vehicle' => [
                'key' => 'vehicle',
                'public_id' => 'CARTAG24',
                'type' => DAT_ASSET_TYPE_VEHICLE,
                'name' => 'BMW 320i Touring',
                'description' => t('case.vehicle.description', 'Family estate, usually in the same car park. Two child seats in the back.'),
                'metadata' => [
                    'brand' => 'BMW',
                    'model' => '320i Touring',
                    'year' => '2018',
                    'color' => t('case.vehicle.colour', 'Black'),
                    'license_plate' => 'B-AB 1234',
                    'vin' => 'WBA8E11020K123456',
                ],
            ],
            'clothing' => [
                'key' => 'clothing',
                'public_id' => 'JAKTAG24',
                'type' => DAT_ASSET_TYPE_CLOTHING,
                'name' => 'Patagonia Down Jacket',
                'description' => t('case.clothing.description', 'Club jacket with the badge on the chest. Used at training and on trips.'),
                'metadata' => [
                    'brand' => 'Patagonia',
                    'model' => 'Down Sweater',
                    'size' => 'M',
                    'color' => t('case.clothing.colour', 'Dark blue'),
                ],
            ],
            'item' => [
                'key' => 'item',
                'public_id' => 'BAGTAG24',
                'type' => DAT_ASSET_TYPE_ITEM,
                'name' => 'Camera bag',
                'description' => t('case.item.description', 'Grey shoulder bag with a tripod strap. Carries one camera body and two lenses.'),
                'metadata' => [
                    'category' => t('case.item.category', 'Camera bag'),
                    'brand' => 'Peak Design',
                    'model' => 'Everyday Sling',
                    'color' => t('case.item.colour', 'Grey'),
                    'serial' => 'PD-88213',
                    'purchase_date' => '2024-03-11',
                ],
            ],
            'industrial' => [
                'key' => 'industrial',
                'public_id' => 'MACTAG24',
                'type' => DAT_ASSET_TYPE_INDUSTRIAL,
                'name' => 'Hydraulic power unit HPU-7',
                'description' => t('case.industrial.description', 'Mobile power unit that travels between sites. The maintenance interval is stamped on the plate.'),
                'metadata' => [
                    'manufacturer' => 'HydraTec',
                    'model' => 'HPU-7',
                    'category' => t('case.industrial.category', 'Power unit'),
                    'machine_id' => 'M-2021-0147',
                    'serial' => 'SN-4471-88',
                    'location' => t('case.industrial.location', 'Bay 3, plant 2'),
                    'service_contact' => t('case.industrial.service', 'Service desk, extension 240'),
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

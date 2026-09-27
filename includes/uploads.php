<?php
/**
 * Image uploads.
 *
 * Every upload is re-encoded through GD before it is stored. That drops EXIF
 * data (including GPS coordinates) and neutralises files that only pretend to
 * be images. The database stores the object key, never the binary.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/db.php';

const DAT_UPLOAD_DIR = 'storage/uploads';

if (!function_exists('dat_upload_path')) {
    function dat_upload_path($objectKey = '')
    {
        return DAT_APP_ROOT . '/' . DAT_UPLOAD_DIR . ($objectKey === '' ? '' : '/' . ltrim((string) $objectKey, '/'));
    }
}

if (!function_exists('dat_upload_allowed_types')) {
    function dat_upload_allowed_types()
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }
}

/**
 * Validate, re-encode and store an uploaded image.
 *
 * @return array{success: bool, object_key?: string, errors?: string[]}
 */
if (!function_exists('dat_store_upload')) {
    function dat_store_upload(array $file)
    {
        $errors = [];

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'errors' => [t('error.upload_failed', 'The upload did not complete. Please try again.')]];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'errors' => []];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'errors' => [t('error.upload_failed', 'The upload did not complete. Please try again.')]];
        }
        if (($file['size'] ?? 0) > PORTAL_MAX_UPLOAD_BYTES) {
            return ['success' => false, 'errors' => [t('error.upload_too_large', 'The image is larger than 10 MB.')]];
        }
        if (!function_exists('imagecreatefromstring')) {
            return ['success' => false, 'errors' => [t('error.upload_unsupported', 'Image processing is unavailable on this server.')]];
        }

        $info = @getimagesize($file['tmp_name']);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';
        $allowed = dat_upload_allowed_types();
        if (!isset($allowed[$mime])) {
            return ['success' => false, 'errors' => [t('error.upload_type', 'Only JPEG, PNG and WebP images are accepted.')]];
        }

        $binary = @file_get_contents($file['tmp_name']);
        if ($binary === false) {
            return ['success' => false, 'errors' => [t('error.upload_failed', 'The upload did not complete. Please try again.')]];
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return ['success' => false, 'errors' => [t('error.upload_invalid', 'That file is not a readable image.')]];
        }

        // imagedestroy() is a no-op since PHP 8.0 and deprecated in PHP 8.5.
        $free = static function ($handle): void {
            if (PHP_VERSION_ID < 80500 && $handle !== false && function_exists('imagedestroy')) {
                imagedestroy($handle);
            }
        };

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 40 || $height < 40) {
            $free($image);
            return ['success' => false, 'errors' => [t('error.upload_small', 'Please use an image of at least 40 x 40 pixels.')]];
        }

        // Downscale very large images; re-encoding also removes EXIF/GPS data.
        $maxEdge = 1600;
        if ($width > $maxEdge || $height > $maxEdge) {
            $ratio = min($maxEdge / $width, $maxEdge / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagescale($image, $newWidth, $newHeight);
            if ($resized !== false) {
                $free($image);
                $image = $resized;
            }
        }

        $objectKey = date('Y/m') . '/' . bin2hex(random_bytes(12)) . '.jpg';
        $target = dat_upload_path($objectKey);
        $directory = dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
            $free($image);
            return ['success' => false, 'errors' => [t('error.upload_failed', 'The upload did not complete. Please try again.')]];
        }

        $saved = @imagejpeg($image, $target, 86);
        $free($image);
        if (!$saved) {
            return ['success' => false, 'errors' => [t('error.upload_failed', 'The upload did not complete. Please try again.')]];
        }

        return ['success' => true, 'object_key' => $objectKey];
    }
}

if (!function_exists('dat_attach_image')) {
    function dat_attach_image($assetId, $objectKey, $originalName = null)
    {
        $path = dat_upload_path($objectKey);
        $size = is_file($path) ? (int) filesize($path) : null;
        $position = dat_one(
            'SELECT COALESCE(MAX(position), -1) + 1 AS next FROM ' . dat_table('asset_images') . ' WHERE asset_id = ?',
            [$assetId]
        );

        return dat_exec(
            'INSERT INTO ' . dat_table('asset_images') . '
                (id, asset_id, object_key, original_file_name, mime_type, byte_size, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                dat_uuid(),
                $assetId,
                $objectKey,
                $originalName !== null ? mb_substr((string) $originalName, 0, 190) : null,
                'image/jpeg',
                $size,
                (int) ($position['next'] ?? 0),
                dat_now(),
            ]
        ) >= 0;
    }
}

if (!function_exists('dat_delete_image')) {
    function dat_delete_image($imageId, $assetId, $ownerId)
    {
        $image = dat_one(
            'SELECT i.* FROM ' . dat_table('asset_images') . ' i
               JOIN ' . dat_table('assets') . ' a ON a.id = i.asset_id
              WHERE i.id = ? AND i.asset_id = ? AND a.owner_id = ? LIMIT 1',
            [$imageId, $assetId, $ownerId]
        );

        if ($image === null) {
            return false;
        }

        dat_exec('DELETE FROM ' . dat_table('asset_images') . ' WHERE id = ?', [$imageId]);

        $path = dat_upload_path($image['object_key']);
        if (is_file($path)) {
            @unlink($path);
        }

        return true;
    }
}

if (!function_exists('dat_image_keys')) {
    function dat_image_keys($assetId)
    {
        return array_map(
            static fn ($row) => $row['object_key'],
            dat_asset_images($assetId)
        );
    }
}

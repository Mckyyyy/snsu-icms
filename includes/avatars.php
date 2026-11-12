<?php
// includes/avatars.php - Profile photos (stored in `profile_photos`, served by api/avatar.php)

const PROFILE_PHOTO_MAX_BYTES  = 8 * 1024 * 1024;   // upload limit
const PROFILE_PHOTO_MAX_PIXELS = 30000000;          // ~30 megapixels (phone photos are 12–48 MP)
const PROFILE_PHOTO_SIZE       = 320;               // saved as a 320×320 JPEG

/**
 * Photo versions (first 12 characters of the checksum) for many owners at once.
 * Returns [owner_id => version]; owners without a photo are missing from the result.
 */
function profilePhotoVersions(PDO $pdo, string $type, array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $in = implode(',', $ids);
    $stmt = $pdo->prepare("SELECT owner_id, LEFT(sha256, 12) FROM profile_photos WHERE owner_type = :t AND owner_id IN ($in)");
    $stmt->execute(['t' => $type]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

/** Version of one owner's photo, or null (cached for the request). */
function profilePhotoVersion(PDO $pdo, string $type, int $id): ?string {
    static $cache = [];
    $key = $type . ':' . $id;
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = profilePhotoVersions($pdo, $type, [$id])[$id] ?? null;
    }
    return $cache[$key];
}

function profilePhotoUrl(string $type, int $id, string $version): string {
    return appRootPrefix() . 'api/avatar.php?type=' . $type . '&id=' . $id . '&v=' . rawurlencode($version);
}

/**
 * Avatar markup: the photo when there is one, otherwise the initials.
 * $class/$style are added to the wrapper so existing avatar sizes keep working.
 */
function avatarHtml(string $type, int $id, string $name, ?string $version, string $class = 'avatar', string $style = ''): string {
    $styleAttr = $style !== '' ? ' style="' . e($style) . '"' : '';
    if ($version) {
        return '<div class="' . e($class) . ' avatar-photo"' . $styleAttr . '><img src="'
            . e(profilePhotoUrl($type, $id, $version)) . '" alt="' . e($name) . '" loading="lazy"></div>';
    }
    return '<div class="' . e($class) . '"' . $styleAttr . '>' . e(userInitials($name)) . '</div>';
}

/**
 * Validate an uploaded photo, then crop it to a centred square, resize it and re-encode it as JPEG
 * (this also removes location and camera data). Returns the JPEG bytes, or an error message string
 * through $error.
 */
function processProfilePhoto(?array $upload, ?string &$error): ?string {
    $error = null;
    $maxMb = PROFILE_PHOTO_MAX_BYTES / 1024 / 1024;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose a photo to upload.';
        return null;
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $error = "The photo is too large. The limit is {$maxMb} MB.";
        return null;
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $error = 'The photo failed to upload. Please try again.';
        return null;
    }
    $size = (int)filesize($upload['tmp_name']);
    if ($size <= 0 || $size > PROFILE_PHOTO_MAX_BYTES) {
        $error = $size <= 0 ? 'The photo is empty. Please choose another file.' : "The photo is too large. The limit is {$maxMb} MB.";
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
    $info = @getimagesize($upload['tmp_name']);
    $okTypes = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];
    if (!isset($okTypes[$mime]) || !$info || $info[2] !== $okTypes[$mime] || $info[0] < 1 || $info[1] < 1) {
        $error = 'Please upload a JPG or PNG photo.';
        return null;
    }
    if ($info[0] * $info[1] > PROFILE_PHOTO_MAX_PIXELS) {
        $error = 'The photo has too many pixels. Please use a smaller photo.';
        return null;
    }
    if (!function_exists('imagecreatefromstring')) {
        $error = 'Photo processing is not available on this server.';
        return null;
    }
    @ini_set('memory_limit', '256M');
    $src = @imagecreatefromstring((string)file_get_contents($upload['tmp_name']));
    if (!$src) {
        $error = 'The photo could not be read. Please try another one.';
        return null;
    }
    // Phone photos are often stored sideways with an orientation flag
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($upload['tmp_name']);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle !== 0 && ($rotated = imagerotate($src, $angle, 0))) {
            imagedestroy($src);
            $src = $rotated;
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $out = imagecreatetruecolor(PROFILE_PHOTO_SIZE, PROFILE_PHOTO_SIZE);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // transparent PNG → white
    imagecopyresampled($out, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2),
        PROFILE_PHOTO_SIZE, PROFILE_PHOTO_SIZE, $side, $side);
    imagedestroy($src);
    ob_start();
    imagejpeg($out, null, 86);
    $jpeg = (string)ob_get_clean();
    imagedestroy($out);
    if ($jpeg === '') {
        $error = 'The photo could not be saved. Please try again.';
        return null;
    }
    return $jpeg;
}

function saveProfilePhoto(PDO $pdo, string $type, int $id, string $jpeg): void {
    $stmt = $pdo->prepare("
        INSERT INTO profile_photos (owner_type, owner_id, mime_type, file_size, sha256, photo_data)
        VALUES (:t, :id, 'image/jpeg', :size, :sha, :data)
        ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), file_size = VALUES(file_size),
            sha256 = VALUES(sha256), photo_data = VALUES(photo_data), updated_at = NOW()
    ");
    $stmt->bindValue('t', $type);
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->bindValue('size', strlen($jpeg), PDO::PARAM_INT);
    $stmt->bindValue('sha', hash('sha256', $jpeg));
    $stmt->bindValue('data', $jpeg, PDO::PARAM_LOB);
    $stmt->execute();
}

function removeProfilePhoto(PDO $pdo, string $type, int $id): void {
    $pdo->prepare("DELETE FROM profile_photos WHERE owner_type = :t AND owner_id = :id")->execute(['t' => $type, 'id' => $id]);
}

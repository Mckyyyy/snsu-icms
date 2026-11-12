<?php
// includes/uploads.php - Concern attachment validation and database storage (shared by submit and edit)
//
// Attachments are stored as binary data (LONGBLOB) in `concern_attachments`, one per concern,
// and served only through api/attachment.php after an ownership / role check.

const ATTACHMENT_MAX_BYTES = 20 * 1024 * 1024;   // 20 MB (MySQL max_allowed_packet must stay above this)
const ATTACHMENT_MAX_PIXELS = 12000;   // width/height limit for images
const ATTACHMENT_TYPES = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'pdf'  => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
];
/** Image MIME type → the IMAGETYPE_* constant getimagesize() must report for it. */
const ATTACHMENT_IMAGE_TYPES = [
    'image/jpeg' => IMAGETYPE_JPEG,
    'image/png'  => IMAGETYPE_PNG,
];

function attachmentIsImage(string $mime): bool {
    return isset(ATTACHMENT_IMAGE_TYPES[$mime]);
}

/** Keep the original file name readable but safe to store and send in a header. */
function cleanAttachmentName(string $name, string $ext): string {
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '_', $name) ?? '';
    $name = trim($name, " .\t");
    if ($name === '' || !mb_check_encoding($name, 'UTF-8')) {
        $name = 'attachment.' . $ext;
    }
    if (mb_strlen($name) > 200) {
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190) . '.' . $ext;
    }
    return $name;
}

/**
 * Validate an uploaded attachment and read it into memory.
 * Returns ['name', 'mime', 'size', 'sha256', 'data'], or null when no file was chosen.
 * On a validation problem it sets a flash message and redirects to $back.
 */
function validateConcernAttachment(?array $upload, string $back): ?array {
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $fail = function (string $msg) use ($back) {
        setFlash('error', $msg);
        header("Location: {$back}");
        exit;
    };
    $maxMb = ATTACHMENT_MAX_BYTES / 1024 / 1024;
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $fail("Attachment exceeds maximum file size of {$maxMb}MB.");
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $fail("The attachment failed to upload. Please try again (max {$maxMb}MB).");
    }

    $size = (int)filesize($upload['tmp_name']);
    if ($size <= 0) {
        $fail('The attachment is empty. Please choose another file.');
    }
    if ($size > ATTACHMENT_MAX_BYTES) {
        $fail("Attachment exceeds maximum file size of {$maxMb}MB.");
    }

    // Validate both extension and real MIME type (extension alone is spoofable)
    $ext  = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
    if (!isset(ATTACHMENT_TYPES[$ext]) || !in_array($mime, ATTACHMENT_TYPES[$ext], true)) {
        $fail('Invalid file format. Allowed formats: JPG, PNG, PDF, DOCX.');
    }
    // Images must also be readable images of the declared type (not a renamed file)
    if (attachmentIsImage($mime)) {
        $info = @getimagesize($upload['tmp_name']);
        if (!$info || $info[2] !== ATTACHMENT_IMAGE_TYPES[$mime] || $info[0] < 1 || $info[1] < 1) {
            $fail('The image could not be read. Please upload a valid JPG or PNG photo.');
        }
        if ($info[0] > ATTACHMENT_MAX_PIXELS || $info[1] > ATTACHMENT_MAX_PIXELS) {
            $fail('The image is too large (max ' . ATTACHMENT_MAX_PIXELS . ' pixels per side). Please resize it.');
        }
    }
    // A stored file is sent to MySQL as one packet; make sure the server accepts it
    global $pdo;
    if ($pdo instanceof PDO) {
        $packet = (int)$pdo->query("SELECT @@max_allowed_packet")->fetchColumn();
        if ($packet > 0 && $size + 65536 > $packet) {
            error_log("[ICMS] Attachment of {$size} bytes exceeds MySQL max_allowed_packet ({$packet}).");
            $fail('The attachment is too large for the database server. Please upload a smaller file.');
        }
    }

    $data = file_get_contents($upload['tmp_name']);
    if ($data === false || strlen($data) !== $size) {
        $fail('The attachment could not be read. Please try again.');
    }
    return [
        'name'   => cleanAttachmentName((string)$upload['name'], $ext === 'jpeg' ? 'jpg' : $ext),
        'mime'   => $mime === 'application/zip' ? ATTACHMENT_TYPES['docx'][0] : $mime,
        'size'   => $size,
        'sha256' => hash('sha256', $data),
        'data'   => $data,
    ];
}

/**
 * Insert or replace the attachment of a concern (call inside the caller's transaction).
 * The file bytes are sent to MySQL exactly once, so the packet stays ≈ the file size.
 */
function saveConcernAttachment(PDO $pdo, int $concernId, array $file): void {
    $exists = $pdo->prepare("SELECT 1 FROM concern_attachments WHERE concern_id = :cid FOR UPDATE");
    $exists->execute(['cid' => $concernId]);
    $stmt = $pdo->prepare($exists->fetchColumn()
        ? "UPDATE concern_attachments SET original_name = :name, mime_type = :mime, file_size = :size,
                  sha256 = :sha, file_data = :data, uploaded_at = NOW() WHERE concern_id = :cid"
        : "INSERT INTO concern_attachments (concern_id, original_name, mime_type, file_size, sha256, file_data)
           VALUES (:cid, :name, :mime, :size, :sha, :data)");
    $stmt->bindValue('cid', $concernId, PDO::PARAM_INT);
    $stmt->bindValue('name', $file['name']);
    $stmt->bindValue('mime', $file['mime']);
    $stmt->bindValue('size', $file['size'], PDO::PARAM_INT);
    $stmt->bindValue('sha', $file['sha256']);
    $stmt->bindValue('data', $file['data'], PDO::PARAM_LOB);
    $stmt->execute();
}

function removeConcernAttachment(PDO $pdo, int $concernId): void {
    $pdo->prepare("DELETE FROM concern_attachments WHERE concern_id = :cid")->execute(['cid' => $concernId]);
}

/** Attachment details without the binary data (for pages and lists). */
function getConcernAttachmentMeta(PDO $pdo, int $concernId): ?array {
    $stmt = $pdo->prepare("
        SELECT attachment_id, concern_id, original_name, mime_type, file_size, sha256, uploaded_at
        FROM concern_attachments WHERE concern_id = :cid
    ");
    $stmt->execute(['cid' => $concernId]);
    return $stmt->fetch() ?: null;
}

/** URL that streams the attachment from the database (cache-busted by its checksum). */
function concernAttachmentUrl(array $meta, bool $download = false, string $rootPrefix = '../'): string {
    return $rootPrefix . 'api/attachment.php?concern=' . (int)$meta['concern_id']
        . '&v=' . substr((string)$meta['sha256'], 0, 12) . ($download ? '&download=1' : '');
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

/**
 * One-time import of attachments that were saved in uploads/ before database storage existed.
 * Each file is checked, copied into concern_attachments, read back and compared byte-for-byte;
 * only then is concerns.attachment_path cleared. The original files are left on disk.
 */
function importLegacyAttachmentFiles(PDO $pdo): array {
    $log = ['imported' => 0, 'missing' => 0, 'skipped' => 0];
    $uploads = realpath(__DIR__ . '/../uploads');
    $rows = $pdo->query("
        SELECT c.concern_id, c.attachment_path FROM concerns c
        LEFT JOIN concern_attachments a ON a.concern_id = c.concern_id
        WHERE c.attachment_path IS NOT NULL AND c.attachment_path <> '' AND a.attachment_id IS NULL
    ")->fetchAll();
    foreach ($rows as $r) {
        $file = realpath(__DIR__ . '/../' . $r['attachment_path']);
        if (!$uploads || !$file || !str_starts_with($file, $uploads . DIRECTORY_SEPARATOR) || !is_file($file)) {
            $log['missing']++;
            continue;
        }
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';
        $data = file_get_contents($file);
        if (!isset(ATTACHMENT_TYPES[$ext]) || !in_array($mime, ATTACHMENT_TYPES[$ext], true) || $data === false || $data === '') {
            $log['skipped']++;
            error_log("[ICMS] Attachment import skipped for concern {$r['concern_id']}: {$r['attachment_path']} ({$mime})");
            continue;
        }
        $payload = [
            'name'   => cleanAttachmentName(basename($file), $ext),
            'mime'   => $mime === 'application/zip' ? ATTACHMENT_TYPES['docx'][0] : $mime,
            'size'   => strlen($data),
            'sha256' => hash('sha256', $data),
            'data'   => $data,
        ];
        saveConcernAttachment($pdo, (int)$r['concern_id'], $payload);
        $check = $pdo->prepare("SELECT file_data FROM concern_attachments WHERE concern_id = :cid");
        $check->execute(['cid' => $r['concern_id']]);
        if (hash('sha256', (string)$check->fetchColumn()) === $payload['sha256']) {
            $pdo->prepare("UPDATE concerns SET attachment_path = NULL WHERE concern_id = :cid")->execute(['cid' => $r['concern_id']]);
            $log['imported']++;
        } else {
            removeConcernAttachment($pdo, (int)$r['concern_id']);
            $log['skipped']++;
            error_log("[ICMS] Attachment import verification failed for concern {$r['concern_id']}");
        }
    }
    return $log;
}

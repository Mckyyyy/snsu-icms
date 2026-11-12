<?php
// includes/notifications.php - Audit trail and in-app notification helpers

function logConcernAudit(PDO $pdo, int $concernId, string $actorType, ?int $actorId, string $action, ?string $old = null, ?string $new = null): void {
    $stmt = $pdo->prepare("
        INSERT INTO concern_audit_logs (concern_id, actor_type, actor_id, action, old_value, new_value)
        VALUES (:cid, :atype, :aid, :action, :old, :new)
    ");
    $stmt->execute([
        'cid'    => $concernId,
        'atype'  => $actorType,
        'aid'    => $actorId,
        'action' => $action,
        'old'    => $old,
        'new'    => $new,
    ]);
}

function notifyUser(PDO $pdo, string $recipientType, int $recipientId, ?int $concernId, string $type, string $title, string $message): void {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message)
        VALUES (:rtype, :rid, :cid, :type, :title, :msg)
    ");
    $stmt->execute([
        'rtype' => $recipientType,
        'rid'   => $recipientId,
        'cid'   => $concernId,
        'type'  => $type,
        'title' => $title,
        'msg'   => $message,
    ]);
}

function notifyAllAdmins(PDO $pdo, ?int $concernId, string $type, string $title, string $message): void {
    $ids = $pdo->query("SELECT admin_id FROM administrators WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        notifyUser($pdo, 'admin', (int)$id, $concernId, $type, $title, $message);
    }
}

function getUserNotifications(PDO $pdo, string $role, int $userId, int $limit = 8): array {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE recipient_type = :rtype AND recipient_id = :rid
        ORDER BY created_at DESC, notification_id DESC
        LIMIT {$limit}
    ");
    $stmt->execute(['rtype' => $role, 'rid' => $userId]);
    return $stmt->fetchAll();
}

function countUnreadNotifications(PDO $pdo, string $role, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_type = :rtype AND recipient_id = :rid AND is_read = 0");
    $stmt->execute(['rtype' => $role, 'rid' => $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Record an administrator action (user management, announcements, assignments...).
 */
function logAdminActivity(PDO $pdo, ?int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    try {
        $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, ip_address)
            VALUES (:a, :act, :tt, :tid, :d, :ip)
        ")->execute([
            'a'   => $adminId,
            'act' => $action,
            'tt'  => $targetType,
            'tid' => $targetId,
            'd'   => $details !== null ? mb_substr($details, 0, 500) : null,
            'ip'  => substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45),
        ]);
    } catch (Throwable $e) {
        error_log('[ICMS] Activity log failed: ' . $e->getMessage());
    }
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return intdiv($diff, 60) . ' min ago';
    if ($diff < 86400) return intdiv($diff, 3600) . ' hr ago';
    if ($diff < 604800) { $d = intdiv($diff, 86400); return $d === 1 ? 'yesterday' : "{$d} days ago"; }
    return date('M j, Y', strtotime($datetime));
}

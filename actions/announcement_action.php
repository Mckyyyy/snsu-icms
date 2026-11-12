<?php
// actions/announcement_action.php - Admin announcements (Use case: Manage Announcements)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();
requirePostWithCsrf('../admin/announcements.php');

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $isUrgent = !empty($_POST['is_urgent']) ? 1 : 0;

    if ($title === '' || $content === '') {
        setFlash('error', 'Please provide a title and content for the announcement.');
        header('Location: ../admin/announcements.php');
        exit;
    }

    $pdo->prepare("
        INSERT INTO announcements (admin_id, title, content, category, is_urgent, published_at)
        VALUES (:admin_id, :title, :content, :category, :is_urgent, NOW())
    ")->execute([
        'admin_id'  => $user['id'],
        'title'     => $title,
        'content'   => $content,
        'category'  => $category,
        'is_urgent' => $isUrgent,
    ]);
    $annId = (int)$pdo->lastInsertId();

    if ($isUrgent) {
        // Urgent bulletins reach every active student's notification bell
        $pdo->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message)
            SELECT 'student', student_id, NULL, 'announcement', :title, :msg
            FROM students WHERE status = 'Active'
        ")->execute(['title' => 'Urgent: ' . mb_substr($title, 0, 240), 'msg' => mb_substr($content, 0, 200)]);
    }

    logAdminActivity($pdo, (int)$user['id'], 'announcement_published', 'announcement', $annId, ($isUrgent ? '[Urgent] ' : '') . $title);
    setFlash('success', $isUrgent ? 'Urgent announcement published and sent to all students.' : 'Announcement published successfully!');
} elseif ($action === 'update') {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    if ($title === '' || $content === '') {
        setFlash('error', 'Please provide a title and content for the announcement.');
    } else {
        // Editing never re-sends the urgent notification; it only changes what students read
        $pdo->prepare("UPDATE announcements SET title = :t, content = :c, category = :cat, is_urgent = :u WHERE announcement_id = :id")
            ->execute(['t' => mb_substr($title, 0, 255), 'c' => $content, 'cat' => mb_substr($category, 0, 100),
                       'u' => !empty($_POST['is_urgent']) ? 1 : 0, 'id' => $id]);
        logAdminActivity($pdo, (int)$user['id'], 'announcement_updated', 'announcement', $id, $title);
        setFlash('success', 'Announcement updated.');
    }
} elseif (in_array($action, ['archive', 'delete', 'restore'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    $t = $pdo->prepare("SELECT title FROM announcements WHERE announcement_id = :id");
    $t->execute(['id' => $id]);
    $annTitle = $t->fetchColumn() ?: null;
    if ($annTitle !== null) {
        if ($action === 'restore') {
            $pdo->prepare("UPDATE announcements SET archived_at = NULL, archived_by = NULL WHERE announcement_id = :id")->execute(['id' => $id]);
            logAdminActivity($pdo, (int)$user['id'], 'announcement_restored', 'announcement', $id, $annTitle);
            setFlash('success', 'Announcement restored. Students can see it again.');
        } else {
            $pdo->prepare("UPDATE announcements SET archived_at = NOW(), archived_by = :a WHERE announcement_id = :id")
                ->execute(['a' => $user['id'], 'id' => $id]);
            logAdminActivity($pdo, (int)$user['id'], 'announcement_archived', 'announcement', $id, $annTitle);
            setFlash('success', 'Announcement archived. Students no longer see it; restore it from the Archived tab.');
        }
    }
}

$tab = ($_POST['return_tab'] ?? '') === 'archived' ? '?tab=archived' : '';
header('Location: ../admin/announcements.php' . $tab);
exit;

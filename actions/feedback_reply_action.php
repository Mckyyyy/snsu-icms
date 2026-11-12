<?php
// actions/feedback_reply_action.php - Administrator replies to a student's feedback
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();
requirePostWithCsrf('../admin/feedback.php');

$feedbackId = (int)($_POST['feedback_id'] ?? 0);
$reply      = trim($_POST['admin_reply'] ?? '');
$back       = '../admin/feedback.php' . (isset($_POST['return_type']) && $_POST['return_type'] !== '' ? '?type=' . urlencode($_POST['return_type']) : '') . "#feedback-{$feedbackId}";

if (mb_strlen($reply) < 2 || mb_strlen($reply) > 2000) {
    setFlash('error', 'Please write a reply of 2 to 2,000 characters.');
    header("Location: {$back}");
    exit;
}

$f = $pdo->prepare("SELECT * FROM feedback WHERE feedback_id = :id");
$f->execute(['id' => $feedbackId]);
$feedback = $f->fetch();
if (!$feedback) {
    setFlash('error', 'Feedback not found.');
    header('Location: ../admin/feedback.php');
    exit;
}

$isUpdate = !empty($feedback['admin_reply']);
$pdo->prepare("UPDATE feedback SET admin_reply = :r, replied_by = :a, replied_at = NOW() WHERE feedback_id = :id")
    ->execute(['r' => $reply, 'a' => $user['id'], 'id' => $feedbackId]);
logAdminActivity($pdo, (int)$user['id'], 'feedback_replied', 'feedback', $feedbackId, mb_substr($feedback['subject'], 0, 200));

if ($feedback['student_id']) {
    notifyUser($pdo, 'student', (int)$feedback['student_id'], $feedback['concern_id'] ? (int)$feedback['concern_id'] : null,
        'feedback_reply', $isUpdate ? 'Updated reply to your feedback' : 'The administration replied to your feedback',
        "\"{$feedback['subject']}\": " . mb_substr($reply, 0, 160));
    try {
        queueEmail($pdo, $feedback['student_email'], $feedback['student_name'], 'Reply to your feedback: ' . $feedback['subject'], [
            "Hi {$feedback['student_name']},",
            "Thank you for your feedback \"{$feedback['subject']}\".",
            "Reply from {$user['name']}:\n{$reply}",
        ], appUrl('student/feedback.php'), 'View your feedback');
    } catch (Throwable $e) {
        error_log('[ICMS] Queue email failed: ' . $e->getMessage());
    }
}

setFlash('success', $isUpdate ? 'Reply updated and the student was notified.' : 'Reply sent to the student.');
header("Location: {$back}");
exit;

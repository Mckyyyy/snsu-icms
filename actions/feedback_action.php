<?php
// actions/feedback_action.php - Student feedback & resolution satisfaction ratings
//   General feedback        : from student/feedback.php (Figure 3)
//   Resolution rating       : from student/concern_details.php on a Resolved ticket (concern_id set)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$back      = $concernId > 0 ? "../student/concern_details.php?id={$concernId}" : '../student/feedback.php';
requirePostWithCsrf($back);

$rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$message = trim($_POST['message'] ?? '');

if ($concernId > 0) {
    // ---- Resolution rating: only the owner, only once, only when Resolved
    $c = $pdo->prepare("SELECT ticket_id, status_id FROM concerns WHERE concern_id = :id AND student_id = :sid");
    $c->execute(['id' => $concernId, 'sid' => $user['id']]);
    $concern = $c->fetch();

    if (!$concern || (int)$concern['status_id'] !== 3) {
        setFlash('error', 'You can only rate your own resolved concerns.');
        header("Location: {$back}");
        exit;
    }

    $dup = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE concern_id = :id");
    $dup->execute(['id' => $concernId]);
    if ((int)$dup->fetchColumn() > 0) {
        setFlash('info', 'You have already rated this resolution. Thank you!');
        header("Location: {$back}");
        exit;
    }

    $feedbackType = 'Resolution Rating';
    $subject      = "Resolution of {$concern['ticket_id']}";
    if ($message === '') {
        $message = '(No comment)';
    }
} else {
    // ---- General feedback
    $feedbackType = trim($_POST['feedback_type'] ?? '');
    $subject      = trim($_POST['subject'] ?? '');

    if ($feedbackType === '' || $subject === '' || $message === '') {
        setFlash('error', 'Please fill in the Feedback Type, Subject, and Message fields.');
        header("Location: {$back}");
        exit;
    }
}

$pdo->prepare("
    INSERT INTO feedback (student_id, concern_id, student_name, student_email, feedback_type, subject, message, rating, submitted_at)
    VALUES (:student_id, :concern_id, :name, :email, :type, :subject, :message, :rating, NOW())
")->execute([
    'student_id' => $user['id'],
    'concern_id' => $concernId > 0 ? $concernId : null,
    'name'       => $user['name'],
    'email'      => $user['email'],
    'type'       => $feedbackType,
    'subject'    => mb_substr($subject, 0, 255),
    'message'    => $message,
    'rating'     => $rating,
]);

if ($concernId > 0) {
    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'rated_resolution', null, "{$rating}/5");
    setFlash('success', 'Thank you for rating the resolution of your concern!');
} else {
    setFlash('success', 'Thank you! Your feedback has been submitted to the administration.');
}
header("Location: {$back}");
exit;

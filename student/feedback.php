<?php
// student/feedback.php - Student Feedback Submission (Figure 3 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
$studentId = $user['id'];

// Fetch past feedback from this student
$stmt = $pdo->prepare("SELECT * FROM feedback WHERE student_id = :id ORDER BY submitted_at DESC");
$stmt->execute(['id' => $studentId]);
$pastFeedback = $stmt->fetchAll();

$pageTitle = "Student Feedback | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Campus</div>
    <h1 class="page-title">Feedback</h1>
    <p class="page-desc">Share suggestions about campus services or this system. Every message is read by the administration.</p>
  </div>
</div>

<div class="grid-main-side">
  <form action="../actions/feedback_action.php" method="POST" class="card">
    <?= csrfField() ?>
    <div class="card-header">
      <div>
        <h2 class="card-title">Send Feedback</h2>
        <div class="card-subtitle">For a specific problem, please <a href="submit_concern.php">submit a concern</a> instead so it can be tracked.</div>
      </div>
    </div>
    <div class="card-body">
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="feedbackType">Type <span class="required">*</span></label>
          <select name="feedback_type" id="feedbackType" class="form-control" required>
            <option value="" disabled selected>Select a type</option>
            <option value="System Feedback">System &amp; user interface</option>
            <option value="Facilities Feedback">Facilities &amp; campus environment</option>
            <option value="Academic Feedback">Academic support &amp; curriculum</option>
            <option value="Services Feedback">Student services &amp; administration</option>
            <option value="General Suggestion">General suggestion</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="subject">Subject <span class="required">*</span></label>
          <input type="text" name="subject" id="subject" class="form-control" placeholder="A short summary" required maxlength="255">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="message">Message <span class="required">*</span></label>
        <textarea name="message" id="message" class="form-control" rows="6" placeholder="What is working well? What should we improve?" required maxlength="5000"></textarea>
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <span class="form-label">Overall rating</span>
        <input type="hidden" name="rating" id="ratingValue" value="5">
        <div class="flex items-center gap-3">
          <div class="star-rating" title="Click a star to rate">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="star selected" data-value="<?= $i ?>"><?= icon('star') ?></span>
            <?php endfor; ?>
          </div>
          <span class="text-sm text-muted" id="ratingLabel">Excellent</span>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= icon('message-square', '', 16) ?> Submit Feedback</button>
    </div>
  </form>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Your Feedback History</h2>
      <span class="badge-tag"><?= count($pastFeedback) ?></span>
    </div>
    <?php if (!$pastFeedback): ?>
      <div class="empty-state" style="padding: 32px 16px;">
        <div class="empty-state-icon"><?= icon('message-square', '', 22) ?></div>
        <p>You haven't sent any feedback yet.</p>
      </div>
    <?php endif; ?>
    <div style="max-height: 560px; overflow-y: auto;">
      <?php foreach ($pastFeedback as $fb): ?>
        <div class="list-item">
          <div class="list-item-body">
            <div class="list-item-title"><?= e($fb['subject']) ?></div>
            <div class="flex items-center gap-2" style="margin-top: 4px;"><?= renderStarRating((int)$fb['rating']) ?></div>
            <div class="list-item-text"><?= nl2br(e(mb_strimwidth($fb['message'], 0, 180, '…'))) ?></div>
            <div class="list-item-meta">
              <span class="badge-tag"><?= e($fb['feedback_type']) ?></span><span><?= formatDate($fb['submitted_at']) ?></span>
              <?php if (empty($fb['admin_reply'])): ?><span class="text-muted">No reply yet</span><?php endif; ?>
            </div>
            <?php if (!empty($fb['admin_reply'])): ?>
              <div class="feedback-reply">
                <div class="feedback-reply-head"><?= icon('message-square', '', 14) ?> Reply from the administration · <?= formatDate($fb['replied_at']) ?></div>
                <div class="text-sm"><?= nl2br(e($fb['admin_reply'])) ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
  (function () {
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];
    const input = document.getElementById('ratingValue');
    const out = document.getElementById('ratingLabel');
    document.querySelectorAll('.star-rating .star').forEach((s) => s.addEventListener('click', () => {
      setTimeout(() => { out.textContent = labels[+input.value] || ''; }, 0);
    }));
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

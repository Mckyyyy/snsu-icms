<?php
// api/ai_prioritize.php - JSON endpoint for the DPT-RRT live preview.
// Uses the exact same engine as actions/concern_action.php, so the preview
// a student sees is what will be stored on submission.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (sessionExpired() || !isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 401);
}
requireCsrfJson();
touchSession(); // typing in the form counts as activity
$user = getCurrentUser();

$title       = mb_substr((string)($_POST['title'] ?? ''), 0, 255);
$description = mb_substr((string)($_POST['description'] ?? ''), 0, 10000);
$categoryId  = (int)($_POST['category_id'] ?? 0);

$categoryName = '';
if ($categoryId > 0) {
    $stmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = :id");
    $stmt->execute(['id' => $categoryId]);
    $categoryName = (string)$stmt->fetchColumn();
}

// mode=quick: answer instantly while the student types (a Gemini answer already cached for this exact
// text, otherwise the rule-based engine). The browser then asks again without "quick" for Gemini.
if (($_POST['mode'] ?? '') === 'quick') {
    $result = cachedAiResult($title, $description, $categoryName);
    if ($result === null) {
        $result = calculateDynamicUrgency($title, $description, $categoryName);
        $result['engine'] = 'rules';
        $result['engine_note'] = 'quick';
        $result['fix_hours'] = null;
        $result['fix_note'] = null;
    }
} else {
    // Short wait for the preview; submission itself waits longer (config/ai.php timeout)
    $result = prioritizeConcern($title, $description, $categoryName, activeCategoryNames($pdo), 10);
}
$result['ai_available'] = aiLlmAvailable();

if ($result['suggested_category'] !== null) {
    $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :n AND is_active = 1");
    $stmt->execute(['n' => $result['suggested_category']]);
    $result['suggested_category_id'] = (int)$stmt->fetchColumn() ?: null;
}

// Duplicate detection: open concerns that describe the same problem (students only;
// other students' concerns are shown without names, see duplicateForStudent()).
$similar = [];
if ($user['role'] === 'student') {
    $excludeId = (int)($_POST['concern_id'] ?? 0); // the concern being edited
    foreach (findSimilarConcerns($pdo, $title, $description, $categoryId, $excludeId) as $m) {
        $similar[] = duplicateForStudent($m, (int)$user['id']);
    }
}
$result['similar'] = $similar;

jsonResponse([
    'status' => 'success',
    'data'   => $result,
    'engine' => $result['engine'] === 'gemini' ? 'Google Gemini (' . aiConfig()['gemini_model'] . ')' : 'ICMS-DPT-RRT rule-based engine',
]);

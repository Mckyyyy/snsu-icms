<?php
// actions/category_action.php - Administrators add, rename, archive or restore concern categories (never deleted)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();
requirePostWithCsrf('../admin/categories.php');

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['category_id'] ?? 0);
$back   = '../admin/categories.php';

function categoryInput(): array {
    $name = trim(preg_replace('/\s+/', ' ', $_POST['category_name'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        setFlash('error', 'The category name must be 2 to 100 characters.');
        header('Location: ../admin/categories.php');
        exit;
    }
    if (mb_strlen($desc) > 500) {
        setFlash('error', 'The description must be 500 characters or fewer.');
        header('Location: ../admin/categories.php');
        exit;
    }
    return [$name, $desc];
}

function nameTaken(PDO $pdo, string $name, int $exceptId = 0): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(:n) AND category_id <> :id");
    $s->execute(['n' => $name, 'id' => $exceptId]);
    return (int)$s->fetchColumn() > 0;
}

if ($action === 'add') {
    [$name, $desc] = categoryInput();
    if (nameTaken($pdo, $name)) {
        setFlash('error', "A category named \"{$name}\" already exists.");
    } else {
        $pdo->prepare("INSERT INTO categories (category_name, description, is_active) VALUES (:n, :d, 1)")
            ->execute(['n' => $name, 'd' => $desc !== '' ? $desc : null]);
        logAdminActivity($pdo, (int)$user['id'], 'category_created', 'category', (int)$pdo->lastInsertId(), $name);
        setFlash('success', "Category \"{$name}\" added. Students can choose it right away.");
    }
    header("Location: {$back}");
    exit;
}

// The remaining actions work on an existing category
$c = $pdo->prepare("SELECT * FROM categories WHERE category_id = :id");
$c->execute(['id' => $id]);
$category = $c->fetch();
if (!$category) {
    setFlash('error', 'Category not found.');
    header("Location: {$back}");
    exit;
}

if ($action === 'update') {
    [$name, $desc] = categoryInput();
    if (nameTaken($pdo, $name, $id)) {
        setFlash('error', "A category named \"{$name}\" already exists.");
    } else {
        $pdo->prepare("UPDATE categories SET category_name = :n, description = :d WHERE category_id = :id")
            ->execute(['n' => $name, 'd' => $desc !== '' ? $desc : null, 'id' => $id]);
        $detail = $name === $category['category_name'] ? $name : "{$category['category_name']} → {$name}";
        logAdminActivity($pdo, (int)$user['id'], 'category_updated', 'category', $id, $detail);
        setFlash('success', "Category \"{$name}\" saved.");
    }
} elseif ($action === 'archive' || $action === 'toggle' && (int)$category['is_active'] === 1) {
    // Archived categories stay on existing concerns and in reports; students just can't pick them
    if ((int)$pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn() <= 1 && (int)$category['is_active'] === 1) {
        setFlash('error', 'At least one category must stay active so students can submit concerns.');
        header("Location: {$back}");
        exit;
    }
    $pdo->prepare("UPDATE categories SET is_active = 0, archived_at = NOW(), archived_by = :a WHERE category_id = :id")
        ->execute(['a' => $user['id'], 'id' => $id]);
    logAdminActivity($pdo, (int)$user['id'], 'category_archived', 'category', $id, $category['category_name']);
    setFlash('success', "\"{$category['category_name']}\" was archived. Existing concerns keep it; restore it from the Archived tab.");
} elseif ($action === 'restore' || $action === 'toggle') {
    $back .= '?tab=archived';
    $pdo->prepare("UPDATE categories SET is_active = 1, archived_at = NULL, archived_by = NULL WHERE category_id = :id")->execute(['id' => $id]);
    logAdminActivity($pdo, (int)$user['id'], 'category_restored', 'category', $id, $category['category_name']);
    setFlash('success', "\"{$category['category_name']}\" is available again.");
} elseif ($action === 'delete') {
    setFlash('warning', 'Records are not deleted. Archive the category instead.');
}

header("Location: {$back}");
exit;

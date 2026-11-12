<?php
// actions/masterlist_action.php - Student masterlist: CSV import, single entries, delete, template download
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$admin = getCurrentUser();
$back  = '../admin/masterlist.php';

// Template download (GET, read-only)
if (($_GET['action'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_masterlist_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['student_no', 'name', 'email', 'college', 'program']);
    fputcsv($out, ['SNSU-2025-00001', 'Juan P. Dela Cruz', 'juan@example.com', 'College of Computing & Information Sciences', 'BS Information Technology']);
    fputcsv($out, ['SNSU-2025-00002', 'Maria Santos', '', 'College of Engineering', 'BS Civil Engineering']);
    fclose($out);
    exit;
}

requirePostWithCsrf($back);
$action = $_POST['action'] ?? '';

$upsert = $pdo->prepare("
    INSERT INTO student_masterlist (student_no, name, email, department, program, imported_by)
    VALUES (:no, :name, :email, :dept, :prog, :a)
    ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email),
        department = VALUES(department), program = VALUES(program), imported_by = VALUES(imported_by), imported_at = NOW(),
        archived_at = NULL, archived_by = NULL
");

/** Report how many Pending registrations an import or new entry approved. */
function approvedNote(array $approved): string {
    if (!$approved) {
        return '';
    }
    $n = count($approved);
    return " {$n} pending registration" . ($n === 1 ? ' was' : 's were') . ' approved automatically.';
}

if ($action === 'import') {
    $file = $_FILES['csv_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please choose a CSV file to upload.');
        header("Location: {$back}");
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        setFlash('error', 'The file is larger than 2 MB. Split it into smaller files.');
        header("Location: {$back}");
        exit;
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        setFlash('error', 'Only .csv files are accepted. In Excel, use File > Save As > CSV UTF-8.');
        header("Location: {$back}");
        exit;
    }

    $parsed = parseMasterlistCsv($file['tmp_name']);
    if (!$parsed['rows']) {
        setFlash('error', 'Nothing was imported. ' . implode(' ', array_slice($parsed['errors'], 0, 3)));
        header("Location: {$back}");
        exit;
    }

    $existing = array_flip($pdo->query("SELECT student_no FROM student_masterlist")->fetchAll(PDO::FETCH_COLUMN));
    $added = $updated = 0;
    $pdo->beginTransaction();
    foreach ($parsed['rows'] as $r) {
        $upsert->execute(['no' => $r['student_no'], 'name' => $r['name'], 'email' => $r['email'] ?: null,
                          'dept' => $r['department'] ?: null, 'prog' => $r['program'] ?: null, 'a' => $admin['id']]);
        isset($existing[$r['student_no']]) ? $updated++ : $added++;
    }
    $pdo->commit();

    $approved = approveMatchingPendingStudents($pdo, (int)$admin['id']);
    logAdminActivity($pdo, (int)$admin['id'], 'masterlist_imported', 'masterlist', null,
        "{$file['name']}: {$added} added, {$updated} updated, " . count($parsed['errors']) . ' skipped' . (count($approved) ? ', ' . count($approved) . ' approved' : ''));

    $msg = "Imported {$file['name']}: {$added} added, {$updated} updated." . approvedNote($approved);
    if ($parsed['errors']) {
        $shown = array_slice($parsed['errors'], 0, 3);
        $more  = count($parsed['errors']) - count($shown);
        setFlash('warning', $msg . ' Skipped: ' . implode(' ', $shown) . ($more > 0 ? " (+{$more} more)" : ''));
    } else {
        setFlash('success', $msg);
    }
} elseif ($action === 'add') {
    $r = [
        'student_no' => strtoupper(trim($_POST['student_no'] ?? '')),
        'name'       => trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? '')),
        'email'      => mb_strtolower(trim($_POST['email'] ?? '')),
        'department' => trim($_POST['department'] ?? ''),
        'program'    => trim($_POST['program'] ?? ''),
    ];
    if (!preg_match('/^[A-Z0-9-]{4,50}$/', $r['student_no']) || mb_strlen($r['name']) < 2 || mb_strlen($r['name']) > 150) {
        setFlash('error', 'Enter a valid student number (letters, numbers, dashes) and the full name.');
        header("Location: {$back}");
        exit;
    }
    if ($r['email'] !== '' && !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'The email address is not valid.');
        header("Location: {$back}");
        exit;
    }
    $upsert->execute(['no' => $r['student_no'], 'name' => $r['name'], 'email' => $r['email'] ?: null,
                      'dept' => mb_substr($r['department'], 0, 150) ?: null, 'prog' => mb_substr($r['program'], 0, 150) ?: null, 'a' => $admin['id']]);
    $approved = approveMatchingPendingStudents($pdo, (int)$admin['id']);
    logAdminActivity($pdo, (int)$admin['id'], 'masterlist_entry_added', 'masterlist', null, "{$r['name']} ({$r['student_no']})");
    setFlash('success', "{$r['name']} ({$r['student_no']}) is on the masterlist." . approvedNote($approved));
} elseif ($action === 'update') {
    // Fix a record (typo in the name, wrong email / program, ...)
    $id = (int)($_POST['masterlist_id'] ?? 0);
    $row = $pdo->prepare("
        SELECT m.*, s.student_id, s.email AS account_email
        FROM student_masterlist m LEFT JOIN students s ON s.student_no = m.student_no
        WHERE m.masterlist_id = :id
    ");
    $row->execute(['id' => $id]);
    $entry = $row->fetch();
    if (!$entry) {
        setFlash('error', 'That masterlist entry no longer exists.');
        header("Location: {$back}");
        exit;
    }
    $registered = $entry['student_id'] !== null;
    $r = [
        // The student number links the record to an account, so it is locked once the student has registered
        'student_no' => $registered ? $entry['student_no'] : strtoupper(trim($_POST['student_no'] ?? '')),
        'name'       => trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? '')),
        'email'      => mb_strtolower(trim($_POST['email'] ?? '')),
        'department' => mb_substr(trim($_POST['department'] ?? ''), 0, 150),
        'program'    => mb_substr(trim($_POST['program'] ?? ''), 0, 150),
    ];
    $fail = function (string $msg) use ($back) {
        setFlash('error', $msg);
        header("Location: {$back}");
        exit;
    };
    if (!preg_match('/^[A-Z0-9-]{4,50}$/', $r['student_no']) || mb_strlen($r['name']) < 2 || mb_strlen($r['name']) > 150) {
        $fail('Enter a valid student number (letters, numbers, dashes) and the full name.');
    }
    if ($r['email'] !== '' && (!filter_var($r['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($r['email']) > 150)) {
        $fail('The email address is not valid.');
    }
    if ($r['student_no'] !== $entry['student_no']) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM student_masterlist WHERE student_no = :n AND masterlist_id <> :id");
        $dup->execute(['n' => $r['student_no'], 'id' => $id]);
        if ((int)$dup->fetchColumn() > 0) {
            $fail("{$r['student_no']} is already on the masterlist.");
        }
    }

    // Optionally copy the corrections to the student's account
    $syncAccount = $registered && !empty($_POST['sync_account']);
    if ($syncAccount && $r['email'] !== '' && strcasecmp($r['email'], (string)$entry['account_email']) !== 0 && emailIsTaken($pdo, $r['email'])) {
        $fail("{$r['email']} is already used by another account, so the student's account was not changed. Nothing was saved.");
    }

    $pdo->beginTransaction();
    $pdo->prepare("
        UPDATE student_masterlist
        SET student_no = :no, name = :name, email = :email, department = :dept, program = :prog
        WHERE masterlist_id = :id
    ")->execute(['no' => $r['student_no'], 'name' => $r['name'], 'email' => $r['email'] ?: null,
                 'dept' => $r['department'] ?: null, 'prog' => $r['program'] ?: null, 'id' => $id]);
    if ($syncAccount) {
        $dept = null;
        foreach (SNSU_DEPARTMENTS as $d) {
            if (strcasecmp($r['department'], $d) === 0) {
                $dept = $d;
            }
        }
        $pdo->prepare("
            UPDATE students
            SET name = :name,
                email = COALESCE(:email, email),
                department = COALESCE(:dept, department),
                program = COALESCE(:prog, program)
            WHERE student_id = :sid
        ")->execute(['name' => displayName($r['name']), 'email' => $r['email'] ?: null, 'dept' => $dept,
                     'prog' => $r['program'] ?: null, 'sid' => $entry['student_id']]);
    }
    $pdo->commit();

    $approved = approveMatchingPendingStudents($pdo, (int)$admin['id']);
    $changed = [];
    foreach (['student_no' => 'student number', 'name' => 'name', 'email' => 'email', 'department' => 'college', 'program' => 'program'] as $k => $label) {
        if ((string)$entry[$k] !== $r[$k]) {
            $changed[] = $label;
        }
    }
    logAdminActivity($pdo, (int)$admin['id'], 'masterlist_entry_updated', 'masterlist', $id,
        "{$r['name']} ({$r['student_no']}): " . ($changed ? implode(', ', $changed) : 'no changes') . ($syncAccount ? '; account updated too' : ''));
    setFlash('success', ($changed ? "Updated {$r['student_no']} (" . implode(', ', $changed) . ')' : "No changes to {$r['student_no']}")
        . ($syncAccount ? ". The student's account was updated too." : '.') . approvedNote($approved));
} elseif (in_array($action, ['archive', 'delete', 'restore'], true)) {
    // Masterlist entries are archived, never deleted. Archived entries cannot be found or claimed
    // on the Register page; existing accounts are not affected.
    $id = (int)($_POST['masterlist_id'] ?? 0);
    $row = $pdo->prepare("SELECT * FROM student_masterlist WHERE masterlist_id = :id");
    $row->execute(['id' => $id]);
    if ($entry = $row->fetch()) {
        if ($action === 'restore') {
            $pdo->prepare("UPDATE student_masterlist SET archived_at = NULL, archived_by = NULL WHERE masterlist_id = :id")->execute(['id' => $id]);
            logAdminActivity($pdo, (int)$admin['id'], 'masterlist_entry_restored', 'masterlist', $id, "{$entry['name']} ({$entry['student_no']})");
            setFlash('success', "{$entry['student_no']} is back on the masterlist.");
        } else {
            $pdo->prepare("UPDATE student_masterlist SET archived_at = NOW(), archived_by = :a WHERE masterlist_id = :id")->execute(['a' => $admin['id'], 'id' => $id]);
            logAdminActivity($pdo, (int)$admin['id'], 'masterlist_entry_archived', 'masterlist', $id, "{$entry['name']} ({$entry['student_no']})");
            setFlash('success', "{$entry['student_no']} was archived. It can no longer be claimed on the Register page; existing accounts are not affected.");
        }
    }
    if (($_POST['return_tab'] ?? '') === 'archived') {
        $back .= '?show=archived';
    }
} else {
    setFlash('error', 'Unknown action.');
}

header("Location: {$back}");
exit;

<?php
// includes/registration.php - Verified student registration (masterlist + administrator approval)
//
// A self-registered student is approved automatically when their student number is on the
// Registrar's masterlist (student_masterlist) and the name matches. Everyone else is saved
// as 'Pending' until an administrator approves the account on the Users page.

/** Name words used for matching: lower-case letters only, without suffixes such as Jr. or III. */
function nameTokens(string $name): array {
    $words = preg_split('/[^\p{L}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
    $skip = ['jr' => 1, 'sr' => 1, 'ii' => 1, 'iii' => 1, 'iv' => 1];
    return array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 2 && !isset($skip[$w]))));
}

/**
 * Does a typed name match the masterlist name? Word order and commas do not matter
 * ("Dela Cruz, Juan P." = "Juan Dela Cruz"); at least two words must match
 * (or every word, when the masterlist name has only one).
 */
function masterlistNameMatches(string $masterlistName, string $typedName): bool {
    $list  = nameTokens($masterlistName);
    $typed = nameTokens($typedName);
    if (!$list || !$typed) {
        return false;
    }
    return count(array_intersect($list, $typed)) >= min(2, count($list));
}

/** Masterlist names may be written "Last, First M."; show them as "First M. Last". */
function displayName(string $name): string {
    $parts = array_values(array_filter(array_map("trim", explode(",", $name, 2)), "strlen"));
    return count($parts) === 2 ? $parts[1] . " " . $parts[0] : trim($name);
}

function findMasterlistEntry(PDO $pdo, string $studentNo): ?array {
    $s = $pdo->prepare("SELECT * FROM student_masterlist WHERE student_no = :n AND archived_at IS NULL");
    $s->execute(['n' => strtoupper(trim($studentNo))]);
    return $s->fetch() ?: null;
}

/**
 * Decide what happens to a new self-registration.
 * @return array{verified: bool, note: string}
 */
function checkRegistrationAgainstMasterlist(PDO $pdo, string $studentNo, string $name, string $email): array {
    $entry = findMasterlistEntry($pdo, $studentNo);
    if (!$entry) {
        return ['verified' => false, 'note' => 'Student number is not on the masterlist.'];
    }
    $emailMatches = !empty($entry['email']) && strcasecmp(trim($entry['email']), trim($email)) === 0;
    if (masterlistNameMatches($entry['name'], $name) || $emailMatches) {
        return ['verified' => true, 'note' => 'Matched the masterlist.'];
    }
    return ['verified' => false, 'note' => "Student number is on the masterlist under a different name ({$entry['name']})."];
}

/**
 * Approve every Pending registration that now matches the masterlist (run after an import).
 * @return array<int, array> the students that were approved
 */
function approveMatchingPendingStudents(PDO $pdo, ?int $adminId): array {
    $rows = $pdo->query("
        SELECT s.student_id, s.name, s.email, s.student_no, m.name AS list_name, m.email AS list_email
        FROM students s JOIN student_masterlist m ON m.student_no = s.student_no AND m.archived_at IS NULL
        WHERE s.status = 'Pending'
    ")->fetchAll();

    $approved = [];
    $up = $pdo->prepare("
        UPDATE students SET status = 'Active', masterlist_verified = 1, registration_note = 'Matched the masterlist after import.',
               approved_by = :a, approved_at = NOW()
        WHERE student_id = :id AND status = 'Pending'
    ");
    foreach ($rows as $r) {
        $emailMatches = !empty($r['list_email']) && strcasecmp($r['list_email'], $r['email']) === 0;
        if (!masterlistNameMatches($r['list_name'], $r['name']) && !$emailMatches) {
            continue;
        }
        $up->execute(['a' => $adminId, 'id' => $r['student_id']]);
        if ($up->rowCount()) {
            sendRegistrationApprovedEmail($pdo, $r);
            $approved[] = $r;
        }
    }
    return $approved;
}

function sendRegistrationApprovedEmail(PDO $pdo, array $student): void {
    try {
        queueEmail($pdo, $student['email'], $student['name'], 'Your ICMS account is approved', [
            "Hello {$student['name']},",
            "Your student account ({$student['student_no']}) has been approved. You can now sign in and submit concerns.",
        ], appUrl('index.php'), 'Sign in');
    } catch (Throwable $e) {
        error_log('[ICMS] Approval email failed: ' . $e->getMessage());
    }
}

/** Column aliases accepted in a masterlist CSV header (lower-case, spaces/underscores ignored). */
const MASTERLIST_COLUMNS = [
    'student_no' => ['studentno', 'studentnumber', 'studentid', 'idno', 'idnumber', 'id'],
    'name'       => ['name', 'fullname', 'studentname'],
    'email'      => ['email', 'emailaddress'],
    'department' => ['department', 'college', 'collegedepartment'],
    'program'    => ['program', 'course', 'degreeprogram', 'degree'],
];

/**
 * Parse a masterlist CSV. The first row must be a header naming at least the student number
 * and name columns. Returns valid rows plus the problems found (line numbers are 1-based).
 * @return array{rows: array<int, array>, errors: string[]}
 */
function parseMasterlistCsv(string $path): array {
    $fh = fopen($path, 'r');
    if (!$fh) {
        return ['rows' => [], 'errors' => ['The file could not be read.']];
    }
    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        return ['rows' => [], 'errors' => ['The file is empty.']];
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]); // Excel UTF-8 BOM
    $map = [];
    foreach ($header as $i => $h) {
        $key = preg_replace('/[^a-z]/', '', strtolower((string)$h));
        foreach (MASTERLIST_COLUMNS as $field => $aliases) {
            if (!isset($map[$field]) && in_array($key, $aliases, true)) {
                $map[$field] = $i;
            }
        }
    }
    if (!isset($map['student_no'], $map['name'])) {
        fclose($fh);
        return ['rows' => [], 'errors' => ['The first row must be a header with at least "student_no" and "name" columns. Download the template for the exact format.']];
    }

    $rows = [];
    $errors = [];
    $seen = [];
    $line = 1;
    while (($cols = fgetcsv($fh)) !== false) {
        $line++;
        if (count(array_filter($cols, fn($c) => trim((string)$c) !== '')) === 0) {
            continue; // blank line
        }
        $get = fn($f) => isset($map[$f]) ? trim((string)($cols[$map[$f]] ?? '')) : '';
        $row = [
            'student_no' => strtoupper($get('student_no')),
            'name'       => preg_replace('/\s+/', ' ', $get('name')),
            'email'      => mb_strtolower($get('email')),
            'department' => $get('department'),
            'program'    => $get('program'),
        ];
        if (!preg_match('/^[A-Z0-9-]{4,50}$/', $row['student_no'])) {
            $errors[] = "Line {$line}: invalid student number \"{$row['student_no']}\".";
            continue;
        }
        if (mb_strlen($row['name']) < 2 || mb_strlen($row['name']) > 150) {
            $errors[] = "Line {$line}: missing or too long name.";
            continue;
        }
        if ($row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $row['email'] = ''; // keep the student, drop the bad email
        }
        if (isset($seen[$row['student_no']])) {
            $errors[] = "Line {$line}: {$row['student_no']} appears more than once; the later row was used.";
        }
        $seen[$row['student_no']] = true;
        $rows[$row['student_no']] = array_map(fn($v) => mb_substr($v, 0, 150), $row);
    }
    fclose($fh);
    return ['rows' => array_values($rows), 'errors' => $errors];
}

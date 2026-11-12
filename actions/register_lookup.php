<?php
// actions/register_lookup.php - Public live search for register.php's Full Name field.
//   GET ?q=kris men  ->  { status, matches: [{ ref, name, display_name, program, department, has_account, state, student_no, email, needs_email }] }
//
// Searches the Registrar's masterlist by the start of each word ("kris men" finds "Kristine Joy Mendoza").
// This page is public, so it never reveals a full student number, email or any password. Each match carries
// an opaque, session-bound `ref`:
//   - has an account   -> password_reset_action.php (action=request, account_ref) emails a reset link to the email on file
//   - no account yet   -> register_claim.php (claim_ref) creates the account once the student number is confirmed
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Cache-Control: no-store');

if (isLoggedIn()) {
    jsonResponse(['status' => 'ok', 'matches' => []]);
}

// Only answer requests from our own register page (same-session CSRF token in a header)
if (!isValidCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    jsonResponse(['status' => 'error', 'message' => 'Please reload the page.'], 403);
}

const REGISTER_SEARCH_MIN_LETTERS = 2;
const REGISTER_SEARCH_RESULTS     = 8;
// Session rate limit: plenty for typing a name, too few for paging through the whole student list
const REGISTER_LOOKUP_MAX    = 120;
const REGISTER_LOOKUP_WINDOW = 600; // seconds

$q = trim((string)($_GET['q'] ?? ''));
$words = array_slice(preg_split('/[^\p{L}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY), 0, 5);
if (mb_strlen(implode('', $words)) < REGISTER_SEARCH_MIN_LETTERS || mb_strlen($q) > 150) {
    jsonResponse(['status' => 'ok', 'matches' => []]);
}

$now = time();
$hits = array_values(array_filter($_SESSION['register_lookup_hits'] ?? [], fn($t) => $t > $now - REGISTER_LOOKUP_WINDOW));
if (count($hits) >= REGISTER_LOOKUP_MAX) {
    jsonResponse(['status' => 'error', 'message' => 'Too many searches. Please wait a few minutes.'], 429);
}
$hits[] = $now;
$_SESSION['register_lookup_hits'] = $hits;

/** "SNSU-2022-04189" -> "SNSU-2022-•••••" (only the part every student shares stays visible) */
function maskStudentNo(string $no): string {
    $cut = mb_strrpos($no, '-');
    $keep = $cut !== false ? $cut + 1 : min(3, mb_strlen($no));
    return mb_substr($no, 0, $keep) . str_repeat('•', mb_strlen($no) - $keep);
}

/** "juan.delacruz@student.com" -> "j•••@student.com" */
function maskEmail(string $email): string {
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($user, 0, 1) . '•••' . ($domain !== '' ? '@' . $domain : '');
}

// Every typed word must start a word of the name (the name may be "Last, First M." or "First Last")
$where = [];
$params = [];
foreach ($words as $i => $w) {
    $where[] = "(m.name LIKE :a{$i} OR m.name LIKE :b{$i})";
    $params["a{$i}"] = $w . '%';
    $params["b{$i}"] = '% ' . $w . '%';
}
$params['first'] = $words[0] . '%';
$stmt = $pdo->prepare("
    SELECT m.masterlist_id, m.student_no, m.name, m.email, m.department, m.program,
           s.student_id, s.status, s.email AS account_email
    FROM student_masterlist m
    LEFT JOIN students s ON s.student_no = m.student_no
    WHERE m.archived_at IS NULL AND " . implode(' AND ', $where) . "
    ORDER BY (m.name LIKE :first) DESC, m.name ASC
    LIMIT " . REGISTER_SEARCH_RESULTS);
$stmt->execute($params);

$matches = [];
$accountRefs = [];
$claimRefs = [];
foreach ($stmt->fetchAll() as $r) {
    $ref = bin2hex(random_bytes(12));
    $hasAccount = $r['student_id'] !== null;
    if ($hasAccount) {
        $accountRefs[$ref] = (int)$r['student_id'];
    } else {
        $claimRefs[$ref] = (int)$r['masterlist_id'];
    }
    $email = $hasAccount ? $r['account_email'] : $r['email'];
    $matches[] = [
        'ref'          => $ref,
        'name'         => $r['name'],
        'display_name' => displayName($r['name']),
        'program'      => $r['program'],
        'department'   => $r['department'],
        'has_account'  => $hasAccount,
        'state'        => !$hasAccount ? 'No account yet'
                          : ($r['status'] === 'Pending' ? 'Waiting for approval' : ($r['status'] === 'Inactive' ? 'Deactivated' : 'Has an account')),
        'student_no'   => maskStudentNo((string)$r['student_no']),
        'email'        => $email ? maskEmail((string)$email) : null,
        // Claim form asks for an email only when the Registrar's record has none
        'needs_email'  => !$hasAccount && trim((string)$r['email']) === '',
    ];
}
// Refs are valid only for this browser session (newest 40 of each kept)
$_SESSION['register_lookup_refs'] = array_slice(($_SESSION['register_lookup_refs'] ?? []) + $accountRefs, -40, null, true);
$_SESSION['register_claim_refs']  = array_slice(($_SESSION['register_claim_refs'] ?? []) + $claimRefs, -40, null, true);

jsonResponse(['status' => 'ok', 'matches' => $matches, 'email_reset' => mailCanDeliver()]);

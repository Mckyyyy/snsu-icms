<?php
/**
 * test_suite.php - End-to-End Automated Test Suite for ICMS-DPT-RRT
 * Surigao del Norte State University (SNSU) Capstone Project
 *
 * Full Feature Matrix Tested:
 * 1. Database Connection, Health & Table Count
 * 2. PHP Syntax Integrity Across Entire Project
 * 3. AI Dynamic Prioritization Engine (DPT-RRT Scoring, SLA Assignment, RRT Alert Flag)
 * 4. DPT-RRT Live API Endpoint (/api/ai_prioritize.php)
 * 5. Student Authentication & Session Protection
 * 6. Admin Authentication & Role-Based Authorization
 * 7. Student Concern Submission WITH Photographic Evidence (JPEG Image Upload)
 * 8. Student Concern Submission WITH Photographic Evidence (PNG Image Upload)
 * 9. Student Concern Submission WITH Document Attachment (PDF Document Upload)
 * 10. Student Concern Submission WITHOUT Attachment
 * 11. Security & MIME Validation: Rejection of Disallowed Executables / Scripts
 * 12. Client-Side Image Live Preview Markup & Handlers
 * 13. Image Thumbnail & Photographic Viewer in Student Concern Details
 * 14. Image Thumbnail & Photographic Viewer in Admin Concern Details
 * 15. Admin Response, Department Endorsement & Dynamic Status Lifecycle
 * 16. Student Feedback & 5-Star Rating System
 * 17. User Management: Add Student & Admin, Toggle Status, Delete Account
 * 18. Institutional Announcements: Create Urgent Notice, View on Dashboard, Delete
 * 19. Reports & Analytics Engine: Filter by Category, SLA Compliance & Resolution Rates
 * 20. Zero Raw Browser alert(), Zero Emojis (SVG Vector Icons Only), Responsive Modals & Upper-Right Toast
 * 21. Authentication Logout & Session Invalidation
 * 22. CSRF Protection (requests without a token are rejected)
 * 23. Student Self-Registration (policy, duplicates, success, login)
 * 24. Login Lockout after repeated failures
 * 25. Forgot Password -> Admin Temporary Password -> Forced Password Change
 * 26. Resolution Satisfaction Rating (owner-only, once, resolved-only)
 * 27. SLA Tracking, Audit Trail & Notifications
 * 28. Server-side Pagination/Filters & CSV Export
 * 29. Reopen Resolved Ticket (reason required, rating cleared, fresh SLA)
 * 30. Email Notifications Queue (receipt, status change, RRT, reopen)
 * 31. Ticket Assignment (assign, notify assignee, filter, activity log)
 * 32. Automatic SLA Escalation (breach alert sent once)
 * 33. Idle Session Timeout & keep-alive endpoint
 * 34. Withdraw Concern (Pending only, reason required, record kept, admins told)
 * 35. Edit Concern (owner only, before any admin response, AI re-score, SLA kept)
 * 36. Category Management (add, rename, hide from Submit form, delete rules)
 * 37. Feedback Replies (admin reply, student sees it and is notified)
 * 38. Duplicate Detection (similar open concerns, privacy of other students, admin panel)
 * 39. Verified Registration (masterlist auto-approval, pending approval, approve / reject, CSV import)
 * 40. Anonymous Submission (identity hidden from admin pages, search, export and alerts)
 * 41. ISO 25010 Results (no scores until entered, validation, saved and shown)
 * 42. Masterlist Profile Claim (search, details pop-up, student number verified before password)
 * 43. Page Smoke Test (every admin, student and public page loads without PHP errors)
 * 44. Masterlist Edit (fix details, student number locked once registered, optional account update)
 * 45. Email Routing (real addresses delivered, demo addresses redirected)
 * 46. Archive Instead of Delete (tickets, masterlist, rejected registrations, ratings kept)
 * 47. AI Engine: Tagalog & Cebuano keywords, negation and harmless phrases
 * 48. Expected Fix Time (days shown to students, time left, typical time of similar concerns)
 * 49. Recovery Code (shown once at registration, resets password, single use, lockout, profile regenerate)
 * 50. Gemini AI Prioritization (structured answer, same thresholds, rule-based fallback)
 *
 * Usage:  php test_suite.php [baseUrl]
 *         default baseUrl = http://localhost/TrackingSystem (XAMPP)
 * All records created by the suite are removed at the end.
 */

// Command line only: opened in a browser it would create and delete records in the live database
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

$baseUrl = rtrim($argv[1] ?? getenv('ICMS_BASE_URL') ?: 'http://localhost/TrackingSystem', '/');

// Snapshot so everything the suite creates can be cleaned up afterwards
$startMaxConcernId = (int)$pdo->query("SELECT COALESCE(MAX(concern_id), 0) FROM concerns")->fetchColumn();
$startMaxStudentId = (int)$pdo->query("SELECT COALESCE(MAX(student_id), 0) FROM students")->fetchColumn();
$startMaxFeedbackId = (int)$pdo->query("SELECT COALESCE(MAX(feedback_id), 0) FROM feedback")->fetchColumn();
$startMaxNotifId   = (int)$pdo->query("SELECT COALESCE(MAX(notification_id), 0) FROM notifications")->fetchColumn();
$startMaxAuditId   = (int)$pdo->query("SELECT COALESCE(MAX(log_id), 0) FROM concern_audit_logs")->fetchColumn();
$startMaxResetId   = (int)$pdo->query("SELECT COALESCE(MAX(request_id), 0) FROM password_reset_requests")->fetchColumn();
$startMaxEmailId   = (int)$pdo->query("SELECT COALESCE(MAX(email_id), 0) FROM email_outbox")->fetchColumn();
$startMaxTokenId   = (int)$pdo->query("SELECT COALESCE(MAX(token_id), 0) FROM password_reset_tokens")->fetchColumn();
$startMaxActivityId = (int)$pdo->query("SELECT COALESCE(MAX(activity_id), 0) FROM admin_activity_logs")->fetchColumn();
// Never send real email while testing: the web app switches to log mode while this flag exists
$mailFlag = __DIR__ . '/storage/.mail_log_only';
@file_put_contents($mailFlag, 'test suite running ' . date('c'));
register_shutdown_function(fn() => @unlink($mailFlag));
// ...and never call the real AI model (quota, network): the rule-based engine answers while testing
$aiFlag = __DIR__ . '/storage/.ai_rules_only';
@file_put_contents($aiFlag, 'test suite running ' . date('c'));
register_shutdown_function(fn() => @unlink($aiFlag));
$startMaxCategoryId = (int)$pdo->query("SELECT COALESCE(MAX(category_id), 0) FROM categories")->fetchColumn();
$startMaxAnnouncementId = (int)$pdo->query("SELECT COALESCE(MAX(announcement_id), 0) FROM announcements")->fetchColumn();
$startMaxRejectionId = (int)$pdo->query("SELECT COALESCE(MAX(rejection_id), 0) FROM registration_rejections")->fetchColumn();
$passed = 0;
$failed = 0;
$testNum = 0;

function runTest($name, $fn) {
    global $passed, $failed, $testNum;
    $testNum++;
    echo "\n[" . str_pad($testNum, 2, '0', STR_PAD_LEFT) . "] Testing: {$name} ... ";
    try {
        $result = $fn();
        if ($result === true || (is_array($result) && $result['pass'])) {
            $msg = is_array($result) && !empty($result['msg']) ? " ({$result['msg']})" : "";
            echo "\033[32m[PASS]\033[0m{$msg}\n";
            $passed++;
        } else {
            $msg = is_array($result) && !empty($result['msg']) ? " - {$result['msg']}" : "";
            echo "\033[31m[FAIL]\033[0m{$msg}\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "\033[31m[ERROR]\033[0m - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

/**
 * Fetch the CSRF token bound to the session in $cookieFile.
 * Works logged out (login form field) and logged in (<meta name="csrf-token">).
 */
function getCsrfToken(string $cookieFile): ?string {
    global $baseUrl;
    foreach (['index.php', 'student/dashboard.php', 'admin/dashboard.php', 'change_password.php'] as $page) {
        $r = makeHttpRequest("{$baseUrl}/{$page}", 'GET', [], $cookieFile, false, false);
        if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['body'], $m)
            || preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m)) {
            return $m[1];
        }
    }
    return null;
}

/**
 * HTTP helper. POST requests automatically get a valid csrf_token for the
 * cookie-jar session unless $autoCsrf is false or one is already supplied.
 */
function makeHttpRequest($url, $method = 'GET', $data = [], $cookieFile = null, $isMultipart = false, $autoCsrf = true) {
    if ($method === 'POST' && $autoCsrf && !array_key_exists('csrf_token', $data)) {
        if (!$cookieFile) {
            $cookieFile = tempnam(sys_get_temp_dir(), 'cook_anon_');
        }
        $data['csrf_token'] = getCsrfToken($cookieFile);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    if ($cookieFile) {
        @curl_setopt($ch, CURLOPT_COOKIELIST, 'FLUSH');
    }

    unset($ch);

    return [
        'code'    => $httpCode,
        'headers' => $headers,
        'body'    => $body
    ];
}

echo "\n======================================================================\n";
echo "  ICMS-DPT-RRT COMPREHENSIVE AUTOMATED VERIFICATION TEST SUITE\n";
echo "  Institutional Concern Management System with Dynamic Prioritization\n";
echo "  Surigao del Norte State University (SNSU)\n";
echo "======================================================================\n";

// --- Test 1: Database & Environment ---
runTest("Database Connection & Schema Table Verification", function() use ($pdo) {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['administrators', 'announcements', 'categories', 'concerns', 'feedback', 'responses', 'statuses', 'students',
                 'schema_migrations', 'concern_audit_logs', 'notifications', 'login_attempts', 'password_reset_requests',
                 'email_outbox', 'password_reset_tokens', 'admin_activity_logs', 'student_masterlist'];
    $diff = array_diff($expected, $tables);
    if (empty($diff)) {
        return ['pass' => true, 'msg' => count($tables) . " tables verified in icms_dpt_rrt"];
    }
    return ['pass' => false, 'msg' => 'Missing tables: ' . implode(', ', $diff)];
});

// --- Test 2: PHP Syntax on all files ---
runTest("PHP Syntax Integrity Across Project", function() {
    $dir = new RecursiveDirectoryIterator(__DIR__);
    $ite = new RecursiveIteratorIterator($dir);
    $files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
    $errorCount = 0;
    foreach ($files as $file) {
        $filePath = $file[0];
        if (strpos($filePath, 'scratch') !== false) continue;
        $out = [];
        $ret = 0;
        exec(escapeshellarg(PHP_BINARY) . " -l " . escapeshellarg($filePath) . " 2>&1", $out, $ret);
        if ($ret !== 0) {
            $errorCount++;
        }
    }
    return ['pass' => $errorCount === 0, 'msg' => $errorCount === 0 ? "All PHP files clean" : "$errorCount files have syntax errors"];
});

// --- Test 3: DPT-RRT Urgency Calculation Algorithm ---
runTest("DPT-RRT Urgency Calculation (Critical Hazard vs Medium vs Low)", function() {
    $crit = calculateDynamicUrgency("Exposed live wires and sparking", "Water leaking near electrical junction box", "Facilities");
    if ($crit['priority'] !== 'Critical' || $crit['sla_hours'] !== 24 || $crit['is_rrt_alert'] !== 1 || $crit['score'] < 80) {
        return ['pass' => false, 'msg' => 'Critical hazard failed to trigger score >= 80, 24h SLA, or RRT Alert'];
    }

    $med = calculateDynamicUrgency("Slow WiFi connection in cafeteria study lounge", "The wireless internet disconnects intermittently", "General");
    if ($med['priority'] !== 'Medium' || $med['sla_hours'] !== 72) {
        return ['pass' => false, 'msg' => "Medium priority scored incorrectly: {$med['priority']}"];
    }

    $low = calculateDynamicUrgency("Suggestion for campus benches in gazebo", "Would be nice to have additional benches near gazebo", "General");
    if ($low['priority'] !== 'Low' || $low['sla_hours'] !== 120 || $low['is_rrt_alert'] !== 0) {
        return ['pass' => false, 'msg' => 'Low inquiry scored incorrectly'];
    }

    return ['pass' => true, 'msg' => "Critical: {$crit['score']}/100 (24h SLA, RRT=1), Medium: {$med['score']}/100 (72h SLA), Low: {$low['score']}/100 (120h SLA)"];
});

// --- Test 4: DPT-RRT Live API Endpoint ---
runTest("DPT-RRT Live REST API (/api/ai_prioritize.php)", function() use ($baseUrl) {
    $jar = tempnam(sys_get_temp_dir(), 'cook_api_');
    // Unauthenticated access must be refused
    $anon = makeHttpRequest("{$baseUrl}/api/ai_prioritize.php", 'POST', ['title' => 'fire'], $jar);
    if ($anon['code'] !== 401) {
        @unlink($jar);
        return ['pass' => false, 'msg' => "API allowed unauthenticated access (HTTP {$anon['code']})"];
    }
    makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', [
        'identifier' => 'juan.delacruz@student.com', 'password' => 'student123'
    ], $jar);
    $apiResp = makeHttpRequest("{$baseUrl}/api/ai_prioritize.php", 'POST', [
        'title' => 'fire hazard leak', 'description' => 'emergency immediate', 'category_id' => 1
    ], $jar);
    @unlink($jar);
    $apiJson = json_decode($apiResp['body'], true);
    if (!$apiJson || $apiJson['status'] !== 'success' || $apiJson['data']['priority'] !== 'Critical' || $apiJson['data']['is_rrt_alert'] !== 1) {
        return ['pass' => false, 'msg' => 'API endpoint failed to return Critical priority with RRT alert'];
    }
    return ['pass' => true, 'msg' => "API returned Critical priority, Score {$apiJson['data']['score']}/100, SLA {$apiJson['data']['sla_hours']}h"];
});

// Create temp cookie jars
$studentCookie = tempnam(sys_get_temp_dir(), 'cook_stud_');
$adminCookie   = tempnam(sys_get_temp_dir(), 'cook_adm_');

// --- Test 5: Student Authentication & Session Protection ---
runTest("Student Authentication (Valid & Invalid Credentials)", function() use ($baseUrl, $studentCookie) {
    // 1. Invalid login
    $invalidResp = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', [
        'identifier' => 'juan.delacruz@student.com',
        'password'   => 'wrongpassword123'
    ]);
    if (strpos($invalidResp['headers'], 'dashboard') !== false) {
        return ['pass' => false, 'msg' => 'Invalid credentials incorrectly succeeded'];
    }

    // 2. Valid student login
    $validResp = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', [
        'identifier' => 'juan.delacruz@student.com',
        'password'   => 'student123'
    ], $studentCookie);

    if (strpos($validResp['headers'], 'Location: ../student/dashboard.php') === false && $validResp['code'] !== 302) {
        return ['pass' => false, 'msg' => 'Valid student login did not redirect to student/dashboard.php'];
    }

    // 3. Access student dashboard with session
    $dashResp = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $studentCookie);
    if ($dashResp['code'] !== 200 || strpos($dashResp['body'], 'Juan Dela Cruz') === false) {
        return ['pass' => false, 'msg' => 'Student dashboard failed to load for authenticated student'];
    }

    return ['pass' => true, 'msg' => 'Authenticated as Juan Dela Cruz, dashboard loaded'];
});

// --- Test 6: Admin Authentication & Role Authorization ---
runTest("Admin Authentication (single form, role auto-detected) & Role Authorization", function() use ($baseUrl, $adminCookie, $studentCookie) {
    // 1. Valid Admin login
    $adminResp = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', [
        'identifier' => 'admin@school.edu',
        'password'   => 'admin123'
    ], $adminCookie);

    if (strpos($adminResp['headers'], 'Location: ../admin/dashboard.php') === false && $adminResp['code'] !== 302) {
        return ['pass' => false, 'msg' => 'Admin login did not redirect to admin/dashboard.php'];
    }

    // 2. Access admin dashboard
    $dashResp = makeHttpRequest("{$baseUrl}/admin/dashboard.php", 'GET', [], $adminCookie);
    if ($dashResp['code'] !== 200 || strpos($dashResp['body'], 'Institution overview') === false) {
        return ['pass' => false, 'msg' => "Admin dashboard failed to load (HTTP {$dashResp['code']})"];
    }

    // 3. Security: Student CANNOT access admin dashboard
    $studentAccessAdmin = makeHttpRequest("{$baseUrl}/admin/dashboard.php", 'GET', [], $studentCookie);
    if ($studentAccessAdmin['code'] === 200 && strpos($studentAccessAdmin['body'], 'Institution overview') !== false) {
        return ['pass' => false, 'msg' => 'Security failure: Student was able to access admin dashboard!'];
    }

    return ['pass' => true, 'msg' => 'Admin authenticated, student access to admin protected'];
});

// --- Test 7: Client-Side Live Image Preview Markup Check ---
runTest("Client-Side Live Image Preview UI Setup", function() use ($baseUrl, $studentCookie) {
    $submitPage = makeHttpRequest("{$baseUrl}/student/submit_concern.php", 'GET', [], $studentCookie);
    if ($submitPage['code'] !== 200) {
        return ['pass' => false, 'msg' => "submit_concern.php returned HTTP {$submitPage['code']}"];
    }
    $hasInput = strpos($submitPage['body'], 'id="attachment"') !== false;
    $hasPreviewWrap = strpos($submitPage['body'], 'id="imagePreviewWrap"') !== false;
    $hasPreviewImg = strpos($submitPage['body'], 'id="imagePreviewImg"') !== false;
    $hasRemoveBtn = strpos($submitPage['body'], 'id="removeImageBtn"') !== false;

    if ($hasInput && $hasPreviewWrap && $hasPreviewImg && $hasRemoveBtn) {
        return ['pass' => true, 'msg' => 'File input, preview container, image element, and remove button verified'];
    }
    return ['pass' => false, 'msg' => 'Missing one or more live image preview elements'];
});

// --- Test 8: Concern Submission WITH Image Upload (JPEG) ---
$jpegConcernId = null;
$jpegTicketId = null;
$jpegAttachmentPath = null;

runTest("Concern Submission WITH JPEG Image Upload", function() use ($baseUrl, $studentCookie, $pdo, &$jpegConcernId, &$jpegTicketId, &$jpegAttachmentPath) {
    $testImgPath = __DIR__ . '/scratch_hazard.jpg';
    $im = imagecreatetruecolor(240, 160);
    $bg = imagecolorallocate($im, 220, 38, 38);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, 240, 160, $bg);
    imagestring($im, 4, 20, 70, "EVIDENCE: SPARKING WIRE", $white);
    imagejpeg($im, $testImgPath, 90);
    $uploadedBytes = file_get_contents($testImgPath);

    $cFile = new CURLFile($testImgPath, 'image/jpeg', 'broken_wiring_defect.jpg');

    $postData = [
        'action'      => 'create',
        'category_id' => 1,
        'title'       => 'Urgent: Sparking main breaker and water leak in IT Lab',
        'description' => 'Water drip from ceiling falling onto main power panel causing sparks and smoke.',
        'attachment'  => $cFile
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', $postData, $studentCookie, true);
    @unlink($testImgPath);

    if ($resp['code'] !== 302 || !preg_match('/Location:\s*\.\.\/student\/concern_details\.php\?id=(\d+)/i', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Submission did not redirect to concern details'];
    }

    $jpegConcernId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT c.ticket_id, c.priority, c.attachment_path, a.original_name, a.mime_type, a.file_size, a.file_data
                           FROM concerns c LEFT JOIN concern_attachments a ON a.concern_id = c.concern_id WHERE c.concern_id = :id");
    $stmt->execute(['id' => $jpegConcernId]);
    $concern = $stmt->fetch();

    if (!$concern || $concern['file_data'] === null) {
        return ['pass' => false, 'msg' => 'Concern or attachment binary not saved in database'];
    }
    if ($concern['file_data'] !== $uploadedBytes || (int)$concern['file_size'] !== strlen($uploadedBytes)
        || $concern['mime_type'] !== 'image/jpeg' || $concern['original_name'] !== 'broken_wiring_defect.jpg') {
        return ['pass' => false, 'msg' => 'Stored image bytes / MIME / size / name do not match the upload'];
    }
    if ($concern['attachment_path'] !== null) {
        return ['pass' => false, 'msg' => 'File path still written; attachments must live in the database only'];
    }

    $jpegTicketId = $concern['ticket_id'];
    $jpegAttachmentPath = $concern['original_name'];
    return ['pass' => true, 'msg' => "Ticket {$jpegTicketId}, image stored in MySQL (" . strlen($concern['file_data']) . " bytes, image/jpeg), Priority: {$concern['priority']}"];
});

// --- Test 9: Concern Submission WITH Image Upload (PNG) ---
$pngConcernId = null;

runTest("Concern Submission WITH PNG Image Upload", function() use ($baseUrl, $studentCookie, $pdo, &$pngConcernId) {
    $testPngPath = __DIR__ . '/scratch_broken_chair.png';
    $im = imagecreatetruecolor(200, 150);
    $bg = imagecolorallocate($im, 37, 99, 235);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, 200, 150, $bg);
    imagestring($im, 4, 15, 65, "EVIDENCE: BROKEN CHAIR", $white);
    imagepng($im, $testPngPath);
    $uploadedBytes = file_get_contents($testPngPath);

    $cFile = new CURLFile($testPngPath, 'image/png', 'broken_chair.png');

    $postData = [
        'action'      => 'create',
        'category_id' => 1,
        'title'       => 'Broken student study armchair in Room 102',
        'description' => 'The wooden armrest is splintered and metal bracket has snapped.',
        'attachment'  => $cFile
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', $postData, $studentCookie, true);
    @unlink($testPngPath);

    if ($resp['code'] !== 302 || !preg_match('/Location:\s*\.\.\/student\/concern_details\.php\?id=(\d+)/i', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Submission did not redirect to concern details'];
    }

    $pngConcernId = (int)$m[1];
    $stmt = $pdo->prepare("SELECT mime_type, file_data FROM concern_attachments WHERE concern_id = :id");
    $stmt->execute(['id' => $pngConcernId]);
    $row = $stmt->fetch();

    if (!$row || $row['mime_type'] !== 'image/png' || $row['file_data'] !== $uploadedBytes) {
        return ['pass' => false, 'msg' => 'PNG image not stored in the database correctly'];
    }

    return ['pass' => true, 'msg' => 'PNG stored in MySQL (' . strlen($row['file_data']) . ' bytes, image/png)'];
});

// --- Test 10: Concern Submission WITH Document (PDF) ---
runTest("Concern Submission WITH PDF Document Upload", function() use ($baseUrl, $studentCookie, $pdo) {
    $testPdfPath = __DIR__ . '/scratch_doc.pdf';
    file_put_contents($testPdfPath, "%PDF-1.4\n1 0 obj\n<< /Title (Test Document) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");

    $cFile = new CURLFile($testPdfPath, 'application/pdf', 'enrollment_proof.pdf');
    $postData = [
        'action'      => 'create',
        'category_id' => 3, // Enrollment
        'title'       => 'Grade evaluation request for graduation clearance',
        'description' => 'Official evaluation sheet attached in PDF format for registrar review.',
        'attachment'  => $cFile
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', $postData, $studentCookie, true);
    @unlink($testPdfPath);

    if ($resp['code'] !== 302 || !preg_match('/Location:\s*\.\.\/student\/concern_details\.php\?id=(\d+)/i', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Submission did not redirect to concern details'];
    }

    $stmt = $pdo->prepare("SELECT mime_type, file_size FROM concern_attachments WHERE concern_id = :id");
    $stmt->execute(['id' => (int)$m[1]]);
    $row = $stmt->fetch();

    if (!$row || $row['mime_type'] !== 'application/pdf') {
        return ['pass' => false, 'msg' => 'PDF document not stored in the database'];
    }

    return ['pass' => true, 'msg' => "PDF stored in MySQL ({$row['file_size']} bytes)"];
});

// --- Test 11: Concern Submission WITHOUT Attachment ---
runTest("Concern Submission WITHOUT Attachment", function() use ($baseUrl, $studentCookie, $pdo) {
    $postData = [
        'action'      => 'create',
        'category_id' => 2, // Academics
        'title'       => 'Inquiry regarding prerequisite course syllabus',
        'description' => 'Could the department clarify if CS 101 can be taken concurrently with CS 102?'
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', $postData, $studentCookie);
    if ($resp['code'] !== 302 || !preg_match('/Location:\s*\.\.\/student\/concern_details\.php\?id=(\d+)/i', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Submission did not redirect'];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM concern_attachments WHERE concern_id = :id");
    $stmt->execute(['id' => (int)$m[1]]);

    if ((int)$stmt->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'Expected no attachment row for concern without file'];
    }

    return ['pass' => true, 'msg' => 'Concern created without an attachment row'];
});

// --- Test 12: Security Validation: Block Non-Permitted Extensions ---
runTest("File Security: Strict Extension Whitelisting (.exe / .php Blocked)", function() use ($baseUrl, $studentCookie) {
    $badFile = __DIR__ . '/scratch_virus.exe';
    file_put_contents($badFile, 'MZ executable dummy');

    $cFile = new CURLFile($badFile, 'application/x-msdownload', 'virus.exe');
    $postData = [
        'action'      => 'create',
        'category_id' => 1,
        'title'       => 'Executable test',
        'description' => 'Should be rejected.',
        'attachment'  => $cFile
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', $postData, $studentCookie, true);
    @unlink($badFile);

    if (strpos($resp['headers'], 'Location: ../student/submit_concern.php') === false) {
        return ['pass' => false, 'msg' => 'System did not reject disallowed .exe upload'];
    }

    return ['pass' => true, 'msg' => 'Disallowed extension was rejected with redirect to submit page'];
});

// --- Test 13: Photographic Evidence Thumbnail in Student Detail View ---
runTest("Photographic Evidence Preview in Student Concern Details", function() use ($baseUrl, $studentCookie, $jpegConcernId, $jpegAttachmentPath) {
    $view = makeHttpRequest("{$baseUrl}/student/concern_details.php?id={$jpegConcernId}", 'GET', [], $studentCookie);
    if ($view['code'] !== 200) {
        return ['pass' => false, 'msg' => "HTTP {$view['code']}"];
    }

    $imgTagFound = strpos($view['body'], "api/attachment.php?concern={$jpegConcernId}") !== false && strpos($view['body'], 'Concern Evidence Photo') !== false;
    $cardFound   = strpos($view['body'], $jpegAttachmentPath) !== false;
    if (!$imgTagFound || !$cardFound) {
        return ['pass' => false, 'msg' => 'Image thumbnail tag missing in student view'];
    }
    $img = makeHttpRequest("{$baseUrl}/api/attachment.php?concern={$jpegConcernId}", 'GET', [], $studentCookie);
    if ($img['code'] !== 200 || substr($img['body'], 0, 3) !== "\xFF\xD8\xFF") {
        return ['pass' => false, 'msg' => "Owner could not load the image from the database (HTTP {$img['code']})"];
    }
    return ['pass' => true, 'msg' => 'Inline <img> and download card rendered; image streamed from MySQL (' . strlen($img['body']) . ' bytes)'];
});

// --- Test 14: Photographic Evidence Thumbnail in Admin Detail View ---
runTest("Photographic Evidence Preview in Admin Concern Details", function() use ($baseUrl, $adminCookie, $jpegConcernId, $jpegAttachmentPath) {
    $view = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id={$jpegConcernId}", 'GET', [], $adminCookie);
    if ($view['code'] !== 200) {
        return ['pass' => false, 'msg' => "HTTP {$view['code']}"];
    }

    $imgTagFound = strpos($view['body'], "api/attachment.php?concern={$jpegConcernId}") !== false && strpos($view['body'], 'Concern Evidence Photo') !== false;
    $cardFound   = strpos($view['body'], $jpegAttachmentPath) !== false;
    if (!$imgTagFound || !$cardFound) {
        return ['pass' => false, 'msg' => 'Image thumbnail tag missing in admin view'];
    }
    $img = makeHttpRequest("{$baseUrl}/api/attachment.php?concern={$jpegConcernId}&download=1", 'GET', [], $adminCookie);
    if ($img['code'] !== 200 || substr($img['body'], 0, 3) !== "\xFF\xD8\xFF") {
        return ['pass' => false, 'msg' => "Admin could not download the image from the database (HTTP {$img['code']})"];
    }
    return ['pass' => true, 'msg' => 'Inline <img> and evidence controls rendered for Admin; download streamed from MySQL'];
});

// --- Test 15: Admin Response, Department Endorsement & Dynamic Lifecycle ---
runTest("Admin Response, Department Endorsement & Resolution", function() use ($baseUrl, $adminCookie, $pdo, $jpegConcernId) {
    // 1. Endorse to PPFO and set to In Progress (status_id = 2)
    $respData1 = [
        'concern_id'          => $jpegConcernId,
        'status_id'           => 2,
        'department_endorsed' => 'Physical Plant & Facilities Office (PPFO)',
        'response_text'       => 'Urgent work order #8841 generated. Maintenance dispatched emergency electrician to IT Lab.'
    ];
    $r1 = makeHttpRequest("{$baseUrl}/actions/response_action.php", 'POST', $respData1, $adminCookie);
    if ($r1['code'] !== 302) {
        return ['pass' => false, 'msg' => "Response 1 failed HTTP {$r1['code']}"];
    }

    // 2. Mark as Resolved (status_id = 3)
    $respData2 = [
        'concern_id'          => $jpegConcernId,
        'status_id'           => 3,
        'department_endorsed' => 'Physical Plant & Facilities Office (PPFO)',
        'response_text'       => 'Breaker rewired, overhead pipe clamped, water leak sealed. Verified operational and safe.'
    ];
    $r2 = makeHttpRequest("{$baseUrl}/actions/response_action.php", 'POST', $respData2, $adminCookie);
    if ($r2['code'] !== 302) {
        return ['pass' => false, 'msg' => "Response 2 failed HTTP {$r2['code']}"];
    }

    // Verify DB
    $checkStmt = $pdo->prepare("SELECT status_id FROM concerns WHERE concern_id = :id");
    $checkStmt->execute(['id' => $jpegConcernId]);
    if ($checkStmt->fetchColumn() != 3) {
        return ['pass' => false, 'msg' => 'Concern status is not Resolved (3)'];
    }

    $respStmt = $pdo->prepare("SELECT COUNT(*) FROM responses WHERE concern_id = :id AND department_endorsed IS NOT NULL");
    $respStmt->execute(['id' => $jpegConcernId]);
    $endorseCount = $respStmt->fetchColumn();

    if ($endorseCount < 2) {
        return ['pass' => false, 'msg' => 'Responses missing department endorsement in DB'];
    }

    return ['pass' => true, 'msg' => "Status transitioned to Resolved with PPFO endorsement and 2 timeline entries"];
});

// --- Test 16: Student Feedback & 5-Star Rating System ---
runTest("Student Feedback Submission & 5-Star Rating", function() use ($baseUrl, $studentCookie, $adminCookie, $pdo) {
    $feedData = [
        'feedback_type' => 'Resolution Satisfaction',
        'subject'       => 'Rapid Repair of IT Lab Wiring Defect',
        'rating'        => 5,
        'message'       => 'Outstanding response! The emergency team fixed the hazard in under 3 hours.'
    ];

    $resp = makeHttpRequest("{$baseUrl}/actions/feedback_action.php", 'POST', $feedData, $studentCookie);
    if ($resp['code'] !== 302) {
        return ['pass' => false, 'msg' => "Feedback submission failed HTTP {$resp['code']}"];
    }

    $fStmt = $pdo->query("SELECT * FROM feedback WHERE subject = 'Rapid Repair of IT Lab Wiring Defect' LIMIT 1");
    $fb = $fStmt->fetch();
    if (!$fb || $fb['rating'] != 5) {
        return ['pass' => false, 'msg' => 'Feedback record missing in database or rating mismatch'];
    }

    $adminFb = makeHttpRequest("{$baseUrl}/admin/feedback.php", 'GET', [], $adminCookie);
    if ($adminFb['code'] !== 200 || strpos($adminFb['body'], 'Rapid Repair of IT Lab Wiring Defect') === false) {
        return ['pass' => false, 'msg' => 'Feedback missing from admin feedback portal view'];
    }

    return ['pass' => true, 'msg' => '5-Star rating saved to feedback table and verified in admin view'];
});

// --- Test 17: User Management (Add Student & Admin, Toggle, Delete) ---
runTest("User Management: Add Users, Toggle Status, Archive & Restore Account (never deleted)", function() use ($baseUrl, $adminCookie, $pdo) {
    $testEmail = 'maria.clara.' . time() . '@student.snsu.edu.ph';
    $testName  = 'Maria Clara ' . time();

    // 1. Add Student
    $addData = [
        'action'     => 'add',
        'role'       => 'student',
        'name'       => $testName,
        'email'      => $testEmail,
        'password'   => 'testpass123',
        'student_no' => 'SNSU-AUTO-' . rand(1000, 9999),
        'department' => 'College of Arts and Sciences',
        'program'    => 'BA English Language'
    ];
    $addResp = makeHttpRequest("{$baseUrl}/actions/user_action.php", 'POST', $addData, $adminCookie);
    if ($addResp['code'] !== 302) {
        return ['pass' => false, 'msg' => 'Failed to add student'];
    }

    $sStmt = $pdo->prepare("SELECT student_id, status FROM students WHERE email = :email");
    $sStmt->execute(['email' => $testEmail]);
    $newStudent = $sStmt->fetch();
    if (!$newStudent) {
        return ['pass' => false, 'msg' => 'Student not created in database'];
    }
    $studentId = $newStudent['student_id'];

    // 2. Toggle Status to Inactive
    $togResp = makeHttpRequest("{$baseUrl}/actions/user_action.php", 'POST', ['action' => 'toggle_status', 'type' => 'student', 'id' => $studentId], $adminCookie);
    if ($togResp['code'] !== 302) {
        return ['pass' => false, 'msg' => 'Failed to toggle status'];
    }
    $sStmt->execute(['email' => $testEmail]);
    if ($sStmt->fetch()['status'] !== 'Inactive') {
        return ['pass' => false, 'msg' => 'Status was not toggled to Inactive'];
    }

    // 3. Archive (an old "delete" request archives too): the record stays, hidden from the list
    makeHttpRequest("{$baseUrl}/actions/user_action.php", 'POST', ['action' => 'delete', 'type' => 'student', 'id' => $studentId], $adminCookie);
    $a = $pdo->prepare("SELECT status, archived_at FROM students WHERE student_id = :id");
    $a->execute(['id' => $studentId]);
    $row = $a->fetch();
    if (!$row || $row['archived_at'] === null || $row['status'] !== 'Inactive') {
        return ['pass' => false, 'msg' => 'Student was not archived (or was deleted): ' . json_encode($row)];
    }
    $list = makeHttpRequest("{$baseUrl}/admin/users.php?q=" . urlencode($testEmail), 'GET', [], $adminCookie);
    $arch = makeHttpRequest("{$baseUrl}/admin/users.php?status=Archived&q=" . urlencode($testEmail), 'GET', [], $adminCookie);
    $inRows = fn(string $html) => strpos($html, '<div class="person-sub">' . $testEmail) !== false;
    if ($inRows($list['body']) || !$inRows($arch['body'])) {
        return ['pass' => false, 'msg' => 'Archived account not moved to the Archived filter'];
    }
    // Admin accounts can be archived too (no longer blocked by response history), but not your own
    makeHttpRequest("{$baseUrl}/actions/user_action.php", 'POST', ['action' => 'archive', 'type' => 'admin', 'id' => 1], $adminCookie);
    if ($pdo->query("SELECT archived_at FROM administrators WHERE admin_id = 1")->fetchColumn() !== null) {
        return ['pass' => false, 'msg' => 'An administrator archived their own account'];
    }

    // 4. Restore
    makeHttpRequest("{$baseUrl}/actions/user_action.php", 'POST', ['action' => 'restore', 'type' => 'student', 'id' => $studentId], $adminCookie);
    $a->execute(['id' => $studentId]);
    $row = $a->fetch();
    if ($row['archived_at'] !== null || $row['status'] !== 'Active') {
        return ['pass' => false, 'msg' => 'Restore did not reactivate the account'];
    }
    return ['pass' => true, 'msg' => 'Student created, toggled Inactive, archived (kept, hidden, in Archived filter), self-archive refused, restored'];
});

// --- Test 18: Institutional Announcements (Create, View, Delete) ---
runTest("Institutional Announcements: Create, Edit, View, Archive & Restore", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $annTitle = "Campus Network Upgrade " . time();
    $postData = [
        'action'   => 'create',
        'title'    => $annTitle,
        'category' => 'Technology',
        'is_urgent'=> 1,
        'content'  => 'Wi-Fi infrastructure maintenance scheduled for Building A.'
    ];

    // 1. Create
    $postResp = makeHttpRequest("{$baseUrl}/actions/announcement_action.php", 'POST', $postData, $adminCookie);
    if ($postResp['code'] !== 302) {
        return ['pass' => false, 'msg' => 'Failed to post announcement'];
    }

    $aStmt = $pdo->prepare("SELECT announcement_id FROM announcements WHERE title = :title");
    $aStmt->execute(['title' => $annTitle]);
    $annId = $aStmt->fetchColumn();
    if (!$annId) {
        return ['pass' => false, 'msg' => 'Announcement not found in DB'];
    }

    // 2. Verify on Student Dashboard
    $studentDash = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $studentCookie);
    if (strpos($studentDash['body'], $annTitle) === false) {
        return ['pass' => false, 'msg' => 'Announcement missing from student dashboard'];
    }

    // 3. Edit
    $originalTitle = $annTitle;
    $annTitle .= ' (updated)';
    makeHttpRequest("{$baseUrl}/actions/announcement_action.php", 'POST', ['action' => 'update', 'id' => $annId, 'title' => $annTitle,
        'category' => 'Maintenance', 'content' => 'Moved to Saturday.', 'is_urgent' => 1], $adminCookie);
    if ($pdo->query("SELECT title FROM announcements WHERE announcement_id = " . (int)$annId)->fetchColumn() !== $annTitle) {
        return ['pass' => false, 'msg' => 'Announcement was not edited'];
    }

    // 4. Archive (an old "delete" request archives too): kept in the database, hidden from students
    makeHttpRequest("{$baseUrl}/actions/announcement_action.php", 'POST', ['action' => 'delete', 'id' => $annId], $adminCookie);
    if ($pdo->query("SELECT archived_at FROM announcements WHERE announcement_id = " . (int)$annId)->fetchColumn() === null) {
        return ['pass' => false, 'msg' => 'Announcement was not archived (or was deleted)'];
    }
    foreach (['student/dashboard.php', 'student/announcements.php'] as $pg) {
        if (strpos(makeHttpRequest("{$baseUrl}/{$pg}", 'GET', [], $studentCookie)['body'], $annTitle) !== false) {
            return ['pass' => false, 'msg' => "Archived announcement still shown on {$pg}"];
        }
    }
    if (strpos(makeHttpRequest("{$baseUrl}/admin/announcements.php?tab=archived", 'GET', [], $adminCookie)['body'], e($annTitle)) === false) {
        return ['pass' => false, 'msg' => 'Archived announcement missing from the Archived tab'];
    }
    makeHttpRequest("{$baseUrl}/actions/announcement_action.php", 'POST', ['action' => 'restore', 'id' => $annId], $adminCookie);
    if (strpos(makeHttpRequest("{$baseUrl}/student/announcements.php", 'GET', [], $studentCookie)['body'], $annTitle) === false) {
        return ['pass' => false, 'msg' => 'Restored announcement not visible to students'];
    }

    // Urgent announcements must reach students' notification bells
    $n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND title = :t");
    $n->execute(['t' => 'Urgent: ' . ($originalTitle ?? $annTitle)]);
    if ((int)$n->fetchColumn() === 0) {
        return ['pass' => false, 'msg' => 'Urgent announcement did not notify students'];
    }

    return ['pass' => true, 'msg' => 'Announcement created, students notified, edited, archived (kept, hidden from students), restored'];
});

// --- Test 19: Reports & Analytics and Concern Filtering ---
runTest("Reports & Analytics and Concern Filtering", function() use ($baseUrl, $adminCookie) {
    // 1. Reports & Resolution Monitoring
    $rep = makeHttpRequest("{$baseUrl}/admin/reports.php", 'GET', [], $adminCookie);
    if ($rep['code'] !== 200 || strpos($rep['body'], 'Reports &amp; Resolution Monitoring') === false && strpos($rep['body'], 'Reports & Resolution Monitoring') === false) {
        return ['pass' => false, 'msg' => "HTTP {$rep['code']} on admin reports"];
    }

    $hasRate   = strpos($rep['body'], 'Resolution Rate') !== false && strpos($rep['body'], 'SLA Compliance') !== false;
    $hasCat    = strpos($rep['body'], 'Category Performance') !== false;
    $hasExport = strpos($rep['body'], 'export_csv.php?type=concerns') !== false;

    if (!$hasRate || !$hasCat || !$hasExport) {
        return ['pass' => false, 'msg' => 'Reports page missing key analytics widgets'];
    }

    // 2. Concern Filter in admin/concerns.php
    $filterView = makeHttpRequest("{$baseUrl}/admin/concerns.php?category=1&status=1", 'GET', [], $adminCookie);
    if ($filterView['code'] !== 200 || strpos($filterView['body'], 'Manage Concerns') === false) {
        return ['pass' => false, 'msg' => 'Concern filter view failed to load'];
    }

    return ['pass' => true, 'msg' => 'Resolution & SLA metrics, Category Performance, CSV export link & server-side filtering verified'];
});

// --- Test 20: UI Standard & Zero-Emoji Compliance ---
runTest("UI Standards: Zero raw alert(), Zero raw emojis, Toast & Modal markup", function() {
    // 1. Check main.js for raw alert()
    $mainJs = file_get_contents(__DIR__ . '/assets/js/main.js');
    if (preg_match('/(?<![a-zA-Z0-9_])alert\s*\(/i', $mainJs)) {
        return ['pass' => false, 'msg' => 'Found raw browser alert() call in assets/js/main.js'];
    }

    // 2. Check footer.php for toast and modal containers
    $footer = file_get_contents(__DIR__ . '/includes/footer.php');
    if (strpos($footer, 'id="toastContainer"') === false) {
        return ['pass' => false, 'msg' => 'Missing toastContainer in includes/footer.php'];
    }
    if (strpos($footer, 'id="confirmationModal"') === false) {
        return ['pass' => false, 'msg' => 'Missing confirmationModal in includes/footer.php'];
    }

    // 3. Scan for emojis in code files
    $dir = new RecursiveDirectoryIterator(__DIR__);
    $ite = new RecursiveIteratorIterator($dir);
    $files = new RegexIterator($ite, '/^.+\.(php|js|css)$/i', RecursiveRegexIterator::GET_MATCH);
    $emojiFiles = [];
    foreach ($files as $file) {
        $path = $file[0];
        if (strpos($path, '.git') !== false || strpos($path, 'scratch') !== false || strpos($path, 'test_suite') !== false) continue;
        $content = file_get_contents($path);
        if (preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $content)) {
            $emojiFiles[] = basename($path);
        }
    }

    if (!empty($emojiFiles)) {
        return ['pass' => false, 'msg' => 'Found emojis in: ' . implode(', ', array_unique($emojiFiles))];
    }

    return ['pass' => true, 'msg' => 'Zero raw alert() popups, zero broken emojis, SVG vector icons & responsive modal elements confirmed'];
});

// --- Test 22: CSRF protection ---
runTest("CSRF Protection: State-changing requests without a token are rejected", function() use ($baseUrl, $studentCookie, $pdo) {
    $before = (int)$pdo->query("SELECT COUNT(*) FROM concerns")->fetchColumn();
    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', [
        'action' => 'create', 'category_id' => 1, 'title' => 'CSRF probe', 'description' => 'Should be rejected',
    ], $studentCookie, false, false);
    $after = (int)$pdo->query("SELECT COUNT(*) FROM concerns")->fetchColumn();
    if ($after !== $before) {
        return ['pass' => false, 'msg' => 'Concern was created without a CSRF token'];
    }
    $bad = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', [
        'csrf_token' => str_repeat('0', 64), 'action' => 'create', 'category_id' => 1, 'title' => 'x', 'description' => 'y',
    ], $studentCookie, false, false);
    if ((int)$pdo->query("SELECT COUNT(*) FROM concerns")->fetchColumn() !== $before) {
        return ['pass' => false, 'msg' => 'Concern was created with a forged CSRF token'];
    }
    return ['pass' => true, 'msg' => 'Missing and forged tokens both rejected'];
});

// --- Test 23: Student self-registration ---
$regEmail = 'reg.' . time() . '.autotest@snsu.edu.ph';
$regNo    = 'SNSU-AUTO-R' . rand(10000, 99999);
runTest("Student Self-Registration (Use case: Register)", function() use ($baseUrl, $pdo, $regEmail, $regNo) {
    $jar = tempnam(sys_get_temp_dir(), 'cook_reg_');
    $base = ['student_no' => $regNo, 'name' => 'Auto Test Registrant', 'email' => $regEmail,
             'department' => 'College of Engineering', 'program' => 'BS Civil Engineering'];

    makeHttpRequest("{$baseUrl}/actions/register_action.php", 'POST', $base + ['password' => 'weakpass', 'password_confirm' => 'weakpass'], $jar);
    $c = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = :e");
    $c->execute(['e' => $regEmail]);
    if ((int)$c->fetchColumn() !== 0) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Weak password (no digit) was accepted'];
    }

    $dup = makeHttpRequest("{$baseUrl}/actions/register_action.php", 'POST',
        array_merge($base, ['email' => 'admin@school.edu', 'password' => 'Passw0rd1', 'password_confirm' => 'Passw0rd1']), $jar);
    if (strpos($dup['headers'], 'register.php') === false) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Duplicate email was not rejected'];
    }

    // The Registrar's masterlist lists this student, so the account is approved at once
    $pdo->prepare("INSERT INTO student_masterlist (student_no, name) VALUES (:n, 'Registrant, Auto Test')")->execute(['n' => $regNo]);
    $ok = makeHttpRequest("{$baseUrl}/actions/register_action.php", 'POST', $base + ['password' => 'Passw0rd1', 'password_confirm' => 'Passw0rd1'], $jar);
    $c->execute(['e' => $regEmail]);
    if ((int)$c->fetchColumn() !== 1 || strpos($ok['headers'], 'recovery_code.php') === false
        || !preg_match('/data-code="[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}"/', makeHttpRequest("{$baseUrl}/recovery_code.php", 'GET', [], $jar)['body'])) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Valid registration failed (or no recovery code was shown)'];
    }

    $login = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $regNo, 'password' => 'Passw0rd1'], $jar);
    @unlink($jar);
    if (strpos($login['headers'], 'student/dashboard.php') === false) {
        return ['pass' => false, 'msg' => 'Newly registered student could not log in'];
    }
    return ['pass' => true, 'msg' => 'Weak password & duplicate email rejected; masterlist-verified account created and logged in by student number'];
});

// --- Test 24: Login lockout ---
runTest("Brute-force Protection: Lockout after 5 failed logins", function() use ($baseUrl, $regEmail) {
    $jar = tempnam(sys_get_temp_dir(), 'cook_lock_');
    for ($i = 0; $i < LOGIN_MAX_ATTEMPTS_PER_ID; $i++) {
        makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $regEmail, 'password' => "Wrong{$i}x"], $jar);
    }
    $locked = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $regEmail, 'password' => 'Passw0rd1'], $jar);
    @unlink($jar);
    if (strpos($locked['headers'], 'dashboard') !== false) {
        return ['pass' => false, 'msg' => 'Correct password still accepted during lockout'];
    }
    return ['pass' => true, 'msg' => 'Account locked after ' . LOGIN_MAX_ATTEMPTS_PER_ID . ' failures (even correct password refused)'];
});

// --- Test 25: Forgot password -> temp password -> forced change ---
runTest("Forgot Password, Admin Temporary Password & Forced Change", function() use ($baseUrl, $adminCookie, $pdo, $regEmail) {
    $jar = tempnam(sys_get_temp_dir(), 'cook_fp_');
    makeHttpRequest("{$baseUrl}/actions/password_reset_action.php", 'POST', ['action' => 'request', 'identifier' => $regEmail], $jar);

    $r = $pdo->prepare("SELECT r.user_id FROM password_reset_requests r JOIN students s ON s.student_id = r.user_id
                        WHERE r.user_type = 'student' AND s.email = :e AND r.status = 'Pending'");
    $r->execute(['e' => $regEmail]);
    $studentId = (int)$r->fetchColumn();
    if (!$studentId) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Reset request was not recorded'];
    }

    makeHttpRequest("{$baseUrl}/actions/password_reset_action.php", 'POST', ['action' => 'reset', 'type' => 'student', 'id' => $studentId], $adminCookie);
    $page = makeHttpRequest("{$baseUrl}/admin/users.php", 'GET', [], $adminCookie);
    if (!preg_match('/<code id="tempPwd"[^>]*>([^<]+)</', $page['body'], $m)) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Temporary password not displayed to admin'];
    }
    $temp = html_entity_decode($m[1]);

    $login = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $regEmail, 'password' => $temp], $jar);
    if (strpos($login['headers'], 'change_password.php') === false) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Temporary password login did not force a password change'];
    }
    $blocked = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $jar);
    if ($blocked['code'] === 200) {
        @unlink($jar);
        return ['pass' => false, 'msg' => 'Dashboard reachable before changing the temporary password'];
    }
    makeHttpRequest("{$baseUrl}/actions/change_password_action.php", 'POST', [
        'current_password' => $temp, 'new_password' => 'NewPassw0rd', 'new_password_confirm' => 'NewPassw0rd'
    ], $jar);
    $dash = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $jar);
    @unlink($jar);
    if ($dash['code'] !== 200) {
        return ['pass' => false, 'msg' => 'Dashboard still blocked after changing password'];
    }
    return ['pass' => true, 'msg' => 'Request logged, admin issued temp password, forced change enforced then lifted'];
});

// --- Test 26: Resolution satisfaction rating ---
runTest("Resolution Satisfaction Rating (owner-only, once, resolved-only)", function() use ($baseUrl, $studentCookie, $adminCookie, $pdo, $jpegConcernId) {
    makeHttpRequest("{$baseUrl}/actions/feedback_action.php", 'POST', ['concern_id' => $jpegConcernId, 'rating' => 4, 'message' => 'Fast fix'], $studentCookie);
    makeHttpRequest("{$baseUrl}/actions/feedback_action.php", 'POST', ['concern_id' => $jpegConcernId, 'rating' => 1, 'message' => 'Second try'], $studentCookie);
    $f = $pdo->prepare("SELECT rating FROM feedback WHERE concern_id = :id");
    $f->execute(['id' => $jpegConcernId]);
    $rows = $f->fetchAll(PDO::FETCH_COLUMN);
    if ($rows !== [4] && $rows !== ['4']) {
        return ['pass' => false, 'msg' => 'Expected exactly one rating of 4, got ' . json_encode($rows)];
    }
    // Someone else's concern (seed concern 7 belongs to another student)
    makeHttpRequest("{$baseUrl}/actions/feedback_action.php", 'POST', ['concern_id' => 7, 'rating' => 5], $studentCookie);
    $o = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE concern_id = 7")->fetchColumn();
    if ($o !== 0) {
        return ['pass' => false, 'msg' => "Student rated another student's concern"];
    }
    $admin = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id={$jpegConcernId}", 'GET', [], $adminCookie);
    if (strpos($admin['body'], 'Student Satisfaction Rating') === false) {
        return ['pass' => false, 'msg' => 'Rating not shown to admin'];
    }
    return ['pass' => true, 'msg' => 'Rating saved once, duplicate & foreign ratings refused, visible to admin'];
});

// --- Test 27: SLA tracking, audit trail & notifications ---
runTest("SLA Tracking, Audit Trail & Notifications", function() use ($pdo, $jpegConcernId) {
    $c = $pdo->prepare("SELECT * FROM concerns WHERE concern_id = :id");
    $c->execute(['id' => $jpegConcernId]);
    $row = $c->fetch();
    if (empty($row['sla_due_at']) || empty($row['first_response_at']) || empty($row['resolved_at'])) {
        return ['pass' => false, 'msg' => 'SLA lifecycle timestamps not recorded'];
    }
    $expectedDue = date('Y-m-d H:i:s', strtotime($row['date_submitted']) + $row['sla_hours'] * 3600);
    if ($row['sla_due_at'] !== $expectedDue) {
        return ['pass' => false, 'msg' => "sla_due_at {$row['sla_due_at']} != {$expectedDue}"];
    }
    $state = getSlaState($row + ['status_name' => 'Resolved'])['state'];
    $a = $pdo->prepare("SELECT action FROM concern_audit_logs WHERE concern_id = :id");
    $a->execute(['id' => $jpegConcernId]);
    $actions = $a->fetchAll(PDO::FETCH_COLUMN);
    foreach (['created', 'ai_prioritized', 'status_changed', 'endorsed'] as $need) {
        if (!in_array($need, $actions, true)) {
            return ['pass' => false, 'msg' => "Audit log missing '{$need}'"];
        }
    }
    $n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE concern_id = :id AND recipient_type = 'student'");
    $n->execute(['id' => $jpegConcernId]);
    if ((int)$n->fetchColumn() < 2) {
        return ['pass' => false, 'msg' => 'Student was not notified of status changes'];
    }
    return ['pass' => true, 'msg' => "Due {$row['sla_due_at']}, state '{$state}', " . count($actions) . " audit entries, student notified"];
});

// --- Test 28: Pagination, filters & CSV export ---
runTest("Server-side Pagination/Filters & CSV Export", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE archived_at IS NULL")->fetchColumn(); // the list hides archived concerns
    $allRecords = (int)$pdo->query("SELECT COUNT(*) FROM concerns")->fetchColumn();           // the CSV report keeps every record
    $list = makeHttpRequest("{$baseUrl}/admin/concerns.php", 'GET', [], $adminCookie);
    if (!preg_match('/Showing <strong>(\d+)–(\d+)<\/strong> of <strong>(\d+)<\/strong>/u', $list['body'], $m) || (int)$m[3] !== $total || (int)$m[2] > DEFAULT_PER_PAGE) {
        return ['pass' => false, 'msg' => 'Pagination summary incorrect'];
    }
    $high = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE priority = 'High' AND archived_at IS NULL")->fetchColumn();
    $f = makeHttpRequest("{$baseUrl}/admin/concerns.php?priority=High", 'GET', [], $adminCookie);
    if (!preg_match('/of <strong>(\d+)<\/strong>/', $f['body'], $m2) || (int)$m2[1] !== $high) {
        return ['pass' => false, 'msg' => 'Priority filter count mismatch'];
    }
    $csv = makeHttpRequest("{$baseUrl}/actions/export_csv.php?type=concerns", 'GET', [], $adminCookie);
    $lines = array_filter(explode("\n", trim($csv['body'])));
    if (stripos($csv['headers'], 'text/csv') === false || count($lines) !== $allRecords + 1) {
        return ['pass' => false, 'msg' => 'CSV export missing rows (' . count($lines) . ' lines for ' . $allRecords . ' concerns)'];
    }
    $denied = makeHttpRequest("{$baseUrl}/actions/export_csv.php?type=concerns", 'GET', [], $studentCookie);
    if (stripos($denied['headers'], 'text/csv') !== false) {
        return ['pass' => false, 'msg' => 'Student was able to export CSV'];
    }
    return ['pass' => true, 'msg' => "Paged {$total} concerns, filter matched {$high} High, CSV has {$allRecords} rows (archived included), students denied"];
});

// --- Test 29: Reopen a resolved ticket ---
runTest("Reopen Resolved Ticket (reason required, rating cleared, fresh SLA)", function() use ($baseUrl, $studentCookie, $adminCookie, $pdo, $jpegConcernId) {
    makeHttpRequest("{$baseUrl}/actions/reopen_action.php", 'POST', ['concern_id' => $jpegConcernId, 'reason' => 'short'], $studentCookie);
    $st = $pdo->prepare("SELECT status_id, reopen_count, resolved_at, sla_due_at FROM concerns WHERE concern_id = :id");
    $st->execute(['id' => $jpegConcernId]);
    if ((int)$st->fetch()['status_id'] !== 3) {
        return ['pass' => false, 'msg' => 'Reopened without a proper reason'];
    }
    makeHttpRequest("{$baseUrl}/actions/reopen_action.php", 'POST', ['concern_id' => $jpegConcernId, 'reason' => 'Water is still leaking onto the panel after the repair.'], $studentCookie);
    $st->execute(['id' => $jpegConcernId]);
    $row = $st->fetch();
    if ((int)$row['status_id'] !== 1 || (int)$row['reopen_count'] !== 1 || $row['resolved_at'] !== null || strtotime($row['sla_due_at']) <= time()) {
        return ['pass' => false, 'msg' => 'Reopen did not reset status / resolved_at / SLA: ' . json_encode($row)];
    }
    $r = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE concern_id = :id");
    $r->execute(['id' => $jpegConcernId]);
    if ((int)$r->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'Satisfaction rating was not cleared on reopen'];
    }
    // Pending tickets cannot be reopened again
    makeHttpRequest("{$baseUrl}/actions/reopen_action.php", 'POST', ['concern_id' => $jpegConcernId, 'reason' => 'Trying to reopen a pending ticket'], $studentCookie);
    $st->execute(['id' => $jpegConcernId]);
    if ((int)$st->fetch()['reopen_count'] !== 1) {
        return ['pass' => false, 'msg' => 'A pending ticket was reopened'];
    }
    $admin = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id={$jpegConcernId}", 'GET', [], $adminCookie);
    if (strpos($admin['body'], 'Reopened the concern') === false) {
        return ['pass' => false, 'msg' => 'Reopen reason missing from admin timeline'];
    }
    return ['pass' => true, 'msg' => 'Short reason refused; reopened to Pending with new SLA, rating cleared, shown on admin timeline'];
});

// --- Test 30: Email notifications queue ---
runTest("Email Notifications Queue (receipt, status change, RRT, reopen)", function() use ($pdo, $startMaxEmailId) {
    $rows = $pdo->prepare("SELECT to_email, subject, status FROM email_outbox WHERE email_id > :id");
    $rows->execute(['id' => $startMaxEmailId]);
    $emails = $rows->fetchAll();
    $has = function (string $to, string $needle) use ($emails) {
        foreach ($emails as $e) {
            if ($e['to_email'] === $to && stripos($e['subject'], $needle) !== false) {
                return true;
            }
        }
        return false;
    };
    $checks = [
        'student receipt'      => $has('juan.delacruz@student.com', 'Concern received'),
        'student status email' => $has('juan.delacruz@student.com', 'is now Resolved'),
        'admin RRT alert'      => $has('admin@school.edu', '[RRT CRITICAL]'),
        'admin reopen alert'   => $has('admin@school.edu', 'Concern reopened'),
    ];
    $missing = array_keys(array_filter($checks, fn($ok) => !$ok));
    if ($missing) {
        return ['pass' => false, 'msg' => 'Missing emails: ' . implode(', ', $missing)];
    }
    $failed = count(array_filter($emails, fn($e) => $e['status'] === 'Failed'));
    if ($failed > 0) {
        return ['pass' => false, 'msg' => "{$failed} email(s) failed to deliver (driver: " . mailConfig()['driver'] . ')'];
    }
    return ['pass' => true, 'msg' => count($emails) . ' emails queued, none failed (driver: ' . mailConfig()['driver'] . ')'];
});

// --- Test 31: Ticket assignment ---
runTest("Ticket Assignment (assign, notify assignee, filter, activity log)", function() use ($baseUrl, $adminCookie, $pdo, $pngConcernId) {
    makeHttpRequest("{$baseUrl}/actions/concern_admin_action.php", 'POST', ['action' => 'assign', 'concern_id' => $pngConcernId, 'assigned_admin_id' => 2], $adminCookie);
    $a = $pdo->prepare("SELECT assigned_admin_id FROM concerns WHERE concern_id = :id");
    $a->execute(['id' => $pngConcernId]);
    if ((int)$a->fetchColumn() !== 2) {
        return ['pass' => false, 'msg' => 'Ticket was not assigned'];
    }
    $n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = 2 AND type = 'assigned' AND concern_id = :id");
    $n->execute(['id' => $pngConcernId]);
    if ((int)$n->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Assignee was not notified'];
    }
    $l = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_logs WHERE action = 'concern_assigned' AND target_id = :id");
    $l->execute(['id' => $pngConcernId]);
    if ((int)$l->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Assignment missing from the activity log'];
    }
    $expected = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE assigned_admin_id = 2")->fetchColumn();
    $list = makeHttpRequest("{$baseUrl}/admin/concerns.php?assigned=2", 'GET', [], $adminCookie);
    if (!preg_match('/of <strong>(\d+)<\/strong>/', $list['body'], $m) || (int)$m[1] !== $expected) {
        return ['pass' => false, 'msg' => 'Assignment filter count mismatch'];
    }
    $page = makeHttpRequest("{$baseUrl}/admin/activity.php", 'GET', [], $adminCookie);
    if ($page['code'] !== 200 || strpos($page['body'], 'Assigned ticket') === false) {
        return ['pass' => false, 'msg' => 'Activity Log page does not show the assignment'];
    }
    return ['pass' => true, 'msg' => "Assigned to admin #2, notified, filter shows {$expected}, logged on Activity Log page"];
});

// --- Test 32: Automatic SLA escalation ---
runTest("Automatic SLA Escalation (breach alert sent once)", function() use ($pdo, $pngConcernId) {
    $pdo->prepare("UPDATE concerns SET status_id = 1, sla_due_at = NOW() - INTERVAL 1 HOUR, sla_alert_level = 'none' WHERE concern_id = :id")
        ->execute(['id' => $pngConcernId]);
    runSlaMonitor($pdo);
    runSlaMonitor($pdo); // second run must not alert again
    $c = $pdo->prepare("SELECT sla_alert_level FROM concerns WHERE concern_id = :id");
    $c->execute(['id' => $pngConcernId]);
    if ($c->fetchColumn() !== 'breached') {
        return ['pass' => false, 'msg' => 'sla_alert_level was not set to breached'];
    }
    $n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'sla_breach' AND concern_id = :id");
    $n->execute(['id' => $pngConcernId]);
    $count = (int)$n->fetchColumn();
    if ($count !== 1) { // assigned to admin #2 in the previous test -> exactly one alert
        return ['pass' => false, 'msg' => "Expected 1 breach alert to the assignee, got {$count}"];
    }
    $au = $pdo->prepare("SELECT COUNT(*) FROM concern_audit_logs WHERE concern_id = :id AND action = 'sla_breached'");
    $au->execute(['id' => $pngConcernId]);
    if ((int)$au->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Breach not written to the audit trail'];
    }
    return ['pass' => true, 'msg' => 'Breach detected, assignee alerted exactly once, audit trail updated'];
});

// --- Test 33: Idle session timeout ---
runTest("Idle Session Timeout & keep-alive endpoint", function() use ($baseUrl) {
    $saved = $_SESSION;
    $_SESSION = ['user_id' => 1, 'role' => 'student', 'last_activity' => time() - SESSION_IDLE_TIMEOUT - 60];
    $expired = sessionExpired();
    $flash = $_SESSION['flash']['message'] ?? '';
    $_SESSION = ['user_id' => 1, 'role' => 'student', 'last_activity' => time() - 60];
    $fresh = sessionExpired();
    $_SESSION = $saved;
    if (!$expired || $fresh || stripos($flash, 'inactivity') === false) {
        return ['pass' => false, 'msg' => 'Idle session was not expired correctly'];
    }
    $anon = makeHttpRequest("{$baseUrl}/api/keepalive.php", 'POST', [], tempnam(sys_get_temp_dir(), 'cook_ka_'));
    if ($anon['code'] !== 401) {
        return ['pass' => false, 'msg' => "Keep-alive accepted an anonymous request (HTTP {$anon['code']})"];
    }
    return ['pass' => true, 'msg' => 'Sessions idle > ' . (SESSION_IDLE_TIMEOUT / 60) . ' min are signed out with a notice; keep-alive requires login'];
});

// --- Test 34: Withdraw a concern ---
runTest("Withdraw Concern (Pending only, reason required, record kept, admins told)", function() use ($baseUrl, $studentCookie, $adminCookie, $pdo) {
    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', [
        'action' => 'create', 'category_id' => 3, 'title' => 'Library aircon test for withdrawal',
        'description' => 'Temporary concern created by the automated test suite.',
    ], $studentCookie);
    if (!preg_match('/concern_details\.php\?id=(\d+)/', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Could not create a concern to withdraw'];
    }
    $id = (int)$m[1];
    $st = $pdo->prepare("SELECT status_id, withdraw_reason FROM concerns WHERE concern_id = :id");

    makeHttpRequest("{$baseUrl}/actions/withdraw_action.php", 'POST', ['concern_id' => $id], $studentCookie);
    $st->execute(['id' => $id]);
    if ((int)$st->fetch()['status_id'] !== 1) {
        return ['pass' => false, 'msg' => 'Withdrawn without a reason'];
    }

    makeHttpRequest("{$baseUrl}/actions/withdraw_action.php", 'POST', ['concern_id' => $id, 'reason' => 'mistake'], $studentCookie);
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ((int)$row['status_id'] !== 4 || stripos($row['withdraw_reason'], 'mistake') === false) {
        return ['pass' => false, 'msg' => 'Pending concern was not withdrawn: ' . json_encode($row)];
    }

    $n = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'withdrawn' AND concern_id = :id");
    $n->execute(['id' => $id]);
    if ((int)$n->fetchColumn() === 0) {
        return ['pass' => false, 'msg' => 'Administrators were not notified'];
    }

    // Admins cannot post a status change on a withdrawn ticket
    makeHttpRequest("{$baseUrl}/actions/response_action.php", 'POST', ['concern_id' => $id, 'status_id' => 2, 'response_text' => 'Should be refused'], $adminCookie);
    $r = $pdo->prepare("SELECT COUNT(*) FROM responses WHERE concern_id = :id");
    $r->execute(['id' => $id]);
    if ((int)$r->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'Admin response accepted on a withdrawn ticket'];
    }

    // A concern that is already In Progress cannot be withdrawn (seed concern 5 belongs to this student)
    $before = (int)$pdo->query("SELECT status_id FROM concerns WHERE concern_id = 5")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/withdraw_action.php", 'POST', ['concern_id' => 5, 'reason' => 'mistake'], $studentCookie);
    $after = (int)$pdo->query("SELECT status_id FROM concerns WHERE concern_id = 5")->fetchColumn();
    if ($before === 2 && $after !== 2) {
        return ['pass' => false, 'msg' => 'An In Progress concern was withdrawn'];
    }

    $page = makeHttpRequest("{$baseUrl}/student/concern_details.php?id={$id}", 'GET', [], $studentCookie);
    if (strpos($page['body'], 'You withdrew this concern') === false) {
        return ['pass' => false, 'msg' => 'Withdrawn notice not shown to the student'];
    }
    return ['pass' => true, 'msg' => 'Reason required; Pending ticket withdrawn and kept; admins notified; admin updates and In Progress withdrawals refused'];
});

// --- Test 35: Edit a concern ---
runTest("Edit Concern (owner only, before any admin response, AI re-score, SLA kept)", function() use ($baseUrl, $studentCookie, $adminCookie, $pdo) {
    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', [
        'action' => 'create', 'category_id' => 3, 'title' => 'Slow computer in the library',
        'description' => 'One of the library computers takes a long time to open.',
    ], $studentCookie);
    if (!preg_match('/concern_details\.php\?id=(\d+)/', $resp['headers'], $m)) {
        return ['pass' => false, 'msg' => 'Could not create a concern to edit'];
    }
    $id = (int)$m[1];
    $get = $pdo->prepare("SELECT * FROM concerns WHERE concern_id = :id");
    $get->execute(['id' => $id]);
    $before = $get->fetch();

    $page = makeHttpRequest("{$baseUrl}/student/submit_concern.php?id={$id}", 'GET', [], $studentCookie);
    if ($page['code'] !== 200 || strpos($page['body'], 'Slow computer in the library') === false) {
        return ['pass' => false, 'msg' => 'Edit form did not load with the current values'];
    }

    // Edit: the new text describes a hazard, so the AI must re-score it as Critical
    makeHttpRequest("{$baseUrl}/actions/edit_concern_action.php", 'POST', [
        'concern_id' => $id, 'category_id' => 1, 'title' => 'Library computer outlet is sparking',
        'description' => 'The outlet behind the computer is sparking and there is smoke.',
    ], $studentCookie);
    $get->execute(['id' => $id]);
    $after = $get->fetch();
    $expectedDue = date('Y-m-d H:i:s', strtotime($before['date_submitted']) + 24 * 3600);
    if ($after['title'] !== 'Library computer outlet is sparking' || (int)$after['category_id'] !== 1
        || $after['priority'] !== 'Critical' || (int)$after['edit_count'] !== 1 || $after['sla_due_at'] !== $expectedDue) {
        return ['pass' => false, 'msg' => 'Edit not applied correctly: ' . json_encode(array_intersect_key($after, array_flip(['title', 'category_id', 'priority', 'edit_count', 'sla_due_at'])))];
    }
    $au = $pdo->prepare("SELECT COUNT(*) FROM concern_audit_logs WHERE concern_id = :id AND action = 'edited'");
    $au->execute(['id' => $id]);
    if ((int)$au->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Edit missing from the audit trail'];
    }

    // Once an administrator responds, the concern is locked
    makeHttpRequest("{$baseUrl}/actions/response_action.php", 'POST', ['concern_id' => $id, 'status_id' => 1, 'response_text' => 'Received, checking now.'], $adminCookie);
    makeHttpRequest("{$baseUrl}/actions/edit_concern_action.php", 'POST', [
        'concern_id' => $id, 'category_id' => 1, 'title' => 'Changed after response', 'description' => 'Should be refused.',
    ], $studentCookie);
    $get->execute(['id' => $id]);
    if ($get->fetch()['title'] === 'Changed after response') {
        return ['pass' => false, 'msg' => 'Concern was edited after an administrator responded'];
    }

    // Another student's concern cannot be edited (seed concern 7 belongs to a different student)
    $t7 = $pdo->query("SELECT title FROM concerns WHERE concern_id = 7")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/edit_concern_action.php", 'POST', [
        'concern_id' => 7, 'category_id' => 1, 'title' => 'Hijacked', 'description' => 'Should be refused.',
    ], $studentCookie);
    if ($pdo->query("SELECT title FROM concerns WHERE concern_id = 7")->fetchColumn() !== $t7) {
        return ['pass' => false, 'msg' => "A student edited someone else's concern"];
    }
    return ['pass' => true, 'msg' => 'Prefilled form; edit re-scored to Critical with SLA from the original time; locked after admin response; foreign edits refused'];
});

// --- Test 36: Category management ---
runTest("Category Management (add, rename, archive from Submit form, restore, never deleted)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $name = 'Transportation ' . time();
    makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'add', 'category_name' => $name, 'description' => 'Shuttle and parking concerns'], $adminCookie);
    $f = $pdo->prepare("SELECT * FROM categories WHERE category_name = :n");
    $f->execute(['n' => $name]);
    $cat = $f->fetch();
    if (!$cat || (int)$cat['is_active'] !== 1) {
        return ['pass' => false, 'msg' => 'Category was not added'];
    }
    $id = (int)$cat['category_id'];

    $dup = makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'add', 'category_name' => 'facilities'], $adminCookie);
    if ((int)$pdo->query("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = 'facilities'")->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'A duplicate category name was accepted'];
    }

    $form = makeHttpRequest("{$baseUrl}/student/submit_concern.php", 'GET', [], $studentCookie);
    if (strpos($form['body'], $name) === false) {
        return ['pass' => false, 'msg' => 'New category missing from the Submit form'];
    }

    $renamed = $name . ' Office';
    makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'update', 'category_id' => $id, 'category_name' => $renamed, 'description' => 'Updated'], $adminCookie);
    makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'archive', 'category_id' => $id], $adminCookie);
    $f2 = $pdo->prepare("SELECT category_name, is_active FROM categories WHERE category_id = :id");
    $f2->execute(['id' => $id]);
    $row = $f2->fetch();
    if ($row['category_name'] !== $renamed || (int)$row['is_active'] !== 0) {
        return ['pass' => false, 'msg' => 'Rename or hide failed: ' . json_encode($row)];
    }
    $form = makeHttpRequest("{$baseUrl}/student/submit_concern.php", 'GET', [], $studentCookie);
    if (strpos($form['body'], $renamed) !== false) {
        return ['pass' => false, 'msg' => 'Hidden category still offered on the Submit form'];
    }
    $resp = makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', ['action' => 'create', 'category_id' => $id, 'title' => 'Hidden category probe', 'description' => 'Should be refused'], $studentCookie);
    if (preg_match('/concern_details\.php\?id=/', $resp['headers'])) {
        return ['pass' => false, 'msg' => 'A concern was submitted under a hidden category'];
    }

    // Delete requests never remove a category
    makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'delete', 'category_id' => $id], $adminCookie);
    $f2->execute(['id' => $id]);
    if (!$f2->fetch()) {
        return ['pass' => false, 'msg' => 'A category was deleted'];
    }
    $page = makeHttpRequest("{$baseUrl}/admin/categories.php?tab=archived", 'GET', [], $adminCookie);
    if (strpos($page['body'], e($renamed)) === false) {
        return ['pass' => false, 'msg' => 'Archived category missing from the Archived tab'];
    }
    makeHttpRequest("{$baseUrl}/actions/category_action.php", 'POST', ['action' => 'restore', 'category_id' => $id], $adminCookie);
    if (strpos(makeHttpRequest("{$baseUrl}/student/submit_concern.php", 'GET', [], $studentCookie)['body'], $renamed) === false) {
        return ['pass' => false, 'msg' => 'Restored category not offered on the Submit form'];
    }
    return ['pass' => true, 'msg' => 'Added, duplicate refused, renamed, archived from the form and submissions, delete refused, restored'];
});

// --- Test 37: Feedback replies ---
runTest("Feedback Replies (admin reply, student sees it and is notified)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    makeHttpRequest("{$baseUrl}/actions/feedback_action.php", 'POST', [
        'feedback_type' => 'General Suggestion', 'subject' => 'Reply test ' . time(), 'message' => 'Please add more benches.', 'rating' => 4,
    ], $studentCookie);
    $id = (int)$pdo->query("SELECT MAX(feedback_id) FROM feedback")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/feedback_reply_action.php", 'POST', ['feedback_id' => $id, 'admin_reply' => 'Thank you! Six benches will be installed next week.'], $adminCookie);
    $r = $pdo->prepare("SELECT admin_reply, replied_by FROM feedback WHERE feedback_id = :id");
    $r->execute(['id' => $id]);
    $row = $r->fetch();
    if (stripos((string)$row['admin_reply'], 'Six benches') === false || !$row['replied_by']) {
        return ['pass' => false, 'msg' => 'Reply was not saved'];
    }
    $n = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'feedback_reply' AND recipient_type = 'student'")->fetchColumn();
    if ($n === 0) {
        return ['pass' => false, 'msg' => 'Student was not notified'];
    }
    $page = makeHttpRequest("{$baseUrl}/student/feedback.php", 'GET', [], $studentCookie);
    if (strpos($page['body'], 'Six benches will be installed') === false) {
        return ['pass' => false, 'msg' => 'Reply not shown on the student Feedback page'];
    }
    return ['pass' => true, 'msg' => 'Reply saved, student notified, and shown in the student feedback history'];
});

// --- Test 38: Duplicate detection ---
runTest("Duplicate Detection (similar open concerns, privacy of other students, admin panel)", function() use ($baseUrl, $adminCookie, $studentCookie) {
    $ask = function (string $title, string $desc, int $cat) use ($baseUrl, $studentCookie) {
        $r = makeHttpRequest("{$baseUrl}/api/ai_prioritize.php", 'POST', ['title' => $title, 'description' => $desc, 'category_id' => $cat], $studentCookie);
        $j = json_decode($r['body'], true);
        return $j['data']['similar'] ?? null;
    };
    // Demo data: SCF-2026-001 (Juan's) and SCF-2026-009 (Maria's) are both about the ID printing machine
    $sim = $ask('ID printing machine error', 'The ID printing machine at the registrar shows an error again', 3);
    if (!is_array($sim) || count($sim) < 2) {
        return ['pass' => false, 'msg' => 'Similar ID-printer concerns were not detected: ' . json_encode($sim)];
    }
    $own   = array_values(array_filter($sim, fn($m) => $m['own']));
    $other = array_values(array_filter($sim, fn($m) => !$m['own']));
    if (!$own || $own[0]['ticket_id'] !== 'SCF-2026-001' || !$other || $other[0]['ticket_id'] !== null || $other[0]['concern_id'] !== null) {
        return ['pass' => false, 'msg' => 'Own/other concerns not separated correctly: ' . json_encode($sim)];
    }
    foreach ($sim as $m) {
        if (array_intersect(array_keys($m), ['student_id', 'student_name', 'description', 'email'])) {
            return ['pass' => false, 'msg' => 'Personal data leaked in duplicate results'];
        }
    }
    // A personal category (Student Welfare) hides another student's title
    $welfare = $ask('Counseling schedule during exam week', 'I need a counseling schedule during exam week', 4);
    if (!$welfare || $welfare[0]['own'] || $welfare[0]['title'] !== null) {
        return ['pass' => false, 'msg' => 'Private Student Welfare concern was exposed: ' . json_encode($welfare)];
    }
    // Unrelated text gives no match
    if ($ask('Request for new benches', 'Please add benches near the gym entrance', 1) !== []) {
        return ['pass' => false, 'msg' => 'An unrelated concern was flagged as a duplicate'];
    }
    // Admin ticket page lists the related ticket
    $page = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id=1", 'GET', [], $adminCookie);
    if (strpos($page['body'], 'Possible Duplicates') === false || strpos($page['body'], 'SCF-2026-009') === false) {
        return ['pass' => false, 'msg' => 'Admin ticket page does not show the possible duplicate'];
    }
    return ['pass' => true, 'msg' => count($sim) . ' similar concerns found; other students anonymous; Student Welfare title hidden; unrelated text not flagged; admin panel shows SCF-2026-009'];
});

// --- Test 39: Verified registration (masterlist + approval) ---
runTest("Verified Registration (masterlist auto-approval, pending approval, approve / reject, CSV import)", function() use ($baseUrl, $adminCookie, $pdo) {
    $register = function (string $no, string $name, string $email) use ($baseUrl) {
        return makeHttpRequest("{$baseUrl}/actions/register_action.php", 'POST', [
            'student_no' => $no, 'name' => $name, 'email' => $email,
            'department' => 'College of Computing & Information Sciences', 'program' => 'BS Information Technology',
            'password' => 'Passw0rd1', 'password_confirm' => 'Passw0rd1',
        ]);
    };
    $statusOf = function (string $no) use ($pdo) {
        $s = $pdo->prepare("SELECT status, masterlist_verified, registration_note FROM students WHERE student_no = :n");
        $s->execute(['n' => $no]);
        return $s->fetch() ?: null;
    };
    $canLogIn = function (string $no) use ($baseUrl) {
        $jar = tempnam(sys_get_temp_dir(), 'cook_ver_');
        $r = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $no, 'password' => 'Passw0rd1'], $jar);
        @unlink($jar);
        return strpos($r['headers'], 'student/dashboard.php') !== false;
    };

    // 1. On the masterlist (name written differently) -> approved at once
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'add', 'student_no' => 'TEST-ML-0001', 'name' => 'Masterlist, Tess Q.'], $adminCookie);
    $register('TEST-ML-0001', 'Tess Masterlist', 'tess.ml.autotest@example.com');
    $st = $statusOf('TEST-ML-0001');
    if (!$st || $st['status'] !== 'Active' || (int)$st['masterlist_verified'] !== 1 || !$canLogIn('TEST-ML-0001')) {
        return ['pass' => false, 'msg' => 'Masterlist match was not approved automatically: ' . json_encode($st)];
    }

    // 2. Not on the list -> Pending, cannot sign in, admins notified; approval lets them in and adds them to the list
    $register('TEST-PEND-0002', 'Pia Pending', 'pia.pending.autotest@example.com');
    $st = $statusOf('TEST-PEND-0002');
    if (!$st || $st['status'] !== 'Pending' || $canLogIn('TEST-PEND-0002')) {
        return ['pass' => false, 'msg' => 'Unlisted registration was not held for approval'];
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'registration' AND message LIKE '%TEST-PEND-0002%'")->fetchColumn() === 0) {
        return ['pass' => false, 'msg' => 'Administrators were not notified of the pending registration'];
    }
    $users = makeHttpRequest("{$baseUrl}/admin/users.php", 'GET', [], $adminCookie);
    if (strpos($users['body'], 'Registrations Awaiting Approval') === false || strpos($users['body'], 'TEST-PEND-0002') === false) {
        return ['pass' => false, 'msg' => 'Pending registration not listed on the Users page'];
    }
    $id = (int)$pdo->query("SELECT student_id FROM students WHERE student_no = 'TEST-PEND-0002'")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/registration_action.php", 'POST', ['action' => 'approve', 'student_id' => $id, 'add_to_masterlist' => 1], $adminCookie);
    if ($statusOf('TEST-PEND-0002')['status'] !== 'Active' || !$canLogIn('TEST-PEND-0002')
        || (int)$pdo->query("SELECT COUNT(*) FROM student_masterlist WHERE student_no = 'TEST-PEND-0002'")->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Approval did not activate the account or add it to the masterlist'];
    }

    // 3. Reject -> account removed, number free again
    $register('TEST-PEND-0003', 'Fake Person', 'fake.person.autotest@example.com');
    $id = (int)$pdo->query("SELECT student_id FROM students WHERE student_no = 'TEST-PEND-0003'")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/registration_action.php", 'POST', ['action' => 'reject', 'student_id' => $id, 'reason' => 'Not enrolled'], $adminCookie);
    if ($statusOf('TEST-PEND-0003') !== null) {
        return ['pass' => false, 'msg' => 'Rejected registration was not removed'];
    }

    // 4. Someone using a listed number under another name -> held for approval
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'add', 'student_no' => 'TEST-ML-0005', 'name' => 'Rightful Owner'], $adminCookie);
    $register('TEST-ML-0005', 'Somebody Else', 'somebody.autotest@example.com');
    $st = $statusOf('TEST-ML-0005');
    if (!$st || $st['status'] !== 'Pending' || strpos((string)$st['registration_note'], 'different name') === false) {
        return ['pass' => false, 'msg' => 'Name mismatch on a listed number was approved: ' . json_encode($st)];
    }

    // 5. CSV import approves a waiting registration that now matches, and skips invalid rows
    $register('TEST-PEND-0004', 'Ana Reyes', 'ana.reyes.autotest@example.com');
    $csv = tempnam(sys_get_temp_dir(), 'ml_') . '.csv';
    file_put_contents($csv, "\xEF\xBB\xBFStudent Number,Full Name,Email,College,Course\nTEST-PEND-0004,\"Reyes, Ana M.\",,College of Computing & Information Sciences,BSIT\nbad no!,Someone,,,\nTEST-ML-0006,Carlo Uy,carlo@example.com,,\n");
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST',
        ['action' => 'import', 'csv_file' => new CURLFile($csv, 'text/csv', 'masterlist.csv'), 'csrf_token' => getCsrfToken($adminCookie)], $adminCookie, true);
    @unlink($csv);
    $listed = (int)$pdo->query("SELECT COUNT(*) FROM student_masterlist WHERE student_no IN ('TEST-PEND-0004', 'TEST-ML-0006')")->fetchColumn();
    if ($listed !== 2 || ($statusOf('TEST-PEND-0004')['status'] ?? '') !== 'Active') {
        return ['pass' => false, 'msg' => "CSV import failed (listed {$listed}/2, status " . ($statusOf('TEST-PEND-0004')['status'] ?? 'none') . ')'];
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM student_masterlist WHERE name = 'Someone'")->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'An invalid CSV row was imported'];
    }
    return ['pass' => true, 'msg' => 'Masterlist match auto-approved; unlisted held as Pending and blocked; approve/reject work; name mismatch held; CSV import approved a waiting student and skipped a bad row'];
});

// --- Test 40: Anonymous submission ---
runTest("Anonymous Submission (identity hidden from admin pages, search, export and alerts)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $title = 'Anonymous report about bullying near the gym ' . time();
    makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', [
        'action' => 'create', 'category_id' => 4, 'title' => $title,
        'description' => 'A group of students keeps harassing first years near the gym after classes.', 'is_anonymous' => '1',
    ], $studentCookie);
    $c = $pdo->prepare("SELECT concern_id, ticket_id, is_anonymous, student_id FROM concerns WHERE title = :t");
    $c->execute(['t' => $title]);
    $row = $c->fetch();
    if (!$row || (int)$row['is_anonymous'] !== 1 || (int)$row['student_id'] !== 1) {
        return ['pass' => false, 'msg' => 'Anonymous concern was not saved (or lost its owner)'];
    }
    $id = (int)$row['concern_id'];
    $tid = $row['ticket_id'];
    $identity = ['Juan Dela Cruz', 'SNSU-2022-04189', 'juan.delacruz@student.com'];
    $leaks = fn(string $text) => array_values(array_filter($identity, fn($x) => stripos($text, $x) !== false));
    // Only the page content: the navbar bell legitimately lists other (non-anonymous) concerns
    $main = fn(string $html) => (string)substr($html, (int)strpos($html, '<main class="app-main">'));

    // Admin ticket page
    $page = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id={$id}", 'GET', [], $adminCookie);
    if (strpos($page['body'], 'Anonymous student') === false || $leaks($main($page['body']))) {
        return ['pass' => false, 'msg' => 'Admin ticket page shows the identity: ' . implode(', ', $leaks($main($page['body'])))];
    }
    // Admin list: shown as anonymous, and searching the student's name or number never finds it
    $list = makeHttpRequest("{$baseUrl}/admin/concerns.php?q=" . urlencode($title), 'GET', [], $adminCookie);
    if (strpos($list['body'], $tid) === false || strpos($list['body'], 'Name hidden') === false || $leaks($main($list['body']))) {
        return ['pass' => false, 'msg' => 'Admin concern list exposes the identity'];
    }
    foreach (['Juan Dela Cruz', 'SNSU-2022-04189'] as $who) {
        $search = makeHttpRequest("{$baseUrl}/admin/concerns.php?q=" . urlencode($who), 'GET', [], $adminCookie);
        if (strpos($main($search['body']), $tid) !== false) {
            return ['pass' => false, 'msg' => "Searching \"{$who}\" finds the anonymous ticket"];
        }
    }
    // CSV export row
    $csv = makeHttpRequest("{$baseUrl}/actions/export_csv.php?type=concerns", 'GET', [], $adminCookie);
    $line = '';
    foreach (explode("\n", $csv['body']) as $l) {
        if (strpos($l, $tid) !== false) { $line = $l; break; }
    }
    if ($line === '' || strpos($line, 'Anonymous student') === false || $leaks($line)) {
        return ['pass' => false, 'msg' => 'CSV export exposes the identity: ' . $line];
    }
    // Admin notifications and audit trail
    $n = $pdo->prepare("SELECT GROUP_CONCAT(CONCAT(title, ' ', message) SEPARATOR ' | ') FROM notifications WHERE concern_id = :id AND recipient_type = 'admin'");
    $n->execute(['id' => $id]);
    if ($leaks((string)$n->fetchColumn())) {
        return ['pass' => false, 'msg' => 'Admin notification exposes the identity'];
    }
    // The student still owns and tracks it, with the Anonymous badge, and gets replies
    $mine = makeHttpRequest("{$baseUrl}/student/concern_details.php?id={$id}", 'GET', [], $studentCookie);
    if (strpos($mine['body'], 'Anonymous') === false || strpos($mine['body'], $title) === false) {
        return ['pass' => false, 'msg' => 'Student cannot track the anonymous concern'];
    }
    makeHttpRequest("{$baseUrl}/actions/response_action.php", 'POST', [
        'concern_id' => $id, 'status_id' => 2, 'response_text' => 'Guidance office is looking into this. Thank you for reporting.',
    ], $adminCookie);
    $sn = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE concern_id = :id AND recipient_type = 'student' AND recipient_id = 1");
    $sn->execute(['id' => $id]);
    if ((int)$sn->fetchColumn() === 0) {
        return ['pass' => false, 'msg' => 'The anonymous student was not notified of the admin response'];
    }
    return ['pass' => true, 'msg' => "{$tid} saved anonymously; hidden on the ticket page, list, name search, CSV and admin alerts; student still tracks it and was notified of the reply"];
});

// --- Test 41: ISO 25010 results entry ---
runTest("ISO 25010 Results (no scores until entered, validation, saved and shown)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $pdo->exec("DELETE FROM iso_evaluation_results");
    $rep = makeHttpRequest("{$baseUrl}/admin/reports.php", 'GET', [], $adminCookie);
    if (strpos($rep['body'], 'Not yet evaluated') === false || strpos($rep['body'], '4.88') !== false) {
        return ['pass' => false, 'msg' => 'Reports page shows ISO scores before any survey results were entered'];
    }
    makeHttpRequest("{$baseUrl}/actions/iso_results_action.php", 'POST', ['action' => 'save', 'mean' => ['usability' => '6.10'], 'respondents' => ['usability' => '30']], $adminCookie);
    if ((int)$pdo->query("SELECT COUNT(*) FROM iso_evaluation_results")->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'An out-of-range mean (6.10) was accepted'];
    }
    makeHttpRequest("{$baseUrl}/actions/iso_results_action.php", 'POST', ['action' => 'save', 'survey_date' => date('Y-m-d'),
        'mean' => ['usability' => '4.37', 'security' => '4.12'], 'respondents' => ['usability' => '30', 'security' => '30']], $studentCookie);
    if ((int)$pdo->query("SELECT COUNT(*) FROM iso_evaluation_results")->fetchColumn() !== 0) {
        return ['pass' => false, 'msg' => 'A student could save ISO results'];
    }
    makeHttpRequest("{$baseUrl}/actions/iso_results_action.php", 'POST', ['action' => 'save', 'survey_date' => date('Y-m-d'),
        'mean' => ['usability' => '4.37', 'security' => '4.12'], 'respondents' => ['usability' => '30', 'security' => '30']], $adminCookie);
    $rep = makeHttpRequest("{$baseUrl}/admin/reports.php", 'GET', [], $adminCookie);
    $ok = strpos($rep['body'], '4.37') !== false && strpos($rep['body'], 'Strongly Agree') !== false
        && strpos($rep['body'], 'Agree') !== false && strpos($rep['body'], '30 respondents') !== false;
    $pdo->exec("DELETE FROM iso_evaluation_results");
    if (!$ok) {
        return ['pass' => false, 'msg' => 'Saved ISO results were not shown with their interpretation'];
    }
    return ['pass' => true, 'msg' => 'No scores shown before entry; 6.10 and student entry refused; 4.37 (Strongly Agree) and 4.12 (Agree) saved and shown'];
});

// --- Test 42: Claim a masterlist profile (search -> verify student number -> create password) ---
runTest("Masterlist Profile Claim (search, details pop-up, student number verified before password)", function() use ($baseUrl, $pdo) {
    $pdo->exec("DELETE FROM student_masterlist WHERE student_no IN ('TEST-CLM-0001', 'TEST-CLM-0002')");
    $pdo->exec("INSERT INTO student_masterlist (student_no, name, email, department, program) VALUES
        ('TEST-CLM-0001', 'Claimtest, Zyra B.', 'zyra.claim.autotest@example.com', 'College of Engineering', 'BS Civil Engineering'),
        ('TEST-CLM-0002', 'Noemail Claimtest', NULL, NULL, 'BS Mathematics')");
    $jar = tempnam(sys_get_temp_dir(), 'cook_claim_');
    $page = makeHttpRequest("{$baseUrl}/register.php", 'GET', [], $jar);
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $m)) {
        return ['pass' => false, 'msg' => 'register.php has no CSRF token'];
    }
    $csrf = $m[1];
    if (strpos($page['body'], 'id="findName"') === false || strpos($page['body'], 'id="goManual"') === false) {
        return ['pass' => false, 'msg' => 'Search step / manual registration button missing'];
    }
    $lookup = function (string $q) use ($baseUrl, $jar, $csrf) {
        $ch = curl_init("{$baseUrl}/actions/register_lookup.php?q=" . urlencode($q));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . $csrf, 'Accept: application/json'], CURLOPT_TIMEOUT => 10]);
        $out = json_decode((string)curl_exec($ch), true);
        unset($ch);
        return $out['matches'] ?? [];
    };
    $claim = fn(array $data) => json_decode(makeHttpRequest("{$baseUrl}/actions/register_claim.php", 'POST', $data + ['csrf_token' => $csrf], $jar)['body'], true) ?: [];

    // Search shows the record (masked number/email, college + program) without leaking full details
    $found = $lookup('zyra claim');
    $z = $found[0] ?? null;
    if (!$z || $z['display_name'] !== 'Zyra B. Claimtest' || $z['department'] !== 'College of Engineering' || $z['program'] !== 'BS Civil Engineering'
        || $z['has_account'] || $z['needs_email'] || strpos($z['student_no'], '0001') !== false || strpos((string)$z['email'], 'zyra.claim') !== false) {
        return ['pass' => false, 'msg' => 'Search did not return the masked record: ' . json_encode($z)];
    }
    $ref = $z['ref'];
    $pwd = ['password' => 'Passw0rd1', 'password_confirm' => 'Passw0rd1'];

    // Cannot create the account before verifying, nor with a wrong number
    if (($claim(['claim_ref' => $ref, 'student_no' => 'TEST-CLM-0001'] + $pwd)['status'] ?? '') !== 'error') {
        return ['pass' => false, 'msg' => 'Account created without verifying the student number first'];
    }
    if (($claim(['action' => 'verify', 'claim_ref' => $ref, 'student_no' => 'TEST-CLM-9999'])['status'] ?? '') !== 'error') {
        return ['pass' => false, 'msg' => 'A wrong student number was accepted'];
    }
    if (($claim(['action' => 'verify', 'claim_ref' => $ref, 'student_no' => 'test-clm-0001'])['status'] ?? '') !== 'ok') {
        return ['pass' => false, 'msg' => 'The correct student number was not accepted'];
    }
    if (($claim(['claim_ref' => $ref, 'student_no' => 'TEST-CLM-9999'] + $pwd)['status'] ?? '') !== 'error') {
        return ['pass' => false, 'msg' => 'Number swapped after verification was accepted'];
    }
    $claim(['action' => 'verify', 'claim_ref' => $ref, 'student_no' => 'TEST-CLM-0001']);
    // The email on record wins, even if a different one is posted
    $ok = $claim(['claim_ref' => $ref, 'student_no' => 'TEST-CLM-0001', 'email' => 'someone.else.autotest@example.com'] + $pwd);
    $acct = $pdo->query("SELECT email, department, program, status, masterlist_verified FROM students WHERE student_no = 'TEST-CLM-0001'")->fetch();
    if (($ok['status'] ?? '') !== 'ok' || !$acct || $acct['email'] !== 'zyra.claim.autotest@example.com' || $acct['status'] !== 'Active'
        || (int)$acct['masterlist_verified'] !== 1 || $acct['program'] !== 'BS Civil Engineering' || $acct['department'] !== 'College of Engineering') {
        return ['pass' => false, 'msg' => 'Claimed account is wrong: ' . json_encode($acct)];
    }
    $login = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => 'zyra.claim.autotest@example.com', 'password' => 'Passw0rd1'], tempnam(sys_get_temp_dir(), 'cook_cl2_'));
    if (strpos($login['headers'], 'student/dashboard.php') === false) {
        return ['pass' => false, 'msg' => 'Claimed account could not sign in'];
    }
    if (($lookup('zyra claim')[0]['state'] ?? '') !== 'Has an account') {
        return ['pass' => false, 'msg' => 'Claimed record is not shown as "Has an account"'];
    }

    // Record without an email: asks for one
    $n = $lookup('noemail claim')[0] ?? null;
    if (!$n || !$n['needs_email']) {
        return ['pass' => false, 'msg' => 'Record without email did not ask for one'];
    }
    $claim(['action' => 'verify', 'claim_ref' => $n['ref'], 'student_no' => 'TEST-CLM-0002']);
    if (($claim(['claim_ref' => $n['ref'], 'student_no' => 'TEST-CLM-0002'] + $pwd)['field'] ?? '') !== 'email'
        || ($claim(['claim_ref' => $n['ref'], 'student_no' => 'TEST-CLM-0002', 'email' => 'noemail.claim.autotest@example.com'] + $pwd)['status'] ?? '') !== 'ok') {
        return ['pass' => false, 'msg' => 'Claim of a record without email failed'];
    }

    // Lockout after repeated wrong numbers
    $pdo->exec("INSERT INTO student_masterlist (student_no, name) VALUES ('TEST-CLM-0003', 'Lockout Claimtest')");
    $l = $lookup('lockout claim')[0]['ref'] ?? '';
    for ($i = 0; $i < 5; $i++) {
        $claim(['action' => 'verify', 'claim_ref' => $l, 'student_no' => 'TEST-CLM-99' . $i]);
    }
    $locked = $claim(['action' => 'verify', 'claim_ref' => $l, 'student_no' => 'TEST-CLM-0003']);
    @unlink($jar);
    if (($locked['status'] ?? '') !== 'error' || stripos($locked['message'] ?? '', 'too many') === false) {
        return ['pass' => false, 'msg' => 'No lockout after 5 wrong student numbers'];
    }
    return ['pass' => true, 'msg' => 'Masked record shown; no account without verification; wrong/swapped numbers refused; record email + course used; sign-in works; no-email record asks for one; lockout after 5 tries'];
});

// --- Test 43: Every page loads without PHP errors ---
runTest("Page Smoke Test (every admin, student and public page loads without PHP errors)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    $concernId = (int)$pdo->query("SELECT concern_id FROM concerns WHERE student_id = 1 ORDER BY concern_id LIMIT 1")->fetchColumn();
    $pages = [
        [null, 'index.php'], [null, 'register.php'], [null, 'register.php?manual=1'], [null, 'forgot_password.php'],
    ];
    foreach (glob(__DIR__ . '/admin/*.php') as $f) {
        $pages[] = [$adminCookie, 'admin/' . basename($f) . (basename($f) === 'concern_details.php' ? "?id={$concernId}" : '')];
    }
    foreach (glob(__DIR__ . '/student/*.php') as $f) {
        $pages[] = [$studentCookie, 'student/' . basename($f) . (basename($f) === 'concern_details.php' ? "?id={$concernId}" : '')];
    }
    $bad = [];
    foreach ($pages as [$jar, $path]) {
        $r = makeHttpRequest("{$baseUrl}/{$path}", 'GET', [], $jar);
        if ($r['code'] !== 200) {
            $bad[] = "{$path} (HTTP {$r['code']})";
        } elseif (preg_match('/(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught)\s*:/i', strip_tags($r['body']), $m)) {
            $bad[] = "{$path} ({$m[1]})";
        }
    }
    if ($bad) {
        return ['pass' => false, 'msg' => 'Problems: ' . implode(', ', $bad)];
    }
    return ['pass' => true, 'msg' => count($pages) . ' pages returned 200 with no PHP errors'];
});

// --- Test 44: Edit a masterlist record ---
runTest("Masterlist Edit (fix details, student number locked once registered, optional account update)", function() use ($baseUrl, $adminCookie, $pdo) {
    $pdo->exec("DELETE FROM students WHERE student_no LIKE 'TEST-EDT-%'");
    $pdo->exec("DELETE FROM student_masterlist WHERE student_no LIKE 'TEST-EDT-%'");
    $pdo->exec("INSERT INTO student_masterlist (student_no, name, email, program) VALUES
        ('TEST-EDT-0001', 'Typo Nmae', 'wrong.edit.autotest@example.com', 'BSIT'),
        ('TEST-EDT-0002', 'Reg Istered', 'old.edit.autotest@example.com', 'BSCS')");
    $pdo->exec("INSERT INTO students (student_no, name, email, password, department, program, status)
        VALUES ('TEST-EDT-0002', 'Reg Istered', 'old.edit.autotest@example.com', '" . password_hash('x', PASSWORD_DEFAULT) . "',
                'College of Computing & Information Sciences', 'BSCS', 'Active')");
    $idOf = fn(string $no) => (int)$pdo->query("SELECT masterlist_id FROM student_masterlist WHERE student_no = '{$no}'")->fetchColumn();
    $id1 = $idOf('TEST-EDT-0001');
    $id2 = $idOf('TEST-EDT-0002');
    $edit = fn(array $d) => makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'update'] + $d, $adminCookie);

    // Page shows the Edit button and modal
    $page = makeHttpRequest("{$baseUrl}/admin/masterlist.php?q=TEST-EDT", 'GET', [], $adminCookie);
    if (strpos($page['body'], 'data-edit-entry=') === false || strpos($page['body'], 'id="editEntryModal"') === false) {
        return ['pass' => false, 'msg' => 'Edit button / modal missing on the masterlist page'];
    }

    // 1. Unregistered record: everything can be fixed, including the student number
    $edit(['masterlist_id' => $id1, 'student_no' => 'test-edt-0003', 'name' => 'Typo Name', 'email' => 'Right.Edit.Autotest@example.com',
           'department' => 'College of Engineering', 'program' => 'BS Information Technology']);
    $r = $pdo->query("SELECT * FROM student_masterlist WHERE masterlist_id = {$id1}")->fetch();
    if ($r['student_no'] !== 'TEST-EDT-0003' || $r['name'] !== 'Typo Name' || $r['email'] !== 'right.edit.autotest@example.com'
        || $r['department'] !== 'College of Engineering' || $r['program'] !== 'BS Information Technology') {
        return ['pass' => false, 'msg' => 'Unregistered record was not updated: ' . json_encode($r)];
    }
    // Duplicate student number and invalid email are refused
    $edit(['masterlist_id' => $id1, 'student_no' => 'TEST-EDT-0002', 'name' => 'Typo Name']);
    $edit(['masterlist_id' => $id1, 'student_no' => 'TEST-EDT-0003', 'name' => 'Typo Name', 'email' => 'not-an-email']);
    $r = $pdo->query("SELECT student_no, email FROM student_masterlist WHERE masterlist_id = {$id1}")->fetch();
    if ($r['student_no'] !== 'TEST-EDT-0003' || $r['email'] !== 'right.edit.autotest@example.com') {
        return ['pass' => false, 'msg' => 'A duplicate number or invalid email was saved'];
    }

    // 2. Registered record: number stays locked; corrections copied to the account when asked
    $edit(['masterlist_id' => $id2, 'student_no' => 'TEST-EDT-9999', 'name' => 'Istered, Reg A.', 'email' => 'new.edit.autotest@example.com',
           'department' => 'College of Engineering', 'program' => 'BS Computer Science', 'sync_account' => 1]);
    $m = $pdo->query("SELECT * FROM student_masterlist WHERE masterlist_id = {$id2}")->fetch();
    $s = $pdo->query("SELECT * FROM students WHERE student_no = 'TEST-EDT-0002'")->fetch();
    if ($m['student_no'] !== 'TEST-EDT-0002' || !$s || $s['email'] !== 'new.edit.autotest@example.com' || $s['name'] !== 'Reg A. Istered'
        || $s['department'] !== 'College of Engineering' || $s['program'] !== 'BS Computer Science') {
        return ['pass' => false, 'msg' => 'Registered record / account sync wrong: ' . json_encode([$m['student_no'], $s])];
    }
    // Without the checkbox the account is left alone
    $edit(['masterlist_id' => $id2, 'student_no' => 'TEST-EDT-0002', 'name' => 'Istered, Reg A.', 'email' => 'other.edit.autotest@example.com', 'program' => 'BS Computer Science']);
    if ($pdo->query("SELECT email FROM students WHERE student_no = 'TEST-EDT-0002'")->fetchColumn() !== 'new.edit.autotest@example.com') {
        return ['pass' => false, 'msg' => 'Account changed although "also update the account" was not ticked'];
    }
    // An email already used by someone else is refused (nothing saved)
    $edit(['masterlist_id' => $id2, 'student_no' => 'TEST-EDT-0002', 'name' => 'Istered, Reg A.', 'email' => 'juan.delacruz@student.com', 'sync_account' => 1]);
    if ($pdo->query("SELECT email FROM student_masterlist WHERE masterlist_id = {$id2}")->fetchColumn() === 'juan.delacruz@student.com') {
        return ['pass' => false, 'msg' => "Another account's email was accepted"];
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM admin_activity_logs WHERE action = 'masterlist_entry_updated' AND target_id = {$id2}")->fetchColumn() < 1) {
        return ['pass' => false, 'msg' => 'Edit not recorded in the activity log'];
    }
    $pdo->exec("DELETE FROM students WHERE student_no LIKE 'TEST-EDT-%'");
    return ['pass' => true, 'msg' => 'Details fixed; duplicate number and bad email refused; number locked for a registered student; account updated only when ticked; taken email refused; logged'];
});

// --- Test 45: Email routing (real students receive mail, made-up demo addresses go to the demo inbox) ---
runTest("Email Routing (real addresses delivered, demo addresses redirected)", function() {
    $cfg = ['redirect_all_to' => '', 'demo_redirect_to' => 'demo.inbox@gmail.com',
            'demo_recipients' => ['@school.edu', '@student.com', 'facilities@snsu.edu.ph']];
    $cases = [
        'real.student@gmail.com'    => null,
        'someone@snsu.edu.ph'       => null,
        'Juan@Student.com'          => 'demo.inbox@gmail.com',
        'admin@school.edu'          => 'demo.inbox@gmail.com',
        'facilities@snsu.edu.ph'    => 'demo.inbox@gmail.com',
        'demo.inbox@gmail.com'      => null,
    ];
    foreach ($cases as $to => $want) {
        if (mailRedirectTarget($cfg, $to) !== $want) {
            return ['pass' => false, 'msg' => "{$to} routed to " . var_export(mailRedirectTarget($cfg, $to), true)];
        }
    }
    if (mailRedirectTarget(['redirect_all_to' => 'all@gmail.com'] + $cfg, 'real.student@gmail.com') !== 'all@gmail.com') {
        return ['pass' => false, 'msg' => 'redirect_all_to did not redirect everything'];
    }
    return ['pass' => true, 'msg' => 'Real student emails delivered directly; demo domains/addresses redirected; redirect_all_to still overrides'];
});

// --- Test 46: Records are archived, never deleted ---
runTest("Archive Instead of Delete (tickets, masterlist, rejected registrations, ratings kept)", function() use ($baseUrl, $adminCookie, $studentCookie, $pdo) {
    // Tickets: open ones cannot be archived; finished ones leave the queue but stay everywhere else
    $title = 'Archive probe broken faucet ' . time();
    makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', ['action' => 'create', 'category_id' => 1, 'title' => $title,
        'description' => 'The faucet in the second floor restroom keeps leaking water.'], $studentCookie);
    $c = $pdo->prepare("SELECT concern_id, ticket_id FROM concerns WHERE title = :t");
    $c->execute(['t' => $title]);
    $row = $c->fetch();
    if (!$row) {
        return ['pass' => false, 'msg' => 'Probe concern was not created'];
    }
    $id = (int)$row['concern_id'];
    $tid = $row['ticket_id'];
    $archivedAt = fn() => $pdo->query("SELECT archived_at FROM concerns WHERE concern_id = {$id}")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/concern_admin_action.php", 'POST', ['action' => 'delete', 'concern_id' => $id], $adminCookie);
    if ($archivedAt() !== null) {
        return ['pass' => false, 'msg' => 'An open ticket was archived'];
    }
    $pdo->exec("UPDATE concerns SET status_id = 3, resolved_at = NOW() WHERE concern_id = {$id}");
    makeHttpRequest("{$baseUrl}/actions/concern_admin_action.php", 'POST', ['action' => 'delete', 'concern_id' => $id], $adminCookie);
    if ($archivedAt() === null || (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE concern_id = {$id}")->fetchColumn() !== 1) {
        return ['pass' => false, 'msg' => 'Resolved ticket was not archived (or was deleted)'];
    }
    $queue = makeHttpRequest("{$baseUrl}/admin/concerns.php?q=" . urlencode($tid), 'GET', [], $adminCookie);
    $arch  = makeHttpRequest("{$baseUrl}/admin/concerns.php?archived=1&q=" . urlencode($tid), 'GET', [], $adminCookie);
    $mine  = makeHttpRequest("{$baseUrl}/student/my_concerns.php?q=" . urlencode($tid), 'GET', [], $studentCookie);
    $page  = makeHttpRequest("{$baseUrl}/admin/concern_details.php?id={$id}", 'GET', [], $adminCookie);
    $listed = fn(string $html) => strpos($html, ">{$tid}<") !== false;
    if ($listed($queue['body']) || !$listed($arch['body']) || !$listed($mine['body'])
        || strpos($page['body'], 'This ticket was archived') === false) {
        return ['pass' => false, 'msg' => 'Archived ticket shown in the wrong places'];
    }
    makeHttpRequest("{$baseUrl}/actions/concern_admin_action.php", 'POST', ['action' => 'restore', 'concern_id' => $id], $adminCookie);
    if ($archivedAt() !== null) {
        return ['pass' => false, 'msg' => 'Ticket was not restored'];
    }

    // Masterlist: archived entries cannot be found on the Register page; restoring brings them back
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'add', 'student_no' => 'TEST-ARC-0001', 'name' => 'Archie Vedentry'], $adminCookie);
    $mid = (int)$pdo->query("SELECT masterlist_id FROM student_masterlist WHERE student_no = 'TEST-ARC-0001'")->fetchColumn();
    $jar = tempnam(sys_get_temp_dir(), 'cook_arc_');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', makeHttpRequest("{$baseUrl}/register.php", 'GET', [], $jar)['body'], $m);
    $find = function () use ($baseUrl, $jar, $m) {
        $ch = curl_init("{$baseUrl}/actions/register_lookup.php?q=" . urlencode('archie vedentry'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . ($m[1] ?? '')], CURLOPT_TIMEOUT => 10]);
        $out = json_decode((string)curl_exec($ch), true);
        unset($ch);
        return count($out['matches'] ?? []);
    };
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'delete', 'masterlist_id' => $mid], $adminCookie);
    $kept = (int)$pdo->query("SELECT COUNT(*) FROM student_masterlist WHERE masterlist_id = {$mid} AND archived_at IS NOT NULL")->fetchColumn();
    if ($kept !== 1 || $find() !== 0) {
        return ['pass' => false, 'msg' => 'Masterlist entry not archived, or still searchable on Register'];
    }
    makeHttpRequest("{$baseUrl}/actions/masterlist_action.php", 'POST', ['action' => 'restore', 'masterlist_id' => $mid], $adminCookie);
    $found = $find();
    @unlink($jar);
    if ($found !== 1) {
        return ['pass' => false, 'msg' => 'Restored masterlist entry not searchable'];
    }

    // Rejected registrations stay on record and are listed for administrators
    if ((int)$pdo->query("SELECT COUNT(*) FROM registration_rejections WHERE student_no = 'TEST-PEND-0003' AND reason = 'Not enrolled'")->fetchColumn() < 1) {
        return ['pass' => false, 'msg' => 'Rejected registration was not kept on record'];
    }
    if (strpos(makeHttpRequest("{$baseUrl}/admin/users.php", 'GET', [], $adminCookie)['body'], 'Rejected Registrations') === false) {
        return ['pass' => false, 'msg' => 'Rejected Registrations record not shown on the Users page'];
    }

    // Reopening a ticket keeps the old rating on record (detached from the ticket)
    if ((int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE concern_id IS NULL AND subject LIKE '%before it was reopened%'")->fetchColumn() < 1) {
        return ['pass' => false, 'msg' => 'The rating of a reopened ticket was deleted'];
    }
    return ['pass' => true, 'msg' => 'Open ticket refused; resolved ticket archived (out of queue, still in student history), restored; masterlist archive hides from Register; rejections and old ratings kept'];
});

// --- Test 47: AI engine understands Filipino / Cebuano and negation ---
runTest("AI Engine: Tagalog & Cebuano keywords, negation and harmless phrases", function() use ($baseUrl, $studentCookie) {
    $cases = [
        ['May sunog sa canteen', 'Nag-uusok ang kusina, delikado, may nakuryente.', 'Facilities', 'Critical'],
        ['Kalayo sa building', 'Naay kalayo sa ikaduhang andana, daghan na ang aso.', 'Facilities', 'Critical'],
        ['Usok sa lab', 'Walang tigil ang usok sa computer lab!', 'Facilities', 'Critical'],
        ['Fire! Not a drill', 'This is not a drill, there is a fire in the canteen!', 'Services', 'Critical'],
        ['Walang kuryente', 'Walang kuryente sa buong building simula kaninang umaga.', 'Facilities', 'High'],
        ['Sirang upuan', 'Maraming sirang upuan sa Room 101, may pagsusulit kami bukas.', 'Facilities', 'High'],
        ['Gi-bully ko', 'Gibully ko sa akong classmate, gihulga pa ko.', 'Student Welfare', 'High'],
        ['Hugaw nga CR', 'Hugaw kaayo ang CR ug walay tubig.', 'Facilities', 'Medium'],
        ['Fire drill schedule', 'There is no fire, just asking when the fire drill is.', 'Services', 'Low'],
    ];
    foreach ($cases as [$t, $d, $cat, $want]) {
        $r = calculateDynamicUrgency($t, $d, $cat);
        if ($r['priority'] !== $want) {
            return ['pass' => false, 'msg' => "\"{$t}\" scored {$r['priority']} ({$r['score']}), expected {$want}"];
        }
    }
    $neg = calculateDynamicUrgency('Fire drill schedule', 'There is no fire, just asking when the fire drill is.', 'Services');
    if ($neg['is_rrt_alert'] || strpos(implode(' ', $neg['reasons']), 'Negated mentions not counted: fire') === false) {
        return ['pass' => false, 'msg' => 'Negated "no fire" still raised an alert or was not explained'];
    }
    if (calculateDynamicUrgency('Sirang upuan', 'Maraming sirang upuan sa classroom', '')['suggested_category'] !== 'Facilities') {
        return ['pass' => false, 'msg' => 'Tagalog text did not suggest the Facilities category'];
    }
    // The live preview API gives the same answer
    $api = json_decode(makeHttpRequest("{$baseUrl}/api/ai_prioritize.php", 'POST',
        ['title' => 'May sunog sa canteen', 'description' => 'Nag-uusok ang kusina, delikado.', 'category_id' => 1], $studentCookie)['body'], true);
    if (($api['data']['priority'] ?? '') !== 'Critical' || ($api['data']['is_rrt_alert'] ?? 0) !== 1) {
        return ['pass' => false, 'msg' => 'Live preview API did not return Critical for a Tagalog fire report'];
    }
    return ['pass' => true, 'msg' => count($cases) . ' Tagalog/Cebuano/English cases scored correctly; "no fire" not counted and explained; category suggested; live API agrees'];
});

// --- Test 48: Students see in plain days when their concern should be fixed ---
runTest("Expected Fix Time (days shown to students, time left, typical time of similar concerns)", function() use ($baseUrl, $studentCookie, $pdo) {
    if (slaTargetText(24) !== 'within 1 day' || slaTargetText(72) !== 'within 3 days' || formatDurationLong(2 * 86400 + 4 * 3600) !== '2 days 4 hours') {
        return ['pass' => false, 'msg' => 'Day wording helpers are wrong'];
    }
    $id = (int)$pdo->query("SELECT concern_id FROM concerns WHERE student_id = 1 AND status_id IN (1, 2) ORDER BY concern_id LIMIT 1")->fetchColumn();
    $page = makeHttpRequest("{$baseUrl}/student/concern_details.php?id={$id}", 'GET', [], $studentCookie)['body'];
    if (strpos($page, 'When Will It Be Fixed?') === false || !preg_match('/Expected to be fixed.{0,200}?\d+ days?/s', strip_tags($page))
        || !preg_match('/(Time left|Overdue by)/', $page)) {
        return ['pass' => false, 'msg' => 'Student concern page does not show the expected fix time in days'];
    }
    $form = makeHttpRequest("{$baseUrl}/student/submit_concern.php", 'GET', [], $studentCookie)['body'];
    if (strpos($form, 'Expected to be fixed:') === false) {
        return ['pass' => false, 'msg' => 'Submit form preview does not say when it will be fixed'];
    }
    $t = typicalResolutionTime($pdo, 'Medium', 0);
    return ['pass' => true, 'msg' => 'Shown as days with time left / overdue; submit preview explains it' . ($t ? '; similar Medium concerns take ~' . formatDurationLong((int)round($t['hours'] * 3600)) : '')];
});

// --- Test 49: Recovery code (self-service password reset without email or administrator) ---
runTest("Recovery Code (shown once at registration, resets password, single use, lockout, profile regenerate)", function() use ($baseUrl, $pdo) {
    $no = 'TEST-RC-0001';
    $email = 'recovery.code.autotest@example.com';
    $pdo->exec("DELETE FROM student_masterlist WHERE student_no = '{$no}'");
    $pdo->exec("INSERT INTO student_masterlist (student_no, name) VALUES ('{$no}', 'Codey, Rhea C.')");
    $jar = tempnam(sys_get_temp_dir(), 'cook_rc_');
    $reg = makeHttpRequest("{$baseUrl}/actions/register_action.php", 'POST', [
        'student_no' => $no, 'name' => 'Rhea C. Codey', 'email' => $email, 'department' => 'College of Computing & Information Sciences',
        'program' => 'BS Information Technology', 'password' => 'Passw0rd1', 'password_confirm' => 'Passw0rd1',
    ], $jar);
    $page = makeHttpRequest("{$baseUrl}/recovery_code.php", 'GET', [], $jar)['body'];
    if (strpos($reg['headers'], 'recovery_code.php') === false || !preg_match('/data-code="([A-Z0-9-]{14})"/', $page, $m)) {
        return ['pass' => false, 'msg' => 'No recovery code shown after registering'];
    }
    $code = $m[1];
    // Shown once only
    if (strpos(makeHttpRequest("{$baseUrl}/recovery_code.php", 'GET', [], $jar)['body'], $code) !== false) {
        return ['pass' => false, 'msg' => 'Recovery code was shown a second time'];
    }
    $hash = $pdo->query("SELECT recovery_code_hash FROM students WHERE student_no = '{$no}'")->fetchColumn();
    if (!$hash || str_contains($hash, str_replace('-', '', $code))) {
        return ['pass' => false, 'msg' => 'Recovery code not stored as a hash'];
    }
    $reset = fn(string $id, string $c, string $pw) => makeHttpRequest("{$baseUrl}/actions/password_reset_action.php", 'POST',
        ['action' => 'recovery', 'identifier' => $id, 'recovery_code' => $c, 'password' => $pw, 'password_confirm' => $pw], $jar);
    $canLogIn = function (string $pw) use ($baseUrl, $no) {
        $j = tempnam(sys_get_temp_dir(), 'cook_rcl_');
        $r = makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $no, 'password' => $pw], $j);
        @unlink($j);
        return strpos($r['headers'], 'student/dashboard.php') !== false;
    };

    // Wrong code -> refused, password unchanged
    $reset($no, 'AAAA-BBBB-CCCC', 'NewPassw0rd2');
    if ($canLogIn('NewPassw0rd2') || !$canLogIn('Passw0rd1')) {
        return ['pass' => false, 'msg' => 'A wrong recovery code changed the password'];
    }
    // Right code (typed in lower case without dashes) -> new password, new code shown
    $ok = $reset($no, strtolower(str_replace('-', ' ', $code)), 'NewPassw0rd2');
    $page = makeHttpRequest("{$baseUrl}/recovery_code.php", 'GET', [], $jar)['body'];
    if (strpos($ok['headers'], 'recovery_code.php') === false || !$canLogIn('NewPassw0rd2')
        || !preg_match('/data-code="([A-Z0-9-]{14})"/', $page, $m2) || $m2[1] === $code) {
        return ['pass' => false, 'msg' => 'Correct recovery code did not reset the password and issue a new code'];
    }
    // The used code no longer works
    $reset($email, $code, 'Another0ne3');
    if ($canLogIn('Another0ne3')) {
        return ['pass' => false, 'msg' => 'A used recovery code worked a second time'];
    }
    // Lockout after repeated wrong codes (the correct new code is then refused too)
    for ($i = 0; $i < 5; $i++) {
        $reset('lockout-' . $no, 'ZZZZ-ZZZZ-ZZZ' . $i, 'Whatever1x');
    }
    $locked = makeHttpRequest("{$baseUrl}/actions/password_reset_action.php", 'POST',
        ['action' => 'recovery', 'identifier' => 'lockout-' . $no, 'recovery_code' => $m2[1], 'password' => 'Whatever1x', 'password_confirm' => 'Whatever1x'], $jar);
    $flash = makeHttpRequest("{$baseUrl}/forgot_password.php", 'GET', [], $jar)['body'];
    if (stripos($flash, 'Too many incorrect attempts') === false) {
        return ['pass' => false, 'msg' => 'No lockout after repeated wrong recovery codes'];
    }

    // Profile: a signed-in student regenerates the code only with the current password
    $sj = tempnam(sys_get_temp_dir(), 'cook_rcp_');
    makeHttpRequest("{$baseUrl}/actions/auth_action.php", 'POST', ['identifier' => $no, 'password' => 'NewPassw0rd2'], $sj);
    $before = $pdo->query("SELECT recovery_code_hash FROM students WHERE student_no = '{$no}'")->fetchColumn();
    makeHttpRequest("{$baseUrl}/actions/recovery_code_action.php", 'POST', ['current_password' => 'wrong-password1'], $sj);
    if ($pdo->query("SELECT recovery_code_hash FROM students WHERE student_no = '{$no}'")->fetchColumn() !== $before) {
        return ['pass' => false, 'msg' => 'Recovery code regenerated without the current password'];
    }
    $gen = makeHttpRequest("{$baseUrl}/actions/recovery_code_action.php", 'POST', ['current_password' => 'NewPassw0rd2'], $sj);
    $profile = makeHttpRequest("{$baseUrl}/student/profile.php", 'GET', [], $sj)['body'];
    @unlink($sj);
    @unlink($jar);
    if (strpos($gen['headers'], 'recovery_code.php') === false || $pdo->query("SELECT recovery_code_hash FROM students WHERE student_no = '{$no}'")->fetchColumn() === $before
        || strpos($profile, 'Recovery Code') === false) {
        return ['pass' => false, 'msg' => 'Profile could not regenerate the recovery code'];
    }

    // Email health: a failed SMTP attempt switches Forgot Password away from "email me a link"
    $file = mailHealthFile();
    $saved = is_file($file) ? file_get_contents($file) : null;
    file_put_contents($file, json_encode(['ok' => false, 'at' => date('c'), 'error' => 'test']));
    $healthy = mailDeliveryHealthy();
    $saved === null ? @unlink($file) : file_put_contents($file, $saved);
    if ($healthy) {
        return ['pass' => false, 'msg' => 'A failed SMTP attempt was not remembered'];
    }
    return ['pass' => true, 'msg' => 'Code shown once and hashed; wrong code refused; right code resets and issues a new one; used code dead; lockout; profile regenerate needs password; SMTP failure detected'];
});

// --- Test 50: Gemini AI prioritization (answer parsing, thresholds, fallback to the rules) ---
runTest("Gemini AI Prioritization (structured answer, same thresholds, rule-based fallback)", function() use ($baseUrl, $studentCookie, $pdo) {
    $cats = ['Academic', 'Facilities', 'Services', 'Student Welfare'];
    $wrap = fn(string $json) => json_encode(['candidates' => [['content' => ['parts' => [['text' => $json]]]]]]);

    // A normal Gemini answer
    $p = aiParseGeminiResponse($wrap('{"score": 91, "category": "facilities", "reasons": ["Smoke and possible fire in the canteen", "People are at risk"]}'), $cats);
    if (!$p || $p['score'] !== 91 || $p['category'] !== 'Facilities' || count($p['reasons']) !== 2) {
        return ['pass' => false, 'msg' => 'Valid Gemini answer not parsed: ' . json_encode($p)];
    }
    // Fix-time estimate: kept when sensible, dropped when missing or out of range
    $f = aiParseGeminiResponse($wrap('{"score": 90, "category": "Facilities", "reasons": ["Wet outlet"], "fix_hours": 4, "fix_note": "Cut power and replace the outlet"}'), $cats);
    if (($f['fix_hours'] ?? null) !== 4 || ($f['fix_note'] ?? '') !== 'Cut power and replace the outlet'
        || aiParseGeminiResponse($wrap('{"score": 90, "category": "Facilities", "reasons": ["x"], "fix_hours": 999}'), $cats)['fix_hours'] !== null
        || aiParseGeminiResponse($wrap('{"score": 50, "category": "Services", "reasons": ["x"]}'), $cats)['fix_hours'] !== null) {
        return ['pass' => false, 'msg' => 'Fix-time estimate not handled correctly: ' . json_encode($f)];
    }
    // Answers wrapped in a code fence still work; thought parts are ignored
    $fenced = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'thinking...', 'thought' => true], ['text' => "```json\n{\"score\": 45, \"category\": \"Services\", \"reasons\": [\"Slow wifi\"]}\n```"]]]]]]);
    if ((aiParseGeminiResponse($fenced, $cats)['score'] ?? 0) !== 45) {
        return ['pass' => false, 'msg' => 'Fenced / thought answer not parsed'];
    }
    // Bad answers are rejected (so the rules take over)
    foreach (['{"score": 0, "category": "Facilities", "reasons": []}', '{"score": 140, "category": "Facilities", "reasons": []}', 'not json', '{"category": "Facilities"}'] as $bad) {
        if (aiParseGeminiResponse($wrap($bad), $cats) !== null) {
            return ['pass' => false, 'msg' => "Invalid answer accepted: {$bad}"];
        }
    }
    if (aiParseGeminiResponse('{"error": {"code": 429}}', $cats) !== null) {
        return ['pass' => false, 'msg' => 'An API error body was accepted'];
    }
    // Same thresholds as the rule-based engine
    foreach ([95 => 'Critical', 80 => 'Critical', 79 => 'High', 60 => 'High', 59 => 'Medium', 40 => 'Medium', 39 => 'Low', 1 => 'Low'] as $score => $want) {
        if (aiPriorityForScore($score) !== $want) {
            return ['pass' => false, 'msg' => "Score {$score} mapped to " . aiPriorityForScore($score)];
        }
    }
    // Without the AI (test flag / no key) the rules answer and say so
    $r = prioritizeConcern('May sunog sa canteen', 'Nag-uusok ang kusina, delikado.', 'Facilities', $cats);
    if ($r['engine'] !== 'rules' || $r['priority'] !== 'Critical' || $r['engine_note'] === '') {
        return ['pass' => false, 'msg' => 'Fallback to the rule-based engine failed: ' . json_encode($r)];
    }
    // Live preview reports the engine; a submitted concern records it
    $csrf = getCsrfToken($studentCookie);
    $ch = curl_init("{$baseUrl}/api/ai_prioritize.php");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $studentCookie, CURLOPT_COOKIEJAR => $studentCookie,
        CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . $csrf], CURLOPT_POSTFIELDS => ['title' => 'Broken chairs in Room 101', 'description' => 'Many chairs are broken before our exam.', 'category_id' => 1]]);
    $api = json_decode((string)curl_exec($ch), true);
    unset($ch);
    if (($api['data']['engine'] ?? '') !== 'rules' || !isset($api['engine'])) {
        return ['pass' => false, 'msg' => 'Preview API does not report the engine: ' . json_encode($api)];
    }
    $title = 'Engine probe flooded hallway ' . time();
    makeHttpRequest("{$baseUrl}/actions/concern_action.php", 'POST', ['action' => 'create', 'category_id' => 1, 'title' => $title,
        'description' => 'The hallway near the electrical room is flooded with water.'], $studentCookie);
    $e = $pdo->prepare("SELECT ai_engine FROM concerns WHERE title = :t");
    $e->execute(['t' => $title]);
    if ($e->fetchColumn() !== 'rules') {
        return ['pass' => false, 'msg' => 'Submitted concern did not record which engine was used'];
    }
    return ['pass' => true, 'msg' => 'Gemini JSON parsed (fences/thoughts handled), bad answers rejected, thresholds match, rules fallback works and is recorded'];
});

// --- Test 21: Logout & Session Invalidation ---
runTest("Authentication Logout & Session Invalidation", function() use ($baseUrl, $studentCookie) {
    // GET must NOT log the user out (CSRF-safe logout)
    makeHttpRequest("{$baseUrl}/logout.php", 'GET', [], $studentCookie);
    $stillIn = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $studentCookie);
    if ($stillIn['code'] !== 200) {
        return ['pass' => false, 'msg' => 'A plain GET to logout.php ended the session'];
    }
    $logoutResp = makeHttpRequest("{$baseUrl}/logout.php", 'POST', [], $studentCookie);
    if ($logoutResp['code'] !== 302 || strpos($logoutResp['headers'], 'Location: index.php') === false) {
        return ['pass' => false, 'msg' => 'Logout did not redirect to index.php'];
    }

    // Trying to access student dashboard after logout should redirect to login
    $dashAfter = makeHttpRequest("{$baseUrl}/student/dashboard.php", 'GET', [], $studentCookie);
    if ($dashAfter['code'] === 200 && strpos($dashAfter['body'], 'Student Portal') !== false) {
        return ['pass' => false, 'msg' => 'Session was not cleared after logout'];
    }

    return ['pass' => true, 'msg' => 'Session destroyed and access redirected to index.php'];
});

// Cleanup temp cookie jars
@unlink($studentCookie);
@unlink($adminCookie);

// Remove every record the suite created so seed data stays pristine (attachments go with their concerns: ON DELETE CASCADE)
$pdo->prepare("DELETE FROM concerns WHERE concern_id > :id")->execute(['id' => $startMaxConcernId]);
$pdo->prepare("DELETE FROM feedback WHERE feedback_id > :id")->execute(['id' => $startMaxFeedbackId]);
$pdo->prepare("DELETE FROM students WHERE student_id > :id")->execute(['id' => $startMaxStudentId]);
$pdo->prepare("DELETE FROM notifications WHERE notification_id > :id")->execute(['id' => $startMaxNotifId]);
$pdo->prepare("DELETE FROM concern_audit_logs WHERE log_id > :id")->execute(['id' => $startMaxAuditId]);
$pdo->prepare("DELETE FROM password_reset_requests WHERE request_id > :id")->execute(['id' => $startMaxResetId]);
$pdo->prepare("DELETE FROM email_outbox WHERE email_id > :id")->execute(['id' => $startMaxEmailId]);
$pdo->prepare("DELETE FROM password_reset_tokens WHERE token_id > :id")->execute(['id' => $startMaxTokenId]);
$pdo->prepare("DELETE FROM admin_activity_logs WHERE activity_id > :id")->execute(['id' => $startMaxActivityId]);
$pdo->prepare("DELETE FROM categories WHERE category_id > :id AND category_id NOT IN (SELECT category_id FROM concerns)")->execute(['id' => $startMaxCategoryId]);
// Test-only cleanup (the application itself never deletes these records)
$pdo->prepare("DELETE FROM announcements WHERE announcement_id > :id")->execute(['id' => $startMaxAnnouncementId]);
$pdo->prepare("DELETE FROM registration_rejections WHERE rejection_id > :id")->execute(['id' => $startMaxRejectionId]);
$pdo->exec("UPDATE administrators SET archived_at = NULL, archived_by = NULL WHERE admin_id IN (1, 2, 3) AND archived_at IS NOT NULL");
$pdo->exec("DELETE FROM login_attempts WHERE identifier LIKE '%autotest%' OR identifier LIKE 'juan.delacruz%' OR identifier LIKE 'TEST-%' OR identifier LIKE 'claim:TEST-%' OR identifier LIKE 'recovery:%TEST-%'");
$pdo->exec("DELETE FROM students WHERE student_no LIKE 'TEST-%'");
$pdo->prepare("DELETE FROM student_masterlist WHERE student_no LIKE 'TEST-%' OR student_no = :n")->execute(['n' => $regNo]);
$pdo->exec("ALTER TABLE concerns AUTO_INCREMENT = " . ($startMaxConcernId + 1));
$pdo->exec("ALTER TABLE students AUTO_INCREMENT = " . ($startMaxStudentId + 1));
echo "\n  Cleanup: removed test concerns, uploads, users, feedback, notifications and audit rows.\n";

echo "\n======================================================================\n";
echo "  FINAL TEST SUMMARY: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . ")\n";
if ($failed === 0) {
    echo "  \033[32mALL {$passed} SYSTEM TESTS PASSED WITH 100% SUCCESS!\033[0m\n";
} else {
    echo "  \033[31mSOME TESTS FAILED. PLEASE REVIEW LOG ABOVE.\033[0m\n";
}
echo "======================================================================\n\n";

exit($failed === 0 ? 0 : 1);

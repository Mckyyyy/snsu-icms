<?php
// database/demo_reset.php - Clean the database and load a fresh, realistic demo set (for the defense)
//
// Run it with scripts/reset_demo_data.php (CLI only, makes a backup first).
//
// What it does:
//   1. Deletes all activity: concerns, attachments, responses, audit trail, notifications, feedback,
//      announcements, chat, email outbox, reset requests, login attempts and admin activity.
//   2. Deletes every student account except the six demo students (IDs 1-6) and removes the
//      listed test entries from the masterlist. The masterlist itself is kept.
//   3. Loads the seed concerns again, re-dates them (refreshDemoData) and adds more concerns
//      across every category, priority and status, with responses, ratings, chat and announcements.
//   Every concern is analyzed by the AI (Google Gemini when available, otherwise the rule-based engine),
//   exactly as if a student had submitted it.

require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/demo_data.php';

/** Masterlist rows that were only made for testing (removed on reset). */
const DEMO_RESET_REMOVE_MASTERLIST = ['SNSU-24-2025', 'SNSU-2025-123456', 'SNSU-2026-01234'];

function resetDemoDatabase(PDO $pdo, bool $useAi = true, ?callable $log = null): array {
    $log = $log ?? function (string $msg): void {};
    $removedStudents = $pdo->query("SELECT CONCAT(name, ' <', email, '>') FROM students WHERE student_id > 6 ORDER BY student_id")->fetchAll(PDO::FETCH_COLUMN);

    // ---- 1. Clean ----
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    try {
        foreach ([
            'concern_attachments', 'responses', 'concern_audit_logs', 'notifications', 'feedback', 'concerns',
            'announcements', 'message_reactions', 'message_hidden', 'messages', 'conversations', 'chat_presence',
            'email_outbox', 'password_reset_tokens', 'password_reset_requests', 'login_attempts',
            'admin_activity_logs', 'registration_rejections', 'profile_photos',
        ] as $table) {
            if (tableExists($pdo, $table)) {
                $pdo->exec("DELETE FROM `{$table}`");
                $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }
        $pdo->exec("DELETE FROM students WHERE student_id > 6");
        $pdo->exec("ALTER TABLE students AUTO_INCREMENT = 7");
        $in = implode(',', array_map([$pdo, 'quote'], DEMO_RESET_REMOVE_MASTERLIST));
        $pdo->exec("DELETE FROM student_masterlist WHERE student_no IN ({$in})");
        // Demo accounts back to a clean state (passwords are left as they are)
        $pdo->exec("UPDATE students SET status = 'Active', archived_at = NULL, archived_by = NULL, must_change_password = 0,
                    recovery_code_hash = NULL, recovery_code_set_at = NULL, last_login_at = NULL WHERE student_id <= 6");
        $pdo->exec("UPDATE administrators SET status = 'Active', archived_at = NULL, archived_by = NULL, must_change_password = 0");
        $pdo->exec("UPDATE categories SET archived_at = NULL, archived_by = NULL WHERE category_id <= 4");
    } finally {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    $log('Cleaned all records; removed ' . count($removedStudents) . ' student accounts.');

    // ---- 2. Seed concerns, responses, announcements and feedback ----
    foreach (splitSqlStatements(file_get_contents(__DIR__ . '/seed.sql')) as $stmt) {
        if (preg_match('/^\s*INSERT INTO `(concerns|responses|announcements|feedback)`/i', $stmt)) {
            $pdo->exec($stmt);
        }
    }
    $ai = demoAiAnalyzer($pdo, $useAi, $log);
    foreach ($pdo->query("SELECT concern_id FROM concerns ORDER BY concern_id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ai((int)$id);
    }
    refreshDemoData($pdo);
    foreach ($pdo->query("SELECT concern_id FROM concerns WHERE ai_engine IS NULL")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ai((int)$id);
    }
    $log('Loaded and re-dated the seed concerns.');

    // ---- 3. More concerns, chat and announcements ----
    $extra = addMoreDemoConcerns($pdo, $ai);
    addDemoAnnouncementsAndChat($pdo);
    $log("Added {$extra} more concerns, announcements and chat messages.");
    @unlink(__DIR__ . '/../storage/sla_monitor.last');

    $engines = $pdo->query("SELECT ai_engine, COUNT(*) FROM concerns GROUP BY ai_engine")->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['removed_students' => $removedStudents, 'concerns' => array_sum($engines), 'ai_engines' => $engines];
}

/**
 * Returns fn(int $concernId) that runs the AI on a concern and stores the result
 * (priority, score, reasons, engine, fix estimate, SLA). Gemini is paced to stay inside the free-tier limit.
 */
function demoAiAnalyzer(PDO $pdo, bool $useAi, callable $log): callable {
    $categoryNames = activeCategoryNames($pdo);
    @unlink(aiBackoffFile());
    $lastCall = 0.0;
    return function (int $id) use ($pdo, $useAi, $log, $categoryNames, &$lastCall): void {
        $row = $pdo->prepare("SELECT c.title, c.description, c.date_submitted, cat.category_name FROM concerns c JOIN categories cat ON cat.category_id = c.category_id WHERE c.concern_id = :id");
        $row->execute(['id' => $id]);
        $c = $row->fetch();
        if (!$c) {
            return;
        }
        $result = null;
        if ($useAi && aiConfig()['provider'] === 'gemini' && trim((string)aiConfig()['gemini_api_key']) !== '') {
            for ($try = 1; $try <= 3 && $result === null; $try++) {
                $wait = 4.2 - (microtime(true) - $lastCall);   // ≤ 15 requests per minute
                if ($wait > 0) {
                    usleep((int)($wait * 1e6));
                }
                $lastCall = microtime(true);
                try {
                    $result = geminiPrioritize($c['title'], $c['description'], $c['category_name'], $categoryNames);
                } catch (Throwable $e) {
                    $log("  Gemini try {$try} failed for concern {$id}: " . mb_substr($e->getMessage(), 0, 120));
                    @unlink(aiBackoffFile());
                    sleep(in_array((int)$e->getCode(), [429, 503], true) ? 20 : 3);
                }
            }
        }
        if ($result === null) {
            $result = calculateDynamicUrgency($c['title'], $c['description'], $c['category_name']);
            $result += ['engine' => 'rules', 'fix_hours' => null, 'fix_note' => null];
        }
        $pdo->prepare("
            UPDATE concerns SET priority = :p, ai_urgency_score = :s, ai_reasons = :r, ai_engine = :e,
                   ai_fix_hours = :fh, ai_fix_note = :fn, sla_hours = :h, is_rrt_alert = :rrt,
                   sla_due_at = DATE_ADD(date_submitted, INTERVAL :h2 HOUR)
            WHERE concern_id = :id
        ")->execute([
            'p' => $result['priority'], 's' => $result['score'], 'r' => json_encode($result['reasons']),
            'e' => $result['engine'], 'fh' => $result['fix_hours'], 'fn' => $result['fix_note'],
            'h' => $result['sla_hours'], 'h2' => $result['sla_hours'], 'rrt' => $result['is_rrt_alert'],
            'id' => $id,
        ]);
        $log(sprintf('  #%d %-9s %3d/100 (%s)  %s', $id, $result['priority'], $result['score'], $result['engine'], mb_substr($c['title'], 0, 50)));
    };
}

/** More concerns spread over the last two weeks, so the dashboards and reports have data. */
function addMoreDemoConcerns(PDO $pdo, callable $ai): int {
    $now = time();
    $at  = fn(float $hoursAgo) => date('Y-m-d H:i:s', (int)round($now - $hoursAgo * 3600));
    $cat = $pdo->query("SELECT category_name, category_id FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
    $statusName = [1 => 'Pending', 2 => 'In Progress', 3 => 'Resolved'];
    $adminName = $pdo->query("SELECT admin_id, name FROM administrators")->fetchAll(PDO::FETCH_KEY_PAIR);

    // [student, category, title, description, hours ago, status, assigned admin, anonymous,
    //  responses [[hours after submit, admin, status, text, department]], rating [stars, comment] or null]
    $concerns = [
        [3, 'Facilities', 'Aircon not working in Room 201',
         'The air-conditioning unit in Room 201 of the CCIS building has not been working since Monday. The room gets very hot in the afternoon and around 40 students attend classes there.',
         330, 3, 2, 0, [[5, 2, 2, 'Aircon technician scheduled to check the unit tomorrow morning.', 'Facilities & Maintenance'],
                        [30, 2, 3, 'Compressor capacitor replaced. The unit is cooling normally again.', 'Facilities & Maintenance']],
         [5, 'Fixed in about a day. The room is comfortable again, thank you!']],
        [4, 'Services', 'Enrollment portal very slow during peak hours',
         'The online enrollment portal takes several minutes to load and often times out between 8 AM and 11 AM. Some of us could not finish enlisting our subjects.',
         300, 3, 1, 0, [[3, 1, 2, 'Reported to MIS. They are adding server capacity for the enrollment period.', 'MIS / IT Services'],
                        [50, 1, 3, 'MIS upgraded the enrollment server. Page loads are now under 3 seconds during peak hours.', 'MIS / IT Services']],
         [4, 'Much faster now, but it took two days.']],
        [6, 'Facilities', 'Leaking faucet in the 2nd floor comfort room, Engineering building',
         'One faucet in the male comfort room on the 2nd floor of the Engineering building keeps running and cannot be closed. Water is being wasted the whole day.',
         280, 3, 2, 0, [[8, 2, 3, 'Plumber replaced the faucet cartridge. No more leaking.', 'Facilities & Maintenance']],
         [4, 'Quick repair. Thank you.']],
        [2, 'Academic', 'Grade for IT 211 not yet encoded',
         'My final grade for IT 211 (Data Structures) is still not showing in the student portal even though the deadline for encoding was last week.',
         260, 3, 3, 0, [[12, 3, 2, 'We contacted the instructor. The grade sheet is being reviewed by the department.', 'Registrar / Academic Affairs'],
                        [40, 3, 3, 'Grades for IT 211 are now encoded and visible in the student portal.', 'Registrar / Academic Affairs']],
         [5, 'My grade is now visible. Thank you, Dean Ramos.']],
        [5, 'Student Welfare', 'Scholarship stipend delayed for two months',
         'Our CHED scholarship stipend for September and October has not been released yet. Many of us rely on it for transportation and board.',
         240, 3, 3, 0, [[20, 3, 2, 'Endorsed to the Scholarship Office. They are waiting for the fund transfer.', 'Office of Student Affairs'],
                        [130, 3, 3, 'The stipend for September and October has been released. Please check your accounts.', 'Office of Student Affairs']],
         [3, 'Released, but it took more than five days to get an update.']],
        [4, 'Facilities', 'Broken door knob in Room 105',
         'The door knob of Room 105 is broken and the door cannot be locked. Equipment inside the room is not secure after classes.',
         200, 3, 2, 0, [[6, 2, 3, 'New lockset installed in Room 105.', 'Facilities & Maintenance']],
         [4, 'Door can be locked again.']],
        [2, 'Services', 'Library closes earlier than the posted schedule',
         'The library posted that it is open until 7 PM but the staff has been closing it at 5 PM this week. We cannot study there after classes.',
         170, 3, 1, 0, [[10, 1, 3, 'The Library confirmed the 7 PM schedule. A second staff member is now assigned for the evening shift.', 'Student Services']],
         [4, 'Open until 7 PM again.']],
        [3, 'Facilities', 'Flickering lights in Room 302 causing headaches',
         'Most of the fluorescent lights in Room 302 are flickering. Several classmates complain of headaches and eye strain during long lectures.',
         140, 3, 2, 0, [[4, 2, 2, 'Electrician will replace the tubes and ballasts this afternoon.', 'Facilities & Maintenance'],
                        [20, 2, 3, 'All lights in Room 302 replaced with LED tubes.', 'Facilities & Maintenance']],
         [5, 'Much better lighting now!']],
        [1, 'Academic', 'Mabagal ang pag-release ng TOR sa Registrar',
         'Nag-request ako ng Transcript of Records noong isang linggo para sa aking scholarship application pero hanggang ngayon wala pa. Kailangan ko na ito sa Biyernes.',
         50, 2, 3, 0, [[6, 3, 2, 'Your TOR request is being processed. It will be ready for pickup on Thursday.', 'Registrar / Academic Affairs']], null],
        [6, 'Academic', 'Request for additional sections of CS 321',
         'Only one section of CS 321 was opened and it is already full. At least 30 students still need the subject to graduate on time.',
         96, 2, 3, 0, [[18, 3, 2, 'Forwarded to the CCIS Dean. A second section is being considered.', 'Academic Affairs / CCIS']], null],
        [5, 'Facilities', 'Water dispenser empty in the CCIS lobby',
         'The water dispenser in the CCIS lobby has been empty for three days. Students have to go to the canteen to buy water.',
         30, 2, 2, 0, [[5, 2, 2, 'Water refill delivery requested from the supplier.', 'Facilities & Maintenance']], null],
        [3, 'Student Welfare', 'Classmates being bullied in our block group chat',
         'Some students in our block group chat keep posting insulting messages and edited photos of two classmates. One of them stopped attending classes. Please help.',
         10, 1, null, 1, [], null],
        [6, 'Services', 'No Wi-Fi signal in the Engineering building',
         'The campus Wi-Fi cannot be detected anywhere in the Engineering building since yesterday. We cannot access the LMS for our online quizzes.',
         14, 1, null, 0, [], null],
        [4, 'Facilities', 'Broken stair railing near the gymnasium',
         'The metal railing of the stairs near the gymnasium is loose and partly detached. Students hold on to it while going down and someone could fall.',
         6, 1, null, 0, [], null],
        [5, 'Facilities', 'Guba ang electric fan sa Room 108',
         'Ang duha ka electric fan sa Room 108 dili na molihok. Init kaayo sa sulod labi na sa hapon.',
         20, 1, null, 0, [], null],
    ];

    $ins = $pdo->prepare("
        INSERT INTO concerns (ticket_id, student_id, is_anonymous, category_id, status_id, assigned_admin_id, title, description,
                              priority, ai_urgency_score, sla_hours, date_submitted)
        VALUES (:t, :s, :anon, :cat, 1, NULL, :title, :desc, 'Medium', 50, 72, :sub)
    ");
    $insResp = $pdo->prepare("INSERT INTO responses (concern_id, admin_id, response_text, department_endorsed, status_id, date_responded) VALUES (:c, :a, :t, :d, :s, :at)");
    $insAudit = $pdo->prepare("INSERT INTO concern_audit_logs (concern_id, actor_type, actor_id, action, old_value, new_value, created_at) VALUES (:c, :type, :actor, :action, :old, :new, :at)");
    $insNotif = $pdo->prepare("INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message, is_read, created_at) VALUES ('student', :r, :c, :type, :title, :msg, :read, :at)");
    $insRating = $pdo->prepare("
        INSERT INTO feedback (student_id, concern_id, student_name, student_email, feedback_type, subject, message, rating, submitted_at)
        SELECT s.student_id, :c, s.name, s.email, 'Resolution Rating', :subj, :msg, :rating, :at FROM students s WHERE s.student_id = :s
    ");

    foreach ($concerns as [$student, $catName, $title, $desc, $ago, $statusId, $assigned, $anon, $responses, $rating]) {
        $submitted = $at($ago);
        $ins->execute(['t' => 'TMP-' . bin2hex(random_bytes(6)), 's' => $student, 'anon' => $anon, 'cat' => $cat[$catName],
                       'title' => $title, 'desc' => $desc, 'sub' => $submitted]);
        $id = (int)$pdo->lastInsertId();
        $ticket = formatTicketId($id, date('Y', strtotime($submitted)));
        $pdo->prepare("UPDATE concerns SET ticket_id = :t WHERE concern_id = :id")->execute(['t' => $ticket, 'id' => $id]);
        $ai($id);
        $priority = $pdo->query("SELECT CONCAT(priority, ', SLA ', sla_hours, 'h') FROM concerns WHERE concern_id = {$id}")->fetchColumn();

        $insAudit->execute(['c' => $id, 'type' => 'student', 'actor' => $student, 'action' => 'created', 'old' => null, 'new' => 'Pending', 'at' => $submitted]);
        $insAudit->execute(['c' => $id, 'type' => 'system', 'actor' => null, 'action' => 'ai_prioritized', 'old' => null, 'new' => $priority, 'at' => $submitted]);
        if ($assigned) {
            $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => 1, 'action' => 'assigned', 'old' => null,
                'new' => $adminName[$assigned] ?? 'Administrator', 'at' => $at($ago - max(0.2, ($responses[0][0] ?? 1) / 2))]);
        }
        $prev = 'Pending';
        $first = null;
        $resolved = null;
        foreach ($responses as [$after, $adminId, $st, $text, $dept]) {
            $when = $at($ago - $after);
            $first = $first ?? $when;
            if ($st === 3) {
                $resolved = $when;
            }
            $insResp->execute(['c' => $id, 'a' => $adminId, 't' => $text, 'd' => $dept, 's' => $st, 'at' => $when]);
            $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => $adminId, 'action' => 'responded', 'old' => null, 'new' => $text, 'at' => $when]);
            if ($statusName[$st] !== $prev) {
                $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => $adminId, 'action' => 'status_changed', 'old' => $prev, 'new' => $statusName[$st], 'at' => $when]);
                $prev = $statusName[$st];
            }
            $insNotif->execute(['r' => $student, 'c' => $id, 'type' => $st === 3 ? 'status_change' : 'response',
                'title' => $st === 3 ? "{$ticket} is now Resolved" : "{$ticket}: new response",
                'msg' => mb_substr($text, 0, 250), 'read' => $ago - $after > 24 ? 1 : 0, 'at' => $when]);
        }
        $pdo->prepare("UPDATE concerns SET status_id = :st, assigned_admin_id = :a, first_response_at = :f, resolved_at = :r WHERE concern_id = :id")
            ->execute(['st' => $statusId, 'a' => $assigned, 'f' => $first, 'r' => $statusId === 3 ? $resolved : null, 'id' => $id]);
        if ($rating && $resolved) {
            $insRating->execute(['c' => $id, 'subj' => "Resolution of {$ticket}", 'msg' => $rating[1], 'rating' => $rating[0],
                'at' => date('Y-m-d H:i:s', strtotime($resolved) + 3 * 3600), 's' => $student]);
        }
        // Admins are told about new concerns (the newest ones are still unread)
        $isRrt = (int)$pdo->query("SELECT is_rrt_alert FROM concerns WHERE concern_id = {$id}")->fetchColumn();
        $pdo->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message, is_read, created_at)
            SELECT 'admin', admin_id, :c, :type, :title, :msg, :read, :at FROM administrators
        ")->execute(['c' => $id, 'type' => $isRrt ? 'rrt' : 'new_concern', 'title' => ($isRrt ? 'RRT Critical Alert: ' : 'New concern: ') . $ticket,
                     'msg' => $title, 'read' => $ago > 24 ? 1 : 0, 'at' => $submitted]);
    }
    return count($concerns);
}

function addDemoAnnouncementsAndChat(PDO $pdo): void {
    $now = time();
    $at  = fn(float $hoursAgo) => date('Y-m-d H:i:s', (int)round($now - $hoursAgo * 3600));
    $day = fn(int $n, string $fmt = 'F j, Y') => date($fmt, strtotime("+{$n} days"));

    $ann = $pdo->prepare("INSERT INTO announcements (admin_id, title, content, category, is_urgent, published_at) VALUES (:a, :t, :c, :cat, :u, :at)");
    $ann->execute(['a' => 1, 't' => 'Enrollment for the Second Semester', 'cat' => 'Academic', 'u' => 0, 'at' => $at(72),
        'c' => 'Online enrollment for the second semester opens on ' . $day(10) . '. Settle your clearance at your college before enlisting your subjects.']);
    $ann->execute(['a' => 2, 't' => 'Water Interruption Advisory', 'cat' => 'Maintenance', 'u' => 1, 'at' => $at(3),
        'c' => 'There will be no water supply in the main campus on ' . $day(2, 'F j') . ' from 8:00 AM to 12:00 NN due to pipe repairs near the gymnasium. Please store water ahead of time.']);

    // Chat between students and the office
    $conv = $pdo->prepare("INSERT INTO conversations (student_id, created_at) VALUES (:s, :at)");
    $msg  = $pdo->prepare("INSERT INTO messages (conversation_id, sender_type, sender_id, body, created_at, read_at, updated_at) VALUES (:c, :type, :id, :b, :at, :read, :at2)");
    foreach ([
        1 => [[30, 'student', 1, 'Good morning po. May I ask if the projector in Room 304 will be fixed before our report on Friday?'],
              [29.5, 'admin', 1, 'Good morning, Juan. Your concern is already in our queue. We will update your ticket once the technician has checked it.'],
              [29.4, 'student', 1, 'Thank you po!']],
        2 => [[6, 'student', 2, 'Hello, is the Guidance Office open on Saturday for counseling?'],
              [5.5, 'admin', 3, 'Hi Maria. Yes, there are slots on Saturday from 8 AM to 12 NN. You can also follow your concern ticket for the schedule.']],
    ] as $studentId => $lines) {
        $conv->execute(['s' => $studentId, 'at' => $at($lines[0][0])]);
        $cid = (int)$pdo->lastInsertId();
        $last = null;
        foreach ($lines as $i => [$ago, $type, $sender, $body]) {
            $read = $i < count($lines) - 1 || $ago > 12 ? $at($ago - 0.1) : null;
            $msg->execute(['c' => $cid, 'type' => $type, 'id' => $sender, 'b' => $body, 'at' => $at($ago), 'read' => $read, 'at2' => $at($ago)]);
            $last = [(int)$pdo->lastInsertId(), $at($ago)];
        }
        $pdo->prepare("UPDATE conversations SET last_message_id = :m, last_message_at = :at WHERE conversation_id = :c")
            ->execute(['m' => $last[0], 'at' => $last[1], 'c' => $cid]);
    }
}

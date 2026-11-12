<?php
// database/demo_data.php - Make the seed data look current for demos and the defense
//
// The seed concerns were written with fixed 2024 dates, which made every ticket
// "SLA breached by 900+ days". This re-dates the seed rows relative to *now* and
// gives them a realistic mix: on track, at risk, breached, met, missed, an active
// Rapid Response (Critical) ticket, assignments, ratings and an audit trail.
//
// Run any time (it is idempotent):
//   /Applications/XAMPP/xamppfiles/bin/php scripts/refresh_demo_data.php
// It also runs automatically right after the seed is applied on a fresh install.
// Only the original seed records (concern IDs 1-11) and the demo rows it adds are touched.

function refreshDemoData(PDO $pdo): array {
    $now = time();
    $at  = fn(float $hoursAgo) => date('Y-m-d H:i:s', (int)round($now - $hoursAgo * 3600));
    $sla = ['Critical' => 24, 'High' => 48, 'Medium' => 72, 'Low' => 120];

    // id => [hours ago submitted, status_id, assigned admin, [responses: hours after submit, admin, status, text, dept]]
    $plan = [
        11 => [3,   1, 2, [[0.25, 2, 1, 'RAPID RESPONSE TEAM DISPATCHED: CCIS Lab 1 main circuit breaker switched off. Facilities electrical crew en route with high priority.', 'Facilities & Maintenance']]],
        10 => [40,  1, null, []],
        5  => [30,  2, 1, [[4, 1, 2, 'We have already endorsed this to the maintenance team. Replacement chairs are being pulled from storage.', 'Facilities & Maintenance']]],
        4  => [80,  1, null, []],
        7  => [20,  1, null, []],
        9  => [60,  2, 1, [[6, 1, 2, 'MIS confirmed the duplicate-number bug in the ID printing software and is preparing a patch.', 'MIS / IT Services']]],
        8  => [50,  2, 3, [[10, 3, 2, 'Request forwarded to the University Library. Two additional copies are being ordered.', 'Registrar / Academic Affairs']]],
        1  => [80,  2, 1, [[5, 1, 2, 'Technician dispatched from the vendor to service the ID printer card feeder.', 'MIS / IT Services']]],
        2  => [150, 3, 1, [[6, 1, 2, 'Meeting scheduled with the canteen concessionaire.', 'Student Services'],
                           [28, 1, 3, 'Food warmer heating elements inspected and replaced. Food is now served at the proper temperature.', 'Student Services']]],
        3  => [110, 3, 3, [[20, 3, 3, 'Exam room schedule reconciled with the CCIS Dean office. IT 312 moved to Rm 402.', 'Academic Affairs / CCIS']]],
        6  => [220, 3, 3, [[30, 3, 2, 'Checking the LMS attendance logs with the instructor.', null],
                           [90, 3, 3, 'Attendance corrected in the LMS after verifying the submission timestamp.', 'Registrar / Academic Affairs']]],
    ];

    $existing = $pdo->query("SELECT concern_id, priority FROM concerns WHERE concern_id BETWEEN 1 AND 11")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($existing) < 11) {
        return ['skipped' => 'seed concerns not found'];
    }

    $pdo->beginTransaction();
    try {
        $ids = implode(',', array_keys($plan));
        $pdo->exec("DELETE FROM responses WHERE concern_id IN ({$ids})");
        $pdo->exec("DELETE FROM concern_audit_logs WHERE concern_id IN ({$ids})");
        $pdo->exec("DELETE FROM notifications WHERE concern_id IN ({$ids})");
        $pdo->exec("DELETE FROM feedback WHERE concern_id IN ({$ids})");

        $updConcern = $pdo->prepare("
            UPDATE concerns SET ticket_id = :ticket, status_id = :st, assigned_admin_id = :asg, date_submitted = :sub,
                   sla_hours = :h, sla_due_at = :due, sla_alert_level = 'none', first_response_at = :first,
                   resolved_at = :res, reopen_count = 0, last_reopened_at = NULL, withdrawn_at = NULL, withdraw_reason = NULL, edited_at = NULL, edit_count = 0
            WHERE concern_id = :id
        ");
        $insResp = $pdo->prepare("
            INSERT INTO responses (concern_id, admin_id, response_text, department_endorsed, status_id, date_responded)
            VALUES (:c, :a, :t, :d, :s, :at)
        ");
        $insAudit = $pdo->prepare("
            INSERT INTO concern_audit_logs (concern_id, actor_type, actor_id, action, old_value, new_value, created_at)
            VALUES (:c, :type, :actor, :action, :old, :new, :at)
        ");
        $statusName = [1 => 'Pending', 2 => 'In Progress', 3 => 'Resolved'];
        $adminName = $pdo->query("SELECT admin_id, name FROM administrators")->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($plan as $id => [$ago, $statusId, $assigned, $responses]) {
            $priority  = $existing[$id];
            $hours     = $sla[$priority] ?? 72;
            $submitted = $at($ago);
            $first     = $responses ? $at($ago - $responses[0][0]) : null;
            $resolved  = null;
            foreach ($responses as $r) {
                if ($r[2] === 3) {
                    $resolved = $at($ago - $r[0]);
                }
            }
            $updConcern->execute([
                'ticket' => formatTicketId($id, date('Y', strtotime($submitted))),
                'st'     => $statusId,
                'asg'    => $assigned,
                'sub'    => $submitted,
                'h'      => $hours,
                'due'    => date('Y-m-d H:i:s', strtotime($submitted) + $hours * 3600),
                'first'  => $first,
                'res'    => $statusId === 3 ? $resolved : null,
                'id'     => $id,
            ]);

            $insAudit->execute(['c' => $id, 'type' => 'student', 'actor' => null, 'action' => 'created', 'old' => null, 'new' => 'Pending', 'at' => $submitted]);
            $insAudit->execute(['c' => $id, 'type' => 'system', 'actor' => null, 'action' => 'ai_prioritized', 'old' => null, 'new' => "{$priority}, SLA {$hours}h", 'at' => $submitted]);
            if ($assigned) {
                // Admin User (#1) triages and assigns; the assignee then responds
                $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => 1, 'action' => 'assigned', 'old' => null,
                    'new' => $adminName[$assigned] ?? 'Administrator', 'at' => $first ?? $at(max(0.1, $ago - 0.2))]);
            }
            $prev = 'Pending';
            foreach ($responses as [$after, $adminId, $st, $text, $dept]) {
                $when = $at($ago - $after);
                $insResp->execute(['c' => $id, 'a' => $adminId, 't' => $text, 'd' => $dept, 's' => $st, 'at' => $when]);
                $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => $adminId, 'action' => 'responded', 'old' => null, 'new' => mb_substr($text, 0, 255), 'at' => $when]);
                if ($statusName[$st] !== $prev) {
                    $insAudit->execute(['c' => $id, 'type' => 'admin', 'actor' => $adminId, 'action' => 'status_changed', 'old' => $prev, 'new' => $statusName[$st], 'at' => $when]);
                    $prev = $statusName[$st];
                }
            }
        }

        // Evidence images for concerns 5 and 11 are stored in the database by createDemoEvidenceImages() below

        // Resolution ratings: one happy, one unhappy (the SLA-missed ticket); concern 3 stays unrated
        $insRating = $pdo->prepare("
            INSERT INTO feedback (student_id, concern_id, student_name, student_email, feedback_type, subject, message, rating, submitted_at)
            SELECT s.student_id, c.concern_id, s.name, s.email, 'Resolution Rating', CONCAT('Resolution of ', c.ticket_id), :msg, :rating, :at
            FROM concerns c JOIN students s ON s.student_id = c.student_id WHERE c.concern_id = :id
        ");
        $insRating->execute(['msg' => 'The food warmers were fixed within a day. Thank you!', 'rating' => 5, 'at' => $at(120), 'id' => 2]);
        $insRating->execute(['msg' => 'It was fixed, but it took almost four days and affected my grade computation.', 'rating' => 2, 'at' => $at(125), 'id' => 6]);

        // A Student Welfare concern so every category has data
        $welfareTitle = 'Request for counseling schedule during exam week';
        $hasWelfare = $pdo->prepare("SELECT concern_id FROM concerns WHERE title = :t");
        $hasWelfare->execute(['t' => $welfareTitle]);
        $welfareId = (int)$hasWelfare->fetchColumn();
        $welfareDesc = 'Several of us in BSCS 2-A are feeling a lot of stress before the midterms. Can the Guidance Office open extra counseling slots this week?';
        $ai = calculateDynamicUrgency($welfareTitle, $welfareDesc, 'Student Welfare');
        $catId = (int)$pdo->query("SELECT category_id FROM categories WHERE category_name = 'Student Welfare'")->fetchColumn();
        $wSub = $at(5);
        if (!$welfareId && $catId) {
            $pdo->prepare("
                INSERT INTO concerns (ticket_id, student_id, category_id, status_id, title, description, priority, ai_urgency_score,
                                      ai_reasons, ai_suggested_category_id, sla_hours, sla_due_at, is_rrt_alert, date_submitted)
                VALUES (:t, 2, :cat, 1, :title, :desc, :p, :score, :reasons, :cat2, :h, :due, 0, :sub)
            ")->execute([
                't' => 'TMP-' . bin2hex(random_bytes(6)), 'cat' => $catId, 'title' => $welfareTitle, 'desc' => $welfareDesc,
                'p' => $ai['priority'], 'score' => $ai['score'], 'reasons' => json_encode($ai['reasons']), 'cat2' => $catId,
                'h' => $ai['sla_hours'], 'due' => date('Y-m-d H:i:s', strtotime($wSub) + $ai['sla_hours'] * 3600), 'sub' => $wSub,
            ]);
            $welfareId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE concerns SET ticket_id = :t WHERE concern_id = :id")->execute(['t' => formatTicketId($welfareId), 'id' => $welfareId]);
        } elseif ($welfareId) {
            $pdo->prepare("UPDATE concerns SET date_submitted = :sub, sla_due_at = :due, status_id = 1, resolved_at = NULL, first_response_at = NULL, sla_alert_level = 'none' WHERE concern_id = :id")
                ->execute(['sub' => $wSub, 'due' => date('Y-m-d H:i:s', strtotime($wSub) + $ai['sla_hours'] * 3600), 'id' => $welfareId]);
        }

        // Announcements and general feedback dated recently
        $pdo->prepare("UPDATE announcements SET published_at = :d WHERE announcement_id = 1")->execute(['d' => $at(26)]);
        $pdo->prepare("UPDATE announcements SET published_at = :d WHERE announcement_id = 2")->execute(['d' => $at(50)]);
        $pdo->prepare("UPDATE announcements SET published_at = :d WHERE announcement_id = 3")->execute(['d' => $at(8)]);
        // Keep the announcement text in step with the demo dates (future events, not 2024)
        $day = fn(int $n, string $fmt = 'F j, Y') => date($fmt, strtotime("+{$n} days"));
        $ann = $pdo->prepare("UPDATE announcements SET content = :c WHERE announcement_id = :id");
        $ann->execute(['id' => 1, 'c' => 'The system will undergo maintenance on ' . $day(4) . ' from 1:00 AM to 3:00 AM. Please save any pending work ahead of time.']);
        $ann->execute(['id' => 2, 'c' => 'ID printing will be available from ' . $day(6, 'F j') . ' to ' . $day(13) . ' at the Admin Office between 8:00 AM and 4:00 PM.']);
        $ann->execute(['id' => 3, 'c' => 'Classes are suspended on ' . $day(3, 'F j, Y (l)') . ' for the university foundation anniversary and school events.']);
        $pdo->prepare("UPDATE feedback SET submitted_at = :d WHERE feedback_id = 1")->execute(['d' => $at(70)]);
        $pdo->prepare("UPDATE feedback SET submitted_at = :d WHERE feedback_id = 2")->execute(['d' => $at(140)]);
        // One answered and one unanswered piece of feedback, so the reply feature is visible
        $pdo->prepare("UPDATE feedback SET admin_reply = :r, replied_by = 1, replied_at = :d WHERE feedback_id = 1")
            ->execute(['r' => 'Thank you, Juan! We are glad the tracking helps. We will keep improving it based on student feedback.', 'd' => $at(60)]);
        $pdo->exec("UPDATE feedback SET admin_reply = NULL, replied_by = NULL, replied_at = NULL WHERE feedback_id = 2");

        // Notifications a student would have received for the seeded activity
        $notif = $pdo->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message, is_read, created_at)
            SELECT 'student', c.student_id, c.concern_id, :type, CONCAT(c.ticket_id, :suffix), :msg, :read, :at FROM concerns c WHERE c.concern_id = :id
        ");
        $notif->execute(['type' => 'status_change', 'suffix' => ' is now In Progress', 'msg' => 'We have already endorsed this to the maintenance team.', 'read' => 0, 'at' => $at(26), 'id' => 5]);
        $notif->execute(['type' => 'response', 'suffix' => ': new response', 'msg' => 'Rapid Response Team dispatched to CCIS Lab 1.', 'read' => 0, 'at' => $at(2.75), 'id' => 11]);
        $notif->execute(['type' => 'status_change', 'suffix' => ' is now Resolved', 'msg' => 'Exam room schedule reconciled with the CCIS Dean office.', 'read' => 1, 'at' => $at(90), 'id' => 3]);

        // Admin-side: the RRT alert and an assignment
        $pdo->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, concern_id, type, title, message, is_read, created_at)
            SELECT 'admin', a.admin_id, 11, 'rrt', CONCAT('RRT Critical Alert: ', c.ticket_id), CONCAT(c.title, ' (Critical)'), 0, :at
            FROM administrators a CROSS JOIN concerns c WHERE c.concern_id = 11
        ")->execute(['at' => $at(3)]);

        // One anonymous concern (personal Student Welfare request), so the feature is visible in the demo
        $pdo->exec("UPDATE concerns SET is_anonymous = IF(concern_id = 12, 1, 0) WHERE concern_id <= 12");

        // Registrar masterlist: the demo students plus enrolled students who have not registered yet
        $ml = $pdo->prepare("
            INSERT INTO student_masterlist (student_no, name, email, department, program, imported_by, imported_at)
            VALUES (:no, :name, :email, :dept, :prog, 1, :at)
            ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), department = VALUES(department), program = VALUES(program), imported_at = VALUES(imported_at)
        ");
        foreach ($pdo->query("SELECT student_no, name, email, department, program FROM students WHERE status <> 'Pending' AND student_id <= 6")->fetchAll() as $st) {
            $ml->execute(['no' => $st['student_no'], 'name' => $st['name'], 'email' => $st['email'], 'dept' => $st['department'], 'prog' => $st['program'], 'at' => $at(240)]);
        }
        foreach ([
            ['SNSU-2024-02233', 'Liza Mae Torres', null, 'College of Computing & Information Sciences', 'BS Information Technology'],
            ['SNSU-2024-04410', 'Ramon Bautista', null, 'College of Engineering', 'BS Electrical Engineering'],
            ['SNSU-2025-00318', 'Grace Ann Lim', 'grace.lim@gmail.com', 'College of Education', 'BEEd'],
            ['SNSU-2025-01150', 'Joshua Navarro', null, 'College of Technology', 'BS Industrial Technology'],
        ] as [$no, $name, $email, $dept, $prog]) {
            $ml->execute(['no' => $no, 'name' => $name, 'email' => $email, 'dept' => $dept, 'prog' => $prog, 'at' => $at(240)]);
        }
        $pdo->exec("UPDATE students SET masterlist_verified = 1 WHERE student_id <= 6");

        // One self-registration waiting for approval (not on the masterlist), so the approval step can be demonstrated
        $pdo->prepare("
            INSERT INTO students (student_no, name, email, password, department, program, status, registration_source,
                                  masterlist_verified, registration_note, created_at)
            VALUES ('SNSU-2025-07777', 'Kevin Lim', 'kevin.lim@gmail.com', :pw, 'College of Computing & Information Sciences',
                    'BS Computer Science', 'Pending', 'self', 0, 'Student number is not on the masterlist.', :at)
            ON DUPLICATE KEY UPDATE status = 'Pending', approved_by = NULL, approved_at = NULL, created_at = VALUES(created_at)
        ")->execute(['pw' => password_hash('student123', PASSWORD_DEFAULT), 'at' => $at(5)]);

        // Administrator activity examples (only once)
        if ((int)$pdo->query("SELECT COUNT(*) FROM admin_activity_logs")->fetchColumn() === 0) {
            $act = $pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, ip_address, created_at) VALUES (:a, :act, :tt, :tid, :d, '127.0.0.1', :at)");
            $t11 = $pdo->query("SELECT ticket_id FROM concerns WHERE concern_id = 11")->fetchColumn();
            $act->execute(['a' => 1, 'act' => 'announcement_published', 'tt' => 'announcement', 'tid' => 3, 'd' => '[Urgent] Class Suspension', 'at' => $at(8)]);
            $act->execute(['a' => 1, 'act' => 'concern_assigned', 'tt' => 'concern', 'tid' => 11, 'd' => "{$t11} → Engr. Carlos Mendoza", 'at' => $at(2.8)]);
            $act->execute(['a' => 1, 'act' => 'announcement_published', 'tt' => 'announcement', 'tid' => 1, 'd' => 'Scheduled Maintenance', 'at' => $at(26)]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $images = createDemoEvidenceImages($pdo);
    return ['concerns' => count($plan) + 1, 'images' => $images];
}

/**
 * Draw two clearly-labelled sample evidence images (GD) and store them in the database
 * as the attachments of demo concerns 11 and 5 (only where that concern has none yet).
 */
function createDemoEvidenceImages(PDO $pdo): int {
    if (!function_exists('imagecreatetruecolor')) {
        return 0;
    }
    $made = 0;
    $specs = [
        'demo_lab1_hazard.jpg'   => [11, 'CCIS Lab 1 - water leak near power strip', [127, 29, 29], [239, 68, 68]],
        'demo_broken_chairs.jpg' => [5, 'Lab 2 - broken student chairs', [30, 58, 138], [96, 165, 250]],
    ];
    $exists = $pdo->prepare("SELECT 1 FROM concerns WHERE concern_id = :id");
    foreach ($specs as $file => [$concernId, $caption, $c1, $c2]) {
        $exists->execute(['id' => $concernId]);
        if (!$exists->fetchColumn() || getConcernAttachmentMeta($pdo, $concernId)) {
            continue;
        }
        $w = 960; $h = 600;
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) { // vertical gradient
            $t = $y / $h;
            $col = imagecolorallocate($im, (int)($c1[0] + ($c2[0] - $c1[0]) * $t), (int)($c1[1] + ($c2[1] - $c1[1]) * $t), (int)($c1[2] + ($c2[2] - $c1[2]) * $t));
            imageline($im, 0, $y, $w, $y, $col);
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $soft  = imagecolorallocatealpha($im, 255, 255, 255, 90);
        imagefilledrectangle($im, 40, 40, $w - 40, $h - 40, $soft);
        imagerectangle($im, 40, 40, $w - 40, $h - 40, $white);
        $scale = 3; // enlarge GD's built-in font for legibility
        foreach ([['SAMPLE EVIDENCE PHOTO', 200], [$caption, 280], ['(demo data - replace with a real photo)', 360]] as [$text, $ty]) {
            $tw = imagefontwidth(5) * strlen($text);
            $tmp = imagecreatetruecolor($tw, imagefontheight(5));
            imagesavealpha($tmp, true);
            imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
            imagestring($tmp, 5, 0, 0, $text, imagecolorallocate($tmp, 255, 255, 255));
            $dw = min($w - 120, $tw * $scale);
            $dh = (int)(imagefontheight(5) * $scale * ($dw / ($tw * $scale)));
            imagecopyresampled($im, $tmp, (int)(($w - $dw) / 2), $ty, 0, 0, (int)$dw, $dh, $tw, imagefontheight(5));
            imagedestroy($tmp);
        }
        ob_start();
        $ok = imagejpeg($im, null, 85);
        $jpeg = (string)ob_get_clean();
        imagedestroy($im);
        if ($ok && $jpeg !== '') {
            saveConcernAttachment($pdo, $concernId, [
                'name' => $file, 'mime' => 'image/jpeg', 'size' => strlen($jpeg),
                'sha256' => hash('sha256', $jpeg), 'data' => $jpeg,
            ]);
            $made++;
        }
    }
    return $made;
}

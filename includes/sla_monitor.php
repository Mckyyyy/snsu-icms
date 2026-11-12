<?php
// includes/sla_monitor.php - Automatic SLA escalation
//
// Finds open concerns that have moved into "at risk" (<= 25% of the SLA window
// left) or "breached" (past the deadline) since the last check, and alerts the
// assigned administrator (or every administrator when unassigned) in the app and
// by email. Each level is sent once per ticket thanks to concerns.sla_alert_level.
//
// Runs automatically on page loads (throttled) and can also be run from cron:
//   /Applications/XAMPP/xamppfiles/bin/php scripts/sla_monitor.php

const SLA_MONITOR_INTERVAL = 120; // seconds between automatic runs

function runSlaMonitor(PDO $pdo): array {
    $ratio = SLA_AT_RISK_RATIO;
    $rows = $pdo->query("
        SELECT c.concern_id, c.ticket_id, c.title, c.priority, c.sla_due_at, c.sla_hours,
               c.sla_alert_level, c.assigned_admin_id, IF(c.is_anonymous = 1, 'Anonymous student', s.name) AS student_name
        FROM concerns c
        JOIN students s ON s.student_id = c.student_id
        WHERE c.status_id IN (1, 2)
          AND c.sla_due_at IS NOT NULL
          AND (
                (c.sla_due_at < NOW() AND c.sla_alert_level <> 'breached')
             OR (c.sla_due_at >= NOW() AND c.sla_alert_level = 'none'
                 AND TIMESTAMPDIFF(SECOND, NOW(), c.sla_due_at) <= c.sla_hours * 3600 * {$ratio})
          )
    ")->fetchAll();

    $sent = ['at_risk' => 0, 'breached' => 0];
    foreach ($rows as $c) {
        $level = strtotime($c['sla_due_at']) < time() ? 'breached' : 'at_risk';

        // Guarded update: only one request wins if two run at the same moment
        $up = $pdo->prepare("UPDATE concerns SET sla_alert_level = :lvl WHERE concern_id = :id AND sla_alert_level = :old");
        $up->execute(['lvl' => $level, 'id' => $c['concern_id'], 'old' => $c['sla_alert_level']]);
        if ($up->rowCount() === 0) {
            continue;
        }

        logConcernAudit($pdo, (int)$c['concern_id'], 'system', null, 'sla_' . $level, $c['sla_alert_level'], $level);

        $due = date('M j, Y · g:i A', strtotime($c['sla_due_at']));
        if ($level === 'breached') {
            $title = "SLA breached: {$c['ticket_id']}";
            $msg   = "\"{$c['title']}\" ({$c['priority']}) passed its {$c['sla_hours']}h deadline ({$due}) without being resolved.";
            $type  = 'sla_breach';
        } else {
            $title = "SLA at risk: {$c['ticket_id']}";
            $msg   = "\"{$c['title']}\" ({$c['priority']}) is due {$due}. Less than 25% of its {$c['sla_hours']}h window remains.";
            $type  = 'sla_risk';
        }

        // Recipients: the assigned admin, otherwise every active admin
        if ($c['assigned_admin_id']) {
            $r = $pdo->prepare("SELECT admin_id, name, email FROM administrators WHERE admin_id = :id AND status = 'Active'");
            $r->execute(['id' => $c['assigned_admin_id']]);
            $recipients = $r->fetchAll();
        } else {
            $recipients = [];
        }
        if (!$recipients) {
            $recipients = $pdo->query("SELECT admin_id, name, email FROM administrators WHERE status = 'Active'")->fetchAll();
        }

        foreach ($recipients as $a) {
            notifyUser($pdo, 'admin', (int)$a['admin_id'], (int)$c['concern_id'], $type, $title, $msg);
            try {
                queueEmail($pdo, $a['email'], $a['name'], $title, [
                    "Hi {$a['name']},",
                    $msg,
                    "Submitted by: {$c['student_name']}",
                    $level === 'breached'
                        ? 'Please respond or resolve the ticket as soon as possible; breaches lower the SLA compliance rate in the reports.'
                        : 'Please act on it before the deadline to keep the SLA compliance rate up.',
                ], appUrl("admin/concern_details.php?id={$c['concern_id']}"), 'Open ticket');
            } catch (Throwable $e) {
                error_log('[ICMS] SLA email failed: ' . $e->getMessage());
            }
        }
        $sent[$level]++;
    }
    return $sent;
}

/**
 * Run the monitor at most once every SLA_MONITOR_INTERVAL seconds (shared across requests).
 */
function runSlaMonitorIfDue(PDO $pdo): void {
    $stamp = __DIR__ . '/../storage/sla_monitor.last';
    if (is_file($stamp) && time() - (int)@filemtime($stamp) < SLA_MONITOR_INTERVAL) {
        return;
    }
    $fh = @fopen($stamp, 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
        return; // another request is already running it
    }
    try {
        @touch($stamp);
        @chmod($stamp, 0666);
        runSlaMonitor($pdo);
    } catch (Throwable $e) {
        error_log('[ICMS] SLA monitor failed: ' . $e->getMessage());
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

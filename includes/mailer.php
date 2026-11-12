<?php
// includes/mailer.php - Email queue + dependency-free SMTP client
//
// Usage: queueEmail($pdo, $to, $name, $subject, $lines, $buttonUrl, $buttonLabel)
// Emails are stored in `email_outbox` first (so a slow/unavailable mail server
// never breaks a submission), then delivered at the end of the request or by
// scripts/process_email_queue.php.

function mailConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/mail.php';
        // While the automated test suite runs it creates this flag, so tests never send real email
        if (is_file(__DIR__ . '/../storage/.mail_log_only')) {
            $cfg['driver'] = 'log';
        }
        // app_url '' = work it out from the address the site is being visited on (handy on web hosts)
        if (trim((string)$cfg['app_url']) === '') {
            $cfg['app_url'] = detectAppUrl();
        }
    }
    return $cfg;
}

/** "https://icms.infinityfreeapp.com" or "http://localhost/TrackingSystem", from the current request. */
function detectAppUrl(): string {
    if (empty($_SERVER['HTTP_HOST'])) {
        return 'http://localhost/TrackingSystem'; // command line: no request to look at
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $root = realpath(__DIR__ . '/..');
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $path = ($docRoot !== '' && str_starts_with($root, $docRoot)) ? str_replace('\\', '/', substr($root, strlen($docRoot))) : '';
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim($path, '/');
}

/**
 * True when emails actually leave the server (used to pick the password-reset mode): SMTP is set up
 * AND the last real delivery attempt did not fail (free web hosts often block outgoing SMTP).
 */
function mailCanDeliver(): bool {
    $cfg = mailConfig();
    return $cfg['driver'] === 'smtp' && $cfg['username'] !== '' && $cfg['password'] !== '' && mailDeliveryHealthy();
}

function mailHealthFile(): string {
    return __DIR__ . '/../storage/mail_health.json';
}

/** False only when the most recent SMTP attempt failed; a later success clears it. */
function mailDeliveryHealthy(): bool {
    $h = @json_decode((string)@file_get_contents(mailHealthFile()), true);
    return !is_array($h) || ($h['ok'] ?? true) !== false;
}

function recordMailHealth(bool $ok, string $error = ''): void {
    if (mailConfig()['driver'] !== 'smtp') {
        return; // log mode says nothing about real delivery
    }
    $file = mailHealthFile();
    $isNew = !is_file($file);
    @file_put_contents($file, json_encode(['ok' => $ok, 'at' => date('c'), 'error' => mb_substr($error, 0, 300)]), LOCK_EX);
    if ($isNew) {
        @chmod($file, 0666); // writable by both Apache and the command-line scripts, whichever created it
    }
}

function appUrl(string $path = ''): string {
    return rtrim(mailConfig()['app_url'], '/') . '/' . ltrim($path, '/');
}

/**
 * Build plain-text + HTML bodies from a list of paragraphs and an optional button.
 */
function buildEmailBodies(string $heading, array $lines, ?string $buttonUrl = null, ?string $buttonLabel = null): array {
    $text = $heading . "\n\n" . implode("\n\n", $lines);
    if ($buttonUrl) {
        $text .= "\n\n" . ($buttonLabel ?? 'Open') . ': ' . $buttonUrl;
    }
    $text .= "\n\n--\nSurigao del Norte State University\nAI-Powered Institutional Concern Management System\nThis is an automated message; please do not reply.";

    $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $paras = '';
    foreach ($lines as $line) {
        $paras .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#334155;">' . nl2br($h($line)) . '</p>';
    }
    $button = $buttonUrl
        ? '<p style="margin:22px 0;"><a href="' . $h($buttonUrl) . '" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;">' . $h($buttonLabel ?? 'Open') . '</a></p>'
        : '';

    $html = '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#0e2246;color:#ffffff;padding:18px 24px;font-size:13px;letter-spacing:.04em;">SNSU &middot; INSTITUTIONAL CONCERN MANAGEMENT</td></tr>'
        . '<tr><td style="padding:24px;">'
        . '<h1 style="margin:0 0 16px;font-size:20px;color:#0f172a;">' . $h($heading) . '</h1>'
        . $paras . $button
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;">Automated message from the AI-Powered Institutional Concern Management System. Please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';

    return [$text, $html];
}

/**
 * Add an email to the outbox. Returns false when mail is disabled or the address is invalid.
 */
function queueEmail(PDO $pdo, string $toEmail, ?string $toName, string $subject, array $lines, ?string $buttonUrl = null, ?string $buttonLabel = null): bool {
    if (mailConfig()['driver'] === 'off' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [$text, $html] = buildEmailBodies($subject, $lines, $buttonUrl, $buttonLabel);
    $pdo->prepare("
        INSERT INTO email_outbox (to_email, to_name, subject, body_text, body_html)
        VALUES (:e, :n, :s, :t, :h)
    ")->execute(['e' => $toEmail, 'n' => $toName, 's' => $subject, 't' => $text, 'h' => $html]);

    // Deliver once, after the page logic has finished
    static $hooked = false;
    if (!$hooked && mailConfig()['send_on_request'] && PHP_SAPI !== 'cli') {
        $hooked = true;
        register_shutdown_function(function () use ($pdo) {
            try {
                // Real SMTP takes seconds per message: hand it to a background worker so the
                // page never waits. Log mode is instant, so it is processed right here.
                if (mailConfig()['driver'] !== 'smtp' || !spawnEmailWorker()) {
                    if (mailConfig()['driver'] === 'smtp') {
                        // No background worker (shared hosting): finish the page for the browser first,
                        // so a slow or blocked SMTP server never makes the visitor wait
                        if (session_status() === PHP_SESSION_ACTIVE) {
                            session_write_close();
                        }
                        if (function_exists('fastcgi_finish_request')) {
                            fastcgi_finish_request();
                        } elseif (function_exists('litespeed_finish_request')) {
                            litespeed_finish_request();
                        }
                    }
                    processEmailQueue($pdo, mailConfig()['driver'] === 'smtp' ? 1 : 5);
                }
            } catch (Throwable $e) {
                error_log('[ICMS] Email queue error: ' . $e->getMessage());
            }
        });
    }
    return true;
}

/**
 * Start scripts/process_email_queue.php in the background (returns at once).
 * Returns false when no PHP command-line binary or process function is available.
 */
function spawnEmailWorker(): bool {
    $php = null;
    foreach (['/Applications/XAMPP/xamppfiles/bin/php', 'C:\\xampp\\php\\php.exe', '/opt/lampp/bin/php', '/usr/bin/php', '/usr/local/bin/php'] as $c) {
        if (@is_file($c)) { $php = $c; break; }
    }
    $script = realpath(__DIR__ . '/../scripts/process_email_queue.php');
    if (!$php || !$script) {
        return false;
    }
    if (stripos(PHP_OS, 'WIN') === 0) {
        if (!function_exists('popen')) return false;
        $h = @popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > NUL 2>&1', 'r');
        if ($h === false) return false;
        pclose($h);
        return true;
    }
    if (!function_exists('exec')) {
        return false;
    }
    @exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &', $out, $code);
    return $code === 0;
}

/** Queue the same email to every active administrator. */
function queueEmailToAdmins(PDO $pdo, string $subject, array $lines, ?string $buttonUrl = null, ?string $buttonLabel = null): void {
    foreach ($pdo->query("SELECT name, email FROM administrators WHERE status = 'Active'")->fetchAll() as $a) {
        queueEmail($pdo, $a['email'], $a['name'], $subject, $lines, $buttonUrl, $buttonLabel);
    }
}

/**
 * Deliver up to $limit pending emails. Each email gets at most 3 attempts.
 * Returns [sent, failed].
 */
function processEmailQueue(PDO $pdo, int $limit = 20): array {
    $rows = $pdo->query("
        SELECT * FROM email_outbox
        WHERE status = 'Pending' AND attempts < 3
        ORDER BY email_id ASC
        LIMIT " . max(1, $limit)
    )->fetchAll();

    $sent = $failed = 0;
    foreach ($rows as $row) {
        $result = deliverOutboxRow($pdo, $row);
        if ($result === true) {
            $sent++;
        } elseif ($result === false) {
            $failed++;
        }
    }
    return [$sent, $failed];
}

/**
 * Try one outbox row now. true = sent, false = failed, null = another request already took it.
 * Every SMTP attempt updates the delivery health used by mailCanDeliver().
 */
function deliverOutboxRow(PDO $pdo, array $row): ?bool {
    // Claim the row so two overlapping requests can't send the same email twice
    $claim = $pdo->prepare("UPDATE email_outbox SET attempts = attempts + 1 WHERE email_id = :id AND attempts = :a AND status = 'Pending'");
    $claim->execute(['id' => $row['email_id'], 'a' => $row['attempts']]);
    if ($claim->rowCount() === 0) {
        return null;
    }
    try {
        deliverEmail($row);
        $pdo->prepare("UPDATE email_outbox SET status = 'Sent', sent_at = NOW(), last_error = NULL WHERE email_id = :id")
            ->execute(['id' => $row['email_id']]);
        recordMailHealth(true);
        return true;
    } catch (Throwable $e) {
        $final = (int)$row['attempts'] + 1 >= 3;
        $pdo->prepare("UPDATE email_outbox SET status = :st, last_error = :err WHERE email_id = :id")
            ->execute(['st' => $final ? 'Failed' : 'Pending', 'err' => mb_substr($e->getMessage(), 0, 500), 'id' => $row['email_id']]);
        recordMailHealth(false, $e->getMessage());
        error_log('[ICMS] Email #' . $row['email_id'] . ' failed: ' . $e->getMessage());
        return false;
    }
}

/** Send one queued email immediately (used for password-reset links). True only if it really left. */
function sendQueuedEmailNow(PDO $pdo, int $emailId): bool {
    $s = $pdo->prepare("SELECT * FROM email_outbox WHERE email_id = :id AND status = 'Pending'");
    $s->execute(['id' => $emailId]);
    $row = $s->fetch();
    return $row ? deliverOutboxRow($pdo, $row) === true : false;
}

/**
 * Where an email should really go when it must not reach its recipient, or null to send it normally.
 *   redirect_all_to  - every email goes to this inbox (whole-system demo mode)
 *   demo_recipients  - only these made-up addresses ("@school.edu" = whole domain, or a full address)
 *                      are sent to demo_redirect_to; real student emails are delivered to the student
 */
function mailRedirectTarget(array $cfg, string $to): ?string {
    $to = mb_strtolower(trim($to));
    $all = trim((string)($cfg['redirect_all_to'] ?? ''));
    if ($all !== '') {
        return strcasecmp($all, $to) === 0 ? null : $all;
    }
    $demoInbox = trim((string)($cfg['demo_redirect_to'] ?? ''));
    if ($demoInbox === '' || strcasecmp($demoInbox, $to) === 0) {
        return null;
    }
    foreach ((array)($cfg['demo_recipients'] ?? []) as $pattern) {
        $pattern = mb_strtolower(trim((string)$pattern));
        if ($pattern === '') {
            continue;
        }
        $hit = $pattern[0] === '@' ? str_ends_with($to, $pattern) : $to === $pattern;
        if ($hit) {
            return $demoInbox;
        }
    }
    return null;
}

function deliverEmail(array $msg): void {
    $cfg = mailConfig();
    switch ($cfg['driver']) {
        case 'smtp':
            // Demo / development safety: made-up addresses (or everything, with redirect_all_to)
            // go to one inbox, and the real recipient is shown in the subject
            $redirect = mailRedirectTarget($cfg, $msg['to_email']);
            if ($redirect !== null) {
                $original = $msg['to_name'] ? "{$msg['to_name']} <{$msg['to_email']}>" : $msg['to_email'];
                $msg['subject']   = "[for {$msg['to_email']}] " . $msg['subject'];
                $msg['body_text'] = "(Redirected copy - intended for {$original})\n\n" . $msg['body_text'];
                if (!empty($msg['body_html'])) {
                    $msg['body_html'] = '<p style="font:12px sans-serif;color:#92400e;background:#fff4dc;padding:8px 12px;border-radius:6px;">Redirected copy &mdash; intended for '
                        . htmlspecialchars($original, ENT_QUOTES, 'UTF-8') . '</p>' . $msg['body_html'];
                }
                $msg['to_email'] = $redirect;
            }
            smtpSend($cfg, $msg);
            return;
        case 'log':
            $entry = str_repeat('=', 72) . "\n"
                . 'Date:    ' . date('Y-m-d H:i:s') . "\n"
                . 'To:      ' . ($msg['to_name'] ? "{$msg['to_name']} <{$msg['to_email']}>" : $msg['to_email']) . "\n"
                . 'Subject: ' . $msg['subject'] . "\n\n"
                . $msg['body_text'] . "\n";
            $logFile = __DIR__ . '/../storage/mail.log';
            $isNew = !is_file($logFile);
            if (@file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
                throw new RuntimeException('Cannot write storage/mail.log (check folder permissions).');
            }
            if ($isNew) {
                // Writable by both Apache and the CLI scripts, whichever created it
                @chmod($logFile, 0666);
            }
            return;
        default:
            return; // 'off'
    }
}

// --------------------------------------------------------------------------
// Minimal SMTP client (RFC 5321) with STARTTLS / implicit TLS and AUTH LOGIN
// --------------------------------------------------------------------------

function smtpSend(array $cfg, array $msg): void {
    $secure = $cfg['encryption'] === 'ssl';
    $remote = ($secure ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . (int)$cfg['port'];
    // ssl_verify can be turned off in mail.local.php only for hosts whose CA certificates are broken
    $verify = ($cfg['ssl_verify'] ?? true) !== false;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify]]);
    $sock = @stream_socket_client($remote, $errno, $errstr, $cfg['timeout'], STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        throw new RuntimeException("SMTP connect to {$remote} failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($sock, $cfg['timeout']);

    try {
        smtpExpect($sock, 220);
        $ehloHost = parse_url($cfg['app_url'], PHP_URL_HOST) ?: 'localhost';
        smtpCommand($sock, "EHLO {$ehloHost}", 250);

        if ($cfg['encryption'] === 'tls') {
            smtpCommand($sock, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            smtpCommand($sock, "EHLO {$ehloHost}", 250);
        }

        if ($cfg['username'] !== '') {
            smtpCommand($sock, 'AUTH LOGIN', 334);
            smtpCommand($sock, base64_encode($cfg['username']), 334);
            smtpCommand($sock, base64_encode($cfg['password']), 235, true);
        }

        smtpCommand($sock, 'MAIL FROM:<' . $cfg['from_email'] . '>', 250);
        smtpCommand($sock, 'RCPT TO:<' . $msg['to_email'] . '>', [250, 251]);
        smtpCommand($sock, 'DATA', 354);

        $data = buildMimeMessage($cfg, $msg);
        // Dot-stuffing: a line starting with "." must be doubled
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($sock, $data . "\r\n.\r\n");
        smtpExpect($sock, 250);

        smtpCommand($sock, 'QUIT', 221);
    } finally {
        fclose($sock);
    }
}

function smtpCommand($sock, string $cmd, $expect, bool $secret = false): string {
    fwrite($sock, $cmd . "\r\n");
    try {
        return smtpExpect($sock, $expect);
    } catch (RuntimeException $e) {
        $shown = $secret ? '[credentials]' : $cmd;
        throw new RuntimeException("SMTP '{$shown}' rejected: " . $e->getMessage());
    }
}

function smtpExpect($sock, $expect): string {
    $expect = (array)$expect;
    $response = '';
    while (($line = fgets($sock, 515)) !== false) {
        $response .= $line;
        // Multi-line replies use "250-"; the last line uses "250 "
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expect, true)) {
        throw new RuntimeException(trim($response) ?: 'no response (timeout)');
    }
    return $response;
}

function encodeHeader(string $value): string {
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

function buildMimeMessage(array $cfg, array $msg): string {
    $boundary = 'icms_' . bin2hex(random_bytes(12));
    $domain   = substr(strrchr($cfg['from_email'], '@') ?: '@localhost', 1);
    $to       = $msg['to_name'] ? encodeHeader($msg['to_name']) . " <{$msg['to_email']}>" : $msg['to_email'];

    $headers = [
        'Date: ' . date('r'),
        'From: ' . encodeHeader($cfg['from_name']) . " <{$cfg['from_email']}>",
        "To: {$to}",
        'Subject: ' . encodeHeader($msg['subject']),
        'Message-ID: <' . bin2hex(random_bytes(10)) . "@{$domain}>",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        'Auto-Submitted: auto-generated',
    ];

    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($msg['body_text'])) . "\r\n";
    if (!empty($msg['body_html'])) {
        $body .= "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($msg['body_html'])) . "\r\n";
    }
    $body .= "--{$boundary}--";

    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

<?php
// includes/auth_helpers.php - Password policy, login rate limiting, registration lookups

const LOGIN_MAX_ATTEMPTS_PER_ID = 5;   // per identifier within the window
const LOGIN_MAX_ATTEMPTS_PER_IP = 20;  // per IP within the window
const LOGIN_LOCKOUT_MINUTES     = 15;

const SNSU_DEPARTMENTS = [
    'College of Computing & Information Sciences',
    'College of Engineering',
    'College of Technology',
    'College of Education',
    'College of Arts & Sciences',
    'College of Business & Management',
];

/**
 * Returns an error message, or null when the password is acceptable.
 * Policy: at least 8 characters, containing a letter and a number.
 */
function validatePasswordPolicy(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.'; // bcrypt input limit
    }
    return null;
}

function clientIp(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function normalizeIdentifier(string $identifier): string {
    return mb_substr(mb_strtolower(trim($identifier), 'UTF-8'), 0, 150);
}

/**
 * Seconds until the identifier/IP may try again, or 0 if not locked out.
 */
function loginLockoutRemaining(PDO $pdo, string $identifier): int {
    $window = LOGIN_LOCKOUT_MINUTES;
    $checks = [
        ['identifier', normalizeIdentifier($identifier), LOGIN_MAX_ATTEMPTS_PER_ID],
        ['ip_address', clientIp(), LOGIN_MAX_ATTEMPTS_PER_IP],
    ];
    $remaining = 0;
    foreach ($checks as [$col, $val, $max]) {
        $stmt = $pdo->prepare("
            SELECT attempted_at FROM login_attempts
            WHERE {$col} = :v AND attempted_at > (NOW() - INTERVAL {$window} MINUTE)
            ORDER BY attempted_at DESC
            LIMIT {$max}
        ");
        $stmt->execute(['v' => $val]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows) >= $max) {
            // Locked until the oldest of the last $max attempts leaves the window
            $unlockAt = strtotime(end($rows)) + $window * 60;
            $remaining = max($remaining, $unlockAt - time());
        }
    }
    return max(0, $remaining);
}

function recordFailedLogin(PDO $pdo, string $identifier): void {
    $pdo->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (:i, :ip)")
        ->execute(['i' => normalizeIdentifier($identifier), 'ip' => clientIp()]);
    // Housekeeping: drop rows older than a day
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
}

function clearFailedLogins(PDO $pdo, string $identifier): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE identifier = :i")
        ->execute(['i' => normalizeIdentifier($identifier)]);
}

/**
 * True when the email is already used by any student or administrator
 * (one email = one account keeps login unambiguous).
 */
function emailIsTaken(PDO $pdo, string $email): bool {
    $stmt = $pdo->prepare("
        SELECT (SELECT COUNT(*) FROM students WHERE email = :e1)
             + (SELECT COUNT(*) FROM administrators WHERE email = :e2)
    ");
    $stmt->execute(['e1' => $email, 'e2' => $email]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Human-readable temporary password, e.g. "Snsu-4821-k7qd" (letters + digits, passes policy).
 */
function generateTemporaryPassword(): string {
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $tail = '';
    for ($i = 0; $i < 4; $i++) {
        $tail .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'Snsu-' . random_int(1000, 9999) . '-' . $tail;
}

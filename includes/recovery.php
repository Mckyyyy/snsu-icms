<?php
// includes/recovery.php - Student recovery codes (self-service password reset without email)
//
// A code looks like "K7QD-4821-MXPT": 12 characters from an alphabet without look-alikes
// (no 0/O, 1/I/L), about 59 bits of randomness. Only a bcrypt hash is stored. The code is shown
// once (recovery_code.php); using it to reset the password replaces it with a new one.

const RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

function generateRecoveryCode(): string {
    $chars = '';
    $max = strlen(RECOVERY_CODE_ALPHABET) - 1;
    for ($i = 0; $i < 12; $i++) {
        $chars .= RECOVERY_CODE_ALPHABET[random_int(0, $max)];
    }
    return implode('-', str_split($chars, 4));
}

/** "k7qd 4821-mxpt" -> "K7QD4821MXPT" (spaces, dashes and case do not matter when typing it back). */
function normalizeRecoveryCode(string $code): string {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
}

/** Create (or replace) a student's recovery code and return it in plain text, to be shown once. */
function issueRecoveryCode(PDO $pdo, int $studentId): string {
    $code = generateRecoveryCode();
    $pdo->prepare("UPDATE students SET recovery_code_hash = :h, recovery_code_set_at = NOW() WHERE student_id = :id")
        ->execute(['h' => password_hash(normalizeRecoveryCode($code), PASSWORD_DEFAULT), 'id' => $studentId]);
    return $code;
}

function recoveryCodeMatches(?string $hash, string $typed): bool {
    $typed = normalizeRecoveryCode($typed);
    return $hash !== null && $hash !== '' && strlen($typed) === 12 && password_verify($typed, $hash);
}

/**
 * Remember a freshly issued code for recovery_code.php, which shows it once.
 * $next is where "Continue" goes; $context is the sentence shown above the code.
 */
function showRecoveryCodeNext(string $code, string $name, string $context, string $next): void {
    $_SESSION['recovery_code_display'] = ['code' => $code, 'name' => $name, 'context' => $context, 'next' => $next];
}

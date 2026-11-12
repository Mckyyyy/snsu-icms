<?php
// includes/security.php - CSRF protection and request-method guards

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Per-session CSRF token.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input to drop inside every POST form.
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrf(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforce POST + valid CSRF token for a state-changing form handler.
 * On failure, flash an error and redirect to $redirect.
 */
function requirePostWithCsrf(string $redirect): void {
    $ok = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isValidCsrf($_POST['csrf_token'] ?? null);
    if (!$ok) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'message' => 'Your session has expired or the request was invalid. Please try again.',
        ];
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Enforce CSRF on JSON endpoints (token sent in the X-CSRF-Token header or csrf_token field).
 */
function requireCsrfJson(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isValidCsrf($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

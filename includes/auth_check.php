<?php
// includes/auth_check.php - Session guard and role-based access control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Sign users out after this many seconds without activity. */
const SESSION_IDLE_TIMEOUT = 1800;

/**
 * True when a logged-in session has been idle longer than SESSION_IDLE_TIMEOUT.
 * Expired sessions are cleared (keeping a flash message for the login page).
 */
function sessionExpired(): bool {
    if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
        return false;
    }
    if (time() - (int)$_SESSION['last_activity'] <= SESSION_IDLE_TIMEOUT) {
        return false;
    }
    $_SESSION = [];
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['flash'] = [
        'type'    => 'warning',
        'message' => 'You were signed out after ' . (SESSION_IDLE_TIMEOUT / 60) . ' minutes of inactivity. Please sign in again.',
    ];
    return true;
}

/** Mark the session as active now (page views and keep-alive pings, not background polling). */
function touchSession(): void {
    if (!empty($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check if a user is actively authenticated
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Return current logged in user object/array
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'         => $_SESSION['user_id'],
        'role'       => $_SESSION['role'], // 'student' or 'admin'
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'student_no' => $_SESSION['student_no'] ?? null,
        'department' => $_SESSION['user_department'] ?? '',
        'program'    => $_SESSION['user_program'] ?? ''
    ];
}

/**
 * Relative prefix from the running script back to the app root.
 */
function appRootPrefix(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/student/', '/admin/', '/actions/', '/api/'] as $dir) {
        if (strpos($script, $dir) !== false) {
            return '../';
        }
    }
    return '';
}

/**
 * Require active authentication and optional role validation
 */
function requireAuth(?string $requiredRole = null): void {
    sessionExpired();
    if (!isLoggedIn()) {
        // Keep a more specific message (e.g. the inactivity notice) if one is already set
        $_SESSION['flash'] = $_SESSION['flash'] ?? [
            'type' => 'warning',
            'message' => 'Please log in to access this page.'
        ];
        header('Location: ' . appRootPrefix() . 'index.php');
        exit;
    }

    touchSession();

    // Accounts holding an admin-issued temporary password must set their own first
    $allowedWhileForced = ['change_password.php', 'change_password_action.php'];
    if (!empty($_SESSION['must_change_password']) && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $allowedWhileForced, true)) {
        header('Location: ' . appRootPrefix() . 'change_password.php');
        exit;
    }

    if ($requiredRole !== null && strtolower($_SESSION['role']) !== strtolower($requiredRole)) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Unauthorized access for your account role.'
        ];
        
        $target = appRootPrefix() . (($_SESSION['role'] === 'admin') ? 'admin/dashboard.php' : 'student/dashboard.php');
        header("Location: {$target}");
        exit;
    }
}

/**
 * Guard student-only pages
 */
function requireStudent(): void {
    requireAuth('student');
}

/**
 * Guard admin-only pages
 */
function requireAdmin(): void {
    requireAuth('admin');
}

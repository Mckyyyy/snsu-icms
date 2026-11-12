<?php
// logout.php - Safe session destruction (POST + CSRF only, so a link/image cannot log users out)
require_once __DIR__ . '/includes/security.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isValidCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: index.php');
    exit;
}

// Show the person as offline in Messages right away
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    try {
        require_once __DIR__ . '/config/db.php';
        require_once __DIR__ . '/includes/helpers.php';
        chatSetOffline(getDBConnection(), $_SESSION['role'] === 'admin' ? 'admin' : 'student', (int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        error_log('[ICMS] Chat presence on logout: ' . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
$_SESSION['flash'] = [
    'type' => 'info',
    'message' => 'You have been safely logged out.'
];

header('Location: index.php');
exit;

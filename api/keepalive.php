<?php
// api/keepalive.php - Extend the session while the user is actively using the page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (sessionExpired() || !isLoggedIn()) {
    jsonResponse(['status' => 'expired'], 401);
}
requireCsrfJson();
touchSession();
jsonResponse(['status' => 'success', 'timeout' => SESSION_IDLE_TIMEOUT]);

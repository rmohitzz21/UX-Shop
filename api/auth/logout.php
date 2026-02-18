<?php
header('Content-Type: application/json');

// Use consistent session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(86400, '/');
    session_start();
}

session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>

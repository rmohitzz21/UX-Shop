<?php
// includes/helpers.php

/**
 * Send a JSON response and exit
 */
function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    $response = [
        "status" => $status,
        "message" => $message
    ];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    exit;
}

/**
 * Check if the current user is an admin
 */
function isAdmin() {
    
// Check session first (for security)
if (isset($_SESSION['admin_id'])) {
    return true;
}

// Check localStorage fallback / Client-side token (Less secure but might be needed for current implementation)
// Note: In production, ONLY trust session/JWT.
return false; 
}

/**
 * Enforce Admin Role
 */
function requireAdmin() {
if (!isAdmin()) {
    sendResponse("error", "Unauthorized: Admin access required", null, 401);
}
}

/**
 * Enforce Authenticated User
 */
function requireAuth() {
// session_start handled in config.php
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    sendResponse("error", "Unauthorized: Login required", null, 401);
}
}


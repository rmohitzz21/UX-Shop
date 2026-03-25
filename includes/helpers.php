<?php
// includes/helpers.php

/**
 * Send a JSON response and exit
 */
function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    $response = [
        "status"  => $status,
        "message" => $message
    ];
    if ($data !== null) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    exit;
}

/**
 * Require an authenticated USER session.
 * Admin sessions do NOT satisfy this — prevents SEC-06 (admin session on user endpoints).
 */
function requireUserAuth() {
    if (empty($_SESSION['user_id'])) {
        sendResponse("error", "Unauthorized: Login required", null, 401);
    }
}

/**
 * Require an authenticated ADMIN session.
 */
function requireAdmin() {
    if (empty($_SESSION['admin_id'])) {
        sendResponse("error", "Unauthorized: Admin access required", null, 401);
    }
}

/**
 * @deprecated Use requireUserAuth() for user endpoints.
 * Kept for backward compatibility — still only accepts user sessions.
 */
function requireAuth() {
    requireUserAuth();
}

/**
 * Check if the current request has a valid CSRF token.
 * Accepts token from: X-CSRF-Token header OR JSON body field 'csrf_token'.
 * Call this on all state-changing endpoints (POST/PUT/DELETE).
 */
function validateCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        sendResponse("error", "Session expired. Please refresh the page.", null, 403);
    }

    // Check header first (used by JS fetch calls)
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    // Fallback: check JSON body (used by form-based submissions)
    if (empty($headerToken)) {
        $body = json_decode(file_get_contents('php://input'), true);
        $headerToken = $body['csrf_token'] ?? '';
        // Re-populate php://input is not possible after reading — endpoints must pass $data if needed.
        // Endpoints that need to validate CSRF AND read JSON body should call validateCsrfFromToken($token).
    }

    if (empty($headerToken) || !hash_equals($_SESSION['csrf_token'], $headerToken)) {
        sendResponse("error", "Invalid or missing CSRF token", null, 403);
    }
}

/**
 * Validate CSRF from an already-decoded token string.
 * Use this when the endpoint has already parsed the JSON body.
 */
function validateCsrfFromToken(string $token) {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendResponse("error", "Invalid or missing CSRF token", null, 403);
    }
}

/**
 * Check if admin is logged in (non-terminating).
 */
function isAdmin() {
    return !empty($_SESSION['admin_id']);
}

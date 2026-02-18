<?php
// includes/config.php

// Ensure we don't output HTML errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

// Configure Session
if (session_status() === PHP_SESSION_NONE) {
    // Set cookie parameters BEFORE starting session
    // Lifetime: 24 hours, HttpOnly to prevent JS access, SameSite=Lax for CSRF protection
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'uxmerchandise';

try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Return JSON error if something goes wrong
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Global Helpers
require_once __DIR__ . '/helpers.php';


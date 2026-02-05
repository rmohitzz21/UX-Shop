<?php
// includes/config.php

// Ensure we don't output HTML errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

// Configure Session
if (session_status() === PHP_SESSION_NONE) {
    // Set cookie parameters BEFORE starting session
    // Lifetime: 24 hours, Path: /, Domain: check manually or default, Secure: false (dev)
    session_set_cookie_params(86400, '/');
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

// Global Helpers
require_once __DIR__ . '/helpers.php';


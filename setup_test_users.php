<?php
require_once 'includes/config.php';

// 1. Setup Test User
$userEmail = 'testuser@example.com';
$userPass = 'password123';
$userHash = password_hash($userPass, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, is_blocked = 0, role = 'user' WHERE email = ?");
    $stmt->bind_param("ss", $userHash, $userEmail);
    echo "Updated Test User.\n";
} else {
    // Insert
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_blocked) VALUES (?, ?, 'Test', 'User', 'user', 0)");
    $stmt->bind_param("ss", $userEmail, $userHash);
    echo "Created Test User.\n";
}
$stmt->execute();

// 2. Setup Test Admin
$adminEmail = 'admin@uxpacific.com';
$adminPass = 'password123';
$adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, role = 'admin', is_blocked = 0 WHERE email = ?");
    $stmt->bind_param("ss", $adminHash, $adminEmail);
    echo "Updated Test Admin.\n";
} else {
    // Insert
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_blocked) VALUES (?, ?, 'Admin', 'User', 'admin', 0)");
    $stmt->bind_param("ss", $adminEmail, $adminHash);
    echo "Created Test Admin.\n";
}
$stmt->execute();

echo "Setup Complete.";
?>

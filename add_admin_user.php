<?php
require_once 'includes/config.php';

$email = 'Hello@uxpacific.com';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Check if exists
$check = $conn->query("SELECT id FROM users WHERE email = '$email'");
if ($check->num_rows == 0) {
    $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES ('$email', '$hash', 'Admin', 'User', 'admin')";
    if ($conn->query($sql)) {
        echo "Created admin: $email / $password";
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "Admin $email already exists. Updating password...";
    $conn->query("UPDATE users SET password_hash = '$hash', role = 'admin' WHERE email = '$email'");
    echo "Updated.";
}
?>

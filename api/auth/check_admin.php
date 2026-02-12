<?php
require_once '../../includes/config.php';

$email = 'admin@uxpacific.com';
$password = 'password123';

echo "Checking for user: $email\n";

$stmt = $conn->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo "User found:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Password Hash: " . $user['password_hash'] . "\n";
    
    if (password_verify($password, $user['password_hash'])) {
        echo "Password verification: SUCCESS\n";
    } else {
        echo "Password verification: FAILED\n";
    }
} else {
    echo "User not found.\n";
}

$stmt->close();
$conn->close();
?>

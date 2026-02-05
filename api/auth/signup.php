<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$firstName = $input['firstName'] ?? '';
$lastName = $input['lastName'] ?? '';
$phone = $input['phone'] ?? '';

// Handle fullName if provided (splitting logic)
if (empty($firstName) && !empty($input['fullName'])) {
    $parts = explode(' ', trim($input['fullName']), 2);
    $firstName = $parts[0];
    $lastName = $parts[1] ?? '';
}

// Basic validation
if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
    exit;
}
$stmt->close();

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES (?, ?, ?, ?, ?, 'customer')");
$stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $phone);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    
    // Generate a simple token (in production use JWT)
    $token = bin2hex(random_bytes(32));
    
    echo json_encode(['status' => 'success', 'message' => 'User registered successfully', 'user' => ['id' => $userId, 'email' => $email, 'firstName' => $firstName, 'lastName' => $lastName, 'role' => 'customer'], 'tokens' => ['access_token' => $token, 'refresh_token' => $token]]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
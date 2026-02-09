<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse("error", "Invalid input", null, 400);
}

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendResponse("error", "Email and password are required", null, 400);
}

// Find user by email first, then check role
$stmt = $conn->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    if ($user['role'] !== 'admin') {
        sendResponse("error", "Invalid admin credentials or access denied", null, 401);
    }
    
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        sendResponse("success", "Login successful", [
            "id" => $user['id'],
            "email" => $user['email'],
            "role" => $user['role']
        ]);
    }
}

sendResponse("error", "Invalid admin credentials or access denied", null, 401);
$stmt->close();
$conn->close();
?>

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

// Find user with admin role
$stmt = $conn->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password_hash'])) {
    
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    sendResponse("success", "Login successful", [
        "id" => $user['id'],
        "email" => $user['email'],
        "role" => $user['role']
    ]);
} else {
    // Fallback for initial setup: check hardcoded if no admin in DB yet
    // BUT we should really just ensure one exists in DB.
    // For now, let's keep it secure and only check DB.
    sendResponse("error", "Invalid admin credentials or access denied", null, 401);
}

$stmt->close();
$conn->close();
?>

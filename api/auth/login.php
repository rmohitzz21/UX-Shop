<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, email, password_hash, first_name, last_name, role, is_blocked FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if ($user['is_blocked'] == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Your account has been blocked. Please contact support.']);
        exit;
    }

    if (password_verify($password, $user['password_hash'])) {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Generate token for mobile/client app compatibility
        $token = bin2hex(random_bytes(32));
        
        sendResponse("success", "Login successful", [
            'user' => [
                'id' => $user['id'], 
                'email' => $user['email'], 
                'firstName' => $user['first_name'], 
                'lastName' => $user['last_name'], 
                'role' => $user['role']
            ],
            'tokens' => [
                'access_token' => $token, 
                'refresh_token' => $token
            ]
        ]);
    } else {
        sendResponse("error", "Invalid credentials", null, 401);
    }
} else {
    sendResponse("error", "Invalid credentials", null, 401);
}

$stmt->close();
$conn->close();
?>
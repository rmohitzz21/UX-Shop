<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Validation
$firstName = $input['firstName'] ?? '';
$lastName = $input['lastName'] ?? '';
$phone = $input['phone'] ?? '';

if (empty($firstName) || empty($lastName)) {
    echo json_encode(['status' => 'error', 'message' => 'First and Last name are required']);
    exit;
}

// Update user data
$stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
$stmt->bind_param("sssi", $firstName, $lastName, $phone, $user_id);

if ($stmt->execute()) {
    // Update session data
    $_SESSION['username'] = $firstName . ' ' . $lastName;
    
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update profile']);
}

$stmt->close();
$conn->close();
?>

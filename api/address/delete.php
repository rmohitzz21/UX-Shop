<?php
// api/address/delete.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = $conn->real_escape_string($data['id']);

// Check if address belongs to user
$check = $conn->query("SELECT id FROM addresses WHERE id = '$address_id' AND user_id = '$user_id'");

if ($check->num_rows == 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Address not found']);
    exit;
}

if ($conn->query("DELETE FROM addresses WHERE id = '$address_id'")) {
    echo json_encode(['status' => 'success', 'message' => 'Address deleted']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

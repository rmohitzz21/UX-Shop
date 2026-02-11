<?php
// api/cart/clear.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Clear all items for this user
$sql = "DELETE FROM cart WHERE user_id = '$user_id'";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Cart cleared']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>

<?php
// api/cart/clear.php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Cart cleared']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to clear cart']);
}
$stmt->close();
$conn->close();
?>

<?php
// api/cart/remove.php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing product_id']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$product_id = intval($data['product_id']);
$size = isset($data['size']) ? substr($data['size'], 0, 20) : '';
$available_type = isset($data['available_type']) ? substr($data['available_type'], 0, 20) : 'physical';

$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND size = ? AND available_type = ?");
$stmt->bind_param("iiss", $user_id, $product_id, $size, $available_type);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
}
$stmt->close();
$conn->close();
?>

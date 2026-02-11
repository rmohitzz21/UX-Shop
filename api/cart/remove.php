<?php
// api/cart/remove.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !isset($data['size']) || !isset($data['product_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($data['product_id']);
$size = $conn->real_escape_string($data['size']);
$product_type = $conn->real_escape_string($data['product_type']);

// Delete item
$sql = "DELETE FROM cart WHERE user_id = '$user_id' AND product_id = '$product_id' AND size = '$size' AND product_type = '$product_type'";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>

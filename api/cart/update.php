<?php
// api/cart/update.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !isset($data['quantity']) || !isset($data['size']) || !isset($data['available_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($data['product_id']);
$quantity = intval($data['quantity']);
$size = $conn->real_escape_string($data['size']);
$available_type = $conn->real_escape_string($data['available_type']);

// Find the item in cart
$sql = "UPDATE cart SET quantity = '$quantity' WHERE user_id = '$user_id' AND product_id = '$product_id' AND size = '$size' AND available_type = '$available_type'";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Quantity updated']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>

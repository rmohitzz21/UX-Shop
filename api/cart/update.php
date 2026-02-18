<?php
// api/cart/update.php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !isset($data['quantity'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$product_id = intval($data['product_id']);
$quantity = intval($data['quantity']);
$size = isset($data['size']) ? substr($data['size'], 0, 20) : '';
$available_type = isset($data['available_type']) ? substr($data['available_type'], 0, 20) : 'physical';

$max_per_product = 10;

if ($quantity <= 0) {
    // Delete item if quantity is 0 or negative
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? AND size = ? AND available_type = ?");
    $stmt->bind_param("iiss", $user_id, $product_id, $size, $available_type);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
    exit;
}

if ($quantity > $max_per_product) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Maximum $max_per_product items per product allowed"]);
    exit;
}

$stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ? AND size = ? AND available_type = ?");
$stmt->bind_param("iiiss", $quantity, $user_id, $product_id, $size, $available_type);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Quantity updated']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update quantity']);
}
$stmt->close();
$conn->close();
?>

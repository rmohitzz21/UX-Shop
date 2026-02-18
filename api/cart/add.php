<?php
// api/cart/add.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
file_put_contents('cart_log.txt', date('Y-m-d H:i:s') . " - Received data: " . print_r($data, true) . "\n", FILE_APPEND);

if (!isset($data['product_id']) || !isset($data['quantity'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($data['product_id']);
$quantity = intval($data['quantity']);
$size = isset($data['size']) ? $conn->real_escape_string($data['size']) : '';
$available_type = isset($data['available_type']) ? $conn->real_escape_string($data['available_type']) : 'physical';

// Check if item exists in cart
$check = $conn->query("SELECT id, quantity FROM cart WHERE user_id = '$user_id' AND product_id = '$product_id' AND size = '$size' AND available_type = '$available_type'");

if ($check->num_rows > 0) {
    // Update quantity
    $row = $check->fetch_assoc();
    $new_quantity = $row['quantity'] + $quantity;
    $conn->query("UPDATE cart SET quantity = '$new_quantity' WHERE id = '{$row['id']}'");
    echo json_encode(['status' => 'success', 'message' => 'Cart updated']);
} else {
    // Insert new item
    $sql = "INSERT INTO cart (user_id, product_id, quantity, size, available_type) VALUES ('$user_id', '$product_id', '$quantity', '$size', '$available_type')";
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Item added to cart']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
}
?>

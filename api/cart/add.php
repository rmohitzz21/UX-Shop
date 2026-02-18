<?php
// api/cart/add.php
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

if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Quantity must be positive']);
    exit;
}

// Validate available_type
$allowed_types = ['physical', 'digital', 'both'];
if (!in_array($available_type, $allowed_types)) {
    $available_type = 'physical';
}

// Check if item exists in cart using prepared statement
$check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ? AND available_type = ?");
$check->bind_param("iiss", $user_id, $product_id, $size, $available_type);
$check->execute();
$result = $check->get_result();

$max_per_product = 10;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $new_quantity = $row['quantity'] + $quantity;
    if ($new_quantity > $max_per_product) {
        $new_quantity = $max_per_product;
        if ($row['quantity'] >= $max_per_product) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Maximum $max_per_product items per product allowed"]);
            $check->close();
            $conn->close();
            exit;
        }
    }
    $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $update->bind_param("ii", $new_quantity, $row['id']);
    $update->execute();
    $update->close();
    echo json_encode(['status' => 'success', 'message' => 'Cart updated', 'quantity' => $new_quantity]);
} else {
    if ($quantity > $max_per_product) {
        $quantity = $max_per_product;
    }
    $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size, available_type) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("iiiss", $user_id, $product_id, $quantity, $size, $available_type);
    if ($insert->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Item added to cart']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to add item to cart']);
    }
    $insert->close();
}
$check->close();
$conn->close();
?>

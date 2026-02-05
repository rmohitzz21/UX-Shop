<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/config.php';

// Get raw POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
    exit;
}

// Validate required fields
$required_fields = ['items', 'total', 'paymentMethod', 'shipping'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit;
    }
}

// Extract data
$user_id = isset($data['userId']) ? intval($data['userId']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid User ID is required to place an order."]);
    exit;
}
$order_number = 'UXP-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
$total = floatval($data['total']);
$subtotal = floatval($data['subtotal']);
$shipping_cost = floatval($data['shipping_cost']);
$tax = floatval($data['tax']);
$payment_method = $conn->real_escape_string($data['paymentMethod']);
$status = 'Pending';

// Serialize shipping address
$shipping_address = json_encode($data['shipping']);

// Start transaction
$conn->begin_transaction();

try {
    // Insert into orders table
    // Note: user_id is passed as 'i', if it's null PHP/MySQLi driver usually handles it as SQL NULL
    $stmt = $conn->prepare("INSERT INTO orders (order_number, user_id, total, subtotal, shipping, tax, payment_method, status, shipping_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("siddddsss", $order_number, $user_id, $total, $subtotal, $shipping_cost, $tax, $payment_method, $status, $shipping_address);
    
    if (!$stmt->execute()) {
        throw new Exception("Error creating order: " . $stmt->error);
    }
    
    $order_id = $conn->insert_id;
    
    // Insert order items
    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($data['items'] as $item) {
        $product_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $size = isset($item['size']) ? $item['size'] : '';
        
        $stmt_item->bind_param("iiids", $order_id, $product_id, $quantity, $price, $size);
        
        if (!$stmt_item->execute()) {
            throw new Exception("Error inserting order item: " . $stmt_item->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully",
        "orderNumber" => $order_number,
        "orderId" => $order_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>

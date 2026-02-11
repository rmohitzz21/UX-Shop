<?php
// api/cart/list.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT c.id, c.product_id, c.quantity, c.size, c.product_type,
        p.name, p.price, p.image, p.description, p.stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = '$user_id'
        ORDER BY c.created_at DESC";

$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['product_id'], // Use product_id as id for frontend compat
        'cart_id' => $row['id'],
        'name' => $row['name'],
        'price' => floatval($row['price']),
        'image' => $row['image'],
        'description' => $row['description'],
        'quantity' => intval($row['quantity']),
        'size' => $row['size'],
        'product_type' => $row['product_type'],
        'stock' => intval($row['stock'])
    ];
}

echo json_encode(['status' => 'success', 'data' => $items]);
?>

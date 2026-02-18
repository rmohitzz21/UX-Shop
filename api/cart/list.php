<?php
// api/cart/list.php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT c.id, c.product_id, c.quantity, c.size, c.available_type,
        p.name, p.price, p.image, p.description, p.stock, p.available_type AS product_available_type
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
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
        'available_type' => $row['available_type'],
        'stock' => intval($row['stock'])
    ];
}

$stmt->close();
$conn->close();
echo json_encode(['status' => 'success', 'data' => $items]);
?>

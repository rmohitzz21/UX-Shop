<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/helpers.php';

requireAuth();
$userId = $_SESSION['user_id'];

// Fetch orders for the user
$stmt = $conn->prepare("SELECT id, order_number, total, status, created_at, shipping_address FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($orderRow = $result->fetch_assoc()) {
    $orderId = $orderRow['id'];
    
    // Fetch items for this order
    // Note: We join with products to ensuring we have latest image/name if needed, 
    // OR strictly use what was in order time. 
    // Usually order_items table should have snapshotted name/price. 
    // Our order_items table relies on product_id. 
    // Let's join products to get image and name.
    
    $itemsStmt = $conn->prepare("SELECT oi.quantity, oi.price, oi.size, p.name, p.image, p.id as product_id 
                                 FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 WHERE oi.order_id = ?");
    $itemsStmt->bind_param("i", $orderId);
    $itemsStmt->execute();
    $itemsRes = $itemsStmt->get_result();
    
    $items = [];
    while ($item = $itemsRes->fetch_assoc()) {
        $items[] = [
            'id' => $item['product_id'],
            'name' => $item['name'],
            'image' => $item['image'],
            'price' => (float)$item['price'],
            'quantity' => (int)$item['quantity'],
            'size' => $item['size']
        ];
    }
    $itemsStmt->close();
    
    $orders[] = [
        'orderNumber' => $orderRow['order_number'],
        'date' => $orderRow['created_at'],
        'status' => $orderRow['status'],
        'total' => (float)$orderRow['total'],
        'shipping' => json_decode($orderRow['shipping_address'], true),
        'items' => $items
    ];
}

$stmt->close();
$conn->close();

sendResponse("success", "Orders fetched successfully", $orders);
?>

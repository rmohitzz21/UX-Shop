<?php
header('Content-Type: application/json');
require_once '../../../includes/config.php';

// Enforce Admin Access
requireAdmin();

if ($conn->connect_error) {
    sendResponse("error", "Database connection failed", null, 500);
}

// Get Input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse("error", "Invalid input", null, 400);
}

$orderNumber = $input['order_number'] ?? '';
$status = $input['status'] ?? '';

if (empty($orderNumber) || empty($status)) {
    sendResponse("error", "Order number and status are required", null, 400);
}

// Validate status enum
$validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
if (!in_array($status, $validStatuses)) {
    sendResponse("error", "Invalid status value", null, 400);
}

// Update Order Status
$sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_number = ?";
// Note: orders table might not have updated_at column in schema provided, 
// checking schema... schema shows created_at but NOT updated_at for orders table. 
// I will verify schema dump again. 
// Step 464 schema dump: 
// line 56: `created_at` timestamp NOT NULL DEFAULT current_timestamp()
// No updated_at in orders table. So I should NOT try to set updated_at unless I added it.
// I will skip updated_at.

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_number = ?");
$stmt->bind_param("ss", $status, $orderNumber);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        sendResponse("success", "Order status updated successfully");
    } else {
        // Could mean order not found OR status was already same value
        // Check if order exists
        $checkStmt = $conn->prepare("SELECT id FROM orders WHERE order_number = ?");
        $checkStmt->bind_param("s", $orderNumber);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
        $checkStmt->close();
        if ($check->num_rows == 0) {
           sendResponse("error", "Order not found", null, 404);
        } else {
           sendResponse("success", "Order status updated (no change)"); 
        }
    }
} else {
    sendResponse("error", "Failed to update order status", null, 500);
}

$stmt->close();
$conn->close();
?>

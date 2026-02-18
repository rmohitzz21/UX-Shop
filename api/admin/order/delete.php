<?php
header('Content-Type: application/json');
require_once '../../../includes/config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['id'] ?? null;

if (!$order_id) {
    // Try POST form data as fallback
    $order_id = $_POST['id'] ?? null;
}

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
    exit;
}

// Start Transaction
$conn->begin_transaction();

try {
    // Delete order items first
    $stmtItems = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmtItems->bind_param("i", $order_id);
    if (!$stmtItems->execute()) {
        throw new Exception("Failed to delete order items");
    }
    $stmtItems->close();

    // Delete order
    $stmtOrder = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmtOrder->bind_param("i", $order_id);
    if (!$stmtOrder->execute()) {
        throw new Exception("Failed to delete order");
    }
    
    if ($stmtOrder->affected_rows === 0) {
        throw new Exception("Order not found or already deleted");
    }
    $stmtOrder->close();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Order deleted successfully']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>

<?php

require_once '../../../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}


$id = $_POST['id'] ?? null;
if(!$id)
{
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
    exit;
}

// Check if product exists and get image path
$stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

// Check for dependencies in orders
$checkStmt = $conn->prepare("SELECT id FROM order_items WHERE product_id = ? LIMIT 1");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['status' => 'error', 'message' => 'Cannot delete product because it has been ordered. Please deactivate it instead.']);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Delete from cart first (safe to remove)
$cartStmt = $conn->prepare("DELETE FROM cart WHERE product_id = ?");
$cartStmt->bind_param("i", $id);
$cartStmt->execute();
$cartStmt->close();

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $id);

if($stmt->execute())
{
    // Delete image file if it exists
    if (!empty($product['image'])) {
        $imagePath = '../../../' . $product['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
}
else
{
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete product: ' . $stmt->error]);
}

$stmt->close();
$conn->close();

?>
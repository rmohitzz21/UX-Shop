<?php
// api/cart/merge.php
header('Content-Type: application/json');
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['cart'])) {
    echo json_encode(['status' => 'success', 'message' => 'No local cart to merge']);
    exit;
}

$local_cart = $data['cart'];

// Start transaction to ensure atomicity
$conn->begin_transaction();

try {
    foreach ($local_cart as $item) {
        $product_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $size = isset($item['size']) ? $item['size'] : ''; // Raw size
        $available_type = isset($item['available_type']) ? $item['available_type'] : 'physical';
        
        // Validation
        if ($quantity <= 0) continue;
        
        // Use prepared statements to check if item exists in DB cart for this user
        $checkStmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (size = ? OR size IS NULL OR size = '') AND available_type = ? FOR UPDATE");
        // Bind params: iiss 
        $checkStmt->bind_param("iiss", $user_id, $product_id, $size, $available_type);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Update quantity
            // Strategy: Add local quantity to DB quantity? Or replace?
            // Usually merging means adding.
            $new_quantity = $row['quantity'] + $quantity;
            $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $new_quantity, $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert new item
            $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size, available_type) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("iiiss", $user_id, $product_id, $quantity, $size, $available_type);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Cart merged successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Merge failed: ' . $e->getMessage()]);
}

$conn->close();
?>

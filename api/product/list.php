<?php
header('Content-Type: application/json');

require_once '../../includes/config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Fetch only active products
$sql = "SELECT * FROM products WHERE is_active = 1 AND (stock > 0 OR available_type != 'physical') ORDER BY created_at DESC";
$result = $conn->query($sql);

$products = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "count" => count($products),
    "data" => $products
]);

$conn->close();
?>

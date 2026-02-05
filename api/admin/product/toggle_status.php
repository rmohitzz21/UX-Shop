<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../../includes/config.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['is_active'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$id = intval($data['id']);
$is_active = intval($data['is_active']);

$stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $is_active, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Product status updated"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update status"]);
}

$stmt->close();
$conn->close();
?>

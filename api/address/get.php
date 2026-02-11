<?php
// api/address/get.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM addresses WHERE user_id = '$user_id' ORDER BY is_default DESC, created_at DESC");

$addresses = [];
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}

echo json_encode(['status' => 'success', 'data' => $addresses]);
?>

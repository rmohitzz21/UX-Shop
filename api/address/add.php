<?php
// api/address/add.php
require_once '../../includes/config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

// Validate required fields
$required = ['firstName', 'lastName', 'address', 'city', 'state', 'zip', 'country', 'phone'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing field: ' . $field]);
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$first_name = $conn->real_escape_string($data['firstName']);
$last_name = $conn->real_escape_string($data['lastName']);
$address_line1 = $conn->real_escape_string($data['address']);
$address_line2 = isset($data['address2']) ? $conn->real_escape_string($data['address2']) : '';
$city = $conn->real_escape_string($data['city']);
$state = $conn->real_escape_string($data['state']);
$zip_code = $conn->real_escape_string($data['zip']);
$country = $conn->real_escape_string($data['country']);
$phone = $conn->real_escape_string($data['phone']);
$is_default = isset($data['isDefault']) && $data['isDefault'] ? 1 : 0;

// If this is the first address, make it default
if ($is_default == 0) {
    $count_res = $conn->query("SELECT COUNT(*) as cnt FROM addresses WHERE user_id = '$user_id'");
    $count_row = $count_res->fetch_assoc();
    if ($count_row['cnt'] == 0) {
        $is_default = 1;
    }
}

// If setting as default, unset previous default
if ($is_default) {
    $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = '$user_id'");
}

$sql = "INSERT INTO addresses (user_id, first_name, last_name, address_line1, address_line2, city, state, zip_code, country, phone, is_default)
        VALUES ('$user_id', '$first_name', '$last_name', '$address_line1', '$address_line2', '$city', '$state', '$zip_code', '$country', '$phone', '$is_default')";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Address added successfully', 'data' => ['id' => $conn->insert_id]]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>

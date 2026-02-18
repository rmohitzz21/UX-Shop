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

$user_id = intval($_SESSION['user_id']);
$first_name = $data['firstName'];
$last_name = $data['lastName'];
$address_line1 = $data['address'];
$address_line2 = isset($data['address2']) ? $data['address2'] : '';
$city = $data['city'];
$state = $data['state'];
$zip_code = $data['zip'];
$country = $data['country'];
$phone = $data['phone'];
$is_default = isset($data['isDefault']) && $data['isDefault'] ? 1 : 0;

// If this is the first address, make it default
if ($is_default == 0) {
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM addresses WHERE user_id = ?");
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $count_row = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    if ($count_row['cnt'] == 0) {
        $is_default = 1;
    }
}

// If setting as default, unset previous default
if ($is_default) {
    $unsetStmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
    $unsetStmt->bind_param("i", $user_id);
    $unsetStmt->execute();
    $unsetStmt->close();
}

$stmt = $conn->prepare("INSERT INTO addresses (user_id, first_name, last_name, address_line1, address_line2, city, state, zip_code, country, phone, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssssssi", $user_id, $first_name, $last_name, $address_line1, $address_line2, $city, $state, $zip_code, $country, $phone, $is_default);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Address added successfully', 'data' => ['id' => $conn->insert_id]]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to add address']);
}
$stmt->close();
?>

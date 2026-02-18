<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'Access denied'; exit; }
require_once '../../../includes/config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add is_active column if it doesn't exist
$sql = "SHOW COLUMNS FROM products LIKE 'is_active'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    $sql = "ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    if ($conn->query($sql) === TRUE) {
        echo "Column is_active added successfully";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column is_active already exists";
}

$conn->close();
?>

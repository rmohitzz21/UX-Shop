<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'Access denied'; exit; }
require_once '../../../includes/config.php';

// First, delete orders with NULL user_id to avoid constraint issues (or we could update them, but usually easier to start clean)
// $conn->query("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id IS NULL)");
// $conn->query("DELETE FROM orders WHERE user_id IS NULL");

// Actually, let's keep them but update to a placeholder or just force NOT NULL which might error if values are still there.
// The user says "this never be gone null", so I will enforce it.

// Alter table to NOT NULL
$sql = "ALTER TABLE orders MODIFY user_id INT(11) NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Table orders updated successfully. user_id is now NOT NULL.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
    echo "Note: This usually happens if there are existing NULL values in the column.\n";
}

// Verify change
$result = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'user_id'");
$row = $result->fetch_assoc();
echo "Column: " . $row['Field'] . "\n";
echo "Null status: " . $row['Null'] . "\n";

$conn->close();
?>

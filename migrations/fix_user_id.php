<?php
require_once '../../../includes/config.php';

// Alter table to allow NULL for user_id
$sql = "ALTER TABLE orders MODIFY user_id INT(11) NULL";

if ($conn->query($sql) === TRUE) {
    echo "Table orders updated successfully. user_id now allows NULL.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}

// Verify change
$result = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'user_id'");
$row = $result->fetch_assoc();
echo "Null status: " . $row['Null'] . "\n";

$conn->close();
?>

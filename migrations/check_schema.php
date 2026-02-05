<?php
require_once '../../../includes/config.php';

// Check if user_id column allows NULL
$result = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'user_id'");
$row = $result->fetch_assoc();

echo "Column: " . $row['Field'] . "\n";
echo "Type: " . $row['Type'] . "\n";
echo "Null: " . $row['Null'] . "\n";
echo "Key: " . $row['Key'] . "\n";
echo "Default: " . $row['Default'] . "\n";
echo "Extra: " . $row['Extra'] . "\n";

?>

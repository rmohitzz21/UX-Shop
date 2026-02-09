<?php
require_once 'includes/config.php';

// Add commercial_price column to products table
$sql = "ALTER TABLE products ADD COLUMN commercial_price DECIMAL(10, 2) DEFAULT NULL AFTER price";

if ($conn->query($sql) === TRUE) {
    echo "Column 'commercial_price' added successfully.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
?>

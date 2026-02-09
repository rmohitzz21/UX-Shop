<?php
require_once 'includes/config.php';

// Add available_type column to products table
// We will use this to determine if a product is Physical, Digital, or Both (e.g. for Workbooks)
$sql = "ALTER TABLE products ADD COLUMN available_type ENUM('physical', 'digital', 'both') NOT NULL DEFAULT 'physical' AFTER category";

if ($conn->query($sql) === TRUE) {
    echo "Column 'available_type' added successfully.\n";
} else {
    // It might already exist or conflict, check error
    echo "Error adding column (might already exist): " . $conn->error . "\n";
}

// Update existing records based on the old 'product_type' logic if needed
// For now, we'll just set defaults.
// Let's explicitly set 'Booklet' and 'Workbook' database items to 'both' for testing if they exist.
$update_sql = "UPDATE products SET available_type = 'both' WHERE category IN ('Workbook', 'Booklet')";
$conn->query($update_sql);

echo "Schema update complete.";
?>

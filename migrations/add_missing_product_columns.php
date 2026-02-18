<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'Access denied'; exit; }
/**
 * Migration: Add missing columns to products table
 * Run this if admin product creation fails with "Unknown column" errors
 */
require_once __DIR__ . '/../includes/config.php';

$columns_to_add = [
    'commercial_price' => "ALTER TABLE products ADD COLUMN commercial_price DECIMAL(10,2) DEFAULT NULL AFTER price",
    'is_featured' => "ALTER TABLE products ADD COLUMN is_featured TINYINT(1) DEFAULT 0 AFTER is_active",
    'related_products' => "ALTER TABLE products ADD COLUMN related_products TEXT DEFAULT NULL AFTER is_featured",
    'whats_included' => "ALTER TABLE products ADD COLUMN whats_included TEXT DEFAULT NULL AFTER related_products",
    'file_specification' => "ALTER TABLE products ADD COLUMN file_specification TEXT DEFAULT NULL AFTER whats_included",
    'additional_images' => "ALTER TABLE products ADD COLUMN additional_images TEXT DEFAULT NULL AFTER file_specification",
];

echo "Checking products table for missing columns...\n";

foreach ($columns_to_add as $column => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM products LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        if ($conn->query($sql)) {
            echo "  Added column: $column\n";
        } else {
            echo "  ERROR adding $column: " . $conn->error . "\n";
        }
    } else {
        echo "  Column $column already exists\n";
    }
}

echo "Done.\n";
$conn->close();
?>

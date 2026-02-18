-- Migration: Add available_type to cart table and products table
-- Run this on existing databases to add the missing columns

-- Add available_type column to cart table
ALTER TABLE `cart` ADD COLUMN `available_type` VARCHAR(20) DEFAULT 'physical' AFTER `size`;

-- Add available_type column to products table
ALTER TABLE `products` ADD COLUMN `available_type` VARCHAR(20) DEFAULT 'physical' AFTER `is_active`;

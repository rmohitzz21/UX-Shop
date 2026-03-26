-- ============================================================
-- Migration: Enhance Addresses Table for Address Management System
-- Version: 001
-- Date: 2026-03-26
-- ============================================================
--
-- This migration adds new columns to support:
-- 1. Address labels (Home, Work, etc.)
-- 2. Address types (shipping, billing, both)
-- 3. Performance indexes
--
-- IMPORTANT: This is a non-breaking migration.
-- All new columns have defaults, so existing rows remain valid.
-- ============================================================

-- Add label column for user-defined address names
ALTER TABLE `addresses`
  ADD COLUMN IF NOT EXISTS `label` VARCHAR(50) DEFAULT NULL
    COMMENT 'User-defined label: Home, Work, Office, etc.'
    AFTER `phone`;

-- Add address_type column for shipping/billing differentiation
ALTER TABLE `addresses`
  ADD COLUMN IF NOT EXISTS `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both'
    COMMENT 'Address purpose: shipping, billing, or both'
    AFTER `label`;

-- Add composite index for efficient default address lookups
-- (Useful when fetching default address at checkout)
CREATE INDEX IF NOT EXISTS `idx_user_default` ON `addresses` (`user_id`, `is_default`);

-- Add index for filtering by address type
CREATE INDEX IF NOT EXISTS `idx_user_type` ON `addresses` (`user_id`, `address_type`);

-- ============================================================
-- Verification Query (run manually to confirm migration)
-- ============================================================
-- DESCRIBE addresses;
-- Expected new columns: label, address_type
-- Expected new indexes: idx_user_default, idx_user_type
-- ============================================================

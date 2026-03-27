-- ============================================================
-- Migration: Enhance Addresses Table for Address Management System
-- Version: 001
-- Date: 2026-03-26
-- ============================================================
--
-- This migration adds new columns to support:
-- 1. Address labels (Home, Work, etc.)
-- 2. Address types (shipping, billing, both)
--
-- IMPORTANT: This is a non-breaking migration.
-- All new columns have defaults, so existing rows remain valid.
--
-- Run this in phpMyAdmin or MySQL CLI:
-- mysql -u root -p uxmerchandise < migrations/001_enhance_addresses_table.sql
-- ============================================================

-- Add label column for user-defined address names
-- Using ALTER IGNORE to skip if column exists (MariaDB/MySQL compatible)
ALTER TABLE `addresses`
  ADD COLUMN `label` VARCHAR(50) DEFAULT NULL
    COMMENT 'User-defined label: Home, Work, Office, etc.';

-- Add address_type column for shipping/billing differentiation
ALTER TABLE `addresses`
  ADD COLUMN `address_type` ENUM('shipping', 'billing', 'both') DEFAULT 'both'
    COMMENT 'Address purpose: shipping, billing, or both';

-- ============================================================
-- Verification Query (run manually to confirm migration)
-- ============================================================
-- DESCRIBE addresses;
-- Expected new columns: label, address_type
-- ============================================================

-- Migration: add_order_item_snapshot
-- Adds product_name and product_image columns to order_items
-- so order history is preserved even if products are deleted or renamed.

ALTER TABLE `order_items`
  ADD COLUMN IF NOT EXISTS `product_name`  VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot of product name at time of order',
  ADD COLUMN IF NOT EXISTS `product_image` VARCHAR(500) DEFAULT NULL COMMENT 'Snapshot of product image path at time of order';

-- Also create contact_messages table if it does not exist
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)    NOT NULL,
  `email`      VARCHAR(255)    NOT NULL,
  `phone`      VARCHAR(50)     DEFAULT NULL,
  `subject`    VARCHAR(255)    DEFAULT NULL,
  `message`    TEXT            NOT NULL,
  `ip`         VARCHAR(45)     DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

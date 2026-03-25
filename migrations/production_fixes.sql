-- =============================================================================
-- UX Pacific Merchandise — Production Fixes Migration
-- Run once before production deployment
-- =============================================================================

-- ── PERF-04: Add missing indexes ─────────────────────────────────────────────

ALTER TABLE orders
    ADD INDEX IF NOT EXISTS idx_orders_user_id  (user_id),
    ADD INDEX IF NOT EXISTS idx_orders_status   (status),
    ADD INDEX IF NOT EXISTS idx_orders_created  (created_at);

ALTER TABLE order_items
    ADD INDEX IF NOT EXISTS idx_items_order_id   (order_id),
    ADD INDEX IF NOT EXISTS idx_items_product_id (product_id);

ALTER TABLE cart
    ADD INDEX IF NOT EXISTS idx_cart_user_id (user_id);

ALTER TABLE password_reset_tokens
    ADD INDEX IF NOT EXISTS idx_prt_expires (expires_at);

-- ── DB-04: Add updated_at to orders ──────────────────────────────────────────

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS updated_at
        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;

-- ── DB-02: FK on password_reset_tokens.user_id ───────────────────────────────

-- Drop existing index if it was added without FK, then re-add as FK
ALTER TABLE password_reset_tokens
    DROP INDEX IF EXISTS idx_prt_user_id;

ALTER TABLE password_reset_tokens
    ADD CONSTRAINT fk_prt_user
    FOREIGN KEY IF NOT EXISTS (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE;

-- ── DB-03: order_items FK — allow product deletion without breaking history ───

ALTER TABLE order_items
    DROP FOREIGN KEY IF EXISTS order_items_ibfk_1;  -- adjust name if different

ALTER TABLE order_items
    ADD CONSTRAINT fk_items_product
    FOREIGN KEY IF NOT EXISTS (product_id)
    REFERENCES products(id)
    ON DELETE SET NULL;

-- ── Razorpay: payment tracking columns on orders ─────────────────────────────

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_id         VARCHAR(100) NULL DEFAULT NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS razorpay_order_id  VARCHAR(100) NULL DEFAULT NULL AFTER payment_id;

-- ── DB-08: Soft delete — add deleted_at to products ──────────────────────────

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD INDEX IF NOT EXISTS idx_products_deleted (deleted_at);

-- Update all list/active queries to include: AND deleted_at IS NULL

-- ── DB-07: Fix product name typo ──────────────────────────────────────────────

UPDATE products SET name = 'Website Template' WHERE id = 31 AND name = 'Webiste Template';

-- ── DB-05: Remove test data ───────────────────────────────────────────────────

DELETE FROM users    WHERE email = 'ts_admin@test.uxpacific.local';
DELETE FROM products WHERE name  = 'TS_Product_setup';

-- ── DB-01: product_type column (already absent in current schema — no-op) ────
-- ALTER TABLE products DROP COLUMN IF EXISTS product_type;

-- ── Recommended: Create restricted application DB user ────────────────────────
-- Run this as root, then update .env with DB_USER=ux_app and DB_PASS=<password>
--
-- CREATE USER IF NOT EXISTS 'ux_app'@'localhost' IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON uxmerchandise.* TO 'ux_app'@'localhost';
-- FLUSH PRIVILEGES;

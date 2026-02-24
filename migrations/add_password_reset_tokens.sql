-- Migration: add_password_reset_tokens
-- Creates the table used by the real forgot-password flow

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NOT NULL,
  `token_hash` VARCHAR(255)    NOT NULL COMMENT 'SHA-256 hash of the raw token',
  `expires_at` DATETIME        NOT NULL,
  `used_at`    DATETIME        DEFAULT NULL COMMENT 'Set when token is consumed',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user_id`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

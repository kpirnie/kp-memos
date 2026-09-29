-- ---------------------------------------------------------------------------
-- KP Memos Schema
--
-- Every table the application uses. The application account never touches
-- these directly; it only holds EXECUTE on the kpm_ stored procedures.
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

-- pin the schema's default collation to match the tables. procedure parameters and
-- variables inherit the database default, and newer mariadb defaults
-- (utf8mb4_uca1400_ai_ci) can't be compared with the tables' utf8mb4_unicode_ci
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- installation metadata
CREATE TABLE IF NOT EXISTS `kpm_meta` (
    `meta_key` VARCHAR(64) NOT NULL,
    `meta_value` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user accounts
CREATE TABLE IF NOT EXISTS `kpm_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(64) NOT NULL,
    `display_name` VARCHAR(128) NOT NULL DEFAULT '',
    `email` VARCHAR(255) NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
    `totp_secret` VARCHAR(255) NULL DEFAULT NULL,
    `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `totp_last_step` BIGINT UNSIGNED NULL DEFAULT NULL,
    `session_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kpm_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- single-use totp recovery codes, stored as keyed hashes
CREATE TABLE IF NOT EXISTS `kpm_recovery_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` CHAR(64) NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_kpm_recovery_codes_user` (`user_id`, `code_hash`),
    CONSTRAINT `fk_kpm_recovery_codes_user` FOREIGN KEY (`user_id`) REFERENCES `kpm_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- security audit trail
CREATE TABLE IF NOT EXISTS `kpm_audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `event` VARCHAR(64) NOT NULL,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `detail` VARCHAR(1024) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_kpm_audit_log_user` (`user_id`, `created_at`),
    KEY `ix_kpm_audit_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- nested note categories, per user
CREATE TABLE IF NOT EXISTS `kpm_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_kpm_categories_user` (`user_id`, `parent_id`),
    KEY `ix_kpm_categories_parent` (`parent_id`),
    CONSTRAINT `fk_kpm_categories_user` FOREIGN KEY (`user_id`) REFERENCES `kpm_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kpm_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `kpm_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- note tags, per user
CREATE TABLE IF NOT EXISTS `kpm_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kpm_tags_user_name` (`user_id`, `name`),
    CONSTRAINT `fk_kpm_tags_user` FOREIGN KEY (`user_id`) REFERENCES `kpm_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notes
CREATE TABLE IF NOT EXISTS `kpm_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `body_html` MEDIUMTEXT NOT NULL,
    `body_text` MEDIUMTEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `pin_order` INT NOT NULL DEFAULT 0,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `share_token` VARCHAR(64) NULL DEFAULT NULL,
    `share_password_hash` VARCHAR(255) NULL DEFAULT NULL,
    `share_expires_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kpm_notes_share_token` (`share_token`),
    KEY `ix_kpm_notes_user_updated` (`user_id`, `updated_at`),
    KEY `ix_kpm_notes_user_created` (`user_id`, `created_at`),
    KEY `ix_kpm_notes_user_pinned` (`user_id`, `is_pinned`, `pin_order`),
    FULLTEXT KEY `ft_kpm_notes_search` (`title`, `body_text`),
    CONSTRAINT `fk_kpm_notes_user` FOREIGN KEY (`user_id`) REFERENCES `kpm_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- note to category links
CREATE TABLE IF NOT EXISTS `kpm_note_categories` (
    `note_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`note_id`, `category_id`),
    KEY `ix_kpm_note_categories_category` (`category_id`),
    CONSTRAINT `fk_kpm_note_categories_note` FOREIGN KEY (`note_id`) REFERENCES `kpm_notes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kpm_note_categories_category` FOREIGN KEY (`category_id`) REFERENCES `kpm_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- note to tag links
CREATE TABLE IF NOT EXISTS `kpm_note_tags` (
    `note_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`note_id`, `tag_id`),
    KEY `ix_kpm_note_tags_tag` (`tag_id`),
    CONSTRAINT `fk_kpm_note_tags_note` FOREIGN KEY (`note_id`) REFERENCES `kpm_notes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kpm_note_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `kpm_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- note attachments; the files live in storage/attachments under stored_name
CREATE TABLE IF NOT EXISTS `kpm_attachments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `note_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` CHAR(64) NOT NULL,
    `mime_type` VARCHAR(127) NOT NULL DEFAULT 'application/octet-stream',
    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sha256` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kpm_attachments_stored` (`stored_name`),
    KEY `ix_kpm_attachments_note` (`note_id`),
    KEY `ix_kpm_attachments_user` (`user_id`),
    CONSTRAINT `fk_kpm_attachments_note` FOREIGN KEY (`note_id`) REFERENCES `kpm_notes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kpm_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `kpm_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- record the schema version
INSERT INTO `kpm_meta` (`meta_key`, `meta_value`) VALUES ('schema_version', '1')
    ON DUPLICATE KEY UPDATE `meta_value` = VALUES(`meta_value`);

-- Event registration packages (named promotions with own price and member rules)

CREATE TABLE IF NOT EXISTS `event_promotions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` INT UNSIGNED NOT NULL,
    `slug` VARCHAR(64) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `registration_mode` ENUM('individual', 'group_flat', 'group_per_head') NOT NULL DEFAULT 'group_flat',
    `member_min` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `member_max` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `require_all_members` TINYINT(1) NOT NULL DEFAULT 0,
    `package_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `compare_at_price` DECIMAL(10,2) NULL DEFAULT NULL,
    `pricing_config` JSON NULL,
    `valid_from` DATETIME NULL DEFAULT NULL,
    `valid_until` DATETIME NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_slug` (`event_id`, `slug`),
    KEY `idx_event_active` (`event_id`, `is_active`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `event_registration`
    ADD COLUMN `event_promotion_id` INT UNSIGNED NULL DEFAULT NULL AFTER `promo_id`,
    ADD KEY `idx_event_promotion_id` (`event_promotion_id`);

ALTER TABLE `event_registration_pendings`
    ADD COLUMN `event_promotion_id` INT UNSIGNED NULL DEFAULT NULL AFTER `promo_id`,
    ADD KEY `idx_event_promotion_id` (`event_promotion_id`);

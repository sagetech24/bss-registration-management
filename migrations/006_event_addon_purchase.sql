-- Post-registration guest add-on purchases (separate checkout per batch)

CREATE TABLE IF NOT EXISTS `event_addon_purchase` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_id` INT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    `confirmation_number` VARCHAR(32) NOT NULL,
    `guest_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending', 'paid', 'failed', 'free') NOT NULL DEFAULT 'pending',
    `payment_request_id` VARCHAR(64) NULL DEFAULT NULL,
    `payment_option` VARCHAR(32) NOT NULL DEFAULT 'N/A',
    `guest_payload` JSON NOT NULL,
    `pricing_snapshot` JSON NULL,
    `form_schema_snapshot` JSON NULL,
    `is_email_confirmation_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_registration_id` (`registration_id`),
    KEY `idx_event_id` (`event_id`),
    KEY `idx_payment_request_id` (`payment_request_id`),
    KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

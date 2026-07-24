-- Soft-delete support for promotion packages (keep rows referenced by registrations)
ALTER TABLE `event_promotions`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD KEY `idx_event_deleted` (`event_id`, `deleted_at`);
